<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * The header block every admin page opens with: optional "back" link,
 * title, optional subtitle, and optional primary action button. Keeps
 * every admin screen's top edge identical so the flow between them feels
 * like one product.
 *
 * Every property needs a default — the framework only fills properties
 * that are initialised (see PropertiesTrait::getPublicProperties()).
 *
 *   <?= view_cell('App\Cells\PageHeader::render', [
 *       'title'       => 'Categories',
 *       'subtitle'    => 'What listeners can book.',
 *       'actionUrl'   => site_url('admin/categories/create'),
 *       'actionLabel' => '+ New category',
 *   ]) ?>
 */
class PageHeader extends Cell
{
    public string $title = '';
    public string $subtitle = '';
    public string $backUrl = '';
    public string $backLabel = 'Back';
    public string $actionUrl = '';
    public string $actionLabel = '';

    public function render(): string
    {
        $html = '<div class="page-header d-flex justify-content-between align-items-start gap-3"><div>';

        if ($this->backUrl !== '') {
            $html .= '<p class="mb-1"><a href="' . esc($this->backUrl, 'attr') . '" class="text-muted small text-decoration-none">&larr; '
                . esc($this->backLabel) . '</a></p>';
        }

        $html .= '<h1 class="h3 mb-1">' . esc($this->title) . '</h1>';

        if ($this->subtitle !== '') {
            $html .= '<p class="text-muted mb-0 small">' . esc($this->subtitle) . '</p>';
        }

        $html .= '</div>';

        if ($this->actionUrl !== '') {
            $html .= '<a href="' . esc($this->actionUrl, 'attr') . '" class="btn btn-cbs-primary btn-sm text-nowrap">'
                . esc($this->actionLabel) . '</a>';
        }

        return $html . '</div>';
    }
}
