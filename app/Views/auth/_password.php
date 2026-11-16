<?php
/**
 * A password field with a show/hide button and a Caps Lock warning (assets/js/auth.js). Included by the account
 * pages after they set, with $this->setVar():
 *
 * @var string                $pwField        input name and id
 * @var string                $pwLabel        label text
 * @var string                $pwAutocomplete current-password or new-password
 * @var list<string>          $pwRules        rules to list under the field (new passwords), or []
 * @var string                $pwExtra        HTML placed beside the label (a "Forgot password?" link), or ''
 * @var array<string, string> $errors
 */
$describedBy = trim((isset($errors[$pwField]) ? $pwField . '-error ' : '') . ($pwRules !== [] ? $pwField . '-rules ' : '') . $pwField . '-caps');
?>
<div class="au-field">
    <div class="au-field-top">
        <label class="au-label" for="<?= esc($pwField, 'attr') ?>"><?= esc($pwLabel) ?></label>
        <?= $pwExtra ?>
    </div>
    <div class="au-control au-control--toggle au-control--icon">
        <?= cbs_icon_svg('lock', 1.1) ?>
        <input type="password" id="<?= esc($pwField, 'attr') ?>" name="<?= esc($pwField, 'attr') ?>" class="au-input" required
            autocomplete="<?= esc($pwAutocomplete, 'attr') ?>" data-caps aria-describedby="<?= esc($describedBy, 'attr') ?>"
            <?= isset($errors[$pwField]) ? 'aria-invalid="true"' : '' ?>>
        <button type="button" class="au-reveal" data-reveal="<?= esc($pwField, 'attr') ?>" aria-label="Show password" aria-pressed="false" hidden>
            <span data-when="hidden"><?= cbs_icon_svg('eye', 1.2) ?></span>
            <span data-when="shown" hidden><?= cbs_icon_svg('eye-off', 1.2) ?></span>
        </button>
    </div>
    <?= cbs_field_error($errors, $pwField) ?>
    <p class="au-caps" id="<?= esc($pwField, 'attr') ?>-caps" role="status" hidden>Caps Lock is on.</p>
    <?php if ($pwRules !== []): ?>
        <ul class="au-rules" id="<?= esc($pwField, 'attr') ?>-rules">
            <?php foreach ($pwRules as $rule): ?>
                <li><?= esc($rule) ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</div>
