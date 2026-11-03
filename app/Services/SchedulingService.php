<?php

namespace App\Services;

use App\Models\ShowModel;
use App\Models\ShowSlotModel;
use Config\CbsRadio;

/**
 * Owns all show-slot capacity logic: finding/creating the slot for a date,
 * and reserving/releasing a spot in it. BookingService calls this rather
 * than touching ShowSlotModel directly, so capacity rules live in one place.
 */
class SchedulingService
{
    protected ShowModel $showModel;
    protected ShowSlotModel $showSlotModel;
    protected CbsRadio $config;

    public function __construct(
        ?ShowModel $showModel = null,
        ?ShowSlotModel $showSlotModel = null,
        ?CbsRadio $config = null
    ) {
        $this->showModel     = $showModel ?? new ShowModel();
        $this->showSlotModel = $showSlotModel ?? new ShowSlotModel();
        $this->config        = $config ?? config('CbsRadio');
    }

    /**
     * Reserve a spot in a show's slot for a given date. Creates the slot
     * row on first use for that date. Runs inside its own transaction so
     * two simultaneous bookings can't both squeeze into the last spot.
     *
     * @return array{success: bool, message: string, slot: ?array}
     */
    public function reserveSlot(int $showId, string $date): array
    {
        $show = $this->showModel->find($showId);

        if ($show === null || (int) $show['is_active'] !== 1) {
            return ['success' => false, 'message' => 'This show is not available for booking.', 'slot' => null];
        }

        $db = db_connect();
        $db->transStart();

        $slot = $this->showSlotModel->findOrCreateForDate($showId, $date, $this->config->defaultSlotCapacity);

        if (! $this->showSlotModel->hasCapacity($slot)) {
            $db->transComplete();

            return ['success' => false, 'message' => 'This show is fully booked for the selected date.', 'slot' => $slot];
        }

        $this->showSlotModel->incrementBooked($slot['id']);
        $db->transComplete();

        if ($db->transStatus() === false) {
            $db->resetTransStatus(); // strict mode would otherwise fail every later transaction on this connection
            return ['success' => false, 'message' => 'Could not reserve the slot, please try again.', 'slot' => null];
        }

        return ['success' => true, 'message' => 'Slot reserved.', 'slot' => $this->showSlotModel->find($slot['id'])];
    }

    /**
     * Release a previously reserved spot — call when a booking tied to a
     * slot is cancelled or rejected.
     */
    public function releaseSlot(int $slotId): bool
    {
        return $this->showSlotModel->decrementBooked($slotId);
    }

    /**
     * Shows available for a given day (1=Mon..7=Sun) for the booking
     * flow's time-slot picker.
     */
    public function showsForDay(int $isoDayOfWeek): array
    {
        $shows = $this->showModel->active();

        return array_values(array_filter(
            $shows,
            fn (array $show) => $this->showModel->runsOn($show, $isoDayOfWeek)
        ));
    }
}
