<?php

namespace Tests\Database\Services;

use App\Models\BookingModel;
use App\Models\ShowSlotModel;
use App\Services\PresenterQueueService;
use Tests\Support\TestCase\AppTestCase;

/**
 * What a presenter reads out: approved greetings for a show on a day, in the order they were made.
 *
 * @internal
 */
final class PresenterQueueServiceTest extends AppTestCase
{
    private PresenterQueueService $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new PresenterQueueService();
    }

    private function slot(int|string $showId, string $date): array
    {
        return (new ShowSlotModel())->findOrCreateForDate((int) $showId, $date, 20);
    }

    public function testAShowWithNoSlotForTheDayHasAnEmptyQueue(): void
    {
        $show = $this->makeShow();

        $this->assertSame([], $this->queue->queueForShow((int) $show['id'], '2026-12-24'));
    }

    public function testTheQueueIsTheApprovedGreetingsForThatShowAndDayInOrder(): void
    {
        $show = $this->makeShow();
        $slot = $this->slot($show['id'], '2026-12-24');

        $first  = $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $slot['id'], 'recipient_name' => 'First']);
        $second = $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $slot['id'], 'recipient_name' => 'Second']);

        $this->assertSame(['First', 'Second'], array_column($this->queue->queueForShow((int) $show['id'], '2026-12-24'), 'recipient_name'));
        $this->assertNotNull($first);
        $this->assertNotNull($second);
    }

    public function testOnlyApprovedGreetingsAreQueued(): void
    {
        $show = $this->makeShow();
        $slot = $this->slot($show['id'], '2026-12-24');

        foreach ([BookingModel::STATUS_PENDING_PAYMENT, BookingModel::STATUS_PENDING_MODERATION, BookingModel::STATUS_REJECTED, BookingModel::STATUS_CANCELLED, BookingModel::STATUS_AIRED] as $status) {
            $this->makeBooking(['status' => $status, 'show_slot_id' => $slot['id']]);
        }

        $this->assertSame([], $this->queue->queueForShow((int) $show['id'], '2026-12-24'), 'Nothing unpaid, unreviewed or already read out may reach a presenter.');
    }

    public function testAnotherShowOrAnotherDayDoesNotLeakIn(): void
    {
        $morning = $this->makeShow();
        $evening = $this->makeShow();

        $here = $this->slot($morning['id'], '2026-12-24');
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $here['id'], 'recipient_name' => 'Here']);
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $this->slot($morning['id'], '2026-12-25')['id'], 'recipient_name' => 'Other day']);
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $this->slot($evening['id'], '2026-12-24')['id'], 'recipient_name' => 'Other show']);

        $this->assertSame(['Here'], array_column($this->queue->queueForShow((int) $morning['id'], '2026-12-24'), 'recipient_name'));
    }

    public function testTheDefaultDayIsToday(): void
    {
        $show = $this->makeShow();
        $slot = $this->slot($show['id'], date('Y-m-d'));
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $slot['id'], 'recipient_name' => 'Today']);

        $this->assertSame(['Today'], array_column($this->queue->queueForShow((int) $show['id']), 'recipient_name'));
    }

    public function testTodaysQueuesAreListedForEveryActiveShow(): void
    {
        $morning = $this->makeShow(['name' => 'Morning', 'start_time' => '06:00:00']);
        $evening = $this->makeShow(['name' => 'Evening', 'start_time' => '16:00:00']);
        $this->makeShow(['name' => 'Retired', 'is_active' => 0]);

        $slot = $this->slot($morning['id'], date('Y-m-d'));
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'show_slot_id' => $slot['id'], 'recipient_name' => 'Early bird']);

        $queues = $this->queue->todaysQueuesByShow();

        $this->assertSame(['Morning', 'Evening'], array_column(array_column($queues, 'show'), 'name'));
        $this->assertSame(['Early bird'], array_column($queues[0]['queue'], 'recipient_name'));
        $this->assertSame([], $queues[1]['queue'], 'A show with nothing booked is still listed, with an empty queue.');
        $this->assertNotNull($evening);
    }
}
