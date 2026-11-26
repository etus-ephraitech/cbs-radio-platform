<?php

namespace App\Libraries;

use CodeIgniter\HTTP\IncomingRequest;

/**
 * Reads the filters of an admin list from the query string. Everything in a query string is untrusted and ends up
 * in a WHERE clause, so each value is checked against what the list allows: a choice must be one of its options, a
 * date a real calendar date, and free text is trimmed and capped. Anything else is dropped, and the list shows
 * everything for that filter rather than an error.
 *
 * A spec is field name => 'search' | 'date' | list of allowed values. For example:
 *
 *     AdminFilters::read($request, ['q' => 'search', 'status' => ['pending', 'paid'], 'from' => 'date']);
 */
final class AdminFilters
{
    public const SEARCH_MAX = 100;

    /**
     * @param array<string, string|list<string>> $spec
     *
     * @return array<string, string> every field of the spec, '' when absent or not allowed
     */
    public static function read(IncomingRequest $request, array $spec): array
    {
        $out = [];

        foreach ($spec as $name => $rule) {
            $raw = $request->getGet($name);
            $raw = is_string($raw) ? trim($raw) : '';

            $out[$name] = match (true) {
                $rule === 'search' => mb_substr($raw, 0, self::SEARCH_MAX),
                $rule === 'date'   => self::validDate($raw) ? $raw : '',
                is_array($rule)    => in_array($raw, $rule, true) ? $raw : '',
                default            => '',
            };
        }

        return $out;
    }

    /**
     * Whether a "to" date is not before a "from" date; when it is, the pair is swapped, so a mistyped range still
     * shows something sensible.
     *
     * @param array<string, string> $filters
     *
     * @return array<string, string>
     */
    public static function orderedRange(array $filters, string $from = 'from', string $to = 'to'): array
    {
        if (($filters[$from] ?? '') !== '' && ($filters[$to] ?? '') !== '' && $filters[$from] > $filters[$to]) {
            [$filters[$from], $filters[$to]] = [$filters[$to], $filters[$from]];
        }

        return $filters;
    }

    /**
     * "YYYY-MM-DD" that exists on the calendar.
     */
    public static function validDate(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * The page to show, kept inside the list.
     */
    public static function page(IncomingRequest $request, int $total, int $perPage): array
    {
        $totalPages = max((int) ceil($total / max($perPage, 1)), 1);
        $page       = min(max((int) $request->getGet('page'), 1), $totalPages);

        return [$page, $totalPages];
    }
}
