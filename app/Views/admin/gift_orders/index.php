<?= $this->extend('layouts/admin') ?>

<?= $this->section('head') ?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Gift Orders',
    'subtitle' => 'Paid orders are ready to fulfill. Vendor gifts are placed with the vendor when you fulfill them.',
]) ?>

<?= view_cell('App\Cells\FilterBar::render', [
    'action' => 'admin/gift-orders',
    'fields' => [
        ['name' => 'q', 'label' => 'Search', 'type' => 'search', 'placeholder' => 'Name, phone, gift or number'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['pending_payment' => 'Pending payment', 'paid' => 'Paid', 'fulfilled' => 'Fulfilled', 'cancelled' => 'Cancelled']],
        ['name' => 'source', 'label' => 'Gift from', 'type' => 'select', 'options' => ['local' => 'Our gifts', 'vendor' => 'Vendors']],
        ['name' => 'from', 'label' => 'From', 'type' => 'date'],
        ['name' => 'to', 'label' => 'To', 'type' => 'date'],
    ],
    'values' => $filters,
]) ?>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr>
            <th>Ref</th>
            <th>Gift</th>
            <th>Recipient</th>
            <th>Delivery</th>
            <th>Greeting</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Submitted</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($giftOrders as $order): ?>
            <?php $id = (int) $order['id']; $isVendor = $order['gift_source'] === 'vendor'; ?>
            <tr>
                <td class="text-nowrap"><?= esc(cbs_gift_reference($id)) ?></td>
                <td class="small">
                    <?= esc($order['gift_name']) ?> &times; <?= esc($order['quantity']) ?>
                    <?php if ($isVendor): ?><div class="text-muted">via vendor</div><?php endif; ?>
                </td>
                <td class="small">
                    <?= esc($order['recipient_name']) ?>
                    <?php if (! empty($order['recipient_phone'])): ?>
                        <div class="text-muted"><?= esc($order['recipient_phone']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="small" style="max-width: 220px;">
                    <?php if (! empty($order['delivery_details'])): ?>
                        <details>
                            <summary class="text-muted"><?= esc(cbs_excerpt($order['delivery_details'], 40)) ?></summary>
                            <div style="white-space: pre-wrap;"><?= esc($order['delivery_details']) ?></div>
                        </details>
                    <?php else: ?>
                        <span class="text-muted">&mdash;</span>
                    <?php endif; ?>
                </td>
                <td class="small text-nowrap">
                    <?php if (! empty($order['booking_id'])): ?>
                        <a href="<?= site_url('admin/bookings/' . $order['booking_id']) ?>" class="text-decoration-none">
                            <?= esc(cbs_booking_reference((int) $order['booking_id'])) ?>
                        </a>
                    <?php else: ?>
                        <span class="text-muted">Standalone</span>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap"><?= cbs_money((float) $order['amount'], $order['currency']) ?></td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'gift-order', 'status' => $order['status']]) ?></td>
                <td class="small text-nowrap" data-order="<?= strtotime($order['created_at']) ?>">
                    <?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $order['created_at']]) ?>
                </td>
                <td class="text-end text-nowrap">
                    <?php if ($order['status'] === 'paid'): ?>
                        <?php if ($isVendor): ?>
                            <form id="fulfill-form-<?= $id ?>" method="post" class="d-inline"
                                  action="<?= site_url('admin/gift-orders/' . $id . '/fulfill') ?>">
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
                        <form id="cancel-form-<?= $id ?>" method="post" class="d-inline"
                              action="<?= site_url('admin/gift-orders/' . $id . '/cancel') ?>">
                            <?= csrf_field() ?>
                        </form>
                        <?= view_cell('App\Cells\ConfirmButton::render', [
                            'formId'            => 'cancel-form-' . $id,
                            'title'             => 'Cancel this gift order?',
                            'label'             => 'Cancel',
                            'buttonClass'       => 'btn btn-sm btn-outline-danger',
                            'confirmButtonText' => 'Yes, cancel',
                        ]) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <span class="text-muted small"><?= (int) $total ?> in total</span>
    <?= view_cell('App\Cells\Pagination::render', ['page' => $page, 'totalPages' => $totalPages, 'baseUrl' => 'admin/gift-orders', 'query' => $filters]) ?>
</div>

<?= $this->endSection() ?>
