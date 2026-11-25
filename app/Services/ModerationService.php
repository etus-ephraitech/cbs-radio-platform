<?php

namespace App\Services;

use App\Models\BookingModel;
use App\Models\ModerationLogModel;

/**
 * Owns the moderation workflow for a booking: approve, reject, or flag,
 * each paired with an audit-log entry and a sender notification. This is
 * the one place that should ever change a booking's moderation status.
 */
class ModerationService
{
    protected BookingModel $bookingModel;
    protected ModerationLogModel $moderationLogModel;
    protected SchedulingService $schedulingService;
    protected NotificationService $notificationService;

    public function __construct(
        ?BookingModel $bookingModel = null,
        ?ModerationLogModel $moderationLogModel = null,
        ?SchedulingService $schedulingService = null,
        ?NotificationService $notificationService = null
    ) {
        $this->bookingModel        = $bookingModel ?? new BookingModel();
        $this->moderationLogModel  = $moderationLogModel ?? new ModerationLogModel();
        $this->schedulingService   = $schedulingService ?? new SchedulingService();
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function approve(int $bookingId, int $moderatorId, ?string $notes = null): array
    {
        $booking = $this->bookingModel->find($bookingId);

        if ($booking === null) {
            return ['success' => false, 'message' => 'Booking not found.'];
        }

        if ($booking['status'] !== BookingModel::STATUS_PENDING_MODERATION) {
            return ['success' => false, 'message' => 'Only bookings pending moderation can be approved.'];
        }

        $this->bookingModel->markApproved($bookingId, $moderatorId, $notes);
        $this->moderationLogModel->log($bookingId, $moderatorId, 'approved', $notes);

        $updated = $this->bookingModel->find($bookingId);
        $this->notificationService->sendModerationResult($updated);

        return ['success' => true, 'message' => 'Booking approved.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function reject(int $bookingId, int $moderatorId, ?string $notes = null): array
    {
        $booking = $this->bookingModel->find($bookingId);

        if ($booking === null) {
            return ['success' => false, 'message' => 'Booking not found.'];
        }

        if (! in_array($booking['status'], [BookingModel::STATUS_PENDING_MODERATION, BookingModel::STATUS_PENDING_PAYMENT], true)) {
            return ['success' => false, 'message' => 'This booking cannot be rejected in its current state.'];
        }

        $this->bookingModel->markRejected($bookingId, $moderatorId, $notes);
        $this->moderationLogModel->log($bookingId, $moderatorId, 'rejected', $notes);

        // Release the show-slot spot this booking was holding, if any.
        if (! empty($booking['show_slot_id'])) {
            $this->schedulingService->releaseSlot((int) $booking['show_slot_id']);
        }

        $updated = $this->bookingModel->find($bookingId);
        $this->notificationService->sendModerationResult($updated);

        return ['success' => true, 'message' => 'Booking rejected.'];
    }

    /**
     * Flags a booking for closer review without changing its status —
     * e.g. a moderator is unsure and wants a second opinion before
     * approving or rejecting. Logged only; no sender notification.
     *
     * @return array{success: bool, message: string}
     */
    public function flag(int $bookingId, int $moderatorId, ?string $notes = null): array
    {
        $booking = $this->bookingModel->find($bookingId);

        if ($booking === null) {
            return ['success' => false, 'message' => 'Booking not found.'];
        }

        $this->moderationLogModel->log($bookingId, $moderatorId, 'flagged', $notes);

        return ['success' => true, 'message' => 'Booking flagged for review.'];
    }

    /**
     * Full moderation history for a booking — for an admin detail view.
     */
    public function history(int $bookingId): array
    {
        return $this->moderationLogModel->forBooking($bookingId);
    }
}
