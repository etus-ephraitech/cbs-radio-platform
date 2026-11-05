<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Gifts<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'                => 'Gifts',
    'subtitle'             => 'The catalog listeners can send. Our own gifts are editable here; vendor gifts come from the vendor sync.',
    'actionUrl'            => site_url('admin/gifts/create'),
    'actionLabel'          => 'New local gift',
    'actionIcon'           => 'ri-add-line',
    'secondaryActionUrl'   => site_url('admin/gift-vendors'),
    'secondaryActionLabel' => 'Manage vendors',
    'secondaryActionIcon'  => 'ri-store-2-line',
]) ?>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">
            Our gifts
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= count($localGifts) ?></span>
        </div>
    </div>

    <?php if (empty($localGifts)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-gift-2-line" aria-hidden="true"></i></span>
            <h6>No local gifts yet.</h6>
            <p class="mb-3 fs-13">E-cards, airtime, shout-outs and dedications fulfilled by CBS Radio itself.</p>
            <a href="<?= site_url('admin/gifts/create') ?>" class="btn btn-primary"><i class="ri-add-line me-1" aria-hidden="true"></i>New local gift</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
                <thead>
                    <tr><th>Name</th><th>Type</th><th class="text-end">Price</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($localGifts as $gift): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($gift['name']) ?></td>
                            <td class="text-capitalize"><?= esc($gift['type']) ?></td>
                            <td class="text-end fw-semibold"><?= cbs_money((float) $gift['price'], $gift['currency']) ?></td>
                            <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $gift['is_active']]) ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('admin/gifts/' . $gift['id'] . '/edit') ?>" class="btn btn-sm btn-primary-light"><i class="ri-pencil-line me-1" aria-hidden="true"></i>Edit</a>
                                    <?= view_cell('App\Cells\ActiveToggle::render', [
                                        'baseUrl'      => 'admin/gifts',
                                        'id'           => $gift['id'],
                                        'isActive'     => $gift['is_active'],
                                        'confirmTitle' => 'Deactivate this gift?',
                                        'confirmText'  => 'It will no longer appear in the gift catalog.',
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

<div class="card custom-card">
    <div class="card-header justify-content-between">
        <div>
            <div class="card-title">
                Vendor-sourced gifts
                <span class="badge bg-secondary-transparent rounded-pill ms-1 align-middle"><?= count($vendorGifts) ?></span>
            </div>
            <p class="text-muted fs-12 mb-0 mt-1">Name, price, and description are set by the vendor's catalog and refresh on each sync — only availability can be changed here.</p>
        </div>
    </div>

    <?php if (empty($vendorGifts)): ?>
        <div class="cbs-empty pt-2">
            <span class="avatar avatar-xl avatar-rounded bg-secondary-transparent fs-3"><i class="ri-store-2-line" aria-hidden="true"></i></span>
            <h6>No vendor gifts synced yet.</h6>
            <p class="mb-0 fs-13">Add a vendor and run a sync to bring their products in.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
                <thead>
                    <tr><th>Name</th><th>Vendor</th><th>Type</th><th class="text-end">Price</th><th>Last synced</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($vendorGifts as $gift): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($gift['name']) ?></td>
                            <td><?= esc($gift['vendor_name']) ?></td>
                            <td class="text-capitalize"><?= esc($gift['type']) ?></td>
                            <td class="text-end"><?= cbs_money((float) $gift['price'], $gift['currency']) ?></td>
                            <td class="fs-13"><?= $gift['synced_at'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => $gift['synced_at']]) : '&mdash;' ?></td>
                            <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $gift['is_active']]) ?></td>
                            <td class="text-end">
                                <?= view_cell('App\Cells\ActiveToggle::render', [
                                    'baseUrl'         => 'admin/gifts',
                                    'id'              => $gift['id'],
                                    'isActive'        => $gift['is_active'],
                                    'confirm'         => false,
                                    'deactivateLabel' => 'Hide',
                                    'activateLabel'   => 'Unhide',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
