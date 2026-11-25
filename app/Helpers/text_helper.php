<?php

/**
 * Text helpers for the CBS Radio platform.
 * Autoload via app/Config/Autoload.php -> $helpers = ['text', ...]
 * or load on demand with helper('text').
 */

if (! function_exists('cbs_excerpt')) {
    /**
     * Safely truncate a booking message/tribute for list/table display
     * without cutting a word in half.
     */
    function cbs_excerpt(string $text, int $limit = 100, string $suffix = '…'): string
    {
        $text = trim(strip_tags($text));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $limit);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated . $suffix;
    }
}

if (! function_exists('cbs_slug')) {
    /**
     * Generate a URL-safe slug for category names
     * (e.g. "Milestones & Achievements" -> "milestones-achievements").
     */
    function cbs_slug(string $string): string
    {
        $slug = strtolower(trim($string));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }
}

if (! function_exists('cbs_mask_phone')) {
    /**
     * Mask a phone number for display in admin lists/logs, e.g.
     * "0772123456" -> "0772***456". Keeps enough visible to spot
     * duplicates/typos without exposing the full number.
     */
    function cbs_mask_phone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $length = strlen($digits);

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        $visibleStart = substr($digits, 0, 4);
        $visibleEnd   = substr($digits, -3);
        $maskedMiddle = str_repeat('*', $length - 7);

        return $visibleStart . $maskedMiddle . $visibleEnd;
    }
}

if (! function_exists('cbs_mask_email')) {
    /**
     * Mask an email for display, e.g. "jane.doe@gmail.com" -> "ja***@gmail.com".
     */
    function cbs_mask_email(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, 2);

        return $visible . str_repeat('*', max(mb_strlen($local) - 2, 3)) . '@' . $domain;
    }
}

if (! function_exists('cbs_booking_reference')) {
    /**
     * Human-readable reference code for a booking, used in SMS confirmations,
     * receipts, and the presenter/admin UI (e.g. "CBS-000123").
     */
    function cbs_booking_reference(int $bookingId): string
    {
        return 'CBS-' . str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('cbs_gift_reference')) {
    /**
     * Human-readable reference code for a standalone gift order (e.g. "GFT-000045").
     */
    function cbs_gift_reference(int $giftOrderId): string
    {
        return 'GFT-' . str_pad((string) $giftOrderId, 6, '0', STR_PAD_LEFT);
    }
}
