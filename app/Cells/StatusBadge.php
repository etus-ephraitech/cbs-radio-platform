<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * Reusable status badge. Delegates to the ui_helper badge functions so
 * there's one source of truth for status -> badge mapping, whether it's
 * called as a cell or via the helper directly.
 *
 * Tag syntax:
 *   <x:status-badge type="booking" status="<?= $booking['status'] ?>" />
 *   <x:status-badge type="payment" status="<?= $payment['status'] ?>" />
 *   <x:status-badge type="gift-order" status="<?= $order['status'] ?>" />
 *   <x:status-badge type="notification" status="<?= $log['status'] ?>" />
 *   <x:status-badge type="active" status="<?= $row['is_active'] ?>" />
 *
 * Function syntax (always available, no setup required):
 *   <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'booking', 'status' => $booking['status']]) ?>
 */
class StatusBadge extends Cell
{
    public string $type = 'booking';
    // string|int|bool because the framework fills cell properties in strict
    // mode: an is_active flag arrives as an int, a status as a string.
    public string|int|bool $status = '';

    public function render(): string
    {
        helper('ui');

        return match ($this->type) {
            'payment'                  => cbs_payment_status_badge($this->status),
            'notification'             => cbs_notification_status_badge($this->status),
            'active'                   => cbs_active_badge($this->status),
            'gift-order', 'gift_order' => cbs_gift_order_status_badge($this->status),
            default                    => cbs_booking_status_badge($this->status),
        };
    }
}
