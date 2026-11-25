<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * The Deactivate / Activate button pair used on every reference-data list
 * (categories, shows, packages, gifts, vendors). Posts to
 * {baseUrl}/{id}/deactivate or {baseUrl}/{id}/activate — the route
 * convention all those controllers already follow.
 *
 * Deactivating asks for confirmation by default (SweetAlert2 via
 * ConfirmButton). Pass 'confirm' => false for low-stakes toggles like
 * hiding a single synced vendor gift, where a prompt would just be noise.
 *
 *   <?= view_cell('App\Cells\ActiveToggle::render', [
 *       'baseUrl'      => 'admin/categories',
 *       'id'           => $category['id'],
 *       'isActive'     => $category['is_active'],
 *       'confirmTitle' => 'Deactivate this category?',
 *       'confirmText'  => 'It will no longer appear in the booking flow.',
 *   ]) ?>
 */
class ActiveToggle extends Cell
{
    public string $baseUrl = '';
    public int|string $id = 0;
    public int|string|bool $isActive = 1;
    public string $confirmTitle = 'Deactivate this item?';
    public string $confirmText = '';
    public string $deactivateLabel = 'Deactivate';
    public string $activateLabel = 'Activate';
    public bool $confirm = true;

    public function render(): string
    {
        helper('form');

        $base   = trim($this->baseUrl, '/');
        $id     = (string) $this->id;
        $active = (int) $this->isActive === 1;

        if (! $active) {
            return '<form method="post" class="d-inline" action="' . esc(site_url("{$base}/{$id}/activate"), 'attr') . '">'
                . csrf_field()
                . '<button type="submit" class="btn btn-sm btn-outline-success">' . esc($this->activateLabel) . '</button></form>';
        }

        if (! $this->confirm) {
            return '<form method="post" class="d-inline" action="' . esc(site_url("{$base}/{$id}/deactivate"), 'attr') . '">'
                . csrf_field()
                . '<button type="submit" class="btn btn-sm btn-outline-danger">' . esc($this->deactivateLabel) . '</button></form>';
        }

        // Form ids must be unique on the page, so derive one from the
        // route base + row id.
        $formId = 'toggle-' . str_replace('/', '-', $base) . '-' . $id;

        return '<form id="' . esc($formId, 'attr') . '" method="post" class="d-inline" action="'
            . esc(site_url("{$base}/{$id}/deactivate"), 'attr') . '">' . csrf_field() . '</form>'
            . view_cell('App\Cells\ConfirmButton::render', [
                'formId'            => $formId,
                'title'             => $this->confirmTitle,
                'text'              => $this->confirmText,
                'label'             => $this->deactivateLabel,
                'buttonClass'       => 'btn btn-sm btn-outline-danger',
                'confirmButtonText' => 'Yes, ' . strtolower($this->deactivateLabel),
            ]);
    }
}
