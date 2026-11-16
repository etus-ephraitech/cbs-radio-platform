<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Gift vendors<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Gift vendors',
    'subtitle'    => 'Third-party catalogs synced in via API and shown alongside our own gifts.',
    'actionUrl'   => site_url('admin/gift-vendors/create'),
    'actionLabel' => 'New vendor',
    'actionIcon'  => 'ri-add-line',
]) ?>

<div class="card custom-card">
    <div class="card-header justify-content-between">
        <div class="card-title">
            Vendors
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= count($vendors) ?></span>
        </div>
        <form method="post" action="<?= site_url('admin/gift-vendors/sync') ?>" class="d-flex flex-wrap align-items-center gap-2">
            <?= csrf_field() ?>
            <span class="text-muted fs-12">Also runs on a schedule via <code>php spark gifts:sync</code>.</span>
            <button type="submit" class="btn btn-sm btn-secondary-light"><i class="ri-refresh-line me-1" aria-hidden="true"></i>Sync all active vendors now</button>
        </form>
    </div>

    <?php if (empty($vendors)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-store-2-line" aria-hidden="true"></i></span>
            <h6>No vendors yet.</h6>
            <p class="mb-3 fs-13">Add a vendor to pull their products into the gift catalog.</p>
            <a href="<?= site_url('admin/gift-vendors/create') ?>" class="btn btn-primary"><i class="ri-add-line me-1" aria-hidden="true"></i>New vendor</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
                <thead>
                    <tr><th>Name</th><th>Slug</th><th>API base URL</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($vendor['name']) ?></td>
                            <td><code><?= esc($vendor['slug']) ?></code></td>
                            <td class="text-muted fs-13"><?= esc($vendor['api_base_url'] ?: '—') ?></td>
                            <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $vendor['is_active']]) ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('admin/gift-vendors/' . $vendor['id'] . '/edit') ?>" class="btn btn-sm btn-primary-light"><i class="ri-pencil-line me-1" aria-hidden="true"></i>Edit</a>
                                    <?= view_cell('App\Cells\ActiveToggle::render', [
                                        'baseUrl'      => 'admin/gift-vendors',
                                        'id'           => $vendor['id'],
                                        'isActive'     => $vendor['is_active'],
                                        'confirmTitle' => 'Deactivate this vendor?',
                                        'confirmText'  => 'Their products stay in the catalog but will no longer re-sync.',
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
