<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php
$id          = (int) $booking['id'];
$canModerate = $booking['status'] === 'pending_moderation';
$canCancel   = ! in_array($booking['status'], ['aired', 'cancelled'], true);
?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => 'Booking ' . cbs_booking_reference($id),
    'subtitle'  => 'Submitted ' . $booking['created_at'],
    'backUrl'   => site_url('admin/bookings'),
    'backLabel' => 'All bookings',
]) ?>

<div class="row g-3">
    <div class="col-lg-8">

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h2 class="h6 mb-0">Message for <?= esc($booking['recipient_name']) ?></h2>
                    <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'booking', 'status' => $booking['status']]) ?>
                </div>

                <p style="white-space: pre-wrap;"><?= esc($booking['message_text']) ?></p>

                <?php if (! empty($booking['moderation_notes'])): ?>
                    <div class="alert alert-warning py-2 small"><?= esc($booking['moderation_notes']) ?></div>
                <?php endif; ?>

                <?php if (! empty($booking['voice_note_path'])): ?>
                    <div class="small text-muted mb-1">Voice note (<?= cbs_duration($booking['voice_note_duration_seconds'] ?? null) ?>)</div>
                    <audio controls preload="none" style="width: 100%; max-width: 360px;"
                           src="<?= site_url('admin/bookings/' . $id . '/voice-note') ?>"></audio>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canModerate || $canCancel): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Actions</h2>

                    <?php if ($canModerate): ?>
                        <form id="moderate-form" method="post" action="<?= site_url('admin/moderation/' . $id . '/reject') ?>" class="mb-3">
                            <?= csrf_field() ?>
                            <input type="text" name="notes" class="form-control form-control-sm mb-2" placeholder="Optional note for the audit log">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-success"
                                        formaction="<?= site_url('admin/moderation/' . $id . '/approve') ?>">Approve</button>
                                <?= view_cell('App\Cells\ConfirmButton::render', [
                                    'formId'            => 'moderate-form',
                                    'title'             => 'Reject this booking?',
                                    'text'              => 'The sender will be notified. This cannot be undone.',
                                    'label'             => 'Reject',
                                    'buttonClass'       => 'btn btn-sm btn-outline-danger',
                                    'confirmButtonText' => 'Yes, reject',
                                ]) ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary"
                                        formaction="<?= site_url('admin/moderation/' . $id . '/flag') ?>">Flag</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($canCancel): ?>
                        <form id="cancel-booking-form" method="post" action="<?= site_url('admin/bookings/' . $id . '/cancel') ?>">
                            <?= csrf_field() ?>
                        </form>
                        <?= view_cell('App\Cells\ConfirmButton::render', [
                            'formId'            => 'cancel-booking-form',
                            'title'             => 'Cancel this booking?',
                            'text'              => 'Its show-slot spot is released. Any payment already taken is NOT refunded automatically.',
                            'label'             => 'Cancel booking',
                            'buttonClass'       => 'btn btn-sm btn-outline-danger',
                            'confirmButtonText' => 'Yes, cancel booking',
                        ]) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6">Payments</h2>
                <?php if (empty($payments)): ?>
                    <p class="text-muted small mb-0">No payment attempts yet.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Provider</th><th>Amount</th><th>Status</th><th>Paid</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="small"><?= esc($payment['provider']) ?></td>
                                    <td class="small"><?= cbs_money((float) $payment['amount'], $payment['currency']) ?></td>
                                    <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'payment', 'status' => $payment['status']]) ?></td>
                                    <td class="small"><?= $payment['paid_at'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => $payment['paid_at']]) : '&mdash;' ?></td>
                                    <td class="text-end">
                                        <?php if ($payment['status'] !== 'success'): ?>
                                            <form id="markpaid-form-<?= (int) $payment['id'] ?>" method="post"
                                                  action="<?= site_url('admin/payments/' . $payment['id'] . '/mark-manual') ?>">
                                                <?= csrf_field() ?>
                                            </form>
                                            <?= view_cell('App\Cells\ConfirmButton::render', [
                                                'formId'            => 'markpaid-form-' . (int) $payment['id'],
                                                'title'             => 'Mark this payment as paid?',
                                                'text'              => 'Use this only once you have confirmed payment outside the system.',
                                                'label'             => 'Mark paid',
                                                'buttonClass'       => 'btn btn-sm btn-outline-success',
                                                'confirmButtonText' => 'Yes, mark paid',
                                            ]) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php if (! empty($giftOrders)): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Gifts sent with this greeting</h2>
                    <table class="table table-sm align-middle mb-2">
                        <thead><tr><th>Ref</th><th>Gift</th><th>Qty</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($giftOrders as $order): ?>
                                <tr>
                                    <td class="small"><?= esc(cbs_gift_reference((int) $order['id'])) ?></td>
                                    <td class="small"><?= esc($order['gift_name']) ?></td>
                                    <td class="small"><?= esc($order['quantity']) ?></td>
                                    <td class="small"><?= cbs_money((float) $order['amount'], $order['currency']) ?></td>
                                    <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'gift-order', 'status' => $order['status']]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="<?= site_url('admin/gift-orders') ?>" class="small">Manage gift orders &rarr;</a>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <div class="col-lg-4">

        <div class="card mb-3">
            <div class="card-body small">
                <h2 class="h6">Booking</h2>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Category</dt><dd class="col-7"><?= esc($booking['category_name']) ?></dd>
                    <dt class="col-5 text-muted">Package</dt><dd class="col-7"><?= esc($booking['package_level']) ?></dd>
                    <dt class="col-5 text-muted">Amount</dt><dd class="col-7"><?= cbs_money((float) $booking['amount'], $booking['currency']) ?></dd>
                    <dt class="col-5 text-muted">Airs</dt>
                    <dd class="col-7">
                        <?php if (! empty($booking['show_name'])): ?>
                            <?= esc($booking['show_name']) ?>, <?= esc($booking['slot_date']) ?>
                        <?php elseif (! empty($booking['scheduled_date'])): ?>
                            <?= esc($booking['scheduled_date']) ?>
                        <?php else: ?>
                            <span class="text-muted">No preference</span>
                        <?php endif; ?>
                    </dd>
                    <?php if (! empty($booking['aired_at'])): ?>
                        <dt class="col-5 text-muted">Aired</dt>
                        <dd class="col-7"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['aired_at']]) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body small">
                <h2 class="h6">Sender</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Name</dt><dd class="col-8"><?= esc($booking['sender_name']) ?></dd>
                    <dt class="col-4 text-muted">Phone</dt><dd class="col-8"><?= esc($booking['sender_phone']) ?></dd>
                    <dt class="col-4 text-muted">Email</dt><dd class="col-8"><?= esc($booking['sender_email'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body small">
                <h2 class="h6">Moderation history</h2>
                <?php if (empty($moderation)): ?>
                    <p class="text-muted mb-0">No moderation actions yet.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($moderation as $entry): ?>
                            <li class="mb-2">
                                <span class="text-capitalize fw-semibold"><?= esc($entry['action']) ?></span>
                                by user #<?= esc($entry['moderator_id']) ?>
                                <span class="text-muted">&middot; <?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $entry['created_at']]) ?></span>
                                <?php if (! empty($entry['notes'])): ?>
                                    <div class="text-muted"><?= esc($entry['notes']) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body small">
                <h2 class="h6 d-flex justify-content-between">
                    Notifications
                    <a href="<?= site_url('admin/notifications?booking_id=' . $id) ?>" class="fw-normal">full log &rarr;</a>
                </h2>
                <?php if (empty($notifications)): ?>
                    <p class="text-muted mb-0">Nothing sent for this booking.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($notifications as $note): ?>
                            <li class="mb-2">
                                <span class="text-uppercase"><?= esc($note['channel']) ?></span>
                                <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'notification', 'status' => $note['status']]) ?>
                                <span class="text-muted">&middot; <?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $note['created_at']]) ?></span>
                                <div class="text-muted"><?= esc(cbs_excerpt($note['message'], 70)) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
