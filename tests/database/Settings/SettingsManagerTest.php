<?php

namespace Tests\Database\Settings;

use App\Libraries\Settings\Overrides;
use App\Libraries\Settings\SettingsManager;
use App\Models\SettingChangeModel;
use App\Models\SettingModel;
use Config\CbsRadio;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\StaffUser;
use Tests\Support\TestCase\AppTestCase;

/**
 * Admin > Settings behind the screen: what is saved, what is refused, what is recorded, and how a saved value
 * reaches the rest of the application. (Each individual rule is tested without a database in
 * tests/unit/Settings/SettingsValidationTest.php.)
 *
 * @internal
 */
final class SettingsManagerTest extends AppTestCase
{
    use SetsEnvironment;

    private SettingsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new SettingsManager();
    }

    /**
     * The row the screen shows for one setting.
     *
     * @return array<string, mixed>
     */
    private function item(string $group, string $key): array
    {
        foreach ($this->manager->forGroup($group) as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        $this->fail("No setting {$key} in the {$group} group.");
    }

    private function overrideCount(): int
    {
        return $this->db->table('settings')->countAllResults();
    }

    private function auditCount(): int
    {
        return $this->db->table('setting_changes')->countAllResults();
    }

    // ------------------------------------------------------------------ what the screen shows

    public function testEverySettingInATabIsListedWithItsCodedValueAndNoOverride(): void
    {
        $items = $this->manager->forGroup('site');

        $this->assertCount(4, $items);

        foreach ($items as $item) {
            $this->assertFalse($item['overridden']);
            $this->assertSame($item['base'], $item['current'], 'With no override, what is in force is what the code ships with.');
            $this->assertSame(str_replace('.', '_', $item['key']), $item['field']);
        }
    }

    public function testAnOverrideShowsAsInForceAndAsOverridden(): void
    {
        $this->setSetting('site.heroHeadline', 'Greetings that travel.');

        $item = $this->item('site', 'site.heroHeadline');

        $this->assertTrue($item['overridden']);
        $this->assertSame('Greetings that travel.', $item['current']);
        $this->assertNotSame('Greetings that travel.', $item['base'], 'The coded value is still known, for "reset to default".');
    }

    public function testAnUnknownTabHasNothingInIt(): void
    {
        $this->assertSame([], $this->manager->forGroup('no-such-tab'));
    }

    // ------------------------------------------------------------------ saving

    public function testSavingAChangeStoresItAndTellsHowManyChanged(): void
    {
        $result = $this->manager->save('site', ['site_heroHeadline' => 'Greetings that travel.', 'site_announcement' => 'Closed Friday'], [], StaffUser::id());

        $this->assertSame(['success' => true, 'errors' => [], 'changed' => 2], $result);
        $this->assertSame(
            ['site.heroHeadline' => 'Greetings that travel.', 'site.announcement' => 'Closed Friday'],
            (new SettingModel())->allKeyed()
        );
    }

    public function testASavedValueTakesEffectImmediatelyEverywhere(): void
    {
        $this->manager->save('site', ['site_heroHeadline' => 'Greetings that travel.'], [], null);

        $this->assertSame('Greetings that travel.', SettingsManager::overrides()['site.heroHeadline'], 'The cache was cleared.');

        $this->resetFactories();

        $this->assertSame('Greetings that travel.', config('CbsRadio')->heroHeadline);
    }

    public function testEveryChangeIsAuditedWithBeforeAfterAndWho(): void
    {
        $this->manager->save('site', ['site_announcement' => 'First'], [], StaffUser::id());
        $this->manager->save('site', ['site_announcement' => 'Second'], [], StaffUser::id());

        $rows = (new SettingChangeModel())->recent(5);

        $this->assertCount(2, $rows);
        $this->assertSame(['site.announcement', 'set', 'First', 'Second'], [$rows[0]['setting_key'], $rows[0]['action'], $rows[0]['old_value'], $rows[0]['new_value']]);
        $this->assertSame(['site.announcement', 'set', '', 'First'], [$rows[1]['setting_key'], $rows[1]['action'], (string) $rows[1]['old_value'], $rows[1]['new_value']]);
        $this->assertSame((string) StaffUser::id(), (string) $rows[0]['changed_by']);
    }

    public function testSubmittingTheValueAlreadyInForceChangesNothing(): void
    {
        // Otherwise the first time someone saved a tab it would quietly pin every default as an "override",
        // and a later change to a default in the code would stop reaching the site.
        $current = $this->item('site', 'site.heroHeadline')['current'];

        $result = $this->manager->save('site', ['site_heroHeadline' => $current], [], null);

        $this->assertSame(0, $result['changed']);
        $this->assertSame(0, $this->overrideCount());
        $this->assertSame(0, $this->auditCount());
    }

    public function testAFieldNotOnTheSubmittedFormIsLeftAlone(): void
    {
        $this->manager->save('site', ['site_announcement' => 'Hello'], [], null);

        $this->manager->save('site', ['site_heroHeadline' => 'Changed'], [], null);

        $this->assertSame('Hello', (new SettingModel())->allKeyed()['site.announcement'], 'Saving one field must not disturb another.');
    }

    public function testInputIsTidiedBeforeItIsStored(): void
    {
        $this->manager->save('general', ['general_defaultCurrency' => ' usd '], [], null);
        $this->manager->save('bookings', ['moderation_flagTerms' => "scam, lottery\nscam\n  free money \n"], [], null);

        $stored = (new SettingModel())->allKeyed();

        $this->assertSame('USD', $stored['general.defaultCurrency']);
        $this->assertSame("scam\nlottery\nfree money", $stored['moderation.flagTerms']);
    }

    public function testASwitchCanBeTurnedTheOtherWay(): void
    {
        $base = $this->item('notifications', 'notifications.emailEnabled')['base'];

        $this->manager->save('notifications', ['notifications_emailEnabled' => $base ? '0' : '1'], [], null);

        $this->reloadConfig();

        $this->assertSame(! $base, config('Notifications')->channelsEnabled['email']);
    }

    public function testASettingInsideAnArrayReachesTheRightPlace(): void
    {
        $this->manager->save('templates', ['notifications_template_moderation_approved_sms' => 'Hi {sender_name}, ref {reference}: approved.'], [], null);

        $this->reloadConfig();

        $templates = config('Notifications')->templates;

        $this->assertSame('Hi {sender_name}, ref {reference}: approved.', $templates['moderation_approved']['sms']);
        $this->assertNotSame('Hi {sender_name}, ref {reference}: approved.', $templates['aired']['sms'], 'The other templates are untouched.');
    }

    public function testSavingATabThatDoesNotExistDoesNothing(): void
    {
        $this->assertSame(['success' => true, 'errors' => [], 'changed' => 0], $this->manager->save('no-such-tab', ['x' => 'y'], [], null));
    }

    // ------------------------------------------------------------------ resetting

    public function testResettingRemovesTheOverrideAndAuditsIt(): void
    {
        $this->manager->save('site', ['site_heroHeadline' => 'Greetings that travel.'], [], StaffUser::id());
        $base = $this->item('site', 'site.heroHeadline')['base'];

        $result = $this->manager->save('site', [], ['site_heroHeadline' => '1'], StaffUser::id());

        $this->assertSame(1, $result['changed']);
        $this->assertSame(0, $this->overrideCount());

        $latest = (new SettingChangeModel())->recent(1)[0];
        $this->assertSame(['reset', 'Greetings that travel.', $base], [$latest['action'], $latest['old_value'], $latest['new_value']]);
    }

    public function testResettingSomethingNeverChangedIsIgnored(): void
    {
        $result = $this->manager->save('site', [], ['site_heroHeadline' => '1'], null);

        $this->assertSame(0, $result['changed']);
        $this->assertSame(0, $this->auditCount());
    }

    public function testAResetBeatsAValueSubmittedForTheSameField(): void
    {
        $this->manager->save('site', ['site_announcement' => 'Old'], [], null);

        $this->manager->save('site', ['site_announcement' => 'Typed but also reset'], ['site_announcement' => '1'], null);

        $this->assertArrayNotHasKey('site.announcement', (new SettingModel())->allKeyed());
    }

    public function testAfterAResetTheCodedValueIsBackInForce(): void
    {
        $base = $this->item('site', 'site.heroHeadline')['base'];

        $this->manager->save('site', ['site_heroHeadline' => 'Temporary'], [], null);
        $this->manager->save('site', [], ['site_heroHeadline' => '1'], null);
        $this->resetFactories();

        $this->assertSame($base, config('CbsRadio')->heroHeadline);
    }

    // ------------------------------------------------------------------ refusing

    public function testABadValueIsRefusedWithTheSettingsNameInTheMessage(): void
    {
        $result = $this->manager->save('site', ['site_liveStreamUrl' => 'javascript:alert(1)'], [], null);

        $this->assertFalse($result['success']);
        $this->assertSame(['Live stream address: must be a web address starting with http:// or https://.'], $result['errors']);
        $this->assertSame(0, $result['changed']);
    }

    public function testNothingIsSavedIfAnythingOnTheFormIsRefused(): void
    {
        $result = $this->manager->save('site', [
            'site_heroHeadline'  => 'A perfectly good headline',
            'site_liveStreamUrl' => 'not a url',
        ], [], StaffUser::id());

        $this->assertFalse($result['success']);
        $this->assertSame(0, $this->overrideCount(), 'The good field must not be saved while the bad one is refused.');
        $this->assertSame(0, $this->auditCount());
    }

    public function testEveryProblemIsReportedAtOnce(): void
    {
        $result = $this->manager->save('site', ['site_heroHeadline' => '', 'site_liveStreamUrl' => 'nope', 'site_announcement' => str_repeat('x', 161)], [], null);

        $this->assertCount(3, $result['errors']);
    }

    public function testARequiredSettingCannotBeEmptied(): void
    {
        $result = $this->manager->save('site', ['site_heroHeadline' => ''], [], null);

        $this->assertSame(['Homepage headline: cannot be empty. Use "Reset to default" instead.'], $result['errors']);
    }

    public function testAnOptionalSettingCanBeEmptiedToSwitchItOff(): void
    {
        $this->manager->save('site', ['site_announcement' => 'Closed Friday'], [], null);

        $result = $this->manager->save('site', ['site_announcement' => ''], [], null);

        $this->assertTrue($result['success']);
        $this->assertSame(['site.announcement' => ''], (new SettingModel())->allKeyed(), 'Emptied, and still overriding the default.');
    }

    // ------------------------------------------------------------------ settings that depend on each other

    public function testTheLongestVoiceNoteMustBeLongerThanTheShortest(): void
    {
        $result = $this->manager->save('bookings', ['uploads_voiceNoteMinSeconds' => '40', 'uploads_voiceNoteMaxSeconds' => '30'], [], null);

        $this->assertSame(['Longest voice note: must be longer than the shortest.'], $result['errors']);
        $this->assertSame(0, $this->overrideCount());
    }

    public function testTheCheckAlsoAppliesWhenOnlyOneOfTheTwoIsChanged(): void
    {
        // The maximum is left at its default of 30; raising only the minimum above it must still be refused.
        $result = $this->manager->save('bookings', ['uploads_voiceNoteMinSeconds' => '35'], [], null);

        $this->assertFalse($result['success']);

        $this->assertTrue($this->manager->save('bookings', ['uploads_voiceNoteMinSeconds' => '10', 'uploads_voiceNoteMaxSeconds' => '60'], [], null)['success']);
    }

    public function testAMinimumEqualToTheMaximumIsRefused(): void
    {
        $this->assertFalse($this->manager->save('bookings', ['uploads_voiceNoteMinSeconds' => '20', 'uploads_voiceNoteMaxSeconds' => '20'], [], null)['success']);
    }

    public function testOnlinePaymentsNeedAProviderAndCredentialsBeforeTheyCanBeSwitchedOn(): void
    {
        $this->setEnv('momo.apiKey', null);
        $this->setEnv('momo.apiSecret', null);

        $result = $this->manager->save('payments', ['payments_mode' => 'mobile_money'], [], null);

        $this->assertFalse($result['success']);
        $this->assertContains('Mobile Money provider: choose one before switching on online payments.', $result['errors']);
        $this->assertContains('Payment mode: online payments need momo.apiKey and momo.apiSecret in .env first.', $result['errors']);
    }

    public function testChoosingAProviderIsNotEnoughWithoutCredentials(): void
    {
        $this->setEnv('momo.apiKey', 'key');
        $this->setEnv('momo.apiSecret', null);

        $result = $this->manager->save('payments', ['payments_mode' => 'mobile_money', 'payments_provider' => 'mtn'], [], null);

        $this->assertSame(['Payment mode: online payments need momo.apiKey and momo.apiSecret in .env first.'], $result['errors']);
    }

    public function testOnlinePaymentsCanBeSwitchedOnOnceEverythingIsInPlace(): void
    {
        $this->setEnv('momo.apiKey', 'key');
        $this->setEnv('momo.apiSecret', 'secret');

        $result = $this->manager->save('payments', ['payments_mode' => 'mobile_money', 'payments_provider' => 'mtn'], [], null);

        $this->assertTrue($result['success'], implode(' ', $result['errors']));

        $this->reloadConfig();

        $this->assertSame('mobile_money', config('Payments')->mode);
        $this->assertSame('mtn', config('Payments')->provider);
    }

    public function testGoingBackToManualPaymentsIsAlwaysAllowed(): void
    {
        $this->setEnv('momo.apiKey', 'key');
        $this->setEnv('momo.apiSecret', 'secret');
        $this->manager->save('payments', ['payments_mode' => 'mobile_money', 'payments_provider' => 'mtn'], [], null);

        $this->setEnv('momo.apiKey', null);
        $this->setEnv('momo.apiSecret', null);

        $this->assertTrue($this->manager->save('payments', ['payments_mode' => 'manual'], [], null)['success'], 'Falling back must never be blocked by missing credentials.');
    }

    public function testChangingAnUnrelatedPaymentsFieldIsNotBlockedByTheModeAlreadyInForce(): void
    {
        $this->setEnv('momo.apiKey', 'key');
        $this->setEnv('momo.apiSecret', 'secret');
        $this->manager->save('payments', ['payments_mode' => 'mobile_money', 'payments_provider' => 'mtn'], [], null);

        $this->setEnv('momo.apiKey', null);

        $this->assertTrue($this->manager->save('payments', ['payments_merchantCode' => '654321'], [], null)['success']);
    }

    // ------------------------------------------------------------------ the change history

    public function testRecentChangesAreLabelledForTheScreen(): void
    {
        $this->manager->save('site', ['site_heroHeadline' => 'One'], [], StaffUser::id());
        $this->manager->save('bookings', ['content_messageMaxLength' => '300'], [], StaffUser::id());

        $changes = $this->manager->recentChanges(10);

        $this->assertSame(['Maximum message length (characters)', 'Homepage headline'], array_column($changes, 'label'));
        $this->assertSame(['int', 'string'], array_column($changes, 'type'));
        $this->assertCount(1, $this->manager->recentChanges(1));
    }

    // ------------------------------------------------------------------ credentials: reported, never shown

    public function testCredentialsAreReportedAsConfiguredOrNotAndNeverRevealed(): void
    {
        $this->setEnv('momo.apiKey', 'super-secret-value');
        $this->setEnv('momo.apiSecret', null);

        $status = array_column($this->manager->secretStatus('payments'), 'configured', 'env');

        $this->assertTrue($status['momo.apiKey']);
        $this->assertFalse($status['momo.apiSecret']);
        $this->assertStringNotContainsString('super-secret-value', json_encode($this->manager->secretStatus('payments')));
    }

    public function testAnUnknownTabHasNoCredentials(): void
    {
        $this->assertSame([], $this->manager->secretStatus('site'));
    }

    public function testBlankCredentialsCountAsMissing(): void
    {
        $this->setEnv('sms.apiKey', '   ');

        $this->assertFalse($this->manager->envConfigured('sms.apiKey'));
        $this->assertFalse($this->manager->envConfigured('never.set.at.all'));
    }

    // ------------------------------------------------------------------ the dashboard's configuration row

    public function testHealthSaysManualPaymentsNeedStaffToConfirmEachOne(): void
    {
        config('Payments')->mode = 'manual';

        $payments = $this->manager->health()[0];

        $this->assertSame(['Payments', 'info', 'Manual — staff confirm each payment'], [$payments['label'], $payments['status'], $payments['detail']]);
    }

    public function testHealthWarnsWhenOnlinePaymentsAreOnButNotSetUp(): void
    {
        $this->setEnv('momo.apiKey', null);
        $this->setEnv('momo.apiSecret', null);
        config('Payments')->mode     = 'mobile_money';
        config('Payments')->provider = 'mtn';

        $this->assertSame('warn', $this->manager->health()[0]['status']);

        $this->setEnv('momo.apiKey', 'k');
        $this->setEnv('momo.apiSecret', 's');

        $ok = $this->manager->health()[0];
        $this->assertSame(['ok', 'Mobile Money (online)'], [$ok['status'], $ok['detail']]);

        config('Payments')->provider = '';
        $this->assertSame('warn', $this->manager->health()[0]['status'], 'No provider chosen is not ready, whatever the credentials.');
    }

    public function testHealthReportsTheTextMessageGateway(): void
    {
        config('Notifications')->channelsEnabled['sms'] = false;
        $this->assertSame(['SMS', 'info', 'Off'], array_values(array_intersect_key($this->manager->health()[1], array_flip(['label', 'status', 'detail']))));

        config('Notifications')->channelsEnabled['sms'] = true;
        $this->setEnv('sms.apiKey', null);
        $this->assertSame(['warn', 'On, but no gateway key in .env'], [$this->manager->health()[1]['status'], $this->manager->health()[1]['detail']]);

        $this->setEnv('sms.apiKey', 'abc');
        $this->assertSame(['ok', 'On'], [$this->manager->health()[1]['status'], $this->manager->health()[1]['detail']]);
    }

    public function testHealthReportsEmail(): void
    {
        config('Notifications')->channelsEnabled['email'] = true;
        $this->setEnv('email.SMTPHost', null);
        $this->assertSame(['warn', 'On, but no SMTP host in .env'], [$this->manager->health()[2]['status'], $this->manager->health()[2]['detail']]);

        $this->setEnv('email.SMTPHost', 'smtp.example.test');
        $this->assertSame('ok', $this->manager->health()[2]['status']);

        config('Notifications')->channelsEnabled['email'] = false;
        $this->assertSame('info', $this->manager->health()[2]['status']);
    }

    // ------------------------------------------------------------------ the cache

    public function testOverridesAreCachedUntilFlushed(): void
    {
        $this->assertSame([], SettingsManager::overrides());

        (new SettingModel())->setOverride('site.announcement', 'Hello', null);

        $this->assertSame([], SettingsManager::overrides(), 'Still the cached answer.');

        SettingsManager::flush();

        $this->assertSame(['site.announcement' => 'Hello'], SettingsManager::overrides());
    }

    // ------------------------------------------------------------------ reaching the configuration

    public function testSuspendingOverridesGivesTheCodedDefaults(): void
    {
        $this->setSetting('site.heroHeadline', 'Greetings that travel.');

        Overrides::$suspended = true;

        try {
            $this->assertNotSame('Greetings that travel.', (new CbsRadio())->heroHeadline);
        } finally {
            Overrides::$suspended = false;
        }

        $this->assertSame('Greetings that travel.', (new CbsRadio())->heroHeadline, 'And they apply again as soon as it is switched back.');
    }

    public function testASettingStoredAsTextArrivesAsTheRightType(): void
    {
        $this->setSetting('content.messageMaxLength', '321');
        $this->setSetting('moderation.flagTerms', "scam\nlottery");

        $this->assertSame(321, config('CbsRadio')->messageMaxLength);
        $this->assertSame(['scam', 'lottery'], config('CbsRadio')->moderationFlagTerms);
    }
}
