<?php

namespace Tests\Database\Services;

use App\Models\BookingModel;
use App\Models\GiftOrderModel;
use App\Models\PaymentModel;
use App\Services\BookingService;
use App\Services\PaymentService;
use Tests\Support\Libraries\FakeMobileMoneyPayments;
use Tests\Support\Libraries\RecordingNotifier;
use Tests\Support\Libraries\StaffUser;
use Tests\Support\TestCase\AppTestCase;

/**
 * Taking payment, by hand (staff confirm it) or online (Mobile Money), and what a confirmed payment sets moving.
 *
 * @internal
 */
final class PaymentServiceTest extends AppTestCase
{
    private RecordingNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notifier = new RecordingNotifier();
    }

    private function service(string $class = PaymentService::class): PaymentService
    {
        return new $class(bookingService: new BookingService(notificationService: $this->notifier));
    }

    private function paymentCount(): int
    {
        return $this->db->table('payments')->countAllResults();
    }

    // ------------------------------------------------------------------ manual mode

    public function testInManualModeAPendingPaymentIsCreatedForTheBookingsAmount(): void
    {
        config('Payments')->mode = 'manual';
        $booking = $this->makeBooking(['amount' => 30000]);

        $result = $this->service()->initiateForBooking($booking);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['manual']);
        $this->assertSame('Follow the payment instructions below.', $result['message']);

        $payment = $result['payment'];
        $this->assertSame('manual', $payment['provider']);
        $this->assertSame('pending', $payment['status']);
        $this->assertSame('30000.00', $payment['amount']);
        $this->assertSame((string) $booking['id'], (string) $payment['booking_id']);
        $this->assertNull($payment['gift_order_id']);
    }

    public function testAskingAgainReusesTheSamePendingPayment(): void
    {
        // The pay page can be reloaded, or opened twice; each load must not add another payment.
        config('Payments')->mode = 'manual';
        $booking = $this->makeBooking();
        $service = $this->service();

        $first  = $service->initiateForBooking($booking);
        $second = $service->initiateForBooking($booking);

        $this->assertSame($first['payment']['id'], $second['payment']['id']);
        $this->assertSame('Your payment is awaiting confirmation.', $second['message']);
        $this->assertTrue($second['manual']);
        $this->assertSame(1, $this->paymentCount());
    }

    public function testAFailedPaymentLeavesRoomForAFreshAttempt(): void
    {
        config('Payments')->mode = 'manual';
        $booking = $this->makeBooking();
        $service = $this->service();

        $first = $service->initiateForBooking($booking);
        (new PaymentModel())->markFailed($first['payment']['id']);

        $second = $service->initiateForBooking($booking);

        $this->assertNotSame($first['payment']['id'], $second['payment']['id']);
        $this->assertSame('pending', $second['payment']['status']);
    }

    public function testEachBookingGetsItsOwnPayment(): void
    {
        config('Payments')->mode = 'manual';
        $service = $this->service();

        $a = $service->initiateForBooking($this->makeBooking());
        $b = $service->initiateForBooking($this->makeBooking());

        $this->assertNotSame($a['payment']['id'], $b['payment']['id']);
        $this->assertSame(2, $this->paymentCount());
    }

    public function testAGiftOrderIsPaidForTheSameWay(): void
    {
        config('Payments')->mode = 'manual';
        $order = $this->makeGiftOrder(['amount' => 15000]);

        $result = $this->service()->initiateForGiftOrder($order);

        $this->assertTrue($result['success']);
        $this->assertSame((string) $order['id'], (string) $result['payment']['gift_order_id']);
        $this->assertNull($result['payment']['booking_id']);
        $this->assertSame('15000.00', $result['payment']['amount']);
    }

    // ------------------------------------------------------------------ online mode

    public function testOnlinePaymentsAreRefusedUntilAProviderIsChosen(): void
    {
        config('Payments')->mode     = 'mobile_money';
        config('Payments')->provider = '';

        $result = $this->service()->initiateForBooking($this->makeBooking());

        $this->assertFalse($result['success']);
        $this->assertSame('Online payments are not set up yet.', $result['message']);
        $this->assertSame(0, $this->paymentCount());
    }

    public function testTodaysMobileMoneyIntegrationIsAStubAndAFailedChargeIsRecordedAsFailed(): void
    {
        // Pinned honestly: until CBS confirms the provider, the real charge always fails. What matters is that the
        // failure is recorded and reported, not lost, and that nobody is left looking at a payment that never was.
        config('Payments')->mode     = 'mobile_money';
        config('Payments')->provider = 'mtn';

        $result = $this->service()->initiateForBooking($this->makeBooking());

        $this->assertFalse($result['success']);
        $this->assertFalse($result['manual']);
        $this->assertSame('Mobile Money provider integration not yet implemented.', $result['message']);
        $this->assertSame('failed', $result['payment']['status']);
    }

    public function testAnAcceptedChargeLeavesAPendingPaymentAndAsksThePayersPhone(): void
    {
        config('Payments')->mode     = 'mobile_money';
        config('Payments')->provider = 'mtn';
        $booking = $this->makeBooking(['amount' => 30000, 'sender_phone' => '0772123456']);

        /** @var FakeMobileMoneyPayments $service */
        $service = $this->service(FakeMobileMoneyPayments::class);

        $result = $service->initiateForBooking($booking);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['manual']);
        $this->assertSame('Payment request sent to your phone.', $result['message']);
        $this->assertSame(['mtn', 'pending'], [$result['payment']['provider'], $result['payment']['status']]);

        $this->assertCount(1, $service->charges);
        $this->assertSame('0772123456', $service->charges[0]['phone']);
        $this->assertSame(30000.0, $service->charges[0]['amount']);
        $this->assertSame((string) $result['payment']['id'], $service->charges[0]['reference'], 'The provider is given our payment id to quote back.');
    }

    public function testADeclinedChargeIsReportedWithTheProvidersReason(): void
    {
        config('Payments')->mode     = 'mobile_money';
        config('Payments')->provider = 'airtel';

        /** @var FakeMobileMoneyPayments $service */
        $service              = $this->service(FakeMobileMoneyPayments::class);
        $service->chargeResult = ['success' => false, 'message' => 'Insufficient funds.'];

        $result = $service->initiateForBooking($this->makeBooking());

        $this->assertFalse($result['success']);
        $this->assertSame('Insufficient funds.', $result['message']);
        $this->assertSame('failed', $result['payment']['status']);
    }

    public function testAnOnlinePaymentAlreadyPendingIsNotChargedAgain(): void
    {
        config('Payments')->mode     = 'mobile_money';
        config('Payments')->provider = 'mtn';
        $booking = $this->makeBooking();

        /** @var FakeMobileMoneyPayments $service */
        $service = $this->service(FakeMobileMoneyPayments::class);

        $service->initiateForBooking($booking);
        $again = $service->initiateForBooking($booking);

        $this->assertCount(1, $service->charges, 'Reloading the page must not send the payer a second prompt.');
        $this->assertSame('Your payment is awaiting confirmation.', $again['message']);
        $this->assertFalse($again['manual']);
    }

    // ------------------------------------------------------------------ the provider's callback

    public function testASuccessfulCallbackSettlesThePaymentAndMovesTheBookingOn(): void
    {
        $booking = $this->makeBooking();
        $payment = $this->makePayment(['booking_id' => $booking['id'], 'provider' => 'mtn', 'provider_reference' => 'REF-1']);

        $this->assertTrue($this->service()->handleCallback(['provider_reference' => 'REF-1', 'status' => 'success']));

        $after = (new PaymentModel())->find($payment['id']);
        $this->assertSame('success', $after['status']);
        $this->assertNotNull($after['paid_at']);
        $this->assertSame('pending_moderation', (new BookingModel())->find($booking['id'])['status']);
    }

    public function testASuccessfulCallbackForAGiftOrderMarksItPaid(): void
    {
        $order = $this->makeGiftOrder();
        $this->makePayment(['gift_order_id' => $order['id'], 'provider' => 'mtn', 'provider_reference' => 'REF-G']);

        $this->service()->handleCallback(['provider_reference' => 'REF-G', 'status' => 'success']);

        $this->assertSame('paid', (new GiftOrderModel())->find($order['id'])['status']);
    }

    public function testAFailedCallbackFailsThePaymentAndLeavesTheBookingAlone(): void
    {
        $booking = $this->makeBooking();
        $payment = $this->makePayment(['booking_id' => $booking['id'], 'provider' => 'mtn', 'provider_reference' => 'REF-2']);

        $this->assertTrue($this->service()->handleCallback(['provider_reference' => 'REF-2', 'status' => 'failed']));

        $this->assertSame('failed', (new PaymentModel())->find($payment['id'])['status']);
        $this->assertSame('pending_payment', (new BookingModel())->find($booking['id'])['status']);
    }

    public function testACallbackForAnUnknownReferenceIsIgnored(): void
    {
        $booking = $this->makeBooking();
        $this->makePayment(['booking_id' => $booking['id'], 'provider_reference' => 'REF-3']);

        $this->assertFalse($this->service()->handleCallback(['provider_reference' => 'SOMEONE-ELSES', 'status' => 'success']));
        $this->assertSame('pending_payment', (new BookingModel())->find($booking['id'])['status']);
    }

    public function testTheSameSuccessCallbackArrivingTwiceDoesNoHarm(): void
    {
        // Providers retry callbacks. The second one must not push a booking that has moved on back through the queue.
        $booking = $this->makeBooking();
        $this->makePayment(['booking_id' => $booking['id'], 'provider_reference' => 'REF-4']);
        $service = $this->service();

        $service->handleCallback(['provider_reference' => 'REF-4', 'status' => 'success']);
        (new BookingModel())->update($booking['id'], ['status' => BookingModel::STATUS_APPROVED]);

        $service->handleCallback(['provider_reference' => 'REF-4', 'status' => 'success']);

        $this->assertSame('approved', (new BookingModel())->find($booking['id'])['status']);
    }

    // ------------------------------------------------------------------ staff confirming a payment by hand

    public function testStaffCanConfirmAManualPaymentAndTheBookingMovesOn(): void
    {
        $booking = $this->makeBooking();
        $payment = $this->makePayment(['booking_id' => $booking['id']]);

        $this->assertTrue($this->service()->markManualPayment((int) $payment['id'], StaffUser::id()));

        $after = (new PaymentModel())->find($payment['id']);
        $this->assertSame('success', $after['status']);
        $this->assertSame((string) StaffUser::id(), (string) $after['marked_paid_by']);
        $this->assertSame('pending_moderation', (new BookingModel())->find($booking['id'])['status']);
    }

    public function testConfirmingAGiftOrdersPaymentMarksTheOrderPaid(): void
    {
        $order   = $this->makeGiftOrder();
        $payment = $this->makePayment(['gift_order_id' => $order['id']]);

        $this->service()->markManualPayment((int) $payment['id'], StaffUser::id());

        $this->assertSame('paid', (new GiftOrderModel())->find($order['id'])['status']);
    }

    public function testAPaymentAlreadyConfirmedCannotBeConfirmedAgain(): void
    {
        $booking = $this->makeBooking();
        $payment = $this->makePayment(['booking_id' => $booking['id']]);
        $service = $this->service();

        $this->assertTrue($service->markManualPayment((int) $payment['id'], StaffUser::id()));
        $this->assertFalse($service->markManualPayment((int) $payment['id'], StaffUser::id()));
    }

    public function testAnUnknownPaymentCannotBeConfirmed(): void
    {
        $this->assertFalse($this->service()->markManualPayment(999999, StaffUser::id()));
    }

    public function testAPaymentThatFailedCanStillBeConfirmedByHandIfTheMoneyArrivedAnyway(): void
    {
        $booking = $this->makeBooking();
        $payment = $this->makePayment(['booking_id' => $booking['id'], 'status' => 'failed']);

        $this->assertTrue($this->service()->markManualPayment((int) $payment['id'], StaffUser::id()));
        $this->assertSame('pending_moderation', (new BookingModel())->find($booking['id'])['status']);
    }
}
