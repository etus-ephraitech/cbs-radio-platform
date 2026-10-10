<?php

namespace App\Models;

use CodeIgniter\Model;

class ShowSlotModel extends Model
{
    protected $table            = 'show_slots';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'show_id',
        'slot_date',
        'capacity',
        'booked_count',
        'is_open',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'show_id'  => 'required|is_natural_no_zero',
        'slot_date' => 'required|valid_date',
        'capacity' => 'permit_empty|is_natural',
        'is_open'  => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Fetch the slot for a show on a given date, creating it with the
     * default capacity if it doesn't exist yet. Scheduling only ever
     * creates slots on demand rather than pre-generating a calendar.
     */
    public function findOrCreateForDate(int $showId, string $slotDate, int $defaultCapacity = 20): array
    {
        $slot = $this->where('show_id', $showId)
            ->where('slot_date', $slotDate)
            ->first();

        if ($slot !== null) {
            return $slot;
        }

        $id = $this->insert([
            'show_id'      => $showId,
            'slot_date'    => $slotDate,
            'capacity'     => $defaultCapacity,
            'booked_count' => 0,
            'is_open'      => 1,
        ]);

        return $this->find($id);
    }

    /**
     * Whether a slot still has room for another booking.
     */
    public function hasCapacity(array $slot): bool
    {
        return (int) $slot['is_open'] === 1 && (int) $slot['booked_count'] < (int) $slot['capacity'];
    }

    /**
     * Atomically increments booked_count. Call this inside the same
     * transaction as creating the booking row, after confirming
     * hasCapacity() to avoid a race between two simultaneous bookings.
     */
    public function incrementBooked(int $slotId): bool
    {
        return $this->builder()
            ->where('id', $slotId)
            ->set('booked_count', 'booked_count + 1', false)
            ->update();
    }

    /**
     * Releases a spot — call when a booking is cancelled or rejected after
     * having occupied a slot.
     */
    public function decrementBooked(int $slotId): bool
    {
        return $this->builder()
            ->where('id', $slotId)
            ->where('booked_count >', 0)
            ->set('booked_count', 'booked_count - 1', false)
            ->update();
    }
}
