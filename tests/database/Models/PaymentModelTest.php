<?php

namespace Tests\Database\Models;

use App\Models\PaymentModel;
use Tests\Support\Libraries\StaffUser;
use Tests\Support\TestCase\AppTestCase;

/**
 * @internal
 */
final class PaymentModelTest extends AppTestCase
{
    private PaymentModel $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = new PaymentModel();
    }

    public function testAPaymentIsForABookingOrAGiftOrderButNeverBothOrNeither(): void
    {
        $this->assertTrue($this->payments->validatePayable(['booking_id' => 5]));
        $this->assertTrue($this->payments->validatePayable(['gift_order_id' => 7]));
        $this->assertFalse($this->payments->validatePayable(['booking_id' => 5, 'gift_order_id' => 7]), 'Both is ambiguous.');
        $this->assertFalse($this->payments->validatePayable([]), 'Neither pays for nothing.');
        $this->assertFalse($this->payments->validatePayable(['booking_id' => null, 'gift_order_id' => 0]));
    }

    public function testASuccessfulCallbackRecordsTheProvidersReferenceAndTheTime(): void
    {
        $payment = $this->makePayment();

        $this->assertTrue($this->payments->markSuccess($payment['id'], 'MTN-REF-123'));

        $after = $this->payments->find($payment['id']);
        $this->assertSame('success', $after['status']);
        $this->assertSame('MTN-REF-123', $after['provider_reference']);
        $this->assertEqualsWithDelta(time(), strtotime($after['paid_at']), 5);
    }

    public function testAFailedPaymentIsMarkedFailedWithNoPaidTime(): void
    {
        $payment = $this->makePayment();

        $this->payments->markFailed($payment['id']);

        $after = $this->payments->find($payment['id']);
        $this->assertSame('failed', $after['status']);
        $this->assertNull($after['paid_at']);
    }

    public function testAManualPaymentRecordsWhichStaffMemberConfirmedIt(): void
    {
        $payment = $this->makePayment();

        $this->payments->markManuallyPaid($payment['id'], StaffUser::id());

        $after = $this->payments->find($payment['id']);
        $this->assertSame('success', $after['status']);
        $this->assertSame((string) StaffUser::id(), (string) $after['marked_paid_by']);
        $this->assertNotNull($after['paid_at']);
    }

    public function testAPaymentIsFoundFromItsBookingOrGiftOrder(): void
    {
        $booking   = $this->makeBooking();
        $giftOrder = $this->makeGiftOrder();

        $forBooking   = $this->makePayment(['booking_id' => $booking['id']]);
        $forGiftOrder = $this->makePayment(['gift_order_id' => $giftOrder['id']]);

        $this->assertSame($forBooking['id'], $this->payments->forBooking((int) $booking['id'])['id']);
        $this->assertSame($forGiftOrder['id'], $this->payments->forGiftOrder((int) $giftOrder['id'])['id']);
        $this->assertNull($this->payments->forBooking(999999));
        $this->assertNull($this->payments->forGiftOrder(999999));
    }

    public function testAPaymentNeedsAProviderAnAmountAndAKnownStatus(): void
    {
        $booking = $this->makeBooking();

        $this->assertFalse($this->payments->insert(['booking_id' => $booking['id'], 'amount' => 100, 'provider' => '']));
        $this->assertFalse($this->payments->insert(['booking_id' => $booking['id'], 'provider' => 'manual']));
        $this->assertFalse($this->payments->insert(['booking_id' => $booking['id'], 'provider' => 'manual', 'amount' => 100, 'status' => 'refunded']));
    }
}
