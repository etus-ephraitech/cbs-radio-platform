<?php

namespace Tests\Database\Services;

use App\Libraries\VendorGiftClients\GenericRestVendorClient;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\FakeHttpGateway;
use Tests\Support\TestCase\AppTestCase;

/**
 * The generic REST gift vendor client. Its request and response shapes are an assumption about a typical vendor
 * (the real vendor is not yet chosen), so these tests record that assumption: what it sends, how it reads the
 * answer, and that it never lets a vendor's trouble escape as an exception.
 *
 * @internal
 */
final class GenericRestVendorClientTest extends AppTestCase
{
    use SetsEnvironment;

    /**
     * @param array<string, mixed> $overrides
     */
    private function client(array $overrides = []): GenericRestVendorClient
    {
        return new GenericRestVendorClient($overrides + ['slug' => 'acme', 'api_base_url' => 'https://vendor.example.test/api']);
    }

    // ------------------------------------------------------------------ the catalogue

    public function testTheCatalogueIsFetchedWithTheVendorsKeyAndNormalised(): void
    {
        $this->setEnv('vendor.acme.apiKey', 'secret-key');
        $http = FakeHttpGateway::install(body: json_encode(['data' => [
            ['id' => 12, 'name' => 'Rose bouquet', 'price' => '65000', 'currency' => 'UGX', 'category' => 'physical', 'description' => 'A dozen roses'],
        ]]));

        $catalog = $this->client()->fetchCatalog();

        $this->assertSame(['GET', 'https://vendor.example.test/api/products'], [$http->calls[0]['method'], $http->calls[0]['url']]);
        $this->assertSame('Bearer secret-key', $http->calls[0]['options']['headers']['Authorization']);
        $this->assertSame([[
            'vendor_product_ref' => '12',
            'name'               => 'Rose bouquet',
            'description'        => 'A dozen roses',
            'price'              => 65000.0,
            'currency'           => 'UGX',
            'type'               => 'physical',
        ]], $catalog);
    }

    public function testMissingOptionalFieldsGetSensibleDefaults(): void
    {
        $this->setEnv('vendor.acme.apiKey', 'k');
        FakeHttpGateway::install(body: json_encode(['data' => [['id' => 'A', 'name' => 'Plain', 'price' => 100]]]));

        $item = $this->client()->fetchCatalog()[0];

        $this->assertSame(['UGX', 'physical', null], [$item['currency'], $item['type'], $item['description']]);
    }

    public function testRowsMissingAnIdANameOrAPriceAreSkipped(): void
    {
        $this->setEnv('vendor.acme.apiKey', 'k');
        FakeHttpGateway::install(body: json_encode(['data' => [
            ['name' => 'No id', 'price' => 1],
            ['id' => 2, 'price' => 1],
            ['id' => 3, 'name' => 'No price'],
            ['id' => 4, 'name' => 'Free', 'price' => 0],
            ['id' => 5, 'name' => 'Fine', 'price' => 10],
        ]]));

        $this->assertSame(['4', '5'], array_column($this->client()->fetchCatalog(), 'vendor_product_ref'), 'A price of 0 is a price; a missing price is not.');
    }

    public function testATrailingSlashOnTheBaseAddressDoesNotDoubleUp(): void
    {
        $this->setEnv('vendor.acme.apiKey', 'k');
        $http = FakeHttpGateway::install(body: '{"data": []}');

        $this->client(['api_base_url' => 'https://vendor.example.test/api/'])->fetchCatalog();

        $this->assertSame('https://vendor.example.test/api/products', $http->calls[0]['url']);
    }

    public function testNothingIsRequestedWithoutAKeyOrAnAddress(): void
    {
        $http = FakeHttpGateway::install();

        $this->setEnv('vendor.acme.apiKey', null);
        $this->assertSame([], $this->client()->fetchCatalog());

        $this->setEnv('vendor.acme.apiKey', 'k');
        $this->assertSame([], $this->client(['api_base_url' => ''])->fetchCatalog());
        $this->assertSame([], $http->calls);
    }

    public function testEachVendorUsesItsOwnKey(): void
    {
        $this->setEnv('vendor.acme.apiKey', 'acme-key');
        $this->setEnv('vendor.zed.apiKey', 'zed-key');
        $http = FakeHttpGateway::install(body: '{"data": []}');

        $this->client(['slug' => 'zed'])->fetchCatalog();

        $this->assertSame('Bearer zed-key', $http->calls[0]['options']['headers']['Authorization']);
    }

    public function testAVendorThatIsDownOrAnswersNonsenseGivesAnEmptyCatalogue(): void
    {
        $this->setEnv('vendor.acme.apiKey', 'k');

        FakeHttpGateway::install(failWith: 'Connection timed out');
        $this->assertSame([], $this->client()->fetchCatalog());

        FakeHttpGateway::install(body: 'this is not json');
        $this->assertSame([], $this->client()->fetchCatalog());

        FakeHttpGateway::install(body: '{"unexpected": "shape"}');
        $this->assertSame([], $this->client()->fetchCatalog());
    }

    // ------------------------------------------------------------------ orders

    /**
     * @return array<string, mixed>
     */
    private function order(): array
    {
        return [
            'vendor_product_ref' => 'SKU-9', 'quantity' => 2, 'recipient_name' => 'Mama Ruth',
            'recipient_phone' => '0700111222', 'delivery_details' => 'Ntinda, blue gate',
        ];
    }

    public function testAnOrderIsPostedInTheVendorsFormatAndTheirReferenceReturned(): void
    {
        $this->setEnv('vendor.acme.apiKey', 'secret-key');
        $http = FakeHttpGateway::install(201, json_encode(['order_id' => 'ORD-5521']));

        $result = $this->client()->submitOrder($this->order());

        $this->assertSame(['success' => true, 'vendor_order_ref' => 'ORD-5521', 'message' => 'Order placed with vendor.'], $result);
        $this->assertSame(['POST', 'https://vendor.example.test/api/orders'], [$http->calls[0]['method'], $http->calls[0]['url']]);
        $this->assertSame('Bearer secret-key', $http->calls[0]['options']['headers']['Authorization']);
        $this->assertSame([
            'product_id' => 'SKU-9', 'quantity' => 2, 'recipient_name' => 'Mama Ruth',
            'recipient_phone' => '0700111222', 'delivery_details' => 'Ntinda, blue gate',
        ], $http->lastPayload());
    }

    public function testAnOrderTheVendorDeclinesIsReportedAsDeclined(): void
    {
        $this->setEnv('vendor.acme.apiKey', 'k');

        foreach ([[422, '{"error": "out of stock"}'], [200, '{}'], [500, '']] as [$status, $body]) {
            FakeHttpGateway::install($status, $body);

            $this->assertSame(
                ['success' => false, 'vendor_order_ref' => null, 'message' => 'Vendor declined the order.'],
                $this->client()->submitOrder($this->order()),
                "HTTP {$status} with body '{$body}'"
            );
        }
    }

    public function testAnUnreachableVendorIsReportedWithoutAnException(): void
    {
        $this->setEnv('vendor.acme.apiKey', 'k');
        FakeHttpGateway::install(failWith: 'Connection refused');

        $result = $this->client()->submitOrder($this->order());

        $this->assertFalse($result['success']);
        $this->assertSame('Could not reach vendor: Connection refused', $result['message']);
    }

    public function testNoOrderIsSentWithoutAKeyOrAnAddress(): void
    {
        $http = FakeHttpGateway::install();
        $this->setEnv('vendor.acme.apiKey', null);

        $this->assertSame(
            ['success' => false, 'vendor_order_ref' => null, 'message' => 'Vendor is not configured.'],
            $this->client()->submitOrder($this->order())
        );
        $this->assertSame([], $http->calls);
    }
}
