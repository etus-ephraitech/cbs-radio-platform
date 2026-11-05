<?php
/**
 * Page header for the v1 dashboard theme: the theme's breadcrumb bar, with
 * the page title, an optional subtitle and up to two actions. Rendered by
 * App\Cells\PageHeader; see that class for the variables.
 *
 * @var string $title
 * @var string $subtitle
 * @var string $backUrl
 * @var string $backLabel
 * @var string $actionUrl
 * @var string $actionLabel
 * @var string $actionIcon
 * @var string $secondaryActionUrl
 * @var string $secondaryActionLabel
 * @var string $secondaryActionIcon
 */
$isDashboard = trim(uri_string(), '/') === 'admin';
?>
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <?php if ($isDashboard): ?>
                    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="<?= site_url('admin') ?>">Dashboard</a></li>
                    <?php if ($backUrl !== ''): ?>
                        <li class="breadcrumb-item"><a href="<?= esc($backUrl, 'attr') ?>"><?= esc($backLabel) ?></a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= esc($title) ?></li>
                <?php endif; ?>
            </ol>
        </nav>
        <h1 class="page-title fw-semibold fs-18 mb-0"><?= esc($title) ?></h1>
        <?php if ($subtitle !== ''): ?>
            <p class="text-muted fs-13 mb-0 mt-1"><?= esc($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($actionUrl !== '' || $secondaryActionUrl !== ''): ?>
        <div class="btn-list d-flex flex-wrap gap-2">
            <?php if ($secondaryActionUrl !== ''): ?>
                <a href="<?= esc($secondaryActionUrl, 'attr') ?>" class="btn btn-secondary-light btn-wave">
                    <?php if ($secondaryActionIcon !== ''): ?><i class="<?= esc($secondaryActionIcon, 'attr') ?> me-1 align-middle" aria-hidden="true"></i><?php endif; ?>
                    <?= esc($secondaryActionLabel) ?>
                </a>
            <?php endif; ?>
            <?php if ($actionUrl !== ''): ?>
                <a href="<?= esc($actionUrl, 'attr') ?>" class="btn btn-primary btn-wave">
                    <?php if ($actionIcon !== ''): ?><i class="<?= esc($actionIcon, 'attr') ?> me-1 align-middle" aria-hidden="true"></i><?php endif; ?>
                    <?= esc($actionLabel) ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
