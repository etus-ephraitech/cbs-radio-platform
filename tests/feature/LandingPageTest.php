<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * The public homepage. Everything on it comes from the database or from Admin > Settings, so these tests make
 * the data and the settings do things and watch the page follow, including the cases where it must not.
 *
 * @internal
 */
final class LandingPageTest extends AppFeatureTestCase
{
    private function body(): string
    {
        return $this->get('/')->getBody();
    }

    /**
     * The text of the first match of a pattern, decoded as a browser would show it, so that a test does not depend
     * on which characters the escaper happens to write as entities (an em dash may arrive as &mdash;).
     */
    private function textOf(string $html, string $pattern): ?string
    {
        return preg_match($pattern, $html, $match)
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : null;
    }

    /**
     * A catalogue with one category and two packages, one show and one gift: enough for every section to appear.
     *
     * @return array{category: array, tiers: array<int, array>, show: array, gift: array}
     */
    private function catalogue(): array
    {
        $category = $this->makeCategory(['name' => 'Birthdays', 'slug' => 'birthdays', 'description' => 'Birthday wishes for every age']);

        return [
            'category' => $category,
            'tiers'    => [
                $this->makeTier($category['id'], ['package_level' => 'Basic Text', 'features' => 'On-air readout', 'price' => 15000]),
                $this->makeTier($category['id'], ['package_level' => 'Voice Note + Text', 'features' => 'Readout + 15s voice note', 'price' => 30000]),
            ],
            'show' => $this->makeShow(['name' => 'Morning Express', 'start_time' => '06:00:00', 'end_time' => '10:00:00']),
            'gift' => $this->makeGift(['name' => 'Birthday E-card', 'price' => 5000]),
        ];
    }

    // ------------------------------------------------------------------ the page is built from live data

    public function testTheHomepageIsServedAndHasExactlyOneMainHeading(): void
    {
        $result = $this->get('/');

        $result->assertOK();
        $result->assertSee('Say it on air.');
        $this->assertSame(1, substr_count($result->getBody(), '<h1'));
    }

    public function testEachBookableOccasionIsListedWithItsPackagesAndRealPrices(): void
    {
        $this->catalogue();

        $body = $this->body();

        foreach (['Birthdays', 'Birthday wishes for every age', 'Basic Text', 'On-air readout', 'UGX 15,000', 'Voice Note + Text', 'UGX 30,000'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
    }

    public function testEachOccasionLinksToItsOwnBookingPage(): void
    {
        $this->catalogue();

        $this->assertStringContainsString(site_url('booking/birthdays'), $this->body());
    }

    public function testAnInactiveCategoryOrPackageNeverAppears(): void
    {
        $live   = $this->catalogue();
        $closed = $this->makeCategory(['name' => 'Milestones', 'is_active' => 0]);
        $this->makeTier($closed['id'], ['package_level' => 'Hidden package']);
        $this->makeTier($live['category']['id'], ['package_level' => 'Retired package', 'is_active' => 0]);

        $body = $this->body();

        $this->assertStringNotContainsString('Milestones', $body);
        $this->assertStringNotContainsString('Hidden package', $body);
        $this->assertStringNotContainsString('Retired package', $body);
    }

    public function testACategoryWhosePackagesAreAllInactiveDisappearsAlongWithTheTributeBandThatDependedOnIt(): void
    {
        $tributes = $this->makeCategory(['name' => 'Tributes & In Memoriam', 'slug' => 'tributes-in-memoriam']);
        $tier     = $this->makeTier($tributes['id']);

        $this->assertStringContainsString('For the moments that matter most', $this->body());

        (new \App\Models\RateTierModel())->update($tier['id'], ['is_active' => 0]);

        $body = $this->body();
        $this->assertStringNotContainsString('Tributes &amp; In Memoriam', $body);
        $this->assertStringNotContainsString('For the moments that matter most', $body);
    }

    public function testTheTributeCallToActionLeadsToTheTributeCategory(): void
    {
        $tributes = $this->makeCategory(['name' => 'Tributes & In Memoriam', 'slug' => 'tributes-in-memoriam']);
        $this->makeTier($tributes['id']);

        $this->assertStringContainsString('href="' . site_url('booking/tributes-in-memoriam') . '"', $this->body());
    }

    public function testShowsAreListedWithReadableTimesAndDays(): void
    {
        $this->makeShow(['name' => 'Morning Express', 'start_time' => '06:00:00', 'end_time' => '10:00:00']);
        $this->makeShow(['name' => 'Evening Drive', 'start_time' => '16:00:00', 'end_time' => '19:00:00', 'days_of_week' => '1,2,3,4,5']);

        $body = $this->body();

        foreach (['Morning Express', '6:00 am to 10:00 am', 'Every day', 'Evening Drive', '4:00 pm to 7:00 pm', 'Weekdays'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
    }

    public function testOurOwnGiftsAndVendorGiftsAreShownWithPrices(): void
    {
        $vendor = $this->makeVendor();
        $this->makeGift(['name' => 'Birthday E-card', 'price' => 5000]);
        $this->makeGift(['name' => 'Rose Bouquet', 'type' => 'physical', 'price' => 65000, 'source' => 'vendor', 'vendor_id' => $vendor['id']]);

        $body = $this->body();

        foreach (['Birthday E-card', 'UGX 5,000', 'Rose Bouquet', 'UGX 65,000', 'Delivered gift'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
        $this->assertStringContainsString(site_url('gifts'), $body);
    }

    public function testAGiftDeliveredByAHostedShopIsFeaturedWithItsPhotoAndAnAddToCartButton(): void
    {
        $gift = $this->makeHostedGift(['name' => 'Flower Bouquet', 'price' => 45000, 'image_url' => 'https://kataleplus.example.test/uploads/rose.jpg']);

        $body = html_entity_decode($this->body(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString("Gifts they'll love", $body);
        $this->assertStringContainsString('Flower Bouquet', $body);
        $this->assertStringContainsString('UGX 45,000', $body);
        $this->assertStringContainsString('src="https://kataleplus.example.test/uploads/rose.jpg"', $body);
        $this->assertStringContainsString('Delivered to their door', $body);
        $this->assertStringContainsString('action="' . site_url('gifts/cart/add') . '"', $body);
        $this->assertStringContainsString('name="gift_id" value="' . $gift['id'] . '"', $body);
        $this->assertStringContainsString("paid for on our partner shop's secure page", $body);
        $this->assertStringContainsString('Delivered to their door', $body);
        $this->assertStringNotContainsString('Add to your greeting', $body, 'No greeting group when there are no greeting gifts.');
    }

    public function testGiftsFromEveryVendorAreShownInTheirOwnGroups(): void
    {
        $generic = $this->makeVendor(['name' => 'Gift Hub', 'slug' => 'gift-hub']);
        $this->makeGift(['name' => 'Birthday E-card']);
        $this->makeGift(['name' => 'Hub Airtime', 'type' => 'airtime', 'source' => 'vendor', 'vendor_id' => $generic['id']]);
        $this->makeHostedGift(['name' => 'Flower Bouquet']);

        $body = $this->body();

        foreach (['Add to your greeting', 'Birthday E-card', 'Hub Airtime', 'From Gift Hub', 'Delivered to their door', 'Flower Bouquet', 'From Katale'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
        $this->assertSame(1, substr_count($body, 'gifts/cart/add'), 'Only the delivered gift is bought with the cart.');
    }

    public function testAGiftOfOursOffersSendThisAndNeverTheCart(): void
    {
        $gift = $this->makeGift(['name' => 'Birthday E-card', 'price' => 5000]);

        $body = html_entity_decode($this->body(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString('href="' . site_url('gifts?gift=' . $gift['id']) . '"', $body);
        $this->assertStringNotContainsString('gifts/cart/add', $body);
        $this->assertStringNotContainsString('partner shop', $body, 'No shop notice when no shop gift is shown.');
    }

    public function testADeliveredGiftThatIsOutOfStockIsNotOffered(): void
    {
        $this->makeHostedGift(['name' => 'Last Year Hamper', 'stock_available' => 0]);

        $this->assertStringNotContainsString('Last Year Hamper', $this->body());
    }

    public function testADeliveredGiftIsNeverTheOneShownRidingAlongWithAGreeting(): void
    {
        $this->makeHostedGift(['name' => 'Flower Bouquet']);

        $body = $this->body();

        $this->assertStringNotContainsString('added to the greeting', $body, 'A delivered gift cannot join a greeting, so the hero must not claim it does.');
    }

    public function testTheHeaderShowsTheCartOnlyWhenSomethingIsInIt(): void
    {
        $gift = $this->makeHostedGift();

        preg_match('#<header class="cbs-header">.*?</header>#s', $this->body(), $empty);
        $this->assertStringNotContainsString('gifts/cart', $empty[0]);

        preg_match('#<header class="cbs-header">.*?</header>#s', $this->withSession(['gift_cart' => [$gift['id'] => 2]])->get('/')->getBody(), $full);
        $this->assertStringContainsString('gifts/cart', $full[0]);
        $this->assertStringContainsString('Gift cart, 2 items', html_entity_decode($full[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testASectionWithNothingToShowIsLeftOutEntirely(): void
    {
        $this->makeCategory();   // a category with no packages is not bookable

        $body = $this->body();

        $this->assertStringContainsString("aren't open for booking just yet", $body);
        $this->assertStringNotContainsString('On the air', $body);
        $this->assertStringNotContainsString("Gifts they'll love", $body);
        $this->assertStringNotContainsString('For the moments that matter most', $body);
        $this->assertStringContainsString('How it works', $body);
        $this->assertStringContainsString('Questions', $body, 'The static parts of the page remain.');
    }

    public function testAnEmptyCatalogueStillRendersCleanly(): void
    {
        $this->get('/')->assertOK();
    }

    // ------------------------------------------------------------------ the show that is on air right now

    public function testAShowWhoseHoursCoverTheMomentIsMarkedOnAir(): void
    {
        $this->makeShow(['start_time' => '00:00:00', 'end_time' => '23:59:59', 'days_of_week' => null]);

        $this->assertStringContainsString('On air now', $this->body());
    }

    public function testAShowStartingLaterTodayIsNotOnAir(): void
    {
        $this->makeShow(['start_time' => date('H:i:s', strtotime('+1 hour')), 'end_time' => date('H:i:s', strtotime('+2 hours'))]);

        $this->assertStringNotContainsString('On air now', $this->body());
    }

    public function testAShowRunningPastMidnightIsOnAirOnEitherSideOfIt(): void
    {
        $this->makeShow(['start_time' => date('H:i:s', strtotime('-1 hour')), 'end_time' => date('H:i:s', strtotime('-2 hours'))]);

        $this->assertStringContainsString('On air now', $this->body(), 'Started an hour ago, ends "before" it started: it wraps past midnight.');
    }

    public function testAShowRunningPastMidnightThatHasNotStartedIsNotOnAir(): void
    {
        $this->makeShow(['start_time' => date('H:i:s', strtotime('+1 hour')), 'end_time' => date('H:i:s', strtotime('-1 hour'))]);

        $this->assertStringNotContainsString('On air now', $this->body());
    }

    public function testAShowInTheRightHoursOnAnotherDayIsNotOnAir(): void
    {
        $today = (int) date('N');
        $other = $today % 7 + 1;

        $this->makeShow(['start_time' => '00:00:00', 'end_time' => '23:59:59', 'days_of_week' => (string) $other]);

        $this->assertStringNotContainsString('On air now', $this->body());
    }

    // ------------------------------------------------------------------ staff control the copy from Settings

    public function testTheHeadlineAndIntroductionFollowTheSettings(): void
    {
        $this->setSetting('site.heroHeadline', 'Greetings that travel.');
        $this->setSetting('site.heroSubheadline', 'Tell the whole country you care.');

        $body = $this->body();

        $this->assertStringContainsString('Greetings that travel.', $body);
        $this->assertStringNotContainsString('Say it on air.', $body);
        $this->assertStringContainsString('<meta name="description" content="Tell the whole country you care.">', $body, 'The introduction is also the page description for search and sharing.');
    }

    public function testThereIsNoAnnouncementBarUnlessOneIsSet(): void
    {
        $this->assertStringNotContainsString('<div class="cbs-announce"', $this->body());

        $this->setSetting('site.announcement', "Mother's Day greetings close on Friday.");

        $this->assertSame(
            "Mother's Day greetings close on Friday.",
            $this->textOf($this->body(), '#<div class="cbs-announce"[^>]*>(.*?)</div>#s')
        );
    }

    public function testTheAnnouncementAlsoShowsOnTheBookingPages(): void
    {
        $category = $this->makeCategory(['slug' => 'birthdays']);
        $this->makeTier($category['id']);
        $this->setSetting('site.announcement', 'Closed on Friday');

        $this->assertStringContainsString('<div class="cbs-announce"', $this->get('booking')->getBody());
        $this->assertStringContainsString('<div class="cbs-announce"', $this->get('booking/birthdays')->getBody());
    }

    public function testTheListenLiveButtonAppearsOnlyWhenAStreamIsSet(): void
    {
        $this->assertStringNotContainsString('Listen live', $this->body());

        $this->setSetting('site.liveStreamUrl', 'https://stream.example.test/cbs');

        $body = $this->body();

        $this->assertStringContainsString('Listen live', $body);
        $this->assertStringContainsString('data-src="https://stream.example.test/cbs"', $body);
        $this->assertStringContainsString('preload="none" data-src=', $body, 'Nothing downloads until someone presses it.');
    }

    public function testContactDetailsAppearWhereTheyAreUseful(): void
    {
        $this->setSetting('general.contactPhone', '+256 772 000 111');
        $this->setSetting('general.contactEmail', 'info@cbs.example.test');

        $body = $this->body();

        $this->assertStringContainsString('href="tel:+256772000111"', $body);
        $this->assertStringContainsString('href="mailto:info@cbs.example.test"', $body);
        $this->assertStringContainsString('Questions? Call +256 772 000 111', $body);
    }

    public function testWithNoContactDetailsTheFooterHasNoEmptyColumnAndNoPlaceholder(): void
    {
        $body = $this->body();

        $this->assertStringNotContainsString('<h2>Get in touch</h2>', $body);
        $this->assertStringNotContainsString('coming soon', $body);
    }

    // ------------------------------------------------------------------ search and sharing

    public function testThePageDescribesItselfToSearchEnginesAndSocialSites(): void
    {
        $body = $this->body();

        $this->assertSame(
            'CBS Radio — send a greeting or tribute to be read on air',
            $this->textOf($body, '#<title>(.*?)</title>#s')
        );

        foreach (['<meta property="og:title"', '<meta property="og:type" content="website">', '<link rel="canonical"', '<meta name="theme-color"'] as $tag) {
            $this->assertStringContainsString($tag, $body);
        }
    }

    public function testTheStructuredDataIsValidAndCarriesOnlyWhatStaffEntered(): void
    {
        $this->setSetting('general.contactPhone', '+256 772 000 111');

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $this->body(), $match);
        $data = json_decode($match[1] ?? '', true);

        $this->assertIsArray($data, 'The structured data is not valid JSON.');
        $this->assertSame('RadioStation', $data['@type']);
        $this->assertSame('CBS Radio', $data['name']);
        $this->assertSame('+256 772 000 111', $data['telephone']);
        $this->assertArrayNotHasKey('email', $data, 'No email was entered, so none is claimed.');
    }

    // ------------------------------------------------------------------ nothing staff type can break the page

    public function testMarkupTypedAsTheHeadlineOrAnnouncementIsShownAsTextNeverRun(): void
    {
        $this->setSetting('site.heroHeadline', '<script>alert(1)</script>');
        $this->setSetting('site.announcement', '"><img src=x onerror=alert(1)>');

        $body = $this->body();

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
    }

    #[DataProvider('hostileStreamAddresses')]
    public function testAHostileStreamAddressNeverReachesTheAudioElementEvenIfItGotIntoTheDatabase(string $address): void
    {
        // The Settings screen refuses these (see SettingsValidationTest). This is the second line of defence: even a
        // value written some other way must not be able to break out of its attribute.
        $this->setSetting('site.liveStreamUrl', $address);

        $body = $this->body();

        $this->assertDoesNotMatchRegularExpression('/data-src="[^"]*"[^>]*\bonerror\b/i', $body);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function hostileStreamAddresses(): array
    {
        return [
            'a script tag'       => ['https://x.test/"><script>alert(1)</script>'],
            'an attribute break' => ['https://x.test/" onerror="alert(1)'],
        ];
    }

    // ------------------------------------------------------------------ what the public does and does not see

    public function testThePublicHeaderOffersNoStaffLinksAndTheFooterHasOneQuietOne(): void
    {
        $body = $this->body();
        preg_match('#<header class="cbs-header">.*?</header>#s', $body, $header);

        $this->assertStringNotContainsString('Presenter', $header[0]);
        $this->assertStringNotContainsString('Admin', $header[0]);
        $this->assertStringContainsString('>Staff</a>', $body);
    }

    public function testTheHomepageLoadsNoFrameworkScripts(): void
    {
        $body = $this->body();

        foreach (['jquery', 'bootstrap', 'toastr'] as $library) {
            $this->assertStringNotContainsString($library, $body, "The homepage should stay light and not load {$library}.");
        }
    }

    public function testTheHomepageIsAccessibleToKeyboardAndScreenReaders(): void
    {
        $body = $this->body();

        $this->assertStringContainsString('Skip to content', $body);
        $this->assertStringContainsString('<html lang="en">', $body);
        $this->assertStringContainsString('id="main"', $body);
    }

    public function testAFlashedMessageShowsAsABannerAndWithoutOneThereIsNone(): void
    {
        $withMessage = $this->withSession(['toastr_error' => 'Something went wrong.', '__ci_vars' => ['toastr_error' => 'new']])->get('/')->getBody();

        $this->assertStringContainsString('cbs-notice--error', $withMessage);
        $this->assertStringContainsString('Something went wrong.', $withMessage);

        $this->assertStringNotContainsString('<div class="cbs-notice', $this->withSession([])->get('/')->getBody());
    }

    public function testAVendorsGiftsAreNotPushedOffThePageByManyOfOurOwn(): void
    {
        $vendor = $this->makeVendor(['name' => 'Gift Hub', 'slug' => 'gift-hub']);

        foreach (range(1, 8) as $n) {
            $this->makeGift(['name' => "Our gift {$n}", 'price' => 1000 * $n]);
        }

        $this->makeGift(['name' => 'Hub Airtime', 'type' => 'airtime', 'price' => 90000, 'source' => 'vendor', 'vendor_id' => $vendor['id']]);

        $body = $this->body();

        $this->assertStringContainsString('Hub Airtime', $body, 'The vendor gift gets a place although it is the dearest.');
        $this->assertStringContainsString('Our gift 1', $body);
        $this->assertStringNotContainsString('Our gift 8', $body, 'The page is still a selection, not the whole catalogue.');
    }

    public function testAGiftFromAVendorThatIsSwitchedOffIsNotShown(): void
    {
        $off = $this->makeVendor(['name' => 'Closed Hub', 'slug' => 'closed-hub', 'is_active' => 0]);
        $this->makeGift(['name' => 'Ghost Airtime', 'type' => 'airtime', 'source' => 'vendor', 'vendor_id' => $off['id']]);

        $this->assertStringNotContainsString('Ghost Airtime', $this->body());
    }
}
