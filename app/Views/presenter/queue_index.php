<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="page-header">
    <h1 class="h3 mb-1">Today's Queue</h1>
    <p class="text-muted mb-0">Approved greetings, ready to read on air — <?= date('l, F j') ?>.</p>
</div>

<?php if (empty($showQueues)): ?>
    <div class="alert alert-secondary">No active shows are configured.</div>
<?php endif; ?>

<?php foreach ($showQueues as $entry): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <strong><?= esc($entry['show']['name']) ?></strong>
            <span class="badge bg-secondary"><?= count($entry['queue']) ?> in queue</span>
        </div>
        <?php if (empty($entry['queue'])): ?>
            <div class="card-body text-muted small">Nothing in the queue for this show yet.</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($entry['queue'] as $booking): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold">For <?= esc($booking['recipient_name']) ?></div>
                                <div class="small text-muted"><?= esc(cbs_excerpt($booking['message_text'], 140)) ?></div>
                                <div class="small text-muted mt-1">
                                    Ref <?= esc(cbs_booking_reference((int) $booking['id'])) ?>
                                    &middot; <?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['created_at']]) ?>
                                    <?php if (! empty($booking['voice_note_path'])): ?>
                                        &middot; <a href="<?= site_url('presenter/bookings/' . $booking['id'] . '/voice-note') ?>" target="_blank">Play voice note (<?= cbs_duration($booking['voice_note_duration_seconds'] ?? null) ?>)</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="post" action="<?= site_url('presenter/bookings/' . $booking['id'] . '/aired') ?>" class="ms-3">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-cbs-primary">Mark aired</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>
