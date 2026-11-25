<?php

namespace App\Services;

use App\Libraries\VendorGiftClients\VendorClients;
use App\Libraries\VendorGiftClients\VendorGiftClientInterface;
use App\Models\GiftModel;
use App\Models\GiftOrderModel;
use App\Models\GiftVendorModel;

/**
 * Owns gift ordering — both the standalone "Send a Gift" flow and a gift
 * attached to an existing booking, for both local gifts and vendor-sourced
 * ones (see GiftModel::SOURCE_LOCAL / SOURCE_VENDOR). A local gift is
 * fulfilled by an admin marking it done by hand; a vendor gift is
 * fulfilled by actually placing the order with the vendor's API.
 */
class GiftOrderService
{
    protected GiftModel $giftModel;
    protected GiftOrderModel $giftOrderModel;
    protected GiftVendorModel $giftVendorModel;

    public function __construct(
        ?GiftModel $giftModel = null,
        ?GiftOrderModel $giftOrderModel = null,
        ?GiftVendorModel $giftVendorModel = null
    ) {
        $this->giftModel       = $giftModel ?? new GiftModel();
        $this->giftOrderModel  = $giftOrderModel ?? new GiftOrderModel();
        $this->giftVendorModel = $giftVendorModel ?? new GiftVendorModel();
    }

    /**
     * Expected $input keys: gift_id, sender_name, sender_phone,
     * recipient_name, recipient_phone, delivery_details, quantity.
     * Pass $bookingId when the gift rides along with an existing greeting;
     * leave null for the standalone "Send a Gift" flow. Pass
     * $bundledWithBooking when the gift was chosen inside that greeting's form
     * and is paid for in the greeting's payment (see GreetingCheckoutService);
     * such an order never gets a payment of its own.
     *
     * @return array{success: bool, message: string, giftOrder: ?array, errors: array}
     */
    public function createOrder(array $input, ?int $bookingId = null, bool $bundledWithBooking = false): array
    {
        if ($bundledWithBooking && $bookingId === null) {
            return ['success' => false, 'message' => 'A gift paid with a greeting needs that greeting.', 'giftOrder' => null, 'errors' => []];
        }

        $gift = empty($input['gift_id']) ? null : $this->giftModel->find($input['gift_id']); // not find(null): that is every row

        if ($gift === null || (int) $gift['is_active'] !== 1) {
            return ['success' => false, 'message' => 'Selected gift is not available.', 'giftOrder' => null, 'errors' => []];
        }

        // A gift from a vendor with its own checkout is paid on the vendor's site, never through ours: it goes in
        // the gift cart (see HostedCheckoutService). Refused here so that no one can pay us for it by a hand-made request.
        if ($gift['source'] === GiftModel::SOURCE_VENDOR && ! empty($gift['vendor_id'])
            && GiftVendorModel::isHosted($this->giftVendorModel->find($gift['vendor_id']))) {
            return ['success' => false, 'message' => 'This gift is bought through the gift cart.', 'giftOrder' => null, 'errors' => []];
        }

        $quantity = max((int) ($input['quantity'] ?? 1), 1);

        $data = [
            'booking_id'           => $bookingId,
            'bundled_with_booking' => $bundledWithBooking ? 1 : 0,
            'gift_id'              => $gift['id'],
            'sender_name'          => $input['sender_name'] ?? null,
            'sender_phone'         => $input['sender_phone'] ?? null,
            'recipient_name'       => $input['recipient_name'] ?? null,
            'recipient_phone'      => $input['recipient_phone'] ?? null,
            'delivery_details'     => $input['delivery_details'] ?? null,
            'quantity'             => $quantity,
            'amount'               => $gift['price'] * $quantity,
            'currency'             => $gift['currency'],
            'status'               => GiftOrderModel::STATUS_PENDING_PAYMENT,
        ];

        $id = $this->giftOrderModel->insert($data);

        if ($id === false) {
            return ['success' => false, 'message' => 'Could not create gift order.', 'giftOrder' => null, 'errors' => $this->giftOrderModel->errors()];
        }

        return ['success' => true, 'message' => 'Gift order created.', 'giftOrder' => $this->giftOrderModel->find($id), 'errors' => []];
    }

    /**
     * Called by PaymentService once payment for this gift order succeeds.
     */
    public function confirmPaymentReceived(int $giftOrderId): bool
    {
        $order = $this->giftOrderModel->find($giftOrderId);

        if ($order === null || $order['status'] !== GiftOrderModel::STATUS_PENDING_PAYMENT) {
            return false;
        }

        return $this->giftOrderModel->markPaid($giftOrderId);
    }

    /**
     * Fulfills a paid gift order. For a local gift this is an admin
     * hand-off (e-card sent, airtime topped up) and just marks it done.
     * For a vendor-sourced gift this actually places the order with the
     * vendor's API first, and only marks it fulfilled if the vendor
     * confirms.
     *
     * @return array{success: bool, message: string}
     */
    public function fulfill(int $giftOrderId): array
    {
        $order = $this->giftOrderModel->find($giftOrderId);

        if ($order === null || $order['status'] !== GiftOrderModel::STATUS_PAID) {
            return ['success' => false, 'message' => 'This gift order is not ready to be fulfilled.'];
        }

        $gift = $this->giftModel->find($order['gift_id']);

        if ($gift === null) {
            return ['success' => false, 'message' => 'The gift for this order no longer exists.'];
        }

        if ($gift['source'] !== GiftModel::SOURCE_VENDOR) {
            $this->giftOrderModel->markFulfilled($giftOrderId);

            return ['success' => true, 'message' => 'Gift order marked fulfilled.'];
        }

        // find(null) means "every row" in CodeIgniter, so a gift with no vendor must never reach it.
        $vendor = empty($gift['vendor_id']) ? null : $this->giftVendorModel->find($gift['vendor_id']);

        if ($vendor === null) {
            return ['success' => false, 'message' => 'This gift\'s vendor is no longer configured.'];
        }

        $client = $this->vendorClient($vendor);
        $result = $client->submitOrder([
            'vendor_product_ref' => $gift['vendor_product_ref'],
            'quantity'           => (int) $order['quantity'],
            'recipient_name'     => $order['recipient_name'],
            'recipient_phone'    => $order['recipient_phone'],
            'delivery_details'   => $order['delivery_details'],
        ]);

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message']];
        }

        $this->giftOrderModel->markFulfilled($giftOrderId, $result['vendor_order_ref']);

        return ['success' => true, 'message' => 'Order placed with vendor and marked fulfilled.'];
    }

    /**
     * The client for one vendor. A method of its own so that a test can supply a fake, instead of the
     * service reaching out to a real vendor's servers.
     */
    protected function vendorClient(array $vendor): VendorGiftClientInterface
    {
        return VendorClients::for($vendor);
    }

    /**
     * The greeting's payment has been received: every gift chosen with it, still waiting for payment, is paid too.
     *
     * @return int how many gift orders were marked paid
     */
    public function confirmBundledPaymentReceived(int $bookingId): int
    {
        $paid = 0;

        foreach ($this->giftOrderModel->bundledWithBooking($bookingId, GiftOrderModel::STATUS_PENDING_PAYMENT) as $order) {
            if ($this->giftOrderModel->markPaid((int) $order['id'])) {
                $paid++;
            }
        }

        return $paid;
    }

    /**
     * The greeting was cancelled: gifts chosen with it that were never paid for are cancelled with it. A gift that
     * has been paid is left for staff to decide on (deliver it anyway, or refund), like the greeting's own payment.
     *
     * @return int how many gift orders were cancelled
     */
    public function cancelBundledUnpaid(int $bookingId): int
    {
        $cancelled = 0;

        foreach ($this->giftOrderModel->bundledWithBooking($bookingId, GiftOrderModel::STATUS_PENDING_PAYMENT) as $order) {
            if ($this->giftOrderModel->markCancelled((int) $order['id'])) {
                $cancelled++;
            }
        }

        return $cancelled;
    }

    public function cancel(int $giftOrderId): bool
    {
        $order = $this->giftOrderModel->find($giftOrderId);

        if ($order === null || $order['status'] === GiftOrderModel::STATUS_FULFILLED) {
            return false;
        }

        return $this->giftOrderModel->markCancelled($giftOrderId);
    }
}
