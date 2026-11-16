<?= $this->extend(ui_layout('admin')) ?>

<?php
$pageTitle = $gift ? 'Edit gift' : 'New local gift';
$types     = [
    'ecard'      => 'E-card',
    'airtime'    => 'Airtime top-up',
    'shoutout'   => 'Shout-out',
    'dedication' => 'Song dedication',
    'physical'   => 'Physical (our own fulfillment)',
];
?>

<?= $this->section('title') ?><?= esc($pageTitle) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $pageTitle,
    'backUrl'   => site_url('admin/gifts'),
    'backLabel' => 'Gifts',
]) ?>

<div class="row">
    <div class="col-xl-7 col-lg-9">
        <?= view_cell('App\Cells\FormErrors::render') ?>

        <form method="post" action="<?= $gift ? site_url('admin/gifts/' . $gift['id'] . '/update') : site_url('admin/gifts') ?>" class="card custom-card">
            <?= csrf_field() ?>

            <div class="card-header"><div class="card-title">Gift details</div></div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <label for="gift-name" class="form-label">Name</label>
                    <input type="text" id="gift-name" name="name" class="form-control" required maxlength="150"
                           value="<?= esc(old('name', null, false) ?? $gift['name'] ?? '', 'attr') ?>">
                </div>

                <div class="mb-3">
                    <label for="gift-type" class="form-label">Type</label>
                    <select id="gift-type" name="type" class="form-select" required>
                        <?php foreach ($types as $value => $label): ?>
                            <option value="<?= $value ?>" <?= (old('type') ?? $gift['type'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="gift-description" class="form-label">Description</label>
                    <textarea id="gift-description" name="description" class="form-control" rows="2"><?= esc(old('description', null, false) ?? $gift['description'] ?? '') ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-8">
                        <label for="gift-price" class="form-label">Price</label>
                        <input type="number" step="0.01" min="0" id="gift-price" name="price" class="form-control" required
                               value="<?= esc(old('price', null, false) ?? $gift['price'] ?? '', 'attr') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label for="gift-currency" class="form-label">Currency</label>
                        <input type="text" id="gift-currency" name="currency" class="form-control text-uppercase" maxlength="3"
                               value="<?= esc(old('currency', null, false) ?? $gift['currency'] ?? $config->defaultCurrency, 'attr') ?>">
                    </div>
                </div>

                <div class="form-check form-switch">
                    <input type="checkbox" role="switch" name="is_active" value="1" class="form-check-input" id="isActive"
                           <?= (old('is_active') ?? $gift['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isActive">Active (shown in the gift catalog)</label>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i><?= $gift ? 'Save changes' : 'Create gift' ?></button>
                <a href="<?= site_url('admin/gifts') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
