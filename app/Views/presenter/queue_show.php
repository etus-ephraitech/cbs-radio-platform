<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <p class="mb-1"><a href="<?= site_url('presenter') ?>" class="text-muted small">&larr; All shows</a></p>
        <h1 class="h3 mb-1">Queue for <?= esc($date) ?></h1>
    </div>
    <form method="get" class="d-flex gap-2">
        <input type="date" name="date" value="<?= esc($date) ?>" class="form-control form-control-sm">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Go</button>
    </form>
</div>

<?php if (empty($queue)): ?>
    <div class="alert alert-secondary">Nothing in the queue for this date.</div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($queue as $booking): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold">For <?= esc($booking['recipient_name']) ?></div>
                        <div class="small text-muted"><?= esc($booking['message_text']) ?></div>
                        <div class="small text-muted mt-1">
                            Ref <?= esc(cbs_booking_reference((int) $booking['id'])) ?>
                            <?php if (! empty($booking['voice_note_path'])): ?>
                                &middot; <a href="<?= site_url('presenter/bookings/' . $booking['id'] . '/voice-note') ?>" target="_blank">Play voice note (<?= cbs_duration($booking['voice_note_duration_seconds'] ?? null) ?>)</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="post" action="<?= site_url('presenter/bookings/' . $booking['id'] . '/aired') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-cbs-primary">Mark aired</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
