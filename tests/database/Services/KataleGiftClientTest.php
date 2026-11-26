<?php

namespace Tests\Database\Services;

use App\Libraries\VendorGiftClients\KataleGiftClient;
use App\Libraries\VendorGiftClients\VendorClients;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\FakeHttpGateway;
use Tests\Support\TestCase\AppTestCase;

/**
 * The Katale Plus Partner Gifts API client (guide revision 1.2): what it sends, how it reads the answers, and that
 * a vendor's trouble comes back as a result a visitor can be told about, never as an exception or a leaked key.
 *
 * @internal
 */
final class KataleGiftClientTest extends AppTestCase
{
    use SetsEnvironment;

    private const BASE   = 'https://kataleplus.example.test/api/v1/partner';
    private const ORIGIN = 'https://kataleplus.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setEnv('vendor.katale.apiKey', 'secret-partner-key');
    }

    private function client(array $overrides = []): KataleGiftClient
    {
        return new KataleGiftClient($overrides + ['slug' => 'katale', 'name' => 'Katale', 'api_base_url' => self::BASE]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'id' => 123, 'title' => 'Flower Bouquet', 'slug' => 'flower-bouquet', 'image' => 'flower-bouquet.jpg',
            'description' => 'A bouquet for a special occasion.', 'minimum_delivery_hours' => 24, 'subcategory_id' => 5,
            'selling' => 50000, 'offered' => 45000, 'currency' => 'UGX',
            'inventories' => [['id' => 456, 'quantity' => 10]],
            'delivery_options' => [['id' => 789, 'country' => 'UG', 'state' => 'Kampala', 'price' => '10000.00', 'day_needed' => 1]],
        ];
    }

    private function page(array $rows, int $current = 1, int $last = 1): string
    {
        return json_encode(['current_page' => $current, 'data' => $rows, 'last_page' => $last, 'per_page' => 48, 'total' => count($rows)]);
    }

    private function inStationTime(string $utc, string $format = 'Y-m-d H:i:s'): string
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(config('App')->appTimezone))->format($format);
    }

    // ------------------------------------------------------------------ the catalogue

    public function testTheCatalogueIsReadWithThePartnerKeyAndNormalised(): void
    {
        $http = FakeHttpGateway::install(body: $this->page([$this->row()]));

        $catalog = $this->client()->fetchCatalog();

        $call = $http->calls[0];
        $this->assertSame(['GET', self::BASE . '/gifts'], [$call['method'], $call['url']]);
        $this->assertSame(['per_page' => 48, 'page' => 1], $call['options']['query']);
        $this->assertSame('Bearer secret-partner-key', $call['options']['headers']['Authorization']);
        $this->assertSame('application/json', $call['options']['headers']['Accept']);
        $this->assertFalse($call['options']['http_errors'], 'A 404 or 422 is an answer to read, not an exception.');

        $this->assertSame([[
            'vendor_product_ref'     => '123',
            'name'                   => 'Flower Bouquet',
            'description'            => 'A bouquet for a special occasion.',
            'price'                  => 45000.0,
            'currency'               => 'UGX',
            'type'                   => 'physical',
            'image_url'              => self::ORIGIN . '/uploads/flower-bouquet.jpg',
            'minimum_delivery_hours' => 24,
            'stock'                  => 10,
            'inventories'            => [['id' => '456', 'quantity' => 10]],
        ]], $catalog);
    }

    public function testEveryPageOfTheCatalogueIsRead(): void
    {
        $http = FakeHttpGateway::install()
            ->enqueue(200, $this->page([$this->row(['id' => 1])], 1, 3))
            ->enqueue(200, $this->page([$this->row(['id' => 2])], 2, 3))
            ->enqueue(200, $this->page([$this->row(['id' => 3])], 3, 3));

        $catalog = $this->client()->fetchCatalog();

        $this->assertSame(['1', '2', '3'], array_column($catalog, 'vendor_product_ref'));
        $this->assertSame([1, 2, 3], array_map(static fn (array $c): int => $c['options']['query']['page'], $http->calls));
    }

    public function testTheOfferedPriceIsUsedOnlyWhenItIsPositive(): void
    {
        FakeHttpGateway::install(body: $this->page([
            $this->row(['id' => 1, 'selling' => 50000, 'offered' => 45000]),
            $this->row(['id' => 2, 'selling' => 50000, 'offered' => 0]),
            $this->row(['id' => 3, 'selling' => 50000, 'offered' => null]),
        ]));

        $this->assertSame([45000.0, 50000.0, 50000.0], array_column($this->client()->fetchCatalog(), 'price'));
    }

    public function testGiftsThatCannotBeSoldAsListedAreLeftOut(): void
    {
        FakeHttpGateway::install(body: $this->page([
            $this->row(['id' => null]),
            $this->row(['id' => 2, 'title' => '  ']),
            $this->row(['id' => 3, 'selling' => 0, 'offered' => 0]),
            $this->row(['id' => 4, 'selling' => 'free', 'offered' => null]),
            $this->row(['id' => 5]),
        ]));

        $this->assertSame(['5'], array_column($this->client()->fetchCatalog(), 'vendor_product_ref'));
    }

    public function testTextFromTheVendorIsStrippedOfMarkup(): void
    {
        FakeHttpGateway::install(body: $this->page([$this->row(['title' => '<b>Bouquet</b>', 'description' => '<script>alert(1)</script>Roses'])]));

        $gift = $this->client()->fetchCatalog()[0];

        $this->assertSame('Bouquet', $gift['name']);
        $this->assertStringNotContainsString('<', $gift['description']);
    }

    public function testStockIsTheSumOfTheInventoriesAndAnEmptyListIsNoStock(): void
    {
        FakeHttpGateway::install(body: $this->page([
            $this->row(['id' => 1, 'inventories' => [['id' => 10, 'quantity' => 4], ['id' => 11, 'quantity' => 3]]]),
            $this->row(['id' => 2, 'inventories' => []]),
            $this->row(['id' => 3, 'inventories' => [['id' => 12, 'quantity' => -5]]]),
        ]));

        $this->assertSame([7, 0, 0], array_column($this->client()->fetchCatalog(), 'stock'));
    }

    public function testACatalogueThatCannotBeReadIsAFailedSyncNotAShortOne(): void
    {
        FakeHttpGateway::install()
            ->enqueue(200, $this->page([$this->row(['id' => 1])], 1, 2))
            ->enqueue(500, '{"message": "Server Error"}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Katale did not return its catalogue');

        $this->client()->fetchCatalog();
    }

    public function testWithoutAKeyNothingIsSentAndTheMessageNamesTheSetting(): void
    {
        $this->setEnv('vendor.katale.apiKey', null);
        $http = FakeHttpGateway::install();

        try {
            $this->client()->fetchCatalog();
            $this->fail('A vendor with no key must not look like an empty catalogue.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('vendor.katale.apiKey', $e->getMessage());
        }

        $this->assertSame([], $http->calls);
    }

    public function testTheKeyIsReadFromTheVendorsOwnSlug(): void
    {
        $this->setEnv('vendor.katale.apiKey', null);
        $this->setEnv('vendor.katale-plus.apiKey', 'other-key');
        $http = FakeHttpGateway::install(body: $this->page([]));

        $this->client(['slug' => 'katale-plus'])->fetchCatalog();

        $this->assertSame('Bearer other-key', $http->calls[0]['options']['headers']['Authorization']);
    }

    public function testTheDocumentedAddressIsUsedWhenNoneIsSet(): void
    {
        $http = FakeHttpGateway::install(body: $this->page([]));

        $this->client(['api_base_url' => null])->fetchCatalog();

        $this->assertSame('https://kataleplus.com/api/v1/partner/gifts', $http->calls[0]['url']);
    }

    public function testATrailingSlashOnTheAddressDoesNotDoubleUp(): void
    {
        $http = FakeHttpGateway::install(body: $this->page([]));

        $this->client(['api_base_url' => self::BASE . '/'])->fetchCatalog();

        $this->assertSame(self::BASE . '/gifts', $http->calls[0]['url']);
    }

    // ------------------------------------------------------------------ pictures

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function images(): array
    {
        return [
            'a full https address is used as given'     => ['https://cdn.example.test/a.jpg', 'https://cdn.example.test/a.jpg'],
            'a bare file name goes under uploads'       => ['flower.jpg', self::ORIGIN . '/uploads/flower.jpg'],
            'a leading slash is dropped'                => ['/flower.jpg', self::ORIGIN . '/uploads/flower.jpg'],
            'an uploads prefix is not doubled'          => ['uploads/flower.jpg', self::ORIGIN . '/uploads/flower.jpg'],
            'a leading slash and uploads prefix'        => ['/uploads/flower.jpg', self::ORIGIN . '/uploads/flower.jpg'],
            'a subfolder is kept'                       => ['gifts/2026/flower.jpg', self::ORIGIN . '/uploads/gifts/2026/flower.jpg'],
            'spaces are encoded'                        => ['red roses.jpg', self::ORIGIN . '/uploads/red%20roses.jpg'],
            'plain http is dropped'                     => ['http://cdn.example.test/a.jpg', null],
            'a script address is dropped'               => ['javascript:alert(1)', null],
            'a protocol-relative address is dropped'    => ['//evil.example.test/a.jpg', null],
            'a way out of the folder is dropped'        => ['../secret.png', null],
            'nothing is nothing'                        => ['', null],
        ];
    }

    #[DataProvider('images')]
    public function testAPictureAddressIsBuiltSafely(string $image, ?string $expected): void
    {
        FakeHttpGateway::install(body: json_encode(['data' => $this->row(['image' => $image])]));

        $this->assertSame($expected, $this->client()->fetchGift('123')['image_url']);
    }

    // ------------------------------------------------------------------ one gift

    public function testOneGiftIsReadByItsId(): void
    {
        $http = FakeHttpGateway::install(body: json_encode(['data' => $this->row(['inventories' => [['id' => 456, 'quantity' => 10], ['id' => 457, 'quantity' => 2]]])]));

        $gift = $this->client()->fetchGift('123');

        $this->assertSame(self::BASE . '/gifts/123', $http->calls[0]['url']);
        $this->assertSame([['id' => '456', 'quantity' => 10], ['id' => '457', 'quantity' => 2]], $gift['inventories']);
        $this->assertSame(12, $gift['stock']);
    }

    public function testAGiftTheVendorNoLongerHasIsNull(): void
    {
        FakeHttpGateway::install(404, '{"message": "Not found"}');

        $this->assertNull($this->client()->fetchGift('123'));
    }

    public function testAGiftThatCannotBeReadThrowsSoThatTheCartIsNotTrusted(): void
    {
        FakeHttpGateway::install(503);

        $this->expectException(RuntimeException::class);

        $this->client()->fetchGift('123');
    }

    // ------------------------------------------------------------------ opening a checkout

    public function testACheckoutIsOpenedWithTheCartAnIdempotencyKeyAndTheReturnAddress(): void
    {
        $http = FakeHttpGateway::install(body: json_encode(['data' => [
            'id' => '9b0f7ac0-34e8-4f2c-8d27-1f5c9e6a023b', 'checkout_url' => 'https://kataleplus.example.test/partner-checkout/TOKEN',
            'expires_at' => '2026-12-25T10:30:00+00:00', 'currency' => 'UGX',
        ]]));

        $result = $this->client()->createCheckoutSession(
            [['gift_id' => '123', 'inventory_id' => '456', 'quantity' => '2'], ['gift_id' => 124, 'inventory_id' => 457, 'quantity' => 1]],
            'cbsg-abcdef123456',
            'https://cbs.example.test/gifts/checkout/abc'
        );

        $call = $http->calls[0];
        $this->assertSame(['POST', self::BASE . '/checkout-sessions'], [$call['method'], $call['url']]);
        $this->assertSame('cbsg-abcdef123456', $call['options']['headers']['Idempotency-Key']);
        $this->assertSame('application/json', $call['options']['headers']['Content-Type']);
        $this->assertSame([
            'items'      => [['gift_id' => 123, 'inventory_id' => 456, 'quantity' => 2], ['gift_id' => 124, 'inventory_id' => 457, 'quantity' => 1]],
            'return_url' => 'https://cbs.example.test/gifts/checkout/abc',
        ], $call['options']['json'], 'Ids and quantities are whole numbers, and nothing else is sent in a cart line.');

        $this->assertTrue($result['success']);
        $this->assertSame('9b0f7ac0-34e8-4f2c-8d27-1f5c9e6a023b', $result['data']['session_id']);
        $this->assertSame('https://kataleplus.example.test/partner-checkout/TOKEN', $result['data']['checkout_url']);
        $this->assertSame($this->inStationTime('2026-12-25 10:30:00'), $result['data']['expires_at']);
    }

    public function testNoReturnAddressIsSentWhenThereIsNone(): void
    {
        $http = FakeHttpGateway::install(body: json_encode(['data' => ['id' => 's', 'checkout_url' => 'https://k.example.test/c']]));

        $this->client()->createCheckoutSession([['gift_id' => 1, 'inventory_id' => 2, 'quantity' => 1]], 'cbsg-abcdef123456');

        $this->assertArrayNotHasKey('return_url', $http->calls[0]['options']['json']);
    }

    public function testASuccessWithoutAnAddressToSendTheCustomerToIsNotASuccess(): void
    {
        FakeHttpGateway::install(body: json_encode(['data' => ['id' => 's']]));

        $this->assertFalse($this->client()->createCheckoutSession([['gift_id' => 1, 'inventory_id' => 2, 'quantity' => 1]], 'cbsg-abcdef123456')['success']);
    }

    /**
     * @return array<string, array{0: int, 1: string, 2: bool, 3: string}>
     */
    public static function refusals(): array
    {
        return [
            'a refused cart shows the vendor\'s own message' => [422, '{"message": "The items.0.quantity field must be at least 1.", "errors": {"items.0.quantity": ["The items.0.quantity field must be at least 1."]}}', false, 'The items.0.quantity field must be at least 1.'],
            'a conflicting key is explained'                 => [409, '{"message": "Idempotency key reused with a different request."}', false, 'Idempotency key reused with a different request.'],
            'a refused key is not retried'                   => [401, '{"message": "Unauthenticated."}', false, 'the partner key was refused'],
            'a busy vendor may answer next time'             => [429, '{}', true, 'too many requests'],
            'a failing vendor may answer next time'          => [500, '{}', true, 'status 500'],
            'an unavailable vendor may answer next time'     => [503, '{}', true, 'status 503'],
            'a gone gift is not retried'                     => [404, '{}', false, 'no longer available'],
        ];
    }

    #[DataProvider('refusals')]
    public function testARefusedCheckoutIsAResultWithAMessageAndWhetherToRetry(int $status, string $body, bool $retryable, string $messageContains): void
    {
        FakeHttpGateway::install($status, $body);

        $result = $this->client()->createCheckoutSession([['gift_id' => 1, 'inventory_id' => 2, 'quantity' => 1]], 'cbsg-abcdef123456');

        $this->assertFalse($result['success']);
        $this->assertSame($retryable, $result['retryable']);
        $this->assertStringContainsString($messageContains, $result['message']);
        $this->assertSame([], $result['data']);
        $this->assertStringNotContainsString('secret-partner-key', json_encode($result));
    }

    public function testAValidationRefusalCarriesTheFieldDetails(): void
    {
        FakeHttpGateway::install(422, '{"message": "Invalid.", "errors": {"items.0.quantity": ["Too many."]}}');

        $result = $this->client()->createCheckoutSession([['gift_id' => 1, 'inventory_id' => 2, 'quantity' => 1]], 'cbsg-abcdef123456');

        $this->assertSame(['items.0.quantity' => ['Too many.']], $result['errors']);
    }

    public function testAVendorThatCannotBeReachedIsAResultAndRetryable(): void
    {
        FakeHttpGateway::install(failWith: 'cURL error 28: timed out; Authorization: Bearer secret-partner-key');

        $result = $this->client()->createCheckoutSession([['gift_id' => 1, 'inventory_id' => 2, 'quantity' => 1]], 'cbsg-abcdef123456');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['retryable']);
        $this->assertSame(0, $result['status']);
        $this->assertStringNotContainsString('secret-partner-key', json_encode($result), 'The network error\'s own text must never reach a visitor.');
    }

    public function testWithoutAKeyACheckoutIsNotAttemptedAndIsNotRetryable(): void
    {
        $this->setEnv('vendor.katale.apiKey', null);
        $http = FakeHttpGateway::install();

        $result = $this->client()->createCheckoutSession([['gift_id' => 1, 'inventory_id' => 2, 'quantity' => 1]], 'cbsg-abcdef123456');

        $this->assertFalse($result['success']);
        $this->assertFalse($result['retryable']);
        $this->assertStringContainsString('vendor.katale.apiKey', $result['message']);
        $this->assertSame([], $http->calls);
    }

    // ------------------------------------------------------------------ asking about a checkout

    public function testAPendingCheckoutIsReadWithNoOrderYet(): void
    {
        $http = FakeHttpGateway::install(body: json_encode(['data' => [
            'id' => 's-1', 'state' => 'awaiting_payment', 'expires_at' => '2026-12-25T10:30:00+00:00', 'order_id' => 1001,
            'reference' => 'KATALE-REFERENCE', 'paid' => false, 'amount' => '170000.00', 'currency' => 'UGX',
        ]]));

        $result = $this->client()->checkoutSession('s-1');

        $this->assertSame(self::BASE . '/checkout-sessions/s-1', $http->calls[0]['url']);
        $this->assertSame([
            'state' => 'awaiting_payment', 'paid' => false, 'order_id' => '1001', 'reference' => 'KATALE-REFERENCE',
            'amount' => 170000.0, 'currency' => 'UGX', 'expires_at' => $this->inStationTime('2026-12-25 10:30:00'),
        ], $result['data']);
    }

    public function testBeforeAnOrderExistsTheOrderFieldsAreNull(): void
    {
        FakeHttpGateway::install(body: json_encode(['data' => ['id' => 's', 'state' => 'open', 'order_id' => null, 'reference' => null, 'paid' => false, 'amount' => null]]));

        $data = $this->client()->checkoutSession('s')['data'];

        $this->assertSame([null, null, null, false], [$data['order_id'], $data['reference'], $data['amount'], $data['paid']]);
    }

    public function testAPaidCheckoutSaysSo(): void
    {
        FakeHttpGateway::install(body: json_encode(['data' => ['id' => 's', 'state' => 'paid', 'paid' => true, 'order_id' => 7, 'amount' => '1.00']]));

        $data = $this->client()->checkoutSession('s')['data'];

        $this->assertSame(['paid', true], [$data['state'], $data['paid']]);
    }

    public function testAnExpiredSessionIsAGoneAnswerNotAnError(): void
    {
        FakeHttpGateway::install(410, '{"message": "Gone"}');

        $result = $this->client()->checkoutSession('s');

        $this->assertFalse($result['success']);
        $this->assertSame(410, $result['status']);
        $this->assertFalse($result['retryable']);
    }

    public function testASessionIdWithOddCharactersCannotChangeTheAddress(): void
    {
        $http = FakeHttpGateway::install(404);

        $this->client()->checkoutSession('../orders/1?x=y');

        $this->assertSame(self::BASE . '/checkout-sessions/..%2Forders%2F1%3Fx%3Dy', $http->calls[0]['url']);
    }

    // ------------------------------------------------------------------ the order

    public function testAnOrderIsReadWithItsDeliveriesInStationTime(): void
    {
        $http = FakeHttpGateway::install(body: json_encode(['data' => [
            'id' => 1001, 'reference' => 'KATALE-REFERENCE', 'amount' => '170000.00', 'currency' => 'UGX', 'payment_method' => 'stripe',
            'paid' => true, 'cancelled' => false, 'status' => 1,
            'deliveries' => [['title' => 'Flower Bouquet', 'quantity' => 2, 'requested_delivery_at' => '2026-12-26 11:00:00', 'minimum_delivery_hours' => 24, 'state' => 'pending']],
        ]]));

        $data = $this->client()->order('1001')['data'];

        $this->assertSame(self::BASE . '/orders/1001', $http->calls[0]['url']);
        $this->assertSame(['1001', 'KATALE-REFERENCE', 170000.0, 'stripe', true, false, 1], [$data['id'], $data['reference'], $data['amount'], $data['payment_method'], $data['paid'], $data['cancelled'], $data['status']]);
        $this->assertSame([[
            'title' => 'Flower Bouquet', 'quantity' => 2, 'requested_delivery_at' => $this->inStationTime('2026-12-26 11:00:00', 'Y-m-d H:i'),
            'minimum_delivery_hours' => 24, 'state' => 'pending',
        ]], $data['deliveries']);
    }

    public function testAnOrderWithNoDeliveriesYetHasAnEmptyList(): void
    {
        FakeHttpGateway::install(body: json_encode(['data' => ['id' => 1, 'paid' => false]]));

        $this->assertSame([], $this->client()->order('1')['data']['deliveries']);
    }

    // ------------------------------------------------------------------ the rest

    public function testPlacingAnOrderFromHereIsRefusedBecauseKataleTakesTheOrderItself(): void
    {
        $http = FakeHttpGateway::install();

        $result = $this->client()->submitOrder(['vendor_product_ref' => '1', 'quantity' => 1, 'recipient_name' => 'x', 'recipient_phone' => null, 'delivery_details' => null]);

        $this->assertFalse($result['success']);
        $this->assertSame([], $http->calls);
    }

    public function testTheDriverDecidesWhichClientAVendorGets(): void
    {
        $this->assertInstanceOf(KataleGiftClient::class, VendorClients::for(['driver' => 'katale', 'slug' => 'k']));
        $this->assertNotInstanceOf(KataleGiftClient::class, VendorClients::for(['driver' => 'generic', 'slug' => 'g']));
        $this->assertNotInstanceOf(KataleGiftClient::class, VendorClients::for(['slug' => 'g']), 'A vendor with no driver is a generic one.');
    }

    public function testAGenericVendorHasNoHostedCheckout(): void
    {
        $this->expectException(\LogicException::class);

        VendorClients::hosted(['driver' => 'generic', 'slug' => 'g', 'name' => 'G']);
    }
}
