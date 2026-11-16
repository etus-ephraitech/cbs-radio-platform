<?php
/**
 * Status filter pills for a v1 list card header.
 *
 * @var array<string, string> $tabs    status value => label; '' is "all"
 * @var string                $current the status being shown ('' for all)
 * @var string                $baseUrl the list's route, e.g. 'admin/payments'
 * @var string                $label   accessible name of the filter
 * @var string                $param   query parameter name (default "status")
 */
$param ??= 'status';
?>
<nav class="nav nav-pills flex-wrap gap-1" aria-label="<?= esc($label, 'attr') ?>">
    <?php foreach ($tabs as $value => $text): ?>
        <?php $active = (string) $current === (string) $value; ?>
        <a href="<?= site_url($baseUrl) . ($value === '' ? '' : '?' . http_build_query([$param => $value])) ?>"
           class="nav-link py-1 px-3 fs-13<?= $active ? ' active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= esc($text) ?></a>
    <?php endforeach; ?>
</nav>
