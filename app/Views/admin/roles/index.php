<?php
/**
 * Admin > Roles (superadmin only).
 *
 * @var bool                                    $available false when the auth package cannot edit roles in the app
 * @var list<array<string, mixed>>              $roles
 * @var array<string, array<string, string>>    $groups    area => [permission => label]
 */
?>
<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Roles<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Roles',
    'subtitle'    => 'What each role is called and what it may open. Only superadmins see this page.',
    'actionUrl'   => $available ? site_url('admin/roles/create') : '',
    'actionLabel' => 'New role',
    'actionIcon'  => 'ri-add-line',
]) ?>

<?php if (! $available): ?>
    <div class="alert alert-warning" role="alert">
        <strong>Roles cannot be edited here yet.</strong> This needs <code>ephraitech/ci4-auth</code> 0.11 or later.
        Run <code>composer update ephraitech/ci4-auth</code>, then <code>php spark migrate -n "Ephraitech\Auth"</code>
        and <code>php spark auth:sync</code>.
    </div>
<?php else: ?>

<div class="card custom-card">
    <div class="card-header"><div class="card-title">All roles <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= count($roles) ?></span></div></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-cbs">
            <thead>
                <tr><th>Role</th><th>Where it comes from</th><th class="text-end">People</th><th class="text-end">Permissions</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= esc($role['title']) ?> <code class="fs-12 text-muted"><?= esc($role['name']) ?></code></div>
                            <?php if ($role['description'] !== ''): ?><div class="fs-12 text-muted"><?= esc($role['description']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($role['isSuper']): ?>
                                <span class="badge bg-danger-transparent text-danger">Super role</span>
                            <?php elseif ($role['inConfig'] && $role['appManaged']): ?>
                                <span class="badge bg-info-transparent text-info">Configuration, changed here</span>
                            <?php elseif ($role['inConfig']): ?>
                                <span class="badge bg-secondary-transparent text-secondary">Configuration</span>
                            <?php else: ?>
                                <span class="badge bg-success-transparent text-success">Made here</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= (int) $role['holders'] ?></td>
                        <td class="text-end"><?= $role['isSuper'] ? 'All' : count($role['permissions']) ?></td>
                        <td class="text-end"><a href="<?= site_url('admin/roles/' . $role['name']) ?>" class="btn btn-sm btn-primary-light">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card custom-card">
    <div class="card-header"><div class="card-title">Who may do what</div></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 text-center align-middle">
            <thead>
                <tr>
                    <th class="text-start">Permission</th>
                    <?php foreach ($roles as $role): ?><th><?= esc($role['title']) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $area => $permissions): ?>
                    <tr class="table-light"><th class="text-start text-uppercase fs-11" colspan="<?= count($roles) + 1 ?>"><?= esc($area) ?></th></tr>
                    <?php foreach ($permissions as $name => $label): ?>
                        <tr>
                            <td class="text-start"><?= esc($label) ?> <code class="fs-12 text-muted"><?= esc($name) ?></code></td>
                            <?php foreach ($roles as $role): ?>
                                <?php $has = $role['isSuper'] || in_array($name, $role['permissions'], true); ?>
                                <td><?= $has ? '<span class="text-success" aria-label="Yes">&#10003;</span>' : '<span class="text-muted" aria-label="No">&ndash;</span>' ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer fs-13 text-muted">
        A permission only has an effect where the site checks it: the admin areas (bookings, gifts, catalog, notifications, settings) do.
        Users, Roles, Logo and Appearance are always for superadmins.
    </div>
</div>

<?php endif; ?>

<?= $this->endSection() ?>
