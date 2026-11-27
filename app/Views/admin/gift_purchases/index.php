<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Shop Purchases',
    'subtitle' => 'Gifts visitors bought through a partner shop. They pay the shop, not us; "paid" is what the shop reports.',
]) ?>

<?= view_cell('App\Cells\FilterBar::render', [
    'action' => 'admin/gift-purchases',
    'fields' => [
        ['name' => 'q', 'label' => 'Search', 'type' => 'search', 'placeholder' => 'Reference or shop'],
        ['name' => 'state', 'label' => 'Status', 'type' => 'select', 'options' => ['awaiting_payment' => 'Awaiting payment', 'in_checkout' => 'In checkout', 'open' => 'Open', 'paid' => 'Paid', 'cancelled' => 'Cancelled', 'expired' => 'Expired', 'failed' => 'Failed']],
        ['name' => 'vendor', 'label' => 'Shop', 'type' => 'select', 'options' => $vendors],
        ['name' => 'from', 'label' => 'From', 'type' => 'date'],
        ['name' => 'to', 'label' => 'To', 'type' => 'date'],
    ],
    'values' => $filters,
]) ?>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr><th>Reference</th><th>Shop</th><th>Gifts</th><th class="text-end">Amount</th><th>Status</th><th>Started</th><th></th></tr>
    </thead>
    <tbody>
        <?php if (empty($purchases)): ?>
            <tr><td colspan="7" class="text-muted">No purchases match.</td></tr>
        <?php endif; ?>
        <?php foreach ($purchases as $purchase): ?>
            <?php
            $lines  = \App\Models\VendorCheckoutModel::lines($purchase);
            $amount = $purchase['final_amount'] !== null ? (float) $purchase['final_amount'] : (float) $purchase['estimate_total'];
            ?>
            <tr>
                <td class="text-nowrap"><?= esc($purchase['purchase_reference']) ?></td>
                <td class="small"><?= esc($purchase['vendor_name']) ?></td>
                <td class="small"><?= esc(implode(', ', array_map(static fn (array $l): string => ($l['title'] ?? 'Gift') . ' × ' . ($l['quantity'] ?? 1), $lines))) ?></td>
                <td class="text-end text-nowrap">
                    <?= cbs_money($amount, $purchase['currency']) ?>
                    <?php if ($purchase['final_amount'] === null): ?><div class="text-muted small">estimate</div><?php endif; ?>
                </td>
                <td><?= cbs_checkout_state_badge($purchase['state']) ?></td>
                <td class="small"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $purchase['created_at']]) ?></td>
                <td class="text-end"><a href="<?= site_url('admin/gift-purchases/' . (int) $purchase['id']) ?>" class="btn btn-sm btn-outline-secondary">Open</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <span class="text-muted small"><?= (int) $total ?> in total</span>
    <?= view_cell('App\Cells\Pagination::render', ['page' => $page, 'totalPages' => $totalPages, 'baseUrl' => 'admin/gift-purchases', 'query' => $filters]) ?>
</div>

<?= $this->endSection() ?>
