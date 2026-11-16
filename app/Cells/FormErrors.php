<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * Renders the validation-error alert shown at the top of a form after a
 * failed submit. Reads the 'errors' flashdata set by every admin
 * controller's `redirect()->back()->with('errors', ...)`, so a form view
 * just drops this in with no props.
 *
 *   <?= view_cell('App\Cells\FormErrors::render') ?>
 */
class FormErrors extends Cell
{
    public function render(): string
    {
        $errors = session('errors');

        if (empty($errors) || ! is_array($errors)) {
            return '';
        }

        $items = '';

        foreach ($errors as $error) {
            $items .= '<li>' . esc((string) $error) . '</li>';
        }

        return '<div class="alert alert-danger"><ul class="mb-0">' . $items . '</ul></div>';
    }
}
