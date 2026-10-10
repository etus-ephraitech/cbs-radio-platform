<?php

namespace App\Models;

use CodeIgniter\Model;

class BookingModel extends Model
{
    protected $table            = 'bookings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    public const STATUS_PENDING_PAYMENT    = 'pending_payment';
    public const STATUS_PENDING_MODERATION = 'pending_moderation';
    public const STATUS_APPROVED           = 'approved';
    public const STATUS_REJECTED           = 'rejected';
    public const STATUS_AIRED              = 'aired';
    public const STATUS_CANCELLED          = 'cancelled';

    /** Every status, in lifecycle order — used for admin filter dropdowns. */
    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PENDING_MODERATION,
        self::STATUS_APPROVED,
        self::STATUS_AIRED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    protected $allowedFields = [
        'category_id',
        'rate_tier_id',
        'show_slot_id',
        'sender_name',
        'sender_phone',
        'sender_email',
        'recipient_name',
        'message_text',
        'voice_note_path',
        'voice_note_duration_seconds',
        'scheduled_date',
        'status',
        'amount',
        'currency',
        'moderated_by',
        'moderation_notes',
        'aired_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'category_id'    => 'required|is_natural_no_zero',
        'rate_tier_id'   => 'required|is_natural_no_zero',
        'show_slot_id'   => 'permit_empty|is_natural_no_zero',
        'sender_name'    => 'required|max_length[150]',
        'sender_phone'   => 'required|max_length[20]',
        'sender_email'   => 'permit_empty|valid_email|max_length[150]',
        'recipient_name' => 'required|max_length[150]',
        'message_text'   => 'required',
        'status'         => 'permit_empty|in_list[pending_payment,pending_moderation,approved,rejected,aired,cancelled]',
        'amount'         => 'required|decimal',
        'currency'       => 'permit_empty|max_length[3]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Bookings awaiting moderation, newest first — feeds the admin
     * moderation queue.
     */
    public function pendingModeration(): array
    {
        return $this->where('status', self::STATUS_PENDING_MODERATION)
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Approved bookings for a given show slot, in submission order — feeds
     * the presenter portal's live queue for that slot.
     */
    public function approvedForSlot(int $showSlotId): array
    {
        return $this->where('show_slot_id', $showSlotId)
            ->where('status', self::STATUS_APPROVED)
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function byStatus(string $status): array
    {
        return $this->where('status', $status)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll();
    }

    /**
     * Approve a booking after moderation. Pair with ModerationLogModel::log()
     * to keep an audit trail of who approved it and why.
     */
    public function markApproved(int $bookingId, int $moderatorId, ?string $notes = null): bool
    {
        return $this->update($bookingId, [
            'status'           => self::STATUS_APPROVED,
            'moderated_by'     => $moderatorId,
            'moderation_notes' => $notes,
        ]);
    }

    /**
     * Reject a booking after moderation. If the booking held a show-slot
     * spot, release it via ShowSlotModel::decrementBooked() in the same
     * transaction as this call.
     */
    public function markRejected(int $bookingId, int $moderatorId, ?string $notes = null): bool
    {
        return $this->update($bookingId, [
            'status'           => self::STATUS_REJECTED,
            'moderated_by'     => $moderatorId,
            'moderation_notes' => $notes,
        ]);
    }

    /**
     * Mark a booking as having aired, stamping the exact time for the
     * sender's confirmation SMS/email.
     */
    public function markAired(int $bookingId): bool
    {
        return $this->update($bookingId, [
            'status'   => self::STATUS_AIRED,
            'aired_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markCancelled(int $bookingId): bool
    {
        return $this->update($bookingId, ['status' => self::STATUS_CANCELLED]);
    }

    /**
     * One page of the admin bookings list, newest first, with the
     * category, package, and show names attached. $filters may contain:
     * q (free text), status, category_id, date_from, date_to (Y-m-d,
     * matched against created_at). Empty keys are ignored; callers are
     * expected to have validated the values.
     */
    public function adminSearch(array $filters, int $limit, int $offset): array
    {
        $this->adminBaseQuery($filters);

        return $this->orderBy('bookings.created_at', 'DESC')
            ->orderBy('bookings.id', 'DESC')
            ->orderBy('bookings.id', 'DESC')
            ->findAll($limit, $offset);
    }

    /**
     * Total rows matching the same filters, for building pagination.
     */
    public function adminCount(array $filters): int
    {
        $this->adminBaseQuery($filters);

        return $this->countAllResults();
    }

    /**
     * A single booking with the same joined names as the list, for the
     * admin detail page.
     */
    public function adminFind(int $bookingId): ?array
    {
        $this->adminBaseQuery([]);

        return $this->where('bookings.id', $bookingId)->first();
    }

    protected function adminBaseQuery(array $filters): void
    {
        $this->select('bookings.*, categories.name AS category_name, rate_tiers.package_level, '
                . 'shows.name AS show_name, show_slots.slot_date')
            ->join('categories', 'categories.id = bookings.category_id')
            ->join('rate_tiers', 'rate_tiers.id = bookings.rate_tier_id')
            ->join('show_slots', 'show_slots.id = bookings.show_slot_id', 'left')
            ->join('shows', 'shows.id = show_slots.show_id', 'left');

        if (! empty($filters['q'])) {
            $q = trim($filters['q']);

            $this->groupStart();

            // "CBS-000012", "cbs12" or a bare "12" also match by booking id.
            if (preg_match('/^(?:CBS-?)?0*(\d+)$/i', $q, $m)) {
                $this->orWhere('bookings.id', (int) $m[1]);
            }

            // Make a literal % or _ in the search text match literally. The
            // query builder adds the ESCAPE clause (via the `true` below)
            // but does not escape the value itself, so do it here, using the
            // connection's own escape character. Quotes are NOT escaped
            // here — the builder's value binding already handles those, and
            // escaping them twice would break names like O'Brien.
            $esc  = $this->db->likeEscapeChar;
            $like = str_replace([$esc, '%', '_'], [$esc . $esc, $esc . '%', $esc . '_'], $q);

            $this->orLike('bookings.sender_name', $like, 'both', true)
                ->orLike('bookings.sender_phone', $like, 'both', true)
                ->orLike('bookings.recipient_name', $like, 'both', true)
                ->orLike('bookings.message_text', $like, 'both', true)
                ->groupEnd();
        }

        if (! empty($filters['status'])) {
            $this->where('bookings.status', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $this->where('bookings.category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['date_from'])) {
            $this->where('bookings.created_at >=', $filters['date_from'] . ' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $this->where('bookings.created_at <=', $filters['date_to'] . ' 23:59:59');
        }
    }
}
