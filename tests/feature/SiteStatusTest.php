<?php

namespace Tests\Feature;

use App\Models\BookingModel;
use App\Models\SettingChangeModel;
use App\Models\SettingModel;
use CodeIgniter\Test\TestResponse;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\StaffUser;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * The coming soon and maintenance modes (Admin > Settings > Site status), as whole requests: what a visitor is
 * shown on every public route, what a search engine is told, who is let through, what must keep working while the
 * site is closed, and how staff switch it, preview it and see that it is on.
 *
 * @internal
 */
final class SiteStatusTest extends AppFeatureTestCase
{
    use SetsEnvironment;

    /** @var array<string, mixed> */
    private array $booking;

    /** @var array<string, mixed> */
    private array $giftOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->silenceNotifications();
        config('Payments')->mode = 'manual';

        $category = $this->makeCategory(['slug' => 'birthdays', 'name' => 'Birthdays']);
        $this->makeTier($category['id']);
        $this->booking   = $this->makeBooking(['category_id' => $category['id']]);
        $this->giftOrder = $this->makeGiftOrder();
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param array<string, string> $more other status.* settings, by their short name
     */
    private function close(string $mode, array $more = []): void
    {
        $this->setSetting('status.mode', $mode);

        foreach ($more as $name => $value) {
            $this->setSetting('status.' . $name, $value);
        }
    }

    private function inStationTime(string $modifier): string
    {
        return (new DateTimeImmutable($modifier, new DateTimeZone(config('App')->appTimezone)))->format('Y-m-d H:i');
    }

    private function visible(string $html): string
    {
        $html = preg_replace('#<(style|script|noscript|svg)\b.*?</\1>#s', '', $html);

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    private function url(string $template): string
    {
        return str_replace(['{booking}', '{gift}'], [(string) $this->booking['id'], (string) $this->giftOrder['id']], $template);
    }

    /**
     * Something signed in, on a public page, which is all that tells staff from a visitor.
     */
    private function asStaff(): static
    {
        return $this->withSession(['user_id' => StaffUser::id()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingForm(): array
    {
        return [
            'category_id'    => $this->booking['category_id'],
            'rate_tier_id'   => $this->db->table('rate_tiers')->get()->getRowArray()['id'],
            'sender_name'    => 'Grace Nakato',
            'sender_phone'   => '0772123456',
            'recipient_name' => 'Mama Ruth',
            'message_text'   => 'Happy birthday',
        ];
    }

    private function bookingCount(): int
    {
        return $this->db->table('bookings')->countAllResults();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function publicPages(): array
    {
        return [
            'the homepage'              => ['/'],
            'the list of occasions'     => ['booking'],
            'a booking form'            => ['booking/birthdays'],
            'a booking payment page'    => ['booking/{booking}/pay'],
            'a booking status page'     => ['booking/{booking}/confirmation'],
            'the gift catalogue'        => ['gifts'],
            'a gift payment page'       => ['gifts/{gift}/pay'],
            'a gift status page'        => ['gifts/{gift}/confirmation'],
            'the gift cart'             => ['gifts/cart'],
            'a partner shop order page' => ['gifts/checkout/0123456789abcdef0123456789abcdef'],
        ];
    }

    // ================================================================== while the site is live

    #[DataProvider('publicPages')]
    public function testALiveSiteIsUntouched(string $page): void
    {
        $result = $this->get($this->url($page));

        $this->assertTrue($result->response()->getStatusCode() < 400, "{$page} should open normally.");
        $this->assertSame('', $result->response()->getHeaderLine('X-Robots-Tag'));
        $this->assertSame('', $result->response()->getHeaderLine('Retry-After'));
        $this->assertStringNotContainsString('Under maintenance', (string) $result->response()->getBody());
    }

    public function testNoStaffReminderIsShownWhileTheSiteIsLive(): void
    {
        $this->assertStringNotContainsString('Staff view: the site is', $this->asStaff()->get('/')->getBody());
    }

    public function testAnUnrecognisedStatusIsTreatedAsLiveNotAsClosed(): void
    {
        foreach (['maintainance', 'closed', 'Maintenance', ''] as $mistyped) {
            $this->db->table('settings')->truncate();
            (new SettingModel())->setOverride('status.mode', $mistyped, null);
            $this->reloadConfig();

            $this->get('/')->assertOK();
        }
    }

    // ================================================================== under maintenance

    #[DataProvider('publicPages')]
    public function testEveryPublicPageAnswersUnavailableWhileUnderMaintenance(string $page): void
    {
        $this->close('maintenance');

        $result = $this->get($this->url($page));

        $result->assertStatus(503);
        $this->assertStringContainsString('Back soon.', (string) $result->response()->getBody());
    }

    public function testASearchEngineIsToldItIsTemporaryAndNotToKeepIt(): void
    {
        $this->close('maintenance');

        $response = $this->get('/')->response();

        $this->assertSame(503, $response->getStatusCode(), 'Not 200 and not 404: "unavailable for now" is what keeps the pages in the index.');
        $this->assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesRegularExpression('/^\d+$/', $response->getHeaderLine('Retry-After'));
    }

    public function testRetryAfterCountsDownToTheReturnTime(): void
    {
        $this->close('maintenance', ['returnAt' => $this->inStationTime('+2 hours')]);

        $seconds = (int) $this->get('/')->response()->getHeaderLine('Retry-After');

        $this->assertGreaterThan(7200 - 120, $seconds);
        $this->assertLessThanOrEqual(7200, $seconds);
    }

    public function testWithNoReturnTimeRetryAfterIsAnHour(): void
    {
        $this->close('maintenance');

        $this->assertSame('3600', $this->get('/')->response()->getHeaderLine('Retry-After'));
    }

    public function testNothingCanBeSubmittedWhileUnderMaintenance(): void
    {
        $this->close('maintenance');
        $before = $this->bookingCount();

        $this->post('booking', $this->bookingForm())->assertStatus(503);
        $this->post('gifts', ['gift_id' => 1, 'sender_name' => 'x', 'sender_phone' => '0772123456', 'recipient_name' => 'y'])->assertStatus(503);
        $this->post('gifts/cart/add', ['gift_id' => 1, 'quantity' => 1])->assertStatus(503);
        $this->post('gifts/cart/update', ['quantities' => [1 => 2]])->assertStatus(503);
        $this->post('gifts/cart/remove/1')->assertStatus(503);
        $this->post('gifts/checkout')->assertStatus(503);

        $this->assertSame($before, $this->bookingCount(), 'A blocked booking must not be created behind the notice.');
        $this->assertSame(1, $this->db->table('gift_orders')->countAllResults());
    }

    public function testThePageSaysWhatIsHappeningInThePlainStandardWording(): void
    {
        $this->close('maintenance');

        $visible = $this->visible($this->get('/')->getBody());

        foreach (['Under maintenance', 'Back soon.', "We're making some improvements to CBS Radio greetings and will be back shortly."] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
    }

    public function testStaffCanReplaceTheStandardWordingWithTheirOwn(): void
    {
        $this->close('maintenance', ['message' => "We are upgrading the payment system.\nSorry for the wait."]);

        $body = $this->get('/')->getBody();

        $this->assertStringContainsString('We are upgrading the payment system.', $body);
        $this->assertStringContainsString('<br', $body, 'Line breaks in the message are kept.');
        $this->assertStringContainsString('Back soon.', $body, 'The headline stays, so the page always says what is happening.');
        $this->assertStringNotContainsString("We're making some improvements", $body);
    }

    public function testWhatStaffTypeIsEscaped(): void
    {
        $this->close('maintenance', ['message' => '<script>alert(1)</script><img src=x onerror=alert(1)>']);

        $body = $this->get('/')->getBody();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function testTheReturnTimeIsWrittenOutInFull(): void
    {
        $this->close('maintenance', ['returnAt' => '2030-01-01 09:05']);

        $visible = $this->visible($this->get('/')->getBody());

        $this->assertStringContainsString('Expected back', $visible);
        $this->assertStringContainsString('Tuesday 1 January 2030, 9:05 am', $visible);
    }

    public function testAReturnTimeThatHasPassedIsNotShownAsIfItWereAhead(): void
    {
        $this->close('maintenance', ['returnAt' => '2020-01-01 09:00']);

        $visible = $this->visible($this->get('/')->getBody());

        $this->assertStringContainsString('Taking a little longer than planned', $visible);
        $this->assertStringNotContainsString('2020', $visible);
    }

    public function testMaintenanceHasNoCountdownOnlyComingSoonDoes(): void
    {
        $this->close('maintenance', ['returnAt' => $this->inStationTime('+3 days')]);

        $this->assertStringNotContainsString('id="countdown"', $this->get('/')->getBody());
    }

    public function testABrokenReturnTimeInTheDatabaseIsIgnoredNotShown(): void
    {
        // Not reachable through the screen (it validates), but a hand-edited row must not break the page.
        $this->close('maintenance');
        (new SettingModel())->setOverride('status.returnAt', 'garbage', null);
        $this->reloadConfig();

        $result = $this->get('/');

        $result->assertStatus(503);
        $this->assertSame('3600', $result->response()->getHeaderLine('Retry-After'));
        $this->assertStringNotContainsString('garbage', $result->getBody());
        $this->assertStringNotContainsString('Expected back', $this->visible($result->getBody()));
    }

    // ================================================================== coming soon

    #[DataProvider('publicPages')]
    public function testEveryPublicPageShowsTheComingSoonPageBeforeLaunch(string $page): void
    {
        $this->close('coming_soon');

        $result = $this->get($this->url($page));

        $result->assertOK();
        $this->assertStringContainsString('Coming soon.', (string) $result->response()->getBody());
    }

    public function testComingSoonIsAnIntentionalPageNotAnOutage(): void
    {
        $this->close('coming_soon');

        $response = $this->get('/')->response();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Retry-After'), 'There is nothing to retry: the site has not opened.');
        $this->assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'), 'A page that is not launched must stay out of search results.');
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testNothingCanBeSubmittedBeforeLaunchEither(): void
    {
        $this->close('coming_soon');
        $before = $this->bookingCount();

        $this->post('booking', $this->bookingForm())->assertOK();   // the coming soon page, not a saved booking

        $this->assertSame($before, $this->bookingCount());
    }

    public function testAFutureOpeningTimeGetsACountdownOverTheWrittenDate(): void
    {
        $this->close('coming_soon', ['returnAt' => '2030-01-01 09:05']);

        $body = $this->get('/')->getBody();

        $this->assertStringContainsString('id="countdown"', $body);
        $this->assertMatchesRegularExpression('/data-until="2030-01-01T09:05:00[+-]\d\d:\d\d"/', $body, 'A full timestamp with its offset, so the visitor\'s own clock counts to the right moment.');
        $this->assertStringContainsString('Tuesday 1 January 2030, 9:05 am', $this->visible($body), 'The date is also written out, for anyone without scripts.');
        $this->assertStringContainsString('Opening', $this->visible($body));
    }

    public function testAnOpeningTimeThatHasPassedSaysAnyMomentNowWithNoCountdown(): void
    {
        $this->close('coming_soon', ['returnAt' => '2020-01-01 09:00']);

        $body = $this->get('/')->getBody();

        $this->assertStringContainsString('Any moment now', $this->visible($body));
        $this->assertStringNotContainsString('id="countdown"', $body);
    }

    public function testWithNoOpeningTimeThereIsNoDateAndNoCountdown(): void
    {
        $this->close('coming_soon');

        $body = $this->get('/')->getBody();

        $this->assertStringNotContainsString('id="countdown"', $body);
        $this->assertStringNotContainsString('Opening Tuesday', $this->visible($body));
    }

    // ================================================================== the page itself

    public function testTheStatusPageLeadsNowhereThatIsClosed(): void
    {
        $this->setSetting('site.announcement', 'Mother\'s Day greetings close on Friday');
        $this->close('maintenance');

        $body = $this->get('/')->getBody();

        $this->assertStringNotContainsString('id="siteNav"', $body, 'No menu.');
        $this->assertStringNotContainsString('Send a greeting', $body, 'No button to a page that would only show this notice.');
        $this->assertStringNotContainsString(site_url('booking'), $body);
        $this->assertStringNotContainsString(site_url('gifts'), $body);
        $this->assertStringNotContainsString('Mother', $body, 'The announcement banner belongs to the open site.');
        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $body);
        $this->assertStringNotContainsString('rel="canonical"', $body);
        $this->assertStringNotContainsString('og:title', $body);
    }

    public function testStaffCanStillFindTheWayInFromThePage(): void
    {
        $this->close('maintenance');

        $this->assertStringContainsString('href="' . site_url('admin') . '"', $this->get('/')->getBody());
    }

    public function testTheContactDetailsAreOfferedAndLinkedWhenThereAreSome(): void
    {
        $this->setSetting('general.contactPhone', '+256 772 000 111');
        $this->setSetting('general.contactEmail', 'info@cbs.example');
        $this->close('maintenance');

        $body = $this->get('/')->getBody();

        $this->assertStringContainsString('href="tel:+256772000111"', $body);
        $this->assertStringContainsString('href="mailto:info@cbs.example"', $body);
        $this->assertStringContainsString('Need to reach us?', $body);
    }

    public function testWithNoContactDetailsThePageDoesNotInviteAnyone(): void
    {
        $this->setSetting('general.contactPhone', '');
        $this->setSetting('general.contactEmail', '');
        $this->close('maintenance');

        $this->assertStringNotContainsString('Need to reach us?', $this->get('/')->getBody());
    }

    public function testTheDialSweepsOnlyBeforeLaunchAndStaysStillForMaintenance(): void
    {
        $this->close('coming_soon');
        $this->assertStringContainsString('class="cbs-needle cbs-needle--sweep"', $this->get('/')->getBody());

        $this->close('maintenance');
        $this->assertStringNotContainsString('cbs-needle--sweep"', $this->get('/')->getBody());
    }

    public function testTheTitleSaysWhichPageThisIs(): void
    {
        $title = static fn (string $html): string => preg_match('#<title>(.*?)</title>#s', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : '';

        $this->close('maintenance');
        $this->assertSame('Under maintenance — CBS Radio', $title($this->get('/')->getBody()));

        $this->close('coming_soon');
        $this->assertSame('Opening soon — CBS Radio', $title($this->get('/')->getBody()));
    }

    public function testThePagesVariablesDoNotOutliveThePage(): void
    {
        // CodeIgniter keeps the data given to a view for the views rendered after it. The status page's own
        // variables (no navigation, noindex, its title) would turn the next page into a copy of it.
        $leaked = static fn (): array => array_intersect(['minimal', 'noindex', 'fullTitle', 'lean', 'copy', 'message', 'mode'], array_keys(service('renderer')->getData()));

        $this->close('maintenance');
        $this->get('/')->assertStatus(503);
        $this->assertSame([], $leaked(), 'The visitor page left its variables behind.');

        $this->get('admin/settings/preview/maintenance')->assertOK();
        $this->assertSame([], $leaked(), 'The staff preview left its variables behind.');
    }

    // ================================================================== staff keep the whole site

    /**
     * @return array<string, array{0: string}>
     */
    public static function closedModes(): array
    {
        return ['coming soon' => ['coming_soon'], 'under maintenance' => ['maintenance']];
    }

    #[DataProvider('closedModes')]
    public function testSignedInStaffSeeTheRealSiteWithAReminder(string $mode): void
    {
        $this->close($mode);

        $result = $this->asStaff()->get('/');

        $result->assertOK();
        $this->assertSame('', $result->response()->getHeaderLine('X-Robots-Tag'));
        $this->assertStringContainsString('Staff view: the site is', $result->getBody());
        $this->assertStringContainsString($mode === 'maintenance' ? 'under maintenance' : 'set to coming soon', $result->getBody());
        $this->assertStringContainsString('visitors cannot see this page', $this->visible($result->getBody()));
        $this->assertStringContainsString('Say it on air.', $result->getBody(), 'The real homepage, not the notice.');
    }

    #[DataProvider('publicPages')]
    public function testStaffCanOpenEveryPublicPageWhileItIsClosed(string $page): void
    {
        $this->close('maintenance');

        $result = $this->asStaff()->get($this->url($page));

        $this->assertTrue($result->response()->getStatusCode() < 400, "{$page} should open for staff.");
        $this->assertStringNotContainsString('Back soon.', (string) $result->response()->getBody());
    }

    public function testStaffCanTryTheBookingFormWhileTheSiteIsClosed(): void
    {
        $this->close('maintenance');
        $before = $this->bookingCount();

        $this->asStaff()->post('booking', $this->bookingForm())->assertRedirect();

        $this->assertSame($before + 1, $this->bookingCount(), 'Staff test the site before it opens.');
    }

    public function testAVisitorWhoseSessionIsEmptyIsStillAVisitor(): void
    {
        $this->close('maintenance');

        $this->withSession(['something_else' => 'x'])->get('/')->assertStatus(503);
        $this->withSession(['user_id' => ''])->get('/')->assertStatus(503);
        $this->withSession(['user_id' => 0])->get('/')->assertStatus(503);
    }

    // ================================================================== what must keep working

    #[DataProvider('closedModes')]
    public function testAProviderCanStillConfirmAPaymentWhileTheSiteIsClosed(string $mode): void
    {
        $this->close($mode);
        $this->setEnv('momo.callbackToken', 'a-long-random-callback-token');
        $payment = $this->makePayment(['booking_id' => $this->booking['id'], 'provider' => 'mtn', 'provider_reference' => 'REF-9']);

        $result = $this->withBodyFormat('json')->post('payments/callback?token=a-long-random-callback-token', ['provider_reference' => 'REF-9', 'status' => 'success']);

        $result->assertOK();
        $result->assertJSONExact(['received' => true]);
        $this->assertSame('success', $this->db->table('payments')->where('id', $payment['id'])->get()->getRowArray()['status']);
    }

    #[DataProvider('closedModes')]
    public function testTheAdminAreaAndThePresenterPortalAreNeverClosed(string $mode): void
    {
        $this->close($mode);

        foreach (['admin', 'admin/bookings', 'admin/settings/status', 'presenter'] as $page) {
            $this->get($page)->assertOK();
        }
    }

    // ================================================================== telling staff it is on

    #[DataProvider('closedModes')]
    public function testEveryAdminPageRemindsStaffWhileTheSiteIsClosed(string $mode): void
    {
        $this->close($mode);

        $visible = $this->visible($this->get('admin/bookings')->getBody());

        $this->assertStringContainsString($mode === 'maintenance' ? 'The public site is under maintenance.' : 'The public site is set to coming soon.', $visible);
        $this->assertStringContainsString('Visitors cannot book or send gifts. You can still use everything.', $visible);
    }

    public function testNoReminderIsShownOnTheAdminPagesWhileTheSiteIsLive(): void
    {
        $this->assertStringNotContainsString('The public site is', $this->visible($this->get('admin/bookings')->getBody()));
    }

    public function testTheDashboardsConfigurationPanelSaysSo(): void
    {
        $this->assertStringContainsString('Live', $this->visible($this->get('admin')->getBody()));

        $this->close('maintenance');

        $this->assertStringContainsString('Under maintenance: visitors cannot book', $this->visible($this->get('admin')->getBody()));
    }

    // ================================================================== previewing

    #[DataProvider('closedModes')]
    public function testStaffCanPreviewEitherPageEvenWhileTheSiteIsLive(string $mode): void
    {
        // The site is live, so staff could not otherwise see these pages at all.
        $result = $this->get('admin/settings/preview/' . $mode);

        $result->assertOK();
        $this->assertSame('', $result->response()->getHeaderLine('X-Robots-Tag'), 'A preview is an ordinary page for a person in a browser tab.');
        $this->assertSame('', $result->response()->getHeaderLine('Retry-After'));
        $this->assertStringContainsString('Staff preview: this is what visitors see', $this->visible($result->getBody()));
        $this->assertStringContainsString($mode === 'maintenance' ? 'Back soon.' : 'Coming soon.', $result->getBody());
    }

    public function testAPreviewShowsTheMessageAndTimeAsLastSaved(): void
    {
        $this->close('live', ['message' => 'Doors open for the Christmas rush.', 'returnAt' => '2030-01-01 09:05']);

        $visible = $this->visible($this->get('admin/settings/preview/coming_soon')->getBody());

        $this->assertStringContainsString('Doors open for the Christmas rush.', $visible);
        $this->assertStringContainsString('Tuesday 1 January 2030, 9:05 am', $visible);
    }

    public function testAPreviewOfSomethingElseIsRefused(): void
    {
        foreach (['live', 'nonsense', 'MAINTENANCE', 'maintenance-and-more'] as $what) {
            $this->get('admin/settings/preview/' . $what)->assertRedirect();

            $this->assertSame('Unknown page to preview.', $this->flash('toastr_error'), $what);
        }
    }

    // ================================================================== the settings tab

    public function testTheTabHasItsThreeSettingsAndTheTools(): void
    {
        $body    = $this->get('admin/settings/status')->getBody();
        $visible = $this->visible($body);

        foreach (['Site status', 'Message to visitors', 'Opening or return time', 'Preview the coming soon page', 'Preview the maintenance page', 'What stays working'] as $text) {
            $this->assertStringContainsString($text, $visible);
        }

        $this->assertStringContainsString('href="' . site_url('admin/settings/preview/maintenance') . '"', $body);
        $this->assertStringContainsString('<option value="live" selected>', $body, 'The site starts open.');
        $this->assertStringContainsString('type="datetime-local"', $body, 'A date and time picker, not a box to type a format into.');
    }

    public function testTheStatusTabIsInTheTabBarOnEveryOtherTab(): void
    {
        $this->assertStringContainsString('Site status', $this->visible($this->get('admin/settings/general')->getBody()));
    }

    public function testTheSavedTimeIsGivenBackToThePickerInItsOwnForm(): void
    {
        $this->setSetting('status.returnAt', '2030-01-01 09:05');

        $this->assertStringContainsString('value="2030-01-01T09:05"', $this->get('admin/settings/status')->getBody());
    }

    // ================================================================== switching it, through the screen

    public function testSwitchingTheSiteOffThroughTheScreenClosesItForVisitors(): void
    {
        $this->post('admin/settings/status', ['s' => ['status_mode' => 'maintenance']])->assertRedirect();

        $this->assertSame('1 setting(s) saved.', $this->flash('toastr_success'));

        $this->reloadConfig();   // what the next request, a fresh process, would do by itself
        $this->get('/')->assertStatus(503);
    }

    public function testSwitchingItBackOnOpensItAgain(): void
    {
        $this->close('maintenance');
        $this->get('/')->assertStatus(503);

        $this->post('admin/settings/status', ['s' => ['status_mode' => 'live']]);
        $this->reloadConfig();

        $this->get('/')->assertOK();
        $this->assertStringContainsString('Say it on air.', $this->get('/')->getBody());
    }

    public function testThePickersValueIsStoredInTheOneShapeTheSiteReads(): void
    {
        $this->post('admin/settings/status', ['s' => ['status_mode' => 'coming_soon', 'status_returnAt' => '2030-01-01T09:05']]);

        $stored = (new SettingModel())->allKeyed();

        $this->assertSame('2030-01-01 09:05', $stored['status.returnAt']);
        $this->assertSame('coming_soon', $stored['status.mode']);
    }

    public function testAnImpossibleOrMalformedTimeIsRefusedAndNothingIsSaved(): void
    {
        foreach (['2026-02-30T10:00', '2026-13-01T10:00', 'tomorrow', '2026-12-01'] as $bad) {
            $this->post('admin/settings/status', ['s' => ['status_mode' => 'maintenance', 'status_returnAt' => $bad]])->assertRedirect();

            $this->assertSame(['Opening or return time: must be a date and time such as 2026-12-01 09:00.'], $this->flash('errors'), $bad);
        }

        $this->assertSame([], (new SettingModel())->allKeyed(), 'The valid mode was not saved either: it is all or nothing.');
        $this->reloadConfig();
        $this->get('/')->assertOK();
    }

    public function testAStatusThatIsNotOneOfTheThreeIsRefused(): void
    {
        $this->post('admin/settings/status', ['s' => ['status_mode' => 'closed']]);

        $this->assertSame(['Site status: is not one of the available choices.'], $this->flash('errors'));
    }

    public function testAMessageThatIsTooLongIsRefused(): void
    {
        $this->post('admin/settings/status', ['s' => ['status_message' => str_repeat('x', 401)]]);

        $this->assertSame(['Message to visitors: is too long (max 400 characters).'], $this->flash('errors'));

        $this->post('admin/settings/status', ['s' => ['status_message' => str_repeat('x', 400)]]);
        $this->assertSame('1 setting(s) saved.', $this->flash('toastr_success'));
    }

    public function testResettingTheStatusPutsTheSiteBackToLive(): void
    {
        $this->close('maintenance');

        $this->post('admin/settings/status', ['reset' => ['status_mode' => '1']]);
        $this->reloadConfig();

        $this->assertArrayNotHasKey('status.mode', (new SettingModel())->allKeyed());
        $this->get('/')->assertOK();
    }

    public function testWhoSwitchedTheSiteOffAndWhatItWasIsOnRecord(): void
    {
        $this->post('admin/settings/status', ['s' => ['status_mode' => 'maintenance']]);

        $row = (new SettingChangeModel())->recent(1)[0];

        $this->assertSame(['status.mode', 'set', 'maintenance'], [$row['setting_key'], $row['action'], $row['new_value']]);
        $this->assertSame((string) StaffUser::id(), (string) $row['changed_by']);
    }
}
