<?php

namespace App\Models;

use CodeIgniter\Model;

class GiftOrderModel extends Model
{
    protected $table            = 'gift_orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID            = 'paid';
    public const STATUS_FULFILLED       = 'fulfilled';
    public const STATUS_CANCELLED       = 'cancelled';

    protected $allowedFields = [
        'booking_id',
        'bundled_with_booking',
        'gift_id',
        'sender_name',
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'delivery_details',
        'quantity',
        'amount',
        'currency',
        'status',
        'vendor_order_ref',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'booking_id'           => 'permit_empty|is_natural_no_zero',
        'bundled_with_booking' => 'permit_empty|in_list[0,1]',
        'gift_id'              => 'required|is_natural_no_zero',
        'sender_name'      => 'required|max_length[150]',
        'sender_phone'     => 'required|max_length[20]',
        'recipient_name'   => 'required|max_length[150]',
        'recipient_phone'  => 'permit_empty|max_length[20]',
        'delivery_details' => 'permit_empty',
        'quantity'         => 'permit_empty|is_natural_no_zero',
        'amount'           => 'required|decimal',
        'currency'         => 'permit_empty|max_length[3]',
        'status'           => 'permit_empty|in_list[pending_payment,paid,fulfilled,cancelled]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Standalone gift orders not attached to a greeting booking — the
     * "Send a Gift" quick-action flow from the homepage.
     */
    public function standalone(): array
    {
        return $this->where('booking_id', null)->orderBy('created_at', 'DESC')->findAll();
    }

    public function forBooking(int $bookingId): array
    {
        return $this->where('booking_id', $bookingId)->findAll();
    }

    /**
     * Admin list, newest first, with the gift's name and source attached.
     * Pass a status to filter.
     */
    public function adminList(?string $status = null): array
    {
        $this->select('gift_orders.*, gifts.name AS gift_name, gifts.source AS gift_source')
            ->join('gifts', 'gifts.id = gift_orders.gift_id');

        if ($status) {
            $this->where('gift_orders.status', $status);
        }

        return $this->orderBy('gift_orders.created_at', 'DESC')->orderBy('gift_orders.id', 'DESC')->findAll();
    }

    /**
     * The admin list with filters: status, the gift's source (ours or a vendor's), a created-between date range,
     * and a search over the sender, the recipient, the gift's name, the vendor's order reference and the order or
     * greeting number.
     *
     * @param array<string, string> $filters q, status, source, from, to (already checked by AdminFilters)
     */
    public function adminSearch(array $filters, int $limit, int $offset): array
    {
        $this->select('gift_orders.*, gifts.name AS gift_name, gifts.source AS gift_source')
            ->join('gifts', 'gifts.id = gift_orders.gift_id');
        $this->applyAdminFilters($filters);

        return $this->orderBy('gift_orders.created_at', 'DESC')->orderBy('gift_orders.id', 'DESC')->findAll($limit, $offset);
    }

    public function adminCount(array $filters): int
    {
        $this->join('gifts', 'gifts.id = gift_orders.gift_id');
        $this->applyAdminFilters($filters);

        return $this->countAllResults();
    }

    private function applyAdminFilters(array $f): void
    {
        if (($f['status'] ?? '') !== '') {
            $this->where('gift_orders.status', $f['status']);
        }

        if (($f['source'] ?? '') !== '') {
            $this->where('gifts.source', $f['source']);
        }

        if (($f['from'] ?? '') !== '') {
            $this->where('gift_orders.created_at >=', $f['from'] . ' 00:00:00');
        }

        if (($f['to'] ?? '') !== '') {
            $this->where('gift_orders.created_at <=', $f['to'] . ' 23:59:59');
        }

        $q = $f['q'] ?? '';

        if ($q !== '') {
            $this->groupStart()
                ->like('gift_orders.sender_name', $q)
                ->orLike('gift_orders.sender_phone', $q)
                ->orLike('gift_orders.recipient_name', $q)
                ->orLike('gift_orders.recipient_phone', $q)
                ->orLike('gift_orders.vendor_order_ref', $q)
                ->orLike('gifts.name', $q);

            if (ctype_digit($q)) {
                $this->orWhere('gift_orders.id', (int) $q)->orWhere('gift_orders.booking_id', (int) $q);
            }

            $this->groupEnd();
        }
    }

    /**
     * A booking's gift orders with the gift name attached, for the admin
     * booking detail page.
     */
    public function forBookingWithGift(int $bookingId): array
    {
        return $this->select('gift_orders.*, gifts.name AS gift_name')
            ->join('gifts', 'gifts.id = gift_orders.gift_id')
            ->where('gift_orders.booking_id', $bookingId)
            ->orderBy('gift_orders.created_at', 'ASC')
            ->orderBy('gift_orders.id', 'ASC')
            ->findAll();
    }

    /**
     * The gift orders chosen inside a greeting's form, paid for in that greeting's payment, with the gift's name,
     * type and source attached. Pass a status to filter.
     */
    public function bundledWithBooking(int $bookingId, ?string $status = null): array
    {
        $this->select('gift_orders.*, gifts.name AS gift_name, gifts.type AS gift_type, gifts.source AS gift_source')
            ->join('gifts', 'gifts.id = gift_orders.gift_id')
            ->where('gift_orders.booking_id', $bookingId)
            ->where('gift_orders.bundled_with_booking', 1);

        if ($status !== null) {
            $this->where('gift_orders.status', $status);
        }

        return $this->orderBy('gift_orders.id', 'ASC')->findAll();
    }

    /**
     * What a greeting's bundled gifts still waiting for payment add to that greeting's payment, in its currency.
     * A gift in any other currency can't be added to the same payment, so it is never counted (the checkout refuses
     * to create one).
     */
    public function bundledUnpaidTotal(int $bookingId, string $currency): float
    {
        $row = $this->builder()
            ->selectSum('amount', 'total')
            ->where('booking_id', $bookingId)
            ->where('bundled_with_booking', 1)
            ->where('status', self::STATUS_PENDING_PAYMENT)
            ->where('currency', $currency)
            ->get()
            ->getRowArray();

        return round((float) ($row['total'] ?? 0), 2);
    }

    public function markPaid(int $giftOrderId): bool
    {
        return $this->update($giftOrderId, ['status' => self::STATUS_PAID]);
    }

    public function markFulfilled(int $giftOrderId, ?string $vendorOrderRef = null): bool
    {
        $data = ['status' => self::STATUS_FULFILLED];

        if ($vendorOrderRef !== null) {
            $data['vendor_order_ref'] = $vendorOrderRef;
        }

        return $this->update($giftOrderId, $data);
    }

    public function markCancelled(int $giftOrderId): bool
    {
        return $this->update($giftOrderId, ['status' => self::STATUS_CANCELLED]);
    }
}
