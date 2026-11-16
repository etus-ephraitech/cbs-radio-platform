<?php

namespace App\Models;

use CodeIgniter\Model;

class ModerationLogModel extends Model
{
    protected $table            = 'moderation_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'booking_id',
        'moderator_id',
        'action',
        'notes',
    ];

    // Table has created_at only — a moderation log entry is never edited;
    // a correction is a new entry, not an update.
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'booking_id'   => 'required|is_natural_no_zero',
        'moderator_id' => 'required|is_natural_no_zero',
        'action'       => 'required|in_list[approved,rejected,flagged]',
        'notes'        => 'permit_empty',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function log(int $bookingId, int $moderatorId, string $action, ?string $notes = null): int|string|false
    {
        return $this->insert([
            'booking_id'   => $bookingId,
            'moderator_id' => $moderatorId,
            'action'       => $action,
            'notes'        => $notes,
        ]);
    }

    /**
     * Full moderation history for a booking, oldest first — useful for an
     * audit trail if a decision is later queried.
     */
    public function forBooking(int $bookingId): array
    {
        return $this->where('booking_id', $bookingId)->orderBy('created_at', 'ASC')->findAll();
    }
}
