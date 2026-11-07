<?php
/**
 * How to pay, for a greeting or a gift order: the instructions in manual mode, the "check your phone" state when a
 * Mobile Money prompt was sent, or what went wrong. Included by v1/booking/pay and v1/gifts/pay after they set:
 *
 * @var array{success: bool, message: string, payment: ?array, manual: bool} $result
 * @var \Config\Payments $payments
 * @var \Config\CbsRadio $contact
 * @var string           $reference  the reference the payer must quote
 * @var string           $amountText the amount, formatted
 * @var string           $statusUrl  where to follow it
 */
$reach = array_filter([
    $contact->contactPhone !== '' ? 'call ' . $contact->contactPhone : '',
    $contact->contactEmail !== '' ? 'email ' . $contact->contactEmail : '',
]);
?>
<?php if ($result['success'] && $result['manual']): ?>
    <h2>How to pay</h2>
    <p class="ck-sub">Pay by Mobile Money, then we confirm it and send your order on its way.</p>

    <ol class="ck-howto">
        <li>
            <div>
                <strong>Send <?= esc($amountText) ?></strong>
                <p class="ck-instructions"><?= esc($payments->manualInstructions) ?></p>
                <?php if ($payments->merchantCode !== ''): ?>
                    <div class="ck-code">
                        <span>Merchant code: <strong data-copy-source><?= esc($payments->merchantCode) ?></strong></span>
                        <button type="button" class="ck-btn ck-btn--line ck-copy" data-copy hidden><?= cbs_icon_svg('copy', 1) ?> Copy</button>
                    </div>
                <?php endif; ?>
            </div>
        </li>
        <li>
            <div>
                <strong>Quote your reference</strong>
                <p>Please quote reference <strong><?= esc($reference) ?></strong>, so we can match your payment.</p>
            </div>
        </li>
        <li>
            <div>
                <strong>We take it from there</strong>
                <p>Once our team confirms the payment, you'll get an SMS, and you can follow every step on the status page.</p>
            </div>
        </li>
    </ol>

    <?php if ($reach): ?>
        <p class="ck-hint" style="margin-top:1.25rem">Questions? <?= esc(ucfirst(implode(' or ', $reach))) ?>.</p>
    <?php endif; ?>
<?php elseif ($result['success']): ?>
    <h2>Approve the payment on your phone</h2>
    <div class="ck-waiting" style="margin-top:1rem">
        <span class="ck-pulse"><?= cbs_icon_svg('phone', 1.3) ?></span>
        <div>
            <strong><?= esc($result['message']) ?></strong>
            <p class="ck-hint">Check your phone for a Mobile Money prompt and enter your PIN. Once it's confirmed, your order moves on automatically.</p>
        </div>
    </div>
<?php else: ?>
    <h2>We couldn't start the payment</h2>
    <div class="ck-alert ck-alert--warn" style="margin-top:1rem">
        <?= cbs_icon_svg('alert', 1.25) ?>
        <div>
            <strong><?= esc($result['message']) ?></strong>
            <p style="margin:.35rem 0 0">
                If this doesn't resolve shortly, contact CBS Radio with your reference <strong><?= esc($reference) ?></strong>
                <?= $reach ? '(' . esc(implode(' or ', $reach)) . ')' : '' ?> — our team can confirm payment manually.
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="ck-actions">
    <a class="ck-btn ck-btn--ink" href="<?= esc($statusUrl, 'attr') ?>">View status <?= cbs_icon_svg('arrow', 1.05) ?></a>
</div>
