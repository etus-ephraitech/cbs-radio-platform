<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Packages<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Packages',
    'subtitle'    => 'The priced options listeners choose between inside each category.',
    'actionUrl'   => site_url('admin/rate-tiers/create'),
    'actionLabel' => 'New package',
    'actionIcon'  => 'ri-add-line',
]) ?>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">
            Packages
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= count($rateTiers) ?></span>
        </div>
    </div>

    <?php if (empty($rateTiers)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-stack-line" aria-hidden="true"></i></span>
            <h6>No packages yet.</h6>
            <p class="mb-3 fs-13">Listeners need at least one package in a category to book and pay.</p>
            <a href="<?= site_url('admin/rate-tiers/create') ?>" class="btn btn-primary"><i class="ri-add-line me-1" aria-hidden="true"></i>New package</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
                <thead>
                    <tr><th>Category</th><th>Package</th><th>On-air?</th><th class="text-end">Price</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rateTiers as $tier): ?>
                        <tr>
                            <td><?= esc($tier['category_name']) ?></td>
                            <td class="text-wrap" style="min-width: 14rem;">
                                <span class="fw-semibold"><?= esc($tier['package_level']) ?></span>
                                <?php if (! empty($tier['features'])): ?>
                                    <span class="cell-sub"><?= esc(cbs_excerpt($tier['features'], 80)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $tier['includes_on_air'] === 1): ?>
                                    <span class="badge bg-info-transparent"><i class="ri-mic-line me-1" aria-hidden="true"></i>Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted">No</span>
                                <?php endif; ?>
                                <?php if ((int) ($tier['includes_voice_note'] ?? 0) === 1): ?>
                                    <span class="badge bg-success-transparent"><i class="ri-voice-recognition-line me-1" aria-hidden="true"></i>Voice note</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold"><?= cbs_money((float) $tier['price'], $tier['currency']) ?></td>
                            <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $tier['is_active']]) ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('admin/rate-tiers/' . $tier['id'] . '/edit') ?>" class="btn btn-sm btn-primary-light"><i class="ri-pencil-line me-1" aria-hidden="true"></i>Edit</a>
                                    <?= view_cell('App\Cells\ActiveToggle::render', [
                                        'baseUrl'      => 'admin/rate-tiers',
                                        'id'           => $tier['id'],
                                        'isActive'     => $tier['is_active'],
                                        'confirmTitle' => 'Deactivate this package?',
                                        'confirmText'  => 'It will no longer be selectable in the booking flow.',
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
