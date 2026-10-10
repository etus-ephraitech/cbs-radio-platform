<?php

namespace Tests\Feature;

use App\Models\BookingModel;
use App\Models\ShowSlotModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Every page the application serves, visited once with realistic data behind it. The cheapest test there is, and the
 * one that catches a broken view, a mistyped route or a method that no longer exists.
 *
 * @internal
 */
final class RoutesSmokeTest extends AppFeatureTestCase
{
    /** @var array<string, int|string> filled in by setUp: the ids and slugs the paths below refer to */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $category = $this->makeCategory(['slug' => 'birthdays']);
        $tier     = $this->makeTier($category['id']);
        $show     = $this->makeShow();
        $slot     = (new ShowSlotModel())->findOrCreateForDate($show['id'], date('Y-m-d'));
        $booking  = $this->makeBooking(['category_id' => $category['id'], 'rate_tier_id' => $tier['id'], 'show_slot_id' => $slot['id'], 'status' => BookingModel::STATUS_PENDING_PAYMENT]);
        $gift     = $this->makeGift();
        $order    = $this->makeGiftOrder(['gift_id' => $gift['id']]);

        $this->makePayment(['booking_id' => $booking['id']]);

        $this->ids = [
            'category' => $category['id'], 'slug' => 'birthdays', 'tier' => $tier['id'], 'show' => $show['id'], 'booking' => $booking['id'],
            'gift' => $gift['id'], 'order' => $order['id'], 'vendor' => $this->makeVendor()['id'],
            'template' => (int) (new \App\Models\MessageTemplateModel())->insert(['language' => 'English', 'title' => 'T', 'body' => 'B {recipient}']),
        ];
    }

    private function resolve(string $path): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn (array $m) => (string) $this->ids[$m[1]], $path);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pages(): array
    {
        $paths = [
            // public
            '/', 'booking', 'booking/{slug}', 'booking/{booking}/pay', 'booking/{booking}/confirmation',
            'gifts', 'gifts/{order}/pay', 'gifts/{order}/confirmation',
            // presenter
            'presenter', 'presenter/shows/{show}',
            // admin
            'admin', 'admin/settings/general', 'admin/settings/site', 'admin/settings/payments', 'admin/settings/notifications',
            'admin/settings/templates', 'admin/settings/bookings',
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

    #[DataProvider('pages')]
    public function testThePageIsServed(string $path): void
    {
        $result = $this->get($this->resolve($path));

        $result->assertOK();
        $this->assertNotSame('', trim($result->getBody()), 'The page came back empty.');
    }

    public function testEveryPageEmitsNoPhpErrorsOrWarnings(): void
    {
        foreach (array_keys(self::pages()) as $path) {
            $body = $this->get($this->resolve($path))->getBody();

            foreach (['Fatal error', 'Warning:', 'Notice:', 'Deprecated:', 'Undefined variable', 'Undefined array key', 'Stack trace'] as $symptom) {
                $this->assertStringNotContainsString($symptom, $body, "{$path} shows '{$symptom}'.");
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function missingThings(): array
    {
        return [
            'an unknown category'             => ['booking/no-such-category', 'booking'],
            'an unknown booking to pay'       => ['booking/999999/pay', 'booking'],
            'an unknown booking confirmation' => ['booking/999999/confirmation', 'booking'],
            'an unknown gift order to pay'    => ['gifts/999999/pay', 'gifts'],
            'an unknown admin booking'        => ['admin/bookings/999999', 'admin/bookings'],
            'an unknown category to edit'     => ['admin/categories/999999/edit', 'admin/categories'],
            'an unknown template to edit'     => ['admin/message-templates/999999/edit', 'admin/message-templates'],
            'an unknown settings tab'         => ['admin/settings/no-such-tab', 'admin/settings'],
        ];
    }

    #[DataProvider('missingThings')]
    public function testAMissingThingSendsThePersonBackToTheListWithAMessage(string $path, string $backTo): void
    {
        $result = $this->get($path);

        $result->assertRedirect();
        $this->assertStringEndsWith('/' . $backTo, rtrim($result->getRedirectUrl(), '/'));
        $this->assertNotEmpty($this->flash('toastr_error'));
    }

    public function testASettingsLandingGoesToTheFirstTab(): void
    {
        $this->get('admin/settings')->assertRedirectTo(site_url('admin/settings/general'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function voiceNotePaths(): array
    {
        return [
            'in the booking list'  => ['admin/bookings/{booking}/voice-note'],
            'in moderation'        => ['admin/moderation/{booking}/voice-note'],
            'in the presenter app' => ['presenter/bookings/{booking}/voice-note'],
        ];
    }

    #[DataProvider('voiceNotePaths')]
    public function testAVoiceNoteThatDoesNotExistIsNotFound(string $path): void
    {
        // The booking has no voice note. In the "testing" environment CodeIgniter rethrows the not-found
        // exception instead of rendering a page; in production it becomes the 404 page.
        $this->expectException(PageNotFoundException::class);

        $this->get($this->resolve($path));
    }
}
