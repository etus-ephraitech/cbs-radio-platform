<?php
/**
 * Paying for a greeting, and the gifts chosen with it, in one payment.
 *
 * @var array<string, mixed>       $booking
 * @var array                      $result     PaymentService::initiateForBooking()
 * @var \Config\Payments           $payments
 * @var \Config\CbsRadio           $contact
 * @var list<array<string, mixed>> $giftOrders gifts paid for with this greeting
 * @var float                      $total      what this payment covers
 */
$this->setVar('lean', true);
$this->setVar('fullTitle', null); // the page title below names this page; never a title left from another page
$this->setVar('pageTitle', 'Pay for your greeting');

$asset     = static fn (string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');
$reference = cbs_booking_reference((int) $booking['id']);
$currency  = $booking['currency'];

$this->setVar('reference', $reference)
    ->setVar('amountText', cbs_money($total, $currency))
    ->setVar('statusUrl', site_url('booking/' . $booking['id'] . '/confirmation'));
?>
<?= $this->extend(ui_layout('public')) ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= esc($asset('assets/css/site.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="ck-page">
    <section class="ck-hero">
        <div class="ck-wrap">
            <p class="ck-eyebrow"><?= cbs_icon_svg('shield', 1.1) ?> Almost there</p>
            <h1>Pay for your greeting</h1>
            <p class="ck-amount" style="color:#fff;margin-top:.9rem"><?= cbs_money($total, $currency) ?></p>
            <span class="ck-ref">Reference <code><?= esc($reference) ?></code></span>
        </div>
    </section>

    <div class="ck-wrap ck-lift">
        <div class="ck-split">
            <div class="ck-card ck-card--pad">
                <?= $this->include(ui_view_path('booking/_payment')) ?>
            </div>

            <aside class="ck-summary" aria-label="What you're paying for">
                <div class="ck-summary-head">
                    <span class="ck-lampdot" aria-hidden="true"></span>
                    <h2>Your order</h2>
                </div>
                <div class="ck-summary-body">
                    <div class="ck-sum-row"><span>Greeting for</span><span><?= esc($booking['recipient_name']) ?></span></div>
                    <div class="ck-sum-quote"><p><?= esc(cbs_excerpt($booking['message_text'], 160)) ?></p></div>
                    <div class="ck-sum-row"><span>Greeting</span><span><?= cbs_money((float) $booking['amount'], $currency) ?></span></div>
                    <?php if ($giftOrders !== []): ?>
                        <div class="ck-sum-row"><span>Gifts</span><span><?= count($giftOrders) === 1 ? '1 gift' : count($giftOrders) . ' gifts' ?></span></div>
                        <ul class="ck-sum-gifts">
                            <?php foreach ($giftOrders as $order): ?>
                                <li>
                                    <span><?= (int) $order['quantity'] > 1 ? (int) $order['quantity'] . ' &times; ' : '' ?><?= esc($order['gift_name']) ?></span>
                                    <span><?= cbs_money((float) $order['amount'], $order['currency']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <div class="ck-sum-total"><span>Total</span><strong><?= cbs_money($total, $currency) ?></strong></div>
                    <ul class="ck-trust">
                        <li><?= cbs_icon_svg('check', 1) ?> One payment covers everything above</li>
                        <li><?= cbs_icon_svg('check', 1) ?> Checked by our team before it airs</li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc($asset('assets/js/site.js')) ?>" defer></script>
<?= $this->endSection() ?>
