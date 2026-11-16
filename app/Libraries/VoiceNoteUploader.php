<?php

namespace App\Libraries;

use Config\CbsRadio;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Validates and stores a booking's optional voice note upload. All limits
 * come from Config\CbsRadio — change them there, not here.
 *
 * KNOWN LIMITATION: true server-side audio duration detection requires an
 * extension this project doesn't currently depend on (e.g. getID3, or
 * shelling out to ffprobe). Until one is added, duration is taken from
 * $clientReportedSeconds — the browser's MediaRecorder API reports this
 * accurately for a freshly-recorded clip, but a tampered or re-uploaded
 * file could misreport it. For Phase 1 this is an acceptable trade-off
 * given the low stakes (worst case: a slightly-too-long clip reaches
 * moderation, where a human reviews it anyway); revisit if abuse shows up.
 */
class VoiceNoteUploader
{
    protected CbsRadio $config;
    protected FileManager $fileManager;

    public function __construct(?CbsRadio $config = null, ?FileManager $fileManager = null)
    {
        $this->config      = $config ?? config('CbsRadio');
        $this->fileManager = $fileManager ?? new FileManager();
    }

    /**
     * Checks an uploaded voice note and, when it passes, says how long it
     * really is.
     *
     * Checked in order: the upload itself; the file type (by what PHP
     * detects, not by what the browser claims); the file name's extension;
     * the size; and the length. When the server has ffprobe, the file is
     * also opened to confirm it is a playable audio recording and to measure
     * its true length, so the browser's reported length is only a fallback
     * for servers without it.
     *
     * 'duration' is whole seconds — measured when 'verified' is true,
     * otherwise whatever the browser reported (or null if it reported none).
     *
     * @return array{valid: bool, message: string, duration: ?int, verified: bool}
     */
    public function validate(UploadedFile $file, ?int $clientReportedSeconds): array
    {
        $fail = static fn(string $message): array => ['valid' => false, 'message' => $message, 'duration' => null, 'verified' => false];

        // A file that has already been moved is no longer an "uploaded" file, so this has to be asked first:
        // asked second, isValid() would already have said no and this message could never be reached.
        if ($file->hasMoved()) {
            return $fail('This file has already been processed.');
        }

        if (! $file->isValid()) {
            return $fail(match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Your voice note is larger than this server accepts. Try a shorter recording.',
                UPLOAD_ERR_PARTIAL                        => "Your voice note didn't finish uploading. Check your connection and try again.",
                default                                   => "The voice note didn't upload properly. Please record it again.",
            });
        }

        if (! in_array($file->getMimeType(), $this->config->voiceNoteAllowedMimeTypes, true)) {
            return $fail("That file isn't a type of audio we can use. Record it again, or choose an " . $this->describeFormats() . ' file.');
        }

        $extension = strtolower($file->getClientExtension());

        if (! in_array($extension, $this->config->voiceNoteAllowedExtensions, true)) {
            return $fail("That file name doesn't look like an audio recording. Choose an " . $this->describeFormats() . ' file.');
        }

        $sizeKB = $file->getSize() / 1024;

        if ($sizeKB > $this->config->voiceNoteMaxSizeKB) {
            return $fail('Your voice note is ' . $this->describeSize($sizeKB) . '. The largest allowed is '
                . $this->describeSize($this->config->voiceNoteMaxSizeKB) . '. Try a shorter recording.');
        }

        $seconds  = $clientReportedSeconds !== null ? (float) $clientReportedSeconds : null;
        $verified = false;
        $inspect  = $this->inspect($file->getRealPath());

        if ($inspect !== null) {
            if (! $inspect['audio']) {
                return $fail("That file doesn't contain a playable audio recording.");
            }

            if ($inspect['video']) {
                return $fail('That looks like a video, not a voice note. Please send an audio recording.');
            }

            if ($inspect['duration'] !== null) {
                $seconds  = $inspect['duration'];
                $verified = true;
            }
        }

        if ($seconds !== null) {
            // The recorder's timer and the finished file can differ by a
            // fraction of a second, so a hair under the minimum or over the
            // maximum is let through rather than rejecting a correct recording.
            $tolerance = 0.75;
            $shown     = (int) round($seconds);

            if ($seconds < $this->config->voiceNoteMinSeconds - $tolerance) {
                return $fail("Your voice note is {$shown} second" . ($shown === 1 ? '' : 's') . ' long. It needs to be at least '
                    . $this->config->voiceNoteMinSeconds . ' seconds.');
            }

            if ($seconds > $this->config->voiceNoteMaxSeconds + $tolerance) {
                return $fail("Your voice note is {$shown} seconds long. The longest allowed is "
                    . $this->config->voiceNoteMaxSeconds . ' seconds.');
            }
        }

        return [
            'valid'    => true,
            'message'  => '',
            'duration' => $seconds !== null ? (int) round($seconds) : null,
            'verified' => $verified,
        ];
    }

    /**
     * Opens the file with ffprobe, if this server has it, and reports whether
     * it holds audio, whether it holds (non-cover-art) video, and how long
     * it is. Returns null when ffprobe can't be used — not installed, not
     * allowed to run programs, or switched off — in which case the caller
     * falls back to the length the browser reported.
     *
     * Browsers' WebM recordings carry no duration in the header, so when the
     * header has none the length is taken from the last audio packet.
     *
     * @return array{audio: bool, video: bool, duration: ?float}|null
     */
    protected function inspect(string $path): ?array
    {
        if (! $this->config->voiceNoteVerifyWithFfprobe || ! $this->ffprobeUsable()) {
            return null;
        }

        $bin = escapeshellcmd($this->config->voiceNoteFfprobePath);
        $arg = escapeshellarg($path);

        exec("timeout 10 {$bin} -v error -show_entries stream=codec_type:stream_disposition=attached_pic:format=duration -of json {$arg} 2>/dev/null", $lines, $code);

        if ($code === 124 || $code === 127) {
            return null; // timed out, or `timeout`/ffprobe isn't there after all: can't tell
        }

        $data = json_decode(implode("\n", $lines), true);

        if ($code !== 0 || ! is_array($data)) {
            return ['audio' => false, 'video' => false, 'duration' => null]; // ffprobe could not read it as media at all
        }

        $audio = false;
        $video = false;

        foreach ($data['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'audio') {
                $audio = true;
            } elseif (($stream['codec_type'] ?? '') === 'video' && empty($stream['disposition']['attached_pic'])) {
                $video = true; // an attached picture (album art) is not a video
            }
        }

        $duration = isset($data['format']['duration']) && is_numeric($data['format']['duration'])
            ? (float) $data['format']['duration']
            : null;

        if ($duration === null && $audio) {
            $lines = [];
            exec("timeout 10 {$bin} -v error -select_streams a:0 -show_entries packet=pts_time,duration_time -of csv=p=0 {$arg} 2>/dev/null", $lines);

            $last = null;

            foreach (array_reverse($lines) as $line) {
                $parts = explode(',', trim($line));

                if (is_numeric($parts[0])) { // explode() always returns at least one element
                    $last = (float) $parts[0] + (isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : 0.0);
                    break;
                }
            }

            $duration = $last;
        }

        return ['audio' => $audio, 'video' => $video, 'duration' => $duration];
    }

    /**
     * Whether we may run ffprobe here: running programs is allowed and the binary exists. Worked out once per
     * configured path (not once per process), so changing voiceNoteFfprobePath takes effect.
     */
    protected function ffprobeUsable(): bool
    {
        static $known = [];

        $path = $this->config->voiceNoteFfprobePath;

        if (array_key_exists($path, $known)) {
            return $known[$path];
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        if (! function_exists('exec') || in_array('exec', $disabled, true)) {
            return $known[$path] = false;
        }

        exec('command -v ' . escapeshellarg($path) . ' 2>/dev/null', $out, $code);

        return $known[$path] = ($code === 0);
    }

    /** "MP3, M4A and OGG"-style list of the formats configured as acceptable, so every message agrees with the settings. */
    protected function describeFormats(): string
    {
        $list = array_map('strtoupper', $this->config->voiceNoteAllowedExtensions);
        $last = array_pop($list);

        return $list === [] ? (string) $last : implode(', ', $list) . ' or ' . $last;
    }

    protected function describeSize(float $kb): string
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.') . ' MB' : (int) round($kb) . ' KB';
    }

    /**
     * Moves a validated upload into the configured voice-notes directory
     * under a randomly generated name, and returns the relative path to
     * store on the booking row. Delegates the actual filesystem work to
     * FileManager so storage logic isn't duplicated per upload type.
     *
     * Call validate() first — this method does not re-check limits.
     */
    public function store(UploadedFile $file): string
    {
        return $this->fileManager->store($file, $this->config->voiceNoteUploadSubpath);
    }

    /**
     * Deletes a stored voice note — e.g. when a booking is rejected or
     * cancelled and its attachment should be cleaned up.
     */
    public function delete(string $relativePath): bool
    {
        return $this->fileManager->delete($relativePath);
    }
}
