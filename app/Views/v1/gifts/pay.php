<?php
/**
 * Paying for a gift sent on its own (or added to a greeting afterwards).
 *
 * @var array<string, mixed> $giftOrder
 * @var array                $result    PaymentService::initiateForGiftOrder()
 * @var \Config\Payments     $payments
 * @var \Config\CbsRadio     $contact
 */
$this->setVar('lean', true);
$this->setVar('fullTitle', null); // the page title below names this page; never a title left from another page
$this->setVar('pageTitle', 'Pay for your gift');

$asset     = static fn (string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');
$reference = cbs_gift_reference((int) $giftOrder['id']);
$amount    = cbs_money((float) $giftOrder['amount'], $giftOrder['currency']);

$this->setVar('reference', $reference)
    ->setVar('amountText', $amount)
    ->setVar('statusUrl', site_url('gifts/' . $giftOrder['id'] . '/confirmation'));
?>
<?= $this->extend(ui_layout('public')) ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= esc($asset('assets/css/site.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="ck-page">
    <section class="ck-hero">
        <div class="ck-wrap">
            <p class="ck-eyebrow"><?= cbs_icon_svg('gift', 1.1) ?> Almost there</p>
            <h1>Pay for your gift</h1>
            <p class="ck-amount" style="color:#fff;margin-top:.9rem"><?= $amount ?></p>
            <span class="ck-ref">Reference <code><?= esc($reference) ?></code></span>
        </div>
    </section>

    <div class="ck-wrap ck-lift">
        <div class="ck-split">
            <div class="ck-card ck-card--pad">
                <?= $this->include(ui_view_path('booking/_payment')) ?>
            </div>
            <aside class="ck-summary" aria-label="What you're paying for">
                <div class="ck-summary-head"><span class="ck-lampdot" aria-hidden="true"></span><h2>Your gift</h2></div>
                <div class="ck-summary-body">
                    <div class="ck-sum-row"><span>For</span><span><?= esc($giftOrder['recipient_name']) ?></span></div>
                    <div class="ck-sum-row"><span>Quantity</span><span><?= esc($giftOrder['quantity']) ?></span></div>
                    <div class="ck-sum-total"><span>Total</span><strong><?= $amount ?></strong></div>
                </div>
            </aside>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc($asset('assets/js/site.js')) ?>" defer></script>
<?= $this->endSection() ?>
