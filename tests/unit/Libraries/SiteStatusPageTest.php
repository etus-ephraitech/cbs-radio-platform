<?php

namespace Tests\Unit\Libraries;

use App\Libraries\SiteStatusPage;
use Config\CbsRadio;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\UnitTestCase;

/**
 * The facts behind the coming soon and maintenance pages: which modes close the site, how the return time is read
 * and shown to the second, and what a search engine is told about when to come back. (The pages themselves, the
 * headers and who is let through are tested as whole requests in tests/feature/SiteStatusTest.php.)
 *
 * @internal
 */
final class SiteStatusPageTest extends UnitTestCase
{
    private function zone(): DateTimeZone
    {
        return new DateTimeZone(config('App')->appTimezone);
    }

    private function page(string $returnAt = '', string $mode = 'maintenance', string $message = ''): SiteStatusPage
    {
        $config               = new CbsRadio();
        $config->siteMode     = $mode;
        $config->siteReturnAt = $returnAt;
        $config->siteMessage  = $message;

        return new SiteStatusPage($config);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, $this->zone());
    }

    // ------------------------------------------------------------------ which modes close the site

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function modes(): array
    {
        return [
            'live'                          => ['live', false],
            'coming soon'                   => ['coming_soon', true],
            'under maintenance'             => ['maintenance', true],
            // A typo in a setting, or an old value, must never lock visitors out of a working site.
            'something mistyped'            => ['maintainance', false],
            'empty'                         => ['', false],
            'wrong case'                    => ['Maintenance', false],
            'with a stray space'            => ['maintenance ', false],
        ];
    }

    #[DataProvider('modes')]
    public function testOnlyTheTwoClosedModesCloseTheSite(string $mode, bool $closes): void
    {
        $this->assertSame($closes, SiteStatusPage::closes($mode));
    }

    public function testTheModeNamesAreTheOnesTheSettingStores(): void
    {
        $this->assertSame('live', SiteStatusPage::LIVE);
        $this->assertSame('coming_soon', SiteStatusPage::COMING_SOON);
        $this->assertSame('maintenance', SiteStatusPage::MAINTENANCE);
    }

    // ------------------------------------------------------------------ reading the return time

    public function testNoReturnTimeIsNoReturnTime(): void
    {
        $this->assertNull($this->page('')->returnAt());
        $this->assertNull($this->page('   ')->returnAt());
    }

    public function testAReturnTimeIsReadInTheStationsOwnTimeZone(): void
    {
        $at = $this->page('2026-12-01 09:30')->returnAt();

        $this->assertNotNull($at);
        $this->assertSame('2026-12-01 09:30', $at->format('Y-m-d H:i'));
        $this->assertSame(config('App')->appTimezone, $at->getTimezone()->getName(), 'Staff type local time; it must not be read as UTC.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function notReturnTimes(): array
    {
        return [
            'a date that does not exist' => ['2026-02-30 10:00'],
            'hour 25'                    => ['2026-12-01 25:00'],
            'no time'                    => ['2026-12-01'],
            'words'                      => ['tomorrow'],
            'the picker\'s own form'     => ['2026-12-01T09:00'],
            'with seconds'               => ['2026-12-01 09:00:00'],
        ];
    }

    #[DataProvider('notReturnTimes')]
    public function testAnythingElseIsIgnoredNotGuessedAt(string $value): void
    {
        // The setting is validated when saved, but a hand-edited row or an old value is not. A bad one must
        // simply not be shown, never turned into some other moment.
        $this->assertNull($this->page($value)->returnAt());
    }

    // ------------------------------------------------------------------ what a search engine is told

    public function testWithNoReturnTimeVisitorsAreToldToTryAgainInAnHour(): void
    {
        $this->assertSame(3600, $this->page('')->retryAfterSeconds($this->at('2026-12-01 08:00')));
    }

    public function testItCountsDownToAFutureReturnTime(): void
    {
        $page = $this->page('2026-12-01 10:00');

        $this->assertSame(7200, $page->retryAfterSeconds($this->at('2026-12-01 08:00')));
        $this->assertSame(1800, $page->retryAfterSeconds($this->at('2026-12-01 09:30')));
    }

    public function testAReturnTimeThatHasPassedFallsBackToAnHour(): void
    {
        $page = $this->page('2026-12-01 10:00');

        $this->assertSame(3600, $page->retryAfterSeconds($this->at('2026-12-01 10:00')), 'Exactly now is already past.');
        $this->assertSame(3600, $page->retryAfterSeconds($this->at('2026-12-02 10:00')));
    }

    public function testItNeverAsksForLessThanAMinute(): void
    {
        $this->assertSame(60, $this->page('2026-12-01 10:00')->retryAfterSeconds($this->at('2026-12-01 09:59:50')));
    }

    public function testItNeverAsksASearchEngineToStayAwayForMoreThanADay(): void
    {
        // A very long wait can look like "gone". A later check will find the page still closed and ask again.
        $this->assertSame(86400, $this->page('2027-06-01 10:00')->retryAfterSeconds($this->at('2026-12-01 08:00')));
    }

    public function testADayLightSavingChangeCannotMakeItNegativeOrHuge(): void
    {
        $seconds = $this->page('2026-12-01 10:00')->retryAfterSeconds($this->at('2026-11-30 10:00'));

        $this->assertGreaterThanOrEqual(60, $seconds);
        $this->assertLessThanOrEqual(86400, $seconds);
    }

    // ------------------------------------------------------------------ the words

    public function testEachModeHasItsOwnWording(): void
    {
        $maintenance = SiteStatusPage::copy('maintenance');
        $comingSoon  = SiteStatusPage::copy('coming_soon');

        $this->assertSame('Back soon.', $maintenance['headline']);
        $this->assertSame('Expected back', $maintenance['when']);
        $this->assertSame('Coming soon.', $comingSoon['headline']);
        $this->assertSame('Opening', $comingSoon['when']);

        foreach ([$maintenance, $comingSoon] as $copy) {
            $this->assertNotSame('', $copy['kicker']);
            $this->assertNotSame('', $copy['body']);
        }
    }

    public function testAnUnknownModeGetsTheComingSoonWordingNeverBlank(): void
    {
        $this->assertSame(SiteStatusPage::copy('coming_soon'), SiteStatusPage::copy('whatever'));
    }

    public function testTheStandardWordingMakesNoPromisesAboutTimeOrMoney(): void
    {
        foreach (['maintenance', 'coming_soon'] as $mode) {
            $body = SiteStatusPage::copy($mode)['body'];

            $this->assertDoesNotMatchRegularExpression('/\b(minutes?|hours?|today|tomorrow|free|refund|guarantee)\b/i', $body, 'A standard message cannot know any of these.');
        }
    }
}
