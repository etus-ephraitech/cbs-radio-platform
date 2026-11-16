<?php

namespace Tests\Feature;

use App\Models\BookingModel;
use App\Models\ShowSlotModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * What a presenter sees and does: the day's approved greetings per show, marking one as read out, and playing a
 * listener's voice note. The same voice-note files are served by three screens (the presenter's, the booking list
 * and moderation), so the file-serving cases run against all three.
 *
 * @internal
 */
final class PresenterQueueTest extends AppFeatureTestCase
{
    private const VOICE_DIR = 'voice-notes-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->silenceNotifications();
    }

    /**
     * @return array<string, mixed>
     */
    private function approvedToday(array $show, string $recipient, array $overrides = []): array
    {
        $slot = (new ShowSlotModel())->findOrCreateForDate($show['id'], date('Y-m-d'));

        return $this->makeBooking($overrides + ['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $slot['id'], 'recipient_name' => $recipient]);
    }

    private function visible(string $html): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    // ------------------------------------------------------------------ the queue

    public function testTheDashboardListsEachActiveShowWithItsApprovedGreetings(): void
    {
        $morning = $this->makeShow(['name' => 'Morning Express', 'start_time' => '06:00:00']);
        $this->makeShow(['name' => 'Evening Drive', 'start_time' => '16:00:00']);
        $this->makeShow(['name' => 'Retired show', 'is_active' => 0]);
        $booking = $this->approvedToday($morning, 'Mama Ruth');

        $visible = $this->visible($this->get('presenter')->getBody());

        foreach (['Morning Express', 'Mama Ruth', cbs_booking_reference((int) $booking['id']), 'Evening Drive', 'Nothing in the queue for this show yet.'] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
        $this->assertStringNotContainsString('Retired show', $visible);
    }

    public function testWithNoShowsThePageSaysSo(): void
    {
        $this->assertStringContainsString('No active shows are configured.', $this->visible($this->get('presenter')->getBody()));
    }

    public function testOnlyApprovedGreetingsReachThePresenter(): void
    {
        $show = $this->makeShow();
        $this->approvedToday($show, 'Approved one');
        $this->approvedToday($show, 'Not yet reviewed', ['status' => BookingModel::STATUS_PENDING_MODERATION]);
        $this->approvedToday($show, 'Never paid', ['status' => BookingModel::STATUS_PENDING_PAYMENT]);
        $this->approvedToday($show, 'Already read', ['status' => BookingModel::STATUS_AIRED]);

        $visible = $this->visible($this->get('presenter/shows/' . $show['id'])->getBody());

        $this->assertStringContainsString('Approved one', $visible);

        foreach (['Not yet reviewed', 'Never paid', 'Already read'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $visible);
        }
    }

    public function testAShowsQueueCanBeLookedUpForAnotherDay(): void
    {
        $show = $this->makeShow();
        $slot = (new ShowSlotModel())->findOrCreateForDate($show['id'], '2026-12-24');
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $slot['id'], 'recipient_name' => 'Christmas Eve']);
        $this->approvedToday($show, 'Today only');

        $visible = $this->visible($this->get('presenter/shows/' . $show['id'] . '?date=2026-12-24')->getBody());

        $this->assertStringContainsString('Christmas Eve', $visible);
        $this->assertStringNotContainsString('Today only', $visible);
    }

    // ------------------------------------------------------------------ reading one out

    public function testMarkingAGreetingAiredRecordsItAndTakesItOffTheQueue(): void
    {
        $show    = $this->makeShow();
        $booking = $this->approvedToday($show, 'Mama Ruth');

        $this->post('presenter/bookings/' . $booking['id'] . '/aired')->assertRedirect();

        $this->assertSame('Marked as aired.', $this->flash('toastr_success'));

        $after = (new BookingModel())->find($booking['id']);
        $this->assertSame('aired', $after['status']);
        $this->assertNotNull($after['aired_at']);

        $this->assertStringNotContainsString('Mama Ruth', $this->visible($this->get('presenter/shows/' . $show['id'])->getBody()));
    }

    public function testOnlyAnApprovedGreetingCanBeMarkedAired(): void
    {
        foreach ([BookingModel::STATUS_PENDING_PAYMENT, BookingModel::STATUS_PENDING_MODERATION, BookingModel::STATUS_REJECTED, BookingModel::STATUS_AIRED, BookingModel::STATUS_CANCELLED] as $status) {
            $booking = $this->makeBooking(['status' => $status]);

            $this->post('presenter/bookings/' . $booking['id'] . '/aired')->assertRedirect();

            $this->assertSame('Could not mark this booking as aired.', $this->flash('toastr_error'), $status);
            $this->assertSame($status, (new BookingModel())->find($booking['id'])['status']);
        }
    }

    public function testAnUnknownGreetingCannotBeMarkedAired(): void
    {
        $this->post('presenter/bookings/999999/aired')->assertRedirect();

        $this->assertSame('Could not mark this booking as aired.', $this->flash('toastr_error'));
    }

    // ------------------------------------------------------------------ playing a voice note

    /**
     * @return array<string, array{0: string}>
     */
    public static function voiceNoteScreens(): array
    {
        return [
            'the presenter'    => ['presenter/bookings/{id}/voice-note'],
            'the booking list' => ['admin/bookings/{id}/voice-note'],
            'moderation'       => ['admin/moderation/{id}/voice-note'],
        ];
    }

    private function bookingWithVoiceNote(string $relativePath): int|string
    {
        return $this->makeBooking(['voice_note_path' => $relativePath, 'voice_note_duration_seconds' => 6])['id'];
    }

    private function storeVoiceNote(string $fixture = 'chrome-recording.webm'): string
    {
        $dir = WRITEPATH . self::VOICE_DIR;

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'note-' . bin2hex(random_bytes(4)) . '.webm';
        copy(\Tests\Support\Libraries\AudioFixtures::path($fixture), "{$dir}/{$name}");

        return self::VOICE_DIR . '/' . $name;
    }

    #[DataProvider('voiceNoteScreens')]
    public function testAStoredVoiceNoteIsPlayedBackInline(string $route): void
    {
        $path = $this->storeVoiceNote();
        $id   = $this->bookingWithVoiceNote($path);

        $result = $this->get(str_replace('{id}', (string) $id, $route));

        $result->assertOK();
        $this->assertSame('video/webm', $result->response()->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('inline;', $result->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame(file_get_contents(WRITEPATH . $path), $result->response()->getBody(), 'The exact bytes that were recorded.');
    }

    #[DataProvider('voiceNoteScreens')]
    public function testABookingWithoutAVoiceNoteHasNothingToPlay(string $route): void
    {
        $id = $this->makeBooking()['id'];

        $this->expectException(PageNotFoundException::class);

        $this->get(str_replace('{id}', (string) $id, $route));
    }

    #[DataProvider('voiceNoteScreens')]
    public function testAVoiceNoteWhoseFileHasGoneIsNotFound(string $route): void
    {
        $id = $this->bookingWithVoiceNote(self::VOICE_DIR . '/was-deleted.webm');

        $this->expectException(PageNotFoundException::class);

        $this->get(str_replace('{id}', (string) $id, $route));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function hostileStoredPaths(): array
    {
        return [
            'climbing out to the project root' => ['../.env'],
            'climbing out to the app'          => ['voice-notes-test/../../app/Config/App.php'],
            'the system password file'         => ['../../../../../../../../etc/passwd'],
            'the session folder'               => ['session/cbs-test-secret.txt'],
            'the log folder'                   => ['logs/cbs-test-secret.txt'],
        ];
    }

    #[DataProvider('hostileStoredPaths')]
    public function testAHostilePathInTheDatabaseCanNeverServeAnotherFile(string $storedPath): void
    {
        // Paths are written only by our own uploader, so this should never happen. But a path read from the database
        // is not trusted, and the voice-note screens must not become a way to read the server's other files.
        foreach (['session', 'logs'] as $folder) {
            @mkdir(WRITEPATH . $folder, 0755, true);
        }
        file_put_contents(WRITEPATH . 'session/cbs-test-secret.txt', 'a session id');
        file_put_contents(WRITEPATH . 'logs/cbs-test-secret.txt', 'a stack trace');

        try {
            $id = $this->bookingWithVoiceNote($storedPath);

            foreach (['presenter/bookings/{id}/voice-note', 'admin/bookings/{id}/voice-note', 'admin/moderation/{id}/voice-note'] as $route) {
                try {
                    $body = $this->get(str_replace('{id}', (string) $id, $route))->response()->getBody();
                    $this->fail("{$route} served something for the stored path '{$storedPath}': " . substr((string) $body, 0, 40));
                } catch (PageNotFoundException) {
                    $this->addToAssertionCount(1);
                }
            }
        } finally {
            @unlink(WRITEPATH . 'session/cbs-test-secret.txt');
            @unlink(WRITEPATH . 'logs/cbs-test-secret.txt');
        }
    }
}
