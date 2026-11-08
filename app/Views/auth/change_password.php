<?php
/**
 * Change password for a signed-in staff member.
 *
 * @var string                $authLayout
 * @var string                $authSection
 * @var array<string, string> $authViews
 * @var list<string>          $passwordRules
 * @var array<string, string> $errors
 */
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<div class="au-card">
    <span class="au-icon"><?= cbs_icon_svg('lock', 1.5) ?></span>
    <h1>Change password</h1>
    <p class="au-lede">You'll stay signed in on this device.</p>

    <?= $this->include($authViews['messages']) ?>

    <form method="post" action="<?= url_to('auth.password.update') ?>" novalidate data-auth-form>
        <?= csrf_field() ?>

        <?php $this->setVar('pwField', 'current_password')->setVar('pwLabel', 'Current password')->setVar('pwAutocomplete', 'current-password')->setVar('pwRules', [])->setVar('pwExtra', ''); ?>
        <?= $this->include('auth/_password') ?>

        <?php $this->setVar('pwField', 'password')->setVar('pwLabel', 'New password')->setVar('pwAutocomplete', 'new-password')->setVar('pwRules', $passwordRules); ?>
        <?= $this->include('auth/_password') ?>

        <?php $this->setVar('pwField', 'password_confirm')->setVar('pwLabel', 'Confirm new password')->setVar('pwRules', []); ?>
        <?= $this->include('auth/_password') ?>

        <button type="submit" class="au-btn" data-busy-label="Saving…">Change password</button>
    </form>

    <a class="au-back" href="<?= site_url('admin') ?>"><?= cbs_icon_svg('back', 1) ?> Back to the dashboard</a>
</div>
<?= $this->endSection() ?>
