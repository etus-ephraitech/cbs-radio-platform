<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<?php
// "Call 0772… or email x@y" — built from whichever contact details are set.
$reach = array_filter([
    $contact->contactPhone !== '' ? 'call ' . $contact->contactPhone : '',
    $contact->contactEmail !== '' ? 'email ' . $contact->contactEmail : '',
]);
?>

<div class="mx-auto" style="max-width: 480px;">
    <div class="card shadow-sm">
        <div class="card-body text-center py-4">
            <h1 class="h4 mb-3">Pay for your greeting</h1>
            <p class="text-muted">Reference <strong><?= esc(cbs_booking_reference((int) $booking['id'])) ?></strong></p>
            <p class="display-6 mb-4"><?= cbs_money((float) $booking['amount'], $booking['currency']) ?></p>

            <?php if ($result['success'] && $result['manual']): ?>
                <div class="alert alert-info text-start">
                    <?= nl2br(esc($payments->manualInstructions)) ?>
                    <?php if ($payments->merchantCode !== ''): ?>
                        <div class="mt-2">Merchant code: <strong><?= esc($payments->merchantCode) ?></strong></div>
                    <?php endif; ?>
                    <div class="mt-2">Please quote reference <strong><?= esc(cbs_booking_reference((int) $booking['id'])) ?></strong>.</div>
                </div>
                <?php if ($reach): ?>
                    <p class="text-muted small">Questions? <?= esc(ucfirst(implode(' or ', $reach))) ?>.</p>
                <?php endif; ?>
            <?php elseif ($result['success']): ?>
                <div class="alert alert-success"><?= esc($result['message']) ?></div>
                <p class="text-muted small">Check your phone for a Mobile Money prompt. Once confirmed, your greeting moves to review automatically.</p>
            <?php else: ?>
                <div class="alert alert-warning"><?= esc($result['message']) ?></div>
                <p class="text-muted small">
                    If this doesn't resolve shortly, contact CBS Radio with your reference above
                    <?= $reach ? '(' . esc(implode(' or ', $reach)) . ')' : '' ?> — our team can confirm payment manually.
                </p>
            <?php endif; ?>

            <a href="<?= site_url('booking/' . $booking['id'] . '/confirmation') ?>" class="btn btn-cbs-primary">View status</a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
