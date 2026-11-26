<?php

namespace Tests\Feature;

use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\AudioFixtures;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * The voice recorder belongs to packages that include a voice note: the form says which packages have one, offers
 * the recorder only for those (the page's script does the showing and hiding), and the server refuses a recording
 * sent with a package that does not include one. Admin > Packages is where staff switch it on.
 *
 * @internal
 */
final class PackageVoiceNoteTest extends AppFeatureTestCase
{
    use SetsEnvironment;

    private array $category;
    private array $withVoice;
    private array $textOnly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->silenceNotifications();
        config('CbsRadio')->voiceNoteVerifyWithFfprobe = false;

        $this->category  = $this->makeCategory(['name' => 'Birthdays', 'slug' => 'birthdays']);
        $this->withVoice = $this->makeTier($this->category['id'], ['package_level' => 'Premium Greeting', 'features' => 'On-air readout and your recording', 'includes_voice_note' => 1]);
        $this->textOnly  = $this->makeTier($this->category['id'], ['package_level' => 'Plain Greeting', 'features' => 'On-air readout', 'includes_voice_note' => 0]);
    }

    private function form(array $tier, array $overrides = []): array
    {
        return $overrides + [
            'category_id'    => $this->category['id'],
            'rate_tier_id'   => $tier['id'],
            'sender_name'    => 'Grace Nakato',
            'sender_phone'   => '0772123456',
            'recipient_name' => 'Mama Ruth',
            'message_text'   => 'Happy birthday Mama Ruth!',
        ];
    }

    private function radioFor(string $html, array $tier): string
    {
        preg_match('/<input[^>]*name="rate_tier_id"[^>]*value="' . $tier['id'] . '"[^>]*>/s', $html, $m);

        return $m[0] ?? '';
    }

    public function testEachPackageOnTheFormSaysWhetherItIncludesAVoiceNote(): void
    {
        $this->setEnv('ui_version', 'v1');

        $body = $this->get('booking/birthdays')->getBody();

        $this->assertStringContainsString('data-voice="1"', $this->radioFor($body, $this->withVoice));
        $this->assertStringContainsString('data-voice="0"', $this->radioFor($body, $this->textOnly));
    }

    public function testTheVoiceNoteTagComesFromTheSettingNotFromTheWordsInTheName(): void
    {
        $this->setEnv('ui_version', 'v1');
        $this->makeTier($this->category['id'], ['package_level' => 'Voice of the People', 'features' => 'Read on air', 'includes_voice_note' => 0]);

        $body = $this->get('booking/birthdays')->getBody();

        $this->assertSame(1, substr_count($body, 'ck-tag"><svg'), 'Only the package that includes a voice note carries the tag.');
    }

    public function testTheFormHasASpotForTheRecorderAndAnExplanationForPackagesWithout(): void
    {
        $this->setEnv('ui_version', 'v1');

        $body = $this->get('booking/birthdays')->getBody();

        $this->assertStringContainsString('id="voice"', $body);
        $this->assertStringContainsString('id="voiceNotIncluded"', $body);
        $this->assertStringContainsString('Your package is read out as text', $body);
    }

    public function testARecordingIsAcceptedWithAPackageThatIncludesOne(): void
    {
        AudioFixtures::attachToRequest('voice_note', 'chrome-recording.webm', 'voice-note.webm', 'audio/webm');

        $this->post('booking', $this->form($this->withVoice, ['voice_note_duration_seconds' => '6']))->assertRedirect();

        $this->assertSame(1, $this->db->table('bookings')->countAllResults());
        $this->assertNotEmpty($this->db->table('bookings')->get()->getRowArray()['voice_note_path']);
    }

    public function testARecordingIsRefusedWithAPackageThatDoesNotIncludeOne(): void
    {
        AudioFixtures::attachToRequest('voice_note', 'chrome-recording.webm', 'voice-note.webm', 'audio/webm');

        $this->post('booking', $this->form($this->textOnly, ['voice_note_duration_seconds' => '6']))->assertRedirect();

        $this->assertStringContainsString('does not include a voice note', $this->flash('toastr_error'));
        $this->assertStringContainsString('Plain Greeting', $this->flash('toastr_error'));
        $this->assertSame(0, $this->db->table('bookings')->countAllResults());
        $this->assertSame([], glob(WRITEPATH . 'voice-notes-test/*') ?: []);
    }

    public function testAPackageWithoutAVoiceNoteBooksNormallyWithoutOne(): void
    {
        $this->post('booking', $this->form($this->textOnly))->assertRedirect();

        $this->assertSame(1, $this->db->table('bookings')->countAllResults());
        $this->assertNull($this->db->table('bookings')->get()->getRowArray()['voice_note_path']);
    }

    public function testStaffCanSwitchTheVoiceNoteOnForAPackage(): void
    {
        $this->post('admin/rate-tiers/' . $this->textOnly['id'] . '/update', [
            'category_id'         => $this->category['id'],
            'package_level'       => 'Plain Greeting',
            'price'               => '15000',
            'includes_on_air'     => '1',
            'includes_voice_note' => '1',
            'is_active'           => '1',
        ])->assertRedirect();

        $this->assertSame('1', (string) $this->db->table('rate_tiers')->where('id', $this->textOnly['id'])->get()->getRowArray()['includes_voice_note']);
    }

    public function testLeavingTheVoiceNoteUntickedSwitchesItOff(): void
    {
        $this->post('admin/rate-tiers/' . $this->withVoice['id'] . '/update', [
            'category_id'   => $this->category['id'],
            'package_level' => 'Premium Greeting',
            'price'         => '15000',
            'is_active'     => '1',
        ])->assertRedirect();

        $this->assertSame('0', (string) $this->db->table('rate_tiers')->where('id', $this->withVoice['id'])->get()->getRowArray()['includes_voice_note']);
    }
}
