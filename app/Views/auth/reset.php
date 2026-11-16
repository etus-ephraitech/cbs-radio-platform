<?php
/**
 * Reset password: from an emailed link (mode "link"), with a code (mode "code"), or a link that has expired.
 *
 * @var string                $authLayout
 * @var string                $authSection
 * @var array<string, string> $authViews
 * @var string                $mode       link | code | expired
 * @var string|null           $selector
 * @var string|null           $code
 * @var int                   $codeLength
 * @var string                $loginLabel
 * @var list<string>          $passwordRules
 * @var array<string, string> $errors
 * @var array<string, mixed>  $old
 */
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<div class="au-card">
    <?php if ($mode === 'expired'): ?>
        <span class="au-icon au-icon--red"><?= cbs_icon_svg('clock', 1.5) ?></span>
        <h1>This link has expired</h1>
        <p class="au-lede">Reset links work once and expire after a short time. Request a new one to continue.</p>
        <a href="<?= url_to('auth.forgot') ?>" class="au-btn">Request a new code</a>
        <a class="au-back" href="<?= url_to('auth.login') ?>"><?= cbs_icon_svg('back', 1) ?> Back to sign in</a>
    <?php else: ?>
        <span class="au-icon"><?= cbs_icon_svg('lock', 1.5) ?></span>
        <h1><?= $mode === 'link' ? 'Choose a new password' : 'Reset your password' ?></h1>
        <p class="au-lede"><?= $mode === 'link' ? 'Pick a password you haven\'t used here before.' : 'Enter the code we sent you, then choose a new password.' ?></p>

        <?= $this->include($authViews['messages']) ?>

        <form method="post" action="<?= url_to('auth.reset.update') ?>" novalidate data-auth-form>
            <?= csrf_field() ?>

            <?php if ($mode === 'link'): ?>
                <input type="hidden" name="s" value="<?= esc($selector, 'attr') ?>">
                <input type="hidden" name="c" value="<?= esc($code, 'attr') ?>">
            <?php else: ?>
                <div class="au-field">
                    <label class="au-label" for="login"><?= esc($loginLabel) ?></label>
                    <div class="au-control au-control--icon">
                        <?= cbs_icon_svg('user', 1.1) ?>
                        <input type="text" id="login" name="login" class="au-input" value="<?= esc($old['login'] ?? '', 'attr') ?>"
                            autocomplete="username" autocapitalize="off" spellcheck="false" required<?= cbs_field_invalid($errors, 'login') ?>>
                    </div>
                    <?= cbs_field_error($errors, 'login') ?>
                </div>

                <div class="au-field">
                    <label class="au-label" for="code">Code</label>
                    <input type="text" id="code" name="code" class="au-input au-code" inputmode="numeric" autocomplete="one-time-code"
                        maxlength="<?= (int) $codeLength ?>" required data-digits<?= cbs_field_invalid($errors, 'code') ?>>
                    <?= cbs_field_error($errors, 'code') ?>
                </div>
            <?php endif ?>

            <?php $this->setVar('pwField', 'password')->setVar('pwLabel', 'New password')->setVar('pwAutocomplete', 'new-password')->setVar('pwRules', $passwordRules)->setVar('pwExtra', ''); ?>
            <?= $this->include('auth/_password') ?>

            <?php $this->setVar('pwField', 'password_confirm')->setVar('pwLabel', 'Confirm new password')->setVar('pwRules', []); ?>
            <?= $this->include('auth/_password') ?>

            <button type="submit" class="au-btn" data-busy-label="Saving…">Reset password</button>
        </form>

        <?php if ($mode === 'code'): ?>
            <p class="au-alt">Didn't get a code? <a href="<?= url_to('auth.forgot') ?>">Request another</a></p>
        <?php endif ?>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
