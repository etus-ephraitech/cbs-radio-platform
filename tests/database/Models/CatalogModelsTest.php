<?php

namespace Tests\Database\Models;

use App\Models\CategoryModel;
use App\Models\GiftVendorModel;
use App\Models\RateTierModel;
use Tests\Support\TestCase\AppTestCase;

/**
 * Categories, their packages (rate tiers) and gift vendors: what is offered, and what is hidden.
 *
 * @internal
 */
final class CatalogModelsTest extends AppTestCase
{
    // ------------------------------------------------------------------ categories

    public function testActiveCategoriesAreSortedByNameAndInactiveOnesLeftOut(): void
    {
        $this->makeCategory(['name' => 'Weddings']);
        $this->makeCategory(['name' => 'Birthdays']);
        $this->makeCategory(['name' => 'Hidden', 'is_active' => 0]);

        $this->assertSame(['Birthdays', 'Weddings'], array_column((new CategoryModel())->active(), 'name'));
    }

    public function testACategoryIsFoundBySlugIncludingAnInactiveOne(): void
    {
        $this->makeCategory(['name' => 'Birthdays', 'slug' => 'birthdays']);
        $this->makeCategory(['name' => 'Old', 'slug' => 'old', 'is_active' => 0]);

        $model = new CategoryModel();

        $this->assertSame('Birthdays', $model->findBySlug('birthdays')['name']);
        $this->assertSame('Old', $model->findBySlug('old')['name'], 'Callers decide what to do about an inactive category.');
        $this->assertNull($model->findBySlug('no-such-slug'));
    }

    public function testTwoCategoriesCannotShareASlug(): void
    {
        $this->makeCategory(['slug' => 'birthdays']);

        $model = new CategoryModel();

        $this->assertFalse($model->insert(['name' => 'Another', 'slug' => 'birthdays']));
        $this->assertArrayHasKey('slug', $model->errors());
    }

    public function testACategoryMayBeSavedAgainWithItsOwnSlug(): void
    {
        $category = $this->makeCategory(['slug' => 'birthdays']);

        $this->assertTrue((new CategoryModel())->update($category['id'], ['name' => 'Renamed', 'slug' => 'birthdays']));
    }

    public function testEditingACategoryWithoutRenamingItSavesTheChanges(): void
    {
        // The regression: saving a row with its slug unchanged was refused as a duplicate of itself, so an
        // administrator who edited only the description saw nothing happen and nothing was saved.
        $category = $this->makeCategory(['name' => 'Birthdays', 'slug' => 'birthdays', 'description' => 'Old']);

        $saved = (new CategoryModel())->update($category['id'], [
            'name' => 'Birthdays', 'slug' => 'birthdays', 'description' => 'New description', 'is_active' => 0,
        ]);

        $this->assertTrue($saved);

        $reloaded = (new CategoryModel())->find($category['id']);
        $this->assertSame('New description', $reloaded['description']);
        $this->assertSame('0', (string) $reloaded['is_active']);
    }

    public function testTakingAnotherCategorysSlugIsStillRefusedOnUpdate(): void
    {
        $first  = $this->makeCategory(['slug' => 'birthdays']);
        $second = $this->makeCategory(['slug' => 'weddings']);

        $model = new CategoryModel();

        $this->assertFalse($model->update($second['id'], ['name' => 'Weddings', 'slug' => $first['slug']]));
        $this->assertArrayHasKey('slug', $model->errors());
        $this->assertSame('weddings', $model->find($second['id'])['slug']);
    }

    public function testTheIdUsedForTheCheckIsNeverWrittenAsData(): void
    {
        $category = $this->makeCategory(['slug' => 'birthdays']);
        $other    = $this->makeCategory(['slug' => 'weddings']);

        (new CategoryModel())->update($category['id'], ['name' => 'Changed', 'slug' => 'birthdays']);

        $this->assertSame((int) $category['id'], (int) (new CategoryModel())->findBySlug('birthdays')['id']);
        $this->assertSame((int) $other['id'], (int) (new CategoryModel())->findBySlug('weddings')['id']);
    }

    public function testSlugsAreMadeUniqueByNumbering(): void
    {
        $this->makeCategory(['slug' => 'birthdays']);
        $this->makeCategory(['slug' => 'birthdays-2']);

        $model = new CategoryModel();

        $this->assertSame('birthdays-3', $model->generateUniqueSlug('Birthdays'));
        $this->assertSame('fresh-name', $model->generateUniqueSlug('Fresh Name'));
    }

    public function testARowKeepsItsOwnSlugWhenCheckingForOne(): void
    {
        $category = $this->makeCategory(['slug' => 'birthdays']);

        $this->assertSame('birthdays', (new CategoryModel())->generateUniqueSlug('Birthdays', (int) $category['id']));
    }

    // ------------------------------------------------------------------ packages

    public function testACategorysPackagesAreActiveOnesCheapestFirst(): void
    {
        $category = $this->makeCategory();
        $other    = $this->makeCategory();

        $this->makeTier($category['id'], ['package_level' => 'Premium', 'price' => 50000]);
        $this->makeTier($category['id'], ['package_level' => 'Basic', 'price' => 15000]);
        $this->makeTier($category['id'], ['package_level' => 'Retired', 'price' => 1000, 'is_active' => 0]);
        $this->makeTier($other['id'], ['package_level' => 'Elsewhere', 'price' => 100]);

        $this->assertSame(
            ['Basic', 'Premium'],
            array_column((new RateTierModel())->forCategory($category['id']), 'package_level')
        );
    }

    public function testBookablePricingIsActivePackagesInActiveCategories(): void
    {
        $weddings  = $this->makeCategory(['name' => 'Weddings']);
        $birthdays = $this->makeCategory(['name' => 'Birthdays']);
        $closed    = $this->makeCategory(['name' => 'Closed', 'is_active' => 0]);

        $this->makeTier($weddings['id'], ['package_level' => 'Wedding gold', 'price' => 80000]);
        $this->makeTier($birthdays['id'], ['package_level' => 'Voice', 'price' => 30000]);
        $this->makeTier($birthdays['id'], ['package_level' => 'Text', 'price' => 15000]);
        $this->makeTier($birthdays['id'], ['package_level' => 'Retired', 'price' => 5000, 'is_active' => 0]);
        $this->makeTier($closed['id'], ['package_level' => 'In a closed category', 'price' => 1]);

        $pricing = (new RateTierModel())->activePricing();

        $this->assertSame(
            ['Text', 'Voice', 'Wedding gold'],
            array_column($pricing, 'package_level'),
            'Category name first, then price. Nothing inactive, and nothing in an inactive category.'
        );
        $this->assertSame(['Birthdays', 'Birthdays', 'Weddings'], array_column($pricing, 'category_name'));
        $this->assertSame($birthdays['slug'], $pricing[0]['category_slug']);
    }

    public function testTheAdminListIncludesEverythingWithItsCategoryName(): void
    {
        $category = $this->makeCategory(['name' => 'Birthdays']);

        $this->makeTier($category['id'], ['package_level' => 'Live', 'price' => 10]);
        $this->makeTier($category['id'], ['package_level' => 'Retired', 'price' => 20, 'is_active' => 0]);

        $all = (new RateTierModel())->allWithCategory();

        $this->assertSame(['Live', 'Retired'], array_column($all, 'package_level'));
        $this->assertSame(['Birthdays', 'Birthdays'], array_column($all, 'category_name'));
    }

    public function testAPackageNeedsACategoryANameAndAPrice(): void
    {
        $model = new RateTierModel();

        $this->assertFalse($model->insert(['package_level' => 'Orphan', 'price' => 10]));
        $this->assertArrayHasKey('category_id', $model->errors());

        $this->assertFalse($model->insert(['category_id' => $this->makeCategory()['id'], 'package_level' => 'Free?']));
        $this->assertArrayHasKey('price', $model->errors());
    }

    public function testNeitherAPackageNorAGiftCanHaveANegativePrice(): void
    {
        $category = $this->makeCategory();

        $tiers = new RateTierModel();
        $this->assertFalse($tiers->insert(['category_id' => $category['id'], 'package_level' => 'Typo', 'price' => '-15000']));
        $this->assertArrayHasKey('price', $tiers->errors());
        $this->assertNotFalse($tiers->insert(['category_id' => $category['id'], 'package_level' => 'Free', 'price' => '0']));

        $gifts = new \App\Models\GiftModel();
        $this->assertFalse($gifts->insert(['name' => 'Typo', 'type' => 'ecard', 'price' => '-1']));
        $this->assertArrayHasKey('price', $gifts->errors());
    }

    // ------------------------------------------------------------------ vendors

    public function testActiveVendorsAreSortedByName(): void
    {
        $this->makeVendor(['name' => 'Zed Flowers']);
        $this->makeVendor(['name' => 'Acme Gifts']);
        $this->makeVendor(['name' => 'Gone', 'is_active' => 0]);

        $this->assertSame(['Acme Gifts', 'Zed Flowers'], array_column((new GiftVendorModel())->active(), 'name'));
    }

    public function testEditingAVendorWithoutRenamingItSavesTheChanges(): void
    {
        $vendor = $this->makeVendor(['name' => 'Acme', 'slug' => 'acme', 'api_base_url' => 'https://old.example.test/api']);

        $saved = (new GiftVendorModel())->update($vendor['id'], [
            'name' => 'Acme', 'slug' => 'acme', 'api_base_url' => 'https://new.example.test/api', 'is_active' => 1,
        ]);

        $this->assertTrue($saved);
        $this->assertSame('https://new.example.test/api', (new GiftVendorModel())->find($vendor['id'])['api_base_url']);
    }

    public function testAVendorsAddressMustBeAUrl(): void
    {
        $model = new GiftVendorModel();

        $this->assertFalse($model->insert(['name' => 'Bad', 'slug' => 'bad', 'api_base_url' => 'not a url']));
        $this->assertArrayHasKey('api_base_url', $model->errors());
        $this->assertNotFalse($model->insert(['name' => 'Fine', 'slug' => 'fine', 'api_base_url' => 'https://vendor.example.test/api']));
    }
}
