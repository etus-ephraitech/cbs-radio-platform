<?= $this->extend('layouts/admin') ?>

<?= $this->section('head') ?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Payments',
    'subtitle' => 'Mobile Money payments for bookings and gift orders, with a manual mark-as-paid fallback.',
]) ?>

<div class="btn-group btn-group-sm mb-3">
    <a href="<?= site_url('admin/payments') ?>" class="btn btn-outline-secondary <?= $status === null ? 'active' : '' ?>">All</a>
    <a href="<?= site_url('admin/payments?status=pending') ?>" class="btn btn-outline-secondary <?= $status === 'pending' ? 'active' : '' ?>">Pending</a>
    <a href="<?= site_url('admin/payments?status=success') ?>" class="btn btn-outline-secondary <?= $status === 'success' ? 'active' : '' ?>">Success</a>
    <a href="<?= site_url('admin/payments?status=failed') ?>" class="btn btn-outline-secondary <?= $status === 'failed' ? 'active' : '' ?>">Failed</a>
</div>

<table class="table table-sm table-hover bg-white align-middle" id="paymentsTable">
    <thead>
        <tr>
            <th>ID</th>
            <th>For</th>
            <th>Provider</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Paid</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payments as $payment): ?>
            <tr>
                <td>#<?= esc($payment['id']) ?></td>
                <td>
                    <?php if (! empty($payment['booking_id'])): ?>
                        <a href="<?= site_url('admin/bookings/' . $payment['booking_id']) ?>" class="text-decoration-none">
                            <?= esc(cbs_booking_reference((int) $payment['booking_id'])) ?>
                        </a>
                    <?php elseif (! empty($payment['gift_order_id'])): ?>
                        <a href="<?= site_url('admin/gift-orders') ?>" class="text-decoration-none">
                            <?= esc(cbs_gift_reference((int) $payment['gift_order_id'])) ?>
                        </a>
                    <?php else: ?>
                        &mdash;
                    <?php endif; ?>
                </td>
                <td><?= esc($payment['provider']) ?></td>
                <td><?= cbs_money((float) $payment['amount'], $payment['currency']) ?></td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'payment', 'status' => $payment['status']]) ?></td>
                <td data-order="<?= $payment['paid_at'] ? strtotime($payment['paid_at']) : 0 ?>">
                    <?= $payment['paid_at'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => $payment['paid_at']]) : '&mdash;' ?>
                </td>
                <td class="text-end">
                    <?php if ($payment['status'] !== 'success'): ?>
                        <form id="mark-paid-form-<?= (int) $payment['id'] ?>" method="post"
                              action="<?= site_url('admin/payments/' . $payment['id'] . '/mark-manual') ?>">
                            <?= csrf_field() ?>
                        </form>
                        <?= view_cell('App\Cells\ConfirmButton::render', [
                            'formId'            => 'mark-paid-form-' . (int) $payment['id'],
                            'title'             => 'Mark this payment as paid?',
                            'text'              => 'Use this only once you have confirmed payment outside the system.',
                            'label'             => 'Mark paid',
                            'buttonClass'       => 'btn btn-sm btn-outline-success',
                            'confirmButtonText' => 'Yes, mark paid',
                        ]) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function () {
    $('#paymentsTable').DataTable({ order: [[0, 'desc']], columnDefs: [{ orderable: false, targets: -1 }] });
});
</script>
<?= $this->endSection() ?>
