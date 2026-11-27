<?php
/**
 * Admin > Users > one user (superadmin only).
 *
 * @var array<string, mixed>  $user
 * @var array<string, string> $roles    role name => title
 * @var array<string, string> $statuses status => label
 * @var bool                  $isMe
 * @var string|null           $password a generated password to show once, or null
 */
?>
<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?><?= esc($user['email'] ?: 'User') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $user['email'] ?: 'User',
    'subtitle'  => $isMe ? 'This is you. You can reset your own password here, but not your roles or status.' : '',
    'backUrl'   => site_url('admin/users'),
    'backLabel' => 'Users',
]) ?>

<?php if (! empty($password)): ?>
    <div class="alert alert-warning" role="alert">
        <strong>Copy this password now.</strong> It is shown only once and cannot be looked up again.
        <div class="mt-2"><code class="fs-5 user-select-all" id="newPassword"><?= esc($password) ?></code></div>
    </div>
<?php endif; ?>

<?= view_cell('App\Cells\FormErrors::render') ?>

<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="<?= site_url('admin/users/' . $user['id'] . '/roles') ?>" class="card custom-card">
            <?= csrf_field() ?>
            <div class="card-header"><div class="card-title">Roles</div></div>
            <div class="card-body pt-0">
                <fieldset<?= $isMe ? ' disabled' : '' ?>>
                    <?php foreach ($roles as $name => $title): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="roles[]" id="role-<?= esc($name, 'attr') ?>" value="<?= esc($name, 'attr') ?>"<?= in_array($name, $user['roles'], true) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="role-<?= esc($name, 'attr') ?>"><?= esc($title) ?></label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
            </div>
            <?php if (! $isMe): ?><div class="card-footer"><button type="submit" class="btn btn-primary">Save roles</button></div><?php endif; ?>
        </form>
    </div>

    <div class="col-lg-6">
        <form method="post" action="<?= site_url('admin/users/' . $user['id'] . '/status') ?>" class="card custom-card">
            <?= csrf_field() ?>
            <div class="card-header"><div class="card-title">Status</div></div>
            <div class="card-body pt-0">
                <fieldset<?= $isMe ? ' disabled' : '' ?>>
                    <label for="user-status" class="form-label">Account status</label>
                    <select name="status" id="user-status" class="form-select mb-3">
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= esc($value, 'attr') ?>"<?= $user['status'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="user-reason" class="form-label">Reason <span class="text-muted">(optional, kept with the account)</span></label>
                    <input type="text" name="reason" id="user-reason" class="form-control" maxlength="255" value="<?= esc((string) ($user['status_reason'] ?? ''), 'attr') ?>">
                    <div class="form-text">Suspended and inactive accounts cannot sign in.</div>
                </fieldset>
            </div>
            <?php if (! $isMe): ?><div class="card-footer"><button type="submit" class="btn btn-primary">Save status</button></div><?php endif; ?>
        </form>
    </div>

    <div class="col-12">
        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Password and account</div></div>
            <div class="card-body pt-0 d-flex flex-wrap gap-2 align-items-center">
                <form id="reset-form" method="post" action="<?= site_url('admin/users/' . $user['id'] . '/reset-password') ?>"><?= csrf_field() ?></form>
                <?= view_cell('App\Cells\ConfirmButton::render', [
                    'formId'            => 'reset-form',
                    'title'             => 'Set a new password?',
                    'text'              => 'A new password is generated and shown once. Their other sign-ins end immediately.',
                    'label'             => 'Reset password',
                    'buttonClass'       => 'btn btn-warning',
                    'confirmButtonText' => 'Yes, reset it',
                ]) ?>

                <?php if (! $isMe): ?>
                    <form id="delete-form" method="post" action="<?= site_url('admin/users/' . $user['id'] . '/delete') ?>"><?= csrf_field() ?></form>
                    <?= view_cell('App\Cells\ConfirmButton::render', [
                        'formId'            => 'delete-form',
                        'title'             => 'Delete this account?',
                        'text'              => 'They will no longer be able to sign in. Their past actions stay on record.',
                        'label'             => 'Delete account',
                        'buttonClass'       => 'btn btn-outline-danger',
                        'confirmButtonText' => 'Yes, delete',
                    ]) ?>
                <?php endif; ?>

                <span class="text-muted fs-13 ms-auto">
                    Created <?= esc($user['created_at'] ? (string) $user['created_at'] : '—') ?>
                    · Last sign-in <?= esc($user['last_login_at'] ? (string) $user['last_login_at'] : 'never') ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
