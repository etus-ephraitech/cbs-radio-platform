<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Moderation Queue',
    'subtitle' => count($bookings) . ' booking(s) awaiting review, oldest first.',
]) ?>

<?php if (empty($bookings)): ?>
    <div class="alert alert-secondary">Nothing waiting for review right now.</div>
<?php endif; ?>

<?php foreach ($bookings as $booking): ?>
    <?php $id = (int) $booking['id']; ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <a href="<?= site_url('admin/bookings/' . $id) ?>" class="fw-semibold text-decoration-none">
                        <?= esc(cbs_booking_reference($id)) ?>
                    </a>
                    <span class="mx-1 text-muted">&middot;</span>
                    <strong>For <?= esc($booking['recipient_name']) ?></strong>
                    <span class="text-muted small"> &middot; from <?= esc($booking['sender_name']) ?> (<?= esc(cbs_mask_phone($booking['sender_phone'])) ?>)</span>
                </div>
                <span class="text-muted small"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['created_at']]) ?></span>
            </div>

            <p class="mb-2" style="white-space: pre-wrap;"><?= esc($booking['message_text']) ?></p>

            <?php if (! empty($booking['moderation_notes'])): ?>
                <div class="alert alert-warning py-2 small mb-2"><?= esc($booking['moderation_notes']) ?></div>
            <?php endif; ?>

            <?php if (! empty($booking['voice_note_path'])): ?>
                <p class="small mb-3">
                    <a href="<?= site_url('admin/moderation/' . $id . '/voice-note') ?>" target="_blank">
                        Play voice note (<?= cbs_duration($booking['voice_note_duration_seconds'] ?? null) ?>)
                    </a>
                </p>
            <?php endif; ?>

            <?php /* The form's own action is "reject", because the ConfirmButton cell submits the form as-is
                     after its confirm dialog. Approve and Flag post elsewhere via the native formaction attribute. */ ?>
            <form id="moderate-form-<?= $id ?>" method="post" class="row g-2"
                  action="<?= site_url('admin/moderation/' . $id . '/reject') ?>">
                <?= csrf_field() ?>
                <div class="col-md-8">
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Optional note for the audit log">
                </div>
                <div class="col-md-4 d-flex gap-2 justify-content-end">
                    <button type="submit" class="btn btn-sm btn-success"
                            formaction="<?= site_url('admin/moderation/' . $id . '/approve') ?>">Approve</button>

                    <?= view_cell('App\Cells\ConfirmButton::render', [
                        'formId'            => 'moderate-form-' . $id,
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
        </div>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>
