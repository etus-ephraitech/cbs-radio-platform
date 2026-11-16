<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Bookings<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Bookings',
    'subtitle' => 'Search and review every greeting that has been submitted.',
]) ?>

<?php $isFiltered = array_filter($filters, static fn ($value) => $value !== null && $value !== '') !== []; ?>

<div class="card custom-card">
    <div class="card-header justify-content-between">
        <div class="card-title">
            All bookings
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= esc(number_format((int) $total)) ?></span>
        </div>
        <?php if ($isFiltered): ?>
            <a href="<?= site_url('admin/bookings') ?>" class="btn btn-sm btn-light"><i class="ri-close-line me-1" aria-hidden="true"></i>Clear filters</a>
        <?php endif; ?>
    </div>

    <div class="card-body border-bottom">
        <form method="get" action="<?= site_url('admin/bookings') ?>" class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label for="bookings-q" class="form-label fs-12 text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent"><i class="ri-search-line" aria-hidden="true"></i></span>
                    <input type="search" id="bookings-q" name="q" class="form-control" placeholder="Reference, name, phone, or message text" value="<?= esc($filters['q'] ?? '', 'attr') ?>">
                </div>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label for="bookings-status" class="form-label fs-12 text-muted mb-1">Status</label>
                <select id="bookings-status" name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= esc($status, 'attr') ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label for="bookings-category" class="form-label fs-12 text-muted mb-1">Category</label>
                <select id="bookings-category" name="category_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= esc($category['id'], 'attr') ?>" <?= (int) $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= esc($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label for="bookings-from" class="form-label fs-12 text-muted mb-1">Submitted from</label>
                <input type="date" id="bookings-from" name="date_from" class="form-control" value="<?= esc($filters['date_from'] ?? '', 'attr') ?>">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label for="bookings-to" class="form-label fs-12 text-muted mb-1">to</label>
                <input type="date" id="bookings-to" name="date_to" class="form-control" value="<?= esc($filters['date_to'] ?? '', 'attr') ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ri-filter-3-line me-1" aria-hidden="true"></i>Search</button>
                <a href="<?= site_url('admin/bookings') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-inbox-line" aria-hidden="true"></i></span>
            <h6>No bookings match these filters.</h6>
            <p class="mb-0 fs-13"><?= $isFiltered ? 'Try a different search, or clear the filters.' : 'Greetings appear here as soon as listeners submit them.' ?></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Submitted</th>
                        <th>For</th>
                        <th>From</th>
                        <th>Category / package</th>
                        <th>Airs</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('admin/bookings/' . $booking['id']) ?>" class="cbs-reference text-primary"><?= esc(cbs_booking_reference((int) $booking['id'])) ?></a>
                            </td>
                            <td class="fs-13"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['created_at']]) ?></td>
                            <td class="cell-message text-wrap">
                                <span class="fw-semibold"><?= esc($booking['recipient_name']) ?></span>
                                <span class="cell-sub"><?= esc(cbs_excerpt($booking['message_text'], 60)) ?></span>
                            </td>
                            <td>
                                <?= esc($booking['sender_name']) ?>
                                <span class="cell-sub"><?= esc(cbs_mask_phone($booking['sender_phone'])) ?></span>
                            </td>
                            <td>
                                <?= esc($booking['category_name']) ?>
                                <span class="cell-sub"><?= esc($booking['package_level']) ?></span>
                            </td>
                            <td>
                                <?php if (! empty($booking['show_name'])): ?>
                                    <?= esc($booking['show_name']) ?>
                                    <span class="cell-sub"><?= esc($booking['slot_date']) ?></span>
                                <?php elseif (! empty($booking['scheduled_date'])): ?>
                                    <?= esc($booking['scheduled_date']) ?>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold"><?= cbs_money((float) $booking['amount'], $booking['currency']) ?></td>
                            <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'booking', 'status' => $booking['status']]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="text-muted"><?= esc(number_format((int) $total)) ?> booking(s)</span>
            <div class="cbs-pagination">
                <?= view_cell('App\Cells\Pagination::render', [
                    'page'       => $page,
                    'totalPages' => $totalPages,
                    'baseUrl'    => 'admin/bookings',
                    'query'      => $filters,
                ]) ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
