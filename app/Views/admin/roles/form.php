<?php
/**
 * Admin > Roles > New role / one role (superadmin only).
 *
 * @var array<string, mixed>|null               $role      null when making a new one
 * @var array<string, array<string, string>>    $groups    area => [permission => label]
 * @var bool                                    $available
 */
$isNew   = $role === null;
$isSuper = ! $isNew && $role['isSuper'];
$chosen  = old('permissions') !== null ? (array) old('permissions') : ($isNew ? [] : $role['permissions']);
$title   = $isNew ? 'New role' : $role['title'];
$action  = $isNew ? site_url('admin/roles') : site_url('admin/roles/' . $role['name'] . '/update');
?>
<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', ['title' => $title, 'backUrl' => site_url('admin/roles'), 'backLabel' => 'Roles']) ?>

<?= view_cell('App\Cells\FormErrors::render') ?>

<?php if (! $available): ?>
    <div class="alert alert-warning">Roles cannot be edited until <code>ephraitech/ci4-auth</code> is 0.11 or later.</div>
<?php else: ?>

<?php if (! $isNew && $role['inConfig'] && ! $role['appManaged']): ?>
    <div class="alert alert-info fs-13">This role comes from the configuration. Saving it here makes this page the owner of its name, description and permissions; <code>php spark auth:sync</code> will then leave it alone.</div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" class="row g-3">
    <?= csrf_field() ?>

    <div class="col-lg-5">
        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Role</div></div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <label for="role-name" class="form-label">Name</label>
                    <?php if ($isNew): ?>
                        <input type="text" id="role-name" name="name" class="form-control" required maxlength="50" pattern="[a-z0-9][a-z0-9_\-]*" autocomplete="off"
                               value="<?= esc(old('name', null, false) ?? '', 'attr') ?>">
                        <div class="form-text">Lowercase letters, digits, "-" or "_". It cannot be changed later.</div>
                    <?php else: ?>
                        <input type="text" id="role-name" class="form-control" value="<?= esc($role['name'], 'attr') ?>" disabled>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="role-title" class="form-label">Title</label>
                    <input type="text" id="role-title" name="title" class="form-control" required maxlength="100"
                           value="<?= esc(old('title', null, false) ?? ($isNew ? '' : $role['title']), 'attr') ?>">
                </div>
                <div class="mb-0">
                    <label for="role-description" class="form-label">Description</label>
                    <textarea id="role-description" name="description" class="form-control" rows="3" maxlength="255"><?= esc(old('description', null, false) ?? ($isNew ? '' : $role['description'])) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Permissions</div></div>
            <div class="card-body pt-0">
                <?php if ($isSuper): ?>
                    <div class="alert alert-secondary fs-13 mb-0">The super role passes every check, so there is nothing to choose. You can still change its title and description.</div>
                <?php else: ?>
                    <?php foreach ($groups as $area => $permissions): ?>
                        <fieldset class="mb-3" data-permission-group>
                            <legend class="fs-12 text-uppercase text-muted d-flex justify-content-between align-items-center">
                                <span><?= esc($area) ?></span>
                                <button type="button" class="btn btn-link btn-sm p-0" data-toggle-group>Select all</button>
                            </legend>
                            <?php foreach ($permissions as $name => $label): ?>
                                <?php $id = 'perm-' . preg_replace('/[^a-z0-9]+/i', '-', $name); ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" id="<?= esc($id, 'attr') ?>" value="<?= esc($name, 'attr') ?>"<?= in_array($name, $chosen, true) ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="<?= esc($id, 'attr') ?>"><?= esc($label) ?> <code class="fs-12 text-muted"><?= esc($name) ?></code></label>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create role' : 'Save role' ?></button>
        <a href="<?= site_url('admin/roles') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>

<?php if (! $isNew && ! $isSuper): ?>
    <div class="card custom-card mt-3">
        <div class="card-header"><div class="card-title">More</div></div>
        <div class="card-body pt-0 d-flex flex-wrap gap-2 align-items-center">
            <?php if ($role['inConfig'] && $role['appManaged']): ?>
                <form id="release-form" method="post" action="<?= site_url('admin/roles/' . $role['name'] . '/release') ?>"><?= csrf_field() ?></form>
                <?= view_cell('App\Cells\ConfirmButton::render', [
                    'formId'            => 'release-form',
                    'title'             => 'Hand this role back to the configuration?',
                    'text'              => 'The next "php spark auth:sync" restores its configured title, description and permissions.',
                    'label'             => 'Hand back to configuration',
                    'buttonClass'       => 'btn btn-outline-secondary',
                    'confirmButtonText' => 'Yes, hand it back',
                ]) ?>
            <?php endif; ?>

            <?php if (! $role['inConfig']): ?>
                <form id="delete-form" method="post" action="<?= site_url('admin/roles/' . $role['name'] . '/delete') ?>"><?= csrf_field() ?></form>
                <?= view_cell('App\Cells\ConfirmButton::render', [
                    'formId'            => 'delete-form',
                    'title'             => 'Delete this role?',
                    'text'              => 'This cannot be undone. A role someone still has cannot be deleted.',
                    'label'             => 'Delete role',
                    'buttonClass'       => 'btn btn-outline-danger',
                    'confirmButtonText' => 'Yes, delete',
                ]) ?>
            <?php endif; ?>

            <span class="text-muted fs-13 ms-auto"><?= (int) $role['holders'] ?> <?= (int) $role['holders'] === 1 ? 'person has' : 'people have' ?> this role</span>
        </div>
    </div>
<?php endif; ?>

<?php endif; ?>

<script>
document.querySelectorAll('[data-permission-group]').forEach(function (group) {
    var button = group.querySelector('[data-toggle-group]');
    var boxes = group.querySelectorAll('input[type="checkbox"]');
    if (!button) { return; }
    button.addEventListener('click', function () {
        var all = Array.prototype.every.call(boxes, function (b) { return b.checked; });
        boxes.forEach(function (b) { b.checked = !all; });
    });
});
</script>

<?= $this->endSection() ?>
