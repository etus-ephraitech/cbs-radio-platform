<?php

namespace App\Libraries;

use Config\CbsRadio;

/**
 * Sanitizes and validates user-submitted booking text (message_text,
 * recipient_name), and flags messages containing CBS's own configured
 * moderation terms for priority review. All limits/terms come from
 * Config\CbsRadio — change them there, not here.
 */
class MessageSanitizer
{
    protected CbsRadio $config;

    public function __construct(?CbsRadio $config = null)
    {
        $this->config = $config ?? config('CbsRadio');
    }

    /**
     * Strips tags and normalizes whitespace. Run this on message_text and
     * recipient_name before they ever reach the database.
     */
    public function sanitize(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * @return array{valid: bool, message: string, sanitized: string}
     */
    public function validateMessage(string $text): array
    {
        $sanitized = $this->sanitize($text);

        if ($sanitized === '') {
            return ['valid' => false, 'message' => 'Message cannot be empty.', 'sanitized' => $sanitized];
        }

        // The message editor's "start from an example" fills in names when it can and
        // otherwise leaves one of these markers. Reading one out on air would be
        // embarrassing, so a message that still has one is sent back.
        if (($marker = $this->unfilledPlaceholder($sanitized)) !== null) {
            return [
                'valid'     => false,
                'message'   => "Replace {$marker} with a name before sending.",
                'sanitized' => $sanitized,
            ];
        }

        if (mb_strlen($sanitized) > $this->config->messageMaxLength) {
            return [
                'valid'     => false,
                'message'   => 'Message is too long (max ' . $this->config->messageMaxLength . ' characters).',
                'sanitized' => $sanitized,
            ];
        }

        return ['valid' => true, 'message' => '', 'sanitized' => $sanitized];
    }

    /** The markers the message editor leaves when it could not fill a name in. */
    public const PLACEHOLDERS = ['[their name]', '[your name]'];

    /**
     * The first unfilled marker still in the text, or null.
     */
    public function unfilledPlaceholder(string $text): ?string
    {
        foreach (self::PLACEHOLDERS as $marker) {
            if (stripos($text, $marker) !== false) {
                return $marker;
            }
        }

        return null;
    }

    /**
     * @return array{valid: bool, message: string, sanitized: string}
     */
    public function validateRecipientName(string $text): array
    {
        $sanitized = $this->sanitize($text);

        if ($sanitized === '') {
            return ['valid' => false, 'message' => 'Recipient name cannot be empty.', 'sanitized' => $sanitized];
        }

        if (mb_strlen($sanitized) > $this->config->recipientNameMaxLength) {
            return [
                'valid'     => false,
                'message'   => 'Recipient name is too long (max ' . $this->config->recipientNameMaxLength . ' characters).',
                'sanitized' => $sanitized,
            ];
        }

        return ['valid' => true, 'message' => '', 'sanitized' => $sanitized];
    }

    /**
     * Returns which configured flag terms (if any) appear in the message,
     * for the booking flow to pass into moderation_logs or
     * bookings.moderation_notes as a heads-up. This never blocks
     * submission — it only helps a human moderator prioritize; the
     * decision stays with them, which matters most for tributes where
     * the same word can be perfectly appropriate or not depending on
     * context.
     *
     * @return string[] matched terms, empty if none or no terms configured
     */
    public function flaggedTerms(string $text): array
    {
        if (empty($this->config->moderationFlagTerms)) {
            return [];
        }

        $normalized = mb_strtolower($text);
        $matches    = [];

        foreach ($this->config->moderationFlagTerms as $term) {
            $term = mb_strtolower(trim($term));

            if ($term === '') {
                continue;
            }

            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', $normalized)) {
                $matches[] = $term;
            }
        }

        return $matches;
    }
}
