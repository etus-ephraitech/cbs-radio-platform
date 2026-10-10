<?php

namespace Tests\Database\Models;

use App\Models\BookingModel;
use Tests\Support\Libraries\StaffUser;
use Tests\Support\TestCase\AppTestCase;

/**
 * Bookings: the status changes, the lists the staff screens read, and above all the search box.
 *
 * @internal
 */
final class BookingModelTest extends AppTestCase
{
    private BookingModel $bookings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookings = new BookingModel();
    }

    /**
     * Created times are set by the framework, so a test that depends on order sets them itself.
     */
    private function backdate(int|string $bookingId, string $datetime): void
    {
        $this->db->table('bookings')->where('id', $bookingId)->update(['created_at' => $datetime]);
    }

    // ------------------------------------------------------------------ the statuses

    public function testThereAreSixStatusesAndTheListMatchesTheConstants(): void
    {
        $this->assertEqualsCanonicalizing(
            ['pending_payment', 'pending_moderation', 'approved', 'rejected', 'aired', 'cancelled'],
            BookingModel::STATUSES
        );
    }

    public function testANewBookingWaitsForPayment(): void
    {
        $this->assertSame(BookingModel::STATUS_PENDING_PAYMENT, $this->makeBooking()['status']);
    }

    public function testApprovingRecordsWhoAndWhy(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);

        $this->assertTrue($this->bookings->markApproved($booking['id'], StaffUser::id(), 'Looks lovely'));

        $after = $this->bookings->find($booking['id']);
        $this->assertSame('approved', $after['status']);
        $this->assertSame((string) StaffUser::id(), (string) $after['moderated_by']);
        $this->assertSame('Looks lovely', $after['moderation_notes']);
    }

    public function testRejectingRecordsWhoAndWhy(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);

        $this->bookings->markRejected($booking['id'], StaffUser::id(), 'Not suitable for air');

        $after = $this->bookings->find($booking['id']);
        $this->assertSame('rejected', $after['status']);
        $this->assertSame('Not suitable for air', $after['moderation_notes']);
    }

    public function testMarkingAiredStampsTheTime(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_APPROVED]);

        $this->assertNull($booking['aired_at']);

        $this->bookings->markAired($booking['id']);

        $after = $this->bookings->find($booking['id']);
        $this->assertSame('aired', $after['status']);
        $this->assertEqualsWithDelta(time(), strtotime($after['aired_at']), 5);
    }

    public function testCancellingOnlyChangesTheStatus(): void
    {
        $booking = $this->makeBooking();

        $this->bookings->markCancelled($booking['id']);

        $this->assertSame('cancelled', $this->bookings->find($booking['id'])['status']);
    }

    // ------------------------------------------------------------------ the simple lists

    public function testThePendingModerationQueueIsOldestFirst(): void
    {
        $newer  = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION, 'recipient_name' => 'Newer']);
        $older  = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION, 'recipient_name' => 'Older']);
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'recipient_name' => 'Not in the queue']);

        $this->backdate($older['id'], '2026-01-01 09:00:00');
        $this->backdate($newer['id'], '2026-01-02 09:00:00');

        $this->assertSame(['Older', 'Newer'], array_column($this->bookings->pendingModeration(), 'recipient_name'));
    }

    public function testASlotsQueueIsItsApprovedBookingsOnly(): void
    {
        $show = $this->makeShow();
        $slot = (new \App\Models\ShowSlotModel())->findOrCreateForDate($show['id'], '2026-12-24');
        $other = (new \App\Models\ShowSlotModel())->findOrCreateForDate($show['id'], '2026-12-25');

        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $slot['id'], 'recipient_name' => 'Approved here']);
        $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION, 'show_slot_id' => $slot['id'], 'recipient_name' => 'Not yet']);
        $this->makeBooking(['status' => BookingModel::STATUS_AIRED, 'show_slot_id' => $slot['id'], 'recipient_name' => 'Already read']);
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $other['id'], 'recipient_name' => 'Another day']);

        $this->assertSame(['Approved here'], array_column($this->bookings->approvedForSlot($slot['id']), 'recipient_name'));
    }

    public function testBookingsCanBeListedByStatusNewestFirst(): void
    {
        $a = $this->makeBooking(['status' => BookingModel::STATUS_REJECTED, 'recipient_name' => 'First']);
        $b = $this->makeBooking(['status' => BookingModel::STATUS_REJECTED, 'recipient_name' => 'Second']);
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'recipient_name' => 'Different status']);

        $this->backdate($a['id'], '2026-01-01 09:00:00');
        $this->backdate($b['id'], '2026-01-02 09:00:00');

        $this->assertSame(['Second', 'First'], array_column($this->bookings->byStatus('rejected'), 'recipient_name'));
    }

    public function testBookingsMadeInTheSameSecondKeepAStableOrder(): void
    {
        // Created times only have one-second resolution, so a busy minute produces ties. The presenter's queue
        // and the lists must not reshuffle themselves between page loads: ties are broken by id.
        $first  = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);
        $second = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);
        $third  = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);

        foreach ([$first, $second, $third] as $booking) {
            $this->backdate($booking['id'], '2026-01-01 09:00:00');
        }

        $this->assertSame(
            [(int) $first['id'], (int) $second['id'], (int) $third['id']],
            array_map('intval', array_column($this->bookings->pendingModeration(), 'id')),
            'Oldest first, and first-come first-served among the tied.'
        );
        $this->assertSame(
            [(int) $third['id'], (int) $second['id'], (int) $first['id']],
            array_map('intval', array_column($this->bookings->byStatus('pending_moderation'), 'id'))
        );
    }

    // ------------------------------------------------------------------ the admin search

    public function testASearchWithNoFiltersFindsEverythingNewestFirst(): void
    {
        $first  = $this->makeBooking();
        $second = $this->makeBooking();
        $third  = $this->makeBooking();

        $ids = array_map('intval', array_column($this->bookings->adminSearch([], 50, 0), 'id'));

        $this->assertSame([(int) $third['id'], (int) $second['id'], (int) $first['id']], $ids);
        $this->assertSame(3, (new BookingModel())->adminCount([]));
    }

    public function testEachResultCarriesTheNamesTheListShows(): void
    {
        $category = $this->makeCategory(['name' => 'Birthdays']);
        $tier     = $this->makeTier($category['id'], ['package_level' => 'Voice + text']);
        $show     = $this->makeShow(['name' => 'Morning Express']);
        $slot     = (new \App\Models\ShowSlotModel())->findOrCreateForDate($show['id'], '2026-12-24');

        $this->makeBooking(['category_id' => $category['id'], 'rate_tier_id' => $tier['id'], 'show_slot_id' => $slot['id']]);
        $this->makeBooking(['category_id' => $category['id'], 'rate_tier_id' => $tier['id']]);

        $rows = $this->bookings->adminSearch([], 50, 0);

        $this->assertSame('Birthdays', $rows[0]['category_name']);
        $this->assertSame('Voice + text', $rows[0]['package_level']);
        $this->assertNull($rows[0]['show_name'], 'A booking with no slot still appears.');

        $this->assertSame('Morning Express', $rows[1]['show_name']);
        $this->assertSame('2026-12-24', $rows[1]['slot_date']);
    }

    public function testASingleBookingCanBeFetchedWithItsNames(): void
    {
        $booking = $this->makeBooking();

        $found = $this->bookings->adminFind((int) $booking['id']);

        $this->assertSame($booking['id'], $found['id']);
        $this->assertArrayHasKey('category_name', $found);
        $this->assertNull($this->bookings->adminFind(999999));
    }

    public function testSearchingByReferenceOrByNumberFindsThatBooking(): void
    {
        $this->makeBooking(['sender_phone' => '0700000001']);
        $target = $this->makeBooking(['sender_phone' => '0700000002']);

        foreach ([cbs_booking_reference((int) $target['id']), strtolower(cbs_booking_reference((int) $target['id'])), (string) $target['id'], 'CBS' . $target['id']] as $query) {
            $ids = array_map('intval', array_column($this->bookings->adminSearch(['q' => $query], 50, 0), 'id'));

            $this->assertContains((int) $target['id'], $ids, "Searching for '{$query}' should find booking {$target['id']}.");
        }
    }

    public function testSearchingMatchesNamesPhoneAndMessage(): void
    {
        $this->makeBooking(['sender_name' => 'Nakato Grace', 'recipient_name' => 'Aunt Joy', 'sender_phone' => '0772111222', 'message_text' => 'Happy birthday']);
        $this->makeBooking(['sender_name' => 'Okello Peter', 'recipient_name' => 'Brian', 'sender_phone' => '0701999888', 'message_text' => 'Congratulations on the baby']);

        $find = fn (string $q): array => array_column($this->bookings->adminSearch(['q' => $q], 50, 0), 'sender_name');

        $this->assertSame(['Nakato Grace'], $find('nakato'));
        $this->assertSame(['Nakato Grace'], $find('AUNT joy'), 'Case does not matter.');
        $this->assertSame(['Okello Peter'], $find('0701999'));
        $this->assertSame(['Okello Peter'], $find('the baby'));
        $this->assertSame([], $find('nobody has this'));
    }

    public function testWildcardCharactersInASearchAreTakenLiterally(): void
    {
        // The regression: "%" and "_" typed into the search box once matched everything.
        $this->makeBooking(['message_text' => 'Save 100% today', 'recipient_name' => 'Percent']);
        $this->makeBooking(['message_text' => 'Save 1000 today', 'recipient_name' => 'NoPercent']);
        $this->makeBooking(['message_text' => 'file a_b here', 'recipient_name' => 'Underscore']);
        $this->makeBooking(['message_text' => 'file axb here', 'recipient_name' => 'NoUnderscore']);

        $find = fn (string $q): array => array_column($this->bookings->adminSearch(['q' => $q], 50, 0), 'recipient_name');

        $this->assertSame(['Percent'], $find('100%'));
        $this->assertSame(['Percent'], $find('%'), 'A lone % means the character, not "anything".');
        $this->assertSame(['Underscore'], $find('a_b'));
        $this->assertSame(['Underscore'], $find('_'));
        $this->assertSame(1, $this->bookings->adminCount(['q' => '%']));
    }

    public function testSearchTextIsNeverAbleToInjectSql(): void
    {
        $this->makeBooking();

        $this->assertSame([], $this->bookings->adminSearch(['q' => "x' OR '1'='1"], 50, 0));
        $this->assertSame([], $this->bookings->adminSearch(['q' => "'; DROP TABLE bookings; --"], 50, 0));
        $this->assertSame(1, $this->db->table('bookings')->countAllResults(), 'The table is still there with its row.');
    }

    public function testFiltersNarrowTheResults(): void
    {
        $birthdays = $this->makeCategory();
        $weddings  = $this->makeCategory();

        $a = $this->makeBooking(['category_id' => $birthdays['id'], 'status' => BookingModel::STATUS_APPROVED]);
        $b = $this->makeBooking(['category_id' => $birthdays['id'], 'status' => BookingModel::STATUS_REJECTED]);
        $c = $this->makeBooking(['category_id' => $weddings['id'], 'status' => BookingModel::STATUS_APPROVED]);

        $ids = fn (array $filters): array => array_map('intval', array_column($this->bookings->adminSearch($filters, 50, 0), 'id'));

        $this->assertEqualsCanonicalizing([(int) $a['id'], (int) $c['id']], $ids(['status' => 'approved']));
        $this->assertEqualsCanonicalizing([(int) $a['id'], (int) $b['id']], $ids(['category_id' => $birthdays['id']]));
        $this->assertSame([(int) $a['id']], $ids(['status' => 'approved', 'category_id' => $birthdays['id']]));
    }

    public function testTheDateRangeIncludesBothEndDaysWhole(): void
    {
        $early  = $this->makeBooking(['recipient_name' => 'Early']);
        $start  = $this->makeBooking(['recipient_name' => 'Start of range']);
        $end    = $this->makeBooking(['recipient_name' => 'End of range']);
        $late   = $this->makeBooking(['recipient_name' => 'Late']);

        $this->backdate($early['id'], '2026-03-09 23:59:59');
        $this->backdate($start['id'], '2026-03-10 00:00:00');
        $this->backdate($end['id'], '2026-03-12 23:59:59');
        $this->backdate($late['id'], '2026-03-13 00:00:00');

        $names = array_column($this->bookings->adminSearch(['date_from' => '2026-03-10', 'date_to' => '2026-03-12'], 50, 0), 'recipient_name');

        $this->assertSame(['End of range', 'Start of range'], $names);
    }

    public function testResultsArePagedNewestFirstAndTheCountIgnoresPaging(): void
    {
        $ids = [];

        for ($i = 1; $i <= 5; $i++) {
            $ids[] = (int) $this->makeBooking(['recipient_name' => "Booking {$i}"])['id'];
        }

        $page1 = array_map('intval', array_column($this->bookings->adminSearch([], 2, 0), 'id'));
        $page2 = array_map('intval', array_column($this->bookings->adminSearch([], 2, 2), 'id'));
        $page3 = array_map('intval', array_column($this->bookings->adminSearch([], 2, 4), 'id'));

        $this->assertSame([$ids[4], $ids[3]], $page1);
        $this->assertSame([$ids[2], $ids[1]], $page2);
        $this->assertSame([$ids[0]], $page3);
        $this->assertSame(5, $this->bookings->adminCount([]));
    }

    public function testACountAndASearchWithTheSameFiltersAgree(): void
    {
        foreach (['Grace', 'Grace', 'Peter'] as $name) {
            $this->makeBooking(['sender_name' => $name]);
        }

        $filters = ['q' => 'grace'];

        $this->assertSame(count($this->bookings->adminSearch($filters, 50, 0)), (new BookingModel())->adminCount($filters));
    }

    // ------------------------------------------------------------------ validation

    public function testABookingMustHaveTheDetailsAStaffMemberNeeds(): void
    {
        foreach (['sender_name', 'sender_phone', 'recipient_name', 'message_text'] as $field) {
            $category = $this->makeCategory();
            $tier     = $this->makeTier($category['id']);
            $data     = [
                'category_id' => $category['id'], 'rate_tier_id' => $tier['id'], 'sender_name' => 'A', 'sender_phone' => '0700000000',
                'recipient_name' => 'B', 'message_text' => 'C', 'amount' => 100, 'currency' => 'UGX',
            ];
            $data[$field] = '';

            $this->assertFalse($this->bookings->insert($data), "A booking with no {$field} should be refused.");
            $this->assertArrayHasKey($field, $this->bookings->errors());
        }
    }

    public function testAStatusMustBeOneOfTheKnownOnes(): void
    {
        $booking = $this->makeBooking();

        $this->assertFalse($this->bookings->update($booking['id'], ['status' => 'teleported']));
    }
}
