<?php

namespace Tests\Feature;

use App\Models\BookingModel;
use App\Models\PaymentModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * The address a Mobile Money provider calls to say a payment succeeded or failed. It is public (the provider has
 * no login), so the only thing keeping a stranger from marking bookings paid is the secret token in the address.
 *
 * @internal
 */
final class PaymentCallbackTest extends AppFeatureTestCase
{
    use SetsEnvironment;

    private const TOKEN = 'a-long-random-callback-token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->silenceNotifications();
        $this->setEnv('momo.callbackToken', self::TOKEN);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function providerCalls(array $payload, ?string $token = self::TOKEN): \CodeIgniter\Test\TestResponse
    {
        $uri = 'payments/callback' . ($token !== null ? '?token=' . urlencode($token) : '');

        return $this->withBodyFormat('json')->post($uri, $payload);
    }

    /**
     * @return array{booking: array, payment: array}
     */
    private function pendingPayment(string $reference = 'REF-1'): array
    {
        $booking = $this->makeBooking();
        $payment = $this->makePayment(['booking_id' => $booking['id'], 'provider' => 'mtn', 'provider_reference' => $reference]);

        return ['booking' => $booking, 'payment' => $payment];
    }

    private function bookingStatus(array $booking): string
    {
        return (new BookingModel())->find($booking['id'])['status'];
    }

    // ------------------------------------------------------------------ the token

    public function testACallbackWithTheRightTokenIsAcceptedAndSettlesThePayment(): void
    {
        $paid = $this->pendingPayment();

        $result = $this->providerCalls(['provider_reference' => 'REF-1', 'status' => 'success']);

        $result->assertOK();
        $result->assertJSONExact(['received' => true]);
        $this->assertSame('pending_moderation', $this->bookingStatus($paid['booking']));
        $this->assertSame('success', (new PaymentModel())->find($paid['payment']['id'])['status']);
    }

    public function testACallbackWithAWrongOrMissingTokenIsRefusedAndChangesNothing(): void
    {
        $paid = $this->pendingPayment();

        foreach (['not-the-token', self::TOKEN . 'x', substr(self::TOKEN, 0, -1), '', null] as $token) {
            $result = $this->providerCalls(['provider_reference' => 'REF-1', 'status' => 'success'], $token);

            $result->assertStatus(403);
            $result->assertJSONFragment(['error' => 'Invalid callback token.']);
        }

        $this->assertSame('pending_payment', $this->bookingStatus($paid['booking']), 'A stranger must not be able to mark a booking paid.');
        $this->assertSame('pending', (new PaymentModel())->find($paid['payment']['id'])['status']);
    }

    public function testWithNoTokenConfiguredNothingIsAcceptedNotEvenAnEmptyOne(): void
    {
        // If the secret has not been set up, "no token" must not mean "anyone may call".
        $this->setEnv('momo.callbackToken', '');
        $paid = $this->pendingPayment();

        $this->providerCalls(['provider_reference' => 'REF-1', 'status' => 'success'], '')->assertStatus(403);
        $this->providerCalls(['provider_reference' => 'REF-1', 'status' => 'success'], null)->assertStatus(403);

        $this->assertSame('pending_payment', $this->bookingStatus($paid['booking']));
    }

    public function testTheCallbackIsOnlyReachableByPost(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('payments/callback?token=' . self::TOKEN);
    }

    // ------------------------------------------------------------------ the payload

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function malformedPayloads(): array
    {
        return [
            'nothing at all'            => [[]],
            'no status'                 => [['provider_reference' => 'REF-1']],
            'no reference'              => [['status' => 'success']],
            'an empty reference'        => [['provider_reference' => '', 'status' => 'success']],
            'an empty status'           => [['provider_reference' => 'REF-1', 'status' => '']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedPayloads')]
    public function testAMalformedPayloadIsRefusedAs422(array $payload): void
    {
        $paid = $this->pendingPayment();

        $result = $this->providerCalls($payload);

        $result->assertStatus(422);
        $result->assertJSONFragment(['error' => 'Malformed callback payload.']);
        $this->assertSame('pending_payment', $this->bookingStatus($paid['booking']));
    }

    public function testAnUnknownReferenceIsA404(): void
    {
        $this->pendingPayment('REF-1');

        $result = $this->providerCalls(['provider_reference' => 'SOMEONE-ELSES', 'status' => 'success']);

        $result->assertStatus(404);
        $result->assertJSONFragment(['error' => 'Payment record not found.']);
    }

    public function testAFailedStatusFailsThePaymentAndLeavesTheBookingAwaitingPayment(): void
    {
        $paid = $this->pendingPayment();

        $this->providerCalls(['provider_reference' => 'REF-1', 'status' => 'failed'])->assertOK();

        $this->assertSame('failed', (new PaymentModel())->find($paid['payment']['id'])['status']);
        $this->assertSame('pending_payment', $this->bookingStatus($paid['booking']));
    }

    public function testAProviderRetryingTheSameCallbackDoesNoHarm(): void
    {
        $paid = $this->pendingPayment();

        $this->providerCalls(['provider_reference' => 'REF-1', 'status' => 'success'])->assertOK();
        (new BookingModel())->update($paid['booking']['id'], ['status' => BookingModel::STATUS_APPROVED]);

        $this->providerCalls(['provider_reference' => 'REF-1', 'status' => 'success'])->assertOK();

        $this->assertSame('approved', $this->bookingStatus($paid['booking']), 'A retry must not pull an approved booking back into the queue.');
    }

    public function testNoStaffLoginIsNeededBecauseTheProviderHasNone(): void
    {
        // The route is outside the staff group; the stand-in staff filter is not what lets this through.
        $paid = $this->pendingPayment();

        $this->withSession([])->providerCalls(['provider_reference' => 'REF-1', 'status' => 'success'])->assertOK();

        $this->assertSame('pending_moderation', $this->bookingStatus($paid['booking']));
    }
}
