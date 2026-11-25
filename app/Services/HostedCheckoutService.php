<?php

namespace App\Services;

use App\Libraries\VendorGiftClients\HostedCheckoutClientInterface;
use App\Libraries\VendorGiftClients\VendorClients;
use App\Models\GiftModel;
use App\Models\GiftVendorModel;
use App\Models\VendorCheckoutModel;
use DateTimeImmutable;
use Throwable;

/**
 * Buying gifts through a vendor's hosted checkout (Katale Plus). The visitor's cart is checked against the vendor
 * right now, sent to the vendor as a checkout session, and the visitor is sent to the vendor's page to give
 * delivery details and pay. We never take the money, so the only way we learn a purchase was paid is by asking the
 * vendor: status() does that for one purchase, reconcile() for every purchase still moving.
 *
 * Money rule: a purchase is paid when the vendor's status endpoint says so, and never because a visitor came back
 * to our site, or has an order id. Once paid it is never put back to unpaid by a later answer (only to cancelled).
 */
class HostedCheckoutService
{
    /** The shortest gap between two questions about one purchase, so that a refreshing page cannot flood the vendor. */
    public const MIN_SECONDS_BETWEEN_CHECKS = 8;

    protected GiftModel $giftModel;
    protected GiftVendorModel $giftVendorModel;
    protected VendorCheckoutModel $checkoutModel;

    public function __construct(
        ?GiftModel $giftModel = null,
        ?GiftVendorModel $giftVendorModel = null,
        ?VendorCheckoutModel $checkoutModel = null
    ) {
        $this->giftModel       = $giftModel ?? new GiftModel();
        $this->giftVendorModel = $giftVendorModel ?? new GiftVendorModel();
        $this->checkoutModel   = $checkoutModel ?? new VendorCheckoutModel();
    }

    /**
     * The client for one vendor. A method of its own so that a test can supply a fake, instead of the service
     * reaching out to a real vendor's servers.
     */
    protected function hostedClient(array $vendor): HostedCheckoutClientInterface
    {
        return VendorClients::hosted($vendor);
    }

    // ------------------------------------------------------------------ starting a checkout

    /**
     * Checks the cart with the vendor and opens a checkout for it.
     *
     * @param array<int, int> $cart       gift id => quantity
     * @param callable|null   $returnUrl  given the purchase's public token, returns where the vendor should send
     *                                    the visitor back to (used only when it is an https address)
     *
     * @return array{success: bool, message: string, checkout: ?array}
     */
    public function start(array $cart, ?callable $returnUrl = null): array
    {
        if ($cart === []) {
            return $this->refused('Your cart is empty.');
        }

        $gifts = $this->giftModel->hostedByIds(array_keys($cart));

        foreach (array_keys($cart) as $giftId) {
            if (! isset($gifts[$giftId])) {
                return $this->refused('A gift in your cart is no longer available. Please remove it and try again.');
            }
        }

        $vendorIds = array_unique(array_map(static fn (array $gift): int => (int) $gift['vendor_id'], $gifts));

        if (count($vendorIds) !== 1) {
            return $this->refused('Gifts from different shops are paid separately. Please check out one shop at a time.');
        }

        $vendor = $this->giftVendorModel->find($vendorIds[0]);

        if ($vendor === null || ! GiftVendorModel::isHosted($vendor)) {
            return $this->refused('This shop is not taking orders right now.');
        }

        $client = $this->hostedClient($vendor);
        $items  = [];
        $lines  = [];
        $total  = 0.0;
        $currencies = [];

        foreach ($cart as $giftId => $quantity) {
            $gift = $gifts[$giftId];

            try {
                $fresh = $client->fetchGift((string) $gift['vendor_product_ref']);
            } catch (Throwable $e) {
                log_message('warning', 'Hosted checkout: could not read gift {gift} from {vendor}: {error}', [
                    'gift' => $giftId, 'vendor' => $vendor['slug'], 'error' => $e->getMessage(),
                ]);

                return $this->refused('We could not check the stock with the shop just now. Please try again in a minute.');
            }

            if ($fresh === null) {
                return $this->refused('"' . $gift['name'] . '" is no longer sold. Please remove it from your cart.');
            }

            $inventory = $this->inventoryFor($fresh['inventories'], (int) $quantity);

            if ($inventory === null) {
                $left = $fresh['inventories'] === [] ? 0 : max(array_column($fresh['inventories'], 'quantity'));

                return $this->refused($left > 0
                    ? 'Only ' . $left . ' of "' . $gift['name'] . '" left. Please lower the quantity.'
                    : '"' . $gift['name'] . '" is out of stock. Please remove it from your cart.');
            }

            if ($fresh['price'] <= 0) {
                return $this->refused('"' . $gift['name'] . '" cannot be ordered right now. Please remove it from your cart.');
            }

            $items[]      = ['gift_id' => (int) $fresh['vendor_product_ref'], 'inventory_id' => (int) $inventory['id'], 'quantity' => (int) $quantity];
            $currencies[] = $fresh['currency'];
            $lineTotal    = $fresh['price'] * $quantity;
            $total       += $lineTotal;
            $lines[]      = [
                'gift_id'      => (int) $fresh['vendor_product_ref'],
                'inventory_id' => (int) $inventory['id'],
                'quantity'     => (int) $quantity,
                'title'        => $fresh['name'],
                'unit_price'   => $fresh['price'],
                'line_total'   => $lineTotal,
            ];
        }

        if (count(array_unique($currencies)) !== 1) {
            return $this->refused('These gifts are priced in different currencies and cannot be paid together.');
        }

        $token    = bin2hex(random_bytes(16));
        $checkoutId = $this->checkoutModel->insert([
            'vendor_id'          => $vendor['id'],
            'public_token'       => $token,
            'purchase_reference' => 'CBSG-' . strtoupper(bin2hex(random_bytes(4))),
            'idempotency_key'    => 'cbsg-' . $token,
            'items'              => json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'estimate_total'     => round($total, 2),
            'currency'           => $currencies[0],
            'state'              => VendorCheckoutModel::STATE_DRAFT,
            'paid'               => 0,
        ]);

        if ($checkoutId === false) {
            return $this->refused('We could not start your order. Please try again.');
        }

        $checkout = $this->checkoutModel->find($checkoutId);
        $back     = $returnUrl !== null ? (string) $returnUrl($token) : '';

        // The vendor only accepts an https return address that matches the website registered for our key; on a
        // development machine (http) it is left out, and the vendor uses the registered one.
        $back = preg_match('#^https://#i', $back) === 1 ? $back : null;

        $result = $client->createCheckoutSession($items, $checkout['idempotency_key'], $back);

        if (! $result['success'] && $result['retryable']) {
            // Once, with the same key and the same cart: if the first attempt did reach the vendor, it answers
            // with that same session instead of opening a second one.
            $result = $client->createCheckoutSession($items, $checkout['idempotency_key'], $back);
        }

        $url = (string) ($result['data']['checkout_url'] ?? '');

        if ($result['success'] && preg_match('#^https://#i', $url) !== 1) {
            $result = ['success' => false, 'message' => 'The shop sent an address we cannot use.'] + $result;
        }

        if (! $result['success']) {
            $this->checkoutModel->update($checkoutId, [
                'state'      => VendorCheckoutModel::STATE_FAILED,
                'last_error' => mb_substr($result['message'] !== '' ? $result['message'] : 'The checkout could not be started.', 0, 500),
            ]);

            return $this->refused($result['message'] !== '' ? $result['message'] : 'The shop could not start your checkout. Please try again.', $this->checkoutModel->find($checkoutId));
        }

        $this->checkoutModel->update($checkoutId, [
            'state'        => VendorCheckoutModel::STATE_OPEN,
            'session_id'   => $result['data']['session_id'],
            'checkout_url' => $url,
            'expires_at'   => $result['data']['expires_at'] ?? null,
            'currency'     => $result['data']['currency'] ?? $currencies[0],
        ]);

        return ['success' => true, 'message' => 'Checkout started.', 'checkout' => $this->checkoutModel->find($checkoutId)];
    }

    /**
     * The inventory to buy from: one with enough units, the one with the most when several would do.
     *
     * @param array<int, array{id: string, quantity: int}> $inventories
     *
     * @return array{id: string, quantity: int}|null
     */
    protected function inventoryFor(array $inventories, int $quantity): ?array
    {
        $best = null;

        foreach ($inventories as $inventory) {
            if ($inventory['quantity'] >= $quantity && ($best === null || $inventory['quantity'] > $best['quantity'])) {
                $best = $inventory;
            }
        }

        return $best;
    }

    // ------------------------------------------------------------------ asking about a purchase

    /**
     * Brings a purchase up to date with what the vendor says, and returns it. Never throws and never makes a
     * purchase worse because the vendor could not be reached: a failed question leaves the purchase as it was.
     *
     * @param bool $force ask even if the purchase was asked about a moment ago (an admin pressing "check now")
     */
    public function status(array $checkout, bool $force = false): array
    {
        if (! $this->needsChecking($checkout) || empty($checkout['session_id'])) {
            return $checkout;
        }

        if (! $force && $this->askedRecently($checkout)) {
            return $checkout;
        }

        $vendor = $this->giftVendorModel->find($checkout['vendor_id']);

        if ($vendor === null) {
            return $checkout;
        }

        $client = $this->hostedClient($vendor);
        $result = $client->checkoutSession((string) $checkout['session_id']);
        $now    = date('Y-m-d H:i:s');
        $update = ['last_checked_at' => $now];

        if (! $result['success']) {
            if ($result['status'] === 410 && ! in_array($checkout['state'], [VendorCheckoutModel::STATE_PAID, VendorCheckoutModel::STATE_CANCELLED], true)) {
                // 410: an unused session that ran out. (A session with an order is reported by its state instead.)
                $update['state'] = VendorCheckoutModel::STATE_EXPIRED;
            } else {
                $update['last_error'] = mb_substr($result['message'], 0, 500);
            }

            $this->checkoutModel->update($checkout['id'], $update);

            return $this->checkoutModel->find($checkout['id']);
        }

        $data  = $result['data'];
        $state = in_array($data['state'], VendorCheckoutModel::VENDOR_STATES, true) ? $data['state'] : $checkout['state'];

        // "paid" needs both the state and the flag. A state that says paid without the flag is not believed.
        if ($state === VendorCheckoutModel::STATE_PAID && ! $data['paid']) {
            $state = $checkout['state'];
        }

        // Paid stays paid. The only thing that may follow is the vendor cancelling the order.
        if ((int) $checkout['paid'] === 1 && ! in_array($state, [VendorCheckoutModel::STATE_PAID, VendorCheckoutModel::STATE_CANCELLED], true)) {
            $state = $checkout['state'];
        }

        $paid = (int) $checkout['paid'] === 1 || ($data['paid'] && $state === VendorCheckoutModel::STATE_PAID);

        $update += [
            'state'            => $state,
            'paid'             => $paid ? 1 : 0,
            'vendor_order_id'  => $data['order_id'] ?? $checkout['vendor_order_id'],
            'vendor_reference' => $data['reference'] ?? $checkout['vendor_reference'],
            'final_amount'     => $data['amount'] ?? $checkout['final_amount'],
            'expires_at'       => $data['expires_at'] ?? $checkout['expires_at'],
            'last_error'       => null,
        ];

        // Delivery progress: read once an order exists and it is paid, or we have never read it. A pending
        // payment has nothing to say about delivery yet, so it is not asked about on every look.
        $orderId = $update['vendor_order_id'];

        if ($orderId !== null && $orderId !== '' && ($paid || empty($checkout['order_details']))) {
            $order = $client->order((string) $orderId);

            if ($order['success']) {
                $update['order_details'] = json_encode($order['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $this->checkoutModel->update($checkout['id'], $update);

        return $this->checkoutModel->find($checkout['id']);
    }

    /**
     * Asks about every purchase still moving (run by `php spark gifts:reconcile`). A visitor who paid and never
     * came back, or whose tab was closed, is found here: the vendor sends no webhook.
     *
     * @return array{checked: int, paid: int, changed: int}
     */
    public function reconcile(int $limit = 25): array
    {
        $checked = 0;
        $paid    = 0;
        $changed = 0;

        foreach ($this->checkoutModel->dueForCheck($limit) as $checkout) {
            $after = $this->status($checkout, true);

            $checked++;
            $changed += $after['state'] !== $checkout['state'] ? 1 : 0;
            $paid    += ((int) $after['paid'] === 1 && (int) $checkout['paid'] !== 1) ? 1 : 0;
        }

        return ['checked' => $checked, 'paid' => $paid, 'changed' => $changed];
    }

    /**
     * Whether the vendor can still tell us anything new: not once the purchase is cancelled or expired, and not
     * once it is paid and every gift has been delivered or cancelled.
     */
    public function needsChecking(array $checkout): bool
    {
        if (in_array($checkout['state'], [VendorCheckoutModel::STATE_CANCELLED, VendorCheckoutModel::STATE_EXPIRED, VendorCheckoutModel::STATE_FAILED, VendorCheckoutModel::STATE_DRAFT], true)) {
            return false;
        }

        if ($checkout['state'] === VendorCheckoutModel::STATE_PAID) {
            $deliveries = VendorCheckoutModel::order($checkout)['deliveries'] ?? [];

            if ($deliveries !== [] && $this->allDeliveriesFinal($deliveries)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     */
    public function allDeliveriesFinal(array $deliveries): bool
    {
        foreach ($deliveries as $delivery) {
            if (! in_array($delivery['state'] ?? '', VendorCheckoutModel::FINAL_DELIVERY_STATES, true)) {
                return false;
            }
        }

        return true;
    }

    private function askedRecently(array $checkout): bool
    {
        if (empty($checkout['last_checked_at'])) {
            return false;
        }

        try {
            $last = new DateTimeImmutable((string) $checkout['last_checked_at']);
        } catch (Throwable) {
            return false;
        }

        return (time() - $last->getTimestamp()) < self::MIN_SECONDS_BETWEEN_CHECKS;
    }

    /**
     * @return array{success: bool, message: string, checkout: ?array}
     */
    private function refused(string $message, ?array $checkout = null): array
    {
        return ['success' => false, 'message' => $message, 'checkout' => $checkout];
    }
}
