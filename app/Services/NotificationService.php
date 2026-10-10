<?php

namespace App\Services;

use App\Models\NotificationsLogModel;
use Config\Notifications;
use Config\Services as CoreServices;

/**
 * Sends and logs every sender-facing notification. Wording lives in
 * Config\Notifications (edit templates there, not here); which channels
 * are active (SMS, email, or both) is also controlled there, independent
 * of whether the sender actually supplied a phone/email.
 *
 * OPEN ITEM: CBS has not yet confirmed which SMS gateway to use (see the
 * scope sign-off doc). sendSms() below is written against Africa's
 * Talking's messaging API shape as a reasonable default for Uganda — verify
 * the exact endpoint/params against whichever gateway CBS confirms, and
 * against that provider's current docs, before relying on it in production.
 * Email uses CodeIgniter's built-in Email service, configured via the
 * standard email.* .env keys — no open item there.
 */
class NotificationService
{
    protected NotificationsLogModel $notificationsLogModel;
    protected Notifications $config;

    public function __construct(?NotificationsLogModel $notificationsLogModel = null, ?Notifications $config = null)
    {
        $this->notificationsLogModel = $notificationsLogModel ?? new NotificationsLogModel();
        $this->config                = $config ?? config('Notifications');
    }

    /**
     * Sent as soon as a booking is created, before moderation — confirms
     * receipt and gives the sender their reference code.
     */
    public function sendBookingConfirmation(array $booking): void
    {
        $this->dispatch('booking_confirmation', $booking);
    }

    /**
     * Sent after an admin approves or rejects a booking.
     */
    public function sendModerationResult(array $booking): void
    {
        $event = $booking['status'] === 'approved' ? 'moderation_approved' : 'moderation_rejected';
        $this->dispatch($event, $booking);
    }

    /**
     * Sent the moment a booking is marked aired.
     */
    public function sendAiredNotice(array $booking): void
    {
        $this->dispatch('aired', $booking);
    }

    /**
     * Re-sends a previously failed notification using the exact message
     * stored in its log row — so a resend says what the original would
     * have, even if the templates in Config\Notifications have been
     * edited since. The attempt is logged as a new row; the original
     * failed row stays in place as the audit trail.
     *
     * @param array $logRow a notifications_log row
     */
    public function resend(array $logRow): bool
    {
        $bookingId = $logRow['booking_id'] !== null ? (int) $logRow['booking_id'] : null;

        if ($logRow['channel'] === 'sms') {
            return $this->sendSms($bookingId, $logRow['recipient'], $logRow['message']);
        }

        // Email rows are stored as "subject\n\nbody" (see sendEmail()).
        [$subject, $body] = array_pad(explode("\n\n", $logRow['message'], 2), 2, '');

        return $this->sendEmail($bookingId, $logRow['recipient'], $subject, $body);
    }

    /**
     * Sends a one-off test message so staff can confirm the gateway
     * settings work. Goes through exactly the same send-and-log path as a
     * real notification (and shows up in the log with no booking), but
     * ignores the channel on/off switch — testing a channel before
     * turning it on is the point.
     */
    public function sendTest(string $channel, string $recipient): bool
    {
        if ($channel === 'sms') {
            return $this->sendSms(null, $recipient, 'This is a test message from CBS Radio. Your SMS settings are working.');
        }

        if ($channel === 'email') {
            return $this->sendEmail(
                null,
                $recipient,
                'CBS Radio test email',
                "This is a test email from CBS Radio.\n\nYour email settings are working."
            );
        }

        return false;
    }

    /**
     * Renders the named template and sends it over every enabled channel
     * the sender gave us contact details for. Never throws — a
     * notification failure should not break the booking/moderation flow
     * that triggered it; failures are logged instead.
     */
    protected function dispatch(string $event, array $booking): void
    {
        helper('text');

        $template = $this->config->templates[$event] ?? null;

        if ($template === null) {
            return;
        }

        $tokens = [
            'reference'      => cbs_booking_reference((int) $booking['id']),
            'sender_name'    => $booking['sender_name'] ?? '',
            'recipient_name' => $booking['recipient_name'] ?? '',
            // Must stay in step with Registry::TEMPLATE_TOKENS — a template
            // may only use placeholders that are filled in here.
            'contact_phone'  => config('CbsRadio')->contactPhone,
        ];

        if ($this->config->channelsEnabled['sms'] && ! empty($booking['sender_phone'])) {
            $message = $this->config->render($template['sms'], $tokens);
            $this->sendSms((int) $booking['id'], $booking['sender_phone'], $message);
        }

        if ($this->config->channelsEnabled['email'] && ! empty($booking['sender_email'])) {
            $subject = $this->config->render($template['email_subject'], $tokens);
            $body    = $this->config->render($template['email_body'], $tokens);
            $this->sendEmail((int) $booking['id'], $booking['sender_email'], $subject, $body);
        }
    }

    /**
     * Sends one SMS and logs the outcome (sent/failed) against the booking.
     */
    protected function sendSms(?int $bookingId, string $toPhone, string $message): bool
    {
        $apiKey   = env('sms.apiKey');
        $senderId = $this->config->smsSenderId;

        if (empty($apiKey)) {
            $this->notificationsLogModel->logFailed($bookingId, 'sms', $toPhone, $message);

            return false;
        }

        try {
            $client = CoreServices::curlrequest();

            // Africa's Talking messaging API shape — confirm against the
            // provider CBS actually selects before go-live.
            $response = $client->post('https://api.africastalking.com/version1/messaging', [
                'headers' => [
                    'apiKey'       => $apiKey,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'username' => env('sms.username', ''),
                    'to'       => $toPhone,
                    'message'  => $message,
                    'from'     => $senderId,
                ],
            ]);

            $ok = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable $e) {
            $ok = false;
        }

        if ($ok) {
            $this->notificationsLogModel->logSent($bookingId, 'sms', $toPhone, $message);
        } else {
            $this->notificationsLogModel->logFailed($bookingId, 'sms', $toPhone, $message);
        }

        return $ok;
    }

    /**
     * Sends one email via CodeIgniter's built-in Email service (configured
     * through the standard email.* .env keys — SMTP host/user/pass/port)
     * and logs the outcome against the booking.
     */
    protected function sendEmail(?int $bookingId, string $toEmail, string $subject, string $body): bool
    {
        try {
            $emailer = CoreServices::email();

            // The "from" details come from settings when an admin has set
            // them, otherwise from .env (Config\Email).
            if ($this->config->emailFromAddress !== '') {
                $emailer->setFrom($this->config->emailFromAddress, $this->config->emailFromName);
            }

            $emailer->setTo($toEmail);
            $emailer->setSubject($subject);
            $emailer->setMessage(nl2br(esc($body)));

            $ok = $emailer->send();
        } catch (\Throwable $e) {
            $ok = false;
        }

        $logMessage = $subject . "\n\n" . $body;

        if ($ok) {
            $this->notificationsLogModel->logSent($bookingId, 'email', $toEmail, $logMessage);
        } else {
            $this->notificationsLogModel->logFailed($bookingId, 'email', $toEmail, $logMessage);
        }

        return $ok;
    }
}
