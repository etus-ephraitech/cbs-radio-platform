<?php

namespace Tests\Database\Models;

use App\Models\GiftModel;
use App\Models\GiftOrderModel;
use Tests\Support\TestCase\AppTestCase;

/**
 * The gift catalogue, and the orders placed against it.
 *
 * @internal
 */
final class GiftModelsTest extends AppTestCase
{
    // ------------------------------------------------------------------ gifts

    public function testActiveGiftsAreSortedByNameAndCarryTheirVendorsName(): void
    {
        $vendor = $this->makeVendor(['name' => 'Acme Flowers']);

        $this->makeGift(['name' => 'Rose bouquet', 'type' => 'physical', 'source' => 'vendor', 'vendor_id' => $vendor['id']]);
        $this->makeGift(['name' => 'Birthday e-card']);
        $this->makeGift(['name' => 'Retired', 'is_active' => 0]);

        $gifts = (new GiftModel())->active();

        $this->assertSame(['Birthday e-card', 'Rose bouquet'], array_column($gifts, 'name'));
        $this->assertNull($gifts[0]['vendor_name'], 'A gift of our own has no vendor.');
        $this->assertSame('Acme Flowers', $gifts[1]['vendor_name']);
    }

    public function testGiftsCanBeFilteredByTypeAmongActiveOnes(): void
    {
        $this->makeGift(['name' => 'Card', 'type' => 'ecard']);
        $this->makeGift(['name' => 'Airtime 5k', 'type' => 'airtime']);
        $this->makeGift(['name' => 'Old card', 'type' => 'ecard', 'is_active' => 0]);

        $this->assertSame(['Card'], array_column((new GiftModel())->byType('ecard'), 'name'));
    }

    public function testOurOwnGiftsAndVendorGiftsAreListedSeparately(): void
    {
        $vendor = $this->makeVendor(['name' => 'Zed']);

        $this->makeGift(['name' => 'Ours B']);
        $this->makeGift(['name' => 'Ours A']);
        $this->makeGift(['name' => 'Theirs', 'type' => 'physical', 'source' => 'vendor', 'vendor_id' => $vendor['id']]);

        $model = new GiftModel();

        $this->assertSame(['Ours A', 'Ours B'], array_column($model->localOnly(), 'name'));
        $this->assertSame(['Theirs'], array_column($model->vendorSourced(), 'name'));
        $this->assertSame('Zed', $model->vendorSourced()[0]['vendor_name']);
    }

    public function testAVendorGiftIsFoundByItsVendorsProductReference(): void
    {
        $vendor = $this->makeVendor();
        $other  = $this->makeVendor();

        $gift = $this->makeGift(['type' => 'physical', 'source' => 'vendor', 'vendor_id' => $vendor['id'], 'vendor_product_ref' => 'SKU-1']);

        $model = new GiftModel();

        $this->assertSame($gift['id'], $model->findByVendorProduct((int) $vendor['id'], 'SKU-1')['id']);
        $this->assertNull($model->findByVendorProduct((int) $other['id'], 'SKU-1'), 'The same reference at another vendor is a different product.');
        $this->assertNull($model->findByVendorProduct((int) $vendor['id'], 'SKU-2'));
    }

    public function testEveryGiftTypeIsAccepted(): void
    {
        foreach (['ecard', 'airtime', 'shoutout', 'dedication', 'physical'] as $type) {
            $this->assertNotFalse((new GiftModel())->insert(['name' => "A {$type}", 'type' => $type, 'price' => 100]), $type);
        }
    }

    public function testAnUnknownGiftTypeOrSourceIsRefused(): void
    {
        $model = new GiftModel();

        $this->assertFalse($model->insert(['name' => 'X', 'type' => 'puppy', 'price' => 100]));
        $this->assertFalse($model->insert(['name' => 'X', 'type' => 'ecard', 'price' => 100, 'source' => 'stolen']));
    }

    // ------------------------------------------------------------------ gift orders

    public function testStandaloneOrdersAreThoseNotAttachedToABooking(): void
    {
        $booking = $this->makeBooking();

        $alone    = $this->makeGiftOrder(['recipient_name' => 'On its own']);
        $attached = $this->makeGiftOrder(['recipient_name' => 'With a greeting', 'booking_id' => $booking['id']]);

        $model = new GiftOrderModel();

        $this->assertSame([$alone['id']], array_column($model->standalone(), 'id'));
        $this->assertSame([$attached['id']], array_column($model->forBooking((int) $booking['id']), 'id'));
    }

    public function testTheAdminListCarriesTheGiftAndFiltersByStatus(): void
    {
        $gift = $this->makeGift(['name' => 'Birthday e-card']);

        $paid    = $this->makeGiftOrder(['gift_id' => $gift['id'], 'status' => GiftOrderModel::STATUS_PAID]);
        $pending = $this->makeGiftOrder(['gift_id' => $gift['id']]);

        $model = new GiftOrderModel();

        $all = $model->adminList();
        $this->assertCount(2, $all);
        $this->assertSame('Birthday e-card', $all[0]['gift_name']);
        $this->assertSame('local', $all[0]['gift_source']);

        $this->assertSame([$paid['id']], array_column($model->adminList('paid'), 'id'));
        $this->assertSame([$pending['id']], array_column($model->adminList('pending_payment'), 'id'));
    }

    public function testABookingsOrdersComeWithTheGiftsName(): void
    {
        $booking = $this->makeBooking();
        $gift    = $this->makeGift(['name' => 'Rose']);

        $this->makeGiftOrder(['gift_id' => $gift['id'], 'booking_id' => $booking['id']]);

        $rows = (new GiftOrderModel())->forBookingWithGift((int) $booking['id']);

        $this->assertCount(1, $rows);
        $this->assertSame('Rose', $rows[0]['gift_name']);
    }

    public function testAnOrderMovesThroughItsStatuses(): void
    {
        $order = $this->makeGiftOrder();
        $model = new GiftOrderModel();

        $model->markPaid($order['id']);
        $this->assertSame('paid', $model->find($order['id'])['status']);

        $model->markFulfilled($order['id']);
        $after = $model->find($order['id']);
        $this->assertSame('fulfilled', $after['status']);
        $this->assertNull($after['vendor_order_ref'], 'An order of our own has no vendor reference.');
    }

    public function testFulfillingAVendorOrderKeepsTheVendorsReference(): void
    {
        $order = $this->makeGiftOrder();

        (new GiftOrderModel())->markFulfilled($order['id'], 'V-777');

        $this->assertSame('V-777', (new GiftOrderModel())->find($order['id'])['vendor_order_ref']);
    }

    public function testAnOrderCanBeCancelled(): void
    {
        $order = $this->makeGiftOrder();

        (new GiftOrderModel())->markCancelled($order['id']);

        $this->assertSame('cancelled', (new GiftOrderModel())->find($order['id'])['status']);
    }

    public function testAnOrderNeedsAGiftASenderARecipientAndAnAmount(): void
    {
        $model = new GiftOrderModel();
        $gift  = $this->makeGift();

        $this->assertFalse($model->insert(['sender_name' => 'A', 'sender_phone' => '1', 'recipient_name' => 'B', 'amount' => 1]));
        $this->assertArrayHasKey('gift_id', $model->errors());

        foreach (['sender_name', 'sender_phone', 'recipient_name', 'amount'] as $field) {
            $data = ['gift_id' => $gift['id'], 'sender_name' => 'A', 'sender_phone' => '0700000000', 'recipient_name' => 'B', 'amount' => 100];
            unset($data[$field]);

            $this->assertFalse($model->insert($data), "An order with no {$field} should be refused.");
        }
    }
}
