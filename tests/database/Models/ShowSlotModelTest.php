<?php

namespace Tests\Database\Models;

use App\Models\ShowSlotModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Tests\Support\TestCase\AppTestCase;

/**
 * A slot is one show on one date, with a capacity that bookings use up.
 *
 * @internal
 */
final class ShowSlotModelTest extends AppTestCase
{
    private ShowSlotModel $slots;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slots = new ShowSlotModel();
    }

    public function testASlotIsCreatedTheFirstTimeADateIsAskedFor(): void
    {
        $show = $this->makeShow();

        $slot = $this->slots->findOrCreateForDate($show['id'], '2026-12-24');

        $this->assertSame((int) $show['id'], (int) $slot['show_id']);
        $this->assertSame('2026-12-24', $slot['slot_date']);
        $this->assertSame(20, (int) $slot['capacity'], 'The default capacity is 20.');
        $this->assertSame(0, (int) $slot['booked_count']);
        $this->assertSame(1, (int) $slot['is_open']);
    }

    public function testTheCapacityGivenIsUsedForANewSlot(): void
    {
        $slot = $this->slots->findOrCreateForDate($this->makeShow()['id'], '2026-12-24', 3);

        $this->assertSame(3, (int) $slot['capacity']);
    }

    public function testAskingAgainForTheSameDayReturnsTheSameSlotAndKeepsItsCapacity(): void
    {
        $show = $this->makeShow();

        $first  = $this->slots->findOrCreateForDate($show['id'], '2026-12-24', 3);
        $second = $this->slots->findOrCreateForDate($show['id'], '2026-12-24', 99);

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(3, (int) $second['capacity'], 'A day already booked keeps its capacity when the default changes.');
        $this->assertSame(1, $this->db->table('show_slots')->countAllResults());
    }

    public function testDifferentDatesAndDifferentShowsGetTheirOwnSlots(): void
    {
        $morning = $this->makeShow();
        $evening = $this->makeShow();

        $ids = [
            $this->slots->findOrCreateForDate($morning['id'], '2026-12-24')['id'],
            $this->slots->findOrCreateForDate($morning['id'], '2026-12-25')['id'],
            $this->slots->findOrCreateForDate($evening['id'], '2026-12-24')['id'],
        ];

        $this->assertCount(3, array_unique($ids));
    }

    public function testTheDatabaseRefusesTwoSlotsForTheSameShowAndDate(): void
    {
        $show = $this->makeShow();
        $this->slots->insert(['show_id' => $show['id'], 'slot_date' => '2026-12-24', 'capacity' => 5]);

        $this->expectException(DatabaseException::class);

        $this->slots->insert(['show_id' => $show['id'], 'slot_date' => '2026-12-24', 'capacity' => 5]);
    }

    public function testCapacityRemainsWhileFewerAreBookedThanTheLimit(): void
    {
        $this->assertTrue($this->slots->hasCapacity(['is_open' => 1, 'booked_count' => 4, 'capacity' => 5]));
        $this->assertFalse($this->slots->hasCapacity(['is_open' => 1, 'booked_count' => 5, 'capacity' => 5]));
        $this->assertFalse($this->slots->hasCapacity(['is_open' => 1, 'booked_count' => 9, 'capacity' => 5]), 'Overbooked is still full.');
    }

    public function testAClosedSlotHasNoCapacityHoweverEmpty(): void
    {
        $this->assertFalse($this->slots->hasCapacity(['is_open' => 0, 'booked_count' => 0, 'capacity' => 5]));
    }

    public function testAZeroCapacitySlotIsAlwaysFull(): void
    {
        $this->assertFalse($this->slots->hasCapacity(['is_open' => 1, 'booked_count' => 0, 'capacity' => 0]));
    }

    public function testBookingsAreCountedUpAndDown(): void
    {
        $slot = $this->slots->findOrCreateForDate($this->makeShow()['id'], '2026-12-24', 5);

        $this->assertTrue($this->slots->incrementBooked($slot['id']));
        $this->assertTrue($this->slots->incrementBooked($slot['id']));
        $this->assertSame(2, (int) $this->slots->find($slot['id'])['booked_count']);

        $this->assertTrue($this->slots->decrementBooked($slot['id']));
        $this->assertSame(1, (int) $this->slots->find($slot['id'])['booked_count']);
    }

    public function testTheCountNeverGoesBelowZero(): void
    {
        $slot = $this->slots->findOrCreateForDate($this->makeShow()['id'], '2026-12-24', 5);

        $this->slots->decrementBooked($slot['id']);
        $this->slots->decrementBooked($slot['id']);

        $this->assertSame(0, (int) $this->slots->find($slot['id'])['booked_count']);
    }

    public function testCountingUsesTheDatabaseValueNotAStaleCopy(): void
    {
        // Two requests holding the same old copy of the row must still add up to two.
        $slot = $this->slots->findOrCreateForDate($this->makeShow()['id'], '2026-12-24', 5);

        $staleCopyA = $this->slots->find($slot['id']);
        $staleCopyB = $this->slots->find($slot['id']);

        $this->slots->incrementBooked($staleCopyA['id']);
        $this->slots->incrementBooked($staleCopyB['id']);

        $this->assertSame(2, (int) $this->slots->find($slot['id'])['booked_count']);
    }
}
