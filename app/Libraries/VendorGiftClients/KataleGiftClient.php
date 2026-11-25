<?php

namespace App\Libraries\VendorGiftClients;

use Config\Services as CoreServices;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Katale Plus, through its Partner Gifts API (guide revision 1.2, API v1).
 *
 *   catalogue   GET  /gifts, paginated: mirrored into our gifts table by GiftVendorSyncService
 *   one gift    GET  /gifts/{id}: its inventories and what is left, read again just before checkout
 *   checkout    POST /checkout-sessions (Idempotency-Key header): the customer is sent to the returned address,
 *               chooses delivery and pays on Katale
 *   status      GET  /checkout-sessions/{id}, then GET /orders/{id} for delivery progress
 *
 * The partner key is a secret and lives in .env as vendor.{slug}.apiKey (slug from the vendor row), never in the
 * database. It is sent only in the Authorization header and never appears in a message, a log line or a result.
 *
 * Katale sets the final price, collects the address and the delivery dates, and takes payment: none of that is
 * done here. Prices we show are therefore estimates, and "paid" is only ever what Katale's status endpoint says.
 */
class KataleGiftClient implements VendorGiftClientInterface, HostedCheckoutClientInterface
{
    public const DEFAULT_BASE_URL = 'https://kataleplus.com/api/v1/partner';

    private const PER_PAGE       = 48;
    private const MAX_PAGES      = 100; // a stop for a catalogue that never says it has ended
    private const TIMEOUT        = 15;
    private const CONNECT_TIMEOUT = 5;

    /** @var array<string, mixed> */
    protected array $vendor;

    /**
     * @param array<string, mixed> $vendor a gift_vendors row
     */
    public function __construct(array $vendor)
    {
        $this->vendor = $vendor;
    }

    // ------------------------------------------------------------------ the catalogue

    public function fetchCatalog(): array
    {
        $catalog = [];
        $page    = 1;
        $last    = 1;

        do {
            $answer = $this->call('GET', '/gifts', ['query' => ['per_page' => self::PER_PAGE, 'page' => $page]]);

            if ($answer['status'] !== 200) {
                // Thrown, not swallowed: a half-read catalogue must show up as a failed sync, not as a short one.
                throw new RuntimeException($this->failureMessage($answer, 'Katale did not return its catalogue'));
            }

            foreach ((array) ($answer['body']['data'] ?? []) as $row) {
                $gift = is_array($row) ? $this->normalizeGift($row) : null;

                if ($gift !== null) {
                    $catalog[] = $gift;
                }
            }

            $last = max(1, (int) ($answer['body']['last_page'] ?? 1));
            $page++;
        } while ($page <= $last && $page <= self::MAX_PAGES);

        return $catalog;
    }

    public function fetchGift(string $vendorProductRef): ?array
    {
        $answer = $this->call('GET', '/gifts/' . rawurlencode($vendorProductRef));

        if ($answer['status'] === 404) {
            return null;
        }

        if ($answer['status'] !== 200) {
            throw new RuntimeException($this->failureMessage($answer, 'Katale could not be asked about this gift'));
        }

        $row = $answer['body']['data'] ?? null;

        return is_array($row) ? $this->normalizeGift($row, true) : null;
    }

    // ------------------------------------------------------------------ the checkout

    public function createCheckoutSession(array $items, string $idempotencyKey, ?string $returnUrl = null): array
    {
        $body = ['items' => array_values(array_map(static fn (array $item): array => [
            'gift_id'      => (int) $item['gift_id'],
            'inventory_id' => (int) $item['inventory_id'],
            'quantity'     => (int) $item['quantity'],
        ], $items))];

        if ($returnUrl !== null && $returnUrl !== '') {
            $body['return_url'] = $returnUrl;
        }

        $answer = $this->call('POST', '/checkout-sessions', [
            'json'    => $body,
            'headers' => ['Idempotency-Key' => $idempotencyKey],
        ]);

        $data = $answer['body']['data'] ?? null;

        if (! in_array($answer['status'], [200, 201], true) || ! is_array($data) || empty($data['id']) || empty($data['checkout_url'])) {
            return $this->failed($answer, 'Katale could not start the checkout');
        }

        return $this->succeeded($answer, [
            'session_id'   => (string) $data['id'],
            'checkout_url' => (string) $data['checkout_url'],
            'expires_at'   => $this->localTime($data['expires_at'] ?? null),
            'currency'     => strtoupper((string) ($data['currency'] ?? 'UGX')),
        ]);
    }

    public function checkoutSession(string $sessionId): array
    {
        $answer = $this->call('GET', '/checkout-sessions/' . rawurlencode($sessionId));
        $data   = $answer['body']['data'] ?? null;

        if ($answer['status'] !== 200 || ! is_array($data)) {
            return $this->failed($answer, 'Katale could not report on this checkout');
        }

        return $this->succeeded($answer, [
            'state'      => (string) ($data['state'] ?? ''),
            'paid'       => (bool) ($data['paid'] ?? false),
            'order_id'   => isset($data['order_id']) ? (string) $data['order_id'] : null,
            'reference'  => isset($data['reference']) ? (string) $data['reference'] : null,
            'amount'     => isset($data['amount']) && is_numeric($data['amount']) ? (float) $data['amount'] : null,
            'currency'   => isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
            'expires_at' => $this->localTime($data['expires_at'] ?? null),
        ]);
    }

    public function order(string $orderId): array
    {
        $answer = $this->call('GET', '/orders/' . rawurlencode($orderId));
        $data   = $answer['body']['data'] ?? null;

        if ($answer['status'] !== 200 || ! is_array($data)) {
            return $this->failed($answer, 'Katale could not report on this order');
        }

        $deliveries = [];

        foreach ((array) ($data['deliveries'] ?? []) as $delivery) {
            if (! is_array($delivery)) {
                continue;
            }

            $deliveries[] = [
                'title'                  => trim(strip_tags((string) ($delivery['title'] ?? ''))),
                'quantity'               => (int) ($delivery['quantity'] ?? 0),
                'requested_delivery_at'  => $this->localTime($delivery['requested_delivery_at'] ?? null, true),
                'minimum_delivery_hours' => isset($delivery['minimum_delivery_hours']) ? (int) $delivery['minimum_delivery_hours'] : null,
                'state'                  => (string) ($delivery['state'] ?? 'pending'),
            ];
        }

        return $this->succeeded($answer, [
            'id'             => (string) ($data['id'] ?? $orderId),
            'reference'      => isset($data['reference']) ? (string) $data['reference'] : null,
            'amount'         => isset($data['amount']) && is_numeric($data['amount']) ? (float) $data['amount'] : null,
            'currency'       => isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
            'payment_method' => isset($data['payment_method']) ? (string) $data['payment_method'] : null,
            'paid'           => (bool) ($data['paid'] ?? false),
            'cancelled'      => (bool) ($data['cancelled'] ?? false),
            'status'         => (int) ($data['status'] ?? 0),
            'deliveries'     => $deliveries,
        ]);
    }

    /**
     * Katale takes the order from the customer, not from us, so there is nothing to submit once a gift is paid.
     */
    public function submitOrder(array $orderData): array
    {
        return [
            'success'          => false,
            'vendor_order_ref' => null,
            'message'          => 'Katale gifts are bought through the gift cart and paid on Katale; they are not placed from here.',
        ];
    }

    // ------------------------------------------------------------------ the wire

    /**
     * One request. Returns the status (0 when Katale could not be reached, -1 when no partner key is set so
     * nothing was sent), the decoded body and any Retry-After.
     * Katale's own error statuses are returned, not thrown, so each caller can treat a 404 or 409 as it should.
     *
     * @param array{query?: array<string, mixed>, json?: array<string, mixed>, headers?: array<string, string>} $options
     *
     * @return array{status: int, body: array<string, mixed>, retryAfter: ?int}
     */
    protected function call(string $method, string $path, array $options = []): array
    {
        $apiKey = (string) env('vendor.' . ($this->vendor['slug'] ?? '') . '.apiKey');

        if ($apiKey === '') {
            return ['status' => -1, 'body' => [], 'retryAfter' => null]; // -1: not set up, nothing was sent
        }

        $headers = ['Authorization' => 'Bearer ' . $apiKey, 'Accept' => 'application/json'] + ($options['headers'] ?? []);
        $request = [
            'headers'         => $headers,
            'http_errors'     => false,
            'timeout'         => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
        ];

        if (isset($options['query'])) {
            $request['query'] = $options['query'];
        }

        if (isset($options['json'])) {
            $request['json']                  = $options['json'];
            $request['headers']['Content-Type'] = 'application/json';
        }

        try {
            $response = CoreServices::curlrequest()->request($method, $this->baseUrl() . $path, $request);
        } catch (Throwable $e) {
            // Never the exception's own text: it can carry the address and, in some versions, the headers.
            log_message('error', 'Katale request failed ({method} {path}): {class}', ['method' => $method, 'path' => $path, 'class' => $e::class]);

            return ['status' => 0, 'body' => [], 'retryAfter' => null];
        }

        $decoded    = json_decode((string) $response->getBody(), true);
        $retryAfter = $response->getHeaderLine('Retry-After');

        return [
            'status'     => (int) $response->getStatusCode(),
            'body'       => is_array($decoded) ? $decoded : [],
            'retryAfter' => ctype_digit($retryAfter) ? (int) $retryAfter : null,
        ];
    }

    protected function baseUrl(): string
    {
        $base = trim((string) ($this->vendor['api_base_url'] ?? ''));

        return rtrim($base !== '' ? $base : self::DEFAULT_BASE_URL, '/');
    }

    /**
     * The site that serves the pictures: the scheme and host of the API address.
     */
    protected function origin(): string
    {
        $parts = parse_url($this->baseUrl());

        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'kataleplus.com')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    // ------------------------------------------------------------------ reading what Katale sends

    /**
     * One gift in our shape, or null when it cannot be sold as listed (no id, no title, no positive price).
     * The price is the offered one when Katale shows a positive offer, otherwise the selling price.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>|null
     */
    protected function normalizeGift(array $row, bool $keepUnpriced = false): ?array
    {
        $title = trim(strip_tags((string) ($row['title'] ?? '')));

        if (empty($row['id']) || $title === '') {
            return null;
        }

        $offered = is_numeric($row['offered'] ?? null) ? (float) $row['offered'] : 0.0;
        $selling = is_numeric($row['selling'] ?? null) ? (float) $row['selling'] : 0.0;
        $price   = $offered > 0 ? $offered : $selling;

        if ($price <= 0 && ! $keepUnpriced) {
            return null;
        }

        $inventories = [];

        foreach ((array) ($row['inventories'] ?? []) as $inventory) {
            if (is_array($inventory) && ! empty($inventory['id'])) {
                $inventories[] = ['id' => (string) $inventory['id'], 'quantity' => max(0, (int) ($inventory['quantity'] ?? 0))];
            }
        }

        $description = trim(strip_tags((string) ($row['description'] ?? '')));
        $hours       = $row['minimum_delivery_hours'] ?? null;

        return [
            'vendor_product_ref'     => (string) $row['id'],
            'name'                   => mb_substr($title, 0, 150),
            'description'            => $description !== '' ? $description : null,
            'price'                  => $price,
            'currency'               => strtoupper(substr((string) ($row['currency'] ?? 'UGX'), 0, 3)) ?: 'UGX',
            // Katale's catalogue is gifts for delivery (flowers, cakes, hampers), which is what 'physical' means here.
            'type'                   => 'physical',
            'image_url'              => $this->imageUrl($row['image'] ?? null),
            'minimum_delivery_hours' => is_numeric($hours) && (int) $hours >= 0 ? (int) $hours : null,
            'stock'                  => array_sum(array_column($inventories, 'quantity')),
            'inventories'            => $inventories,
        ];
    }

    /**
     * An absolute https address is used as given. A file name (with or without a leading slash or "uploads/") is
     * appended to the public uploads address. Anything else, including plain http, is dropped: a missing picture
     * is better than a page that loads something insecure.
     */
    protected function imageUrl(mixed $image): ?string
    {
        $image = trim((string) $image);

        if ($image === '') {
            return null;
        }

        if (preg_match('#^https://#i', $image) === 1) {
            return mb_substr($image, 0, 500);
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $image) === 1) {
            return null;
        }

        $name = preg_replace('#^/?(uploads/)?#', '', ltrim($image, '/')) ?? '';

        if ($name === '' || str_contains($name, '..')) {
            return null;
        }

        $path = implode('/', array_map('rawurlencode', explode('/', $name)));

        return mb_substr($this->origin() . '/uploads/' . $path, 0, 500);
    }

    /**
     * Katale's timestamps either carry an offset or are UTC. They are shown in the station's timezone.
     *
     * @param bool $short drop the seconds (for a delivery slot)
     */
    protected function localTime(mixed $value, bool $short = false): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            $time = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $time = $time->setTimezone(new DateTimeZone(config('App')->appTimezone));
        } catch (Throwable) {
            return null;
        }

        return $time->format($short ? 'Y-m-d H:i' : 'Y-m-d H:i:s');
    }

    // ------------------------------------------------------------------ results

    /**
     * @param array{status: int, body: array<string, mixed>, retryAfter: ?int} $answer
     * @param array<string, mixed>                                            $data
     */
    private function succeeded(array $answer, array $data): array
    {
        return ['success' => true, 'status' => $answer['status'], 'message' => '', 'errors' => [], 'retryable' => false, 'data' => $data];
    }

    /**
     * @param array{status: int, body: array<string, mixed>, retryAfter: ?int} $answer
     */
    private function failed(array $answer, string $fallback): array
    {
        $status = $answer['status'];
        $errors = [];

        foreach ((array) ($answer['body']['errors'] ?? []) as $field => $messages) {
            $errors[(string) $field] = array_values(array_map('strval', (array) $messages));
        }

        return [
            'success'   => false,
            'status'    => $status,
            'message'   => $this->failureMessage($answer, $fallback),
            'errors'    => $errors,
            // A timeout, a busy vendor or one that is failing may answer next time. A refusal will not.
            'retryable' => $status === 0 || $status === 429 || $status >= 500,
            'data'      => [],
        ];
    }

    /**
     * What to tell a visitor. Katale's own validation message is used for a refused cart (the guide says to show
     * it); for everything else a plain sentence, since a raw vendor error means nothing to a visitor.
     *
     * @param array{status: int, body: array<string, mixed>, retryAfter: ?int} $answer
     */
    private function failureMessage(array $answer, string $fallback): string
    {
        $vendorMessage = trim(strip_tags((string) ($answer['body']['message'] ?? '')));

        return match (true) {
            $answer['status'] === -1  => 'Katale is not set up: add vendor.' . ($this->vendor['slug'] ?? '{slug}') . '.apiKey to .env.',
            $answer['status'] === 0   => $fallback . ': it could not be reached.',
            $answer['status'] === 401 => $fallback . ': the partner key was refused. Check vendor.' . ($this->vendor['slug'] ?? '{slug}') . '.apiKey.',
            $answer['status'] === 404 => $fallback . ': it is no longer available.',
            $answer['status'] === 409 => $fallback . ': ' . ($vendorMessage !== '' ? $vendorMessage : 'the request conflicts with an earlier one.'),
            $answer['status'] === 410 => $fallback . ': the checkout has expired.',
            $answer['status'] === 422 => $vendorMessage !== '' ? $vendorMessage : $fallback . ': the cart was refused.',
            $answer['status'] === 429 => $fallback . ': too many requests; try again in ' . ($answer['retryAfter'] ?? 60) . ' seconds.',
            default                   => $fallback . ' (status ' . $answer['status'] . ').',
        };
    }
}
