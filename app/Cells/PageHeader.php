<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * The header block every staff page opens with: optional "back" link,
 * title, optional subtitle, and up to two action buttons. Keeps every
 * screen's top edge identical so the flow between them feels like one
 * product.
 *
 * The markup lives in a view so each UI version can draw it its own way:
 * cells/page_header (the original admin) and v1/cells/page_header (the
 * dashboard theme, with a breadcrumb), picked by ui_view().
 *
 * Every property needs a default — the framework only fills properties
 * that are initialised (see PropertiesTrait::getPublicProperties()).
 *
 *   <?= view_cell('App\Cells\PageHeader::render', [
 *       'title'       => 'Categories',
 *       'subtitle'    => 'What listeners can book.',
 *       'actionUrl'   => site_url('admin/categories/create'),
 *       'actionLabel' => 'New category',
 *       'actionIcon'  => 'ri-add-line',          // v1 only; a Remix/Tabler/Bootstrap icon class
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
    public string $actionIcon = '';

    public string $secondaryActionUrl = '';
    public string $secondaryActionLabel = '';
    public string $secondaryActionIcon = '';

    public function render(): string
    {
        helper('ui');

        return ui_view('cells/page_header', [
            'title'                => $this->title,
            'subtitle'             => $this->subtitle,
            'backUrl'              => $this->backUrl,
            'backLabel'            => $this->backLabel,
            'actionUrl'            => $this->actionUrl,
            'actionLabel'          => $this->actionLabel,
            'actionIcon'           => $this->actionIcon,
            'secondaryActionUrl'   => $this->secondaryActionUrl,
            'secondaryActionLabel' => $this->secondaryActionLabel,
            'secondaryActionIcon'  => $this->secondaryActionIcon,
        ], ['saveData' => false, 'debug' => false]);
    }
}
