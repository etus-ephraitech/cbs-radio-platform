<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Notifications Log',
    'subtitle' => 'Every SMS and email the platform has tried to send. Failed ones can be re-sent once the gateway is working again.',
]) ?>

<form method="get" action="<?= site_url('admin/notifications') ?>" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
            <option value="sent" <?= $filters['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1">Channel</label>
        <select name="channel" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="sms" <?= $filters['channel'] === 'sms' ? 'selected' : '' ?>>SMS</option>
            <option value="email" <?= $filters['channel'] === 'email' ? 'selected' : '' ?>>Email</option>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1">Booking ID</label>
        <input type="number" min="1" name="booking_id" class="form-control form-control-sm" style="width: 110px;"
               value="<?= esc($filters['booking_id'] ?? '') ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-cbs-primary">Filter</button>
        <a href="<?= site_url('admin/notifications') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
</form>

<p class="text-muted small"><?= esc($total) ?> notification(s)</p>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr>
            <th>When</th>
            <th>Channel</th>
            <th>Recipient</th>
            <th>Booking</th>
            <th>Status</th>
            <th>Message</th>
            <th class="text-end"></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($notifications)): ?>
            <tr><td colspan="7" class="text-muted">Nothing matches these filters.</td></tr>
        <?php endif; ?>

        <?php foreach ($notifications as $row): ?>
            <tr>
                <td class="text-nowrap"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $row['created_at']]) ?></td>
                <td class="text-uppercase small"><?= esc($row['channel']) ?></td>
                <td class="small">
                    <?= esc($row['channel'] === 'email' ? cbs_mask_email($row['recipient']) : cbs_mask_phone($row['recipient'])) ?>
                </td>
                <td class="small">
                    <?php if (! empty($row['booking_id'])): ?>
                        <a href="<?= site_url('admin/bookings/' . $row['booking_id']) ?>" class="text-decoration-none">
                            <?= esc(cbs_booking_reference((int) $row['booking_id'])) ?>
                        </a>
                    <?php else: ?>
                        &mdash;
                    <?php endif; ?>
                </td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'notification', 'status' => $row['status']]) ?></td>
                <td class="small" style="max-width: 360px;">
                    <details>
                        <summary class="text-muted"><?= esc(cbs_excerpt($row['message'], 70)) ?></summary>
                        <div class="mt-1" style="white-space: pre-wrap;"><?= esc($row['message']) ?></div>
                    </details>
                </td>
                <td class="text-end">
                    <?php if ($row['status'] === 'failed'): ?>
                        <form method="post" action="<?= site_url('admin/notifications/' . $row['id'] . '/resend') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Resend</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= view_cell('App\Cells\Pagination::render', [
    'page'       => $page,
    'totalPages' => $totalPages,
    'baseUrl'    => 'admin/notifications',
    'query'      => $filters,
]) ?>

<?= $this->endSection() ?>
