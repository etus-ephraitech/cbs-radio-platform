<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationsLogModel extends Model
{
    protected $table            = 'notifications_log';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'booking_id',
        'channel',
        'recipient',
        'message',
        'status',
        'sent_at',
    ];

    // Table has created_at only, no updated_at — a log row is never updated.
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'booking_id' => 'permit_empty|is_natural_no_zero',
        'channel'    => 'required|in_list[sms,email]',
        'recipient'  => 'required|max_length[150]',
        'message'    => 'required',
        'status'     => 'permit_empty|in_list[sent,failed]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    public function logSent(?int $bookingId, string $channel, string $recipient, string $message): int|string|false
    {
        return $this->insert([
            'booking_id' => $bookingId,
            'channel'    => $channel,
            'recipient'  => $recipient,
            'message'    => $message,
            'status'     => 'sent',
            'sent_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function logFailed(?int $bookingId, string $channel, string $recipient, string $message): int|string|false
    {
        return $this->insert([
            'booking_id' => $bookingId,
            'channel'    => $channel,
            'recipient'  => $recipient,
            'message'    => $message,
            'status'     => 'failed',
        ]);
    }

    public function forBooking(int $bookingId): array
    {
        return $this->where('booking_id', $bookingId)->orderBy('created_at', 'DESC')->findAll();
    }

    /**
     * One page of the admin log, newest first. $filters may contain
     * status, channel, and booking_id — any omitted/empty key is ignored.
     * Callers are expected to have validated the values already.
     */
    public function filtered(array $filters, int $limit, int $offset): array
    {
        $this->applyFilters($filters);

        return $this->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll($limit, $offset);
    }

    /**
     * Total rows matching the same filters, for building pagination.
     */
    public function countFiltered(array $filters): int
    {
        $this->applyFilters($filters);

        return $this->countAllResults();
    }

    protected function applyFilters(array $filters): void
    {
        if (! empty($filters['status'])) {
            $this->where('status', $filters['status']);
        }

        if (! empty($filters['channel'])) {
            $this->where('channel', $filters['channel']);
        }

        if (! empty($filters['booking_id'])) {
            $this->where('booking_id', (int) $filters['booking_id']);
        }
    }
}
