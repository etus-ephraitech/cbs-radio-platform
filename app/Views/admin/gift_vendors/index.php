<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Gift Vendors',
    'subtitle'    => 'Third-party catalogs synced in via API and shown alongside our own gifts.',
    'actionUrl'   => site_url('admin/gift-vendors/create'),
    'actionLabel' => '+ New vendor',
]) ?>

<form method="post" action="<?= site_url('admin/gift-vendors/sync') ?>" class="mb-3">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline-secondary btn-sm">Sync all active vendors now</button>
    <span class="text-muted small ms-2">Also runs on a schedule via <code>php spark gifts:sync</code>.</span>
</form>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr><th>Name</th><th>Slug</th><th>Integration</th><th>API base URL</th><th>Status</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
        <?php if (empty($vendors)): ?>
            <tr><td colspan="6" class="text-muted">No vendors yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($vendors as $vendor): ?>
            <tr>
                <td><?= esc($vendor['name']) ?></td>
                <td><code><?= esc($vendor['slug']) ?></code></td>
                <td class="small"><?= $vendor['driver'] === 'katale' ? 'Katale Plus (gift cart)' : 'Generic REST' ?></td>
                <td class="small text-muted"><?= esc($vendor['api_base_url'] ?: '—') ?></td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $vendor['is_active']]) ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?= site_url('admin/gift-vendors/' . $vendor['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <?= view_cell('App\Cells\ActiveToggle::render', [
                        'baseUrl'      => 'admin/gift-vendors',
                        'id'           => $vendor['id'],
                        'isActive'     => $vendor['is_active'],
                        'confirmTitle' => 'Deactivate this vendor?',
                        'confirmText'  => 'Their products stay in the catalog but will no longer re-sync.',
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= $this->endSection() ?>
