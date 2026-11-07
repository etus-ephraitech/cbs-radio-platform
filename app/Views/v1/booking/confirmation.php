<?php
/**
 * A greeting's status, linkable from the confirmation SMS: where it is (paid, checked, on air), what was sent and
 * the gifts that go with it.
 *
 * @var array<string, mixed>       $booking
 * @var list<array<string, mixed>> $giftOrders gifts paid for with this greeting
 */
$this->setVar('lean', true);
$this->setVar('fullTitle', null); // the page title below names this page; never a title left from another page
$this->setVar('pageTitle', 'Greeting status');

$asset     = static fn (string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');
$reference = cbs_booking_reference((int) $booking['id']);
$status    = $booking['status'];

// How far along the greeting is: 1 submitted, 2 paid, 3 approved, 4 on air.
$reached = match ($status) {
    'pending_payment'    => 1,
    'pending_moderation' => 2,
    'approved'           => 3,
    'aired'              => 4,
    'rejected'           => 2,
    default              => 1, // cancelled: stopped wherever it was
};

$stages = [
    1 => ['icon' => 'mail', 'title' => 'Greeting received', 'waiting' => 'Greeting received', 'text' => 'We have your message.'],
    2 => ['icon' => 'shield', 'title' => 'Payment confirmed', 'waiting' => 'Waiting for payment', 'text' => 'Waiting for payment to be confirmed.'],
    3 => ['icon' => 'check', 'title' => 'Approved by our team', 'waiting' => 'Being checked by our team', 'text' => 'Every greeting is read by a person before it airs.'],
    4 => ['icon' => 'radio', 'title' => 'Read on air', 'waiting' => 'Queued for the show', 'text' => empty($booking['aired_at']) ? 'We read it out live on CBS Radio.' : ''],
];

if ($reached >= 2) {
    $stages[2]['text'] = 'Thank you — your payment is in.';
}
if ($status === 'pending_moderation') {
    $stages[3]['text'] = 'Our team is reading your greeting now.';
}
if ($status === 'approved') {
    $stages[4]['text'] = 'It is in the queue for the show.';
}

$headline = match ($status) {
    'pending_payment'    => 'Waiting for your payment',
    'pending_moderation' => 'Paid — being checked by our team',
    'approved'           => 'Approved and queued for the show',
    'aired'              => 'Your greeting has been read on air',
    'rejected'           => "We couldn't air this greeting",
    'cancelled'          => 'This greeting was cancelled',
    default              => 'Greeting status',
};
?>
<?= $this->extend(ui_layout('public')) ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= esc($asset('assets/css/site.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="ck-page">
    <section class="ck-hero">
        <div class="ck-wrap">
            <p class="ck-eyebrow"><?= cbs_icon_svg('radio', 1.1) ?> Greeting status</p>
            <h1><?= esc($headline) ?></h1>
            <span class="ck-ref">Reference <code><?= esc($reference) ?></code></span>
        </div>
    </section>

    <div class="ck-wrap ck-lift">
        <div class="ck-split">
            <div class="ck-card ck-card--pad">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.4rem">
                    <h2 style="margin:0">Where it is now</h2>
                    <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'booking', 'status' => $status]) ?>
                </div>

                <ol class="ck-timeline">
                    <?php foreach ($stages as $n => $stage): ?>
                        <?php
                        if ($n === 1 || $n <= $reached) {
                            $state = 'done';
                        } elseif ($status === 'rejected' && $n === 3) {
                            $state          = 'stopped';
                            $stage['title'] = 'Not approved';
                            $stage['text']  = 'Our team could not air this greeting. Contact us with your reference for help.';
                        } elseif ($status === 'cancelled' && $n === $reached + 1) {
                            $state          = 'stopped';
                            $stage['title'] = 'Cancelled';
                            $stage['text']  = 'This greeting will not be read on air.';
                        } elseif ($n === $reached + 1 && ! in_array($status, ['rejected', 'cancelled'], true)) {
                            $state          = 'current';
                            $stage['title'] = $stage['waiting'];
                        } else {
                            $state = 'todo';
                        }
                        ?>
                        <li data-state="<?= $state ?>">
                            <span class="ck-dot"><?= cbs_icon_svg($state === 'stopped' ? 'x' : $stage['icon'], 1.05) ?></span>
                            <div>
                                <strong><?= esc($stage['title']) ?></strong>
                                <?php if ($stage['text'] !== ''): ?>
                                    <span><?= esc($stage['text']) ?></span>
                                <?php endif; ?>
                                <?php if ($n === 1): ?>
                                    <span><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['created_at']]) ?></span>
                                <?php endif; ?>
                                <?php if ($n === 4 && ! empty($booking['aired_at'])): ?>
                                    <span>Aired <?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $booking['aired_at']]) ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <?php if ($status === 'pending_payment'): ?>
                    <div class="ck-actions">
                        <a class="ck-btn ck-btn--gold" href="<?= site_url('booking/' . $booking['id'] . '/pay') ?>">How to pay</a>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <div class="ck-card ck-card--pad">
                    <h2 style="margin-bottom:1rem">Your greeting</h2>
                    <dl class="ck-facts">
                        <dt>Reference</dt><dd><?= esc($reference) ?></dd>
                        <dt>Recipient</dt><dd><?= esc($booking['recipient_name']) ?></dd>
                        <dt>Message</dt><dd class="ck-msg"><?= esc($booking['message_text']) ?></dd>
                        <?php if (! empty($booking['voice_note_path'])): ?>
                            <dt>Voice note</dt><dd><?= cbs_duration($booking['voice_note_duration_seconds'] ?? null) ?> attached</dd>
                        <?php endif; ?>
                        <dt>Amount</dt><dd><?= cbs_money((float) $booking['amount'], $booking['currency']) ?></dd>
                    </dl>
                </div>

                <?php if ($giftOrders !== []): ?>
                    <div class="ck-card ck-card--pad">
                        <h2 style="margin-bottom:1rem">Gifts with it</h2>
                        <ul class="ck-sum-gifts">
                            <?php foreach ($giftOrders as $order): ?>
                                <?php $giftLook = cbs_gift_look($order['gift_type'] ?? ''); ?>
                                <li style="align-items:center;padding:.6rem 0">
                                    <span style="display:flex;align-items:center;gap:.75rem">
                                        <span class="ck-art ck-art--<?= esc($giftLook['tone'], 'attr') ?>" style="width:2.4rem;height:2.4rem;border-radius:12px"><?= cbs_icon_svg($giftLook['icon'], 1.15) ?></span>
                                        <span><strong><?= (int) $order['quantity'] > 1 ? (int) $order['quantity'] . ' &times; ' : '' ?><?= esc($order['gift_name']) ?></strong><br><span class="ck-hint"><?= cbs_money((float) $order['amount'], $order['currency']) ?></span></span>
                                    </span>
                                    <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'gift-order', 'status' => $order['status']]) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <p class="ck-hint" style="margin-top:1.25rem">Want to send another? <a href="<?= site_url('booking') ?>">Send a new greeting</a>.</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
