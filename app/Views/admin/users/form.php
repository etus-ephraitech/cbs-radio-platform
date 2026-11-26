<?php
/**
 * Admin > Users > New user (superadmin only).
 *
 * @var array<string, string> $roles role name => title
 */
$chosen = (array) (old('roles') ?? ['user']);
?>
<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>New user<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', ['title' => 'New user', 'backUrl' => site_url('admin/users'), 'backLabel' => 'Users']) ?>

<div class="row">
    <div class="col-xl-6 col-lg-8">
        <?= view_cell('App\Cells\FormErrors::render') ?>

        <form method="post" action="<?= site_url('admin/users') ?>" class="card custom-card">
            <?= csrf_field() ?>
            <div class="card-header"><div class="card-title">Account</div></div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <label for="user-email" class="form-label">Email address</label>
                    <input type="email" id="user-email" name="email" class="form-control" required maxlength="254" autocomplete="off"
                           value="<?= esc(old('email', null, false) ?? '', 'attr') ?>">
                    <div class="form-text">They sign in with this address.</div>
                </div>

                <fieldset class="mb-3">
                    <legend class="form-label fs-14">Roles</legend>
                    <?php foreach ($roles as $name => $title): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="roles[]" id="role-<?= esc($name, 'attr') ?>" value="<?= esc($name, 'attr') ?>"<?= in_array($name, $chosen, true) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="role-<?= esc($name, 'attr') ?>"><?= esc($title) ?></label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <div class="alert alert-info fs-13 mb-0">
                    A password is generated and shown <strong>once</strong>, on the next page. Pass it to the person yourself; it is not emailed or stored anywhere you can read it again.
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create user</button>
                <a href="<?= site_url('admin/users') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
