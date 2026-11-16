<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Categories<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Categories',
    'subtitle'    => 'What listeners can book. A deactivated category disappears from the booking flow; existing bookings are unaffected.',
    'actionUrl'   => site_url('admin/categories/create'),
    'actionLabel' => 'New category',
    'actionIcon'  => 'ri-add-line',
]) ?>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">
            Categories
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= count($categories) ?></span>
        </div>
    </div>

    <?php if (empty($categories)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-folder-3-line" aria-hidden="true"></i></span>
            <h6>No categories yet.</h6>
            <p class="mb-3 fs-13">Until there is one, the booking flow has nothing to offer listeners.</p>
            <a href="<?= site_url('admin/categories/create') ?>" class="btn btn-primary"><i class="ri-add-line me-1" aria-hidden="true"></i>New category</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
                <thead>
                    <tr><th>Name</th><th>Booking URL</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($category['name']) ?></td>
                            <td><code>/booking/<?= esc($category['slug']) ?></code></td>
                            <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $category['is_active']]) ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('admin/categories/' . $category['id'] . '/edit') ?>" class="btn btn-sm btn-primary-light"><i class="ri-pencil-line me-1" aria-hidden="true"></i>Edit</a>
                                    <?= view_cell('App\Cells\ActiveToggle::render', [
                                        'baseUrl'      => 'admin/categories',
                                        'id'           => $category['id'],
                                        'isActive'     => $category['is_active'],
                                        'confirmTitle' => 'Deactivate this category?',
                                        'confirmText'  => 'It will no longer appear in the booking flow. Existing bookings are unaffected.',
                                    ]) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
