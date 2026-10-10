<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="mx-auto" style="max-width: 560px;">
    <div class="card shadow-sm">
        <div class="card-body py-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h1 class="h4 mb-0">Gift order status</h1>
                <?= view_cell('App\Cells\StatusBadge::render', ['type' => 'gift-order', 'status' => $giftOrder['status']]) ?>
            </div>

            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted">Reference</dt>
                <dd class="col-sm-8"><?= esc(cbs_gift_reference((int) $giftOrder['id'])) ?></dd>

                <dt class="col-sm-4 text-muted">Recipient</dt>
                <dd class="col-sm-8"><?= esc($giftOrder['recipient_name']) ?></dd>

                <dt class="col-sm-4 text-muted">Quantity</dt>
                <dd class="col-sm-8"><?= esc($giftOrder['quantity']) ?></dd>

                <dt class="col-sm-4 text-muted">Amount</dt>
                <dd class="col-sm-8"><?= cbs_money((float) $giftOrder['amount'], $giftOrder['currency']) ?></dd>

                <dt class="col-sm-4 text-muted">Submitted</dt>
                <dd class="col-sm-8"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $giftOrder['created_at']]) ?></dd>
            </dl>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
