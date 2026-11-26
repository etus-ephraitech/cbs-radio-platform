<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table            = 'payments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    protected $allowedFields = [
        'booking_id',
        'gift_order_id',
        'provider',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'marked_paid_by',
        'paid_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'booking_id'         => 'permit_empty|is_natural_no_zero',
        'gift_order_id'      => 'permit_empty|is_natural_no_zero',
        'provider'           => 'required|max_length[50]',
        'provider_reference' => 'permit_empty|max_length[150]',
        'amount'             => 'required|decimal',
        'currency'           => 'permit_empty|max_length[3]',
        'status'             => 'permit_empty|in_list[pending,success,failed]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Enforces "exactly one of booking_id / gift_order_id" at the
     * application layer, since a portable CHECK constraint isn't used in
     * the migration. Call this before insert().
     */
    public function validatePayable(array $data): bool
    {
        $hasBooking   = ! empty($data['booking_id']);
        $hasGiftOrder = ! empty($data['gift_order_id']);

        return $hasBooking xor $hasGiftOrder;
    }

    /**
     * The admin list, newest first: filtered by status, provider, a created-between date range, and a search that
     * matches the provider's reference, the provider name, or the payment / booking / gift order number.
     *
     * @param array<string, string> $filters q, status, provider, from, to (already checked by AdminFilters)
     */
    public function adminSearch(array $filters, int $limit, int $offset): array
    {
        $this->applyAdminFilters($filters);

        return $this->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll($limit, $offset);
    }

    public function adminCount(array $filters): int
    {
        $this->applyAdminFilters($filters);

        return $this->countAllResults();
    }

    /**
     * The providers that have taken a payment, for the filter's choices.
     *
     * @return list<string>
     */
    public function providers(): array
    {
        return array_column($this->select('provider')->distinct()->orderBy('provider', 'ASC')->findAll(), 'provider');
    }

    private function applyAdminFilters(array $f): void
    {
        if (($f['status'] ?? '') !== '') {
            $this->where('status', $f['status']);
        }

        if (($f['provider'] ?? '') !== '') {
            $this->where('provider', $f['provider']);
        }

        if (($f['from'] ?? '') !== '') {
            $this->where('created_at >=', $f['from'] . ' 00:00:00');
        }

        if (($f['to'] ?? '') !== '') {
            $this->where('created_at <=', $f['to'] . ' 23:59:59');
        }

        $q = $f['q'] ?? '';

        if ($q !== '') {
            $this->groupStart()->like('provider_reference', $q)->orLike('provider', $q);

            if (ctype_digit($q)) {
                $this->orWhere('id', (int) $q)->orWhere('booking_id', (int) $q)->orWhere('gift_order_id', (int) $q);
            }

            $this->groupEnd();
        }
    }

    /**
     * Mark a payment successful from a Mobile Money callback.
     */
    public function markSuccess(int $paymentId, string $providerReference): bool
    {
        return $this->update($paymentId, [
            'status'             => self::STATUS_SUCCESS,
            'provider_reference' => $providerReference,
            'paid_at'            => date('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(int $paymentId): bool
    {
        return $this->update($paymentId, ['status' => self::STATUS_FAILED]);
    }

    /**
     * Admin manual "mark as paid" fallback, per the agreed scope — a
     * payment hiccup should never block a booking from airing.
     */
    public function markManuallyPaid(int $paymentId, int $adminUserId): bool
    {
        return $this->update($paymentId, [
            'status'          => self::STATUS_SUCCESS,
            'marked_paid_by'  => $adminUserId,
            'paid_at'         => date('Y-m-d H:i:s'),
        ]);
    }

    public function forBooking(int $bookingId): ?array
    {
        return $this->where('booking_id', $bookingId)->first();
    }

    public function forGiftOrder(int $giftOrderId): ?array
    {
        return $this->where('gift_order_id', $giftOrderId)->first();
    }
}
