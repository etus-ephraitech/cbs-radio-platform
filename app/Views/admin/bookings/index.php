<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Bookings',
    'subtitle' => 'Search and review every greeting that has been submitted.',
]) ?>

<form method="get" action="<?= site_url('admin/bookings') ?>" class="row g-2 align-items-end mb-3">
    <div class="col-md-4">
        <label class="form-label small mb-1">Search</label>
        <input type="search" name="q" class="form-control form-control-sm"
               placeholder="Reference, name, phone, or message text" value="<?= esc($filters['q'] ?? '') ?>">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= esc($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                    <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1">Category</label>
        <select name="category_id" class="form-select form-select-sm">
            <option value="">All</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= esc($category['id']) ?>" <?= (int) $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= esc($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1">Submitted from</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= esc($filters['date_from'] ?? '') ?>">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-1">to</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= esc($filters['date_to'] ?? '') ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-cbs-primary">Search</button>
        <a href="<?= site_url('admin/bookings') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
</form>

<p class="text-muted small"><?= esc($total) ?> booking(s)</p>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr>
            <th>Ref</th>
            <th>Submitted</th>
            <th>For</th>
            <th>From</th>
            <th>Category / package</th>
            <th>Airs</th>
            <th>Amount</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($bookings)): ?>
            <tr><td colspan="8" class="text-muted">No bookings match these filters.</td></tr>
        <?php endif; ?>

        <?php foreach ($bookings as $booking): ?>
            <tr>
                <td class="text-nowrap">
                    <a href="<?= site_url('admin/bookings/' . $booking['id']) ?>" class="fw-semibold text-decoration-none">
                        <?= esc(cbs_booking_reference((int) $booking['id'])) ?>
                    </a>
                </td>
                <td class="text-nowrap small"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['created_at']]) ?></td>
                <td>
                    <?= esc($booking['recipient_name']) ?>
                    <div class="text-muted small"><?= esc(cbs_excerpt($booking['message_text'], 60)) ?></div>
                </td>
                <td class="small">
                    <?= esc($booking['sender_name']) ?>
                    <div class="text-muted"><?= esc(cbs_mask_phone($booking['sender_phone'])) ?></div>
                </td>
                <td class="small">
                    <?= esc($booking['category_name']) ?>
                    <div class="text-muted"><?= esc($booking['package_level']) ?></div>
                </td>
                <td class="small text-nowrap">
                    <?php if (! empty($booking['show_name'])): ?>
                        <?= esc($booking['show_name']) ?>
                        <div class="text-muted"><?= esc($booking['slot_date']) ?></div>
                    <?php elseif (! empty($booking['scheduled_date'])): ?>
                        <?= esc($booking['scheduled_date']) ?>
                    <?php else: ?>
                        <span class="text-muted">&mdash;</span>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap"><?= cbs_money((float) $booking['amount'], $booking['currency']) ?></td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'booking', 'status' => $booking['status']]) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= view_cell('App\Cells\Pagination::render', [
    'page'       => $page,
    'totalPages' => $totalPages,
    'baseUrl'    => 'admin/bookings',
    'query'      => $filters,
]) ?>

<?= $this->endSection() ?>
