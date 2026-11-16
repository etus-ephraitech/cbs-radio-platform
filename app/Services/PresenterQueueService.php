<?php

namespace App\Services;

use App\Models\BookingModel;
use App\Models\ShowModel;
use App\Models\ShowSlotModel;

/**
 * Builds the presenter portal's live queue: approved bookings for a given
 * show, grouped by today's (or a chosen) slot, oldest-submitted first so
 * presenters work through them in a predictable order.
 */
class PresenterQueueService
{
    protected BookingModel $bookingModel;
    protected ShowModel $showModel;
    protected ShowSlotModel $showSlotModel;

    public function __construct(
        ?BookingModel $bookingModel = null,
        ?ShowModel $showModel = null,
        ?ShowSlotModel $showSlotModel = null
    ) {
        $this->bookingModel  = $bookingModel ?? new BookingModel();
        $this->showModel     = $showModel ?? new ShowModel();
        $this->showSlotModel = $showSlotModel ?? new ShowSlotModel();
    }

    /**
     * The approved queue for a show on a given date (defaults to today).
     * Returns an empty array if no slot exists yet for that date — that
     * just means nothing has been booked into it.
     */
    public function queueForShow(int $showId, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        $slot = $this->showSlotModel
            ->where('show_id', $showId)
            ->where('slot_date', $date)
            ->first();

        if ($slot === null) {
            return [];
        }

        return $this->bookingModel->approvedForSlot((int) $slot['id']);
    }

    /**
     * Every active show with today's queue attached — the presenter
     * portal's default landing view (one feed per show the presenter
     * covers, rather than picking a show first).
     */
    public function todaysQueuesByShow(): array
    {
        $shows  = $this->showModel->active();
        $result = [];

        foreach ($shows as $show) {
            $result[] = [
                'show'  => $show,
                'queue' => $this->queueForShow((int) $show['id']),
            ];
        }

        return $result;
    }
}
