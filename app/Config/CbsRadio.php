<?php

namespace Config;

use App\Libraries\Settings\AppliesSettingOverrides;
use CodeIgniter\Config\BaseConfig;

/**
 * Every project-specific tunable value lives here, with an .env override
 * for each one. If a Model, Service, or Controller needs a limit, a
 * default, or a path — it comes from here, never a literal number typed
 * inline. This is the one file to check (and the one file to change) when
 * CBS asks "can we allow longer voice notes" or "can slots hold more
 * bookings" — no code change needed, just .env.
 */
class CbsRadio extends BaseConfig
{
    use AppliesSettingOverrides;

    // ----- Message / content limits -----
    // Booking.message_text is a TEXT column (no hard DB cap), but the UI
    // and validation enforce this so messages stay radio-readable.
    public int $messageMaxLength = 500;

    public int $recipientNameMaxLength = 150;

    // Used only for the "about N seconds on air" estimate under the message
    // box. Relaxed radio reading is roughly 130-160 words a minute.
    public int $readingWordsPerMinute = 150;

    // ----- Voice notes -----
    // Matches the "15-30 second" range from the original proposal — kept
    // as a min/max pair rather than a fixed 30s so it can be loosened per
    // show type later without a schema or code change.
    public int $voiceNoteMinSeconds = 5;
    public int $voiceNoteMaxSeconds = 30;
    public int $voiceNoteMaxSizeKB  = 2048;

    /**
     * What PHP's file-type detection reports for audio people actually send.
     * Measured with real files: audio-only WebM (every Chrome/Edge/Android
     * recording) is reported as video/webm, and audio-only MP4 (Safari and
     * iPhone) as video/mp4 — so those two must be listed even though this
     * is a *voice note*. Phone voice memos (.m4a) come through as
     * audio/x-m4a, and .aac as audio/x-hx-aac-adts. The ffprobe check
     * below is what stops a real video sneaking in under a video/* type.
     *
     * @var string[]
     */
    public array $voiceNoteAllowedMimeTypes = [
        'audio/mpeg', 'audio/mp3',
        'audio/mp4', 'audio/x-m4a', 'audio/m4a',
        'audio/aac', 'audio/x-aac', 'audio/x-hx-aac-adts',
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave',
        'audio/ogg', 'application/ogg', 'audio/opus',
        'audio/webm',
        'video/webm', 'video/mp4',
    ];

    /**
     * Checked against the file name the visitor's browser sent. .opus is
     * what WhatsApp voice notes are called. (.mp4 is deliberately absent: the
     * recorder names an MP4 recording .m4a, and a file literally called .mp4
     * is far more likely to be a video.)
     *
     * @var string[]
     */
    public array $voiceNoteAllowedExtensions = ['mp3', 'm4a', 'wav', 'ogg', 'opus', 'webm', 'aac'];

    // When the server has ffprobe, the real length of a voice note is measured
    // from the file itself instead of trusting the browser's claim, and
    // anything that isn't a playable audio recording is refused. Without
    // ffprobe the checks fall back to the browser's reported length.
    public bool $voiceNoteVerifyWithFfprobe = true;
    public string $voiceNoteFfprobePath     = 'ffprobe';

    // Relative to WRITEPATH, so it's always inside writable/ regardless of
    // environment — never an absolute path baked in here.
    public string $voiceNoteUploadSubpath = 'voice-notes';

    // ----- Scheduling -----
    public int $defaultSlotCapacity = 20;

    // ----- Currency -----
    public string $defaultCurrency = 'UGX';

    // ----- Contact details -----
    // Shown to people on payment pages when something goes wrong, and
    // available as {contact_phone} in message templates.
    public string $contactPhone = '';
    public string $contactEmail = '';

    // ----- Public site -----
    // Homepage copy and the optional live stream, editable under
    // Admin > Settings > Public site.
    public string $heroHeadline = 'Say it on air.';
    public string $heroSubheadline = 'Birthdays, weddings, graduations and tributes, read out live on CBS Radio. '
        . 'Order from your phone in a few minutes.';
    public string $announcement  = '';
    public string $liveStreamUrl = '';

    // ----- Admin screens -----
    // Rows per page on paginated admin lists (currently the notifications log).
    public int $adminPageSize = 25;

    // ----- Content moderation assist -----
    // Terms that auto-flag a booking for priority moderator review when
    // they appear in the message text. This is a *flag*, never an
    // auto-reject — a human moderator always makes the actual call,
    // which matters most for tributes where context changes everything.
    //
    // Deliberately left empty here: a hardcoded word list is hard to
    // maintain, English-only by default, and this audience spans Luganda
    // and English. CBS's own editorial/moderation team should own this
    // list (see the Content Guidelines open item in the scope sign-off
    // doc) — populate it via .env (comma-separated, see below) or by
    // overriding this property, rather than editing MessageSanitizer
    // itself. For broader coverage later, consider pairing this with a
    // maintained package (e.g. a profanity-filter library) instead of
    // hand-rolling the list.
    /** @var string[] */
    public array $moderationFlagTerms = [];

    public function __construct()
    {
        parent::__construct();

        $this->messageMaxLength         = (int) env('content.messageMaxLength', $this->messageMaxLength);
        $this->recipientNameMaxLength   = (int) env('content.recipientNameMaxLength', $this->recipientNameMaxLength);

        $this->readingWordsPerMinute = (int) env('content.readingWordsPerMinute', $this->readingWordsPerMinute);

        $this->voiceNoteVerifyWithFfprobe = filter_var(env('uploads.verifyWithFfprobe', $this->voiceNoteVerifyWithFfprobe), FILTER_VALIDATE_BOOLEAN);
        $this->voiceNoteFfprobePath       = (string) env('uploads.ffprobePath', $this->voiceNoteFfprobePath);

        $this->voiceNoteMinSeconds = (int) env('uploads.voiceNoteMinSeconds', $this->voiceNoteMinSeconds);
        $this->voiceNoteMaxSeconds = (int) env('uploads.voiceNoteMaxSeconds', $this->voiceNoteMaxSeconds);
        $this->voiceNoteMaxSizeKB  = (int) env('uploads.voiceNoteMaxSizeKB', $this->voiceNoteMaxSizeKB);

        $this->defaultSlotCapacity = (int) env('scheduling.defaultSlotCapacity', $this->defaultSlotCapacity);
        $this->defaultCurrency     = env('cbsradio.defaultCurrency', $this->defaultCurrency);
        $this->adminPageSize       = max((int) env('admin.pageSize', $this->adminPageSize), 1);

        $flagTermsEnv = env('moderation.flagTerms', '');

        if (! empty($flagTermsEnv)) {
            $this->moderationFlagTerms = array_map('trim', explode(',', $flagTermsEnv));
        }

        $this->contactPhone = (string) env('general.contactPhone', $this->contactPhone);
        $this->contactEmail = (string) env('general.contactEmail', $this->contactEmail);

        $this->heroHeadline    = (string) env('site.heroHeadline', $this->heroHeadline);
        $this->heroSubheadline = (string) env('site.heroSubheadline', $this->heroSubheadline);
        $this->announcement    = (string) env('site.announcement', $this->announcement);
        $this->liveStreamUrl   = (string) env('site.liveStreamUrl', $this->liveStreamUrl);

        // Last: admin settings win over .env, which wins over the defaults above.
        $this->applySettingOverrides('CbsRadio');
    }

    /**
     * Full writable-path for voice note storage, built from the configured
     * subpath — use this everywhere instead of concatenating WRITEPATH by hand.
     */
    public function voiceNoteUploadPath(): string
    {
        return rtrim(WRITEPATH, '/') . '/' . trim($this->voiceNoteUploadSubpath, '/');
    }
}
