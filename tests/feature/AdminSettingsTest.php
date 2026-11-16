<?php

namespace Tests\Feature;

use App\Libraries\Settings\Registry;
use App\Models\SettingChangeModel;
use App\Models\SettingModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\FakeHttpGateway;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Admin > Settings through the screen itself: what each tab shows, what saving does, what is refused, what a
 * credential looks like (present or not, never its value), and the buttons that send a test message.
 *
 * @internal
 */
final class AdminSettingsTest extends AppFeatureTestCase
{
    use SetsEnvironment;

    private function visible(string $html): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function tabs(): array
    {
        return array_combine(array_keys(Registry::groups()), array_map(static fn (string $g) => [$g], array_keys(Registry::groups())));
    }

    // ------------------------------------------------------------------ showing

    #[DataProvider('tabs')]
    public function testEveryTabShowsEverySettingInIt(string $group): void
    {
        $visible = $this->visible($this->get('admin/settings/' . $group)->getBody());

        foreach (Registry::forGroup($group) as $definition) {
            $this->assertStringContainsString($definition['label'], $visible, "The {$group} tab is missing a setting.");
        }
    }

    public function testTheTabsAreListedOnEveryTab(): void
    {
        $visible = $this->visible($this->get('admin/settings/general')->getBody());

        foreach (Registry::groups() as $label) {
            $this->assertStringContainsString($label, $visible);
        }
    }

    public function testAnOverriddenSettingShowsItsNewValueInTheForm(): void
    {
        $this->setSetting('site.heroHeadline', 'Greetings that travel.');

        $this->assertStringContainsString('Greetings that travel.', $this->get('admin/settings/site')->getBody());
    }

    public function testWhatIsStoredIsEscapedWhereItIsShownAgain(): void
    {
        $this->setSetting('site.heroHeadline', '"><script>alert(1)</script>');
        $this->setSetting('site.announcement', '<img src=x onerror=alert(1)>');

        $body = $this->get('admin/settings/site')->getBody();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
    }

    // ------------------------------------------------------------------ saving

    public function testSavingAChangeTakesEffectOnTheSiteAndSaysHowManyWereSaved(): void
    {
        $result = $this->post('admin/settings/site', ['s' => ['site_heroHeadline' => 'Greetings that travel.']]);

        $result->assertRedirect();
        $this->assertStringEndsWith('/admin/settings/site', $result->getRedirectUrl());
        $this->assertSame('1 setting(s) saved.', $this->flash('toastr_success'));

        // A real next request is a fresh process that reads the settings again; within one test the framework keeps
        // the configuration it already built, so it is reloaded here to stand in for that.
        $this->reloadConfig();

        $this->assertStringContainsString('Greetings that travel.', $this->get('/')->getBody());
    }

    public function testSavingSeveralSettingsAtOnceCountsThemAll(): void
    {
        $this->post('admin/settings/site', ['s' => ['site_heroHeadline' => 'One', 'site_announcement' => 'Two']]);

        $this->assertSame('2 setting(s) saved.', $this->flash('toastr_success'));
    }

    public function testSavingWhatIsAlreadyInForceChangesNothing(): void
    {
        $this->post('admin/settings/site', ['s' => ['site_heroHeadline' => 'Same']]);
        $this->post('admin/settings/site', ['s' => ['site_heroHeadline' => 'Same']])->assertRedirect();

        $this->assertSame('Nothing was changed.', $this->flash('toastr_success'));
        $this->assertSame(1, (new SettingChangeModel())->countAllResults(), 'No second entry in the history.');
    }

    public function testTheChangeIsRecordedAgainstTheStaffMemberWhoMadeIt(): void
    {
        $this->post('admin/settings/site', ['s' => ['site_heroHeadline' => 'Greetings that travel.']]);

        $row = (new SettingChangeModel())->recent(1)[0];
        $this->assertSame(['site.heroHeadline', 'set', 'Greetings that travel.'], [$row['setting_key'], $row['action'], $row['new_value']]);
        $this->assertSame((string) \Tests\Support\Libraries\StaffUser::id(), (string) $row['changed_by']);
    }

    public function testTheHistoryOnTheScreenShowsWhatWasChanged(): void
    {
        $this->post('admin/settings/site', ['s' => ['site_heroHeadline' => 'Greetings that travel.']]);

        $visible = $this->visible($this->get('admin/settings/site')->getBody());

        $this->assertStringContainsString('Homepage headline', $visible);
    }

    public function testResettingBringsTheCodedValueBack(): void
    {
        $this->setSetting('site.heroHeadline', 'Temporary');

        $this->post('admin/settings/site', ['reset' => ['site_heroHeadline' => '1']])->assertRedirect();

        $this->assertSame('1 setting(s) saved.', $this->flash('toastr_success'));
        $this->assertArrayNotHasKey('site.heroHeadline', (new SettingModel())->allKeyed());

        $this->reloadConfig();

        $this->assertStringContainsString('Say it on air.', $this->get('/')->getBody());
    }

    public function testASettingSavedOnOneTabDoesNotDisturbAnother(): void
    {
        $this->post('admin/settings/site', ['s' => ['site_announcement' => 'Closed Friday']]);
        $this->post('admin/settings/general', ['s' => ['general_contactPhone' => '+256 772 000 111']]);

        $this->assertSame(
            ['site.announcement' => 'Closed Friday', 'general.contactPhone' => '+256 772 000 111'],
            (new SettingModel())->allKeyed()
        );
    }

    // ------------------------------------------------------------------ refusing

    public function testABadValueIsRefusedWithAReasonAndNothingIsSaved(): void
    {
        $this->post('admin/settings/site', ['s' => ['site_heroHeadline' => 'A fine headline', 'site_liveStreamUrl' => 'javascript:alert(1)']])->assertRedirect();

        $this->assertSame(['Live stream address: must be a web address starting with http:// or https://.'], $this->flash('errors'));
        $this->assertSame([], (new SettingModel())->allKeyed(), 'The good field is not saved while the bad one is refused.');
    }

    public function testTheLongestVoiceNoteMustBeLongerThanTheShortest(): void
    {
        $this->post('admin/settings/bookings', ['s' => ['uploads_voiceNoteMinSeconds' => '40', 'uploads_voiceNoteMaxSeconds' => '30']]);

        $this->assertSame(['Longest voice note: must be longer than the shortest.'], $this->flash('errors'));
    }

    public function testOnlinePaymentsCannotBeSwitchedOnWithoutCredentials(): void
    {
        $this->setEnv('momo.apiKey', null);
        $this->setEnv('momo.apiSecret', null);

        $this->post('admin/settings/payments', ['s' => ['payments_mode' => 'mobile_money', 'payments_provider' => 'mtn']]);

        $this->assertSame(['Payment mode: online payments need momo.apiKey and momo.apiSecret in .env first.'], $this->flash('errors'));
        $this->assertSame([], (new SettingModel())->allKeyed());
    }

    public function testAnUnknownTabIsReportedWhetherViewedOrSaved(): void
    {
        $this->get('admin/settings/no-such-tab')->assertRedirect();
        $this->assertSame('Unknown settings section.', $this->flash('toastr_error'));

        $this->post('admin/settings/no-such-tab', ['s' => ['x' => 'y']])->assertRedirect();
        $this->assertSame('Unknown settings section.', $this->flash('toastr_error'));
    }

    // ------------------------------------------------------------------ credentials: present or not, never shown

    public function testACredentialIsShownAsSetOrNotSetAndNeverRevealed(): void
    {
        $this->setEnv('momo.apiKey', 'super-secret-key-value');
        $this->setEnv('momo.apiSecret', null);

        $body = $this->get('admin/settings/payments')->getBody();

        $this->assertStringContainsString('Credentials in .env', $this->visible($body));
        $this->assertStringNotContainsString('super-secret-key-value', $body);
        $this->assertStringContainsString('Set', $this->visible($body));
        $this->assertStringContainsString('Not set', $this->visible($body));
    }

    // ------------------------------------------------------------------ the test buttons

    public function testAnUnknownTestChannelIsRefused(): void
    {
        $this->post('admin/settings/test/pigeon', ['recipient' => 'anyone'])->assertRedirect();

        $this->assertSame('Unknown channel.', $this->flash('toastr_error'));
    }

    public function testAMalformedRecipientIsRefusedBeforeAnythingIsSent(): void
    {
        $http = FakeHttpGateway::install();

        $this->post('admin/settings/test/email', ['recipient' => 'not-an-email']);
        $this->assertSame('Enter a valid email address to send the test to.', $this->flash('toastr_error'));

        foreach (['', 'call me', '12', '(0772) 123456'] as $phone) {
            $this->post('admin/settings/test/sms', ['recipient' => $phone]);
            $this->assertSame('Enter a valid phone number to send the test to.', $this->flash('toastr_error'), "'{$phone}'");
        }

        $this->assertSame([], $http->calls);
    }

    public function testATestTextIsSentThroughTheGatewayAndReported(): void
    {
        $this->setEnv('sms.apiKey', 'key');
        $this->setEnv('sms.username', 'cbsradio');
        $http = FakeHttpGateway::install();

        $this->post('admin/settings/test/sms', ['recipient' => '0772123456'])->assertRedirect();

        $this->assertSame('Test SMS sent. Check the recipient, and the notifications log.', $this->flash('toastr_success'));
        $this->assertSame('0772123456', $http->lastPayload()['to']);
    }

    public function testATestTextThatFailsPointsAtTheCredentials(): void
    {
        $this->setEnv('sms.apiKey', 'key');
        FakeHttpGateway::install(500);

        $this->post('admin/settings/test/sms', ['recipient' => '0772123456']);

        $this->assertSame('The test SMS failed. Check the credentials in .env, then the notifications log for the attempt.', $this->flash('toastr_error'));
    }

    public function testATestEmailIsSentAndAFailingMailServerIsReported(): void
    {
        $this->post('admin/settings/test/email', ['recipient' => 'grace@example.test']);
        $this->assertSame('Test EMAIL sent. Check the recipient, and the notifications log.', $this->flash('toastr_success'));
        $this->assertSame('CBS Radio test email', service('email')->archive['subject']);

        service('email')->returnValue = false;
        $this->post('admin/settings/test/email', ['recipient' => 'grace@example.test']);
        $this->assertSame('The test EMAIL failed. Check the credentials in .env, then the notifications log for the attempt.', $this->flash('toastr_error'));
    }

    public function testTheTestWorksEvenWithTheChannelSwitchedOff(): void
    {
        $this->setEnv('sms.apiKey', 'key');
        config('Notifications')->channelsEnabled = ['sms' => false, 'email' => false];
        $http = FakeHttpGateway::install();

        $this->post('admin/settings/test/sms', ['recipient' => '0772123456']);

        $this->assertCount(1, $http->calls, 'The point of the button is to try the gateway before switching the channel on.');
    }
}
