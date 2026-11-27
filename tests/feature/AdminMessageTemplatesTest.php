<?php

namespace Tests\Feature;

use App\Models\MessageTemplateModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Admin > Message templates: the example messages visitors can start from, in any language.
 *
 * @internal
 */
final class AdminMessageTemplatesTest extends AppFeatureTestCase
{
    private function visible(string $html): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + ['title' => 'Warm wish', 'category_id' => '', 'language' => 'English', 'body' => 'Happy birthday {recipient}! From {sender}.', 'sort_order' => '0', 'is_active' => '1'];
    }

    private function templateCount(): int
    {
        return $this->db->table('message_templates')->countAllResults();
    }

    // ------------------------------------------------------------------ the list

    public function testTheListShowsEachTemplateWithItsOccasionLanguageAndStatus(): void
    {
        $category = $this->makeCategory(['name' => 'Birthdays']);
        $model    = new MessageTemplateModel();
        $model->insert(['category_id' => $category['id'], 'language' => 'Luganda', 'title' => 'Okusanyuka', 'body' => 'Eky omwaka {recipient}']);
        $model->insert(['category_id' => null, 'language' => 'English', 'title' => 'Any occasion one', 'body' => 'Thinking of you', 'is_active' => 0]);

        $visible = $this->visible($this->get('admin/message-templates')->getBody());

        foreach (['Okusanyuka', 'Birthdays', 'Luganda', 'Eky omwaka {recipient}', 'Any occasion', 'English'] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
    }

    public function testWithNoTemplatesTheListExplainsWhatVisitorsWillSee(): void
    {
        $this->assertStringContainsString('No templates yet. Visitors will see an empty message box.', $this->visible($this->get('admin/message-templates')->getBody()));
    }

    public function testWhatStaffTypeIsEscapedWhereItIsShown(): void
    {
        (new MessageTemplateModel())->insert(['language' => 'English', 'title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)> {recipient}']);

        foreach (['admin/message-templates', 'admin/message-templates/1/edit'] as $path) {
            $body = $this->get($path)->getBody();

            $this->assertStringNotContainsString('<script>alert(1)</script>', $body, $path);
            $this->assertStringNotContainsString('<img src=x', $body, $path);
        }
    }

    // ------------------------------------------------------------------ creating

    public function testATemplateIsCreatedForAnOccasionOrForAnyOccasion(): void
    {
        $category = $this->makeCategory();

        $this->post('admin/message-templates', $this->form(['title' => 'Specific', 'category_id' => $category['id']]))->assertRedirect();
        $this->assertSame('Template created.', $this->flash('toastr_success'));

        $this->post('admin/message-templates', $this->form(['title' => 'General', 'category_id' => '']));

        $rows = array_column($this->db->table('message_templates')->orderBy('id')->get()->getResultArray(), 'category_id', 'title');
        $this->assertSame((string) $category['id'], (string) $rows['Specific']);
        $this->assertNull($rows['General']);
    }

    public function testAnyLanguageStaffTypeIsAccepted(): void
    {
        $this->post('admin/message-templates', $this->form(['language' => 'Kiswahili']));

        $this->assertSame('Kiswahili', $this->db->table('message_templates')->get()->getRowArray()['language']);
    }

    public function testLineEndingsAreUnifiedAndTheMessageTrimmed(): void
    {
        $this->post('admin/message-templates', $this->form(['body' => "  Hello {recipient}\r\nFrom {sender}\r  "]));

        $this->assertSame("Hello {recipient}\nFrom {sender}", $this->db->table('message_templates')->get()->getRowArray()['body']);
    }

    public function testANegativeOrderBecomesZero(): void
    {
        $this->post('admin/message-templates', $this->form(['sort_order' => '-7']));

        $this->assertSame('0', (string) $this->db->table('message_templates')->get()->getRowArray()['sort_order']);
    }

    public function testATemplateLeftUntickedIsCreatedHidden(): void
    {
        $data = $this->form();
        unset($data['is_active']);

        $this->post('admin/message-templates', $data);

        $this->assertSame('0', (string) $this->db->table('message_templates')->get()->getRowArray()['is_active']);
    }

    // ------------------------------------------------------------------ refusing

    /**
     * @return array<string, array{0: string}>
     */
    public static function missingParts(): array
    {
        return ['title' => ['title'], 'language' => ['language'], 'message' => ['body']];
    }

    #[DataProvider('missingParts')]
    public function testATemplateNeedsATitleALanguageAndAMessage(string $field): void
    {
        $this->post('admin/message-templates', $this->form([$field => '']))->assertRedirect();

        $this->assertNotEmpty($this->flash('errors'));
        $this->assertSame(0, $this->templateCount());
    }

    public function testAnUnknownPlaceholderIsRefusedAndTheAllowedOnesListed(): void
    {
        $this->post('admin/message-templates', $this->form(['body' => 'Hi {recipent}!']))->assertRedirect();

        $this->assertSame(
            ['The message uses {recipent}, which is not a known placeholder. You can use: {recipient}, {sender}.'],
            $this->flash('errors')
        );
        $this->assertSame(0, $this->templateCount());
    }

    public function testBothKnownPlaceholdersAreAccepted(): void
    {
        $this->post('admin/message-templates', $this->form(['body' => '{recipient} {recipient} {sender}']));

        $this->assertSame(1, $this->templateCount());
    }

    public function testAMessageLongerThanTheBookingFormAllowsIsRefusedWithBothNumbers(): void
    {
        $this->post('admin/message-templates', $this->form(['body' => str_repeat('x', 501)]));

        $this->assertSame(['The message is 501 characters; the booking form allows at most 500.'], $this->flash('errors'));
        $this->assertSame(0, $this->templateCount());
    }

    public function testTheLimitFollowsTheMessageLengthSetting(): void
    {
        $this->setSetting('content.messageMaxLength', '100');

        $this->post('admin/message-templates', $this->form(['body' => str_repeat('x', 101)]));
        $this->assertSame(0, $this->templateCount());

        $this->post('admin/message-templates', $this->form(['body' => str_repeat('x', 100)]));
        $this->assertSame(1, $this->templateCount());
    }

    // ------------------------------------------------------------------ editing

    public function testATemplateCanBeEditedAndTheFormShowsItsCurrentValues(): void
    {
        $model = new MessageTemplateModel();
        $id    = $model->insert(['language' => 'English', 'title' => 'Old title', 'body' => 'Old body {recipient}']);

        $this->assertStringContainsString('Old title', $this->get('admin/message-templates/' . $id . '/edit')->getBody());

        $this->post('admin/message-templates/' . $id . '/update', $this->form(['title' => 'New title', 'body' => 'New body {sender}', 'language' => 'Luganda']))->assertRedirect();

        $this->assertSame('Template updated.', $this->flash('toastr_success'));
        $after = $model->find($id);
        $this->assertSame(['New title', 'New body {sender}', 'Luganda'], [$after['title'], $after['body'], $after['language']]);
    }

    public function testAnInvalidEditChangesNothing(): void
    {
        $model = new MessageTemplateModel();
        $id    = $model->insert(['language' => 'English', 'title' => 'Keep me', 'body' => 'Original {recipient}']);

        $this->post('admin/message-templates/' . $id . '/update', $this->form(['body' => 'Broken {nobody}']));

        $this->assertSame('Original {recipient}', $model->find($id)['body']);
    }

    public function testAnUnknownTemplateIsReported(): void
    {
        $this->post('admin/message-templates/999999/update', $this->form())->assertRedirect();
        $this->assertSame('Template not found.', $this->flash('toastr_error'));

        $this->get('admin/message-templates/999999/edit')->assertRedirect();
        $this->assertSame('Template not found.', $this->flash('toastr_error'));
    }

    public function testATemplateIsHiddenAndShownNotDeleted(): void
    {
        $model = new MessageTemplateModel();
        $id    = $model->insert(['language' => 'English', 'title' => 'T', 'body' => 'B']);

        $this->post('admin/message-templates/' . $id . '/deactivate');
        $this->assertSame('Template hidden from the booking form.', $this->flash('toastr_success'));
        $this->assertSame('0', (string) $model->find($id)['is_active']);

        $this->post('admin/message-templates/' . $id . '/activate');
        $this->assertSame('Template shown on the booking form.', $this->flash('toastr_success'));
        $this->assertSame('1', (string) $model->find($id)['is_active']);
    }

    public function testAHiddenTemplateStopsBeingOfferedToVisitors(): void
    {
        $category = $this->makeCategory(['slug' => 'birthdays']);
        $this->makeTier($category['id']);
        $id = (new MessageTemplateModel())->insert(['category_id' => $category['id'], 'language' => 'English', 'title' => 'Visible title', 'body' => 'B {recipient}']);

        $this->assertStringContainsString('Visible title', $this->get('booking/birthdays')->getBody());

        $this->post('admin/message-templates/' . $id . '/deactivate');

        $this->assertStringNotContainsString('Visible title', $this->get('booking/birthdays')->getBody());
    }

    public function testTheEditorOffersPlaceholderButtonsACountAndAPreview(): void
    {
        $body = $this->get('admin/message-templates/create')->getBody();

        $this->assertStringContainsString('data-token="recipient"', $body);
        $this->assertStringContainsString('data-token="sender"', $body);
        $this->assertStringContainsString('id="tplCount"', $body);
        $this->assertStringContainsString('id="tplPreview"', $body);
        $this->assertStringContainsString('data-max="' . config('CbsRadio')->messageMaxLength . '"', $body);
        $this->assertStringContainsString('assets/js/template-editor.js', $body);
    }

    public function testTheEditorsPlaceholderButtonsAreTheOnesTheServerAccepts(): void
    {
        $body = $this->get('admin/message-templates/create')->getBody();

        preg_match_all('/data-token="([a-z_]+)"/', $body, $m);

        $this->assertSame(MessageTemplateModel::TOKENS, $m[1]);
    }
}
