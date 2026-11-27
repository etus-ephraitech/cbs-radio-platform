<?= $this->extend(ui_layout('public')) ?>

<?php
$state    = $checkout['state'];
$isPaid   = $state === 'paid';
$headline = [
    'paid'             => 'Thank you. Your payment was received.',
    'awaiting_payment' => 'Waiting for your payment',
    'in_checkout'      => 'Your checkout is in progress',
    'open'             => 'Your checkout is waiting for you',
    'cancelled'        => 'This order was cancelled',
    'expired'          => 'This checkout has expired',
    'failed'           => 'We could not start this checkout',
    'draft'            => 'Starting your checkout',
][$state] ?? 'Your gift order';
?>

<?php if ($waiting): ?>
<?= $this->section('head') ?>
<meta http-equiv="refresh" content="15">
<?= $this->endSection() ?>
<?php endif; ?>

<?= $this->section('content') ?>

<div class="mx-auto" style="max-width: 640px;">
    <div class="card shadow-sm">
        <div class="card-body py-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h1 class="h4 mb-0"><?= esc($headline) ?></h1>
                <?= cbs_checkout_state_badge($state) ?>
            </div>

            <?php if ($state === 'awaiting_payment' || $state === 'in_checkout'): ?>
                <p class="text-muted">If you have already paid, this page updates by itself, so you can stay here. Coming back to this page does not by itself mean a payment went through.</p>
            <?php elseif ($state === 'open' && ! empty($checkout['checkout_url'])): ?>
                <p class="text-muted">You have not finished on the shop's page yet.</p>
                <a href="<?= esc($checkout['checkout_url'], 'attr') ?>" class="btn btn-cbs-primary mb-3" rel="noopener">Go to delivery and payment</a>
            <?php elseif ($state === 'expired'): ?>
                <p class="text-muted">The checkout link was not used in time. Nothing was charged. Please start again from your cart.</p>
                <a href="<?= site_url('gifts/cart') ?>" class="btn btn-cbs-primary mb-3">Back to your cart</a>
            <?php elseif ($state === 'cancelled'): ?>
                <p class="text-muted">The shop cancelled this order. If you paid, please contact us with the reference below so we can help.</p>
            <?php elseif ($state === 'failed'): ?>
                <p class="text-muted">Nothing was charged. Please try again from your cart.</p>
                <a href="<?= site_url('gifts/cart') ?>" class="btn btn-cbs-primary mb-3">Back to your cart</a>
            <?php elseif ($isPaid): ?>
                <p class="text-muted">The shop is preparing your gift. You can follow its progress below.</p>
            <?php endif; ?>

            <dl class="row small mb-3">
                <dt class="col-sm-4 text-muted">Our reference</dt>
                <dd class="col-sm-8"><?= esc($checkout['purchase_reference']) ?></dd>

                <?php if (! empty($checkout['vendor_reference'])): ?>
                    <dt class="col-sm-4 text-muted">Shop's reference</dt>
                    <dd class="col-sm-8"><?= esc($checkout['vendor_reference']) ?></dd>
                <?php endif; ?>

                <dt class="col-sm-4 text-muted"><?= $checkout['final_amount'] !== null ? 'Total' : 'Estimated total' ?></dt>
                <dd class="col-sm-8">
                    <?php if ($checkout['final_amount'] !== null): ?>
                        <?= cbs_money_decimal((float) $checkout['final_amount'], $checkout['currency']) ?>
                    <?php else: ?>
                        <?= cbs_money((float) $checkout['estimate_total'], $checkout['currency']) ?>
                        <span class="text-muted">(the shop sets the final price)</span>
                    <?php endif; ?>
                </dd>
            </dl>

            <h2 class="h6">Your gifts</h2>
            <ul class="list-unstyled small mb-3">
                <?php foreach ($lines as $line): ?>
                    <li><?= esc($line['title'] ?? 'Gift') ?> &times; <?= esc($line['quantity'] ?? 1) ?></li>
                <?php endforeach; ?>
            </ul>

            <?php if (! empty($deliveries)): ?>
                <h2 class="h6">Delivery</h2>
                <ul class="list-group list-group-flush small mb-3">
                    <?php foreach ($deliveries as $delivery): ?>
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between gap-2">
                                <span><?= esc($delivery['title'] ?? 'Gift') ?> &times; <?= esc($delivery['quantity'] ?? 1) ?></span>
                                <strong><?= esc(cbs_delivery_state_label((string) ($delivery['state'] ?? 'pending'))) ?></strong>
                            </div>
                            <?php if (! empty($delivery['requested_delivery_at'])): ?>
                                <div class="text-muted">Arrival requested for <?= esc($delivery['requested_delivery_at']) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (! empty($checkout['last_error']) && ! $isPaid): ?>
                <div class="alert alert-secondary small">We could not reach the shop just now to update this page. It will try again shortly.</div>
            <?php endif; ?>

            <p class="text-muted small mb-0">
                Keep this page's address to check on your order later.
                <?php if ($contact->contactPhone !== '' || $contact->contactEmail !== ''): ?>
                    Need help? Contact us<?= $contact->contactPhone !== '' ? ' on ' . esc($contact->contactPhone) : '' ?><?= $contact->contactEmail !== '' ? ' or ' . esc($contact->contactEmail) : '' ?>, with the reference above.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="text-center mt-3"><a href="<?= site_url('gifts') ?>" class="small">Back to gifts</a></div>
</div>

<?= $this->endSection() ?>
