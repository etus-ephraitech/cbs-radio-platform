<?php

namespace App\Libraries\Settings;

/**
 * The one place that describes every setting an admin can change: its
 * label, type, limits, help text, and — the important part — which Config
 * class property it overrides ("target").
 *
 * To make something configurable, add an entry here that points at an
 * existing Config property. Nothing else needs to change: the Config class
 * applies the override, every consumer already reads the Config class, and
 * the settings screen builds its form from this list.
 *
 * What does NOT belong here: secrets (API keys, passwords, tokens). Those
 * stay in .env and are listed under secrets() only so the screen can show
 * whether each one is configured — never its value.
 *
 * Types: bool, int, string, text (multi-line), select, list (one per line).
 */
final class Registry
{
    /** Placeholders a message template may use. NotificationService fills these. */
    public const TEMPLATE_TOKENS = ['reference', 'sender_name', 'recipient_name', 'contact_phone'];

    private const GROUPS = [
        'general'       => 'General',
        'site'          => 'Public site',
        'payments'      => 'Payments',
        'notifications' => 'Notifications',
        'templates'     => 'Message templates',
        'bookings'      => 'Bookings & content',
    ];

    private const TEMPLATE_EVENTS = [
        'booking_confirmation' => 'Greeting received',
        'moderation_approved'  => 'Greeting approved',
        'moderation_rejected'  => 'Greeting not approved',
        'aired'                => 'Greeting aired',
    ];

    /** @var array<string, array>|null */
    private static ?array $definitions = null;

    /**
     * @return array<string, string> group key => label
     */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    /**
     * @return array<string, array>
     */
    public static function definitions(): array
    {
        return self::$definitions ??= self::build();
    }

    /**
     * @return array<string, array>
     */
    public static function forGroup(string $group): array
    {
        return array_filter(self::definitions(), static fn (array $d) => $d['group'] === $group);
    }

    /**
     * .env values the settings screen reports on (configured or not).
     * Values are never read out or displayed.
     *
     * @return array<string, array<int, array{label: string, env: string}>>
     */
    public static function secrets(): array
    {
        return [
            'payments' => [
                ['label' => 'Provider API base URL', 'env' => 'momo.baseUrl'],
                ['label' => 'API key', 'env' => 'momo.apiKey'],
                ['label' => 'API secret', 'env' => 'momo.apiSecret'],
                ['label' => 'Callback verification token', 'env' => 'momo.callbackToken'],
            ],
            'notifications' => [
                ['label' => 'SMS gateway API key', 'env' => 'sms.apiKey'],
                ['label' => 'SMS gateway account username', 'env' => 'sms.username'],
                ['label' => 'SMTP host', 'env' => 'email.SMTPHost'],
                ['label' => 'SMTP username', 'env' => 'email.SMTPUser'],
                ['label' => 'SMTP password', 'env' => 'email.SMTPPass'],
            ],
        ];
    }

    /** Form field name for a setting key. Dots are avoided because old() treats them as array paths. */
    public static function fieldName(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    /** Reverse of fieldName(), by lookup — never by string manipulation. */
    public static function keyForField(string $field): ?string
    {
        foreach (array_keys(self::definitions()) as $key) {
            if (self::fieldName($key) === $field) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Turns the stored text back into the type the Config property holds.
     */
    public static function cast(array $def, ?string $raw): mixed
    {
        $raw ??= '';

        return match ($def['type']) {
            'bool'  => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'int'   => (int) $raw,
            'list'  => self::splitList($raw),
            default => $raw,
        };
    }

    /**
     * The inverse of cast(): a typed value as the text that gets stored.
     */
    public static function toRaw(array $def, mixed $value): string
    {
        return match ($def['type']) {
            'bool'  => $value ? '1' : '0',
            'list'  => implode("\n", (array) $value),
            default => (string) $value,
        };
    }

    /**
     * Cleans one submitted value into its canonical stored text, so
     * "unchanged" comparisons are not fooled by line endings or spacing.
     */
    public static function normalize(array $def, mixed $input): string
    {
        $input = is_scalar($input) ? (string) $input : '';

        return match ($def['type']) {
            'bool'   => in_array(strtolower(trim($input)), ['1', 'on', 'true', 'yes'], true) ? '1' : '0',
            'list'   => implode("\n", self::splitList($input)),
            'text'   => trim(str_replace(["\r\n", "\r"], "\n", $input)),
            default  => ($def['format'] ?? '') === 'currency' ? strtoupper(trim($input)) : trim($input),
        };
    }

    /**
     * @return string[]
     */
    private static function splitList(string $raw): array
    {
        $parts = preg_split('/\R|,/', $raw) ?: [];
        $parts = array_map('trim', $parts);

        return array_values(array_unique(array_filter($parts, static fn ($p) => $p !== '')));
    }

    /**
     * @return array<string, array>
     */
    private static function build(): array
    {
        $defs = [
            // ---------------------------------------------------------- general
            'general.contactPhone' => [
                'group' => 'general', 'type' => 'string', 'format' => 'phone', 'maxLength' => 20,
                'label' => 'Contact phone',
                'help'  => 'Shown to people on payment pages, and available as {contact_phone} in message templates.',
                'target' => ['CbsRadio', 'contactPhone'],
            ],
            'general.contactEmail' => [
                'group' => 'general', 'type' => 'string', 'format' => 'email', 'maxLength' => 150,
                'label' => 'Contact email',
                'help'  => 'Shown to people when something goes wrong with their booking.',
                'target' => ['CbsRadio', 'contactEmail'],
            ],
            'general.defaultCurrency' => [
                'group' => 'general', 'type' => 'string', 'format' => 'currency', 'maxLength' => 3,
                'label' => 'Default currency',
                'help'  => 'Three-letter code used when a package or gift is created without one. Existing prices keep their own currency.',
                'target' => ['CbsRadio', 'defaultCurrency'],
            ],
            'general.adminPageSize' => [
                'group' => 'general', 'type' => 'int', 'min' => 5, 'max' => 200,
                'label' => 'Rows per page in admin lists',
                'help'  => 'Applies to the bookings list and the notifications log.',
                'target' => ['CbsRadio', 'adminPageSize'],
            ],

            // -------------------------------------------------------- public site
            'site.heroHeadline' => [
                'group' => 'site', 'type' => 'string', 'maxLength' => 60, 'required' => true,
                'label' => 'Homepage headline',
                'help'  => 'The big line at the top of the public homepage. Keep it short.',
                'target' => ['CbsRadio', 'heroHeadline'],
            ],
            'site.heroSubheadline' => [
                'group' => 'site', 'type' => 'text', 'maxLength' => 240, 'rows' => 3, 'required' => true,
                'label' => 'Homepage introduction',
                'help'  => 'One or two sentences under the headline.',
                'target' => ['CbsRadio', 'heroSubheadline'],
            ],
            'site.announcement' => [
                'group' => 'site', 'type' => 'string', 'maxLength' => 160,
                'label' => 'Announcement banner',
                'help'  => 'A short notice shown at the top of every public page — for example "Mother\'s Day greetings close on Friday". Leave empty for no banner.',
                'target' => ['CbsRadio', 'announcement'],
            ],
            'site.liveStreamUrl' => [
                'group' => 'site', 'type' => 'string', 'format' => 'url', 'maxLength' => 255,
                'label' => 'Live stream address',
                'help'  => 'The address of the CBS Radio audio stream. When set, a "Listen live" button appears on the homepage; when empty, it is hidden. Use https:// if the site is on https — browsers block plain http audio on secure pages.',
                'target' => ['CbsRadio', 'liveStreamUrl'],
            ],

            // ---------------------------------------------------------- payments
            'payments.mode' => [
                'group' => 'payments', 'type' => 'select',
                'options' => [
                    'manual'       => 'Manual — staff confirm each payment',
                    'mobile_money' => 'Mobile Money — online, confirmed automatically',
                ],
                'label' => 'Payment mode',
                'help'  => 'In manual mode a booking is taken, the payer sees the instructions below, and staff mark it paid from the payments screen. Online mode needs a provider and credentials in .env.',
                'target' => ['Payments', 'mode'],
            ],
            'payments.provider' => [
                'group' => 'payments', 'type' => 'select',
                'options' => ['' => '— not chosen —', 'mtn' => 'MTN Mobile Money', 'airtel' => 'Airtel Money'],
                'label' => 'Mobile Money provider',
                'help'  => 'Only used in Mobile Money mode.',
                'target' => ['Payments', 'provider'],
            ],
            'payments.merchantCode' => [
                'group' => 'payments', 'type' => 'string', 'maxLength' => 50,
                'label' => 'CBS merchant code',
                'help'  => 'Shown to payers in manual mode so they know where to send the money.',
                'target' => ['Payments', 'merchantCode'],
            ],
            'payments.manualInstructions' => [
                'group' => 'payments', 'type' => 'text', 'maxLength' => 1000, 'rows' => 4, 'required' => true,
                'label' => 'Payment instructions',
                'help'  => 'Shown to the payer in manual mode, under the amount and reference.',
                'target' => ['Payments', 'manualInstructions'],
            ],

            // ----------------------------------------------------- notifications
            'notifications.smsEnabled' => [
                'group' => 'notifications', 'type' => 'bool',
                'label' => 'Send SMS notifications',
                'help'  => 'Needs the SMS gateway credentials in .env. Failed sends appear in the notifications log.',
                'target' => ['Notifications', 'channelsEnabled.sms'],
            ],
            'notifications.emailEnabled' => [
                'group' => 'notifications', 'type' => 'bool',
                'label' => 'Send email notifications',
                'help'  => 'Needs the SMTP settings in .env. Only sent when the sender gave an email address.',
                'target' => ['Notifications', 'channelsEnabled.email'],
            ],
            'notifications.smsSenderId' => [
                'group' => 'notifications', 'type' => 'string', 'format' => 'senderid', 'maxLength' => 11,
                'label' => 'SMS sender name',
                'help'  => 'What recipients see as the sender. Up to 11 letters and numbers, no spaces.',
                'target' => ['Notifications', 'smsSenderId'],
            ],
            'notifications.emailFromAddress' => [
                'group' => 'notifications', 'type' => 'string', 'format' => 'email', 'maxLength' => 150,
                'label' => 'Email "from" address',
                'help'  => 'Leave empty to use the address in .env.',
                'target' => ['Notifications', 'emailFromAddress'],
            ],
            'notifications.emailFromName' => [
                'group' => 'notifications', 'type' => 'string', 'maxLength' => 100,
                'label' => 'Email "from" name',
                'help'  => 'Leave empty to use the name in .env.',
                'target' => ['Notifications', 'emailFromName'],
            ],

            // ---------------------------------------------------------- bookings
            'content.messageMaxLength' => [
                'group' => 'bookings', 'type' => 'int', 'min' => 50, 'max' => 2000,
                'label' => 'Maximum message length (characters)',
                'help'  => 'Keeps messages short enough to read on air.',
                'target' => ['CbsRadio', 'messageMaxLength'],
            ],
            'content.recipientNameMaxLength' => [
                'group' => 'bookings', 'type' => 'int', 'min' => 20, 'max' => 300,
                'label' => 'Maximum recipient name length (characters)',
                'help'  => '',
                'target' => ['CbsRadio', 'recipientNameMaxLength'],
            ],
            'content.readingWordsPerMinute' => [
                'group' => 'bookings', 'type' => 'int', 'min' => 80, 'max' => 220,
                'label' => 'Reading pace for the on-air time estimate (words per minute)',
                'help'  => 'Only used for the "about N seconds on air" hint under the message box.',
                'target' => ['CbsRadio', 'readingWordsPerMinute'],
            ],
            'uploads.voiceNoteMinSeconds' => [
                'group' => 'bookings', 'type' => 'int', 'min' => 1, 'max' => 60,
                'label' => 'Shortest voice note (seconds)',
                'help'  => '',
                'target' => ['CbsRadio', 'voiceNoteMinSeconds'],
            ],
            'uploads.voiceNoteMaxSeconds' => [
                'group' => 'bookings', 'type' => 'int', 'min' => 5, 'max' => 300,
                'label' => 'Longest voice note (seconds)',
                'help'  => 'Must be longer than the shortest.',
                'target' => ['CbsRadio', 'voiceNoteMaxSeconds'],
            ],
            'uploads.voiceNoteMaxSizeKB' => [
                'group' => 'bookings', 'type' => 'int', 'min' => 128, 'max' => 20480,
                'label' => 'Largest voice note file (KB)',
                'help'  => "Cannot exceed the server's PHP upload limit (upload_max_filesize / post_max_size) — raise those too if you go above 2048.",
                'target' => ['CbsRadio', 'voiceNoteMaxSizeKB'],
            ],
            'scheduling.defaultSlotCapacity' => [
                'group' => 'bookings', 'type' => 'int', 'min' => 1, 'max' => 500,
                'label' => 'Bookings per show, per day',
                'help'  => 'Applies to show days that have no bookings yet; days already booked keep their current capacity.',
                'target' => ['CbsRadio', 'defaultSlotCapacity'],
            ],
            'moderation.flagTerms' => [
                'group' => 'bookings', 'type' => 'list', 'rows' => 5, 'maxItems' => 200, 'maxLength' => 60,
                'label' => 'Words that flag a booking for priority review',
                'help'  => 'One per line. A match only flags the booking for a moderator — it never blocks or rejects anything.',
                'target' => ['CbsRadio', 'moderationFlagTerms'],
            ],
        ];

        // ------------------------------------------------------------ templates
        foreach (self::TEMPLATE_EVENTS as $event => $eventLabel) {
            $defs["notifications.template.{$event}.sms"] = [
                'group' => 'templates', 'type' => 'text', 'maxLength' => 480, 'rows' => 2, 'required' => true,
                'sms' => true, 'tokens' => true,
                'label' => "{$eventLabel} — SMS",
                'help'  => '',
                'target' => ['Notifications', "templates.{$event}.sms"],
            ];
            $defs["notifications.template.{$event}.email_subject"] = [
                'group' => 'templates', 'type' => 'string', 'maxLength' => 200, 'required' => true, 'tokens' => true,
                'label' => "{$eventLabel} — email subject",
                'help'  => '',
                'target' => ['Notifications', "templates.{$event}.email_subject"],
            ];
            $defs["notifications.template.{$event}.email_body"] = [
                'group' => 'templates', 'type' => 'text', 'maxLength' => 5000, 'rows' => 5, 'required' => true, 'tokens' => true,
                'label' => "{$eventLabel} — email body",
                'help'  => '',
                'target' => ['Notifications', "templates.{$event}.email_body"],
            ];
        }

        return $defs;
    }
}
