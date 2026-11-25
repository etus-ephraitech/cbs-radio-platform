<?php

namespace Tests\Database\Services;

use App\Libraries\VendorGiftClients\HostedCheckoutClientInterface;
use App\Models\GiftModel;
use App\Models\GiftVendorModel;
use App\Models\VendorCheckoutModel;
use App\Services\HostedCheckoutService;
use Tests\Support\Libraries\FakeHostedClient;
use Tests\Support\TestCase\AppTestCase;

/**
 * Buying gifts through a vendor's hosted checkout: checking the cart with the vendor, opening the session, and
 * learning (only ever from the vendor) whether it was paid.
 *
 * @internal
 */
final class HostedCheckoutServiceTest extends AppTestCase
{
    /** @var array<string, mixed> */
    private array $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = $this->makeKatale();
    }

    private function service(FakeHostedClient $client): HostedCheckoutService
    {
        return new class ($client) extends HostedCheckoutService {
            public function __construct(private FakeHostedClient $client)
            {
                parent::__construct();
            }

            protected function hostedClient(array $vendor): HostedCheckoutClientInterface
            {
                return $this->client;
            }
        };
    }

    /**
     * A gift in our catalogue and the same gift as the vendor holds it.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [our row, the vendor's gift]
     */
    private function gift(string $ref, array $ours = [], array $theirs = []): array
    {
        return [
            $this->makeHostedGift($ours + ['vendor_id' => $this->vendor['id'], 'vendor_product_ref' => $ref, 'name' => "Gift {$ref}"]),
            FakeHostedClient::gift($ref, $theirs + ['inventories' => [['id' => (string) ($ref + 1000), 'quantity' => 10]]]),
        ];
    }

    private function checkoutCount(): int
    {
        return $this->db->table('vendor_checkouts')->countAllResults();
    }

    /**
     * @return array<string, mixed>
     */
    private function latestCheckout(): array
    {
        return $this->db->table('vendor_checkouts')->orderBy('id', 'DESC')->get()->getRowArray();
    }

    /**
     * @param array<string, mixed> $status   what the vendor says about the session
     * @param array<string, mixed> $override fields of the stored purchase
     */
    private function paidStatus(array $status = []): array
    {
        return FakeHostedClient::ok($status + [
            'state' => 'paid', 'paid' => true, 'order_id' => '1001', 'reference' => 'KATALE-REFERENCE',
            'amount' => 170000.0, 'currency' => 'UGX', 'expires_at' => '2026-12-25 13:30:00',
        ]);
    }

    // ================================================================== starting a checkout

    public function testACartIsCheckedWithTheVendorAndOpenedAsACheckout(): void
    {
        [$ours, $theirs] = $this->gift('123', [], ['price' => 45000.0]);
        $client          = new FakeHostedClient(['123' => $theirs]);

        $result = $this->service($client)->start([$ours['id'] => 2]);

        $this->assertTrue($result['success']);
        $checkout = $result['checkout'];
        $this->assertSame('open', $checkout['state']);
        $this->assertSame('9b0f7ac0-34e8-4f2c-8d27-1f5c9e6a023b', $checkout['session_id']);
        $this->assertSame('https://kataleplus.example.test/partner-checkout/TOKEN', $checkout['checkout_url']);
        $this->assertNotNull($checkout['expires_at']);
        $this->assertSame('90000.00', $checkout['estimate_total']);
        $this->assertSame('0', (string) $checkout['paid']);

        $this->assertSame([['gift_id' => 123, 'inventory_id' => 1123, 'quantity' => 2]], $client->sessions[0]['items']);
        $this->assertSame($checkout['idempotency_key'], $client->sessions[0]['key']);
        $this->assertSame([['gift_id' => 123, 'inventory_id' => 1123, 'quantity' => 2, 'title' => 'Gift 123', 'unit_price' => 45000.0, 'line_total' => 90000.0]], VendorCheckoutModel::lines($checkout));
    }

    public function testThePriceUsedIsTheVendorsPriceNowNotTheOneWeSyncedEarlier(): void
    {
        [$ours, $theirs] = $this->gift('123', ['price' => 30000], ['price' => 45000.0]);

        $result = $this->service(new FakeHostedClient(['123' => $theirs]))->start([$ours['id'] => 1]);

        $this->assertSame('45000.00', $result['checkout']['estimate_total']);
    }

    public function testSeveralGiftsGoInOneCheckout(): void
    {
        [$a, $ta] = $this->gift('123', [], ['price' => 45000.0]);
        [$b, $tb] = $this->gift('124', [], ['price' => 80000.0]);
        $client   = new FakeHostedClient(['123' => $ta, '124' => $tb]);

        $result = $this->service($client)->start([$a['id'] => 2, $b['id'] => 1]);

        $this->assertSame('170000.00', $result['checkout']['estimate_total']);
        $this->assertSame([123, 124], array_column($client->sessions[0]['items'], 'gift_id'));
        $this->assertSame(1, $this->checkoutCount());
    }

    public function testEachPurchaseGetsItsOwnTokenReferenceAndKey(): void
    {
        [$ours, $theirs] = $this->gift('123');
        $service         = $this->service(new FakeHostedClient(['123' => $theirs]));

        $first  = $service->start([$ours['id'] => 1])['checkout'];
        $second = $service->start([$ours['id'] => 1])['checkout'];

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $first['public_token']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{8,100}$/', $first['idempotency_key'], 'The vendor accepts only 8 to 100 letters, digits, underscores and hyphens.');
        $this->assertMatchesRegularExpression('/^CBSG-[0-9A-F]{8}$/', $first['purchase_reference']);
        $this->assertNotSame($first['public_token'], $second['public_token']);
        $this->assertNotSame($first['idempotency_key'], $second['idempotency_key'], 'Two checkouts must never share a key, or the vendor would hand back the first.');
        $this->assertNotSame($first['purchase_reference'], $second['purchase_reference']);
    }

    public function testAnHttpsReturnAddressIsSentAndAnHttpOneIsLeftOut(): void
    {
        [$ours, $theirs] = $this->gift('123');
        $client          = new FakeHostedClient(['123' => $theirs]);
        $service         = $this->service($client);

        $service->start([$ours['id'] => 1], static fn (string $token): string => 'https://cbs.example.test/gifts/checkout/' . $token);
        $service->start([$ours['id'] => 1], static fn (string $token): string => 'http://localhost:8080/gifts/checkout/' . $token);
        $service->start([$ours['id'] => 1]);

        $this->assertMatchesRegularExpression('#^https://cbs\.example\.test/gifts/checkout/[a-f0-9]{32}$#', $client->sessions[0]['returnUrl']);
        $this->assertNull($client->sessions[1]['returnUrl'], 'The vendor rejects plain http, so it is left out and its registered address is used.');
        $this->assertNull($client->sessions[2]['returnUrl']);
    }

    public function testTheInventoryWithEnoughUnitsIsChosen(): void
    {
        [$ours, $theirs] = $this->gift('123', [], ['inventories' => [['id' => '1', 'quantity' => 2], ['id' => '2', 'quantity' => 8], ['id' => '3', 'quantity' => 5]]]);
        $client          = new FakeHostedClient(['123' => $theirs]);

        $this->service($client)->start([$ours['id'] => 4]);

        $this->assertSame(2, $client->sessions[0]['items'][0]['inventory_id'], 'Not the first, and not one that is too small: the one with the most units.');
    }

    // ------------------------------------------------------------------ a cart that cannot be bought

    public function testAnEmptyCartIsRefusedWithoutAskingTheVendor(): void
    {
        $client = new FakeHostedClient();

        $result = $this->service($client)->start([]);

        $this->assertFalse($result['success']);
        $this->assertSame('Your cart is empty.', $result['message']);
        $this->assertSame([], $client->sessions);
        $this->assertSame(0, $this->checkoutCount());
    }

    public function testAGiftThatIsSwitchedOffIsRefused(): void
    {
        [$ours, $theirs] = $this->gift('123', ['is_active' => 0]);
        $client          = new FakeHostedClient(['123' => $theirs]);

        $result = $this->service($client)->start([$ours['id'] => 1]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no longer available', $result['message']);
        $this->assertSame([], $client->sessions);
    }

    public function testAGiftOfOurOwnCannotBeSentToTheVendor(): void
    {
        $own    = $this->makeGift();
        $client = new FakeHostedClient();

        $result = $this->service($client)->start([$own['id'] => 1]);

        $this->assertFalse($result['success']);
        $this->assertSame([], $client->sessions);
    }

    public function testAGiftFromAGenericVendorCannotBeSentToAHostedCheckout(): void
    {
        $generic = $this->makeVendor(['slug' => 'acme']);
        $gift    = $this->makeHostedGift(['vendor_id' => $generic['id']]);

        $this->assertFalse($this->service(new FakeHostedClient())->start([$gift['id'] => 1])['success']);
    }

    public function testAGiftWhoseShopIsSwitchedOffIsRefused(): void
    {
        [$ours, $theirs] = $this->gift('123');
        (new GiftVendorModel())->update($this->vendor['id'], ['is_active' => 0]);

        $this->assertFalse($this->service(new FakeHostedClient(['123' => $theirs]))->start([$ours['id'] => 1])['success']);
    }

    public function testGiftsFromDifferentShopsAreNotMixedInOneCheckout(): void
    {
        [$a, $ta] = $this->gift('123');
        $other    = $this->makeKatale(['name' => 'Katale 2', 'slug' => 'katale-2']);
        $b        = $this->makeHostedGift(['vendor_id' => $other['id'], 'vendor_product_ref' => '124']);
        $client   = new FakeHostedClient(['123' => $ta, '124' => FakeHostedClient::gift('124')]);

        $result = $this->service($client)->start([$a['id'] => 1, $b['id'] => 1]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('one shop at a time', $result['message']);
        $this->assertSame([], $client->sessions);
    }

    public function testMoreThanIsInStockIsRefusedWithHowManyAreLeft(): void
    {
        [$ours, $theirs] = $this->gift('123', [], ['inventories' => [['id' => '1', 'quantity' => 3]]]);
        $client          = new FakeHostedClient(['123' => $theirs]);

        $result = $this->service($client)->start([$ours['id'] => 5]);

        $this->assertFalse($result['success']);
        $this->assertSame('Only 3 of "Gift 123" left. Please lower the quantity.', $result['message']);
        $this->assertSame([], $client->sessions);
        $this->assertSame(0, $this->checkoutCount(), 'A cart that was never sent leaves no purchase behind.');
    }

    public function testAGiftThatIsOutOfStockIsRefused(): void
    {
        [$ours, $theirs] = $this->gift('123', [], ['inventories' => [['id' => '1', 'quantity' => 0]]]);

        $result = $this->service(new FakeHostedClient(['123' => $theirs]))->start([$ours['id'] => 1]);

        $this->assertSame('"Gift 123" is out of stock. Please remove it from your cart.', $result['message']);
    }

    public function testAGiftWithNoInventoriesIsOutOfStock(): void
    {
        [$ours, $theirs] = $this->gift('123', [], ['inventories' => []]);

        $result = $this->service(new FakeHostedClient(['123' => $theirs]))->start([$ours['id'] => 1]);

        $this->assertStringContainsString('out of stock', $result['message']);
    }

    public function testAGiftTheVendorNoLongerSellsIsRefused(): void
    {
        [$ours] = $this->gift('123');

        $result = $this->service(new FakeHostedClient([]))->start([$ours['id'] => 1]);

        $this->assertSame('"Gift 123" is no longer sold. Please remove it from your cart.', $result['message']);
    }

    public function testAVendorThatCannotBeAskedAboutStockStopsTheCheckout(): void
    {
        [$ours] = $this->gift('123');
        $client = new FakeHostedClient(failGiftWith: 'timed out');

        $result = $this->service($client)->start([$ours['id'] => 1]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('try again in a minute', $result['message']);
        $this->assertStringNotContainsString('timed out', $result['message']);
        $this->assertSame([], $client->sessions, 'A cart that could not be checked is never sent on trust.');
    }

    public function testAGiftThatHasNoPriceRightNowIsRefused(): void
    {
        [$ours, $theirs] = $this->gift('123', [], ['price' => 0.0]);

        $this->assertFalse($this->service(new FakeHostedClient(['123' => $theirs]))->start([$ours['id'] => 1])['success']);
    }

    public function testGiftsPricedInDifferentCurrenciesAreRefused(): void
    {
        [$a, $ta] = $this->gift('123', [], ['currency' => 'UGX']);
        [$b, $tb] = $this->gift('124', [], ['currency' => 'USD']);

        $result = $this->service(new FakeHostedClient(['123' => $ta, '124' => $tb]))->start([$a['id'] => 1, $b['id'] => 1]);

        $this->assertStringContainsString('different currencies', $result['message']);
    }

    // ------------------------------------------------------------------ the vendor refusing or failing

    public function testAnAnswerThatNeverArrivedIsRetriedOnceWithTheSameKeyAndCart(): void
    {
        [$ours, $theirs] = $this->gift('123');
        $client          = new FakeHostedClient(['123' => $theirs], sessionQueue: [FakeHostedClient::failure(0, 'timed out', true)]);

        $result = $this->service($client)->start([$ours['id'] => 2]);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $client->sessions);
        $this->assertSame($client->sessions[0]['key'], $client->sessions[1]['key'], 'The same key, so that a first attempt that did arrive is not opened twice.');
        $this->assertSame($client->sessions[0]['items'], $client->sessions[1]['items']);
        $this->assertSame(1, $this->checkoutCount());
    }

    public function testAVendorThatKeepsFailingLeavesAFailedPurchaseAndATellingMessage(): void
    {
        [$ours, $theirs] = $this->gift('123');
        $client          = new FakeHostedClient(['123' => $theirs], session: FakeHostedClient::failure(503, 'Katale could not start the checkout (status 503).', true));

        $result = $this->service($client)->start([$ours['id'] => 1]);

        $this->assertFalse($result['success']);
        $this->assertCount(2, $client->sessions, 'One try and one retry, not a loop.');
        $this->assertSame('failed', $this->latestCheckout()['state']);
        $this->assertSame('Katale could not start the checkout (status 503).', $this->latestCheckout()['last_error']);
        $this->assertNull($this->latestCheckout()['session_id']);
    }

    public function testARefusalIsShownAndNotRetried(): void
    {
        [$ours, $theirs] = $this->gift('123');
        $client          = new FakeHostedClient(['123' => $theirs], session: FakeHostedClient::failure(422, 'The items.0.quantity field must be at least 1.'));

        $result = $this->service($client)->start([$ours['id'] => 1]);

        $this->assertSame('The items.0.quantity field must be at least 1.', $result['message']);
        $this->assertCount(1, $client->sessions, 'The same invalid cart is not sent again.');
        $this->assertSame('failed', $this->latestCheckout()['state']);
    }

    public function testACheckoutAddressThatIsNotHttpsIsNeverUsed(): void
    {
        [$ours, $theirs] = $this->gift('123');
        $client          = new FakeHostedClient(['123' => $theirs], session: FakeHostedClient::ok([
            'session_id' => 's', 'checkout_url' => 'http://evil.example.test/', 'expires_at' => null, 'currency' => 'UGX',
        ]));

        $result = $this->service($client)->start([$ours['id'] => 1]);

        $this->assertFalse($result['success']);
        $this->assertNull($this->latestCheckout()['checkout_url']);
        $this->assertSame('failed', $this->latestCheckout()['state']);
    }

    // ================================================================== asking about a purchase

    public function testAPurchaseStillWaitingStaysWaitingAndIsMarkedAsChecked(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        $client   = new FakeHostedClient();

        $after = $this->service($client)->status($checkout);

        $this->assertSame('awaiting_payment', $after['state']);
        $this->assertSame('0', (string) $after['paid']);
        $this->assertNotNull($after['last_checked_at']);
        $this->assertSame([$checkout['session_id']], $client->statusCalls);
        $this->assertSame([], $client->orderCalls, 'There is no order to read before there is one.');
    }

    public function testPaymentIsRecordedOnlyWhenTheVendorSaysPaid(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        $client   = new FakeHostedClient(status: $this->paidStatus());

        $after = $this->service($client)->status($checkout);

        $this->assertSame('paid', $after['state']);
        $this->assertSame('1', (string) $after['paid']);
        $this->assertSame('1001', $after['vendor_order_id']);
        $this->assertSame('KATALE-REFERENCE', $after['vendor_reference']);
        $this->assertSame('170000.00', $after['final_amount']);
        $this->assertSame(['1001'], $client->orderCalls);
        $this->assertSame('pending', VendorCheckoutModel::order($after)['deliveries'][0]['state']);
    }

    public function testAnOrderIdAloneIsNotPayment(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        $client   = new FakeHostedClient(status: FakeHostedClient::ok([
            'state' => 'awaiting_payment', 'paid' => false, 'order_id' => '1001', 'reference' => 'KATALE-REFERENCE', 'amount' => 170000.0, 'currency' => 'UGX', 'expires_at' => null,
        ]));

        $after = $this->service($client)->status($checkout);

        $this->assertSame('awaiting_payment', $after['state']);
        $this->assertSame('0', (string) $after['paid']);
        $this->assertSame('1001', $after['vendor_order_id']);
    }

    public function testPaidStateWithoutThePaidFlagIsNotPayment(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        $client   = new FakeHostedClient(status: $this->paidStatus(['paid' => false]));

        $after = $this->service($client)->status($checkout);

        $this->assertSame(['open', '0'], [$after['state'], (string) $after['paid']], 'Both the state and the flag must say paid.');
    }

    public function testAPurchaseThatWasPaidIsNeverPutBackToUnpaid(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'paid', 'paid' => 1, 'vendor_order_id' => '1001']);
        $client   = new FakeHostedClient(status: FakeHostedClient::ok(['state' => 'awaiting_payment', 'paid' => false, 'order_id' => '1001', 'reference' => null, 'amount' => null, 'currency' => 'UGX', 'expires_at' => null]));

        $after = $this->service($client)->status($checkout);

        $this->assertSame(['paid', '1'], [$after['state'], (string) $after['paid']]);
    }

    public function testAPaidPurchaseCanStillBeCancelledByTheVendor(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'paid', 'paid' => 1, 'vendor_order_id' => '1001']);
        $client   = new FakeHostedClient(status: FakeHostedClient::ok(['state' => 'cancelled', 'paid' => true, 'order_id' => '1001', 'reference' => null, 'amount' => null, 'currency' => 'UGX', 'expires_at' => null]));

        $after = $this->service($client)->status($checkout);

        $this->assertSame('cancelled', $after['state']);
        $this->assertSame('1', (string) $after['paid'], 'Cancelled does not say the money was returned, so what was paid is not forgotten.');
    }

    public function testAStateWeDoNotKnowIsIgnored(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        $client   = new FakeHostedClient(status: FakeHostedClient::ok(['state' => 'teleported', 'paid' => false, 'order_id' => null, 'reference' => null, 'amount' => null, 'currency' => 'UGX', 'expires_at' => null]));

        $this->assertSame('open', $this->service($client)->status($checkout)['state']);
    }

    public function testAnExpiredSessionIsMarkedExpired(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        $client   = new FakeHostedClient(status: FakeHostedClient::failure(410, 'Gone'));

        $this->assertSame('expired', $this->service($client)->status($checkout)['state']);
    }

    public function testAVendorThatCannotBeReachedLeavesThePurchaseAsItWasAndSaysSo(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment']);
        $client   = new FakeHostedClient(status: FakeHostedClient::failure(0, 'Katale could not report on this checkout: it could not be reached.', true));

        $after = $this->service($client)->status($checkout);

        $this->assertSame('awaiting_payment', $after['state']);
        $this->assertSame('Katale could not report on this checkout: it could not be reached.', $after['last_error']);
    }

    public function testASuccessfulAnswerClearsAnEarlierError(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'last_error' => 'earlier trouble']);

        $this->assertNull($this->service(new FakeHostedClient())->status($checkout)['last_error']);
    }

    public function testAPageRefreshedInQuickSuccessionDoesNotFloodTheVendor(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        $client   = new FakeHostedClient();
        $service  = $this->service($client);

        $once = $service->status($checkout);
        $service->status($once);
        $service->status($once);

        $this->assertCount(1, $client->statusCalls, 'Asked once, not three times.');
    }

    public function testAnAdminCanAskAgainAtOnce(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        $client   = new FakeHostedClient();
        $service  = $this->service($client);

        $once = $service->status($checkout);
        $service->status($once, true);

        $this->assertCount(2, $client->statusCalls);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function purchasesThatAreOver(): array
    {
        return [
            'cancelled'        => [['state' => 'cancelled']],
            'expired'          => [['state' => 'expired']],
            'failed'           => [['state' => 'failed', 'session_id' => null]],
            'never got a session' => [['state' => 'draft', 'session_id' => null]],
            'paid and delivered' => [['state' => 'paid', 'paid' => 1, 'order_details' => '{"deliveries":[{"state":"delivered"},{"state":"cancelled"}]}']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('purchasesThatAreOver')]
    public function testAPurchaseThatCannotChangeAnymoreIsNotAskedAbout(array $overrides): void
    {
        $checkout = $this->makeCheckout($overrides + ['vendor_id' => $this->vendor['id']]);
        $client   = new FakeHostedClient();

        $after = $this->service($client)->status($checkout);

        $this->assertSame([], $client->statusCalls);
        $this->assertSame($checkout['state'], $after['state']);
    }

    public function testAPaidPurchaseWithDeliveryStillMovingIsAskedAbout(): void
    {
        $checkout = $this->makeCheckout([
            'vendor_id' => $this->vendor['id'], 'state' => 'paid', 'paid' => 1, 'vendor_order_id' => '1001',
            'order_details' => '{"deliveries":[{"state":"preparing"}]}',
        ]);
        $client = new FakeHostedClient(status: $this->paidStatus(), order: FakeHostedClient::ok([
            'id' => '1001', 'paid' => true, 'cancelled' => false, 'status' => 2,
            'deliveries' => [['title' => 'Flower Bouquet', 'quantity' => 2, 'requested_delivery_at' => null, 'minimum_delivery_hours' => 24, 'state' => 'on_the_way']],
        ]));

        $after = $this->service($client)->status($checkout);

        $this->assertSame('on_the_way', VendorCheckoutModel::order($after)['deliveries'][0]['state']);
    }

    // ================================================================== the scheduled check

    public function testTheScheduledCheckFindsAPurchaseThatWasPaidWhileTheVisitorWasAway(): void
    {
        $waiting = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment']);
        $client  = new FakeHostedClient(status: $this->paidStatus());

        $result = $this->service($client)->reconcile();

        $this->assertSame(['checked' => 1, 'paid' => 1, 'changed' => 1], $result);
        $this->assertSame('paid', (new VendorCheckoutModel())->find($waiting['id'])['state']);
    }

    public function testTheScheduledCheckLeavesFinishedAndNeverStartedPurchasesAlone(): void
    {
        $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'cancelled']);
        $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'expired']);
        $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'failed', 'session_id' => null]);
        $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'draft', 'session_id' => null]);
        $client = new FakeHostedClient();

        $this->assertSame(['checked' => 0, 'paid' => 0, 'changed' => 0], $this->service($client)->reconcile());
        $this->assertSame([], $client->statusCalls);
    }

    public function testTheScheduledCheckStopsAtItsLimitAndTakesTheLongestUncheckedFirst(): void
    {
        $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment', 'session_id' => 'recent', 'last_checked_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
        $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment', 'session_id' => 'old', 'last_checked_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))]);
        $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment', 'session_id' => 'never', 'last_checked_at' => null]);
        $client = new FakeHostedClient();

        $this->assertSame(2, $this->service($client)->reconcile(2)['checked']);
        $this->assertSame(['never', 'old'], $client->statusCalls);
    }

    public function testTheScheduledCheckIgnoresPurchasesOlderThanTwoWeeksThatAreStillWaiting(): void
    {
        $stale = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment']);
        $this->db->table('vendor_checkouts')->where('id', $stale['id'])->update(['created_at' => date('Y-m-d H:i:s', strtotime('-15 days'))]);
        $client = new FakeHostedClient();

        $this->assertSame(0, $this->service($client)->reconcile()['checked']);
    }

    public function testAPurchaseThatStaysUnchangedIsCheckedButNotCountedAsChanged(): void
    {
        $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment']);

        $this->assertSame(['checked' => 1, 'paid' => 0, 'changed' => 0], $this->service(new FakeHostedClient())->reconcile());
    }

    public function testGiftRowsAreNotTouchedByChecking(): void
    {
        [$ours] = $this->gift('123');
        $before = (new GiftModel())->find($ours['id']);
        $this->makeCheckout(['vendor_id' => $this->vendor['id']]);

        $this->service(new FakeHostedClient())->reconcile();

        $this->assertSame($before, (new GiftModel())->find($ours['id']));
    }
}
