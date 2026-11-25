<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => 'Purchase ' . $purchase['purchase_reference'],
    'backUrl'   => site_url('admin/gift-purchases'),
    'backLabel' => 'Shop Purchases',
]) ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="h6 mb-0">Payment</h2>
                    <?= cbs_checkout_state_badge($purchase['state']) ?>
                </div>
                <dl class="row small mb-3">
                    <dt class="col-sm-5 text-muted">Shop</dt><dd class="col-sm-7"><?= esc($purchase['vendor_name']) ?></dd>
                    <dt class="col-sm-5 text-muted">Paid (per the shop)</dt><dd class="col-sm-7"><?= (int) $purchase['paid'] === 1 ? 'Yes' : 'No' ?></dd>
                    <dt class="col-sm-5 text-muted">Estimated total</dt><dd class="col-sm-7"><?= cbs_money((float) $purchase['estimate_total'], $purchase['currency']) ?></dd>
                    <dt class="col-sm-5 text-muted">Final total</dt>
                    <dd class="col-sm-7"><?= $purchase['final_amount'] !== null ? cbs_money_decimal((float) $purchase['final_amount'], $purchase['currency']) : '—' ?></dd>
                    <dt class="col-sm-5 text-muted">Shop's order</dt><dd class="col-sm-7"><?= esc($purchase['vendor_order_id'] ?: '—') ?></dd>
                    <dt class="col-sm-5 text-muted">Shop's reference</dt><dd class="col-sm-7"><?= esc($purchase['vendor_reference'] ?: '—') ?></dd>
                    <dt class="col-sm-5 text-muted">Session</dt><dd class="col-sm-7"><code class="small"><?= esc($purchase['session_id'] ?: '—') ?></code></dd>
                    <dt class="col-sm-5 text-muted">Link expires</dt><dd class="col-sm-7"><?= esc($purchase['expires_at'] ?: '—') ?></dd>
                    <dt class="col-sm-5 text-muted">Last checked</dt>
                    <dd class="col-sm-7"><?= $purchase['last_checked_at'] ? view_cell('App\Cells\TimeAgo::render', ['datetime' => $purchase['last_checked_at']]) : 'never' ?></dd>
                </dl>

                <?php if (! empty($purchase['last_error'])): ?>
                    <div class="alert alert-warning small"><?= esc($purchase['last_error']) ?></div>
                <?php endif; ?>

                <?php if (! empty($purchase['session_id'])): ?>
                    <form method="post" action="<?= site_url('admin/gift-purchases/' . (int) $purchase['id'] . '/refresh') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Check with the shop now</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6">Gifts</h2>
                <table class="table table-sm small mb-0">
                    <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td><?= esc($line['title'] ?? 'Gift') ?> &times; <?= esc($line['quantity'] ?? 1) ?></td>
                                <td class="text-end text-nowrap"><?= cbs_money((float) ($line['line_total'] ?? 0), $purchase['currency']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="h6">Delivery</h2>
                <?php if (empty($deliveries)): ?>
                    <p class="text-muted small mb-0">The shop has not reported any delivery yet.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($deliveries as $delivery): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between gap-2">
                                    <span><?= esc($delivery['title'] ?? 'Gift') ?> &times; <?= esc($delivery['quantity'] ?? 1) ?></span>
                                    <strong><?= esc(cbs_delivery_state_label((string) ($delivery['state'] ?? 'pending'))) ?></strong>
                                </div>
                                <?php if (! empty($delivery['requested_delivery_at'])): ?>
                                    <div class="text-muted">Requested for <?= esc($delivery['requested_delivery_at']) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
