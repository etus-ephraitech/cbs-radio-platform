<?php

namespace Tests\Database\Services;

use App\Libraries\VendorGiftClients\VendorGiftClientInterface;
use App\Models\GiftModel;
use App\Services\GiftVendorSyncService;
use Tests\Support\Libraries\FakeVendorClient;
use Tests\Support\TestCase\AppTestCase;

/**
 * Pulling each vendor's catalogue into our gift list, without trampling what staff have decided.
 *
 * @internal
 */
final class GiftVendorSyncServiceTest extends AppTestCase
{
    /**
     * A sync service whose vendors answer from a script: slug => fake client.
     *
     * @param array<string, FakeVendorClient> $clients
     */
    private function syncing(array $clients): GiftVendorSyncService
    {
        return new class ($clients) extends GiftVendorSyncService {
            /** @param array<string, FakeVendorClient> $clients */
            public function __construct(private array $clients)
            {
                parent::__construct();
            }

            protected function vendorClient(array $vendor): VendorGiftClientInterface
            {
                return $this->clients[$vendor['slug']];
            }
        };
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function product(string $ref, array $overrides = []): array
    {
        return $overrides + [
            'vendor_product_ref' => $ref,
            'name'               => "Product {$ref}",
            'description'        => "About {$ref}",
            'price'              => 25000.0,
            'currency'           => 'UGX',
            'type'               => 'physical',
        ];
    }

    public function testANewCatalogueBecomesActiveGiftsLinkedToTheirVendor(): void
    {
        $vendor = $this->makeVendor(['slug' => 'acme']);
        $client = new FakeVendorClient([$this->product('A1', ['price' => 25000.0]), $this->product('A2', ['price' => 8000.0])]);

        $result = $this->syncing(['acme' => $client])->syncAll();

        $this->assertSame(['synced' => 2, 'errors' => []], $result);

        $gift = (new GiftModel())->findByVendorProduct((int) $vendor['id'], 'A1');
        $this->assertSame('Product A1', $gift['name']);
        $this->assertSame('physical', $gift['type']);
        $this->assertSame('vendor', $gift['source']);
        $this->assertSame((string) $vendor['id'], (string) $gift['vendor_id']);
        $this->assertSame('25000.00', $gift['price']);
        $this->assertSame('1', (string) $gift['is_active'], 'New products are offered straight away.');
        $this->assertNotNull($gift['synced_at']);
    }

    public function testWhatOnlySomeVendorsReportIsStoredWhenReportedAndKeptWhenNot(): void
    {
        $vendor = $this->makeVendor(['slug' => 'acme']);
        $client = new FakeVendorClient([$this->product('A1', ['image_url' => 'https://cdn.example.test/a1.jpg', 'minimum_delivery_hours' => 24, 'stock' => 7])]);
        $sync   = $this->syncing(['acme' => $client]);

        $sync->syncAll();

        $gift = (new GiftModel())->findByVendorProduct((int) $vendor['id'], 'A1');
        $this->assertSame(['https://cdn.example.test/a1.jpg', '24', '7'], [$gift['image_url'], (string) $gift['minimum_delivery_hours'], (string) $gift['stock_available']]);

        // A later sync from a vendor that does not report them must not blank what is known.
        $client->catalog = [$this->product('A1', ['price' => 26000.0])];
        $sync->syncAll();

        $gift = (new GiftModel())->findByVendorProduct((int) $vendor['id'], 'A1');
        $this->assertSame('26000.00', $gift['price']);
        $this->assertSame(['https://cdn.example.test/a1.jpg', '24', '7'], [$gift['image_url'], (string) $gift['minimum_delivery_hours'], (string) $gift['stock_available']]);
    }

    public function testStockThatRunsOutIsRecordedAsZeroNotForgotten(): void
    {
        $vendor = $this->makeVendor(['slug' => 'acme']);
        $client = new FakeVendorClient([$this->product('A1', ['stock' => 5])]);
        $sync   = $this->syncing(['acme' => $client]);
        $sync->syncAll();

        $client->catalog = [$this->product('A1', ['stock' => 0])];
        $sync->syncAll();

        $this->assertSame('0', (string) (new GiftModel())->findByVendorProduct((int) $vendor['id'], 'A1')['stock_available']);
    }

    public function testSyncingAgainUpdatesWhatChangedWithoutDuplicating(): void
    {
        $vendor = $this->makeVendor(['slug' => 'acme']);
        $client = new FakeVendorClient([$this->product('A1', ['price' => 25000.0])]);
        $sync   = $this->syncing(['acme' => $client]);

        $sync->syncAll();

        $client->catalog = [$this->product('A1', ['price' => 30000.0, 'name' => 'Renamed by the vendor'])];
        $sync->syncAll();

        $this->assertSame(1, $this->db->table('gifts')->countAllResults());

        $gift = (new GiftModel())->findByVendorProduct((int) $vendor['id'], 'A1');
        $this->assertSame('30000.00', $gift['price']);
        $this->assertSame('Renamed by the vendor', $gift['name']);
    }

    public function testAGiftStaffSwitchedOffStaysOffWhenTheVendorListsItAgain(): void
    {
        // The regression this guards: every sync used to re-activate gifts that staff had deliberately hidden.
        $vendor = $this->makeVendor(['slug' => 'acme']);
        $client = new FakeVendorClient([$this->product('A1')]);
        $sync   = $this->syncing(['acme' => $client]);

        $sync->syncAll();

        $gift = (new GiftModel())->findByVendorProduct((int) $vendor['id'], 'A1');
        (new GiftModel())->update($gift['id'], ['is_active' => 0]);

        $sync->syncAll();

        $this->assertSame('0', (string) (new GiftModel())->find($gift['id'])['is_active']);
    }

    public function testTheSameProductAtTwoVendorsIsTwoGifts(): void
    {
        $this->makeVendor(['slug' => 'acme']);
        $this->makeVendor(['slug' => 'zed']);

        $result = $this->syncing([
            'acme' => new FakeVendorClient([$this->product('SKU-1')]),
            'zed'  => new FakeVendorClient([$this->product('SKU-1')]),
        ])->syncAll();

        $this->assertSame(2, $result['synced']);
        $this->assertSame(2, $this->db->table('gifts')->countAllResults());
    }

    public function testAnInactiveVendorIsNotSynced(): void
    {
        $this->makeVendor(['slug' => 'acme', 'is_active' => 0]);
        $client = new FakeVendorClient([$this->product('A1')]);

        $result = $this->syncing(['acme' => $client])->syncAll();

        $this->assertSame(['synced' => 0, 'errors' => []], $result);
        $this->assertSame(0, $this->db->table('gifts')->countAllResults());
    }

    public function testOneVendorBeingDownDoesNotStopTheOthers(): void
    {
        $this->makeVendor(['name' => 'Acme', 'slug' => 'acme']);
        $this->makeVendor(['name' => 'Zed', 'slug' => 'zed']);

        $result = $this->syncing([
            'acme' => new FakeVendorClient(failCatalogWith: 'Connection refused'),
            'zed'  => new FakeVendorClient([$this->product('Z1')]),
        ])->syncAll();

        $this->assertSame(1, $result['synced']);
        $this->assertSame(['Acme: Connection refused'], $result['errors'], 'The failure names the vendor so staff know whose catalogue is stale.');
        $this->assertSame(1, $this->db->table('gifts')->countAllResults());
    }

    public function testAnEmptyCatalogueIsNotAnError(): void
    {
        $this->makeVendor(['slug' => 'acme']);

        $this->assertSame(['synced' => 0, 'errors' => []], $this->syncing(['acme' => new FakeVendorClient([])])->syncAll());
    }

    public function testAnEmptyCatalogueDoesNotRemoveGiftsAlreadyHere(): void
    {
        // A vendor API that hiccups and returns nothing must not wipe the gifts staff already offer.
        $vendor = $this->makeVendor(['slug' => 'acme']);
        $client = new FakeVendorClient([$this->product('A1')]);
        $sync   = $this->syncing(['acme' => $client]);

        $sync->syncAll();
        $client->catalog = [];
        $sync->syncAll();

        $this->assertNotNull((new GiftModel())->findByVendorProduct((int) $vendor['id'], 'A1'));
    }

    public function testWithNoVendorsThereIsNothingToDo(): void
    {
        $this->assertSame(['synced' => 0, 'errors' => []], $this->syncing([])->syncAll());
    }
}
