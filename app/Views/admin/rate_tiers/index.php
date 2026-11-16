<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Packages',
    'subtitle'    => 'The priced options listeners choose between inside each category.',
    'actionUrl'   => site_url('admin/rate-tiers/create'),
    'actionLabel' => '+ New package',
]) ?>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr><th>Category</th><th>Package</th><th>On-air?</th><th>Price</th><th>Status</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
        <?php if (empty($rateTiers)): ?>
            <tr><td colspan="6" class="text-muted">No packages yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rateTiers as $tier): ?>
            <tr>
                <td><?= esc($tier['category_name']) ?></td>
                <td>
                    <?= esc($tier['package_level']) ?>
                    <?php if (! empty($tier['features'])): ?>
                        <div class="text-muted small"><?= esc(cbs_excerpt($tier['features'], 80)) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $tier['includes_on_air'] === 1): ?>
                        <span class="badge bg-info text-dark">Yes</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">No</span>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap"><?= cbs_money((float) $tier['price'], $tier['currency']) ?></td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $tier['is_active']]) ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?= site_url('admin/rate-tiers/' . $tier['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <?= view_cell('App\Cells\ActiveToggle::render', [
                        'baseUrl'      => 'admin/rate-tiers',
                        'id'           => $tier['id'],
                        'isActive'     => $tier['is_active'],
                        'confirmTitle' => 'Deactivate this package?',
                        'confirmText'  => 'It will no longer be selectable in the booking flow.',
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= $this->endSection() ?>
