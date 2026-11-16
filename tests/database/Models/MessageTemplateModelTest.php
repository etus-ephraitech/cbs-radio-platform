<?php

namespace Tests\Database\Models;

use App\Models\MessageTemplateModel;
use Tests\Support\TestCase\AppTestCase;

/**
 * The example messages offered on the booking form.
 *
 * @internal
 */
final class MessageTemplateModelTest extends AppTestCase
{
    private MessageTemplateModel $templates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templates = new MessageTemplateModel();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function template(string $title, array $overrides = []): int|string
    {
        $id = $this->templates->insert($overrides + [
            'category_id' => null,
            'language'    => 'English',
            'title'       => $title,
            'body'        => "Body of {$title}",
            'sort_order'  => 0,
            'is_active'   => 1,
        ]);

        $this->assertNotFalse($id, json_encode($this->templates->errors()));

        return $id;
    }

    public function testACategoryGetsItsOwnTemplatesAndTheOnesForAnyOccasion(): void
    {
        $birthdays = $this->makeCategory();
        $tributes  = $this->makeCategory();

        $this->template('Birthday only', ['category_id' => $birthdays['id']]);
        $this->template('Tribute only', ['category_id' => $tributes['id']]);
        $this->template('Any occasion');

        $titles = array_column($this->templates->forCategory($birthdays['id']), 'title');
        sort($titles);

        $this->assertSame(['Any occasion', 'Birthday only'], $titles);
    }

    public function testAnInactiveTemplateIsNotOffered(): void
    {
        $category = $this->makeCategory();

        $this->template('Shown', ['category_id' => $category['id']]);
        $this->template('Hidden', ['category_id' => $category['id'], 'is_active' => 0]);

        $this->assertSame(['Shown'], array_column($this->templates->forCategory($category['id']), 'title'));
    }

    public function testTemplatesComeInStaffOrderThenLanguageThenTitle(): void
    {
        $category = $this->makeCategory();

        $this->template('Zebra', ['category_id' => $category['id'], 'sort_order' => 0, 'language' => 'English']);
        $this->template('Apple', ['category_id' => $category['id'], 'sort_order' => 0, 'language' => 'English']);
        $this->template('Okusanyuka', ['category_id' => $category['id'], 'sort_order' => 0, 'language' => 'Luganda']);
        $this->template('First of all', ['category_id' => $category['id'], 'sort_order' => -0 + 0, 'language' => 'Swahili']);
        $this->template('Last', ['category_id' => $category['id'], 'sort_order' => 5, 'language' => 'English']);

        $this->assertSame(
            ['Apple', 'Zebra', 'Okusanyuka', 'First of all', 'Last'],
            array_column($this->templates->forCategory($category['id']), 'title'),
            'sort_order first (lower first), then language, then title.'
        );
    }

    public function testLanguagesAlwaysOfferEnglishAndLugandaPlusAnyInUse(): void
    {
        $this->assertSame(['English', 'Luganda'], $this->templates->languages());

        $this->template('Swahili one', ['language' => 'Swahili']);
        $this->template('English again', ['language' => 'English']);

        $this->assertSame(['English', 'Luganda', 'Swahili'], $this->templates->languages());
    }

    public function testTheAdminListShowsTheCategoryNameOrNoneForAnyOccasion(): void
    {
        $category = $this->makeCategory(['name' => 'Birthdays']);

        $this->template('Specific', ['category_id' => $category['id']]);
        $this->template('General');

        $byTitle = array_column($this->templates->adminList(), 'category_name', 'title');

        $this->assertSame('Birthdays', $byTitle['Specific']);
        $this->assertNull($byTitle['General']);
    }

    public function testATemplateNeedsATitleALanguageAndAMessage(): void
    {
        foreach (['title', 'language', 'body'] as $missing) {
            $data = ['language' => 'English', 'title' => 'T', 'body' => 'B'];
            $data[$missing] = '';

            $this->assertFalse($this->templates->insert($data), "A template with no {$missing} should be refused.");
            $this->assertArrayHasKey($missing, $this->templates->errors());
        }
    }

    public function testATitleIsLimitedToEightyCharacters(): void
    {
        $this->assertFalse($this->templates->insert(['language' => 'English', 'title' => str_repeat('x', 81), 'body' => 'B']));
        $this->assertNotFalse($this->templates->insert(['language' => 'English', 'title' => str_repeat('x', 80), 'body' => 'B']));
    }

    public function testThePlaceholdersAnyTemplateMayUse(): void
    {
        $this->assertSame(['recipient', 'sender'], MessageTemplateModel::TOKENS);
    }
}
