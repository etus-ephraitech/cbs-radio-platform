<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * A button that pops a SweetAlert2 confirm dialog before submitting an
 * existing form on the page. The cell renders only the button — the
 * <form id="{formId}"> it targets must already exist in the view.
 *
 * Tag syntax:
 *   <form id="reject-form-<?= $booking['id'] ?>" method="post"
 *         action="<?= site_url('admin/bookings/' . $booking['id'] . '/reject') ?>">
 *   </form>
 *   <x:confirm-button
 *     form-id="reject-form-<?= $booking['id'] ?>"
 *     title="Reject this booking?"
 *     text="This cannot be undone."
 *     label="Reject"
 *     button-class="btn btn-sm btn-outline-danger" />
 */
class ConfirmButton extends Cell
{
    public string $formId = '';
    public string $title = '';
    public string $text = '';
    public string $label = 'Confirm';
    public string $buttonClass = 'btn btn-danger';
    public string $confirmButtonText = 'Yes, continue';

    public function render(): string
    {
        helper('ui');

        $onclick = cbs_sweetalert_confirm($this->formId, $this->title, $this->text, $this->confirmButtonText);

        return '<button type="button" class="' . esc($this->buttonClass, 'attr') . '" onclick="'
            . esc($onclick, 'attr') . '">' . esc($this->label) . '</button>';
    }
}
