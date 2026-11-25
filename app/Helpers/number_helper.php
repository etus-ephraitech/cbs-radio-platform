<?php

/**
 * Number helpers for the CBS Radio platform.
 * Autoload via app/Config/Autoload.php -> $helpers = ['number', ...]
 * or load on demand with helper('number').
 */

if (! function_exists('cbs_money')) {
    /**
     * Format an amount for display, e.g. 15000 -> "UGX 15,000".
     * Matches the `amount`/`currency` columns on bookings, gift_orders,
     * and payments.
     */
    function cbs_money(float $amount, string $currency = 'UGX'): string
    {
        return $currency . ' ' . number_format($amount, 0);
    }
}

if (! function_exists('cbs_money_decimal')) {
    /**
     * Same as cbs_money() but keeps 2 decimal places — use for anything
     * reconciled against a payment provider's exact figures.
     */
    function cbs_money_decimal(float $amount, string $currency = 'UGX'): string
    {
        return $currency . ' ' . number_format($amount, 2);
    }
}

if (! function_exists('cbs_percentage')) {
    /**
     * Safe percentage of $value against $total — returns 0 instead of
     * dividing by zero (e.g. slot capacity before any bookings exist).
     */
    function cbs_percentage(int|float $value, int|float $total, int $decimals = 0): string
    {
        if ($total <= 0) {
            return '0%';
        }

        return number_format(($value / $total) * 100, $decimals) . '%';
    }
}

if (! function_exists('cbs_duration')) {
    /**
     * Format a voice note's duration in seconds as "m:ss", e.g. 23 -> "0:23".
     * Matches bookings.voice_note_duration_seconds.
     */
    function cbs_duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $remaining);
    }
}

if (! function_exists('cbs_slot_remaining')) {
    /**
     * Remaining bookable spots in a show slot, never negative.
     * Matches show_slots.capacity / show_slots.booked_count.
     */
    function cbs_slot_remaining(int $capacity, int $bookedCount): int
    {
        return max($capacity - $bookedCount, 0);
    }
}
