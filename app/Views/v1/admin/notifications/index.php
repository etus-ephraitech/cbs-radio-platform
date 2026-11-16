<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Notifications log<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Notifications log',
    'subtitle' => 'Every SMS and email the platform has tried to send. Failed ones can be re-sent once the gateway is working again.',
]) ?>

<?php $isFiltered = array_filter($filters, static fn ($value) => $value !== null && $value !== '') !== []; ?>

<div class="card custom-card">
    <div class="card-header justify-content-between">
        <div class="card-title">
            Notifications
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= esc(number_format((int) $total)) ?></span>
        </div>
        <?php if ($isFiltered): ?>
            <a href="<?= site_url('admin/notifications') ?>" class="btn btn-sm btn-light"><i class="ri-close-line me-1" aria-hidden="true"></i>Clear filters</a>
        <?php endif; ?>
    </div>

    <div class="card-body border-bottom">
        <form method="get" action="<?= site_url('admin/notifications') ?>" class="row g-2 align-items-end">
            <div class="col-sm-auto col-6">
                <label for="notifications-status" class="form-label fs-12 text-muted mb-1">Status</label>
                <select id="notifications-status" name="status" class="form-select">
                    <option value="">All</option>
                    <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="sent" <?= $filters['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                </select>
            </div>
            <div class="col-sm-auto col-6">
                <label for="notifications-channel" class="form-label fs-12 text-muted mb-1">Channel</label>
                <select id="notifications-channel" name="channel" class="form-select">
                    <option value="">All</option>
                    <option value="sms" <?= $filters['channel'] === 'sms' ? 'selected' : '' ?>>SMS</option>
                    <option value="email" <?= $filters['channel'] === 'email' ? 'selected' : '' ?>>Email</option>
                </select>
            </div>
            <div class="col-sm-auto col-6">
                <label for="notifications-booking" class="form-label fs-12 text-muted mb-1">Booking ID</label>
                <input type="number" min="1" id="notifications-booking" name="booking_id" class="form-control" style="width: 8rem;" value="<?= esc($filters['booking_id'] ?? '', 'attr') ?>">
            </div>
            <div class="col-sm-auto col-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ri-filter-3-line me-1" aria-hidden="true"></i>Filter</button>
                <a href="<?= site_url('admin/notifications') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-notification-3-line" aria-hidden="true"></i></span>
            <h6>Nothing matches these filters.</h6>
            <p class="mb-0 fs-13">Confirmations and on-air messages are logged here as they are sent.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
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
                    <?php foreach ($notifications as $row): ?>
                        <tr>
                            <td class="fs-13"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $row['created_at']]) ?></td>
                            <td>
                                <span class="badge bg-light text-default text-uppercase">
                                    <i class="<?= $row['channel'] === 'email' ? 'ri-mail-line' : 'ri-message-2-line' ?> me-1" aria-hidden="true"></i><?= esc($row['channel']) ?>
                                </span>
                            </td>
                            <td class="fs-13"><?= esc($row['channel'] === 'email' ? cbs_mask_email($row['recipient']) : cbs_mask_phone($row['recipient'])) ?></td>
                            <td>
                                <?php if (! empty($row['booking_id'])): ?>
                                    <a href="<?= site_url('admin/bookings/' . $row['booking_id']) ?>" class="cbs-reference text-primary"><?= esc(cbs_booking_reference((int) $row['booking_id'])) ?></a>
                                <?php else: ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'notification', 'status' => $row['status']]) ?></td>
                            <td class="text-wrap" style="min-width: 16rem; max-width: 26rem;">
                                <details>
                                    <summary class="text-muted fs-13"><?= esc(cbs_excerpt($row['message'], 70)) ?></summary>
                                    <div class="fs-13 mt-1" style="white-space: pre-wrap;"><?= esc($row['message']) ?></div>
                                </details>
                            </td>
                            <td class="text-end">
                                <?php if ($row['status'] === 'failed'): ?>
                                    <form method="post" action="<?= site_url('admin/notifications/' . $row['id'] . '/resend') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-primary-light"><i class="ri-send-plane-line me-1" aria-hidden="true"></i>Resend</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="text-muted"><?= esc(number_format((int) $total)) ?> notification(s)</span>
            <div class="cbs-pagination">
                <?= view_cell('App\Cells\Pagination::render', [
                    'page'       => $page,
                    'totalPages' => $totalPages,
                    'baseUrl'    => 'admin/notifications',
                    'query'      => $filters,
                ]) ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
