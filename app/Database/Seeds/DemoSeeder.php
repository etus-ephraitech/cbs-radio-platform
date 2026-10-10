<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Local development data, so nobody builds or demos against empty tables.
 *
 *   php spark db:seed DemoSeeder
 *
 * Seeds the CBS rate card from the original proposal, two shows, our own
 * gift catalog, and a handful of sample bookings covering every status so
 * the admin screens (moderation queue, bookings list, payments, gift
 * orders) all have something to show.
 *
 * Safe to run twice: it does nothing if categories already exist. It never
 * touches the users table, and never seeds vendor gifts (those come from
 * `php spark gifts:sync` against a real vendor).
 *
 * NOT for production — the sample bookings use made-up phone numbers.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->db->table('categories')->countAllResults() > 0) {
            CLI::write('Categories already exist — skipping DemoSeeder so nothing is duplicated.', 'yellow');

            return;
        }

        $now = date('Y-m-d H:i:s');

        $categoryIds = [];

        $categoryRows = [
            'birthdays'  => ['Birthdays', 'Personalized birthday wishes for children, adults and elders.'],
            'milestones' => ['Milestones & Achievements', 'Graduations, promotions, business launches and sports victories.'],
            'tributes'   => ['Tributes & In Memoriam', 'Respectful memorial notices and condolences.'],
            'greetings'  => ['Warm Greetings & Celebrations', 'Weddings, Kwanjula, anniversaries and general goodwill.'],
            'holidays'   => ['Key Calendar Days & Holidays', 'Christmas, New Year, Idd, Mother\'s/Father\'s Day and cultural celebrations.'],
        ];

        foreach ($categoryRows as $slug => [$name, $description]) {
            $this->db->table('categories')->insert([
                'name' => $name, 'slug' => $slug, 'description' => $description,
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $categoryIds[$slug] = $this->db->insertID();
        }

        // The rate card from the CBS proposal. includes_on_air is 1 for
        // every tier, including Milestone — the proposal left that one
        // ambiguous, so treat the Milestone value as a placeholder until
        // CBS confirms, then change it under Admin > Packages.
        $tierIds = [];

        $tierRows = [
            ['greetings',  'basic',     'Basic Text',        'One on-air readout during standard programming',            15000],
            ['greetings',  'voice',     'Voice Note + Text', 'On-air readout plus a 15-second voice note played on air',  30000],
            ['birthdays',  'vip',       'VIP Prime Slot',    'On-air readout during peak shows plus website banner',      50000],
            ['milestones', 'milestone', 'Business & Personal', 'Announcement plus a 1-day listing on CBS digital platforms', 75000],
            ['tributes',   'tribute',   'Standard',          'On-air tribute readout plus daily memorial roll',           40000],
            ['tributes',   'tributePr', 'Premium',           'Extended on-air tribute plus a digital memorial page',     100000],
            ['holidays',   'holiday',   'Festive / Holiday', 'Prime holiday readout, dedicated song, gift package discount', 60000],
        ];

        foreach ($tierRows as [$category, $key, $package, $features, $price]) {
            $this->db->table('rate_tiers')->insert([
                'category_id' => $categoryIds[$category], 'package_level' => $package, 'features' => $features,
                'includes_on_air' => 1, 'price' => $price, 'currency' => 'UGX', 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $tierIds[$key] = $this->db->insertID();
        }

        // Starter example messages for the booking form, in English only. Staff add
        // their own — including Luganda — under Admin > Message templates; {recipient}
        // and {sender} are filled in from what the visitor has typed.
        $templateRows = [
            ['birthdays',  'Warm birthday wish', 'Happy birthday {recipient}! Wishing you a day full of joy, good health and everything your heart desires. With love from {sender}.'],
            ['birthdays',  'Short and sweet',    'Happy birthday {recipient}! Many happy returns. From {sender}.'],
            ['birthdays',  'For someone special', '{recipient}, today is your day. Thank you for everything you do for us. Happy birthday, and God bless you. From {sender}.'],
            ['milestones', 'Congratulations',    'Congratulations {recipient}! We are so proud of everything you have achieved. Well done, and the best is still to come. From {sender}.'],
            ['tributes',   'In loving memory',   'Remembering {recipient} today. Though you are gone, you will never be forgotten. May your soul rest in peace. From {sender}.'],
            ['tributes',   'Condolences to a family', 'To {recipient} and family: we are thinking of you in this difficult time. Please accept our heartfelt condolences. From {sender}.'],
            ['greetings',  'Wedding congratulations', 'Congratulations {recipient} on your wedding day! May your life together be filled with love, peace and happiness. From {sender}.'],
            ['greetings',  'Anniversary',        'Happy anniversary {recipient}! Wishing you many more years of love and joy together. From {sender}.'],
            ['holidays',   "Season's greetings", 'Wishing you and your family a blessed and joyful season, {recipient}. From {sender}.'],
            [null,         'Thinking of you',    '{recipient}, we are thinking of you today and sending our love. From {sender}.'],
        ];

        foreach ($templateRows as $order => [$category, $title, $body]) {
            $this->db->table('message_templates')->insert([
                'category_id' => $category === null ? null : $categoryIds[$category],
                'language' => 'English', 'title' => $title, 'body' => $body,
                'sort_order' => $order, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $showIds = [];

        $showRows = [
            ['morning', 'Morning Express', '06:00:00', '10:00:00', null],
            ['evening', 'Evening Drive',   '16:00:00', '19:00:00', '1,2,3,4,5'],
        ];

        foreach ($showRows as [$key, $name, $start, $end, $days]) {
            $this->db->table('shows')->insert([
                'name' => $name, 'start_time' => $start, 'end_time' => $end, 'days_of_week' => $days,
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $showIds[$key] = $this->db->insertID();
        }

        $this->db->table('show_slots')->insert([
            'show_id' => $showIds['morning'], 'slot_date' => date('Y-m-d'),
            'capacity' => 20, 'booked_count' => 3, 'is_open' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $slotId = $this->db->insertID();

        $giftIds = [];

        $giftRows = [
            ['ecard',    'E-card',          'ecard',      'An animated digital greeting card.',          5000],
            ['airtime',  'Airtime top-up',  'airtime',    'Airtime sent straight to the recipient.',    10000],
            ['shoutout', 'Custom shout-out', 'shoutout',  'A personal shout-out read on air.',          20000],
            ['song',     'Song dedication', 'dedication', 'A song played on air in their honour.',      25000],
        ];

        foreach ($giftRows as [$key, $name, $type, $description, $price]) {
            $this->db->table('gifts')->insert([
                'name' => $name, 'type' => $type, 'source' => 'local', 'description' => $description,
                'price' => $price, 'currency' => 'UGX', 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $giftIds[$key] = $this->db->insertID();
        }

        // Sample bookings, one per interesting status. Phone numbers are fake.
        $bookings = [
            ['pending_moderation', 'greetings', 'voice', 'Grace Namatovu', '0700000101', 'Mama Ruth',
                'Happy birthday Mama Ruth, we love you!', 30000, true],
            ['approved', 'greetings', 'basic', 'John Okello', '0700000102', 'Brian',
                'Congratulations Brian on turning 30!', 15000, true],
            ['pending_payment', 'tributes', 'tribute', 'Sarah Kyomuhendo', '0700000103', 'The Mukasa family',
                'With deepest condolences from all of us.', 40000, false],
            ['aired', 'greetings', 'basic', 'Peter Mugisha', '0700000104', 'Aunt Joy',
                'Happy birthday auntie, many happy returns!', 15000, true],
            ['rejected', 'milestones', 'milestone', 'Test Sender', '0700000105', 'Someone',
                'This one was rejected during moderation.', 75000, true],
        ];

        $bookingIds = [];

        foreach ($bookings as [$status, $category, $tier, $sender, $phone, $recipient, $message, $amount, $paid]) {
            $this->db->table('bookings')->insert([
                'category_id' => $categoryIds[$category], 'rate_tier_id' => $tierIds[$tier],
                'show_slot_id' => in_array($status, ['pending_moderation', 'approved', 'aired'], true) ? $slotId : null,
                'sender_name' => $sender, 'sender_phone' => $phone, 'recipient_name' => $recipient,
                'message_text' => $message, 'scheduled_date' => date('Y-m-d'), 'status' => $status,
                'amount' => $amount, 'currency' => 'UGX',
                'aired_at' => $status === 'aired' ? $now : null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $bookingId           = $this->db->insertID();
            $bookingIds[$status] = $bookingId;

            // Unpaid by default; a paid booking overrides all three fields together.
            $settlement = ['status' => 'pending', 'provider_reference' => null, 'paid_at' => null];

            if ($paid) {
                $settlement = ['status' => 'success', 'provider_reference' => 'DEMO-' . $bookingId, 'paid_at' => $now];
            }

            $this->db->table('payments')->insert($settlement + [
                'booking_id' => $bookingId, 'provider' => 'demo',
                'amount' => $amount, 'currency' => 'UGX',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // One paid gift order riding along with a greeting, ready to fulfill.
        $this->db->table('gift_orders')->insert([
            'booking_id' => $bookingIds['pending_moderation'], 'gift_id' => $giftIds['ecard'],
            'sender_name' => 'Grace Namatovu', 'sender_phone' => '0700000101',
            'recipient_name' => 'Mama Ruth', 'quantity' => 1, 'amount' => 5000, 'currency' => 'UGX',
            'status' => 'paid', 'created_at' => $now, 'updated_at' => $now,
        ]);

        CLI::write('DemoSeeder: 5 categories, 7 packages, 10 message templates, 2 shows, 4 gifts, 5 sample bookings, 1 gift order.', 'green');
    }
}
