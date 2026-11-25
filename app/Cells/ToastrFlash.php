<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * Renders any queued Toastr flash messages for the current request.
 * Place once near the end of the layout body, after toastr.min.js has
 * loaded. Takes no props — reads directly from session flashdata set as
 * 'toastr_success' / 'toastr_error' / 'toastr_warning' / 'toastr_info'.
 *
 * Tag syntax:
 *   <x:toastr-flash />
 */
class ToastrFlash extends Cell
{
    public function render(): string
    {
        helper('ui');

        return cbs_toastr_flash();
    }
}
