<?php

namespace Tests\Feature;

use App\Models\GiftOrderModel;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * The filters on the admin lists for payments, gift orders, shop purchases and gifts: each narrows the list to what
 * was asked for, a value the list does not know shows everything instead of failing, and the filter bar keeps what
 * was typed.
 *
 * @internal
 */
final class AdminListFiltersTest extends AppFeatureTestCase
{
    private function visible(string $uri): string
    {
        $html = $this->get($uri)->getBody();

        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    // ================================================================== payments

    public function testPaymentsCanBeSearchedByTheProvidersReference(): void
    {
        $a = $this->makePayment(['provider_reference' => 'MTN-ALPHA-111']);
        $b = $this->makePayment(['provider_reference' => 'MTN-BRAVO-222']);

        $visible = $this->visible('admin/payments?q=BRAVO');

        $this->assertStringContainsString('#' . $b['id'] . ' ', $visible);
        $this->assertStringNotContainsString('#' . $a['id'] . ' ', $visible);
    }

    public function testPaymentsCanBeFoundByTheirNumber(): void
    {
        $a = $this->makePayment();
        $this->makePayment();

        $visible = $this->visible('admin/payments?q=' . $a['id']);

        $this->assertStringContainsString('#' . $a['id'] . ' ', $visible);
    }

    public function testPaymentsCanBeFilteredByProvider(): void
    {
        $manual = $this->makePayment(['provider' => 'manual']);
        $mtn    = $this->makePayment(['provider' => 'mtn']);

        $visible = $this->visible('admin/payments?provider=mtn');

        $this->assertStringContainsString('#' . $mtn['id'] . ' ', $visible);
        $this->assertStringNotContainsString('#' . $manual['id'] . ' ', $visible);
    }

    public function testPaymentsCanBeFilteredByDate(): void
    {
        $payment = $this->makePayment();
        $today   = date('Y-m-d');
        $future  = date('Y-m-d', strtotime('+2 days'));

        $this->assertStringContainsString('#' . $payment['id'] . ' ', $this->visible("admin/payments?from={$today}&to={$today}"));
        $this->assertStringNotContainsString('#' . $payment['id'] . ' ', $this->visible("admin/payments?from={$future}"));
    }

    public function testAPaymentRangeTypedBackToFrontStillWorks(): void
    {
        $payment = $this->makePayment();
        $today   = date('Y-m-d');
        $past    = date('Y-m-d', strtotime('-3 days'));

        $this->assertStringContainsString('#' . $payment['id'] . ' ', $this->visible("admin/payments?from={$today}&to={$past}"));
    }

    public function testAnUnknownPaymentFilterShowsEverythingRatherThanAnError(): void
    {
        $payment = $this->makePayment();

        $this->get('admin/payments?status=%27%3B+DROP&provider=nobody&from=banana&page=zzz')->assertOK();
        $this->assertStringContainsString('#' . $payment['id'] . ' ', $this->visible('admin/payments?status=%27%3B+DROP&from=banana&page=zzz'));
    }

    public function testThePaymentFilterBarKeepsWhatWasTyped(): void
    {
        $this->makePayment(['provider_reference' => 'KEEP-ME-1']);

        $body = $this->get('admin/payments?q=KEEP-ME')->getBody();

        $this->assertStringContainsString('value="KEEP-ME"', $body);
    }

    // ================================================================== gift orders

    public function testGiftOrdersCanBeSearchedByRecipientSenderOrGift(): void
    {
        $this->makeGiftOrder(['recipient_name' => 'Amina Kato']);
        $this->makeGiftOrder(['recipient_name' => 'Brian Okello', 'sender_name' => 'Zed Sender']);

        $byRecipient = $this->visible('admin/gift-orders?q=Amina');
        $this->assertStringContainsString('Amina Kato', $byRecipient);
        $this->assertStringNotContainsString('Brian Okello', $byRecipient);

        $bySender = $this->visible('admin/gift-orders?q=Zed');
        $this->assertStringContainsString('Brian Okello', $bySender);
        $this->assertStringNotContainsString('Amina Kato', $bySender);
    }

    public function testGiftOrdersCanBeFilteredByWhoSuppliesTheGift(): void
    {
        $vendor = $this->makeVendor();
        $ours   = $this->makeGiftOrder(['recipient_name' => 'Ours Only']);
        $theirs = $this->makeGiftOrder([
            'recipient_name' => 'Vendor Only',
            'gift_id'        => $this->makeGift(['source' => 'vendor', 'vendor_id' => $vendor['id'], 'vendor_product_ref' => 'p-1'])['id'],
        ]);

        $vendorOnly = $this->visible('admin/gift-orders?source=vendor');
        $this->assertStringContainsString('Vendor Only', $vendorOnly);
        $this->assertStringNotContainsString('Ours Only', $vendorOnly);
        $this->assertNotSame($ours['id'], $theirs['id']);
    }

    public function testGiftOrderFiltersCombine(): void
    {
        $this->makeGiftOrder(['recipient_name' => 'Paid Amina', 'status' => GiftOrderModel::STATUS_PAID]);
        $this->makeGiftOrder(['recipient_name' => 'Unpaid Amina']);
        $this->makeGiftOrder(['recipient_name' => 'Paid Brian', 'status' => GiftOrderModel::STATUS_PAID]);

        $visible = $this->visible('admin/gift-orders?q=Amina&status=paid');

        $this->assertStringContainsString('Paid Amina', $visible);
        $this->assertStringNotContainsString('Unpaid Amina', $visible);
        $this->assertStringNotContainsString('Paid Brian', $visible);
    }

    public function testAGiftOrderSearchTreatsWildcardsAsPlainText(): void
    {
        $this->makeGiftOrder(['recipient_name' => 'Plain Name']);

        $this->assertStringNotContainsString('Plain Name', $this->visible('admin/gift-orders?q=%25%25%25xyz'));
    }

    // ================================================================== shop purchases

    public function testShopPurchasesCanBeFilteredByShopAndSearched(): void
    {
        $one = $this->makeVendor(['name' => 'Roses Ltd']);
        $two = $this->makeVendor(['name' => 'Hampers Inc']);
        $a   = $this->makeCheckout(['vendor_id' => $one['id']]);
        $b   = $this->makeCheckout(['vendor_id' => $two['id']]);

        $byShop = $this->get('admin/gift-purchases?vendor=' . $two['id'])->getBody();
        $this->assertStringContainsString($b['purchase_reference'], $byShop);
        $this->assertStringNotContainsString($a['purchase_reference'], $byShop);

        $byReference = $this->get('admin/gift-purchases?q=' . urlencode($a['purchase_reference']))->getBody();
        $this->assertStringContainsString($a['purchase_reference'], $byReference);
        $this->assertStringNotContainsString($b['purchase_reference'], $byReference);

        $byShopName = $this->get('admin/gift-purchases?q=Hampers')->getBody();
        $this->assertStringContainsString($b['purchase_reference'], $byShopName);
        $this->assertStringNotContainsString($a['purchase_reference'], $byShopName);
    }

    // ================================================================== gifts

    public function testGiftsCanBeSearchedAndFilteredByTypeAndAvailability(): void
    {
        $this->makeGift(['name' => 'Rose card', 'type' => 'ecard']);
        $this->makeGift(['name' => 'Ten thousand airtime', 'type' => 'airtime']);
        $this->makeGift(['name' => 'Retired card', 'type' => 'ecard', 'is_active' => 0]);

        $byName = $this->visible('admin/gifts?q=Rose');
        $this->assertStringContainsString('Rose card', $byName);
        $this->assertStringNotContainsString('Ten thousand airtime', $byName);

        $byType = $this->visible('admin/gifts?type=airtime');
        $this->assertStringContainsString('Ten thousand airtime', $byType);
        $this->assertStringNotContainsString('Rose card', $byType);

        $inactive = $this->visible('admin/gifts?active=0');
        $this->assertStringContainsString('Retired card', $inactive);
        $this->assertStringNotContainsString('Rose card', $inactive);
    }

    public function testChoosingAVendorShowsOnlyThatVendorsGifts(): void
    {
        $vendor = $this->makeVendor();
        $this->makeGift(['name' => 'Our own thing']);
        $this->makeGift(['name' => 'Vendor thing', 'source' => 'vendor', 'vendor_id' => $vendor['id'], 'vendor_product_ref' => 'p-9']);

        $visible = $this->visible('admin/gifts?vendor=' . $vendor['id']);

        $this->assertStringContainsString('Vendor thing', $visible);
        $this->assertStringNotContainsString('Our own thing', $visible);
    }
}
