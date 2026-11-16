<?php

namespace Tests\Database\Services;

use App\Models\BookingModel;
use App\Models\ShowSlotModel;
use App\Services\BookingService;
use App\Services\ModerationService;
use Tests\Support\Libraries\RecordingNotifier;
use Tests\Support\Libraries\StaffUser;
use Tests\Support\TestCase\AppTestCase;

/**
 * Approving, rejecting and flagging greetings, with the audit trail and the messages that go to the sender.
 *
 * @internal
 */
final class ModerationServiceTest extends AppTestCase
{
    private RecordingNotifier $notifier;
    private ModerationService $moderation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notifier   = new RecordingNotifier();
        $this->moderation = new ModerationService(notificationService: $this->notifier);
    }

    private function queued(array $overrides = []): array
    {
        return $this->makeBooking($overrides + ['status' => BookingModel::STATUS_PENDING_MODERATION]);
    }

    // ------------------------------------------------------------------ approving

    public function testApprovingRecordsWhoWhyAndTellsTheSender(): void
    {
        $booking = $this->queued();

        $result = $this->moderation->approve((int) $booking['id'], StaffUser::id(), 'Lovely');

        $this->assertSame(['success' => true, 'message' => 'Booking approved.'], $result);

        $after = (new BookingModel())->find($booking['id']);
        $this->assertSame('approved', $after['status']);
        $this->assertSame((string) StaffUser::id(), (string) $after['moderated_by']);
        $this->assertSame('Lovely', $after['moderation_notes']);
        $this->assertSame(['moderation_approved'], $this->notifier->events());
    }

    public function testApprovingIsLoggedForTheAuditTrail(): void
    {
        $booking = $this->queued();

        $this->moderation->approve((int) $booking['id'], StaffUser::id(), 'Lovely');

        $history = $this->moderation->history((int) $booking['id']);
        $this->assertCount(1, $history);
        $this->assertSame(['approved', 'Lovely', (string) StaffUser::id()], [$history[0]['action'], $history[0]['notes'], (string) $history[0]['moderator_id']]);
    }

    public function testOnlyABookingWaitingForModerationCanBeApproved(): void
    {
        foreach ([BookingModel::STATUS_PENDING_PAYMENT, BookingModel::STATUS_APPROVED, BookingModel::STATUS_REJECTED, BookingModel::STATUS_AIRED, BookingModel::STATUS_CANCELLED] as $status) {
            $booking = $this->makeBooking(['status' => $status]);

            $this->assertSame(
                ['success' => false, 'message' => 'Only bookings pending moderation can be approved.'],
                $this->moderation->approve((int) $booking['id'], StaffUser::id()),
                "A {$status} booking must not be approvable (an unpaid one would go on air for free)."
            );
            $this->assertSame($status, (new BookingModel())->find($booking['id'])['status']);
        }

        $this->assertSame([], $this->notifier->sent);
        $this->assertSame(0, $this->db->table('moderation_logs')->countAllResults());
    }

    public function testApprovingAnUnknownBookingSaysSo(): void
    {
        $this->assertSame(['success' => false, 'message' => 'Booking not found.'], $this->moderation->approve(999999, StaffUser::id()));
    }

    // ------------------------------------------------------------------ rejecting

    public function testRejectingRecordsWhoWhyAndTellsTheSender(): void
    {
        $booking = $this->queued();

        $result = $this->moderation->reject((int) $booking['id'], StaffUser::id(), 'Not suitable for air');

        $this->assertSame(['success' => true, 'message' => 'Booking rejected.'], $result);

        $after = (new BookingModel())->find($booking['id']);
        $this->assertSame('rejected', $after['status']);
        $this->assertSame('Not suitable for air', $after['moderation_notes']);
        $this->assertSame(['moderation_rejected'], $this->notifier->events());
        $this->assertSame('rejected', $this->moderation->history((int) $booking['id'])[0]['action']);
    }

    public function testAnUnpaidBookingCanBeRejectedToo(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_PAYMENT]);

        $this->assertTrue($this->moderation->reject((int) $booking['id'], StaffUser::id())['success']);
    }

    public function testRejectingGivesThePlaceOnTheShowBack(): void
    {
        $show = $this->makeShow();
        $slot = (new ShowSlotModel())->findOrCreateForDate($show['id'], '2026-12-24', 5);
        (new ShowSlotModel())->incrementBooked($slot['id']);

        $booking = $this->queued(['show_slot_id' => $slot['id']]);

        $this->moderation->reject((int) $booking['id'], StaffUser::id());

        $this->assertSame(0, (int) (new ShowSlotModel())->find($slot['id'])['booked_count']);
    }

    public function testOnlyABookingNotYetApprovedOrFinishedCanBeRejected(): void
    {
        foreach ([BookingModel::STATUS_APPROVED, BookingModel::STATUS_REJECTED, BookingModel::STATUS_AIRED, BookingModel::STATUS_CANCELLED] as $status) {
            $booking = $this->makeBooking(['status' => $status]);

            $this->assertSame(
                ['success' => false, 'message' => 'This booking cannot be rejected in its current state.'],
                $this->moderation->reject((int) $booking['id'], StaffUser::id())
            );
        }

        $this->assertSame([], $this->notifier->sent);
    }

    public function testRejectingAnUnknownBookingSaysSo(): void
    {
        $this->assertSame(['success' => false, 'message' => 'Booking not found.'], $this->moderation->reject(999999, StaffUser::id()));
    }

    // ------------------------------------------------------------------ flagging

    public function testFlaggingLogsAConcernWithoutChangingTheBooking(): void
    {
        $booking = $this->queued();

        $result = $this->moderation->flag((int) $booking['id'], StaffUser::id(), 'Check the name with the presenter');

        $this->assertSame(['success' => true, 'message' => 'Booking flagged for review.'], $result);
        $this->assertSame('pending_moderation', (new BookingModel())->find($booking['id'])['status']);
        $this->assertSame('flagged', $this->moderation->history((int) $booking['id'])[0]['action']);
        $this->assertSame([], $this->notifier->sent, 'The sender is not told about an internal flag.');
    }

    public function testABookingInAnyStateCanBeFlagged(): void
    {
        foreach (BookingModel::STATUSES as $status) {
            $booking = $this->makeBooking(['status' => $status]);

            $this->assertTrue($this->moderation->flag((int) $booking['id'], StaffUser::id())['success'], $status);
        }
    }

    public function testFlaggingAnUnknownBookingSaysSo(): void
    {
        $this->assertSame(['success' => false, 'message' => 'Booking not found.'], $this->moderation->flag(999999, StaffUser::id()));
    }

    // ------------------------------------------------------------------ history

    public function testTheHistoryHoldsEveryDecisionOnABookingAndNoOthers(): void
    {
        $booking = $this->queued();
        $other   = $this->queued();

        $this->moderation->flag((int) $booking['id'], StaffUser::id(), 'first thought');
        $this->moderation->approve((int) $booking['id'], StaffUser::id(), 'fine after all');
        $this->moderation->flag((int) $other['id'], StaffUser::id(), 'not ours');

        $actions = array_column($this->moderation->history((int) $booking['id']), 'action');
        sort($actions);

        $this->assertSame(['approved', 'flagged'], $actions);
        $this->assertSame([], $this->moderation->history(999999));
    }

    // ------------------------------------------------------------------ the whole journey

    public function testABookingCanTravelFromPaymentToAir(): void
    {
        $booking = $this->makeBooking();
        $service = new BookingService(notificationService: $this->notifier);

        $this->assertTrue($service->confirmPaymentReceived((int) $booking['id']));
        $this->assertTrue($this->moderation->approve((int) $booking['id'], StaffUser::id())['success']);
        $this->assertTrue($service->markAired((int) $booking['id']));

        $this->assertSame('aired', (new BookingModel())->find($booking['id'])['status']);
        $this->assertSame(['moderation_approved', 'aired'], $this->notifier->events());
    }
}
