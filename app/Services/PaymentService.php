<?php

namespace App\Services;

use App\Models\GiftOrderModel;
use App\Models\PaymentModel;

/**
 * Owns payment creation and settlement for both bookings and gift orders.
 * Per the agreed scope, payment is a single Mobile Money provider plus an
 * admin "mark as paid" fallback — not a multi-provider system.
 *
 * OPEN ITEM: CBS has not yet confirmed MTN vs Airtel (see the scope
 * sign-off doc). chargeMobileMoney() below is the one method to fill in
 * once that's decided — everything else (record-keeping, callback
 * handling, downstream status updates, admin override) is provider-agnostic
 * and does not need to change.
 */
class PaymentService
{
    /** provider value recorded on a payment that staff will confirm by hand. */
    public const MANUAL_PROVIDER = 'manual';

    protected PaymentModel $paymentModel;
    protected BookingService $bookingService;
    protected GiftOrderService $giftOrderService;
    protected GiftOrderModel $giftOrderModel;

    public function __construct(
        ?PaymentModel $paymentModel = null,
        ?BookingService $bookingService = null,
        ?GiftOrderService $giftOrderService = null,
        ?GiftOrderModel $giftOrderModel = null
    ) {
        $this->paymentModel     = $paymentModel ?? new PaymentModel();
        $this->bookingService   = $bookingService ?? new BookingService();
        $this->giftOrderService = $giftOrderService ?? new GiftOrderService();
        $this->giftOrderModel   = $giftOrderModel ?? new GiftOrderModel();
    }

    /**
     * What paying for a greeting costs: its package, plus every gift chosen with it in the same form that is still
     * waiting for payment. One payment covers them all (see settleLinkedRecord()).
     */
    public function totalForBooking(array $booking): float
    {
        return round(
            (float) $booking['amount'] + $this->giftOrderModel->bundledUnpaidTotal((int) $booking['id'], (string) $booking['currency']),
            2
        );
    }

    /**
     * @return array{success: bool, message: string, payment: ?array, manual: bool}
     */
    public function initiateForBooking(array $booking): array
    {
        return $this->initiate([
            'booking_id' => $booking['id'],
            'amount'     => $this->totalForBooking($booking),
            'currency'   => $booking['currency'],
            'phone'      => $booking['sender_phone'],
        ]);
    }

    /**
     * @return array{success: bool, message: string, payment: ?array, manual: bool}
     */
    public function initiateForGiftOrder(array $giftOrder): array
    {
        return $this->initiate([
            'gift_order_id' => $giftOrder['id'],
            'amount'        => $giftOrder['amount'],
            'currency'      => $giftOrder['currency'],
            'phone'         => $giftOrder['sender_phone'],
        ]);
    }

    /**
     * Starts a payment for a booking or gift order, according to the payment
     * mode set under Admin > Settings > Payments:
     *
     *  - manual: records a pending payment and tells the payer how to pay;
     *    staff mark it paid from the payments screen.
     *  - mobile_money: records a pending payment and asks the provider to
     *    charge the payer's phone.
     *
     * Safe to call again for the same booking or gift order (the pay page is
     * a GET and gets reloaded): an existing pending payment is returned
     * instead of creating another one.
     *
     * @param array{booking_id?: int, gift_order_id?: int, amount: float, currency: string, phone: string} $context
     * @return array{success: bool, message: string, payment: ?array, manual: bool}
     */
    protected function initiate(array $context): array
    {
        $settings = config('Payments');
        $linkKey  = isset($context['booking_id']) ? 'booking_id' : 'gift_order_id';

        $pending = $this->paymentModel
            ->where($linkKey, $context[$linkKey])
            ->where('status', PaymentModel::STATUS_PENDING)
            ->first();

        if ($pending !== null) {
            return [
                'success' => true,
                'message' => 'Your payment is awaiting confirmation.',
                'payment' => $pending,
                'manual'  => $pending['provider'] === self::MANUAL_PROVIDER,
            ];
        }

        if ($settings->mode === 'manual') {
            $paymentId = $this->createPending($context, self::MANUAL_PROVIDER);

            if ($paymentId === false) {
                return $this->failure('Could not create payment record.');
            }

            return [
                'success' => true,
                'message' => 'Follow the payment instructions below.',
                'payment' => $this->paymentModel->find($paymentId),
                'manual'  => true,
            ];
        }

        if ($settings->provider === '') {
            return $this->failure('Online payments are not set up yet.');
        }

        $paymentId = $this->createPending($context, $settings->provider);

        if ($paymentId === false) {
            return $this->failure('Could not create payment record.');
        }

        $charge = $this->chargeMobileMoney($context['phone'], (float) $context['amount'], (string) $paymentId);

        if (! $charge['success']) {
            $this->paymentModel->markFailed($paymentId);

            return [
                'success' => false,
                'message' => $charge['message'],
                'payment' => $this->paymentModel->find($paymentId),
                'manual'  => false,
            ];
        }

        return [
            'success' => true,
            'message' => 'Payment request sent to your phone.',
            'payment' => $this->paymentModel->find($paymentId),
            'manual'  => false,
        ];
    }

    /**
     * @return int|string|false the new payment id
     */
    protected function createPending(array $context, string $provider): int|string|false
    {
        return $this->paymentModel->insert([
            'booking_id'    => $context['booking_id'] ?? null,
            'gift_order_id' => $context['gift_order_id'] ?? null,
            'provider'      => $provider,
            'amount'        => $context['amount'],
            'currency'      => $context['currency'],
            'status'        => PaymentModel::STATUS_PENDING,
        ]);
    }

    /**
     * @return array{success: bool, message: string, payment: null, manual: bool}
     */
    protected function failure(string $message): array
    {
        return ['success' => false, 'message' => $message, 'payment' => null, 'manual' => false];
    }

    /**
     * Called from the Mobile Money provider's webhook/callback route once
     * they confirm success or failure for a prior charge request.
     *
     * @param array{provider_reference: string, status: string} $payload
     */
    public function handleCallback(array $payload): bool
    {
        $payment = $this->paymentModel
            ->where('provider_reference', $payload['provider_reference'])
            ->first();

        if ($payment === null) {
            return false;
        }

        if ($payload['status'] === 'success') {
            $this->paymentModel->markSuccess($payment['id'], $payload['provider_reference']);
            $this->settleLinkedRecord($payment);

            return true;
        }

        $this->paymentModel->markFailed($payment['id']);

        return true;
    }

    /**
     * Admin fallback: mark a payment as paid manually when the Mobile
     * Money flow fails or can't be verified, so a booking is never stuck
     * because of a payment hiccup.
     */
    public function markManualPayment(int $paymentId, int $adminUserId): bool
    {
        $payment = $this->paymentModel->find($paymentId);

        if ($payment === null || $payment['status'] === 'success') {
            return false;
        }

        $this->paymentModel->markManuallyPaid($paymentId, $adminUserId);
        $this->settleLinkedRecord($this->paymentModel->find($paymentId));

        return true;
    }

    /**
     * Pushes a successful payment's status onto whichever record it's
     * linked to — a booking moves into moderation, a gift order moves
     * to paid.
     */
    protected function settleLinkedRecord(array $payment): void
    {
        if (! empty($payment['booking_id'])) {
            $this->bookingService->confirmPaymentReceived((int) $payment['booking_id']);
            // The same payment covered the gifts chosen with the greeting.
            $this->giftOrderService->confirmBundledPaymentReceived((int) $payment['booking_id']);
        }

        if (! empty($payment['gift_order_id'])) {
            $this->giftOrderService->confirmPaymentReceived((int) $payment['gift_order_id']);
        }
    }

    /**
     * The one provider-specific integration point. Fill this in once CBS
     * confirms MTN or Airtel — the request/response shape differs between
     * the two providers' Collections APIs, so this should be written
     * against that provider's current docs rather than guessed at here.
     *
     * @return array{success: bool, message: string}
     */
    protected function chargeMobileMoney(string $phone, float $amount, string $referenceId): array
    {
        // TODO: replace with the confirmed provider's real Collections API
        // call once MTN vs Airtel is decided (see scope sign-off doc).
        return ['success' => false, 'message' => 'Mobile Money provider integration not yet implemented.'];
    }
}
