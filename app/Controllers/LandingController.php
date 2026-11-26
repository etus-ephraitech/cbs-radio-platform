<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Libraries\GiftCart;
use App\Models\GiftModel;
use App\Models\GiftVendorModel;
use App\Models\RateTierModel;
use App\Models\ShowModel;

/**
 * The public homepage. Every figure and name on it comes from the database
 * or from Admin > Settings — occasions, packages and prices, the show
 * schedule, gifts, contact details, headline, announcement, live stream —
 * so it can never advertise something the booking pages would refuse, and
 * staff change it without a deploy. A section with nothing to show (no
 * gifts yet, no shows yet) is left out rather than shown empty.
 */
class LandingController extends BaseController
{
    private const DAY_NAMES = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    private const GIFT_TYPE_LABELS = [
        'ecard'      => 'E-card',
        'airtime'    => 'Airtime',
        'shoutout'   => 'Shout-out',
        'dedication' => 'Song dedication',
        'physical'   => 'Delivered gift',
    ];

    public function index()
    {
        $cbs   = config('CbsRadio');
        $shows = $this->showsWithStatus();

        return ui_view('landing', [
            'lean'            => true,
            'fullTitle'       => 'CBS Radio — send a greeting or tribute to be read on air',
            'metaDescription' => $cbs->heroSubheadline,
            'cbs'             => $cbs,
            'payments'        => config('Payments'),
            'smsOn'           => (bool) config('Notifications')->channelsEnabled['sms'],
            'occasions'       => $this->occasions(),
            'shows'           => $shows,
            'sampleShow'      => $shows[0]['name'] ?? 'our morning show',
            'gifts'           => $this->featuredGifts(),
            'cartCount'       => (new GiftCart())->count(),
            'giftTypes'       => self::GIFT_TYPE_LABELS,
        ]);
    }

    /**
     * Active categories that have at least one bookable package, each with
     * its packages cheapest first. A category with no active package is
     * left off: the booking page would only tell the visitor there is
     * nothing to choose.
     *
     * @return array<int, array{id: int, name: string, slug: string, description: string, packages: array, isTribute: bool}>
     */
    protected function occasions(): array
    {
        $packages = [];

        foreach ((new RateTierModel())->activePricing() as $tier) {
            $packages[(int) $tier['category_id']][] = $tier;
        }

        $occasions = [];

        foreach ((new CategoryModel())->active() as $category) {
            $id = (int) $category['id'];

            if (empty($packages[$id])) {
                continue;
            }

            $occasions[] = [
                'id'          => $id,
                'name'        => $category['name'],
                'slug'        => $category['slug'],
                'description' => (string) ($category['description'] ?? ''),
                'packages'    => $packages[$id],
                'isTribute'   => (bool) preg_match('/tribute|memoriam|memorial/i', $category['slug'] . ' ' . $category['name']),
            ];
        }

        return $occasions;
    }

    /**
     * Active shows with display-ready times and days, and whether each is on
     * the air right now (in the site's own timezone — see app.appTimezone).
     *
     * @return array<int, array{name: string, times: string, days: string, onAirNow: bool}>
     */
    protected function showsWithStatus(): array
    {
        $showModel = new ShowModel();
        $today     = (int) date('N');
        $now       = date('H:i:s');
        $result    = [];

        foreach ($showModel->active() as $show) {
            $start = $show['start_time'];
            $end   = $show['end_time'];

            // A show that runs past midnight (e.g. 22:00 to 02:00) is on
            // either side of midnight; an ordinary one is between start and end.
            $inWindow = $end > $start ? ($now >= $start && $now < $end) : ($now >= $start || $now < $end);

            $result[] = [
                'name'     => $show['name'],
                'times'    => date('g:i a', strtotime($start)) . ' to ' . date('g:i a', strtotime($end)),
                'days'     => $this->describeDays($show['days_of_week']),
                'onAirNow' => $showModel->runsOn($show, $today) && $inWindow,
            ];
        }

        return $result;
    }

    /**
     * "Every day", "Weekdays", "Weekends", or a compact list such as
     * "Mon to Wed, Sat" built from the comma-separated day numbers.
     */
    protected function describeDays(?string $csv): string
    {
        if ($csv === null || $csv === '') {
            return 'Every day';
        }

        $days = array_values(array_unique(array_map('intval', explode(',', $csv))));
        sort($days);

        if ($days === [1, 2, 3, 4, 5, 6, 7]) {
            return 'Every day';
        }

        if ($days === [1, 2, 3, 4, 5]) {
            return 'Weekdays';
        }

        if ($days === [6, 7]) {
            return 'Weekends';
        }

        $groups = [];
        $start  = $previous = null;

        foreach ($days as $day) {
            if ($start === null) {
                $start = $previous = $day;
            } elseif ($day === $previous + 1) {
                $previous = $day;
            } else {
                $groups[] = [$start, $previous];
                $start    = $previous = $day;
            }
        }

        $groups[] = [$start, $previous];

        return implode(', ', array_map(function (array $g): string {
            [$a, $b] = $g;

            return match (true) {
                $a === $b      => self::DAY_NAMES[$a],
                $b === $a + 1  => self::DAY_NAMES[$a] . ', ' . self::DAY_NAMES[$b],
                default        => self::DAY_NAMES[$a] . ' to ' . self::DAY_NAMES[$b],
            };
        }, $groups));
    }

    /**
     * Up to eight gifts for the homepage shelf: a mix of gifts that can be added to a greeting (ours first, then
     * other vendors') and gifts delivered to the door by a hosted-checkout partner (Katale), which are bought with
     * the gift cart. Each carries 'hosted' so the page knows which button to show. A delivered gift that is out of
     * stock is left off, so the shelf never offers what the cart would refuse.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function featuredGifts(): array
    {
        $own = $hosted = [];

        foreach ((new GiftModel())->active() as $gift) {
            $isHosted = $gift['source'] === GiftModel::SOURCE_VENDOR
                && in_array($gift['vendor_driver'] ?? '', GiftVendorModel::HOSTED_DRIVERS, true);

            if ($isHosted) {
                if (isset($gift['stock_available']) && (int) $gift['stock_available'] === 0) {
                    continue;
                }

                $hosted[] = $gift + ['hosted' => true];
            } else {
                $own[] = $gift + ['hosted' => false];
            }
        }

        usort($own, static fn (array $a, array $b) => [$a['source'] === 'local' ? 0 : 1, (float) $a['price']]
            <=> [$b['source'] === 'local' ? 0 : 1, (float) $b['price']]);
        usort($hosted, static fn (array $a, array $b) => (float) $a['price'] <=> (float) $b['price']);

        // Half the shelf each when both kinds exist; whichever kind is short leaves its room to the other.
        $hostedShown = min(count($hosted), max(4, 8 - count($own)));
        $ownShown    = min(count($own), 8 - $hostedShown);

        return array_merge(array_slice($own, 0, $ownShown), array_slice($hosted, 0, $hostedShown));
    }
}
