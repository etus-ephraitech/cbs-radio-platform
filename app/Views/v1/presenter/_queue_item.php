<?php
/**
 * One greeting in a presenter queue: who it is for, the words to read out (large, for reading on air), its
 * reference and voice note, and the "Mark aired" button.
 *
 * @var array<string, mixed> $booking
 * @var bool                 $excerpt Shorten long messages (the all-shows overview) or show them whole
 */
$id = (int) $booking['id'];
?>
<li class="list-group-item py-3">
    <div class="d-flex flex-column flex-md-row align-items-md-start gap-3">
        <div class="flex-grow-1">
            <div class="fw-semibold fs-15 mb-1">For <?= esc($booking['recipient_name']) ?></div>
            <div class="cbs-message fs-15 mb-2"><?= esc($excerpt ? cbs_excerpt($booking['message_text'], 140) : $booking['message_text']) ?></div>
            <div class="d-flex flex-wrap align-items-center gap-2 text-muted fs-12">
                <span>Ref <span class="cbs-reference text-default"><?= esc(cbs_booking_reference($id)) ?></span></span>
                <?php if (! empty($booking['created_at'])): ?>
                    <span>&middot; <?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['created_at']]) ?></span>
                <?php endif; ?>
            </div>
            <?php if (! empty($booking['voice_note_path'])): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="badge bg-secondary-transparent"><i class="ri-mic-line me-1" aria-hidden="true"></i>Voice note (<?= cbs_duration($booking['voice_note_duration_seconds'] ?? null) ?>)</span>
                    <audio class="cbs-audio" controls preload="none" src="<?= site_url('presenter/bookings/' . $id . '/voice-note') ?>"></audio>
                </div>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= site_url('presenter/bookings/' . $id . '/aired') ?>" class="flex-shrink-0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success"><i class="ri-check-double-line me-1" aria-hidden="true"></i>Mark aired</button>
        </form>
    </div>
</li>
