<?php

namespace App\Libraries;

use CodeIgniter\HTTP\ResponseInterface;
use Config\CbsRadio;
use DateTimeImmutable;
use DateTimeZone;

/**
 * What a visitor sees while the public site is closed: the coming soon page or the maintenance page.
 *
 * The mode, the message and the opening or return time are settings (Admin > Settings > Site status). This class
 * turns them into a response, and works out the few facts a response needs: when the visitor should try again,
 * how the time is shown, and whether the person asking is staff.
 *
 * Two modes, two meanings, so two kinds of response:
 *
 *   maintenance   "Not now": HTTP 503 with a Retry-After, which tells search engines and monitors this is
 *                 temporary and the pages are not gone.
 *   coming_soon   "Not yet": HTTP 200. It is an intentional page, shown before launch.
 *
 * Both are marked noindex and never cached, so neither ends up in a search result or stays on a phone after the
 * site has opened.
 */
class SiteStatusPage
{
    public const LIVE        = 'live';
    public const COMING_SOON = 'coming_soon';
    public const MAINTENANCE = 'maintenance';

    /** Asked to come back in an hour when we do not know better. */
    public const DEFAULT_RETRY_SECONDS = 3600;

    /** Never ask a search engine to stay away for more than a day: a long wait can read as "gone". */
    public const MAX_RETRY_SECONDS = 86400;

    /** Never ask for less than a minute: a tiny value invites a retry storm. */
    public const MIN_RETRY_SECONDS = 60;

    protected CbsRadio $config;

    public function __construct(?CbsRadio $config = null)
    {
        $this->config = $config ?? config('CbsRadio');
    }

    /**
     * True for the two modes that close the public site. An unknown value counts as live: a typo in a setting
     * must never lock visitors out.
     */
    public static function closes(string $mode): bool
    {
        return in_array($mode, [self::COMING_SOON, self::MAINTENANCE], true);
    }

    /**
     * Staff keep the whole site while it is closed to visitors, so they can check it before it opens. A staff
     * member is whoever the admin area treats as signed in (see Admin\BaseAdminController::currentUserId()).
     */
    public function isStaff(): bool
    {
        return ! empty(session()->get('user_id'));
    }

    /**
     * Whether a request from this person should be turned away right now.
     */
    public function turnsAway(): bool
    {
        return self::closes($this->config->siteMode) && ! $this->isStaff();
    }

    /**
     * The opening or return time as a date, in the station's own time zone, or null when none is set or it is
     * not a valid "YYYY-MM-DD HH:MM" (the setting is validated on save, but .env or a hand-edited row is not).
     */
    public function returnAt(): ?DateTimeImmutable
    {
        $raw = trim($this->config->siteReturnAt);

        if ($raw === '') {
            return null;
        }

        $zone = new DateTimeZone(config('App')->appTimezone);
        $at   = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $raw, $zone);

        return $at !== false && $at->format('Y-m-d H:i') === $raw ? $at : null;
    }

    /**
     * Seconds to put in Retry-After. Counts down to the return time when there is a future one; otherwise an hour.
     */
    public function retryAfterSeconds(?DateTimeImmutable $now = null): int
    {
        $at = $this->returnAt();

        if ($at === null) {
            return self::DEFAULT_RETRY_SECONDS;
        }

        $now ??= new DateTimeImmutable('now', $at->getTimezone());
        $left  = $at->getTimestamp() - $now->getTimestamp();

        if ($left <= 0) {
            return self::DEFAULT_RETRY_SECONDS;
        }

        return max(self::MIN_RETRY_SECONDS, min(self::MAX_RETRY_SECONDS, $left));
    }

    /**
     * The wording that belongs to each mode. The visitor's message, when staff have written one, replaces only
     * the body text; the headline and the labels stay so the page always says what is happening.
     *
     * @return array{headline: string, kicker: string, body: string, when: string}
     */
    public static function copy(string $mode): array
    {
        return $mode === self::MAINTENANCE
            ? [
                'headline' => 'Back soon.',
                'kicker'   => 'Under maintenance',
                'body'     => "We're making some improvements to CBS Radio greetings and will be back shortly. Thank you for your patience.",
                'when'     => 'Expected back',
            ]
            : [
                'headline' => 'Coming soon.',
                'kicker'   => 'Opening soon',
                'body'     => "Greetings, tributes and gifts, read out live on CBS Radio. We're getting everything ready.",
                'when'     => 'Opening',
            ];
    }

    /**
     * The page for a mode, as a response.
     *
     * @param bool $preview true when staff are looking at it from the admin area: a normal 200 page with a ribbon,
     *                      and none of the headers meant for search engines
     */
    public function respond(ResponseInterface $response, string $mode, bool $preview = false): ResponseInterface
    {
        $mode = $mode === self::MAINTENANCE ? self::MAINTENANCE : self::COMING_SOON;
        $copy = self::copy($mode);
        $at   = $this->returnAt();
        $now  = new DateTimeImmutable('now', $at?->getTimezone() ?? new DateTimeZone(config('App')->appTimezone));

        $message = trim($this->config->siteMessage);

        $html = view('site_status', [
            'lean'       => true,   // layouts/public: no Bootstrap, no jQuery
            'minimal'    => true,   // layouts/public: no navigation, nothing that links to a closed page
            'noindex'    => true,
            'fullTitle'  => $copy['kicker'] . ' — CBS Radio',
            'mode'       => $mode,
            'copy'       => $copy,
            'message'    => $message !== '' ? $message : $copy['body'],
            'returnText' => $at?->format('l j F Y, g:i a') ?? '',
            'returnIso'  => $at?->format(DATE_ATOM) ?? '',
            'returnPast' => $at !== null && $at <= $now,
            'preview'    => $preview,
            'cbs'        => $this->config,
        ], [
            // CodeIgniter keeps the data given to a view for the views rendered after it. These variables (no
            // navigation, noindex, this page's title) must never end up on another page.
            'saveData' => false,
        ]);

        $response->setBody($html)
            ->setContentType('text/html')
            ->setHeader('Cache-Control', 'no-store, max-age=0');

        if ($preview) {
            return $response;
        }

        $response->setHeader('X-Robots-Tag', 'noindex, nofollow');

        if ($mode === self::MAINTENANCE) {
            $response->setStatusCode(503)->setHeader('Retry-After', (string) $this->retryAfterSeconds($now));
        }

        return $response;
    }
}
