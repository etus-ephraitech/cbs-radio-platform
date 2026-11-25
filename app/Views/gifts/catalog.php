<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="page-header">
    <h1 class="h3 mb-1">Send a Gift</h1>
    <p class="text-muted mb-0">A digital e-card, airtime top-up, shout-out, song dedication, or a delivered gift from our partners.</p>
</div>

<?php if (session('errors')): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach (session('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php $hostedGifts = $hostedGifts ?? []; $cartCount = $cartCount ?? 0; ?>

<?php if (empty($gifts) && empty($hostedGifts)): ?>
    <div class="alert alert-secondary">No gifts are available right now.</div>
<?php endif; ?>

<?php if (! empty($gifts)): ?>

<form action="<?= site_url('gifts') ?>" method="post" id="giftForm">
    <?= csrf_field() ?>
    <?php if (! empty($bookingId)): ?>
        <input type="hidden" name="booking_id" value="<?= esc($bookingId) ?>">
        <div class="alert alert-info small">This gift will be sent along with your greeting (ref <?= esc(cbs_booking_reference((int) $bookingId)) ?>).</div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <?php foreach ($gifts as $gift): ?>
            <div class="col-md-6 col-lg-4">
                <label class="d-block border rounded p-3 h-100" style="cursor:pointer" data-gift-type="<?= esc($gift['type']) ?>">
                    <input type="radio" name="gift_id" value="<?= esc($gift['id']) ?>" required class="form-check-input me-2 gift-radio">
                    <strong><?= esc($gift['name']) ?></strong>
                    <span class="float-end fw-bold"><?= cbs_money((float) $gift['price'], $gift['currency']) ?></span>
                    <div class="text-muted small mt-1 text-capitalize">
                        <?= esc($gift['type']) ?>
                        <?php if (! empty($gift['vendor_name'])): ?>
                            &middot; via <?= esc($gift['vendor_name']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small"><?= esc($gift['description'] ?? '') ?></div>
                </label>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Your name</label>
                    <input type="text" name="sender_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Your phone</label>
                    <input type="tel" name="sender_phone" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Recipient's name</label>
                    <input type="text" name="recipient_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Recipient's phone (for airtime/shout-outs)</label>
                    <input type="tel" name="recipient_phone" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="1">
                </div>
                <div class="col-md-8" id="deliveryDetailsWrap">
                    <label class="form-label" id="deliveryDetailsLabel">Delivery details</label>
                    <textarea name="delivery_details" class="form-control" rows="2" id="deliveryDetailsInput"
                              placeholder="For a physical gift: the delivery address. For airtime/e-vouchers: usually not needed."></textarea>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-cbs-primary btn-lg">Continue to payment</button>
</form>

<?php endif; ?>

<?php if (! empty($hostedGifts)): ?>
<section class="<?= empty($gifts) ? '' : 'mt-5 ' ?>mb-4" id="shopGifts" aria-labelledby="shopGiftsHeading">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1" id="shopGiftsHeading">Gifts delivered to their door</h2>
            <p class="text-muted small mb-0">
                From our partner shop. Choose as many as you like; you give the delivery address, pick the arrival
                dates and pay on the shop's secure page.
            </p>
        </div>
        <?php if ($cartCount > 0): ?>
            <a href="<?= site_url('gifts/cart') ?>" class="btn btn-cbs-primary">View cart (<?= esc($cartCount) ?>)</a>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <?php foreach ($hostedGifts as $gift): ?>
            <?php
            $stock      = $gift['stock_available'];
            $outOfStock = $stock !== null && (int) $stock === 0;
            ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <?php if (! empty($gift['image_url'])): ?>
                        <img src="<?= esc($gift['image_url'], 'attr') ?>" class="card-img-top" alt="<?= esc($gift['name'], 'attr') ?>"
                             loading="lazy" style="height: 180px; object-fit: cover;">
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h3 class="h6 mb-1"><?= esc($gift['name']) ?></h3>
                        <div class="fw-bold mb-1"><?= cbs_money((float) $gift['price'], $gift['currency']) ?></div>
                        <?php if (! empty($gift['description'])): ?>
                            <p class="text-muted small mb-2"><?= esc(cbs_excerpt($gift['description'], 110)) ?></p>
                        <?php endif; ?>
                        <?php if ($gift['minimum_delivery_hours'] !== null): ?>
                            <div class="text-muted small mb-2">Needs at least <?= esc($gift['minimum_delivery_hours']) ?> hours' notice.</div>
                        <?php endif; ?>

                        <div class="mt-auto">
                            <?php if ($outOfStock): ?>
                                <span class="badge bg-secondary">Out of stock</span>
                            <?php else: ?>
                                <form action="<?= site_url('gifts/cart/add') ?>" method="post" class="d-flex gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="gift_id" value="<?= esc($gift['id'], 'attr') ?>">
                                    <label class="visually-hidden" for="qty<?= esc($gift['id'], 'attr') ?>">Quantity of <?= esc($gift['name']) ?></label>
                                    <input type="number" name="quantity" id="qty<?= esc($gift['id'], 'attr') ?>" class="form-control form-control-sm"
                                           style="max-width: 5rem;" min="1" max="<?= esc($stock !== null ? min((int) $stock, 100) : 100, 'attr') ?>" value="1">
                                    <button type="submit" class="btn btn-sm btn-cbs-primary flex-grow-1">Add to cart</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Nudge the delivery-details label depending on the selected gift's type,
// since a physical gift needs an address and a digital one usually doesn't.
document.querySelectorAll('.gift-radio').forEach(radio => {
    radio.addEventListener('change', (e) => {
        const type = e.target.closest('[data-gift-type]').dataset.giftType;
        const label = document.getElementById('deliveryDetailsLabel');
        const input = document.getElementById('deliveryDetailsInput');

        if (type === 'physical') {
            label.textContent = 'Delivery address';
            input.placeholder = 'Street address, area, and any landmark for delivery.';
        } else {
            label.textContent = 'Delivery details (optional)';
            input.placeholder = 'Only needed if this gift requires extra delivery info.';
        }
    });
});
</script>
<?= $this->endSection() ?>
