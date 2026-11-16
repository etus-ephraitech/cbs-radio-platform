<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Ordering a gift, on its own or to go with a greeting, and paying for it.
 *
 * @internal
 */
final class GiftFlowTest extends AppFeatureTestCase
{
    /** @var array<string, mixed> */
    private array $gift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->silenceNotifications();
        config('Payments')->mode = 'manual';

        $this->gift = $this->makeGift(['name' => 'Birthday e-card', 'price' => 5000]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'gift_id'          => $this->gift['id'],
            'sender_name'      => 'Grace Nakato',
            'sender_phone'     => '0772123456',
            'recipient_name'   => 'Mama Ruth',
            'recipient_phone'  => '0700111222',
            'delivery_details' => 'Ntinda, blue gate',
            'quantity'         => 2,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestOrder(): array
    {
        return $this->db->table('gift_orders')->orderBy('id', 'DESC')->get()->getRowArray();
    }

    private function orderCount(): int
    {
        return $this->db->table('gift_orders')->countAllResults();
    }

    // ------------------------------------------------------------------ the catalogue

    public function testTheCatalogueListsActiveGiftsWithPricesAndHidesTheRest(): void
    {
        $this->makeGift(['name' => 'Retired gift', 'is_active' => 0]);

        $body = $this->get('gifts')->getBody();

        $this->assertStringContainsString('Birthday e-card', $body);
        $this->assertStringContainsString('UGX 5,000', $body);
        $this->assertStringNotContainsString('Retired gift', $body);
    }

    public function testWithNoGiftsTheCatalogueSaysSo(): void
    {
        (new \App\Models\GiftModel())->update($this->gift['id'], ['is_active' => 0]);

        $this->assertStringContainsString('No gifts are available right now.', $this->get('gifts')->getBody());
    }

    public function testAGiftToGoWithAGreetingNamesThatGreeting(): void
    {
        $booking = $this->makeBooking();

        $body = $this->get('gifts?booking_id=' . $booking['id'])->getBody();

        $this->assertStringContainsString('This gift will be sent along with your greeting', $body);
        $this->assertStringContainsString(cbs_booking_reference((int) $booking['id']), $body);
    }

    // ------------------------------------------------------------------ ordering

    public function testAValidOrderIsPricedFromTheGiftAndSendsTheVisitorToPay(): void
    {
        $result = $this->post('gifts', $this->form());

        $result->assertRedirect();

        $order = $this->latestOrder();
        $this->assertStringEndsWith('/gifts/' . $order['id'] . '/pay', $result->getRedirectUrl());
        $this->assertSame('10000.00', $order['amount'], '2 x 5,000, taken from the gift.');
        $this->assertSame('pending_payment', $order['status']);
        $this->assertSame('Mama Ruth', $order['recipient_name']);
        $this->assertSame('Ntinda, blue gate', $order['delivery_details']);
        $this->assertNull($order['booking_id']);
    }

    public function testTheVisitorCannotNameThePriceOrTheStatus(): void
    {
        $this->post('gifts', $this->form(['amount' => 1, 'status' => 'paid', 'currency' => 'USD']));

        $order = $this->latestOrder();
        $this->assertSame('10000.00', $order['amount']);
        $this->assertSame('pending_payment', $order['status']);
        $this->assertSame('UGX', $order['currency']);
    }

    public function testAnOrderCanBeAttachedToAGreeting(): void
    {
        $booking = $this->makeBooking();

        $this->post('gifts', $this->form(['booking_id' => $booking['id']]));

        $this->assertSame((string) $booking['id'], (string) $this->latestOrder()['booking_id']);
    }

    public function testQuantityDefaultsToOne(): void
    {
        $form = $this->form();
        unset($form['quantity']);

        $this->post('gifts', $form);

        $this->assertSame('1', (string) $this->latestOrder()['quantity']);
        $this->assertSame('5000.00', $this->latestOrder()['amount']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function requiredFields(): array
    {
        return [
            'gift'           => ['gift_id'],
            'sender name'    => ['sender_name'],
            'sender phone'   => ['sender_phone'],
            'recipient name' => ['recipient_name'],
        ];
    }

    #[DataProvider('requiredFields')]
    public function testEachRequiredFieldIsRequired(string $field): void
    {
        $form = $this->form();
        unset($form[$field]);

        $this->post('gifts', $form)->assertRedirect();

        $this->assertArrayHasKey($field, $this->flash('errors'));
        $this->assertSame(0, $this->orderCount());
    }

    public function testMalformedValuesAreRefused(): void
    {
        foreach ([['quantity', '0'], ['quantity', '-3'], ['quantity', 'lots'], ['gift_id', 'abc'], ['booking_id', 'x'], ['sender_name', str_repeat('x', 151)]] as [$field, $value]) {
            $this->post('gifts', $this->form([$field => $value]));

            $this->assertArrayHasKey($field, (array) $this->flash('errors'), "{$field} = '{$value}' should be refused.");
        }

        $this->assertSame(0, $this->orderCount());
    }

    public function testAnUnknownOrSwitchedOffGiftIsRefusedWithAPlainMessage(): void
    {
        $retired = $this->makeGift(['is_active' => 0]);

        foreach ([$retired['id'], 999999] as $giftId) {
            $this->post('gifts', $this->form(['gift_id' => $giftId]))->assertRedirect();

            $this->assertSame('Selected gift is not available.', $this->flash('toastr_error'));
        }

        $this->assertSame(0, $this->orderCount());
    }

    // ------------------------------------------------------------------ paying

    public function testThePayPageShowsHowToPay(): void
    {
        config('Payments')->merchantCode = '654321';
        $order = $this->makeGiftOrder(['amount' => 10000]);

        $visible = preg_replace('/\s+/', ' ', strip_tags($this->get('gifts/' . $order['id'] . '/pay')->getBody()));

        foreach (['Pay for your gift', cbs_gift_reference((int) $order['id']), 'UGX 10,000', 'Merchant code: 654321'] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
    }

    public function testReloadingThePayPageDoesNotCreateAnotherPayment(): void
    {
        $order = $this->makeGiftOrder();

        $this->get('gifts/' . $order['id'] . '/pay');
        $this->get('gifts/' . $order['id'] . '/pay');

        $this->assertSame(1, $this->db->table('payments')->countAllResults());
    }

    public function testAnOrderNoLongerAwaitingPaymentSendsTheVisitorToItsStatus(): void
    {
        $order = $this->makeGiftOrder(['status' => 'paid']);

        $result = $this->get('gifts/' . $order['id'] . '/pay');

        $result->assertRedirect();
        $this->assertStringEndsWith('/gifts/' . $order['id'] . '/confirmation', $result->getRedirectUrl());
        $this->assertSame(0, $this->db->table('payments')->countAllResults());
    }

    public function testTheStatusPageShowsTheOrder(): void
    {
        $order = $this->makeGiftOrder(['recipient_name' => 'Aunt Joy', 'quantity' => 3]);

        $visible = preg_replace('/\s+/', ' ', strip_tags($this->get('gifts/' . $order['id'] . '/confirmation')->getBody()));

        foreach (['Gift order status', cbs_gift_reference((int) $order['id']), 'Aunt Joy'] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
    }

    public function testAnUnknownOrderOnEitherPageSendsThePersonBackToTheCatalogue(): void
    {
        foreach (['gifts/999999/pay', 'gifts/999999/confirmation'] as $path) {
            $this->get($path)->assertRedirect();

            $this->assertSame('Gift order not found.', $this->flash('toastr_error'));
        }
    }
}
