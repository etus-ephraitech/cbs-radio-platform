<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * Prev / "Page X of Y" / Next control for the admin lists that paginate
 * (bookings, notifications). Carries the active filters through every
 * link via $query, so paging never drops a search.
 *
 * Hand-built rather than using CI4's Pager so it needs no Pager template
 * registered in Config\Pager.
 *
 *   <?= view_cell('App\Cells\Pagination::render', [
 *       'page'       => $page,
 *       'totalPages' => $totalPages,
 *       'baseUrl'    => 'admin/bookings',
 *       'query'      => $filters,
 *   ]) ?>
 */
class Pagination extends Cell
{
    public int $page = 1;
    public int $totalPages = 1;
    public string $baseUrl = '';
    public array $query = [];

    public function render(): string
    {
        if ($this->totalPages <= 1) {
            return '';
        }

        $prev = $this->page > 1 ? $this->url($this->page - 1) : null;
        $next = $this->page < $this->totalPages ? $this->url($this->page + 1) : null;

        return '<nav aria-label="Pages"><ul class="pagination pagination-sm mb-0">'
            . $this->item('&laquo; Prev', $prev)
            . '<li class="page-item disabled"><span class="page-link">Page ' . (int) $this->page . ' of ' . (int) $this->totalPages . '</span></li>'
            . $this->item('Next &raquo;', $next)
            . '</ul></nav>';
    }

    protected function url(int $page): string
    {
        $query         = array_filter($this->query, static fn ($v) => $v !== null && $v !== '');
        $query['page'] = $page;

        return site_url(trim($this->baseUrl, '/')) . '?' . http_build_query($query);
    }

    protected function item(string $label, ?string $url): string
    {
        if ($url === null) {
            return '<li class="page-item disabled"><span class="page-link">' . $label . '</span></li>';
        }

        return '<li class="page-item"><a class="page-link" href="' . esc($url, 'attr') . '">' . $label . '</a></li>';
    }
}
