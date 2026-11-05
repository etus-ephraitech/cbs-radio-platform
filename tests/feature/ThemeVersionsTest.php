<?php

namespace Tests\Feature;

use App\Models\BookingModel;
use App\Models\MessageTemplateModel;
use App\Models\ShowSlotModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Both UI versions, every page: the original views (ui_version empty) and the v1 dashboard theme.
 *
 * The rest of the suite runs under v1 (phpunit.xml.dist pins it), so this is what keeps the original UI working
 * while it remains the fallback, and what proves each screen is drawn by the shell it is meant to be drawn by: a
 * v1 staff page in the theme's shell with its assets, an original staff page in the original admin or portal
 * layout, and a public page in the public layout under either version, since no version restyles the public site.
 *
 * @internal
 */
final class ThemeVersionsTest extends AppFeatureTestCase
{
    use SetsEnvironment;

    /** @var array<string, int|string> filled in by setUp: the ids the paths below refer to */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $category = $this->makeCategory(['slug' => 'birthdays']);
        $tier     = $this->makeTier($category['id']);
        $show     = $this->makeShow();
        $slot     = (new ShowSlotModel())->findOrCreateForDate($show['id'], date('Y-m-d'));
        $booking  = $this->makeBooking(['category_id' => $category['id'], 'rate_tier_id' => $tier['id'], 'show_slot_id' => $slot['id'], 'status' => BookingModel::STATUS_APPROVED]);
        $gift     = $this->makeGift();
        $order    = $this->makeGiftOrder(['gift_id' => $gift['id']]);

        $this->makePayment(['booking_id' => $booking['id']]);

        $this->ids = [
            'category' => $category['id'], 'tier' => $tier['id'], 'show' => $show['id'], 'booking' => $booking['id'],
            'gift'     => $gift['id'], 'order' => $order['id'], 'vendor' => $this->makeVendor()['id'],
            'template' => (int) (new MessageTemplateModel())->insert(['language' => 'English', 'title' => 'T', 'body' => 'B {recipient}']),
        ];
    }

    private function resolve(string $path): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn (array $m) => (string) $this->ids[$m[1]], $path);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function adminPages(): array
    {
        $paths = [
            'admin', 'admin/settings/general', 'admin/settings/payments', 'admin/settings/notifications', 'admin/settings/templates',
            'admin/bookings', 'admin/bookings/{booking}', 'admin/moderation', 'admin/payments', 'admin/gift-orders', 'admin/notifications',
            'admin/categories', 'admin/categories/create', 'admin/categories/{category}/edit',
            'admin/shows', 'admin/shows/create', 'admin/shows/{show}/edit',
            'admin/rate-tiers', 'admin/rate-tiers/create', 'admin/rate-tiers/{tier}/edit',
            'admin/message-templates', 'admin/message-templates/create', 'admin/message-templates/{template}/edit',
            'admin/gifts', 'admin/gifts/create', 'admin/gifts/{gift}/edit',
            'admin/gift-vendors', 'admin/gift-vendors/create', 'admin/gift-vendors/{vendor}/edit',
        ];

        return array_combine($paths, array_map(static fn (string $p) => [$p], $paths));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function presenterPages(): array
    {
        return ['presenter' => ['presenter'], 'presenter/shows/{show}' => ['presenter/shows/{show}']];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function publicPages(): array
    {
        $paths = ['/', 'booking', 'booking/birthdays', 'gifts'];

        return array_combine($paths, array_map(static fn (string $p) => [$p], $paths));
    }

    // ------------------------------------------------------------------ v1

    #[DataProvider('adminPages')]
    public function testUnderV1AnAdminPageUsesTheThemeShell(string $path): void
    {
        $this->setEnv('ui_version', 'v1');

        $body = $this->page($this->resolve($path));

        $this->assertThemeShell($body, $path);
        $this->assertStringContainsString('page-header-breadcrumb', $body, "{$path}: the v1 page header is drawn.");
        $this->assertStringContainsString('card custom-card', $body, "{$path}: the screen itself is a v1 screen, not an original one in the new shell.");
        $this->assertStringNotContainsString('admin-sidebar', $body, "{$path}: no part of the original admin layout.");
    }

    #[DataProvider('presenterPages')]
    public function testUnderV1APresenterPageUsesTheThemeShell(string $path): void
    {
        $this->setEnv('ui_version', 'v1');

        $body = $this->page($this->resolve($path));

        $this->assertThemeShell($body, $path);
        $this->assertStringNotContainsString('navbar-cbs', $body, "{$path}: no part of the original portal layout.");
    }

    public function testUnderV1TheMenuMarksTheCurrentSectionEvenOnAChildPage(): void
    {
        $this->setEnv('ui_version', 'v1');

        $body = $this->page('admin/bookings/' . $this->ids['booking']);

        $this->assertMatchesRegularExpression(
            '#<a href="[^"]*/admin/bookings" class="side-menu__item active" aria-current="page">#',
            $body,
            'A booking\'s own page lights up "Bookings" in the side menu.'
        );
        $this->assertSame(1, substr_count($body, 'class="side-menu__item active"'), 'Exactly one menu entry is current.');
    }

    public function testUnderV1EveryThemeAssetThePageLoadsIsShipped(): void
    {
        $this->setEnv('ui_version', 'v1');

        $body = $this->page('admin');

        preg_match_all('#(?:src|href)="' . preg_quote(rtrim(base_url('assets'), '/'), '#') . '/([^"?]+)"#', $body, $matches);

        $this->assertNotEmpty($matches[1], 'The page loads its assets through base_url().');

        foreach (array_unique($matches[1]) as $asset) {
            $this->assertFileExists(FCPATH . 'assets/' . $asset, "assets/{$asset} is referenced by the layout but not shipped.");
        }
    }

    #[DataProvider('publicPages')]
    public function testUnderV1APublicPageKeepsThePublicLayout(string $path): void
    {
        $this->setEnv('ui_version', 'v1');

        $body = $this->page($path);

        $this->assertStringNotContainsString('app-sidebar', $body, "{$path}: the public site is not drawn in the staff shell.");
    }

    // ------------------------------------------------------------------ the original views

    #[DataProvider('adminPages')]
    public function testWithoutAVersionAnAdminPageUsesTheOriginalLayout(string $path): void
    {
        $this->setEnv('ui_version', null);

        $body = $this->page($this->resolve($path));

        $this->assertStringContainsString('admin-sidebar', $body, "{$path}: drawn by the original admin layout.");
        $this->assertStringNotContainsString('app-sidebar', $body, "{$path}: no part of the v1 shell.");
        $this->assertStringNotContainsString('page-header-breadcrumb', $body, "{$path}: the original page header, not the v1 one.");
    }

    #[DataProvider('presenterPages')]
    public function testWithoutAVersionAPresenterPageUsesTheOriginalLayout(string $path): void
    {
        $this->setEnv('ui_version', null);

        $body = $this->page($this->resolve($path));

        $this->assertStringContainsString('navbar-cbs', $body, "{$path}: drawn by the original portal layout.");
        $this->assertStringNotContainsString('app-sidebar', $body, "{$path}: no part of the v1 shell.");
    }

    #[DataProvider('publicPages')]
    public function testWithoutAVersionAPublicPageIsServed(string $path): void
    {
        $this->setEnv('ui_version', null);

        $body = $this->page($path);

        $this->assertStringNotContainsString('app-sidebar', $body, $path);
    }

    /**
     * GET a page, require a 200, and return its HTML.
     */
    private function page(string $path): string
    {
        $result = $this->get($path);
        $result->assertOK();

        return $result->getBody();
    }

    private function assertThemeShell(string $body, string $path): void
    {
        foreach (['id="sidebar"', 'class="app-header', 'assets/css/styles.min.css', 'assets/css/admin.css', 'id="switcher-canvas"'] as $marker) {
            $this->assertStringContainsString($marker, $body, "{$path}: missing {$marker} from the v1 shell.");
        }

        $this->assertStringNotContainsString('<base ', $body, "{$path}: assets are absolute, no <base> element.");
    }
}
