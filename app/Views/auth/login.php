<?php
/**
 * Staff sign in. Variables from ci4-auth's AuthController::login() (see BaseAuthController::render()).
 *
 * @var string                $authLayout
 * @var string                $authSection
 * @var array<string, string> $authViews
 * @var string                $loginLabel
 * @var bool                  $canReset
 * @var bool                  $canRegister
 * @var array<string, string> $errors
 * @var array<string, mixed>  $old
 */
$this->setVar('pwField', 'password')
    ->setVar('pwLabel', 'Password')
    ->setVar('pwAutocomplete', 'current-password')
    ->setVar('pwRules', [])
    ->setVar('pwExtra', $canReset ? '<a href="' . esc(url_to('auth.forgot'), 'attr') . '">Forgot password?</a>' : '');
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<div class="au-card">
    <span class="au-icon au-icon--gold"><?= cbs_icon_svg('radio', 1.5) ?></span>
    <h1>Welcome back</h1>
    <p class="au-lede">Sign in to the CBS Radio studio.</p>

    <?= $this->include($authViews['messages']) ?>

    <form method="post" action="<?= url_to('auth.login.attempt') ?>" novalidate data-auth-form>
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

        <?= $this->include('auth/_password') ?>

        <button type="submit" class="au-btn" data-busy-label="Signing in…">Sign in <?= cbs_icon_svg('arrow', 1.1) ?></button>
    </form>

    <?php if ($canRegister): ?>
        <p class="au-alt">No account yet? <a href="<?= url_to('auth.register') ?>">Create one</a></p>
    <?php endif ?>

    <p class="au-secure"><?= cbs_icon_svg('shield', 1) ?> Staff only. Sign out when you leave a shared computer.</p>
</div>
<?= $this->endSection() ?>
