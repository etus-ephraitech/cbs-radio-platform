<?php

namespace Tests\Database\Models;

use App\Models\SettingChangeModel;
use App\Models\SettingModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Tests\Support\Libraries\StaffUser;
use Tests\Support\TestCase\AppTestCase;

/**
 * The two tables behind Admin > Settings: the overrides themselves, and the log of who changed what.
 *
 * @internal
 */
final class SettingsModelsTest extends AppTestCase
{
    public function testOverridesComeBackAsKeyedStrings(): void
    {
        $model = new SettingModel();

        $model->setOverride('site.heroHeadline', 'Greetings that travel.', null);
        $model->setOverride('uploads.voiceNoteMaxSeconds', '45', null);

        $this->assertSame(
            ['site.heroHeadline' => 'Greetings that travel.', 'uploads.voiceNoteMaxSeconds' => '45'],
            $model->allKeyed(),
            'Everything is stored, and returned, as text.'
        );
    }

    public function testNothingIsOverriddenToStartWith(): void
    {
        $this->assertSame([], (new SettingModel())->allKeyed());
    }

    public function testSettingAnOverrideAgainReplacesItInsteadOfAddingARow(): void
    {
        $model = new SettingModel();

        $model->setOverride('site.announcement', 'First', null);
        $model->setOverride('site.announcement', 'Second', null);

        $this->assertSame(['site.announcement' => 'Second'], $model->allKeyed());
        $this->assertSame(1, $this->db->table('settings')->countAllResults());
    }

    public function testWhoMadeTheChangeIsRecorded(): void
    {
        $model = new SettingModel();

        $model->setOverride('site.announcement', 'Hello', StaffUser::id());

        $row = $this->db->table('settings')->where('setting_key', 'site.announcement')->get()->getRowArray();
        $this->assertSame((string) StaffUser::id(), (string) $row['updated_by']);
    }

    public function testClearingAnOverrideRemovesItAndLeavesTheOthers(): void
    {
        $model = new SettingModel();

        $model->setOverride('a.one', '1', null);
        $model->setOverride('a.two', '2', null);

        $model->clearOverride('a.one');

        $this->assertSame(['a.two' => '2'], $model->allKeyed());
    }

    public function testClearingSomethingThatIsNotOverriddenIsHarmless(): void
    {
        (new SettingModel())->clearOverride('never.set');

        $this->assertSame([], (new SettingModel())->allKeyed());
    }

    public function testAnEmptyOverrideIsKeptAsAnEmptyValue(): void
    {
        // "Empty the announcement" is a real choice and is not the same as "never changed it".
        $model = new SettingModel();

        $model->setOverride('site.announcement', '', null);

        $this->assertSame(['site.announcement' => ''], $model->allKeyed());
    }

    public function testTheDatabaseRefusesTwoRowsForOneSetting(): void
    {
        $this->db->table('settings')->insert(['setting_key' => 'dup.key', 'setting_value' => '1']);

        $this->expectException(DatabaseException::class);

        $this->db->table('settings')->insert(['setting_key' => 'dup.key', 'setting_value' => '2']);
    }

    // ------------------------------------------------------------------ the change log

    public function testEveryChangeIsRecordedWithBeforeAndAfter(): void
    {
        $changes = new SettingChangeModel();

        $changes->record('site.announcement', 'set', null, 'Mother\'s Day', StaffUser::id());

        $row = $changes->recent(5)[0];
        $this->assertSame('site.announcement', $row['setting_key']);
        $this->assertSame('set', $row['action']);
        $this->assertNull($row['old_value']);
        $this->assertSame("Mother's Day", $row['new_value']);
        $this->assertSame((string) StaffUser::id(), (string) $row['changed_by']);
    }

    public function testTheRecentChangesAreNewestFirstAndLimited(): void
    {
        $changes = new SettingChangeModel();

        foreach (['one', 'two', 'three', 'four'] as $value) {
            $changes->record('a.key', 'set', null, $value, null);
        }

        $this->assertSame(['four', 'three'], array_column($changes->recent(2), 'new_value'));
        $this->assertCount(4, $changes->recent());
    }
}
