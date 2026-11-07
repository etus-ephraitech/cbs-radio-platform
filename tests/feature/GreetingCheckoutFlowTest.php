<?php

namespace Tests\Feature;

use App\Models\GiftOrderModel;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * The visitor's greeting checkout over HTTP: occasion → package → message → gifts → details → one payment.
 *
 * @internal
 */
final class GreetingCheckoutFlowTest extends AppFeatureTestCase
{
    /** @var array<string, mixed> */
    private array $category;

    /** @var array<string, mixed> */
    private array $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->silenceNotifications();
        config('Payments')->mode = 'manual';

        $this->category = $this->makeCategory(['name' => 'Birthdays', 'slug' => 'birthdays']);
        $this->tier     = $this->makeTier($this->category['id'], ['package_level' => 'Voice Note + Text', 'price' => 30000]);
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'category_id'    => $this->category['id'],
            'rate_tier_id'   => $this->tier['id'],
            'sender_name'    => 'Grace Nakato',
            'sender_phone'   => '0772123456',
            'recipient_name' => 'Mama Ruth',
            'message_text'   => 'Happy birthday Mama Ruth!',
        ];
    }

    private function visible(string $html): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    private function rows(string $table): int
    {
        return $this->db->table($table)->countAllResults();
    }

    // ------------------------------------------------------------------ the pages

    public function testTheVisitorPagesAreSelfContainedAndNamed(): void
    {
        $booking = $this->makeBooking(['category_id' => $this->category['id'], 'rate_tier_id' => $this->tier['id']]);
        $order   = $this->makeGiftOrder();

        $pages = [
            'booking'                                      => 'Send a greeting',
            'booking/birthdays'                            => 'Birthdays — send a greeting',
            'booking/' . $booking['id'] . '/pay'           => 'Pay for your greeting',
            'booking/' . $booking['id'] . '/confirmation'  => 'Greeting status',
            'gifts'                                        => 'Send a gift',
            'gifts/' . $order['id'] . '/pay'               => 'Pay for your gift',
            'gifts/' . $order['id'] . '/confirmation'      => 'Gift order status',
        ];

        foreach ($pages as $path => $title) {
            $body = $this->get($path)->getBody();

            $this->assertMatchesRegularExpression('#<title>([^<]*)</title>#', $body, $path);
            preg_match('#<title>([^<]*)</title>#', $body, $match);
            $this->assertSame($title . ' — CBS Radio', html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), $path);
            $this->assertStringContainsString('assets/css/site.css', $body, "{$path}: styled by the site's own stylesheet");
            $this->assertStringNotContainsString('bootstrap.min.css', $body, "{$path}: no Bootstrap download for visitors on a slow connection");
        }
    }

    // ------------------------------------------------------------------ the form

    public function testTheFormOffersTheActiveGiftsWithTheirPrices(): void
    {
        $this->makeGift(['name' => 'Airtime top-up', 'type' => 'airtime', 'price' => 10000]);
        $this->makeGift(['name' => 'Retired gift', 'is_active' => 0]);

        $body = $this->get('booking/birthdays')->getBody();

        $this->assertStringContainsString('Airtime top-up', $body);
        $this->assertStringContainsString('UGX 10,000', $body);
        $this->assertStringNotContainsString('Retired gift', $body);
        $this->assertMatchesRegularExpression('/name="gifts\[\d+\]"/', $body, 'Each gift posts its quantity as gifts[<id>].');
    }

    public function testTheFormLeadsThroughEveryStepInOrder(): void
    {
        $body = $this->get('booking/birthdays')->getBody();

        preg_match_all('/<section class="ck-panel ck-step" data-step="(\d)".*?<h2 id="step\d+Title"[^>]*>(.*?)<\/h2>/s', $body, $steps);

        $this->assertSame(['2', '3', '4', '5', '6'], $steps[1], 'Every step is on the page, so the form works without JavaScript too.');
        $this->assertSame(
            ['Choose a package', 'Your message', 'Add a gift', 'Your details', 'Review and pay'],
            array_map(static fn (string $h): string => trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<span.*$/s', '', $h)))), $steps[2]),
            'Package, message, gifts, details, review — in that order.'
        );
    }

    public function testTheFormOffersEveryShowWithTheDaysItRuns(): void
    {
        $this->makeShow(['name' => 'Weekend Vibes', 'days_of_week' => '6,7']);

        $body = $this->get('booking/birthdays')->getBody();

        $this->assertStringContainsString('Weekend Vibes', $body);
        $this->assertStringContainsString('data-show-days="6,7"', $body);
    }

    // ------------------------------------------------------------------ sending a greeting with gifts

    public function testAGreetingWithGiftsIsBookedAndSentToOnePayment(): void
    {
        $airtime = $this->makeGift(['name' => 'Airtime top-up', 'type' => 'airtime', 'price' => 10000]);
        $ecard   = $this->makeGift(['name' => 'E-card', 'price' => 5000]);

        $result = $this->post('booking', $this->form([
            'gifts'                => [$airtime['id'] => '2', $ecard['id'] => '0'],
            'gift_recipient_phone' => '0700111222',
        ]));

        $booking = $this->db->table('bookings')->get()->getRowArray();
        $result->assertRedirectTo(site_url('booking/' . $booking['id'] . '/pay'));

        $orders = (new GiftOrderModel())->bundledWithBooking((int) $booking['id']);
        $this->assertCount(1, $orders, 'Only the gift with a quantity is ordered.');
        $this->assertSame('Airtime top-up', $orders[0]['gift_name']);
        $this->assertSame('2', (string) $orders[0]['quantity']);
        $this->assertSame('0700111222', $orders[0]['recipient_phone']);

        $visible = $this->visible($this->get('booking/' . $booking['id'] . '/pay')->getBody());

        foreach (['Pay for your greeting', 'UGX 50,000', 'Airtime top-up', cbs_booking_reference((int) $booking['id'])] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
        $this->assertSame('50000.00', $this->db->table('payments')->get()->getRowArray()['amount'], 'Greeting 30,000 + 2 × airtime 10,000.');
    }

    public function testARefusedGiftSendsTheVisitorBackWithNothingBooked(): void
    {
        $flowers = $this->makeGift(['name' => 'Flowers', 'type' => 'physical', 'price' => 50000]);

        $result = $this->post('booking', $this->form(['gifts' => [$flowers['id'] => '1']]));

        $result->assertRedirect();
        $this->assertSame('Add a delivery address for the gift that will be delivered.', $this->flash('toastr_error'));
        $this->assertSame(0, $this->rows('bookings'));
        $this->assertSame(0, $this->rows('gift_orders'));
    }

    public function testAGiftPaidWithAGreetingCannotBePaidAgainOnItsOwn(): void
    {
        $gift = $this->makeGift();
        $this->post('booking', $this->form(['gifts' => [$gift['id'] => '1']]));
        $booking = $this->db->table('bookings')->get()->getRowArray();
        $order   = $this->db->table('gift_orders')->get()->getRowArray();

        $result = $this->get('gifts/' . $order['id'] . '/pay');

        $result->assertRedirectTo(site_url('booking/' . $booking['id'] . '/pay'));
        $this->assertSame(0, $this->rows('payments'), 'No separate payment for the gift.');
    }

    public function testTheStatusPageListsTheGiftsSentWithTheGreeting(): void
    {
        $gift = $this->makeGift(['name' => 'Song dedication', 'type' => 'dedication']);
        $this->post('booking', $this->form(['gifts' => [$gift['id'] => '1']]));
        $booking = $this->db->table('bookings')->get()->getRowArray();

        $visible = $this->visible($this->get('booking/' . $booking['id'] . '/confirmation')->getBody());

        $this->assertStringContainsString('Gifts with it', $visible);
        $this->assertStringContainsString('Song dedication', $visible);
    }
}
