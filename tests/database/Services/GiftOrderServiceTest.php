<?php

namespace Tests\Database\Services;

use App\Libraries\VendorGiftClients\VendorGiftClientInterface;
use App\Models\GiftModel;
use App\Models\GiftOrderModel;
use App\Services\GiftOrderService;
use Tests\Support\Libraries\FakeVendorClient;
use Tests\Support\TestCase\AppTestCase;

/**
 * Ordering gifts, and getting them to the recipient, from our own stock or a vendor.
 *
 * @internal
 */
final class GiftOrderServiceTest extends AppTestCase
{
    private GiftOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GiftOrderService();
    }

    /**
     * A service whose vendor is the given fake, so nothing reaches the internet.
     */
    private function serviceWithVendor(FakeVendorClient $vendor): GiftOrderService
    {
        return new class ($vendor) extends GiftOrderService {
            public function __construct(private FakeVendorClient $fake)
            {
                parent::__construct();
            }

            protected function vendorClient(array $vendor): VendorGiftClientInterface
            {
                return $this->fake;
            }
        };
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function input(int|string $giftId, array $overrides = []): array
    {
        return $overrides + [
            'gift_id'          => $giftId,
            'sender_name'      => 'Grace Nakato',
            'sender_phone'     => '0772123456',
            'recipient_name'   => 'Mama Ruth',
            'recipient_phone'  => '0700111222',
            'delivery_details' => 'Kampala, Ntinda',
        ];
    }

    // ------------------------------------------------------------------ ordering

    public function testAnOrderIsPricedFromTheGiftAndWaitsForPayment(): void
    {
        $gift = $this->makeGift(['price' => 5000, 'currency' => 'UGX']);

        $result = $this->service->createOrder($this->input($gift['id'], ['quantity' => 3]));

        $this->assertTrue($result['success']);
        $this->assertSame('Gift order created.', $result['message']);

        $order = $result['giftOrder'];
        $this->assertSame('15000.00', $order['amount'], 'Price times quantity, taken from the gift and never from the form.');
        $this->assertSame('UGX', $order['currency']);
        $this->assertSame('pending_payment', $order['status']);
        $this->assertSame('Mama Ruth', $order['recipient_name']);
        $this->assertSame('Kampala, Ntinda', $order['delivery_details']);
        $this->assertNull($order['booking_id']);
    }

    public function testTheVisitorCannotNameTheirOwnPrice(): void
    {
        $gift = $this->makeGift(['price' => 5000]);

        $result = $this->service->createOrder($this->input($gift['id'], ['amount' => 1, 'status' => 'paid', 'currency' => 'USD']));

        $this->assertSame('5000.00', $result['giftOrder']['amount']);
        $this->assertSame('pending_payment', $result['giftOrder']['status']);
        $this->assertSame('UGX', $result['giftOrder']['currency']);
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function quantities(): array
    {
        return [
            'not given'      => [null, 1],
            'zero'           => [0, 1],
            'negative'       => [-4, 1],
            'words'          => ['lots', 1],
            'a normal one'   => [2, 2],
            'a numeric text' => ['3', 3],
        ];
    }

    /**
     * @dataProvider quantities
     */
    public function testQuantityIsAtLeastOne(mixed $given, int $expected): void
    {
        $gift  = $this->makeGift(['price' => 1000]);
        $input = $this->input($gift['id']);

        if ($given !== null) {
            $input['quantity'] = $given;
        }

        $order = $this->service->createOrder($input)['giftOrder'];

        $this->assertSame($expected, (int) $order['quantity']);
        $this->assertSame(number_format($expected * 1000, 2, '.', ''), $order['amount']);
    }

    public function testAnOrderCanBeAttachedToAGreeting(): void
    {
        $booking = $this->makeBooking();
        $gift    = $this->makeGift();

        $result = $this->service->createOrder($this->input($gift['id']), (int) $booking['id']);

        $this->assertSame((string) $booking['id'], (string) $result['giftOrder']['booking_id']);
    }

    public function testAnUnknownOrInactiveGiftCannotBeOrdered(): void
    {
        $retired = $this->makeGift(['is_active' => 0]);

        foreach ([$retired['id'], 999999] as $giftId) {
            $result = $this->service->createOrder($this->input($giftId));

            $this->assertFalse($result['success']);
            $this->assertSame('Selected gift is not available.', $result['message']);
            $this->assertNull($result['giftOrder']);
        }

        $this->assertSame(0, $this->db->table('gift_orders')->countAllResults());
    }

    public function testAnOrderWithNoGiftChosenIsRefusedNotTreatedAsEveryGift(): void
    {
        $this->makeGift();

        $result = $this->service->createOrder(['sender_name' => 'A', 'sender_phone' => '0700000000', 'recipient_name' => 'B']);

        $this->assertFalse($result['success']);
        $this->assertSame('Selected gift is not available.', $result['message']);
        $this->assertSame(0, $this->db->table('gift_orders')->countAllResults());
    }

    public function testAnOrderMissingRequiredDetailsIsRefusedWithTheReasons(): void
    {
        $gift = $this->makeGift();

        $result = $this->service->createOrder($this->input($gift['id'], ['sender_name' => '']));

        $this->assertFalse($result['success']);
        $this->assertSame('Could not create gift order.', $result['message']);
        $this->assertArrayHasKey('sender_name', $result['errors']);
        $this->assertSame(0, $this->db->table('gift_orders')->countAllResults());
    }

    // ------------------------------------------------------------------ payment

    public function testPaymentMarksAnOrderPaidButOnlyOnce(): void
    {
        $order = $this->makeGiftOrder();

        $this->assertTrue($this->service->confirmPaymentReceived((int) $order['id']));
        $this->assertSame('paid', (new GiftOrderModel())->find($order['id'])['status']);

        $this->assertFalse($this->service->confirmPaymentReceived((int) $order['id']), 'A paid order is not paid again.');
        $this->assertFalse($this->service->confirmPaymentReceived(999999));
    }

    public function testAFulfilledOrderCannotBeMarkedPaidAgain(): void
    {
        $order = $this->makeGiftOrder(['status' => 'fulfilled']);

        $this->assertFalse($this->service->confirmPaymentReceived((int) $order['id']));
        $this->assertSame('fulfilled', (new GiftOrderModel())->find($order['id'])['status']);
    }

    // ------------------------------------------------------------------ delivering

    public function testAnUnpaidOrderCannotBeFulfilled(): void
    {
        foreach (['pending_payment', 'fulfilled', 'cancelled'] as $status) {
            $order = $this->makeGiftOrder(['status' => $status]);

            $this->assertSame(
                ['success' => false, 'message' => 'This gift order is not ready to be fulfilled.'],
                $this->service->fulfill((int) $order['id'])
            );
        }

        $this->assertSame(['success' => false, 'message' => 'This gift order is not ready to be fulfilled.'], $this->service->fulfill(999999));
    }

    public function testAGiftOfOurOwnIsSimplyMarkedFulfilled(): void
    {
        $order = $this->makeGiftOrder(['status' => 'paid']);

        $this->assertSame(['success' => true, 'message' => 'Gift order marked fulfilled.'], $this->service->fulfill((int) $order['id']));
        $this->assertSame('fulfilled', (new GiftOrderModel())->find($order['id'])['status']);
    }

    public function testAVendorGiftIsOrderedFromTheVendorAndKeepsTheirReference(): void
    {
        $vendor = $this->makeVendor();
        $gift   = $this->makeGift(['type' => 'physical', 'source' => 'vendor', 'vendor_id' => $vendor['id'], 'vendor_product_ref' => 'SKU-77']);
        $order  = $this->makeGiftOrder(['gift_id' => $gift['id'], 'status' => 'paid', 'quantity' => 2, 'delivery_details' => 'Ntinda, blue gate']);

        $fake   = new FakeVendorClient(orderResult: ['success' => true, 'vendor_order_ref' => 'V-2024-9', 'message' => 'ok']);
        $result = $this->serviceWithVendor($fake)->fulfill((int) $order['id']);

        $this->assertSame(['success' => true, 'message' => 'Order placed with vendor and marked fulfilled.'], $result);

        $after = (new GiftOrderModel())->find($order['id']);
        $this->assertSame('fulfilled', $after['status']);
        $this->assertSame('V-2024-9', $after['vendor_order_ref']);

        $this->assertCount(1, $fake->orders);
        $this->assertSame('SKU-77', $fake->orders[0]['vendor_product_ref']);
        $this->assertSame(2, $fake->orders[0]['quantity']);
        $this->assertSame('Mama Ruth', $fake->orders[0]['recipient_name']);
        $this->assertSame('0700111222', $fake->orders[0]['recipient_phone']);
        $this->assertSame('Ntinda, blue gate', $fake->orders[0]['delivery_details']);
    }

    public function testAVendorWhoDeclinesLeavesTheOrderPaidAndSaysWhy(): void
    {
        $vendor = $this->makeVendor();
        $gift   = $this->makeGift(['type' => 'physical', 'source' => 'vendor', 'vendor_id' => $vendor['id'], 'vendor_product_ref' => 'SKU-1']);
        $order  = $this->makeGiftOrder(['gift_id' => $gift['id'], 'status' => 'paid']);

        $fake   = new FakeVendorClient(orderResult: ['success' => false, 'vendor_order_ref' => null, 'message' => 'Out of stock.']);
        $result = $this->serviceWithVendor($fake)->fulfill((int) $order['id']);

        $this->assertSame(['success' => false, 'message' => 'Out of stock.'], $result);
        $this->assertSame('paid', (new GiftOrderModel())->find($order['id'])['status'], 'Staff can try again.');
    }

    public function testAVendorGiftWhoseVendorIsGoneFailsCleanly(): void
    {
        // vendor_id is NULL here (as it would be if the vendor had been removed). CodeIgniter's find(null) means
        // "everything", so a careless lookup would "find" every vendor and place the order with the wrong one.
        $gift  = $this->makeGift(['type' => 'physical', 'source' => 'vendor', 'vendor_id' => null, 'vendor_product_ref' => 'SKU-1']);
        $order = $this->makeGiftOrder(['gift_id' => $gift['id'], 'status' => 'paid']);

        $fake   = new FakeVendorClient();
        $result = $this->serviceWithVendor($fake)->fulfill((int) $order['id']);

        $this->assertFalse($result['success']);
        $this->assertSame("This gift's vendor is no longer configured.", $result['message']);
        $this->assertSame([], $fake->orders, 'Nothing may have been sent to any vendor.');
        $this->assertSame('paid', (new GiftOrderModel())->find($order['id'])['status']);
    }

    // ------------------------------------------------------------------ cancelling

    public function testAnOrderNotYetDeliveredCanBeCancelled(): void
    {
        foreach (['pending_payment', 'paid'] as $status) {
            $order = $this->makeGiftOrder(['status' => $status]);

            $this->assertTrue($this->service->cancel((int) $order['id']));
            $this->assertSame('cancelled', (new GiftOrderModel())->find($order['id'])['status']);
        }
    }

    public function testADeliveredOrderCannotBeCancelled(): void
    {
        $order = $this->makeGiftOrder(['status' => 'fulfilled']);

        $this->assertFalse($this->service->cancel((int) $order['id']));
        $this->assertSame('fulfilled', (new GiftOrderModel())->find($order['id'])['status']);
        $this->assertFalse($this->service->cancel(999999));
    }

    public function testTheGiftModelStillHoldsWhatWasOrdered(): void
    {
        $gift = $this->makeGift(['price' => 5000]);
        $this->service->createOrder($this->input($gift['id']));

        // Re-pricing a gift later must not change what an existing order cost.
        (new GiftModel())->update($gift['id'], ['price' => 9999]);

        $this->assertSame('5000.00', $this->db->table('gift_orders')->get()->getRowArray()['amount']);
    }
}
