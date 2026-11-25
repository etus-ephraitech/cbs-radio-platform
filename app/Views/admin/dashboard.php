<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Dashboard',
    'subtitle' => date('l, F j'),
]) ?>

<form method="get" action="<?= site_url('admin/bookings') ?>" class="row g-2 mb-4" style="max-width: 560px;">
    <div class="col">
        <input type="search" name="q" class="form-control" placeholder="Find a booking — reference, name, phone, or message text">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-cbs-primary">Search</button>
    </div>
</form>

<h2 class="h6 text-uppercase text-muted mb-2" style="letter-spacing:.06em">Needs attention</h2>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a href="<?= site_url('admin/moderation') ?>" class="text-decoration-none">
            <div class="card stat-card <?= $attention['pendingModeration'] > 0 ? 'urgent' : '' ?> h-100 shadow-sm">
                <div class="card-body">
                    <div class="display-6 text-dark"><?= esc($attention['pendingModeration']) ?></div>
                    <div class="text-muted small">Awaiting moderation</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= site_url('admin/payments?status=pending') ?>" class="text-decoration-none">
            <div class="card stat-card <?= $attention['pendingPayments'] > 0 ? 'urgent' : '' ?> h-100 shadow-sm">
                <div class="card-body">
                    <div class="display-6 text-dark"><?= esc($attention['pendingPayments']) ?></div>
                    <div class="text-muted small">Pending payments</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= site_url('admin/gift-orders?status=paid') ?>" class="text-decoration-none">
            <div class="card stat-card <?= $attention['giftsToFulfill'] > 0 ? 'urgent' : '' ?> h-100 shadow-sm">
                <div class="card-body">
                    <div class="display-6 text-dark"><?= esc($attention['giftsToFulfill']) ?></div>
                    <div class="text-muted small">Gift orders to fulfill</div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= site_url('admin/notifications?status=failed') ?>" class="text-decoration-none">
            <div class="card stat-card <?= $attention['failedNotices24h'] > 0 ? 'urgent' : '' ?> h-100 shadow-sm">
                <div class="card-body">
                    <div class="display-6 text-dark"><?= esc($attention['failedNotices24h']) ?></div>
                    <div class="text-muted small">Failed notifications (24h)</div>
                    <?php if ($attention['failedNotices24h'] > 0): ?>
                        <div class="small mt-1 text-danger">Review and re-send, or check the gateway settings in <code>.env</code>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
</div>

<h2 class="h6 text-uppercase text-muted mb-2" style="letter-spacing:.06em">Configuration</h2>
<div class="row g-3 mb-4">
    <?php foreach ($health as $item): ?>
        <div class="col-sm-6 col-xl-4">
            <a href="<?= esc($item['url'], 'attr') ?>" class="text-decoration-none">
                <div class="card stat-card <?= $item['status'] === 'warn' ? 'urgent' : '' ?> h-100 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small"><?= esc($item['label']) ?></div>
                        <div class="fw-semibold text-dark"><?= esc($item['detail']) ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="h6 text-uppercase text-muted mb-2" style="letter-spacing:.06em">Catalog health</h2>
<div class="row g-3">
    <?php
    $catalogCards = [
        ['label' => 'Active categories', 'count' => $catalog['categories'], 'url' => 'admin/categories',  'emptyHint' => 'No categories — the booking flow will be empty.'],
        ['label' => 'Active packages',   'count' => $catalog['rateTiers'],  'url' => 'admin/rate-tiers',  'emptyHint' => 'No packages — nothing to select or pay for.'],
        ['label' => 'Active shows',      'count' => $catalog['shows'],      'url' => 'admin/shows',       'emptyHint' => 'No shows — scheduling won\'t offer any slots.'],
        ['label' => 'Active gifts',      'count' => $catalog['gifts'],      'url' => 'admin/gifts',       'emptyHint' => 'No gifts — the gift catalog will be empty.'],
        ['label' => 'Active vendors',    'count' => $catalog['vendors'],    'url' => 'admin/gift-vendors', 'emptyHint' => null],
    ];
    ?>
    <?php foreach ($catalogCards as $card): ?>
        <div class="col-sm-6 col-xl">
            <a href="<?= site_url($card['url']) ?>" class="text-decoration-none">
                <div class="card stat-card <?= ($card['count'] === 0 && $card['emptyHint']) ? 'urgent' : '' ?> h-100 shadow-sm">
                    <div class="card-body">
                        <div class="h3 mb-0 text-dark"><?= esc($card['count']) ?></div>
                        <div class="text-muted small"><?= esc($card['label']) ?></div>
                        <?php if ($card['count'] === 0 && $card['emptyHint']): ?>
                            <div class="small mt-1 text-danger"><?= esc($card['emptyHint']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<p class="text-muted small mt-3 mb-0">
    Last vendor gift sync:
    <?= $catalog['lastSync'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => $catalog['lastSync']]) : 'never' ?>
</p>

<?= $this->endSection() ?>
