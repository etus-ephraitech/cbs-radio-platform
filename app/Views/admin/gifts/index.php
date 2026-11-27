<?php $filtered = array_filter($filters ?? []) !== []; ?>
<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Gifts',
    'subtitle'    => 'The catalog listeners can send. Our own gifts are editable here; vendor gifts come from the vendor sync.',
    'actionUrl'   => site_url('admin/gifts/create'),
    'actionLabel' => '+ New local gift',
]) ?>

<?= view_cell('App\Cells\FilterBar::render', [
    'action' => 'admin/gifts',
    'fields' => [
        ['name' => 'q', 'label' => 'Search', 'type' => 'search', 'placeholder' => 'Name or description'],
        ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['ecard' => 'E-card', 'airtime' => 'Airtime', 'shoutout' => 'Shout-out', 'dedication' => 'Song dedication', 'physical' => 'Delivered gift']],
        ['name' => 'active', 'label' => 'Availability', 'type' => 'select', 'options' => ['1' => 'Active', '0' => 'Inactive']],
        ['name' => 'vendor', 'label' => 'Vendor', 'type' => 'select', 'options' => $vendors],
    ],
    'values' => $filters,
]) ?>

<h2 class="h6 mb-2">Our gifts</h2>
<table class="table table-sm table-hover bg-white align-middle mb-5">
    <thead>
        <tr><th>Name</th><th>Type</th><th>Price</th><th>Status</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
        <?php if (empty($localGifts)): ?>
            <tr><td colspan="5" class="text-muted"><?= $filtered ? 'No gifts of ours match these filters.' : 'No local gifts yet.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($localGifts as $gift): ?>
            <tr>
                <td><?= esc($gift['name']) ?></td>
                <td class="text-capitalize"><?= esc($gift['type']) ?></td>
                <td class="text-nowrap"><?= cbs_money((float) $gift['price'], $gift['currency']) ?></td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $gift['is_active']]) ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?= site_url('admin/gifts/' . $gift['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <?= view_cell('App\Cells\ActiveToggle::render', [
                        'baseUrl'      => 'admin/gifts',
                        'id'           => $gift['id'],
                        'isActive'     => $gift['is_active'],
                        'confirmTitle' => 'Deactivate this gift?',
                        'confirmText'  => 'It will no longer appear in the gift catalog.',
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2 class="h6 mb-1 d-flex align-items-center gap-2">
    Vendor-sourced gifts
    <a href="<?= site_url('admin/gift-vendors') ?>" class="small fw-normal">manage vendors &rarr;</a>
</h2>
<p class="text-muted small">Name, price, and description are set by the vendor's catalog and refresh on each sync — only availability can be changed here.</p>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr><th>Name</th><th>Vendor</th><th>Type</th><th>Price</th><th>Last synced</th><th>Status</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
        <?php if (empty($vendorGifts)): ?>
            <tr><td colspan="7" class="text-muted"><?= $filtered ? 'No vendor gifts match these filters.' : 'No vendor gifts synced yet.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($vendorGifts as $gift): ?>
            <tr>
                <td><?= esc($gift['name']) ?></td>
                <td><?= esc($gift['vendor_name']) ?></td>
                <td class="text-capitalize"><?= esc($gift['type']) ?></td>
                <td class="text-nowrap"><?= cbs_money((float) $gift['price'], $gift['currency']) ?></td>
                <td><?= $gift['synced_at'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => $gift['synced_at']]) : '&mdash;' ?></td>
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

<?= $this->endSection() ?>
