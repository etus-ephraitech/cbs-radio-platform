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

<?php if (empty($gifts)): ?>
    <div class="alert alert-secondary">No gifts are available right now.</div>
<?php else: ?>

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
