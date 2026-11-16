<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="mx-auto" style="max-width: 560px;">
    <div class="card shadow-sm">
        <div class="card-body py-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h1 class="h4 mb-0">Greeting status</h1>
                <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'booking', 'status' => $booking['status']]) ?>
            </div>

            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted">Reference</dt>
                <dd class="col-sm-8"><?= esc(cbs_booking_reference((int) $booking['id'])) ?></dd>

                <dt class="col-sm-4 text-muted">Recipient</dt>
                <dd class="col-sm-8"><?= esc($booking['recipient_name']) ?></dd>

                <dt class="col-sm-4 text-muted">Message</dt>
                <dd class="col-sm-8"><?= esc($booking['message_text']) ?></dd>

                <?php if (! empty($booking['voice_note_path'])): ?>
                    <dt class="col-sm-4 text-muted">Voice note</dt>
                    <dd class="col-sm-8"><?= cbs_duration($booking['voice_note_duration_seconds'] ?? null) ?> attached</dd>
                <?php endif; ?>

                <dt class="col-sm-4 text-muted">Amount</dt>
                <dd class="col-sm-8"><?= cbs_money((float) $booking['amount'], $booking['currency']) ?></dd>

                <dt class="col-sm-4 text-muted">Submitted</dt>
                <dd class="col-sm-8"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['created_at']]) ?></dd>

                <?php if (! empty($booking['aired_at'])): ?>
                    <dt class="col-sm-4 text-muted">Aired</dt>
                    <dd class="col-sm-8"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['aired_at']]) ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
