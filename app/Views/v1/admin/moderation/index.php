<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Moderation queue<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Moderation queue',
    'subtitle' => count($bookings) . ' booking(s) awaiting review, oldest first.',
]) ?>

<?php if (empty($bookings)): ?>
    <div class="card custom-card">
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-success-transparent fs-3"><i class="ri-shield-check-line" aria-hidden="true"></i></span>
            <h6>Nothing waiting for review right now.</h6>
            <p class="mb-0 fs-13">Paid greetings appear here, oldest first, until someone approves, rejects or flags them.</p>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($bookings as $booking): ?>
    <?php $id = (int) $booking['id']; ?>
    <div class="card custom-card">
        <div class="card-header justify-content-between">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="<?= site_url('admin/bookings/' . $id) ?>" class="cbs-reference text-primary"><?= esc(cbs_booking_reference($id)) ?></a>
                <span class="text-muted">&middot;</span>
                <span class="fw-semibold">For <?= esc($booking['recipient_name']) ?></span>
                <span class="text-muted fs-13">from <?= esc($booking['sender_name']) ?> (<?= esc(cbs_mask_phone($booking['sender_phone'])) ?>)</span>
            </div>
            <span class="text-muted fs-12"><i class="ri-time-line me-1 align-middle" aria-hidden="true"></i><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['created_at']]) ?></span>
        </div>
        <div class="card-body pt-0">
            <div class="cbs-message mb-3"><?= esc($booking['message_text']) ?></div>

            <?php if (! empty($booking['moderation_notes'])): ?>
                <div class="alert alert-warning d-flex gap-2 align-items-start">
                    <i class="ri-error-warning-line fs-5" aria-hidden="true"></i>
                    <div><?= esc($booking['moderation_notes']) ?></div>
                </div>
            <?php endif; ?>

            <?php if (! empty($booking['voice_note_path'])): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="avatar avatar-sm avatar-rounded bg-secondary-transparent"><i class="ri-mic-line" aria-hidden="true"></i></span>
                    <span class="fs-13 text-muted">Voice note (<?= cbs_duration($booking['voice_note_duration_seconds'] ?? null) ?>)</span>
                    <audio class="cbs-audio" controls preload="none" src="<?= site_url('admin/moderation/' . $id . '/voice-note') ?>"></audio>
                </div>
            <?php endif; ?>

            <?php /* The form's own action is "reject", because the ConfirmButton cell submits the form as-is
                     after its confirm dialog. Approve and Flag post elsewhere via the native formaction attribute. */ ?>
            <form id="moderate-form-<?= $id ?>" method="post" class="row g-2 align-items-center" action="<?= site_url('admin/moderation/' . $id . '/reject') ?>">
                <?= csrf_field() ?>
                <div class="col-lg">
                    <label for="moderate-notes-<?= $id ?>" class="visually-hidden">Note for the audit log</label>
                    <input type="text" id="moderate-notes-<?= $id ?>" name="notes" class="form-control" placeholder="Optional note for the audit log">
                </div>
                <div class="col-lg-auto d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-success" formaction="<?= site_url('admin/moderation/' . $id . '/approve') ?>">
                        <i class="ri-check-line me-1" aria-hidden="true"></i>Approve
                    </button>
                    <?= view_cell('App\Cells\ConfirmButton::render', [
                        'formId'            => 'moderate-form-' . $id,
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
        </div>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>
