<?php
/**
 * Admin > Shop purchases (v1 theme).
 *
 * @var list<array<string, mixed>> $purchases
 * @var array<int, string>         $vendors    shop id => name
 * @var array<string, string>      $filters
 * @var int                        $total
 * @var int                        $page
 * @var int                        $totalPages
 */
?>
<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Shop purchases<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Shop purchases',
    'subtitle' => 'Gifts visitors bought through a partner shop. They pay the shop, not us; "paid" is what the shop reports.',
]) ?>

<div class="card custom-card">
    <div class="card-header justify-content-between">
        <div class="card-title">Purchases <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= (int) $total ?></span></div>
    </div>

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

    <?php if (empty($purchases)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-store-2-line" aria-hidden="true"></i></span>
            <h6>No purchases match.</h6>
            <p class="mb-0 fs-13">Try fewer filters. Purchases appear when a visitor checks out their gift cart.</p>
        </div>
    <?php else: ?>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0 table-cbs">
                    <thead>
                        <tr><th>Reference</th><th>Shop</th><th>Gifts</th><th class="text-end">Amount</th><th>Status</th><th>Started</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $purchase): ?>
                            <?php
                            $lines  = \App\Models\VendorCheckoutModel::lines($purchase);
                            $amount = $purchase['final_amount'] !== null ? (float) $purchase['final_amount'] : (float) $purchase['estimate_total'];
                            ?>
                            <tr>
                                <td class="cbs-reference"><?= esc($purchase['purchase_reference']) ?></td>
                                <td><?= esc($purchase['vendor_name']) ?></td>
                                <td class="text-wrap fs-13" style="max-width: 18rem;"><?= esc(implode(', ', array_map(static fn (array $l): string => ($l['title'] ?? 'Gift') . ' × ' . ($l['quantity'] ?? 1), $lines))) ?></td>
                                <td class="text-end fw-semibold">
                                    <?= cbs_money($amount, $purchase['currency']) ?>
                                    <?php if ($purchase['final_amount'] === null): ?><span class="cell-sub">estimate</span><?php endif; ?>
                                </td>
                                <td><?= cbs_checkout_state_badge($purchase['state']) ?></td>
                                <td class="fs-13"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $purchase['created_at']]) ?></td>
                                <td class="text-end"><a href="<?= site_url('admin/gift-purchases/' . (int) $purchase['id']) ?>" class="btn btn-sm btn-primary-light">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <?= view_cell('App\Cells\Pagination::render', ['page' => $page, 'totalPages' => $totalPages, 'baseUrl' => 'admin/gift-purchases', 'query' => $filters]) ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
