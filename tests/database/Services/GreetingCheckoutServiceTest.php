<?php

namespace Tests\Database\Services;

use App\Models\BookingModel;
use App\Models\GiftOrderModel;
use App\Services\BookingService;
use App\Services\GiftOrderService;
use App\Services\GreetingCheckoutService;
use App\Services\PaymentService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Libraries\RecordingNotifier;
use Tests\Support\Libraries\StaffUser;
use Tests\Support\TestCase\AppTestCase;

/**
 * A greeting and the gifts chosen with it, booked and paid for as one: what is created, what is refused before
 * anything is written, and how one payment settles all of it.
 *
 * @internal
 */
final class GreetingCheckoutServiceTest extends AppTestCase
{
    private RecordingNotifier $notifier;

    /** @var array<string, mixed> */
    private array $category;

    /** @var array<string, mixed> */
    private array $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notifier = new RecordingNotifier();
        $this->category = $this->makeCategory();
        $this->tier     = $this->makeTier($this->category['id'], ['price' => 30000, 'currency' => 'UGX']);
    }

    private function bookingService(): BookingService
    {
        return new BookingService(notificationService: $this->notifier);
    }

    private function checkout(): GreetingCheckoutService
    {
        return new GreetingCheckoutService(bookingService: $this->bookingService());
    }

    /**
     * @return array<string, mixed>
     */
    private function greeting(array $overrides = []): array
    {
        return $overrides + [
            'category_id'    => $this->category['id'],
            'rate_tier_id'   => $this->tier['id'],
            'sender_name'    => 'Grace Nakato',
            'sender_phone'   => '0772123456',
            'recipient_name' => 'Mama Ruth',
            'message_text'   => 'Happy birthday Mama Ruth!',
        ];
    }

    private function rows(string $table): int
    {
        return $this->db->table($table)->countAllResults();
    }

    // ------------------------------------------------------------------ creating

    public function testAGreetingWithoutGiftsIsJustABooking(): void
    {
        $result = $this->checkout()->submit($this->greeting(), []);

        $this->assertTrue($result['success']);
        $this->assertSame(BookingModel::STATUS_PENDING_PAYMENT, $result['booking']['status']);
        $this->assertSame([], $result['giftOrders']);
        $this->assertSame(0, $this->rows('gift_orders'));
    }

    public function testEachChosenGiftBecomesAnOrderPaidWithTheGreeting(): void
    {
        $ecard   = $this->makeGift(['name' => 'E-card', 'price' => 5000]);
        $airtime = $this->makeGift(['name' => 'Airtime', 'type' => 'airtime', 'price' => 10000]);
        $unused  = $this->makeGift(['name' => 'Not chosen']);

        $result = $this->checkout()->submit(
            $this->greeting(),
            [$ecard['id'] => '2', $airtime['id'] => '1', $unused['id'] => '0'],
            ['recipient_phone' => ' 0700111222 ']
        );

        $this->assertTrue($result['success'], $result['message']);
        $this->assertCount(2, $result['giftOrders']);

        $orders = (new GiftOrderModel())->bundledWithBooking((int) $result['booking']['id']);
        $this->assertSame(['E-card', 'Airtime'], array_column($orders, 'gift_name'));
        $this->assertSame(['10000.00', '10000.00'], array_column($orders, 'amount'), 'Quantity times price.');

        foreach ($orders as $order) {
            $this->assertSame('1', (string) $order['bundled_with_booking']);
            $this->assertSame(GiftOrderModel::STATUS_PENDING_PAYMENT, $order['status']);
            $this->assertSame('Mama Ruth', $order['recipient_name'], "The greeting's recipient receives the gifts.");
            $this->assertSame('Grace Nakato', $order['sender_name']);
            $this->assertSame('0700111222', $order['recipient_phone'], 'Trimmed.');
        }
    }

    public function testAPhysicalGiftNeedsADeliveryAddress(): void
    {
        $flowers = $this->makeGift(['name' => 'Flowers', 'type' => 'physical', 'price' => 50000]);

        $without = $this->checkout()->submit($this->greeting(), [$flowers['id'] => 1], ['delivery_details' => '   ']);

        $this->assertFalse($without['success']);
        $this->assertSame('Add a delivery address for the gift that will be delivered.', $without['message']);
        $this->assertSame(0, $this->rows('bookings'), 'Nothing is written when a gift is refused.');

        $with = $this->checkout()->submit($this->greeting(), [$flowers['id'] => 1], ['delivery_details' => 'Plot 4, Ntinda']);

        $this->assertTrue($with['success']);
        $this->assertSame('Plot 4, Ntinda', $with['giftOrders'][0]['delivery_details']);
    }

    // ------------------------------------------------------------------ refused before anything is written

    public function testAnUnavailableGiftIsRefusedAndNothingIsBooked(): void
    {
        $retired = $this->makeGift(['is_active' => 0]);

        $result = $this->checkout()->submit($this->greeting(), [$retired['id'] => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame('A gift you chose is no longer available. Please choose another.', $result['message']);
        $this->assertSame(0, $this->rows('bookings'));
        $this->assertSame(0, $this->rows('gift_orders'));
        $this->assertSame([], $this->notifier->sent, 'No confirmation for a greeting that was never booked.');
    }

    public function testAGiftThatDoesNotExistIsRefused(): void
    {
        $result = $this->checkout()->submit($this->greeting(), [99999 => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $this->rows('bookings'));
    }

    public function testAGiftInAnotherCurrencyCannotJoinThePayment(): void
    {
        $dollars = $this->makeGift(['name' => 'Gift card', 'currency' => 'USD', 'price' => 10]);

        $result = $this->checkout()->submit($this->greeting(), [$dollars['id'] => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame("Gift card is priced in USD and can't be paid together with this package.", $result['message']);
        $this->assertSame(0, $this->rows('bookings'));
    }

    public function testTooManyOfOneGiftIsRefused(): void
    {
        $gift = $this->makeGift();

        $result = $this->checkout()->submit($this->greeting(), [$gift['id'] => GreetingCheckoutService::MAX_QUANTITY + 1]);

        $this->assertFalse($result['success']);
        $this->assertSame('You can send up to ' . GreetingCheckoutService::MAX_QUANTITY . ' of any one gift.', $result['message']);
        $this->assertSame(0, $this->rows('bookings'));
    }

    public function testTooManyDifferentGiftsAreRefused(): void
    {
        $chosen = [];

        for ($i = 0; $i <= GreetingCheckoutService::MAX_GIFTS; $i++) {
            $chosen[$this->makeGift()['id']] = 1;
        }

        $result = $this->checkout()->submit($this->greeting(), $chosen);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $this->rows('bookings'));
    }

    /**
     * @return array<string, array{0: array<int|string, mixed>}>
     */
    public static function unreadableChoices(): array
    {
        return [
            'a negative quantity'   => [['GIFT' => '-1']],
            'a fractional quantity' => [['GIFT' => '1.5']],
            'words for a quantity'  => [['GIFT' => 'two']],
            'a gift id of words'    => [['abc' => '1']],
            'a quantity as a list'  => [['GIFT' => ['1']]],
        ];
    }

    /**
     * @param array<int|string, mixed> $choice
     */
    #[DataProvider('unreadableChoices')]
    public function testAChoiceThatCannotBeReadIsRefused(array $choice): void
    {
        $gift   = $this->makeGift();
        $choice = array_combine(array_map(static fn ($k) => $k === 'GIFT' ? $gift['id'] : $k, array_keys($choice)), $choice);

        $result = $this->checkout()->submit($this->greeting(), $choice);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $this->rows('bookings'));
    }

    public function testAProblemWithTheGreetingItselfIsReportedAsUsual(): void
    {
        $gift = $this->makeGift();

        $result = $this->checkout()->submit($this->greeting(['message_text' => '']), [$gift['id'] => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $this->rows('gift_orders'), 'No gift without its greeting.');
    }

    // ------------------------------------------------------------------ one payment for everything

    public function testTheGreetingsPaymentCoversItsGifts(): void
    {
        config('Payments')->mode = 'manual';
        $gift   = $this->makeGift(['price' => 5000]);
        $result = $this->checkout()->submit($this->greeting(), [$gift['id'] => 3]);

        $payments = new PaymentService(bookingService: $this->bookingService());
        $payment  = $payments->initiateForBooking($result['booking'])['payment'];

        $this->assertSame('45000.00', $payment['amount'], 'Package 30,000 + 3 × 5,000.');
        $this->assertSame(45000.0, $payments->totalForBooking($result['booking']));
    }

    public function testConfirmingThatPaymentPaysTheGiftsToo(): void
    {
        config('Payments')->mode = 'manual';
        $gift   = $this->makeGift();
        $result = $this->checkout()->submit($this->greeting(), [$gift['id'] => 1]);

        $payments = new PaymentService(bookingService: $this->bookingService());
        $payment  = $payments->initiateForBooking($result['booking'])['payment'];

        $this->assertTrue($payments->markManualPayment((int) $payment['id'], StaffUser::id()));

        $this->assertSame(BookingModel::STATUS_PENDING_MODERATION, (new BookingModel())->find($result['booking']['id'])['status']);
        $this->assertSame(GiftOrderModel::STATUS_PAID, (new GiftOrderModel())->find($result['giftOrders'][0]['id'])['status']);
    }

    public function testAGiftAddedToTheGreetingLaterIsPaidOnItsOwnNotWithTheGreeting(): void
    {
        config('Payments')->mode = 'manual';
        $result = $this->checkout()->submit($this->greeting(), []);
        $later  = (new GiftOrderService())->createOrder([
            'gift_id' => $this->makeGift(['price' => 7000])['id'], 'sender_name' => 'Grace', 'sender_phone' => '0772123456', 'recipient_name' => 'Mama Ruth',
        ], (int) $result['booking']['id']);

        $payments = new PaymentService(bookingService: $this->bookingService());
        $payment  = $payments->initiateForBooking($result['booking'])['payment'];

        $this->assertSame('30000.00', $payment['amount'], 'The later gift has its own payment, so it must not be charged here too.');

        $payments->markManualPayment((int) $payment['id'], StaffUser::id());
        $this->assertSame(GiftOrderModel::STATUS_PENDING_PAYMENT, (new GiftOrderModel())->find($later['giftOrder']['id'])['status']);
    }

    public function testCancellingTheGreetingCancelsItsUnpaidGifts(): void
    {
        $gift   = $this->makeGift();
        $result = $this->checkout()->submit($this->greeting(), [$gift['id'] => 1]);

        $this->bookingService()->cancelBooking((int) $result['booking']['id']);

        $this->assertSame(GiftOrderModel::STATUS_CANCELLED, (new GiftOrderModel())->find($result['giftOrders'][0]['id'])['status']);
    }

    public function testCancellingAPaidGreetingLeavesItsPaidGiftsForStaffToDecide(): void
    {
        $gift   = $this->makeGift();
        $result = $this->checkout()->submit($this->greeting(), [$gift['id'] => 1]);
        (new GiftOrderModel())->markPaid((int) $result['giftOrders'][0]['id']);

        $this->bookingService()->cancelBooking((int) $result['booking']['id']);

        $this->assertSame(GiftOrderModel::STATUS_PAID, (new GiftOrderModel())->find($result['giftOrders'][0]['id'])['status']);
    }

    public function testABundledGiftNeedsItsGreeting(): void
    {
        $result = (new GiftOrderService())->createOrder([
            'gift_id' => $this->makeGift()['id'], 'sender_name' => 'G', 'sender_phone' => '0772123456', 'recipient_name' => 'R',
        ], null, true);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $this->rows('gift_orders'));
    }
}
