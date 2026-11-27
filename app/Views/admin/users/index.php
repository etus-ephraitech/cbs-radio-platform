<?php
/**
 * Admin > Users (superadmin only).
 *
 * @var list<array<string, mixed>> $users
 * @var int                        $total
 * @var int                        $page
 * @var int                        $totalPages
 * @var array<string, string>      $filters
 * @var array<string, string>      $roles    role name => title
 * @var array<string, string>      $statuses status => label
 * @var int                        $meId
 */
$badge = static fn (string $status): string => match ($status) {
    'active'    => 'bg-success-transparent text-success',
    'suspended' => 'bg-danger-transparent text-danger',
    'pending'   => 'bg-warning-transparent text-warning',
    default     => 'bg-secondary-transparent text-secondary',
};
?>
<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Users<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Users',
    'subtitle'    => 'Who can sign in to the admin area, and as what. Only superadmins see this page.',
    'actionUrl'   => site_url('admin/users/create'),
    'actionLabel' => 'New user',
    'actionIcon'  => 'ri-user-add-line',
]) ?>

<div class="card custom-card">
    <div class="card-header justify-content-between">
        <div class="card-title">
            People
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= (int) $total ?></span>
        </div>
    </div>

    <?= view_cell('App\Cells\FilterBar::render', [
        'action' => 'admin/users',
        'fields' => [
            ['name' => 'q', 'label' => 'Search', 'type' => 'search', 'placeholder' => 'Email address'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => $roles],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statuses],
        ],
        'values' => $filters,
    ]) ?>

    <?php if (empty($users)): ?>
        <div class="cbs-empty p-4 text-center">
            <h6>No one matches.</h6>
            <p class="mb-0 fs-13 text-muted">Try fewer filters, or add the person with New user.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
                <thead>
                    <tr><th>Email</th><th>Roles</th><th>Status</th><th>Last sign-in</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= esc($user['email'] ?: '(no email)') ?>
                                <?php if ((int) $user['id'] === $meId): ?><span class="badge bg-primary-transparent ms-1">You</span><?php endif; ?>
                            </td>
                            <td>
                                <?php foreach ($user['roles'] as $role): ?>
                                    <span class="badge bg-light text-default border"><?= esc($roles[$role] ?? $role) ?></span>
                                <?php endforeach; ?>
                                <?php if ($user['roles'] === []): ?><span class="text-muted fs-13">None</span><?php endif; ?>
                            </td>
                            <td><span class="badge <?= $badge((string) $user['status']) ?>"><?= esc($statuses[$user['status']] ?? $user['status']) ?></span></td>
                            <td class="text-muted fs-13"><?= $user['last_login_at'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => (string) $user['last_login_at']]) : 'Never' ?></td>
                            <td class="text-end">
                                <a href="<?= site_url('admin/users/' . $user['id']) ?>" class="btn btn-sm btn-primary-light">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <?= view_cell('App\Cells\Pagination::render', ['page' => $page, 'totalPages' => $totalPages, 'baseUrl' => 'admin/users', 'query' => $filters]) ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
