<?php
/**
 * Forgot password: ask for the sign-in identifier and send a reset code or link.
 *
 * @var string                $authLayout
 * @var string                $authSection
 * @var array<string, string> $authViews
 * @var string                $loginLabel
 * @var array<string, string> $errors
 * @var array<string, mixed>  $old
 */
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<div class="au-card">
    <span class="au-icon"><?= cbs_icon_svg('key', 1.5) ?></span>
    <h1>Forgot your password?</h1>
    <p class="au-lede">Enter your details and we'll send you a code to reset it.</p>

    <?= $this->include($authViews['messages']) ?>

    <form method="post" action="<?= url_to('auth.forgot.send') ?>" novalidate data-auth-form>
        <?= csrf_field() ?>

        <div class="au-field">
            <label class="au-label" for="login"><?= esc($loginLabel) ?></label>
            <div class="au-control au-control--icon">
                <?= cbs_icon_svg('user', 1.1) ?>
                <input type="text" id="login" name="login" class="au-input" value="<?= esc($old['login'] ?? '', 'attr') ?>"
                    autocomplete="username" autocapitalize="off" spellcheck="false" required autofocus<?= cbs_field_invalid($errors, 'login') ?>>
            </div>
            <?= cbs_field_error($errors, 'login') ?>
        </div>

        <button type="submit" class="au-btn" data-busy-label="Sending…">Send reset code</button>
    </form>

    <a class="au-back" href="<?= url_to('auth.login') ?>"><?= cbs_icon_svg('back', 1) ?> Back to sign in</a>
</div>
<?= $this->endSection() ?>
