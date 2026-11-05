<?= $this->extend(ui_layout('admin')) ?>

<?php
$id          = (int) $booking['id'];
$reference   = cbs_booking_reference($id);
$canModerate = $booking['status'] === 'pending_moderation';
$canCancel   = ! in_array($booking['status'], ['aired', 'cancelled'], true);
?>

<?= $this->section('title') ?>Booking <?= esc($reference) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => 'Booking ' . $reference,
    'subtitle'  => 'Submitted ' . $booking['created_at'],
    'backUrl'   => site_url('admin/bookings'),
    'backLabel' => 'All bookings',
]) ?>

<div class="row">
    <div class="col-xl-8">

        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">Message for <?= esc($booking['recipient_name']) ?></div>
                <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'booking', 'status' => $booking['status']]) ?>
            </div>
            <div class="card-body">
                <div class="cbs-message mb-3"><?= esc($booking['message_text']) ?></div>

                <?php if (! empty($booking['moderation_notes'])): ?>
                    <div class="alert alert-warning d-flex gap-2 align-items-start">
                        <i class="ri-error-warning-line fs-5" aria-hidden="true"></i>
                        <div><?= esc($booking['moderation_notes']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (! empty($booking['voice_note_path'])): ?>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="avatar avatar-md avatar-rounded bg-secondary-transparent"><i class="ri-mic-line" aria-hidden="true"></i></span>
                        <div class="flex-grow-1">
                            <div class="text-muted fs-12 mb-1">Voice note (<?= cbs_duration($booking['voice_note_duration_seconds'] ?? null) ?>)</div>
                            <audio class="cbs-audio" controls preload="none" src="<?= site_url('admin/bookings/' . $id . '/voice-note') ?>"></audio>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canModerate || $canCancel): ?>
            <div class="card custom-card">
                <div class="card-header"><div class="card-title">Actions</div></div>
                <div class="card-body">
                    <?php if ($canModerate): ?>
                        <form id="moderate-form" method="post" action="<?= site_url('admin/moderation/' . $id . '/reject') ?>" class="mb-3">
                            <?= csrf_field() ?>
                            <label for="moderate-notes" class="form-label fs-12 text-muted">Note for the audit log (optional)</label>
                            <input type="text" id="moderate-notes" name="notes" class="form-control mb-2" placeholder="e.g. Checked the spelling with the sender">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-success" formaction="<?= site_url('admin/moderation/' . $id . '/approve') ?>">
                                    <i class="ri-check-line me-1" aria-hidden="true"></i>Approve
                                </button>
                                <?= view_cell('App\Cells\ConfirmButton::render', [
                                    'formId'            => 'moderate-form',
                                    'title'             => 'Reject this booking?',
                                    'text'              => 'The sender will be notified. This cannot be undone.',
                                    'label'             => 'Reject',
                                    'buttonClass'       => 'btn btn-danger-light',
                                    'confirmButtonText' => 'Yes, reject',
                                ]) ?>
                                <button type="submit" class="btn btn-warning-light" formaction="<?= site_url('admin/moderation/' . $id . '/flag') ?>">
                                    <i class="ri-flag-line me-1" aria-hidden="true"></i>Flag
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($canCancel): ?>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 <?= $canModerate ? 'pt-3 border-top' : '' ?>">
                            <span class="text-muted fs-13">Cancelling releases its show-slot spot. Payments already taken are not refunded automatically.</span>
                            <form id="cancel-booking-form" method="post" action="<?= site_url('admin/bookings/' . $id . '/cancel') ?>">
                                <?= csrf_field() ?>
                            </form>
                            <?= view_cell('App\Cells\ConfirmButton::render', [
                                'formId'            => 'cancel-booking-form',
                                'title'             => 'Cancel this booking?',
                                'text'              => 'Its show-slot spot is released. Any payment already taken is NOT refunded automatically.',
                                'label'             => 'Cancel booking',
                                'buttonClass'       => 'btn btn-outline-danger',
                                'confirmButtonText' => 'Yes, cancel booking',
                            ]) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Payments</div></div>
            <?php if (empty($payments)): ?>
                <div class="card-body pt-0"><p class="text-muted mb-0">No payment attempts yet.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table text-nowrap mb-0 table-cbs">
                        <thead><tr><th>Provider</th><th class="text-end">Amount</th><th>Status</th><th>Paid</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= esc($payment['provider']) ?></td>
                                    <td class="text-end fw-semibold"><?= cbs_money((float) $payment['amount'], $payment['currency']) ?></td>
                                    <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'payment', 'status' => $payment['status']]) ?></td>
                                    <td class="fs-13"><?= $payment['paid_at'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => $payment['paid_at']]) : '&mdash;' ?></td>
                                    <td class="text-end">
                                        <?php if ($payment['status'] !== 'success'): ?>
                                            <form id="markpaid-form-<?= (int) $payment['id'] ?>" method="post" action="<?= site_url('admin/payments/' . $payment['id'] . '/mark-manual') ?>">
                                                <?= csrf_field() ?>
                                            </form>
                                            <?= view_cell('App\Cells\ConfirmButton::render', [
                                                'formId'            => 'markpaid-form-' . (int) $payment['id'],
                                                'title'             => 'Mark this payment as paid?',
                                                'text'              => 'Use this only once you have confirmed payment outside the system.',
                                                'label'             => 'Mark paid',
                                                'buttonClass'       => 'btn btn-sm btn-success-light',
                                                'confirmButtonText' => 'Yes, mark paid',
                                            ]) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if (! empty($giftOrders)): ?>
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">Gifts sent with this greeting</div>
                    <a href="<?= site_url('admin/gift-orders') ?>" class="fs-13">Manage gift orders <i class="ri-arrow-right-line align-middle" aria-hidden="true"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table text-nowrap mb-0 table-cbs">
                        <thead><tr><th>Ref</th><th>Gift</th><th>Qty</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($giftOrders as $order): ?>
                                <tr>
                                    <td class="cbs-reference"><?= esc(cbs_gift_reference((int) $order['id'])) ?></td>
                                    <td><?= esc($order['gift_name']) ?></td>
                                    <td><?= esc($order['quantity']) ?></td>
                                    <td class="text-end"><?= cbs_money((float) $order['amount'], $order['currency']) ?></td>
                                    <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'gift-order', 'status' => $order['status']]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <div class="col-xl-4">

        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Booking</div></div>
            <div class="card-body pt-0">
                <dl class="cbs-facts">
                    <dt>Category</dt><dd><?= esc($booking['category_name']) ?></dd>
                    <dt>Package</dt><dd><?= esc($booking['package_level']) ?></dd>
                    <dt>Amount</dt><dd class="fw-semibold"><?= cbs_money((float) $booking['amount'], $booking['currency']) ?></dd>
                    <dt>Airs</dt>
                    <dd>
                        <?php if (! empty($booking['show_name'])): ?>
                            <?= esc($booking['show_name']) ?>, <?= esc($booking['slot_date']) ?>
                        <?php elseif (! empty($booking['scheduled_date'])): ?>
                            <?= esc($booking['scheduled_date']) ?>
                        <?php else: ?>
                            <span class="text-muted">No preference</span>
                        <?php endif; ?>
                    </dd>
                    <?php if (! empty($booking['aired_at'])): ?>
                        <dt>Aired</dt><dd><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['aired_at']]) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Sender</div></div>
            <div class="card-body pt-0">
                <dl class="cbs-facts">
                    <dt>Name</dt><dd><?= esc($booking['sender_name']) ?></dd>
                    <dt>Phone</dt><dd><?= esc($booking['sender_phone']) ?></dd>
                    <dt>Email</dt><dd><?= esc($booking['sender_email'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Moderation history</div></div>
            <div class="card-body pt-0">
                <?php if (empty($moderation)): ?>
                    <p class="text-muted mb-0">No moderation actions yet.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($moderation as $entry): ?>
                            <li class="d-flex gap-2 mb-3">
                                <span class="avatar avatar-sm avatar-rounded bg-light text-default flex-shrink-0"><i class="ri-shield-user-line" aria-hidden="true"></i></span>
                                <div>
                                    <span class="text-capitalize fw-semibold"><?= esc($entry['action']) ?></span>
                                    by user #<?= esc($entry['moderator_id']) ?>
                                    <div class="text-muted fs-12"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $entry['created_at']]) ?></div>
                                    <?php if (! empty($entry['notes'])): ?>
                                        <div class="fs-13 mt-1"><?= esc($entry['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">Notifications</div>
                <a href="<?= site_url('admin/notifications?booking_id=' . $id) ?>" class="fs-13">Full log <i class="ri-arrow-right-line align-middle" aria-hidden="true"></i></a>
            </div>
            <div class="card-body pt-0">
                <?php if (empty($notifications)): ?>
                    <p class="text-muted mb-0">Nothing sent for this booking.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($notifications as $note): ?>
                            <li class="mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-uppercase fw-semibold fs-12"><?= esc($note['channel']) ?></span>
                                    <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'notification', 'status' => $note['status']]) ?>
                                    <span class="text-muted fs-12 ms-auto"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $note['created_at']]) ?></span>
                                </div>
                                <div class="text-muted fs-13 mt-1"><?= esc(cbs_excerpt($note['message'], 70)) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
