<?php

namespace App\Services;

use App\Models\BookingModel;
use App\Models\CategoryModel;
use App\Models\RateTierModel;
use App\Libraries\MessageSanitizer;

/**
 * Owns booking creation and lifecycle transitions. This is the single
 * entry point the booking-flow controller should call — it coordinates
 * category/rate-tier lookup, message sanitization (via MessageSanitizer),
 * slot reservation (via SchedulingService), and the initial sender
 * confirmation (via NotificationService), inside one transaction.
 *
 * Voice note handling is NOT done here: the controller should run the
 * uploaded file through App\Libraries\VoiceNoteUploader first (it deals
 * with the HTTP UploadedFile object, which this service never touches)
 * and pass the resulting stored path + duration in as plain values.
 *
 * Expected $input keys for createBooking():
 *   category_id, rate_tier_id, sender_name, sender_phone, sender_email,
 *   recipient_name, message_text, voice_note_path, voice_note_duration_seconds,
 *   show_id (optional), scheduled_date (optional)
 */
class BookingService
{
    protected BookingModel $bookingModel;
    protected CategoryModel $categoryModel;
    protected RateTierModel $rateTierModel;
    protected SchedulingService $schedulingService;
    protected NotificationService $notificationService;
    protected MessageSanitizer $messageSanitizer;

    public function __construct(
        ?BookingModel $bookingModel = null,
        ?CategoryModel $categoryModel = null,
        ?RateTierModel $rateTierModel = null,
        ?SchedulingService $schedulingService = null,
        ?NotificationService $notificationService = null,
        ?MessageSanitizer $messageSanitizer = null
    ) {
        $this->bookingModel        = $bookingModel ?? new BookingModel();
        $this->categoryModel       = $categoryModel ?? new CategoryModel();
        $this->rateTierModel       = $rateTierModel ?? new RateTierModel();
        $this->schedulingService   = $schedulingService ?? new SchedulingService();
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->messageSanitizer    = $messageSanitizer ?? new MessageSanitizer();
    }

    /**
     * @return array{success: bool, message: string, booking: ?array, errors: array}
     */
    public function createBooking(array $input): array
    {
        // find(null) means "every row" in CodeIgniter, so an id that was not sent must never reach it.
        $category = empty($input['category_id']) ? null : $this->categoryModel->find($input['category_id']);
        $rateTier = empty($input['rate_tier_id']) ? null : $this->rateTierModel->find($input['rate_tier_id']);

        if ($category === null || (int) $category['is_active'] !== 1) {
            return $this->failure('Selected category is not available.');
        }

        if ($rateTier === null || (int) $rateTier['is_active'] !== 1 || (int) $rateTier['category_id'] !== (int) $category['id']) {
            return $this->failure('Selected package is not available for this category.');
        }

        $messageCheck = $this->messageSanitizer->validateMessage($input['message_text'] ?? '');

        if (! $messageCheck['valid']) {
            return $this->failure($messageCheck['message']);
        }

        $recipientCheck = $this->messageSanitizer->validateRecipientName($input['recipient_name'] ?? '');

        if (! $recipientCheck['valid']) {
            return $this->failure($recipientCheck['message']);
        }

        // Flags (not blocks) the booking for priority moderator attention —
        // the decision always stays with a human reviewer.
        $flaggedTerms = $this->messageSanitizer->flaggedTerms($messageCheck['sanitized']);

        $db = db_connect();
        $db->transStart();

        $showSlotId = null;

        if (! empty($input['show_id']) && ! empty($input['scheduled_date'])) {
            $reservation = $this->schedulingService->reserveSlot(
                (int) $input['show_id'],
                $input['scheduled_date']
            );

            if (! $reservation['success']) {
                $db->transComplete();

                return $this->failure($reservation['message']);
            }

            $showSlotId = $reservation['slot']['id'];
        }

        $data = [
            'category_id'                 => $category['id'],
            'rate_tier_id'                => $rateTier['id'],
            'show_slot_id'                => $showSlotId,
            'sender_name'                 => $input['sender_name'] ?? null,
            'sender_phone'                => $input['sender_phone'] ?? null,
            'sender_email'                => $input['sender_email'] ?? null,
            'recipient_name'              => $recipientCheck['sanitized'],
            'message_text'                => $messageCheck['sanitized'],
            'voice_note_path'             => $input['voice_note_path'] ?? null,
            'voice_note_duration_seconds' => $input['voice_note_duration_seconds'] ?? null,
            'scheduled_date'              => $input['scheduled_date'] ?? null,
            'status'                      => BookingModel::STATUS_PENDING_PAYMENT,
            'amount'                      => $rateTier['price'],
            'currency'                    => $rateTier['currency'],
            'moderation_notes'            => $flaggedTerms !== []
                ? 'Auto-flagged for review — contains: ' . implode(', ', $flaggedTerms)
                : null,
        ];

        $bookingId = $this->bookingModel->insert($data);

        if ($bookingId === false) {
            // Not transComplete(): that would COMMIT, and the place reserved on the show above would stay
            // taken by a booking that does not exist. The model refusing the data is not a database error,
            // so the transaction would otherwise look successful.
            $db->transRollback();
            $db->resetTransStatus(); // see below: a failed query leaves the status false for every later transaction

            return ['success' => false, 'message' => 'Could not create booking.', 'booking' => null, 'errors' => $this->bookingModel->errors()];
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            // In CodeIgniter's strict transaction mode, one failed query leaves the connection's status false and
            // every later transaction on it then fails too, until the process ends. A web request ends at once, but
            // a worker, a command or a test does not, so the status is cleared here.
            $db->resetTransStatus();

            return $this->failure('Booking could not be saved, please try again.');
        }

        $booking = $this->bookingModel->find($bookingId);
        $this->notificationService->sendBookingConfirmation($booking);

        return ['success' => true, 'message' => 'Booking created.', 'booking' => $booking, 'errors' => []];
    }

    /**
     * Called by PaymentService once a payment for this booking succeeds —
     * moves the booking from awaiting payment into the moderation queue.
     */
    public function confirmPaymentReceived(int $bookingId): bool
    {
        $booking = $this->bookingModel->find($bookingId);

        if ($booking === null || $booking['status'] !== BookingModel::STATUS_PENDING_PAYMENT) {
            return false;
        }

        return $this->bookingModel->update($bookingId, [
            'status' => BookingModel::STATUS_PENDING_MODERATION,
        ]);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function cancelBooking(int $bookingId): array
    {
        $booking = $this->bookingModel->find($bookingId);

        if ($booking === null) {
            return ['success' => false, 'message' => 'Booking not found.'];
        }

        if (in_array($booking['status'], [BookingModel::STATUS_AIRED, BookingModel::STATUS_CANCELLED], true)) {
            return ['success' => false, 'message' => 'This booking can no longer be cancelled.'];
        }

        if (! empty($booking['show_slot_id'])) {
            $this->schedulingService->releaseSlot((int) $booking['show_slot_id']);
        }

        $this->bookingModel->markCancelled($bookingId);

        // Gifts chosen with this greeting and never paid for go with it; paid ones are left for staff to decide on.
        (new GiftOrderService())->cancelBundledUnpaid($bookingId);

        return ['success' => true, 'message' => 'Booking cancelled.'];
    }

    /**
     * Called once a presenter has aired the booking — stamps the time and
     * notifies the sender.
     */
    public function markAired(int $bookingId): bool
    {
        $booking = $this->bookingModel->find($bookingId);

        if ($booking === null || $booking['status'] !== BookingModel::STATUS_APPROVED) {
            return false;
        }

        $this->bookingModel->markAired($bookingId);
        $this->notificationService->sendAiredNotice($this->bookingModel->find($bookingId));

        return true;
    }

    protected function failure(string $message): array
    {
        return ['success' => false, 'message' => $message, 'booking' => null, 'errors' => []];
    }
}
