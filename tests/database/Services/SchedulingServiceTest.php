<?php

namespace Tests\Database\Services;

use App\Models\ShowSlotModel;
use App\Services\SchedulingService;
use Tests\Support\TestCase\AppTestCase;

/**
 * Reserving a place on a show for a day, and giving it back.
 *
 * @internal
 */
final class SchedulingServiceTest extends AppTestCase
{
    private SchedulingService $scheduling;

    protected function setUp(): void
    {
        parent::setUp();

        config('CbsRadio')->defaultSlotCapacity = 2;
        $this->scheduling = new SchedulingService();
    }

    public function testTheFirstReservationCreatesTheDayWithTheConfiguredCapacity(): void
    {
        $show = $this->makeShow();

        $result = $this->scheduling->reserveSlot($show['id'], '2026-12-24');

        $this->assertTrue($result['success']);
        $this->assertSame('Slot reserved.', $result['message']);
        $this->assertSame(2, (int) $result['slot']['capacity']);
        $this->assertSame(1, (int) $result['slot']['booked_count']);
    }

    public function testEachReservationUsesUpOnePlaceUntilTheDayIsFull(): void
    {
        $show = $this->makeShow();

        $this->assertTrue($this->scheduling->reserveSlot($show['id'], '2026-12-24')['success']);
        $this->assertTrue($this->scheduling->reserveSlot($show['id'], '2026-12-24')['success']);

        $third = $this->scheduling->reserveSlot($show['id'], '2026-12-24');

        $this->assertFalse($third['success']);
        $this->assertSame('This show is fully booked for the selected date.', $third['message']);
        $this->assertSame(2, (int) (new ShowSlotModel())->find($third['slot']['id'])['booked_count'], 'A refused booking uses up nothing.');
    }

    public function testAFullDayDoesNotAffectTheNextDayOrAnotherShow(): void
    {
        $morning = $this->makeShow();
        $evening = $this->makeShow();

        $this->scheduling->reserveSlot($morning['id'], '2026-12-24');
        $this->scheduling->reserveSlot($morning['id'], '2026-12-24');

        $this->assertTrue($this->scheduling->reserveSlot($morning['id'], '2026-12-25')['success']);
        $this->assertTrue($this->scheduling->reserveSlot($evening['id'], '2026-12-24')['success']);
    }

    public function testADayAlreadyBookedKeepsItsCapacityWhenTheDefaultChanges(): void
    {
        $show = $this->makeShow();
        $this->scheduling->reserveSlot($show['id'], '2026-12-24');

        config('CbsRadio')->defaultSlotCapacity = 50;

        $again = (new SchedulingService())->reserveSlot($show['id'], '2026-12-24');

        $this->assertSame(2, (int) $again['slot']['capacity']);
    }

    public function testAClosedDayTakesNoBookings(): void
    {
        $show = $this->makeShow();
        $slot = (new ShowSlotModel())->findOrCreateForDate($show['id'], '2026-12-24', 10);
        (new ShowSlotModel())->update($slot['id'], ['is_open' => 0]);

        $result = $this->scheduling->reserveSlot($show['id'], '2026-12-24');

        $this->assertFalse($result['success']);
        $this->assertSame('This show is fully booked for the selected date.', $result['message']);
    }

    public function testAnInactiveOrUnknownShowCannotBeBooked(): void
    {
        $retired = $this->makeShow(['is_active' => 0]);

        foreach ([$retired['id'], 999999] as $showId) {
            $result = $this->scheduling->reserveSlot($showId, '2026-12-24');

            $this->assertFalse($result['success']);
            $this->assertSame('This show is not available for booking.', $result['message']);
            $this->assertNull($result['slot']);
        }

        $this->assertSame(0, $this->db->table('show_slots')->countAllResults(), 'A refused show must not leave an empty day behind.');
    }

    public function testReleasingAPlaceMakesRoomAgain(): void
    {
        $show = $this->makeShow();
        $this->scheduling->reserveSlot($show['id'], '2026-12-24');
        $full = $this->scheduling->reserveSlot($show['id'], '2026-12-24');

        $this->assertFalse($this->scheduling->reserveSlot($show['id'], '2026-12-24')['success']);

        $this->assertTrue($this->scheduling->releaseSlot((int) $full['slot']['id']));

        $this->assertTrue($this->scheduling->reserveSlot($show['id'], '2026-12-24')['success']);
    }

    public function testShowsForADayAreTheActiveOnesThatRunThatDay(): void
    {
        $this->makeShow(['name' => 'Every day']);
        $this->makeShow(['name' => 'Weekdays', 'days_of_week' => '1,2,3,4,5']);
        $this->makeShow(['name' => 'Weekend', 'days_of_week' => '6,7']);
        $this->makeShow(['name' => 'Retired', 'is_active' => 0]);

        $names = fn (int $isoDay): array => array_column($this->scheduling->showsForDay($isoDay), 'name');

        $this->assertSame(['Every day', 'Weekdays'], $names(3), 'Wednesday');
        $this->assertSame(['Every day', 'Weekend'], $names(7), 'Sunday');
    }

    public function testAShowCannotBeReservedOnADayItIsNotOnAir(): void
    {
        $show = $this->makeShow(['name' => 'Weekend Vibes', 'days_of_week' => '6,7']);

        $monday   = $this->scheduling->reserveSlot($show['id'], '2026-12-21'); // a Monday
        $saturday = $this->scheduling->reserveSlot($show['id'], '2026-12-26');

        $this->assertFalse($monday['success']);
        $this->assertSame('Weekend Vibes is not on air on Mondays. Please choose another day or show.', $monday['message']);
        $this->assertTrue($saturday['success']);
        $this->assertSame(1, $this->db->table('show_slots')->countAllResults(), 'No slot is made for the day it does not run.');
    }
}
