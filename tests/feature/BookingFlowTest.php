<?php

namespace Tests\Feature;

use App\Models\BookingModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Libraries\AudioFixtures;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * What a visitor does to book a greeting: choose, fill in the form, attach a voice note, and arrive at payment.
 * Whole requests, through the real routes, controllers, services and views.
 *
 * @internal
 */
final class BookingFlowTest extends AppFeatureTestCase
{
    /** @var array<string, mixed> */
    private array $category;

    /** @var array<string, mixed> */
    private array $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->silenceNotifications();

        // A voice note is checked from the file itself when ffprobe exists. These tests are about the flow, so they
        // rely on the length the browser reports, which works the same everywhere. VoiceNoteUploaderTest covers
        // the measured length.
        config('CbsRadio')->voiceNoteVerifyWithFfprobe = false;

        $this->category = $this->makeCategory(['name' => 'Birthdays', 'slug' => 'birthdays', 'description' => 'Birthday wishes for every age']);
        $this->tier     = $this->makeTier($this->category['id'], ['package_level' => 'Voice Note + Text', 'features' => 'Readout + 15s voice note', 'price' => 30000]);
    }

    /**
     * What the booking form posts.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'category_id'    => $this->category['id'],
            'rate_tier_id'   => $this->tier['id'],
            'sender_name'    => 'Grace Nakato',
            'sender_phone'   => '0772123456',
            'sender_email'   => 'grace@example.test',
            'recipient_name' => 'Mama Ruth',
            'message_text'   => 'Happy birthday Mama Ruth!',
        ];
    }

    /**
     * The example messages the form hands to its script, read the way a browser reads the attribute: whichever
     * quote delimits it, with entities decoded.
     *
     * @return array<int, array<string, mixed>>|null null when the attribute is missing or is not valid JSON
     */
    private function templatesOffered(string $html): ?array
    {
        if (! preg_match('/data-templates=(["\'])(.*?)\1(?=[\s>])/s', $html, $match)) {
            return null;
        }

        $decoded = json_decode(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function bookingCount(): int
    {
        return $this->db->table('bookings')->countAllResults();
    }

    /**
     * @return array<string, mixed>
     */
    private function latestBooking(): array
    {
        return $this->db->table('bookings')->orderBy('id', 'DESC')->get()->getRowArray();
    }

    private function storedVoiceFiles(): int
    {
        return count(glob(WRITEPATH . 'voice-notes-test/*') ?: []);
    }

    // ------------------------------------------------------------------ choosing an occasion

    public function testTheListShowsActiveOccasionsLinkingToTheirForms(): void
    {
        $this->makeCategory(['name' => 'Hidden occasion', 'slug' => 'hidden', 'is_active' => 0]);

        $body = $this->get('booking')->getBody();

        $this->assertStringContainsString('Birthdays', $body);
        $this->assertStringContainsString(site_url('booking/birthdays'), $body);
        $this->assertStringNotContainsString('Hidden occasion', $body);
    }

    public function testAnOccasionsFormListsItsPackagesWithPrices(): void
    {
        $this->makeTier($this->category['id'], ['package_level' => 'Retired package', 'is_active' => 0]);

        $body = $this->get('booking/birthdays')->getBody();

        foreach (['Birthdays', 'Voice Note + Text', 'UGX 30,000', 'Readout + 15s voice note'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
        $this->assertStringNotContainsString('Retired package', $body);
    }

    public function testAnOccasionWithNoPackagesSaysSoInsteadOfShowingAnEmptyForm(): void
    {
        $empty = $this->makeCategory(['name' => 'Empty', 'slug' => 'empty']);

        $body = $this->get('booking/' . $empty['slug'])->getBody();

        $this->assertStringContainsString('No packages are currently available for this category.', $body);
        $this->assertStringNotContainsString('id="bookingForm"', $body);
    }

    public function testAnOccasionStaffHaveSwitchedOffCannotBeBookedByItsAddress(): void
    {
        // Hidden from the list, so it must not be reachable by typing its address either: the visitor would fill the
        // whole form in and only then be told it is not available.
        $retired = $this->makeCategory(['name' => 'Retired occasion', 'slug' => 'retired']);
        $this->makeTier($retired['id']);
        (new \App\Models\CategoryModel())->update($retired['id'], ['name' => 'Retired occasion', 'slug' => 'retired', 'is_active' => 0]);

        $result = $this->get('booking/retired');

        $result->assertRedirect();
        $this->assertSame('Category not found.', $this->flash('toastr_error'));
    }

    // ------------------------------------------------------------------ the form carries the settings

    public function testTheFormIsGivenTheLimitsInForce(): void
    {
        $body = $this->get('booking/birthdays')->getBody();

        foreach (
            [
            'data-max-chars="500"', 'data-wpm="150"', 'data-draft-key="birthdays"', 'data-min-seconds="5"',
            'data-max-seconds="30"', 'data-max-kb="2048"', 'data-exts="mp3,m4a,wav,ogg,opus,webm,aac"', 'Between 5 and 30 seconds',
            ] as $expected
        ) {
            $this->assertStringContainsString($expected, $body);
        }
    }

    public function testChangingTheLimitsInSettingsChangesTheForm(): void
    {
        $this->setSetting('uploads.voiceNoteMaxSeconds', '45');
        $this->setSetting('content.messageMaxLength', '300');
        $this->setSetting('content.readingWordsPerMinute', '120');

        $body = $this->get('booking/birthdays')->getBody();

        $this->assertStringContainsString('data-max-seconds="45"', $body);
        $this->assertStringContainsString('Between 5 and 45 seconds', $body);
        $this->assertStringContainsString('data-max-chars="300"', $body);
        $this->assertStringContainsString('data-wpm="120"', $body);
    }

    public function testTheFormOffersExampleMessagesForThisOccasionAndAnyOccasion(): void
    {
        $other     = $this->makeCategory();
        $templates = new \App\Models\MessageTemplateModel();

        $templates->insert(['category_id' => $this->category['id'], 'language' => 'English', 'title' => 'For birthdays', 'body' => 'Happy birthday {recipient}']);
        $templates->insert(['category_id' => null, 'language' => 'Luganda', 'title' => 'For anything', 'body' => 'Mukama {recipient}']);
        $templates->insert(['category_id' => $other['id'], 'language' => 'English', 'title' => 'For another occasion', 'body' => 'Elsewhere']);
        $templates->insert(['category_id' => $this->category['id'], 'language' => 'English', 'title' => 'Switched off', 'body' => 'Hidden', 'is_active' => 0]);

        $offered = $this->templatesOffered($this->get('booking/birthdays')->getBody());

        $this->assertIsArray($offered, 'The form was not given valid JSON for its example messages.');

        $titles = array_column($offered, 'title');
        sort($titles);

        $this->assertSame(['For anything', 'For birthdays'], $titles);
        $this->assertEqualsCanonicalizing(['id', 'title', 'language', 'body'], array_keys($offered[0]), 'Only what the form needs is handed over.');
    }

    public function testNothingTypedInATemplateCanBreakOutOfThePage(): void
    {
        (new \App\Models\MessageTemplateModel())->insert([
            'category_id' => $this->category['id'], 'language' => 'English',
            'title' => '</script><script>alert(1)</script>', 'body' => '"><img src=x onerror=alert(1)> {recipient}',
        ]);

        $body = $this->get('booking/birthdays')->getBody();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringNotContainsString('<img src=x', $body);

        $offered = $this->templatesOffered($body);
        $this->assertIsArray($offered, 'Hostile text must not stop the form receiving valid JSON.');
        $this->assertSame('</script><script>alert(1)</script>', $offered[0]['title'], 'It arrives as text, to be shown as text.');
    }

    // ------------------------------------------------------------------ making a booking

    public function testAValidBookingAwaitsPaymentAndSendsTheVisitorToThePayPage(): void
    {
        $result = $this->post('booking', $this->form());

        $result->assertRedirect();

        $booking = $this->latestBooking();
        $this->assertStringEndsWith('/booking/' . $booking['id'] . '/pay', $result->getRedirectUrl());
        $this->assertSame(BookingModel::STATUS_PENDING_PAYMENT, $booking['status']);
        $this->assertSame('30000.00', $booking['amount']);
        $this->assertSame('Grace Nakato', $booking['sender_name']);
        $this->assertSame('Happy birthday Mama Ruth!', $booking['message_text']);
        $this->assertNull($booking['voice_note_path']);
    }

    public function testTheVisitorCannotChooseThePriceOrTheStatus(): void
    {
        $this->post('booking', $this->form(['amount' => 1, 'currency' => 'USD', 'status' => 'approved', 'moderated_by' => 1]));

        $booking = $this->latestBooking();
        $this->assertSame('30000.00', $booking['amount']);
        $this->assertSame('UGX', $booking['currency']);
        $this->assertSame('pending_payment', $booking['status']);
        $this->assertNull($booking['moderated_by']);
    }

    public function testMarkupInTheFormIsStrippedBeforeItIsStored(): void
    {
        $this->post('booking', $this->form(['message_text' => '<b>Happy</b> <script>alert(1)</script>birthday', 'recipient_name' => '<i>Mama</i> Ruth']));

        $booking = $this->latestBooking();
        $this->assertStringNotContainsString('<', $booking['message_text']);
        $this->assertSame('Mama Ruth', $booking['recipient_name']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function requiredFields(): array
    {
        return [
            'category'       => ['category_id'],
            'package'        => ['rate_tier_id'],
            'sender name'    => ['sender_name'],
            'sender phone'   => ['sender_phone'],
            'recipient name' => ['recipient_name'],
            'message'        => ['message_text'],
        ];
    }

    #[DataProvider('requiredFields')]
    public function testEachRequiredFieldIsRequired(string $field): void
    {
        $form = $this->form();
        unset($form[$field]);

        $this->post('booking', $form)->assertRedirect();

        $this->assertArrayHasKey($field, $this->flash('errors'));
        $this->assertSame(0, $this->bookingCount());
    }

    public function testMalformedValuesAreRefused(): void
    {
        foreach (
            [
            ['sender_email', 'not-an-email'],
            ['scheduled_date', 'next friday'],
            ['category_id', 'abc'],
            ['rate_tier_id', '0'],
            ['show_id', '-4'],
            ['sender_name', str_repeat('x', 151)],
            ['sender_phone', str_repeat('7', 21)],
            ] as [$field, $value]
        ) {
            $this->post('booking', $this->form([$field => $value]));

            $this->assertArrayHasKey($field, (array) $this->flash('errors'), "{$field} = '{$value}' should be refused.");
        }

        $this->assertSame(0, $this->bookingCount());
    }

    public function testAnEmailIsOptional(): void
    {
        $this->post('booking', $this->form(['sender_email' => '']));

        $this->assertSame(1, $this->bookingCount());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function refusedBySomethingBeyondTheForm(): array
    {
        return [
            'an unknown category'             => [['category_id' => 999999], 'Selected category is not available.'],
            'an empty message after cleaning' => [['message_text' => '<b></b>   '], 'Message cannot be empty.'],
            'a message that is too long'      => [['message_text' => '__LONG__'], 'Message is too long (max 500 characters).'],
            'an unfilled example marker'      => [['message_text' => 'Happy birthday [their name]!'], 'Replace [their name] with a name before sending.'],
            'an unfilled sender marker'       => [['message_text' => 'From [your name]'], 'Replace [your name] with a name before sending.'],
        ];
    }

    #[DataProvider('refusedBySomethingBeyondTheForm')]
    public function testABookingTheServiceRefusesGoesBackWithTheReason(array $override, string $message): void
    {
        $override = array_map(static fn ($v) => $v === '__LONG__' ? str_repeat('x', 501) : $v, $override);

        $this->post('booking', $this->form($override))->assertRedirect();

        $this->assertSame($message, $this->flash('toastr_error'));
        $this->assertSame(0, $this->bookingCount());
    }

    public function testAPackageFromAnotherOccasionIsRefused(): void
    {
        $elsewhere = $this->makeTier();

        $this->post('booking', $this->form(['rate_tier_id' => $elsewhere['id']]));

        $this->assertSame('Selected package is not available for this category.', $this->flash('toastr_error'));
        $this->assertSame(0, $this->bookingCount());
    }

    // ------------------------------------------------------------------ choosing a show

    public function testChoosingAShowAndDateReservesAPlace(): void
    {
        $show = $this->makeShow();

        $this->post('booking', $this->form(['show_id' => $show['id'], 'scheduled_date' => '2026-12-24']));

        $booking = $this->latestBooking();
        $this->assertNotNull($booking['show_slot_id']);
        $this->assertSame('2026-12-24', $booking['scheduled_date']);
        $this->assertSame('1', (string) $this->db->table('show_slots')->get()->getRowArray()['booked_count']);
    }

    public function testAFullShowIsRefusedWithAPlainMessage(): void
    {
        config('CbsRadio')->defaultSlotCapacity = 1;
        $show = $this->makeShow();

        $this->post('booking', $this->form(['show_id' => $show['id'], 'scheduled_date' => '2026-12-24']));
        $this->post('booking', $this->form(['show_id' => $show['id'], 'scheduled_date' => '2026-12-24']));

        $this->assertSame('This show is fully booked for the selected date.', $this->flash('toastr_error'));
        $this->assertSame(1, $this->bookingCount());
    }

    // ------------------------------------------------------------------ the voice note

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> fixture, the name a browser would send, its claimed type
     */
    public static function deviceRecordings(): array
    {
        return [
            'Chrome, Edge, Android (WebM)'   => ['chrome-recording.webm', 'voice-note.webm', 'audio/webm'],
            'Firefox (Ogg)'                  => ['firefox-recording.ogg', 'voice-note.ogg', 'audio/ogg'],
            'WhatsApp voice note (Opus)'     => ['whatsapp-voice.opus', 'PTT-20260101.opus', 'audio/ogg'],
            'a phone voice memo (M4A)'       => ['voice-memo.m4a', 'Voice Memo.m4a', 'audio/x-m4a'],
            'Safari and iPhone (MP4 as M4A)' => ['safari-recording.m4a', 'voice-note.m4a', 'audio/mp4'],
            'MP3'                            => ['recording.mp3', 'greeting.mp3', 'audio/mpeg'],
            'WAV'                            => ['recording.wav', 'greeting.wav', 'audio/wav'],
            'AAC'                            => ['recording.aac', 'greeting.aac', 'audio/aac'],
        ];
    }

    #[DataProvider('deviceRecordings')]
    public function testARecordingFromEveryKindOfDeviceIsAcceptedAndKept(string $fixture, string $name, string $type): void
    {
        AudioFixtures::attachToRequest('voice_note', $fixture, $name, $type);

        $this->post('booking', $this->form(['voice_note_duration_seconds' => '6']))->assertRedirect();

        $this->assertSame(1, $this->bookingCount(), (string) $this->flash('toastr_error'));

        $booking = $this->latestBooking();
        $this->assertMatchesRegularExpression('#^voice-notes-test/#', $booking['voice_note_path']);
        $this->assertFileExists(WRITEPATH . $booking['voice_note_path']);
        $this->assertSame('6', (string) $booking['voice_note_duration_seconds']);
        $this->assertStringNotContainsString($name, $booking['voice_note_path'], 'The visitor\'s own file name is never used on disk.');
    }

    public function testAFileThatIsNotAudioIsRefusedAndNothingIsKept(): void
    {
        AudioFixtures::attachToRequest('voice_note', 'not-audio.txt', 'lovely.mp3', 'audio/mpeg');

        $this->post('booking', $this->form(['voice_note_duration_seconds' => '6']))->assertRedirect();

        $this->assertStringContainsString("isn't a type of audio we can use", $this->flash('toastr_error'));
        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, $this->storedVoiceFiles());
    }

    public function testRealAudioUnderADangerousNameIsRefused(): void
    {
        AudioFixtures::attachToRequest('voice_note', 'chrome-recording.webm', 'shell.php', 'audio/webm');

        $this->post('booking', $this->form(['voice_note_duration_seconds' => '6']));

        $this->assertStringContainsString("file name doesn't look like an audio recording", $this->flash('toastr_error'));
        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, $this->storedVoiceFiles());
    }

    public function testANoteOutsideTheLengthLimitsIsRefusedWithBothNumbers(): void
    {
        AudioFixtures::attachToRequest('voice_note', 'too-long-40s.webm', 'voice-note.webm', 'audio/webm');

        $this->post('booking', $this->form(['voice_note_duration_seconds' => '40']));

        $this->assertSame('Your voice note is 40 seconds long. The longest allowed is 30 seconds.', $this->flash('toastr_error'));
        $this->assertSame(0, $this->bookingCount());
    }

    public function testTheLimitsInSettingsDecideWhatIsAccepted(): void
    {
        $this->setSetting('uploads.voiceNoteMaxSeconds', '60');
        config('CbsRadio')->voiceNoteVerifyWithFfprobe = false;
        AudioFixtures::attachToRequest('voice_note', 'too-long-40s.webm', 'voice-note.webm', 'audio/webm');

        $this->post('booking', $this->form(['voice_note_duration_seconds' => '40']));

        $this->assertSame(1, $this->bookingCount(), 'Raised to 60 seconds, a 40-second note is fine.');
    }

    public function testAnUploadThatPhpItselfRefusedIsNotQuietlyDropped(): void
    {
        // The regression: a file over the server's own limit used to be ignored, and the booking went ahead with no
        // voice note and no word to the visitor about it.
        AudioFixtures::attachRawToRequest('voice_note', ['name' => 'big.webm', 'type' => 'audio/webm', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0]);

        $this->post('booking', $this->form())->assertRedirect();

        $this->assertStringContainsString('larger than this server accepts', $this->flash('toastr_error'));
        $this->assertSame(0, $this->bookingCount(), 'No booking is made without the voice note the visitor meant to send.');
    }

    public function testNoFileChosenIsNotAnError(): void
    {
        AudioFixtures::attachRawToRequest('voice_note', ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]);

        $this->post('booking', $this->form())->assertRedirect();

        $this->assertSame(1, $this->bookingCount());
        $this->assertNull($this->latestBooking()['voice_note_path']);
    }

    public function testAVoiceNoteIsNotLeftBehindWhenTheBookingItBelongedToFails(): void
    {
        // The note is stored before the booking is made. If the booking then fails (a package from another
        // occasion, say), the stored audio must be removed rather than orphaned.
        $elsewhere = $this->makeTier();
        AudioFixtures::attachToRequest('voice_note', 'chrome-recording.webm', 'voice-note.webm', 'audio/webm');

        $this->post('booking', $this->form(['rate_tier_id' => $elsewhere['id'], 'voice_note_duration_seconds' => '6']));

        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, $this->storedVoiceFiles(), 'The audio file of a failed booking was left on disk.');
    }

    public function testTheMeasuredLengthOverridesWhatTheBrowserClaimsWhenTheServerCanMeasure(): void
    {
        if (trim((string) shell_exec('command -v ffprobe 2>/dev/null')) === '') {
            $this->markTestSkipped('ffprobe is not installed.');
        }

        config('CbsRadio')->voiceNoteVerifyWithFfprobe = true;
        AudioFixtures::attachToRequest('voice_note', 'chrome-recording.webm', 'voice-note.webm', 'audio/webm');

        $this->post('booking', $this->form(['voice_note_duration_seconds' => '100']));

        $this->assertSame(1, $this->bookingCount(), 'A 6-second note claiming 100 seconds is a 6-second note.');
        $this->assertSame('6', (string) $this->latestBooking()['voice_note_duration_seconds']);
    }

    // ------------------------------------------------------------------ paying

    public function testThePayPageShowsHowToPayInManualMode(): void
    {
        config('Payments')->mode         = 'manual';
        config('Payments')->merchantCode = '654321';
        config('CbsRadio')->contactPhone = '+256 772 000 111';
        $booking = $this->makeBooking(['category_id' => $this->category['id'], 'rate_tier_id' => $this->tier['id'], 'amount' => 30000]);

        $visible = preg_replace('/\s+/', ' ', strip_tags($this->get('booking/' . $booking['id'] . '/pay')->getBody()));

        foreach (['Pay for your greeting', cbs_booking_reference((int) $booking['id']), 'UGX 30,000', 'Merchant code: 654321', 'Please quote reference', '+256 772 000 111'] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
    }

    public function testReloadingThePayPageDoesNotCreateAnotherPayment(): void
    {
        config('Payments')->mode = 'manual';
        $booking = $this->makeBooking();

        $this->get('booking/' . $booking['id'] . '/pay');
        $this->get('booking/' . $booking['id'] . '/pay');
        $this->get('booking/' . $booking['id'] . '/pay');

        $this->assertSame(1, $this->db->table('payments')->countAllResults());
    }

    public function testABookingNoLongerAwaitingPaymentSendsTheVisitorToItsStatus(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);

        $result = $this->get('booking/' . $booking['id'] . '/pay');

        $result->assertRedirect();
        $this->assertStringEndsWith('/booking/' . $booking['id'] . '/confirmation', $result->getRedirectUrl());
        $this->assertSame(0, $this->db->table('payments')->countAllResults(), 'Visiting the pay page of a paid booking must not start a new payment.');
    }

    public function testOnlinePaymentsNotSetUpAreExplainedOnThePayPage(): void
    {
        config('Payments')->mode     = 'mobile_money';
        config('Payments')->provider = '';
        $booking = $this->makeBooking();

        $this->assertStringContainsString('Online payments are not set up yet.', $this->get('booking/' . $booking['id'] . '/pay')->getBody());
    }

    // ------------------------------------------------------------------ the status page

    public function testTheStatusPageShowsTheBookingAndEscapesWhatWasTyped(): void
    {
        $booking = $this->makeBooking([
            'recipient_name' => 'Mama Ruth', 'message_text' => 'Happy <b>birthday</b> & enjoy', 'status' => BookingModel::STATUS_APPROVED,
        ]);

        $body = $this->get('booking/' . $booking['id'] . '/confirmation')->getBody();

        foreach (['Greeting status', cbs_booking_reference((int) $booking['id']), 'Mama Ruth', 'Approved'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
        $this->assertStringNotContainsString('<b>birthday</b>', $body);
        $this->assertStringContainsString('&lt;b&gt;birthday&lt;/b&gt;', $body);
    }

    public function testTheStatusPageNotesAnAttachedVoiceNote(): void
    {
        $with    = $this->makeBooking(['voice_note_path' => 'voice-notes/x.webm', 'voice_note_duration_seconds' => 12]);
        $without = $this->makeBooking();

        $this->assertStringContainsString('Voice note', $this->get('booking/' . $with['id'] . '/confirmation')->getBody());
        $this->assertStringNotContainsString('Voice note', $this->get('booking/' . $without['id'] . '/confirmation')->getBody());
    }
}
