<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * The search and filter row above an admin list: a text search, drop-downs and date ranges, all as one GET form so
 * a filtered list can be bookmarked or shared, with a Clear link back to the unfiltered list. Pair it with the
 * Pagination cell, passing the same values as its $query so paging keeps the filters.
 *
 * Each field is ['name' => 'q', 'label' => 'Search', 'type' => 'search|select|date', 'placeholder' => '',
 * 'options' => [value => label]] (options for select only; its first, empty choice means "any").
 *
 *   <?= view_cell('App\Cells\FilterBar::render', [
 *       'action' => 'admin/users',
 *       'fields' => [
 *           ['name' => 'q', 'label' => 'Search', 'type' => 'search', 'placeholder' => 'Email'],
 *           ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active']],
 *       ],
 *       'values' => $filters,
 *   ]) ?>
 *
 * The markup is plain Bootstrap, so one view serves the original admin and the v1 theme.
 */
class FilterBar extends Cell
{
    public string $action = '';

    /** @var list<array<string, mixed>> */
    public array $fields = [];

    /** @var array<string, mixed> */
    public array $values = [];

    public function render(): string
    {
        helper('ui');

        $active = array_filter($this->values, static fn ($v): bool => $v !== null && $v !== '' && $v !== 'page');

        return view('cells/filter_bar', [
            'action' => $this->action,
            'fields' => $this->fields,
            'values' => $this->values,
            'active' => count($active),
        ], ['saveData' => false, 'debug' => false]);
    }
}
