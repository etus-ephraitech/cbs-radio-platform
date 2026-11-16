<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * Renders a human-readable relative time ("5 minutes ago"), with the raw
 * timestamp kept in a title attribute for hover/accessibility. Used in the
 * presenter portal's live queue and admin activity lists.
 *
 * Tag syntax:
 *   <x:time-ago datetime="<?= $booking['created_at'] ?>" />
 */
class TimeAgo extends Cell
{
    public string $datetime = '';

    public function render(): string
    {
        helper('ui');

        return '<span title="' . esc($this->datetime, 'attr') . '">'
            . esc(cbs_time_ago($this->datetime)) . '</span>';
    }
}
