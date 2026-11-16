<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Gift orders<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Gift orders',
    'subtitle' => 'Paid orders are ready to fulfill. Vendor gifts are placed with the vendor when you fulfill them.',
]) ?>

<?php
$tabs = [
    ''                => 'All',
    'pending_payment' => 'Pending payment',
    'paid'            => 'Paid',
    'fulfilled'       => 'Fulfilled',
    'cancelled'       => 'Cancelled',
];
?>

<div class="card custom-card">
    <div class="card-header justify-content-between">
        <div class="card-title">Gift orders</div>
        <?= view(ui_view_path('admin/_partials/status_tabs'), ['tabs' => $tabs, 'current' => $status ?? '', 'baseUrl' => 'admin/gift-orders', 'label' => 'Filter gift orders by status'], ['saveData' => false, 'debug' => false]) ?>
    </div>

    <?php if (empty($giftOrders)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-gift-line" aria-hidden="true"></i></span>
            <h6>No gift orders here.</h6>
            <p class="mb-0 fs-13">Orders appear when listeners send a gift, with or without a greeting.</p>
        </div>
    <?php else: ?>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0 table-cbs" id="giftOrdersTable">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Gift</th>
                            <th>Recipient</th>
                            <th>Delivery</th>
                            <th>Greeting</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($giftOrders as $order): ?>
                            <?php $id = (int) $order['id']; $isVendor = $order['gift_source'] === 'vendor'; ?>
                            <tr>
                                <td class="cbs-reference"><?= esc(cbs_gift_reference($id)) ?></td>
                                <td>
                                    <?= esc($order['gift_name']) ?> &times; <?= esc($order['quantity']) ?>
                                    <?php if ($isVendor): ?><span class="cell-sub"><i class="ri-store-2-line me-1" aria-hidden="true"></i>via vendor</span><?php endif; ?>
                                </td>
                                <td>
                                    <?= esc($order['recipient_name']) ?>
                                    <?php if (! empty($order['recipient_phone'])): ?>
                                        <span class="cell-sub"><?= esc($order['recipient_phone']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-wrap" style="min-width: 12rem; max-width: 16rem;">
                                    <?php if (! empty($order['delivery_details'])): ?>
                                        <details>
                                            <summary class="text-muted fs-13"><?= esc(cbs_excerpt($order['delivery_details'], 40)) ?></summary>
                                            <div class="fs-13 mt-1" style="white-space: pre-wrap;"><?= esc($order['delivery_details']) ?></div>
                                        </details>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (! empty($order['booking_id'])): ?>
                                        <a href="<?= site_url('admin/bookings/' . $order['booking_id']) ?>" class="cbs-reference text-primary"><?= esc(cbs_booking_reference((int) $order['booking_id'])) ?></a>
                                        <?php if ((int) ($order['bundled_with_booking'] ?? 0) === 1): ?>
                                            <span class="cell-sub" title="Chosen in the greeting form and paid in the greeting's payment">Paid with the greeting</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Standalone</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-semibold" data-order="<?= (float) $order['amount'] ?>"><?= cbs_money((float) $order['amount'], $order['currency']) ?></td>
                                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'gift-order', 'status' => $order['status']]) ?></td>
                                <td class="fs-13" data-order="<?= strtotime($order['created_at']) ?>"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $order['created_at']]) ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($order['status'] === 'paid'): ?>
                                            <?php if ($isVendor): ?>
                                                <form id="fulfill-form-<?= $id ?>" method="post" class="d-inline" action="<?= site_url('admin/gift-orders/' . $id . '/fulfill') ?>">
                                                    <?= csrf_field() ?>
                                                </form>
                                                <?= view_cell('App\Cells\ConfirmButton::render', [
                                                    'formId'            => 'fulfill-form-' . $id,
                                                    'title'             => 'Place this order with the vendor?',
                                                    'text'              => 'The order is sent to the vendor now.',
                                                    'label'             => 'Fulfill via vendor',
                                                    'buttonClass'       => 'btn btn-sm btn-success',
                                                    'confirmButtonText' => 'Yes, place order',
                                                ]) ?>
                                            <?php else: ?>
                                                <form method="post" class="d-inline" action="<?= site_url('admin/gift-orders/' . $id . '/fulfill') ?>">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-success">Mark fulfilled</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (! in_array($order['status'], ['fulfilled', 'cancelled'], true)): ?>
                                            <form id="cancel-form-<?= $id ?>" method="post" class="d-inline" action="<?= site_url('admin/gift-orders/' . $id . '/cancel') ?>">
                                                <?= csrf_field() ?>
                                            </form>
                                            <?= view_cell('App\Cells\ConfirmButton::render', [
                                                'formId'            => 'cancel-form-' . $id,
                                                'title'             => 'Cancel this gift order?',
                                                'label'             => 'Cancel',
                                                'buttonClass'       => 'btn btn-sm btn-danger-light',
                                                'confirmButtonText' => 'Yes, cancel',
                                            ]) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script>
// DataTables is progressive: without it (offline, CDN blocked) the table above is still complete and usable.
if (window.jQuery && jQuery.fn.DataTable && document.getElementById('giftOrdersTable')) {
    jQuery('#giftOrdersTable').DataTable({ order: [[7, 'desc']], columnDefs: [{ orderable: false, targets: -1 }] });
}
</script>
<?= $this->endSection() ?>
