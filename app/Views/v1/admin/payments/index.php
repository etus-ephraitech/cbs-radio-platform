<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Payments<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.11/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Payments',
    'subtitle' => 'Mobile Money payments for bookings and gift orders, with a manual mark-as-paid fallback.',
]) ?>

<?php
$tabs = [
    ''        => 'All',
    'pending' => 'Pending',
    'success' => 'Success',
    'failed'  => 'Failed',
];
?>

<div class="card custom-card">
    <div class="card-header justify-content-between">
        <div class="card-title">Payments</div>
        <?= view(ui_view_path('admin/_partials/status_tabs'), ['tabs' => $tabs, 'current' => $status ?? '', 'baseUrl' => 'admin/payments', 'label' => 'Filter payments by status'], ['saveData' => false, 'debug' => false]) ?>
    </div>

    <?php if (empty($payments)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-wallet-3-line" aria-hidden="true"></i></span>
            <h6>No payments here.</h6>
            <p class="mb-0 fs-13">Payments appear as listeners pay for greetings and gifts.</p>
        </div>
    <?php else: ?>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0 table-cbs" id="paymentsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>For</th>
                            <th>Provider</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th>Paid</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td data-order="<?= (int) $payment['id'] ?>" class="text-muted">#<?= esc($payment['id']) ?></td>
                                <td>
                                    <?php if (! empty($payment['booking_id'])): ?>
                                        <a href="<?= site_url('admin/bookings/' . $payment['booking_id']) ?>" class="cbs-reference text-primary"><?= esc(cbs_booking_reference((int) $payment['booking_id'])) ?></a>
                                    <?php elseif (! empty($payment['gift_order_id'])): ?>
                                        <a href="<?= site_url('admin/gift-orders') ?>" class="cbs-reference text-primary"><?= esc(cbs_gift_reference((int) $payment['gift_order_id'])) ?></a>
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($payment['provider']) ?></td>
                                <td class="text-end fw-semibold" data-order="<?= (float) $payment['amount'] ?>"><?= cbs_money((float) $payment['amount'], $payment['currency']) ?></td>
                                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'payment', 'status' => $payment['status']]) ?></td>
                                <td class="fs-13" data-order="<?= $payment['paid_at'] ? strtotime($payment['paid_at']) : 0 ?>">
                                    <?= $payment['paid_at'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => $payment['paid_at']]) : '&mdash;' ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($payment['status'] !== 'success'): ?>
                                        <form id="mark-paid-form-<?= (int) $payment['id'] ?>" method="post" action="<?= site_url('admin/payments/' . $payment['id'] . '/mark-manual') ?>">
                                            <?= csrf_field() ?>
                                        </form>
                                        <?= view_cell('App\Cells\ConfirmButton::render', [
                                            'formId'            => 'mark-paid-form-' . (int) $payment['id'],
                                            'title'             => 'Mark this payment as paid?',
                                            'text'              => 'Use this only once you have confirmed payment outside the system.',
                                            'label'             => 'Mark paid',
                                            'buttonClass'       => 'btn btn-sm btn-success-light',
                                            'confirmButtonText' => 'Yes, mark paid',
                                        ]) ?>
                                    <?php endif; ?>
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
if (window.jQuery && jQuery.fn.DataTable && document.getElementById('paymentsTable')) {
    jQuery('#paymentsTable').DataTable({ order: [[0, 'desc']], columnDefs: [{ orderable: false, targets: -1 }] });
}
</script>
<?= $this->endSection() ?>
