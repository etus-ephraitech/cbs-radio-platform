<?php
/**
 * Create an account. Off for CBS Radio (Config\Auth::$allowRegistration = false: staff accounts are created with
 * `php spark auth:user:create`), but styled to match in case it is ever switched on.
 *
 * @var string                                                                            $authLayout
 * @var string                                                                            $authSection
 * @var array<string, string>                                                             $authViews
 * @var array<string, string>                                                             $registrationFields
 * @var array<string, array{label: string, input: string, autocomplete: string, required: bool}> $identifierFields
 * @var list<string>                                                                      $passwordRules
 * @var array<string, string>                                                             $errors
 * @var array<string, mixed>                                                              $old
 */
?>
<?= $this->extend($authLayout) ?>

<?= $this->section($authSection) ?>
<div class="au-card au-card--wide">
    <span class="au-icon au-icon--gold"><?= cbs_icon_svg('user', 1.5) ?></span>
    <h1>Create your account</h1>
    <p class="au-lede">It takes a minute.</p>

    <?= $this->include($authViews['messages']) ?>

    <form method="post" action="<?= url_to('auth.register.store') ?>" novalidate data-auth-form>
        <?= csrf_field() ?>

        <?php foreach ($registrationFields as $column => $label): ?>
            <div class="au-field">
                <label class="au-label" for="<?= esc($column, 'attr') ?>"><?= esc($label) ?></label>
                <input type="text" id="<?= esc($column, 'attr') ?>" name="<?= esc($column, 'attr') ?>" class="au-input"
                    value="<?= esc($old[$column] ?? '', 'attr') ?>"<?= cbs_field_invalid($errors, $column) ?>>
                <?= cbs_field_error($errors, $column) ?>
            </div>
        <?php endforeach ?>

        <?php foreach ($identifierFields as $type => $field): ?>
            <div class="au-field">
                <label class="au-label" for="<?= esc($type, 'attr') ?>">
                    <?= esc($field['label']) ?><?php if (! $field['required']): ?> <small>(optional)</small><?php endif ?>
                </label>
                <input type="<?= esc($field['input'], 'attr') ?>" id="<?= esc($type, 'attr') ?>" name="<?= esc($type, 'attr') ?>" class="au-input"
                    value="<?= esc($old[$type] ?? '', 'attr') ?>" autocomplete="<?= esc($field['autocomplete'], 'attr') ?>"
                    <?= $field['required'] ? 'required' : '' ?><?= cbs_field_invalid($errors, $type) ?>>
                <?= cbs_field_error($errors, $type) ?>
            </div>
        <?php endforeach ?>

        <?php $this->setVar('pwField', 'password')->setVar('pwLabel', 'Password')->setVar('pwAutocomplete', 'new-password')->setVar('pwRules', $passwordRules)->setVar('pwExtra', ''); ?>
        <?= $this->include('auth/_password') ?>

        <?php $this->setVar('pwField', 'password_confirm')->setVar('pwLabel', 'Confirm password')->setVar('pwRules', []); ?>
        <?= $this->include('auth/_password') ?>

        <button type="submit" class="au-btn" data-busy-label="Creating…">Create account</button>
    </form>

    <p class="au-alt">Already have an account? <a href="<?= url_to('auth.login') ?>">Sign in</a></p>
</div>
<?= $this->endSection() ?>
