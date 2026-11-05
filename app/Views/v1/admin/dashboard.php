<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'                => 'Dashboard',
    'subtitle'             => date('l, F j'),
    'actionUrl'            => site_url('admin/moderation'),
    'actionLabel'          => 'Review greetings',
    'actionIcon'           => 'ri-shield-check-line',
    'secondaryActionUrl'   => site_url('presenter'),
    'secondaryActionLabel' => 'Presenter portal',
    'secondaryActionIcon'  => 'ri-mic-line',
]) ?>

<?php
// What needs a person today. Each card links to the list that clears it; a non-zero count turns the card's
// accent from neutral to the colour that matches how urgent that queue is.
$queues = [
    [
        'count' => (int) $attention['pendingModeration'],
        'label' => 'Awaiting moderation',
        'hint'  => 'Greetings paid for and waiting for a reviewer.',
        'url'   => 'admin/moderation',
        'icon'  => 'ri-shield-check-line',
        'tone'  => 'warning',
    ],
    [
        'count' => (int) $attention['pendingPayments'],
        'label' => 'Pending payments',
        'hint'  => 'Started but not yet confirmed.',
        'url'   => 'admin/payments?status=pending',
        'icon'  => 'ri-wallet-3-line',
        'tone'  => 'info',
    ],
    [
        'count' => (int) $attention['giftsToFulfill'],
        'label' => 'Gift orders to fulfill',
        'hint'  => 'Paid for and waiting to be delivered.',
        'url'   => 'admin/gift-orders?status=paid',
        'icon'  => 'ri-gift-line',
        'tone'  => 'primary',
    ],
    [
        'count' => (int) $attention['failedNotices24h'],
        'label' => 'Failed notifications (24h)',
        'hint'  => 'Review and re-send, or check the gateway settings in .env.',
        'url'   => 'admin/notifications?status=failed',
        'icon'  => 'ri-notification-off-line',
        'tone'  => 'danger',
    ],
];

$catalogCards = [
    ['label' => 'Active categories', 'count' => (int) $catalog['categories'], 'url' => 'admin/categories', 'icon' => 'ri-folder-3-line', 'emptyHint' => 'No categories — the booking flow will be empty.'],
    ['label' => 'Active packages', 'count' => (int) $catalog['rateTiers'], 'url' => 'admin/rate-tiers', 'icon' => 'ri-stack-line', 'emptyHint' => 'No packages — nothing to select or pay for.'],
    ['label' => 'Active shows', 'count' => (int) $catalog['shows'], 'url' => 'admin/shows', 'icon' => 'ri-radio-2-line', 'emptyHint' => "No shows — scheduling won't offer any slots."],
    ['label' => 'Active gifts', 'count' => (int) $catalog['gifts'], 'url' => 'admin/gifts', 'icon' => 'ri-gift-2-line', 'emptyHint' => 'No gifts — the gift catalog will be empty.'],
    ['label' => 'Active vendors', 'count' => (int) $catalog['vendors'], 'url' => 'admin/gift-vendors', 'icon' => 'ri-store-2-line', 'emptyHint' => null],
];

$waiting       = array_sum(array_column($queues, 'count'));
$warnings      = count(array_filter($health, static fn (array $item): bool => $item['status'] === 'warn'));
$catalogGaps   = count(array_filter($catalogCards, static fn (array $card): bool => $card['count'] === 0 && $card['emptyHint'] !== null));
?>

<form method="get" action="<?= site_url('admin/bookings') ?>" class="card custom-card" role="search">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-3">
        <div class="flex-grow-1">
            <label for="dashboard-search" class="fw-semibold mb-1 d-block">Find a booking</label>
            <span class="text-muted fs-12">Search by reference, recipient or sender name, phone number or message text.</span>
        </div>
        <div class="input-group" style="max-width: 32rem;">
            <span class="input-group-text bg-transparent"><i class="ri-search-line" aria-hidden="true"></i></span>
            <input type="search" id="dashboard-search" name="q" class="form-control" placeholder="e.g. CBS-000123, Nakato, 0772…">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </div>
</form>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <h2 class="fs-16 fw-semibold mb-0">Needs attention</h2>
    <span class="badge <?= $waiting > 0 ? 'bg-warning-transparent' : 'bg-success-transparent' ?> rounded-pill">
        <?= $waiting > 0 ? esc(number_format($waiting)) . ' waiting' : 'All clear' ?>
    </span>
</div>
<div class="row">
    <?php foreach ($queues as $queue): ?>
        <?php $tone = $queue['count'] > 0 ? $queue['tone'] : 'secondary'; ?>
        <div class="col-sm-6 col-xxl-3">
            <div class="card custom-card">
                <div class="card-body">
                    <a href="<?= site_url($queue['url']) ?>" class="cbs-stat">
                        <span class="avatar avatar-lg avatar-rounded bg-<?= $tone ?>-transparent fs-4 flex-shrink-0">
                            <i class="<?= esc($queue['icon'], 'attr') ?>" aria-hidden="true"></i>
                        </span>
                        <span class="d-block">
                            <span class="cbs-stat-value d-block"><?= esc($queue['count']) ?></span>
                            <span class="cbs-stat-label d-block"><?= esc($queue['label']) ?></span>
                            <span class="text-muted fs-12 d-block"><?= esc($queue['hint']) ?></span>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-xl-7">
        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">Configuration</div>
                <span class="badge <?= $warnings > 0 ? 'bg-danger-transparent' : 'bg-success-transparent' ?>">
                    <?= $warnings > 0 ? esc($warnings) . ' to check' : 'Ready' ?>
                </span>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($health as $item): ?>
                    <?php $ok = $item['status'] !== 'warn'; ?>
                    <li class="list-group-item">
                        <a href="<?= esc($item['url'], 'attr') ?>" class="d-flex align-items-center gap-3 text-reset text-decoration-none">
                            <span class="avatar avatar-sm avatar-rounded <?= $ok ? 'bg-success-transparent' : 'bg-danger-transparent' ?> flex-shrink-0">
                                <i class="<?= $ok ? 'ri-check-line' : 'ri-error-warning-line' ?>" aria-hidden="true"></i>
                            </span>
                            <span class="flex-grow-1">
                                <span class="d-block text-muted fs-12"><?= esc($item['label']) ?></span>
                                <span class="d-block fw-semibold"><?= esc($item['detail']) ?></span>
                            </span>
                            <i class="ri-arrow-right-s-line text-muted fs-5" aria-hidden="true"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">Catalog health</div>
                <span class="badge <?= $catalogGaps > 0 ? 'bg-danger-transparent' : 'bg-success-transparent' ?>">
                    <?= $catalogGaps > 0 ? esc($catalogGaps) . ' empty' : 'Stocked' ?>
                </span>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($catalogCards as $card): ?>
                    <?php $empty = $card['count'] === 0 && $card['emptyHint'] !== null; ?>
                    <li class="list-group-item">
                        <a href="<?= site_url($card['url']) ?>" class="d-flex align-items-center gap-3 text-reset text-decoration-none">
                            <span class="avatar avatar-sm avatar-rounded <?= $empty ? 'bg-danger-transparent' : 'bg-primary-transparent' ?> flex-shrink-0">
                                <i class="<?= esc($card['icon'], 'attr') ?>" aria-hidden="true"></i>
                            </span>
                            <span class="flex-grow-1">
                                <span class="d-block fw-semibold"><?= esc($card['label']) ?></span>
                                <?php if ($empty): ?>
                                    <span class="d-block text-danger fs-12"><?= esc($card['emptyHint']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="fs-5 fw-semibold"><?= esc($card['count']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-footer text-muted">
                <i class="ri-refresh-line me-1" aria-hidden="true"></i>
                Last vendor gift sync:
                <?= $catalog['lastSync'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => $catalog['lastSync']]) : 'never' ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
