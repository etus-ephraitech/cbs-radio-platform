<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="page-header">
    <h1 class="h3 mb-1">Your gift cart</h1>
    <p class="text-muted mb-0">Gifts delivered by our partner shop. You give the delivery address and arrival dates, and pay, on the shop's secure page.</p>
</div>

<?php if (empty($rows)): ?>
    <div class="alert alert-secondary">Your cart is empty.</div>
    <a href="<?= site_url('gifts') ?>" class="btn btn-cbs-primary">Choose a gift</a>
<?php else: ?>

<form action="<?= site_url('gifts/cart/update') ?>" method="post" id="cartForm">
    <?= csrf_field() ?>
    <div class="table-responsive">
        <table class="table align-middle bg-white">
            <thead>
                <tr><th>Gift</th><th class="text-end">Price</th><th style="width: 8rem;">Quantity</th><th class="text-end">Subtotal</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $gift = $row['gift']; $id = (int) $gift['id']; ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (! empty($gift['image_url'])): ?>
                                    <img src="<?= esc($gift['image_url'], 'attr') ?>" alt="" loading="lazy"
                                         style="width: 56px; height: 56px; object-fit: cover; border-radius: .35rem;">
                                <?php endif; ?>
                                <div>
                                    <strong><?= esc($gift['name']) ?></strong>
                                    <?php if ($gift['minimum_delivery_hours'] !== null): ?>
                                        <div class="text-muted small">At least <?= esc($gift['minimum_delivery_hours']) ?> hours' notice</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-end text-nowrap"><?= cbs_money((float) $gift['price'], $gift['currency']) ?></td>
                        <td>
                            <label class="visually-hidden" for="q<?= $id ?>">Quantity of <?= esc($gift['name']) ?></label>
                            <input type="number" id="q<?= $id ?>" name="quantities[<?= $id ?>]" class="form-control form-control-sm"
                                   min="0" max="<?= esc($gift['stock_available'] !== null ? min((int) $gift['stock_available'], $maxQty) : $maxQty, 'attr') ?>"
                                   value="<?= esc($row['quantity'], 'attr') ?>">
                        </td>
                        <td class="text-end text-nowrap"><?= cbs_money((float) $row['subtotal'], $gift['currency']) ?></td>
                        <td class="text-end">
                            <button type="submit" form="remove<?= $id ?>" class="btn btn-sm btn-outline-secondary">Remove</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Estimated total</th>
                    <th class="text-end text-nowrap"><?= cbs_money((float) $total, $currency) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="text-muted small">
        This total is an estimate. The shop works out the final price, including any delivery fee, when you choose your
        delivery on its page. Quantities are checked with the shop again when you continue.
    </p>
    <button type="submit" class="btn btn-outline-secondary">Update quantities</button>
</form>

<?php foreach ($rows as $row): ?>
    <form id="remove<?= (int) $row['gift']['id'] ?>" action="<?= site_url('gifts/cart/remove/' . (int) $row['gift']['id']) ?>" method="post">
        <?= csrf_field() ?>
    </form>
<?php endforeach; ?>

<div class="d-flex flex-wrap gap-2 mt-4">
    <a href="<?= site_url('gifts') ?>" class="btn btn-outline-secondary">Keep browsing</a>
    <form action="<?= site_url('gifts/checkout') ?>" method="post">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-cbs-primary btn-lg">Continue to delivery and payment</button>
    </form>
</div>

<?php endif; ?>

<?= $this->endSection() ?>
