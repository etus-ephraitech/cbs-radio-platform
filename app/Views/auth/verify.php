<?php
/**
 * Verify an email address or phone number with the code that was sent to it.
 *
 * @var string                $authLayout
 * @var string                $authSection
 * @var array<string, string> $authViews
 * @var string                $type        email | phone
 * @var string                $destination where the code went (masked)
 * @var int                   $codeLength
 * @var int                   $resendIn    seconds until another code can be sent
 * @var array<string, string> $errors
 */
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<div class="au-card">
    <span class="au-icon au-icon--gold"><?= cbs_icon_svg($type === 'phone' ? 'phone' : 'mail', 1.5) ?></span>
    <h1>Verify your <?= $type === 'phone' ? 'phone number' : 'email address' ?></h1>
    <p class="au-lede">Enter the code we sent to <strong><?= esc($destination) ?></strong>.</p>

    <?= $this->include($authViews['messages']) ?>

    <form method="post" action="<?= url_to('auth.verify.confirm') ?>" novalidate data-auth-form>
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= esc($type, 'attr') ?>">

        <div class="au-field">
            <label class="au-label" for="code">Verification code</label>
            <input type="text" id="code" name="code" class="au-input au-code" inputmode="numeric" autocomplete="one-time-code"
                maxlength="<?= (int) $codeLength ?>" required autofocus data-digits<?= cbs_field_invalid($errors, 'code') ?>>
            <?= cbs_field_error($errors, 'code') ?>
        </div>

        <button type="submit" class="au-btn" data-busy-label="Checking…">Verify</button>
    </form>

    <div class="au-divider"></div>

    <form method="post" action="<?= url_to('auth.verify.resend') ?>" style="text-align:center">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= esc($type, 'attr') ?>">
        <?php if ($resendIn > 0): ?>
            <p class="au-hint" style="margin:0 0 .25rem" data-countdown="<?= (int) $resendIn ?>">You can request a new code in <span><?= (int) $resendIn ?></span> seconds.</p>
            <button type="submit" class="au-linkbtn" disabled data-countdown-target>Send a new code</button>
        <?php else: ?>
            <button type="submit" class="au-linkbtn">Send a new code</button>
        <?php endif ?>
    </form>
</div>
<?= $this->endSection() ?>
