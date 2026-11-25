<?php

namespace Tests\Support\Libraries;

use App\Services\NotificationService;

/**
 * A NotificationService that only writes down what it was asked to send, so tests of bookings and moderation can
 * check that the right message was triggered without sending, or logging, anything.
 */
class RecordingNotifier extends NotificationService
{
    /** @var array<int, array{event: string, status: string|null, id: int|null}> */
    public array $sent = [];

    public function sendBookingConfirmation(array $booking): void
    {
        $this->record('booking_confirmation', $booking);
    }

    public function sendModerationResult(array $booking): void
    {
        $this->record($booking['status'] === 'approved' ? 'moderation_approved' : 'moderation_rejected', $booking);
    }

    public function sendAiredNotice(array $booking): void
    {
        $this->record('aired', $booking);
    }

    /**
     * @return string[]
     */
    public function events(): array
    {
        return array_column($this->sent, 'event');
    }

    private function record(string $event, array $booking): void
    {
        $this->sent[] = ['event' => $event, 'status' => $booking['status'] ?? null, 'id' => isset($booking['id']) ? (int) $booking['id'] : null];
    }
}
