<?php

namespace Tests\Database\Services;

use App\Models\BookingModel;
use App\Models\ShowSlotModel;
use App\Services\BookingService;
use Tests\Support\Libraries\RecordingNotifier;
use Tests\Support\TestCase\AppTestCase;

/**
 * Making a booking, and moving it through payment, cancellation and airing.
 *
 * @internal
 */
final class BookingServiceTest extends AppTestCase
{
    private RecordingNotifier $notifier;
    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config('CbsRadio')->defaultSlotCapacity = 2;

        $this->notifier = new RecordingNotifier();
        $this->service  = new BookingService(notificationService: $this->notifier);
    }

    /**
     * What the booking form posts, for a category and package that really exist.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        $category = $this->makeCategory();
        $tier     = $this->makeTier($category['id'], ['price' => 30000, 'currency' => 'UGX']);

        return $overrides + [
            'category_id'    => $category['id'],
            'rate_tier_id'   => $tier['id'],
            'sender_name'    => 'Grace Nakato',
            'sender_phone'   => '0772123456',
            'sender_email'   => 'grace@example.test',
            'recipient_name' => 'Mama Ruth',
            'message_text'   => 'Happy birthday Mama Ruth!',
        ];
    }

    private function bookingCount(): int
    {
        return $this->db->table('bookings')->countAllResults();
    }

    // ------------------------------------------------------------------ creating

    public function testABookingIsCreatedAwaitingPaymentAtThePackagesPrice(): void
    {
        $result = $this->service->createBooking($this->input());

        $this->assertTrue($result['success']);
        $this->assertSame('Booking created.', $result['message']);

        $booking = $result['booking'];
        $this->assertSame(BookingModel::STATUS_PENDING_PAYMENT, $booking['status']);
        $this->assertSame('30000.00', $booking['amount'], 'The price comes from the package, never from the form.');
        $this->assertSame('UGX', $booking['currency']);
        $this->assertNull($booking['show_slot_id']);
        $this->assertNull($booking['moderation_notes']);
    }

    public function testThePriceCannotBeChosenByTheVisitor(): void
    {
        $result = $this->service->createBooking($this->input(['amount' => 1, 'currency' => 'USD', 'status' => 'approved']));

        $this->assertSame('30000.00', $result['booking']['amount']);
        $this->assertSame('pending_payment', $result['booking']['status']);
    }

    public function testTheMessageAndNameAreSanitisedBeforeTheyAreStored(): void
    {
        $result = $this->service->createBooking($this->input([
            'message_text'   => "  <b>Happy</b>   birthday\n<script>x()</script>",
            'recipient_name' => ' <i>Mama</i>  Ruth ',
        ]));

        $this->assertSame('Happy birthday x()', $result['booking']['message_text']);
        $this->assertSame('Mama Ruth', $result['booking']['recipient_name']);
    }

    public function testTheSenderIsToldThatTheBookingWasReceived(): void
    {
        $result = $this->service->createBooking($this->input());

        $this->assertSame(['booking_confirmation'], $this->notifier->events());
        $this->assertSame((int) $result['booking']['id'], $this->notifier->sent[0]['id']);
    }

    public function testAVoiceNoteIsRecordedWithItsLength(): void
    {
        $result = $this->service->createBooking($this->input(['voice_note_path' => 'voice-notes/abc.webm', 'voice_note_duration_seconds' => 12]));

        $this->assertSame('voice-notes/abc.webm', $result['booking']['voice_note_path']);
        $this->assertSame(12, (int) $result['booking']['voice_note_duration_seconds']);
    }

    // ------------------------------------------------------------------ refusing

    public function testAnUnknownOrInactiveCategoryIsRefused(): void
    {
        $inactive = $this->makeCategory(['is_active' => 0]);
        $tier     = $this->makeTier($inactive['id']);

        foreach ([999999, $inactive['id']] as $categoryId) {
            $result = $this->service->createBooking($this->input(['category_id' => $categoryId, 'rate_tier_id' => $tier['id']]));

            $this->assertFalse($result['success']);
            $this->assertSame('Selected category is not available.', $result['message']);
        }

        $this->assertSame(0, $this->bookingCount());
    }

    public function testMissingIdsAreRefusedNotTreatedAsEverything(): void
    {
        // find(null) means "all rows" in CodeIgniter; a booking with no category or package chosen must be refused.
        $category = $this->makeCategory();
        $tier     = $this->makeTier($category['id']);

        foreach (
            [
            [['rate_tier_id' => $tier['id']], 'Selected category is not available.'],
            [['category_id' => $category['id']], 'Selected package is not available for this category.'],
            [[], 'Selected category is not available.'],
            [['category_id' => '', 'rate_tier_id' => ''], 'Selected category is not available.'],
            ] as [$ids, $expected]
        ) {
            $input = $ids + $this->input();
            unset($input['category_id'], $input['rate_tier_id']);
            $input += $ids;

            $result = $this->service->createBooking($input);

            $this->assertFalse($result['success']);
            $this->assertSame($expected, $result['message']);
        }

        $this->assertSame(0, $this->bookingCount());
    }

    public function testAPackageFromAnotherCategoryOrAnInactiveOneIsRefused(): void
    {
        $elsewhere = $this->makeTier();
        $category  = $this->makeCategory();
        $retired   = $this->makeTier($category['id'], ['is_active' => 0]);

        foreach ([$elsewhere['id'], $retired['id'], 999999] as $tierId) {
            $result = $this->service->createBooking($this->input(['category_id' => $category['id'], 'rate_tier_id' => $tierId]));

            $this->assertFalse($result['success']);
            $this->assertSame('Selected package is not available for this category.', $result['message']);
        }

        $this->assertSame(0, $this->bookingCount());
    }

    public function testABadMessageIsRefusedBeforeAnythingIsSavedOrSent(): void
    {
        foreach (
            [
            ['message_text' => '   ', 'expect' => 'Message cannot be empty.'],
            ['message_text' => 'Happy birthday [their name]!', 'expect' => 'Replace [their name] with a name before sending.'],
            ['message_text' => str_repeat('x', 501), 'expect' => 'Message is too long (max 500 characters).'],
            ['recipient_name' => '', 'expect' => 'Recipient name cannot be empty.'],
            ] as $case
        ) {
            $expect = array_pop($case);
            $result = $this->service->createBooking($this->input($case));

            $this->assertFalse($result['success']);
            $this->assertSame($expect, $result['message']);
        }

        $this->assertSame(0, $this->bookingCount());
        $this->assertSame([], $this->notifier->sent);
    }

    public function testTheMessageLimitComesFromTheConfiguration(): void
    {
        config('CbsRadio')->messageMaxLength = 20;

        $service = new BookingService(notificationService: $this->notifier);

        $this->assertFalse($service->createBooking($this->input(['message_text' => str_repeat('x', 21)]))['success']);
        $this->assertTrue($service->createBooking($this->input(['message_text' => str_repeat('x', 20)]))['success']);
    }

    // ------------------------------------------------------------------ flagged words

    public function testAFlaggedWordMarksTheBookingForReviewButNeverBlocksIt(): void
    {
        config('CbsRadio')->moderationFlagTerms = ['scam', 'lottery'];

        $service = new BookingService(notificationService: $this->notifier);
        $result  = $service->createBooking($this->input(['message_text' => 'You won the LOTTERY, not a scam']));

        $this->assertTrue($result['success'], 'A flag only asks a moderator to look; it never refuses a booking.');
        $this->assertSame(BookingModel::STATUS_PENDING_PAYMENT, $result['booking']['status']);
        $this->assertSame('Auto-flagged for review — contains: scam, lottery', $result['booking']['moderation_notes']);
    }

    // ------------------------------------------------------------------ choosing a show

    public function testChoosingAShowAndDateReservesAPlace(): void
    {
        $show = $this->makeShow();

        $result = $this->service->createBooking($this->input(['show_id' => $show['id'], 'scheduled_date' => '2026-12-24']));

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['booking']['show_slot_id']);
        $this->assertSame('2026-12-24', $result['booking']['scheduled_date']);

        $slot = (new ShowSlotModel())->find($result['booking']['show_slot_id']);
        $this->assertSame(1, (int) $slot['booked_count']);
        $this->assertSame((int) $show['id'], (int) $slot['show_id']);
    }

    public function testAShowWithNoDateReservesNothing(): void
    {
        $show = $this->makeShow();

        $result = $this->service->createBooking($this->input(['show_id' => $show['id']]));

        $this->assertTrue($result['success']);
        $this->assertNull($result['booking']['show_slot_id']);
        $this->assertSame(0, $this->db->table('show_slots')->countAllResults());
    }

    public function testAFullDayIsRefusedAndNoBookingIsMade(): void
    {
        $show = $this->makeShow();

        $this->service->createBooking($this->input(['show_id' => $show['id'], 'scheduled_date' => '2026-12-24']));
        $this->service->createBooking($this->input(['show_id' => $show['id'], 'scheduled_date' => '2026-12-24']));

        $third = $this->service->createBooking($this->input(['show_id' => $show['id'], 'scheduled_date' => '2026-12-24']));

        $this->assertFalse($third['success']);
        $this->assertSame('This show is fully booked for the selected date.', $third['message']);
        $this->assertSame(2, $this->bookingCount());
    }

    public function testAnInactiveShowIsRefused(): void
    {
        $show = $this->makeShow(['is_active' => 0]);

        $result = $this->service->createBooking($this->input(['show_id' => $show['id'], 'scheduled_date' => '2026-12-24']));

        $this->assertFalse($result['success']);
        $this->assertSame('This show is not available for booking.', $result['message']);
    }

    public function testABookingThatCannotBeSavedDoesNotKeepItsPlaceOnTheShow(): void
    {
        // The place is reserved first and the booking saved second. If the saving then fails, the place must be
        // given back, or a failed booking would quietly use up capacity that nobody holds.
        $show = $this->makeShow();

        $result = $this->service->createBooking($this->input([
            'show_id' => $show['id'], 'scheduled_date' => '2026-12-24', 'sender_name' => '', // the model refuses a booking with no sender
        ]));

        $this->assertFalse($result['success']);
        $this->assertSame('Could not create booking.', $result['message']);
        $this->assertArrayHasKey('sender_name', $result['errors']);

        $slot = $this->db->table('show_slots')->get()->getRowArray();
        $this->assertSame(0, (int) ($slot['booked_count'] ?? 0), 'The failed booking is holding a place on the show.');
        $this->assertSame([], $this->notifier->sent);
    }

    // ------------------------------------------------------------------ payment received

    public function testPaymentMovesABookingOnToModeration(): void
    {
        $booking = $this->makeBooking();

        $this->assertTrue($this->service->confirmPaymentReceived((int) $booking['id']));
        $this->assertSame('pending_moderation', (new BookingModel())->find($booking['id'])['status']);
    }

    public function testPaymentCannotBeConfirmedTwiceOrOutOfOrder(): void
    {
        $approved = $this->makeBooking(['status' => BookingModel::STATUS_APPROVED]);
        $moderation = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);

        $this->assertFalse($this->service->confirmPaymentReceived((int) $approved['id']), 'An approved booking must not slip back into the queue.');
        $this->assertFalse($this->service->confirmPaymentReceived((int) $moderation['id']));
        $this->assertFalse($this->service->confirmPaymentReceived(999999));
        $this->assertSame('approved', (new BookingModel())->find($approved['id'])['status']);
    }

    // ------------------------------------------------------------------ cancelling

    public function testCancellingAGivesThePlaceBack(): void
    {
        $show   = $this->makeShow();
        $result = $this->service->createBooking($this->input(['show_id' => $show['id'], 'scheduled_date' => '2026-12-24']));

        $cancel = $this->service->cancelBooking((int) $result['booking']['id']);

        $this->assertSame(['success' => true, 'message' => 'Booking cancelled.'], $cancel);
        $this->assertSame('cancelled', (new BookingModel())->find($result['booking']['id'])['status']);
        $this->assertSame(0, (int) (new ShowSlotModel())->find($result['booking']['show_slot_id'])['booked_count']);
    }

    public function testABookingWithNoShowCanBeCancelledToo(): void
    {
        $booking = $this->makeBooking();

        $this->assertTrue($this->service->cancelBooking((int) $booking['id'])['success']);
    }

    public function testAnAiredOrAlreadyCancelledBookingCannotBeCancelled(): void
    {
        foreach ([BookingModel::STATUS_AIRED, BookingModel::STATUS_CANCELLED] as $status) {
            $booking = $this->makeBooking(['status' => $status]);

            $this->assertSame(
                ['success' => false, 'message' => 'This booking can no longer be cancelled.'],
                $this->service->cancelBooking((int) $booking['id'])
            );
            $this->assertSame($status, (new BookingModel())->find($booking['id'])['status']);
        }
    }

    public function testCancellingAnUnknownBookingSaysSo(): void
    {
        $this->assertSame(['success' => false, 'message' => 'Booking not found.'], $this->service->cancelBooking(999999));
    }

    // ------------------------------------------------------------------ airing

    public function testAnApprovedBookingCanBeMarkedAiredAndTheSenderIsTold(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_APPROVED]);

        $this->assertTrue($this->service->markAired((int) $booking['id']));

        $after = (new BookingModel())->find($booking['id']);
        $this->assertSame('aired', $after['status']);
        $this->assertNotNull($after['aired_at']);
        $this->assertSame(['aired'], $this->notifier->events());
    }

    public function testOnlyAnApprovedBookingCanBeMarkedAired(): void
    {
        foreach ([BookingModel::STATUS_PENDING_PAYMENT, BookingModel::STATUS_PENDING_MODERATION, BookingModel::STATUS_REJECTED, BookingModel::STATUS_AIRED, BookingModel::STATUS_CANCELLED] as $status) {
            $booking = $this->makeBooking(['status' => $status]);

            $this->assertFalse($this->service->markAired((int) $booking['id']), "A {$status} booking must not be markable as aired.");
        }

        $this->assertFalse($this->service->markAired(999999));
        $this->assertSame([], $this->notifier->sent, 'Nobody is told a greeting aired when it did not.');
    }
}
