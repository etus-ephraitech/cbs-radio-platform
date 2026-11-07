<?php
/**
 * A gift order's status.
 *
 * @var array<string, mixed> $giftOrder
 */
$this->setVar('lean', true);
$this->setVar('fullTitle', null); // the page title below names this page; never a title left from another page
$this->setVar('pageTitle', 'Gift order status');

$asset     = static fn (string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');
$reference = cbs_gift_reference((int) $giftOrder['id']);
$status    = $giftOrder['status'];
$reached   = match ($status) { 'paid' => 2, 'fulfilled' => 3, default => 1 };

$stages = [
    1 => ['icon' => 'gift', 'title' => 'Order received', 'waiting' => 'Order received', 'text' => ''],
    2 => ['icon' => 'shield', 'title' => 'Payment confirmed', 'waiting' => 'Waiting for payment', 'text' => $reached >= 2 ? 'Thank you — your payment is in.' : 'Waiting for payment to be confirmed.'],
    3 => ['icon' => 'truck', 'title' => 'Delivered', 'waiting' => 'Being prepared', 'text' => $reached >= 3 ? 'Your gift is on its way or has arrived.' : 'We send it once payment is confirmed.'],
];
?>
<?= $this->extend(ui_layout('public')) ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= esc($asset('assets/css/site.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="ck-page">
    <section class="ck-hero">
        <div class="ck-wrap">
            <p class="ck-eyebrow"><?= cbs_icon_svg('gift', 1.1) ?> Gift order status</p>
            <h1><?= $status === 'cancelled' ? 'This gift order was cancelled' : ($status === 'fulfilled' ? 'Your gift has been sent' : 'Thank you for your gift') ?></h1>
            <span class="ck-ref">Reference <code><?= esc($reference) ?></code></span>
        </div>
    </section>

    <div class="ck-wrap ck-lift">
        <div class="ck-split">
            <div class="ck-card ck-card--pad">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.4rem">
                    <h2 style="margin:0">Where it is now</h2>
                    <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'gift-order', 'status' => $status]) ?>
                </div>
                <ol class="ck-timeline">
                    <?php foreach ($stages as $n => $stage): ?>
                        <?php
                        if ($n === 1 || $n <= $reached) {
                            $state = 'done';
                        } elseif ($status === 'cancelled' && $n === $reached + 1) {
                            $state          = 'stopped';
                            $stage['title'] = 'Cancelled';
                            $stage['text']  = 'This gift will not be sent.';
                        } elseif ($n === $reached + 1 && $status !== 'cancelled') {
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
                                <?php if ($n === 1): ?>
                                    <span><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $giftOrder['created_at']]) ?></span>
                                <?php elseif ($stage['text'] !== ''): ?>
                                    <span><?= esc($stage['text']) ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($status === 'pending_payment' && (int) ($giftOrder['bundled_with_booking'] ?? 0) !== 1): ?>
                    <div class="ck-actions"><a class="ck-btn ck-btn--gold" href="<?= site_url('gifts/' . $giftOrder['id'] . '/pay') ?>">How to pay</a></div>
                <?php endif; ?>
            </div>

            <div class="ck-card ck-card--pad">
                <h2 style="margin-bottom:1rem">Your gift</h2>
                <dl class="ck-facts">
                    <dt>Reference</dt><dd><?= esc($reference) ?></dd>
                    <dt>Recipient</dt><dd><?= esc($giftOrder['recipient_name']) ?></dd>
                    <dt>Quantity</dt><dd><?= esc($giftOrder['quantity']) ?></dd>
                    <dt>Amount</dt><dd><?= cbs_money((float) $giftOrder['amount'], $giftOrder['currency']) ?></dd>
                    <?php if (! empty($giftOrder['booking_id'])): ?>
                        <dt>Greeting</dt><dd><a href="<?= site_url('booking/' . $giftOrder['booking_id'] . '/confirmation') ?>"><?= esc(cbs_booking_reference((int) $giftOrder['booking_id'])) ?></a></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
