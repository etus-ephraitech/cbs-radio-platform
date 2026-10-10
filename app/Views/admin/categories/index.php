<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Categories',
    'subtitle'    => 'What listeners can book. A deactivated category disappears from the booking flow; existing bookings are unaffected.',
    'actionUrl'   => site_url('admin/categories/create'),
    'actionLabel' => '+ New category',
]) ?>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr><th>Name</th><th>Booking URL</th><th>Status</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
        <?php if (empty($categories)): ?>
            <tr><td colspan="4" class="text-muted">No categories yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($categories as $category): ?>
            <tr>
                <td><?= esc($category['name']) ?></td>
                <td><code>/booking/<?= esc($category['slug']) ?></code></td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $category['is_active']]) ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?= site_url('admin/categories/' . $category['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <?= view_cell('App\Cells\ActiveToggle::render', [
                        'baseUrl'      => 'admin/categories',
                        'id'           => $category['id'],
                        'isActive'     => $category['is_active'],
                        'confirmTitle' => 'Deactivate this category?',
                        'confirmText'  => 'It will no longer appear in the booking flow. Existing bookings are unaffected.',
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= $this->endSection() ?>
