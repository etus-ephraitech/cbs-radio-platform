<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<?php
$reach = array_filter([
    $contact->contactPhone !== '' ? 'call ' . $contact->contactPhone : '',
    $contact->contactEmail !== '' ? 'email ' . $contact->contactEmail : '',
]);
?>

<div class="mx-auto" style="max-width: 480px;">
    <div class="card shadow-sm">
        <div class="card-body text-center py-4">
            <h1 class="h4 mb-3">Pay for your gift</h1>
            <p class="text-muted">Reference <strong><?= esc(cbs_gift_reference((int) $giftOrder['id'])) ?></strong></p>
            <p class="display-6 mb-4"><?= cbs_money((float) $giftOrder['amount'], $giftOrder['currency']) ?></p>

            <?php if ($result['success'] && $result['manual']): ?>
                <div class="alert alert-info text-start">
                    <?= nl2br(esc($payments->manualInstructions)) ?>
                    <?php if ($payments->merchantCode !== ''): ?>
                        <div class="mt-2">Merchant code: <strong><?= esc($payments->merchantCode) ?></strong></div>
                    <?php endif; ?>
                    <div class="mt-2">Please quote reference <strong><?= esc(cbs_gift_reference((int) $giftOrder['id'])) ?></strong>.</div>
                </div>
                <?php if ($reach): ?>
                    <p class="text-muted small">Questions? <?= esc(ucfirst(implode(' or ', $reach))) ?>.</p>
                <?php endif; ?>
            <?php elseif ($result['success']): ?>
                <div class="alert alert-success"><?= esc($result['message']) ?></div>
                <p class="text-muted small">Check your phone for a Mobile Money prompt.</p>
            <?php else: ?>
                <div class="alert alert-warning"><?= esc($result['message']) ?></div>
                <?php if ($reach): ?>
                    <p class="text-muted small">If this doesn't resolve shortly, <?= esc(implode(' or ', $reach)) ?>.</p>
                <?php endif; ?>
            <?php endif; ?>

            <a href="<?= site_url('gifts/' . $giftOrder['id'] . '/confirmation') ?>" class="btn btn-cbs-primary">View status</a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
