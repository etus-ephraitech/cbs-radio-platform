<?php
/**
 * Page header for the original admin (layouts/admin). Rendered by
 * App\Cells\PageHeader; see that class for the variables.
 *
 * @var string $title
 * @var string $subtitle
 * @var string $backUrl
 * @var string $backLabel
 * @var string $actionUrl
 * @var string $actionLabel
 * @var string $secondaryActionUrl
 * @var string $secondaryActionLabel
 */
?>
<div class="page-header d-flex justify-content-between align-items-start gap-3">
    <div>
        <?php if ($backUrl !== ''): ?>
            <p class="mb-1"><a href="<?= esc($backUrl, 'attr') ?>" class="text-muted small text-decoration-none">&larr; <?= esc($backLabel) ?></a></p>
        <?php endif; ?>
        <h1 class="h3 mb-1"><?= esc($title) ?></h1>
        <?php if ($subtitle !== ''): ?>
            <p class="text-muted mb-0 small"><?= esc($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($actionUrl !== '' || $secondaryActionUrl !== ''): ?>
        <div class="d-flex gap-2">
            <?php if ($secondaryActionUrl !== ''): ?>
                <a href="<?= esc($secondaryActionUrl, 'attr') ?>" class="btn btn-outline-secondary btn-sm text-nowrap"><?= esc($secondaryActionLabel) ?></a>
            <?php endif; ?>
            <?php if ($actionUrl !== ''): ?>
                <a href="<?= esc($actionUrl, 'attr') ?>" class="btn btn-cbs-primary btn-sm text-nowrap"><?= esc($actionLabel) ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
