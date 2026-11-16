<?php

namespace Tests\Feature;

use App\Models\CategoryModel;
use App\Models\GiftModel;
use App\Models\GiftVendorModel;
use App\Models\RateTierModel;
use App\Models\ShowModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\FakeHttpGateway;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Managing what the station sells: occasions, packages and prices, shows, gifts and gift vendors. Nothing here is
 * ever deleted, only switched off, because bookings and orders point at it.
 *
 * @internal
 */
final class AdminCatalogTest extends AppFeatureTestCase
{
    use SetsEnvironment;

    // ================================================================== occasions (categories)

    public function testAnOccasionIsCreatedWithAWebAddressMadeFromItsName(): void
    {
        $this->post('admin/categories', ['name' => 'Tributes & In Memoriam', 'description' => 'Respectful notices', 'is_active' => '1'])->assertRedirect();

        $this->assertSame('Category created.', $this->flash('toastr_success'));

        $category = (new CategoryModel())->findBySlug('tributes-in-memoriam');
        $this->assertSame('Tributes & In Memoriam', $category['name']);
        $this->assertSame('1', (string) $category['is_active']);
    }

    public function testTwoOccasionsWithTheSameNameGetDistinctAddresses(): void
    {
        $this->post('admin/categories', ['name' => 'Birthdays', 'is_active' => '1']);
        $this->post('admin/categories', ['name' => 'Birthdays', 'is_active' => '1']);

        $this->assertSame(['birthdays', 'birthdays-2'], array_column($this->db->table('categories')->orderBy('id')->get()->getResultArray(), 'slug'));
    }

    public function testAnOccasionLeftUnticketedIsCreatedSwitchedOff(): void
    {
        $this->post('admin/categories', ['name' => 'Draft occasion']);

        $this->assertSame('0', (string) (new CategoryModel())->findBySlug('draft-occasion')['is_active']);
    }

    public function testAnOccasionNeedsAName(): void
    {
        $this->post('admin/categories', ['name' => ''])->assertRedirect();

        $this->assertArrayHasKey('name', $this->flash('errors'));
        $this->assertSame(0, $this->db->table('categories')->countAllResults());
    }

    public function testEditingAnOccasionWithoutRenamingItSavesTheChanges(): void
    {
        // The regression behind this whole round of testing: this used to save nothing and say nothing.
        $category = $this->makeCategory(['name' => 'Birthdays', 'slug' => 'birthdays', 'description' => 'Old']);

        $this->post('admin/categories/' . $category['id'] . '/update', ['name' => 'Birthdays', 'description' => 'A brand new description', 'is_active' => '1'])->assertRedirect();

        $this->assertSame('Category updated.', $this->flash('toastr_success'));

        $after = (new CategoryModel())->find($category['id']);
        $this->assertSame('A brand new description', $after['description']);
        $this->assertSame('birthdays', $after['slug'], 'The public web address is kept when the name is not changed.');
    }

    public function testSwitchingAnOccasionOffThroughTheEditFormWorks(): void
    {
        $category = $this->makeCategory(['name' => 'Birthdays', 'slug' => 'birthdays']);

        $this->post('admin/categories/' . $category['id'] . '/update', ['name' => 'Birthdays']);   // is_active not ticked

        $this->assertSame('0', (string) (new CategoryModel())->find($category['id'])['is_active']);
    }

    public function testRenamingAnOccasionMakesANewAddressAndNeverCollides(): void
    {
        $this->makeCategory(['name' => 'Weddings', 'slug' => 'weddings']);
        $category = $this->makeCategory(['name' => 'Old name', 'slug' => 'old-name']);

        $this->post('admin/categories/' . $category['id'] . '/update', ['name' => 'Weddings', 'is_active' => '1']);

        $this->assertSame('weddings-2', (new CategoryModel())->find($category['id'])['slug']);
    }

    public function testAnOccasionIsSwitchedOffAndOnNotDeleted(): void
    {
        $category = $this->makeCategory();

        $this->post('admin/categories/' . $category['id'] . '/deactivate');
        $this->assertSame('Category deactivated.', $this->flash('toastr_success'));
        $this->assertSame('0', (string) (new CategoryModel())->find($category['id'])['is_active']);

        $this->post('admin/categories/' . $category['id'] . '/activate');
        $this->assertSame('Category activated.', $this->flash('toastr_success'));
        $this->assertSame('1', (string) (new CategoryModel())->find($category['id'])['is_active']);
    }

    public function testUpdatingAnUnknownOccasionIsReported(): void
    {
        $this->post('admin/categories/999999/update', ['name' => 'Ghost'])->assertRedirect();

        $this->assertSame('Category not found.', $this->flash('toastr_error'));
    }

    // ================================================================== shows

    public function testAShowIsCreatedWithItsDaysStoredInOrder(): void
    {
        $this->post('admin/shows', ['name' => 'Morning Express', 'start_time' => '06:00', 'end_time' => '10:00', 'days_of_week' => ['5', '1', '3'], 'is_active' => '1'])->assertRedirect();

        $this->assertSame('Show created.', $this->flash('toastr_success'));

        $show = $this->db->table('shows')->get()->getRowArray();
        $this->assertSame('1,3,5', $show['days_of_week']);
        $this->assertSame('06:00:00', $show['start_time']);
    }

    public function testNoDaysTickedMeansEveryDay(): void
    {
        $this->post('admin/shows', ['name' => 'Every day show', 'start_time' => '06:00', 'end_time' => '10:00', 'is_active' => '1']);

        $this->assertNull($this->db->table('shows')->get()->getRowArray()['days_of_week']);
    }

    public function testAShowNeedsANameAndBothTimes(): void
    {
        foreach (['name', 'start_time', 'end_time'] as $missing) {
            $data = ['name' => 'X', 'start_time' => '06:00', 'end_time' => '10:00'];
            $data[$missing] = '';

            $this->post('admin/shows', $data);

            $this->assertArrayHasKey($missing, $this->flash('errors'), "A show with no {$missing}.");
        }

        $this->assertSame(0, $this->db->table('shows')->countAllResults());
    }

    public function testACraftedDaysValueIsCleanedUpNotTrusted(): void
    {
        // The form sends a list of ticked boxes. A hand-made request may send anything: a lone value, junk, or
        // days that do not exist. None of it may crash the page or end up stored as a "day".
        $this->post('admin/shows', ['name' => 'Crafted', 'start_time' => '06:00', 'end_time' => '10:00', 'days_of_week' => ['0', '8', 'abc', '3', '3', '-1', '7']])->assertRedirect();

        $this->assertSame('3,7', $this->db->table('shows')->get()->getRowArray()['days_of_week']);
    }

    public function testADaysValueThatIsNotEvenAListDoesNotCrashTheSave(): void
    {
        $this->post('admin/shows', ['name' => 'Lone value', 'start_time' => '06:00', 'end_time' => '10:00', 'days_of_week' => '4'])->assertRedirect();

        $this->assertSame('Show created.', $this->flash('toastr_success'));
        $this->assertSame('4', $this->db->table('shows')->get()->getRowArray()['days_of_week']);
    }

    public function testAShowCanBeEditedAndSwitchedOffAndOn(): void
    {
        $show = $this->makeShow(['name' => 'Old name']);

        $this->post('admin/shows/' . $show['id'] . '/update', ['name' => 'New name', 'start_time' => '07:00', 'end_time' => '09:00', 'days_of_week' => ['6', '7'], 'is_active' => '1']);
        $this->assertSame('Show updated.', $this->flash('toastr_success'));

        $after = (new ShowModel())->find($show['id']);
        $this->assertSame(['New name', '07:00:00', '6,7'], [$after['name'], $after['start_time'], $after['days_of_week']]);

        $this->post('admin/shows/' . $show['id'] . '/deactivate');
        $this->assertSame('Show deactivated.', $this->flash('toastr_success'));
        $this->post('admin/shows/' . $show['id'] . '/activate');
        $this->assertSame('Show activated.', $this->flash('toastr_success'));
    }

    public function testAnUnknownShowIsReported(): void
    {
        $this->post('admin/shows/999999/update', ['name' => 'Ghost', 'start_time' => '06:00', 'end_time' => '10:00']);

        $this->assertSame('Show not found.', $this->flash('toastr_error'));
    }

    // ================================================================== packages (rate tiers)

    public function testAPackageIsCreatedInTheDefaultCurrencyWhenNoneIsGiven(): void
    {
        $category = $this->makeCategory();

        $this->post('admin/rate-tiers', ['category_id' => $category['id'], 'package_level' => 'Voice + text', 'features' => 'Readout and a voice note', 'includes_on_air' => '1', 'price' => '30000', 'is_active' => '1'])->assertRedirect();

        $this->assertSame('Package created.', $this->flash('toastr_success'));

        $tier = $this->db->table('rate_tiers')->get()->getRowArray();
        $this->assertSame(['Voice + text', '30000.00', config('CbsRadio')->defaultCurrency, '1'], [$tier['package_level'], $tier['price'], $tier['currency'], (string) $tier['includes_on_air']]);
    }

    public function testAPackageNeedsAnOccasionANameAndAPrice(): void
    {
        $category = $this->makeCategory();

        foreach (['category_id', 'package_level', 'price'] as $missing) {
            $data = ['category_id' => $category['id'], 'package_level' => 'X', 'price' => '100'];
            unset($data[$missing]);

            $this->post('admin/rate-tiers', $data);

            $this->assertArrayHasKey($missing, $this->flash('errors'), "A package with no {$missing}.");
        }

        $this->assertSame(0, $this->db->table('rate_tiers')->countAllResults());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function priceThatIsNotAPrice(): array
    {
        return [
            'negative'              => ['-15000'],
            'negative with decimal' => ['-0.01'],
            'words'                 => ['cheap'],
            'a thousands comma'     => ['15,000'],
            'two decimal points'    => ['1.5.0'],
        ];
    }

    #[DataProvider('priceThatIsNotAPrice')]
    public function testAPackagePriceMustBeAPlainNonNegativeNumber(string $price): void
    {
        $category = $this->makeCategory();

        $this->post('admin/rate-tiers', ['category_id' => $category['id'], 'package_level' => 'Typo', 'price' => $price]);

        $this->assertArrayHasKey('price', (array) $this->flash('errors'), "'{$price}' should not be accepted as a price.");
        $this->assertSame(0, $this->db->table('rate_tiers')->countAllResults());
    }

    public function testAFreePackageIsAllowed(): void
    {
        $category = $this->makeCategory();

        $this->post('admin/rate-tiers', ['category_id' => $category['id'], 'package_level' => 'Free trial', 'price' => '0']);

        $this->assertSame(1, $this->db->table('rate_tiers')->countAllResults());
    }

    public function testAPackageCanBeEditedAndSwitchedOffAndOn(): void
    {
        $tier = $this->makeTier(null, ['package_level' => 'Old', 'price' => 10000]);

        $this->post('admin/rate-tiers/' . $tier['id'] . '/update', ['category_id' => $tier['category_id'], 'package_level' => 'New', 'price' => '25000', 'currency' => 'USD', 'is_active' => '1']);
        $this->assertSame('Package updated.', $this->flash('toastr_success'));

        $after = (new RateTierModel())->find($tier['id']);
        $this->assertSame(['New', '25000.00', 'USD'], [$after['package_level'], $after['price'], $after['currency']]);

        $this->post('admin/rate-tiers/' . $tier['id'] . '/deactivate');
        $this->assertSame('Package deactivated.', $this->flash('toastr_success'));
        $this->post('admin/rate-tiers/' . $tier['id'] . '/activate');
        $this->assertSame('Package activated.', $this->flash('toastr_success'));
    }

    public function testAnUnknownPackageIsReported(): void
    {
        $this->post('admin/rate-tiers/999999/update', ['category_id' => 1, 'package_level' => 'Ghost', 'price' => '1']);

        $this->assertSame('Package not found.', $this->flash('toastr_error'));
    }

    // ================================================================== gifts

    public function testAGiftOfOurOwnIsCreatedAsLocal(): void
    {
        $this->post('admin/gifts', ['name' => 'Birthday e-card', 'type' => 'ecard', 'description' => 'A pretty card', 'price' => '5000', 'is_active' => '1'])->assertRedirect();

        $this->assertSame('Gift created.', $this->flash('toastr_success'));

        $gift = $this->db->table('gifts')->get()->getRowArray();
        $this->assertSame(['Birthday e-card', 'ecard', '5000.00', 'local'], [$gift['name'], $gift['type'], $gift['price'], $gift['source']]);
    }

    public function testTheSourceOfAGiftCannotBeChosenByTheForm(): void
    {
        $this->post('admin/gifts', ['name' => 'Sneaky', 'type' => 'ecard', 'price' => '100', 'source' => 'vendor', 'vendor_id' => 1]);

        $this->assertSame('local', $this->db->table('gifts')->get()->getRowArray()['source']);
    }

    public function testAGiftNeedsANameAKnownTypeAndASensiblePrice(): void
    {
        foreach (
            [
            ['name', ''], ['type', 'puppy'], ['type', ''], ['price', ''], ['price', '-5'],
            ] as [$field, $value]
        ) {
            $this->post('admin/gifts', ['name' => 'X', 'type' => 'ecard', 'price' => '100', $field => $value]);

            $this->assertArrayHasKey($field, (array) $this->flash('errors'), "{$field} = '{$value}'");
        }

        $this->assertSame(0, $this->db->table('gifts')->countAllResults());
    }

    public function testAGiftOfOurOwnCanBeEditedAndSwitchedOffAndOn(): void
    {
        $gift = $this->makeGift(['name' => 'Old card', 'price' => 1000]);

        $this->post('admin/gifts/' . $gift['id'] . '/update', ['name' => 'New card', 'type' => 'shoutout', 'price' => '2500', 'is_active' => '1']);
        $this->assertSame('Gift updated.', $this->flash('toastr_success'));
        $this->assertSame(['New card', 'shoutout', '2500.00'], array_values(array_intersect_key((new GiftModel())->find($gift['id']), array_flip(['name', 'type', 'price']))));

        $this->post('admin/gifts/' . $gift['id'] . '/deactivate');
        $this->assertSame('Gift deactivated.', $this->flash('toastr_success'));
        $this->post('admin/gifts/' . $gift['id'] . '/activate');
        $this->assertSame('Gift activated.', $this->flash('toastr_success'));
    }

    public function testAVendorsGiftCannotBeEditedHereButCanBeSwitchedOff(): void
    {
        $vendor = $this->makeVendor();
        $gift   = $this->makeGift(['name' => 'Rose bouquet', 'type' => 'physical', 'source' => 'vendor', 'vendor_id' => $vendor['id']]);

        $this->get('admin/gifts/' . $gift['id'] . '/edit')->assertRedirect();
        $this->assertSame('Vendor-sourced gifts are managed by the vendor sync, not edited here.', $this->flash('toastr_error'));

        $this->post('admin/gifts/' . $gift['id'] . '/update', ['name' => 'Hijacked', 'type' => 'ecard', 'price' => '1']);
        $this->assertSame('This gift cannot be edited here.', $this->flash('toastr_error'));
        $this->assertSame('Rose bouquet', (new GiftModel())->find($gift['id'])['name']);

        $this->post('admin/gifts/' . $gift['id'] . '/deactivate');
        $this->assertSame('0', (string) (new GiftModel())->find($gift['id'])['is_active'], 'Staff can still choose not to offer a vendor product.');
    }

    // ================================================================== gift vendors

    public function testAVendorIsCreatedAndToldWhichKeyToAddToTheServer(): void
    {
        $this->post('admin/gift-vendors', ['name' => 'Acme Flowers', 'api_base_url' => 'https://api.acme.example.test', 'is_active' => '1'])->assertRedirect();

        $this->assertSame('Vendor created. Add vendor.acme-flowers.apiKey to .env before syncing.', $this->flash('toastr_success'));
        $this->assertSame('acme-flowers', (new GiftVendorModel())->first()['slug']);
    }

    public function testAVendorsAddressMustBeAWebAddress(): void
    {
        $this->post('admin/gift-vendors', ['name' => 'Bad', 'api_base_url' => 'not a url']);

        $this->assertArrayHasKey('api_base_url', $this->flash('errors'));
        $this->assertSame(0, $this->db->table('gift_vendors')->countAllResults());
    }

    public function testEditingAVendorWithoutRenamingItSavesTheChanges(): void
    {
        $vendor = $this->makeVendor(['name' => 'Acme', 'slug' => 'acme', 'api_base_url' => 'https://old.example.test/api']);

        $this->post('admin/gift-vendors/' . $vendor['id'] . '/update', ['name' => 'Acme', 'api_base_url' => 'https://new.example.test/api', 'is_active' => '1']);

        $this->assertSame('Vendor updated.', $this->flash('toastr_success'));
        $this->assertSame('https://new.example.test/api', (new GiftVendorModel())->find($vendor['id'])['api_base_url']);
        $this->assertSame('acme', (new GiftVendorModel())->find($vendor['id'])['slug'], 'The slug names the .env key, so it must not change when nothing was renamed.');
    }

    public function testSwitchingAVendorOffExplainsWhatHappensToTheirProducts(): void
    {
        $vendor = $this->makeVendor();

        $this->post('admin/gift-vendors/' . $vendor['id'] . '/deactivate');

        $this->assertSame('Vendor deactivated — their products stay but will no longer re-sync.', $this->flash('toastr_success'));

        $this->post('admin/gift-vendors/' . $vendor['id'] . '/activate');
        $this->assertSame('Vendor activated.', $this->flash('toastr_success'));
    }

    public function testSyncNowPullsEveryActiveVendorsCatalogue(): void
    {
        $this->makeVendor(['slug' => 'acme', 'api_base_url' => 'https://vendor.example.test/api']);
        $this->setEnv('vendor.acme.apiKey', 'secret');
        FakeHttpGateway::install(200, json_encode(['data' => [
            ['id' => 'A1', 'name' => 'Rose bouquet', 'price' => 65000],
            ['id' => 'A2', 'name' => 'Chocolates', 'price' => 20000],
        ]]));

        $this->post('admin/gift-vendors/sync')->assertRedirect();

        $this->assertSame('Synced 2 product(s).', $this->flash('toastr_success'));
        $this->assertSame(2, $this->db->table('gifts')->where('source', 'vendor')->countAllResults());
    }

    public function testSyncNowWithNoVendorsSyncsNothing(): void
    {
        $this->post('admin/gift-vendors/sync')->assertRedirect();

        $this->assertSame('Synced 0 product(s).', $this->flash('toastr_success'));
    }

    public function testAVendorThatIsDownLooksLikeAnEmptyCatalogueToday(): void
    {
        // Pinned because it is a real limitation: the generic REST client swallows its own errors and returns an
        // empty list, so a vendor outage and a vendor with nothing to sell look identical to the person who pressed
        // the button. Making the client report failures would be a deliberate change, and would change this test.
        $this->makeVendor(['slug' => 'acme']);
        $this->setEnv('vendor.acme.apiKey', 'secret');
        FakeHttpGateway::install(failWith: 'Connection refused');

        $this->post('admin/gift-vendors/sync');

        $this->assertSame('Synced 0 product(s).', $this->flash('toastr_success'));
    }
}
