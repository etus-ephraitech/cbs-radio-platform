<?php

namespace Tests\Feature;

use App\Models\GiftVendorModel;
use App\Models\VendorCheckoutModel;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\FakeHttpGateway;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Gifts from a partner shop (Katale Plus): choosing them, the cart, the hand-off to the shop's own checkout, the
 * page the visitor comes back to, and what staff see. Everything runs through the real routes, filters, client and
 * views; only the network is replaced, so what is asserted about the requests is what would be sent to Katale.
 *
 * @internal
 */
final class GiftCartTest extends AppFeatureTestCase
{
    use SetsEnvironment;

    /** @var array<string, mixed> */
    private array $vendor;

    /** @var array<string, mixed> */
    private array $gift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setEnv('vendor.katale.apiKey', 'secret-partner-key');
        $this->vendor = $this->makeKatale();
        $this->gift   = $this->makeHostedGift([
            'vendor_id' => $this->vendor['id'], 'vendor_product_ref' => '123', 'name' => 'Flower Bouquet', 'price' => 45000,
            'description' => 'A bouquet for a special occasion.', 'image_url' => 'https://kataleplus.example.test/uploads/flower.jpg',
            'minimum_delivery_hours' => 24, 'stock_available' => 10,
        ]);
    }

    /**
     * What Katale answers for one gift.
     *
     * @param array<string, mixed> $overrides
     */
    private function kataleGift(array $overrides = []): string
    {
        return json_encode(['data' => $overrides + [
            'id' => 123, 'title' => 'Flower Bouquet', 'image' => 'flower.jpg', 'minimum_delivery_hours' => 24,
            'selling' => 50000, 'offered' => 45000, 'currency' => 'UGX', 'inventories' => [['id' => 456, 'quantity' => 10]],
        ]]);
    }

    private function kataleSession(): string
    {
        return json_encode(['data' => [
            'id' => '9b0f7ac0-34e8-4f2c-8d27-1f5c9e6a023b', 'checkout_url' => 'https://kataleplus.example.test/partner-checkout/TOKEN',
            'expires_at' => '2026-12-25T10:30:00+00:00', 'currency' => 'UGX',
        ]]);
    }

    private function kataleStatus(string $state, bool $paid = false, ?int $orderId = null): string
    {
        return json_encode(['data' => [
            'id' => '9b0f7ac0-34e8-4f2c-8d27-1f5c9e6a023b', 'state' => $state, 'expires_at' => '2026-12-25T10:30:00+00:00',
            'order_id' => $orderId, 'reference' => $orderId ? 'KATALE-REFERENCE' : null, 'paid' => $paid,
            'amount' => $orderId ? '170000.00' : null, 'currency' => 'UGX',
        ]]);
    }

    private function kataleOrder(string $deliveryState = 'pending'): string
    {
        return json_encode(['data' => [
            'id' => 1001, 'reference' => 'KATALE-REFERENCE', 'amount' => '170000.00', 'currency' => 'UGX', 'payment_method' => 'stripe',
            'paid' => true, 'cancelled' => false, 'status' => 1,
            'deliveries' => [['title' => 'Flower Bouquet', 'quantity' => 2, 'requested_delivery_at' => '2026-12-26 11:00:00', 'minimum_delivery_hours' => 24, 'state' => $deliveryState]],
        ]]);
    }

    private function cart(array $lines): static
    {
        return $this->withSession(['gift_cart' => $lines]);
    }

    private function visible(string $html): string
    {
        $html = preg_replace('#<(style|script|noscript|svg)\b.*?</\1>#s', '', $html);

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    // ================================================================== the catalogue

    public function testAShopGiftIsShownWithItsPictureAndPriceAndAnAddToCartForm(): void
    {
        $body = $this->get('gifts')->getBody();

        $this->assertStringContainsString('Gifts delivered to their door', $body);
        $this->assertStringContainsString('Flower Bouquet', $body);
        $this->assertStringContainsString('UGX 45,000', $body);
        $this->assertStringContainsString('src="https://kataleplus.example.test/uploads/flower.jpg"', html_entity_decode($body, ENT_QUOTES | ENT_HTML5));
        $this->assertStringContainsString("Needs at least 24 hours' notice", str_replace('&#039;', "'", $body));
        $this->assertStringContainsString('action="' . site_url('gifts/cart/add') . '"', $body);
    }

    public function testAShopGiftIsNeverOfferedInTheFormThatPaysUs(): void
    {
        $this->makeGift(['name' => 'Birthday e-card']);

        $body = $this->get('gifts')->getBody();

        $this->assertStringNotContainsString('value="' . $this->gift['id'] . '" required', $body);
        $this->assertStringContainsString('Birthday e-card', $body);
    }

    public function testWithOnlyShopGiftsThePageIsNotEmptyAndHasNoPayUsForm(): void
    {
        $body = $this->get('gifts')->getBody();

        $this->assertStringNotContainsString('No gifts are available right now.', $body);
        $this->assertStringNotContainsString('id="giftForm"', $body);
    }

    public function testAnOutOfStockGiftCannotBeAdded(): void
    {
        (new \App\Models\GiftModel())->update($this->gift['id'], ['stock_available' => 0]);

        $body = $this->get('gifts')->getBody();

        $this->assertStringContainsString('Out of stock', $body);
        $this->assertStringNotContainsString('action="' . site_url('gifts/cart/add') . '"', $body);
    }

    public function testAGiftWhoseStockWeDoNotKnowCanStillBeAdded(): void
    {
        (new \App\Models\GiftModel())->update($this->gift['id'], ['stock_available' => null]);

        $this->assertStringContainsString('action="' . site_url('gifts/cart/add') . '"', $this->get('gifts')->getBody());
    }

    public function testTheCartLinkAppearsOnlyOnceSomethingIsInIt(): void
    {
        $this->assertStringNotContainsString('View cart', $this->get('gifts')->getBody());
        $this->assertStringContainsString('View cart (3)', $this->cart([$this->gift['id'] => 3])->get('gifts')->getBody());
    }

    // ================================================================== adding

    public function testAGiftIsAddedToTheCart(): void
    {
        $result = $this->post('gifts/cart/add', ['gift_id' => $this->gift['id'], 'quantity' => 2]);

        $this->assertStringEndsWith('/gifts/cart', $result->getRedirectUrl());
        $this->assertSame([$this->gift['id'] => 2], $_SESSION['gift_cart']);
        $this->assertSame('"Flower Bouquet" added to your cart.', $this->flash('toastr_success'));
    }

    public function testAddingAgainAddsToTheQuantity(): void
    {
        $this->cart([$this->gift['id'] => 2])->post('gifts/cart/add', ['gift_id' => $this->gift['id'], 'quantity' => 3]);

        $this->assertSame([$this->gift['id'] => 5], $_SESSION['gift_cart']);
    }

    public function testQuantityDefaultsToOne(): void
    {
        $this->post('gifts/cart/add', ['gift_id' => $this->gift['id']]);

        $this->assertSame([$this->gift['id'] => 1], $_SESSION['gift_cart']);
    }

    public function testMoreThanIsInStockIsCutDownToWhatIsLeft(): void
    {
        $this->cart([$this->gift['id'] => 8])->post('gifts/cart/add', ['gift_id' => $this->gift['id'], 'quantity' => 5]);

        $this->assertSame([$this->gift['id'] => 10], $_SESSION['gift_cart']);
        $this->assertStringContainsString('Only 10 in stock', $this->flash('toastr_success'));
    }

    public function testAnOutOfStockGiftIsRefused(): void
    {
        (new \App\Models\GiftModel())->update($this->gift['id'], ['stock_available' => 0]);

        $this->post('gifts/cart/add', ['gift_id' => $this->gift['id'], 'quantity' => 1]);

        $this->assertSame('"Flower Bouquet" is out of stock.', $this->flash('toastr_error'));
        $this->assertArrayNotHasKey('gift_cart', $_SESSION);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function badAdditions(): array
    {
        return [
            'a quantity of zero'          => [['quantity' => '0']],
            'a negative quantity'         => [['quantity' => '-2']],
            'a word for a quantity'       => [['quantity' => 'lots']],
            'more than a line can hold'   => [['quantity' => '101']],
            'no gift'                     => [['gift_id' => '']],
            'a gift id that is not a number' => [['gift_id' => '1 OR 1=1']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badAdditions')]
    public function testAnInvalidAdditionChangesNothing(array $overrides): void
    {
        $this->post('gifts/cart/add', $overrides + ['gift_id' => $this->gift['id'], 'quantity' => 1]);

        $this->assertArrayNotHasKey('gift_cart', $_SESSION);
        $this->assertNotNull($this->flash('toastr_error'));
    }

    public function testOnlyShopGiftsCanBeAdded(): void
    {
        $own     = $this->makeGift();
        $off     = $this->makeHostedGift(['vendor_id' => $this->vendor['id'], 'is_active' => 0]);
        $generic = $this->makeHostedGift(['vendor_id' => $this->makeVendor()['id']]);

        foreach ([$own, $off, $generic, ['id' => 999999]] as $gift) {
            $this->post('gifts/cart/add', ['gift_id' => $gift['id'], 'quantity' => 1]);

            $this->assertSame('That gift is not available.', $this->flash('toastr_error'));
            $this->assertArrayNotHasKey('gift_cart', $_SESSION);
        }
    }

    public function testACartHoldsAtMostTwentyDifferentGifts(): void
    {
        $lines = [];

        for ($i = 0; $i < 20; $i++) {
            $lines[$this->makeHostedGift(['vendor_id' => $this->vendor['id']])['id']] = 1;
        }

        $this->cart($lines)->post('gifts/cart/add', ['gift_id' => $this->gift['id'], 'quantity' => 1]);

        $this->assertCount(20, $_SESSION['gift_cart']);
        $this->assertStringContainsString('up to 20 different gifts', $this->flash('toastr_error'));
    }

    // ================================================================== the cart

    public function testAnEmptyCartSaysSo(): void
    {
        $body = $this->get('gifts/cart')->getBody();

        $this->assertStringContainsString('Your cart is empty.', $body);
        $this->assertStringNotContainsString('Continue to delivery and payment', $body);
    }

    public function testTheCartShowsEachGiftItsSubtotalAndAnEstimatedTotal(): void
    {
        $other = $this->makeHostedGift(['vendor_id' => $this->vendor['id'], 'name' => 'Chocolate Box', 'price' => 20000]);

        $visible = $this->visible($this->cart([$this->gift['id'] => 2, $other['id'] => 1])->get('gifts/cart')->getBody());

        $this->assertStringContainsString('Flower Bouquet', $visible);
        $this->assertStringContainsString('UGX 45,000', $visible);
        $this->assertStringContainsString('UGX 90,000', $visible, '2 x 45,000.');
        $this->assertStringContainsString('Chocolate Box', $visible);
        $this->assertMatchesRegularExpression('/Estimated total\s*UGX 110,000/', $visible);
        $this->assertStringContainsString('This total is an estimate', $visible);
        $this->assertStringContainsString('Continue to delivery and payment', $visible);
    }

    public function testAGiftThatHasBeenSwitchedOffIsTakenOutOfTheCart(): void
    {
        $gone = $this->makeHostedGift(['vendor_id' => $this->vendor['id'], 'name' => 'Gone Gift', 'is_active' => 0]);

        $body = $this->cart([$this->gift['id'] => 1, $gone['id'] => 1])->get('gifts/cart')->getBody();

        $this->assertStringContainsString('Flower Bouquet', $body);
        $this->assertStringNotContainsString('Gone Gift', $body);
        $this->assertSame([$this->gift['id'] => 1], $_SESSION['gift_cart']);
    }

    public function testQuantitiesAreUpdatedAndZeroRemovesALine(): void
    {
        $other = $this->makeHostedGift(['vendor_id' => $this->vendor['id']]);

        $this->cart([$this->gift['id'] => 2, $other['id'] => 1])->post('gifts/cart/update', ['quantities' => [$this->gift['id'] => 4, $other['id'] => 0]]);

        $this->assertSame([$this->gift['id'] => 4], $_SESSION['gift_cart']);
    }

    public function testAQuantityIsCutDownToWhatIsInStockWhenUpdated(): void
    {
        $this->cart([$this->gift['id'] => 2])->post('gifts/cart/update', ['quantities' => [$this->gift['id'] => 50]]);

        $this->assertSame([$this->gift['id'] => 10], $_SESSION['gift_cart']);
    }

    public function testUpdatingCannotAddAGiftThatIsNotAlreadyInTheCart(): void
    {
        $other = $this->makeHostedGift(['vendor_id' => $this->vendor['id']]);

        $this->cart([$this->gift['id'] => 1])->post('gifts/cart/update', ['quantities' => [$this->gift['id'] => 1, $other['id'] => 5]]);

        $this->assertSame([$this->gift['id'] => 1], $_SESSION['gift_cart']);
    }

    public function testALineIsRemoved(): void
    {
        $other = $this->makeHostedGift(['vendor_id' => $this->vendor['id']]);

        $this->cart([$this->gift['id'] => 2, $other['id'] => 1])->post('gifts/cart/remove/' . $this->gift['id']);

        $this->assertSame([$other['id'] => 1], $_SESSION['gift_cart']);
    }

    // ================================================================== checkout

    public function testCheckoutChecksStockOpensASessionAndSendsTheVisitorToTheShop(): void
    {
        $http = FakeHttpGateway::install()->enqueue(200, $this->kataleGift())->enqueue(200, $this->kataleSession());

        $result = $this->cart([$this->gift['id'] => 2])->post('gifts/checkout');

        $this->assertSame('https://kataleplus.example.test/partner-checkout/TOKEN', $result->getRedirectUrl());

        [$read, $open] = $http->calls;
        $this->assertSame('https://kataleplus.example.test/api/v1/partner/gifts/123', $read['url']);
        $this->assertSame(['POST', 'https://kataleplus.example.test/api/v1/partner/checkout-sessions'], [$open['method'], $open['url']]);
        $this->assertSame('Bearer secret-partner-key', $open['options']['headers']['Authorization']);
        $this->assertSame([['gift_id' => 123, 'inventory_id' => 456, 'quantity' => 2]], $open['options']['json']['items']);

        $checkout = $this->db->table('vendor_checkouts')->get()->getRowArray();
        $this->assertSame($checkout['idempotency_key'], $open['options']['headers']['Idempotency-Key']);
        $this->assertSame(['open', '9b0f7ac0-34e8-4f2c-8d27-1f5c9e6a023b', '90000.00'], [$checkout['state'], $checkout['session_id'], $checkout['estimate_total']]);
        $this->assertSame($checkout['public_token'], $_SESSION['gift_cart_checkout']);
        $this->assertSame([$this->gift['id'] => 2], $_SESSION['gift_cart'], 'The cart stays until the purchase is paid, in case the visitor changes their mind on the shop\'s page.');
    }

    public function testTheKeyIsNeverInAnythingTheVisitorReceives(): void
    {
        FakeHttpGateway::install()->enqueue(200, $this->kataleGift())->enqueue(200, $this->kataleSession());

        $result = $this->cart([$this->gift['id'] => 1])->post('gifts/checkout');

        $this->assertStringNotContainsString('secret-partner-key', $result->response()->getHeaderLine('Location') . $result->getBody());
        $this->assertStringNotContainsString('secret-partner-key', json_encode($_SESSION));
    }

    public function testAnEmptyCartCannotBeCheckedOut(): void
    {
        $http = FakeHttpGateway::install();

        $result = $this->post('gifts/checkout');

        $this->assertStringEndsWith('/gifts/cart', $result->getRedirectUrl());
        $this->assertSame('Your cart is empty.', $this->flash('toastr_error'));
        $this->assertSame([], $http->calls);
    }

    public function testACartWithTooMuchInItIsSentBackWithHowManyAreLeft(): void
    {
        FakeHttpGateway::install()->enqueue(200, $this->kataleGift(['inventories' => [['id' => 456, 'quantity' => 1]]]));

        $result = $this->cart([$this->gift['id'] => 3])->post('gifts/checkout');

        $this->assertStringEndsWith('/gifts/cart', $result->getRedirectUrl());
        $this->assertSame('Only 1 of "Flower Bouquet" left. Please lower the quantity.', $this->flash('toastr_error'));
        $this->assertSame(0, $this->db->table('vendor_checkouts')->countAllResults());
    }

    public function testTheShopsOwnReasonForRefusingACartIsShown(): void
    {
        FakeHttpGateway::install()->enqueue(200, $this->kataleGift())->enqueue(422, '{"message": "This gift is not ready for sale."}');

        $result = $this->cart([$this->gift['id'] => 1])->post('gifts/checkout');

        $this->assertStringEndsWith('/gifts/cart', $result->getRedirectUrl());
        $this->assertSame('This gift is not ready for sale.', $this->flash('toastr_error'));
    }

    public function testAShopThatIsDownLeavesAClearMessageAndNoBrokenRedirect(): void
    {
        FakeHttpGateway::install(failWith: 'connection refused');

        $result = $this->cart([$this->gift['id'] => 1])->post('gifts/checkout');

        $this->assertStringEndsWith('/gifts/cart', $result->getRedirectUrl());
        $this->assertStringContainsString('could not check the stock', $this->flash('toastr_error'));
        $this->assertStringNotContainsString('connection refused', (string) $this->flash('toastr_error'));
    }

    public function testWithNoKeySetUpNothingIsSentAndTheCartSurvives(): void
    {
        $this->setEnv('vendor.katale.apiKey', null);
        $http = FakeHttpGateway::install();

        $result = $this->cart([$this->gift['id'] => 1])->post('gifts/checkout');

        $this->assertStringEndsWith('/gifts/cart', $result->getRedirectUrl());
        $this->assertSame([], $http->calls);
    }

    public function testAShopGiftCannotBePaidForThroughOurOwnForm(): void
    {
        $this->post('gifts', [
            'gift_id' => $this->gift['id'], 'sender_name' => 'Grace', 'sender_phone' => '0772123456', 'recipient_name' => 'Ruth', 'quantity' => 1,
        ]);

        $this->assertSame('This gift is bought through the gift cart.', $this->flash('toastr_error'));
        $this->assertSame(0, $this->db->table('gift_orders')->countAllResults(), 'No one can pay us for something the shop delivers and charges for.');
    }

    // ================================================================== the page the visitor comes back to

    private function purchaseUrl(array $checkout, string $query = ''): string
    {
        return 'gifts/checkout/' . $checkout['public_token'] . $query;
    }

    public function testAnUnknownOrMalformedLinkGoesBackToTheGifts(): void
    {
        foreach (['0123456789abcdef0123456789abcdef', 'short', "' OR 1=1 --", str_repeat('g', 32)] as $token) {
            $result = $this->get('gifts/checkout/' . rawurlencode($token));

            $this->assertStringEndsWith('/gifts', $result->getRedirectUrl(), $token);
            $this->assertSame('We could not find that order.', $this->flash('toastr_error'));
        }
    }

    public function testAWaitingPurchaseSaysSoAndRefreshesItself(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        FakeHttpGateway::install()->enqueue(200, $this->kataleStatus('awaiting_payment'));

        $body = $this->get($this->purchaseUrl($checkout))->getBody();

        $this->assertStringContainsString('Waiting for your payment', $body);
        $this->assertStringContainsString('<meta http-equiv="refresh" content="15">', $body);
        $this->assertStringContainsString($checkout['purchase_reference'], $body);
        $this->assertStringContainsString('Flower Bouquet', $body);
        $this->assertStringContainsString('noindex', $body, 'A private order page is not for search engines.');
    }

    public function testAPaidPurchaseThanksTheVisitorShowsDeliveryAndStopsRefreshing(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        FakeHttpGateway::install()->enqueue(200, $this->kataleStatus('paid', true, 1001))->enqueue(200, $this->kataleOrder('preparing'));

        $body    = $this->get($this->purchaseUrl($checkout))->getBody();
        $visible = $this->visible($body);

        $this->assertStringContainsString('Thank you. Your payment was received.', $visible);
        $this->assertStringContainsString('KATALE-REFERENCE', $visible);
        $this->assertStringContainsString('UGX 170,000.00', $visible);
        $this->assertStringContainsString('Being prepared', $visible);
        $this->assertStringContainsString('Arrival requested for', $visible);
        $this->assertStringNotContainsString('http-equiv="refresh"', $body);
        $this->assertSame('paid', (new VendorCheckoutModel())->find($checkout['id'])['state']);
    }

    public function testComingBackDoesNotMeanPaid(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        FakeHttpGateway::install()->enqueue(200, $this->kataleStatus('awaiting_payment', false, 1001))->enqueue(200, $this->kataleOrder());

        $visible = $this->visible($this->get($this->purchaseUrl($checkout, '?status=paid&paid=1&state=paid&order_id=1001'))->getBody());

        $this->assertStringNotContainsString('Thank you. Your payment was received.', $visible);
        $this->assertStringContainsString('Waiting for your payment', $visible);
        $this->assertSame('0', (string) (new VendorCheckoutModel())->find($checkout['id'])['paid']);
    }

    public function testTheCartIsEmptiedOnlyForTheVisitorWhoSentItOnceItIsPaid(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);

        FakeHttpGateway::install()->enqueue(200, $this->kataleStatus('paid', true, 1001))->enqueue(200, $this->kataleOrder());
        $this->withSession(['gift_cart' => [$this->gift['id'] => 2], 'gift_cart_checkout' => $checkout['public_token']])->get($this->purchaseUrl($checkout));
        $this->assertArrayNotHasKey('gift_cart', $_SESSION);

        $other = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        FakeHttpGateway::install()->enqueue(200, $this->kataleStatus('paid', true, 1002))->enqueue(200, $this->kataleOrder());
        $this->withSession(['gift_cart' => [$this->gift['id'] => 2], 'gift_cart_checkout' => $checkout['public_token']])->get($this->purchaseUrl($other));
        $this->assertSame([$this->gift['id'] => 2], $_SESSION['gift_cart'], 'Someone else\'s paid purchase does not empty this visitor\'s cart.');
    }

    public function testACartThatIsStillWaitingIsKept(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        FakeHttpGateway::install()->enqueue(200, $this->kataleStatus('awaiting_payment'));

        $this->withSession(['gift_cart' => [$this->gift['id'] => 2], 'gift_cart_checkout' => $checkout['public_token']])->get($this->purchaseUrl($checkout));

        $this->assertSame([$this->gift['id'] => 2], $_SESSION['gift_cart']);
    }

    public function testAnExpiredCheckoutOffersAFreshStart(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        FakeHttpGateway::install()->enqueue(410, '{"message": "Gone"}');

        $visible = $this->visible($this->get($this->purchaseUrl($checkout))->getBody());

        $this->assertStringContainsString('This checkout has expired', $visible);
        $this->assertStringContainsString('Nothing was charged', $visible);
        $this->assertStringContainsString('Back to your cart', $visible);
    }

    public function testACancelledOrderTellsThePayerWhoToContact(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'cancelled']);

        $visible = $this->visible($this->get($this->purchaseUrl($checkout))->getBody());

        $this->assertStringContainsString('This order was cancelled', $visible);
        $this->assertStringContainsString('contact us with the reference', $visible);
    }

    public function testAnOpenCheckoutLinksBackToTheShopsPage(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'last_checked_at' => date('Y-m-d H:i:s')]);

        FakeHttpGateway::install();

        $body = html_entity_decode($this->get($this->purchaseUrl($checkout))->getBody(), ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString('href="' . $checkout['checkout_url'] . '"', $body);
    }

    public function testAShopThatCannotBeReachedDoesNotBreakThePage(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment']);
        FakeHttpGateway::install(failWith: 'timed out');

        $result = $this->get($this->purchaseUrl($checkout));

        $result->assertOK();
        $this->assertStringContainsString('We could not reach the shop just now', $result->getBody());
        $this->assertStringNotContainsString('timed out', $result->getBody());
    }

    public function testNothingAboutTheKeyOrTheSessionAddressIsOnTheVisitorsPage(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment', 'last_checked_at' => date('Y-m-d H:i:s')]);

        FakeHttpGateway::install();

        $body = $this->get($this->purchaseUrl($checkout))->getBody();

        $this->assertStringNotContainsString($checkout['session_id'], $body, 'The session id is for our server; the visitor has the shop\'s own address.');
        $this->assertStringNotContainsString('secret-partner-key', $body);
    }

    // ================================================================== staff

    public function testStaffSeeEveryPurchaseAndCanFilterThem(): void
    {
        $paid    = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'paid', 'paid' => 1]);
        $waiting = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'awaiting_payment']);

        $all = $this->get('admin/gift-purchases')->getBody();
        $this->assertStringContainsString($paid['purchase_reference'], $all);
        $this->assertStringContainsString($waiting['purchase_reference'], $all);
        $this->assertStringContainsString('Flower Bouquet × 2', $all);

        $only = $this->get('admin/gift-purchases?state=paid')->getBody();
        $this->assertStringContainsString($paid['purchase_reference'], $only);
        $this->assertStringNotContainsString($waiting['purchase_reference'], $only);
    }

    public function testAnUnknownFilterShowsEverythingRatherThanAnError(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);

        $this->assertStringContainsString($checkout['purchase_reference'], $this->get('admin/gift-purchases?state=%27%3B+DROP+TABLE')->getBody());
    }

    public function testStaffSeePaymentAndDeliveryDetailOfOnePurchase(): void
    {
        $checkout = $this->makeCheckout([
            'vendor_id' => $this->vendor['id'], 'state' => 'paid', 'paid' => 1, 'vendor_order_id' => '1001', 'vendor_reference' => 'KATALE-REFERENCE',
            'final_amount' => 170000, 'order_details' => json_encode(['deliveries' => [['title' => 'Flower Bouquet', 'quantity' => 2, 'requested_delivery_at' => '2026-12-26 14:00', 'state' => 'on_the_way']]]),
        ]);

        $visible = $this->visible($this->get('admin/gift-purchases/' . $checkout['id'])->getBody());

        $this->assertStringContainsString('KATALE-REFERENCE', $visible);
        $this->assertStringContainsString('UGX 170,000.00', $visible);
        $this->assertStringContainsString('On the way', $visible);
        $this->assertStringContainsString('Requested for 2026-12-26 14:00', $visible);
    }

    public function testAnUnknownPurchaseGoesBackToTheList(): void
    {
        $this->assertStringEndsWith('/admin/gift-purchases', $this->get('admin/gift-purchases/999999')->getRedirectUrl());
        $this->assertSame('Purchase not found.', $this->flash('toastr_error'));
    }

    public function testStaffCanAskTheShopAgainAtOnce(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'last_checked_at' => date('Y-m-d H:i:s')]);
        $http     = FakeHttpGateway::install()->enqueue(200, $this->kataleStatus('paid', true, 1001))->enqueue(200, $this->kataleOrder());

        $this->post('admin/gift-purchases/' . $checkout['id'] . '/refresh');

        $this->assertSame('Checked with the shop.', $this->flash('toastr_success'));
        $this->assertSame('paid', (new VendorCheckoutModel())->find($checkout['id'])['state']);
        $this->assertCount(2, $http->calls, 'Asked even though it was asked a moment ago.');
    }

    public function testAFailedCheckIsReportedToStaff(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id']]);
        FakeHttpGateway::install(failWith: 'timed out');

        $this->post('admin/gift-purchases/' . $checkout['id'] . '/refresh');

        $this->assertStringContainsString('The shop could not be asked', $this->flash('toastr_error'));
    }

    public function testAPurchaseThatNeverReachedTheShopCannotBeChecked(): void
    {
        $checkout = $this->makeCheckout(['vendor_id' => $this->vendor['id'], 'state' => 'failed', 'session_id' => null]);
        $http     = FakeHttpGateway::install();

        $this->post('admin/gift-purchases/' . $checkout['id'] . '/refresh');

        $this->assertStringContainsString('never reached the shop', $this->flash('toastr_error'));
        $this->assertSame([], $http->calls);
    }

    // ================================================================== vendors

    public function testAShopIsSetUpAsAKataleVendorFromTheAdminForm(): void
    {
        $this->post('admin/gift-vendors', ['name' => 'Katale Plus', 'driver' => 'katale', 'api_base_url' => '', 'is_active' => '1']);

        $row = $this->db->table('gift_vendors')->where('name', 'Katale Plus')->get()->getRowArray();
        $this->assertSame('katale', $row['driver']);
    }

    public function testAVendorFormThatSendsNoIntegrationMakesAGenericOne(): void
    {
        $this->post('admin/gift-vendors', ['name' => 'Acme', 'is_active' => '1']);

        $this->assertSame('generic', $this->db->table('gift_vendors')->where('name', 'Acme')->get()->getRowArray()['driver']);
    }

    public function testEditingAKataleVendorWithoutSendingTheIntegrationKeepsIt(): void
    {
        $this->post('admin/gift-vendors/' . $this->vendor['id'] . '/update', ['name' => 'Katale', 'is_active' => '1']);

        $this->assertSame('katale', (new GiftVendorModel())->find($this->vendor['id'])['driver']);
    }

    public function testAnUnknownIntegrationIsRefused(): void
    {
        $this->post('admin/gift-vendors', ['name' => 'Sneaky', 'driver' => 'evil', 'is_active' => '1']);

        $this->assertArrayHasKey('driver', $this->flash('errors'));
        $this->assertSame(0, $this->db->table('gift_vendors')->where('name', 'Sneaky')->countAllResults());
    }
}
