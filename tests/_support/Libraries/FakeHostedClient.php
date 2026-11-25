<?php

namespace Tests\Support\Libraries;

use App\Libraries\VendorGiftClients\HostedCheckoutClientInterface;
use App\Libraries\VendorGiftClients\VendorGiftClientInterface;
use RuntimeException;

/**
 * A hosted-checkout vendor that answers from a script: the gifts it "has", a chosen answer for each question, and
 * a record of every checkout it was asked to open. Nothing leaves the machine.
 */
class FakeHostedClient implements VendorGiftClientInterface, HostedCheckoutClientInterface
{
    /** @var array<int, array{items: array, key: string, returnUrl: ?string}> */
    public array $sessions = [];

    /** @var array<int, string> */
    public array $statusCalls = [];

    /** @var array<int, string> */
    public array $orderCalls = [];

    /**
     * @param array<string, array<string, mixed>> $gifts   vendor_product_ref => the gift as fetchGift() returns it
     * @param array<string, mixed>|null           $session what createCheckoutSession() returns (null: a good one)
     * @param array<int, array<string, mixed>>    $sessionQueue answers to give first, one per call (e.g. a timeout, then success)
     * @param array<string, mixed>|null           $status  what checkoutSession() returns (null: awaiting payment)
     * @param array<string, mixed>|null           $order   what order() returns (null: a paid order with one delivery)
     */
    public function __construct(
        public array $gifts = [],
        public ?array $session = null,
        public array $sessionQueue = [],
        public ?array $status = null,
        public ?array $order = null,
        public ?string $failGiftWith = null,
    ) {
    }

    /**
     * A gift as the vendor reports it.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function gift(string $ref, array $overrides = []): array
    {
        return $overrides + [
            'vendor_product_ref'     => $ref,
            'name'                   => "Gift {$ref}",
            'description'            => null,
            'price'                  => 45000.0,
            'currency'               => 'UGX',
            'image_url'              => null,
            'minimum_delivery_hours' => 24,
            'stock'                  => 10,
            'inventories'            => [['id' => (string) ($ref + 1000), 'quantity' => 10]],
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function ok(array $data): array
    {
        return ['success' => true, 'status' => 200, 'message' => '', 'errors' => [], 'retryable' => false, 'data' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public static function failure(int $status, string $message, bool $retryable = false): array
    {
        return ['success' => false, 'status' => $status, 'message' => $message, 'errors' => [], 'retryable' => $retryable, 'data' => []];
    }

    public function fetchCatalog(): array
    {
        return [];
    }

    public function submitOrder(array $orderData): array
    {
        return ['success' => false, 'vendor_order_ref' => null, 'message' => 'Hosted checkout only.'];
    }

    public function fetchGift(string $vendorProductRef): ?array
    {
        if ($this->failGiftWith !== null) {
            throw new RuntimeException($this->failGiftWith);
        }

        return $this->gifts[$vendorProductRef] ?? null;
    }

    public function createCheckoutSession(array $items, string $idempotencyKey, ?string $returnUrl = null): array
    {
        $this->sessions[] = ['items' => $items, 'key' => $idempotencyKey, 'returnUrl' => $returnUrl];

        if ($this->sessionQueue !== []) {
            return array_shift($this->sessionQueue);
        }

        return $this->session ?? self::ok([
            'session_id'   => '9b0f7ac0-34e8-4f2c-8d27-1f5c9e6a023b',
            'checkout_url' => 'https://kataleplus.example.test/partner-checkout/TOKEN',
            'expires_at'   => '2026-12-25 13:30:00',
            'currency'     => 'UGX',
        ]);
    }

    public function checkoutSession(string $sessionId): array
    {
        $this->statusCalls[] = $sessionId;

        return $this->status ?? self::ok([
            'state' => 'awaiting_payment', 'paid' => false, 'order_id' => null, 'reference' => null,
            'amount' => null, 'currency' => 'UGX', 'expires_at' => '2026-12-25 13:30:00',
        ]);
    }

    public function order(string $orderId): array
    {
        $this->orderCalls[] = $orderId;

        return $this->order ?? self::ok([
            'id' => $orderId, 'reference' => 'KATALE-REFERENCE', 'amount' => 170000.0, 'currency' => 'UGX', 'payment_method' => 'stripe',
            'paid' => true, 'cancelled' => false, 'status' => 1,
            'deliveries' => [['title' => 'Flower Bouquet', 'quantity' => 2, 'requested_delivery_at' => '2026-12-26 14:00', 'minimum_delivery_hours' => 24, 'state' => 'pending']],
        ]);
    }
}
