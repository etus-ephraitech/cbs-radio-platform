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
        'booking_id'       => 'permit_empty|is_natural_no_zero',
        'gift_id'          => 'required|is_natural_no_zero',
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
