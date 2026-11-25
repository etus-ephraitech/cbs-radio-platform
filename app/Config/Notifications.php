<?php

namespace Config;

use App\Libraries\Settings\AppliesSettingOverrides;
use CodeIgniter\Config\BaseConfig;

/**
 * Which notification channels are active, and the exact wording sent for
 * each event. Change wording here — never inline a message string in
 * NotificationService. Placeholders in a template are replaced with
 * {curly-brace} tokens; see each template's comment for what's available.
 */
class Notifications extends BaseConfig
{
    use AppliesSettingOverrides;

    /**
     * Per-channel on/off switch, independent of whether a sender supplied
     * a phone/email — NotificationService checks both before sending.
     * Email defaults off since it's not a confirmed Phase 1 requirement;
     * flip to true once CBS confirms they want it alongside SMS.
     */
    public array $channelsEnabled = [
        'sms'   => true,
        'email' => false,
    ];

    /**
     * {reference}  — e.g. CBS-000123
     * {sender_name}
     * {recipient_name}
     */
    public array $templates = [
        'booking_confirmation' => [
            'sms'          => 'Thanks {sender_name}! Your CBS Radio greeting (ref {reference}) was received and is being reviewed.',
            'email_subject' => 'We received your CBS Radio greeting — {reference}',
            'email_body'    => "Hi {sender_name},\n\nThanks for your submission to CBS Radio for {recipient_name}. Your reference is {reference}. We'll let you know once it's been reviewed.\n\n— CBS Radio",
        ],
        'moderation_approved' => [
            'sms'           => 'Good news {sender_name}! Your CBS Radio greeting (ref {reference}) was approved and will air soon.',
            'email_subject' => 'Your CBS Radio greeting was approved — {reference}',
            'email_body'    => "Hi {sender_name},\n\nGood news — your greeting for {recipient_name} (ref {reference}) was approved and will air soon.\n\n— CBS Radio",
        ],
        'moderation_rejected' => [
            'sms'           => 'Your CBS Radio greeting (ref {reference}) could not be approved. Contact CBS Radio for details.',
            'email_subject' => 'Your CBS Radio greeting needs attention — {reference}',
            'email_body'    => "Hi {sender_name},\n\nYour greeting (ref {reference}) could not be approved as submitted. Please contact CBS Radio for details.\n\n— CBS Radio",
        ],
        'aired' => [
            'sms'           => 'Your CBS Radio greeting (ref {reference}) just aired. Thank you for using CBS Radio!',
            'email_subject' => 'Your CBS Radio greeting just aired — {reference}',
            'email_body'    => "Hi {sender_name},\n\nYour greeting for {recipient_name} (ref {reference}) just aired. Thank you for using CBS Radio!\n\n— CBS Radio",
        ],
    ];

    /** What recipients see as the SMS sender. Alphanumeric senders are limited to 11 characters. */
    public string $smsSenderId = 'CBSRADIO';

    /** Empty means "use the address and name from .env (Config\Email)". */
    public string $emailFromAddress = '';
    public string $emailFromName    = '';

    public function __construct()
    {
        parent::__construct();

        $this->smsSenderId      = (string) env('sms.senderId', $this->smsSenderId);
        $this->emailFromAddress = (string) env('notifications.emailFromAddress', $this->emailFromAddress);
        $this->emailFromName    = (string) env('notifications.emailFromName', $this->emailFromName);

        $smsEnabled   = env('notifications.smsEnabled');
        $emailEnabled = env('notifications.emailEnabled');

        if ($smsEnabled !== null) {
            $this->channelsEnabled['sms'] = filter_var($smsEnabled, FILTER_VALIDATE_BOOLEAN);
        }

        if ($emailEnabled !== null) {
            $this->channelsEnabled['email'] = filter_var($emailEnabled, FILTER_VALIDATE_BOOLEAN);
        }

        // Last: admin settings win over .env, which wins over the defaults above.
        $this->applySettingOverrides('Notifications');
    }

    /**
     * Fill a template string's {placeholder} tokens.
     */
    public function render(string $template, array $tokens): string
    {
        $replacements = [];

        foreach ($tokens as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }

        return strtr($template, $replacements);
    }
}
