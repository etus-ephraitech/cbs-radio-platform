<?= $this->extend(ui_layout('admin')) ?>

<?php $pageTitle = $rateTier ? 'Edit package' : 'New package'; ?>

<?= $this->section('title') ?><?= esc($pageTitle) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $pageTitle,
    'backUrl'   => site_url('admin/rate-tiers'),
    'backLabel' => 'Packages',
]) ?>

<div class="row">
    <div class="col-xl-7 col-lg-9">
        <?= view_cell('App\Cells\FormErrors::render') ?>

        <?php if (empty($categories)): ?>
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="ri-error-warning-line fs-5" aria-hidden="true"></i>
                <div>No active categories yet — <a href="<?= site_url('admin/categories/create') ?>" class="alert-link fw-semibold">create one first</a>.</div>
            </div>
        <?php else: ?>
            <form method="post" action="<?= $rateTier ? site_url('admin/rate-tiers/' . $rateTier['id'] . '/update') : site_url('admin/rate-tiers') ?>" class="card custom-card">
                <?= csrf_field() ?>

                <div class="card-header"><div class="card-title">Package details</div></div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label for="tier-category" class="form-label">Category</label>
                        <select id="tier-category" name="category_id" class="form-select" required>
                            <option value="">Choose a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= esc($category['id'], 'attr') ?>" <?= (string) (old('category_id') ?? $rateTier['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= esc($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="tier-level" class="form-label">Package level</label>
                        <input type="text" id="tier-level" name="package_level" class="form-control" required maxlength="100"
                               value="<?= esc(old('package_level', null, false) ?? $rateTier['package_level'] ?? '', 'attr') ?>"
                               placeholder="e.g. Basic Text, Voice Note + Text, Prime Slot">
                    </div>

                    <div class="mb-3">
                        <label for="tier-features" class="form-label">What's included</label>
                        <textarea id="tier-features" name="features" class="form-control" rows="2"
                                  placeholder="e.g. On-air readout during standard programming"><?= esc(old('features', null, false) ?? $rateTier['features'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-8">
                            <label for="tier-price" class="form-label">Price</label>
                            <input type="number" step="0.01" min="0" id="tier-price" name="price" class="form-control" required
                                   value="<?= esc(old('price', null, false) ?? $rateTier['price'] ?? '', 'attr') ?>">
                        </div>
                        <div class="col-sm-4">
                            <label for="tier-currency" class="form-label">Currency</label>
                            <input type="text" id="tier-currency" name="currency" class="form-control text-uppercase" maxlength="3"
                                   value="<?= esc(old('currency', null, false) ?? $rateTier['currency'] ?? $config->defaultCurrency, 'attr') ?>">
                        </div>
                    </div>

                    <div class="form-check form-switch mb-1">
                        <input type="checkbox" role="switch" name="includes_on_air" value="1" class="form-check-input" id="includesOnAir"
                               <?= (old('includes_on_air') ?? $rateTier['includes_on_air'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="includesOnAir">Includes an on-air readout</label>
                    </div>
                    <div class="form-text mb-3">Set per package, so it is always clear whether a package is read out on air.</div>

                    <div class="form-check form-switch mb-1">
                        <input type="checkbox" role="switch" name="includes_voice_note" value="1" class="form-check-input" id="includesVoiceNote"
                               <?= (old('includes_voice_note') ?? $rateTier['includes_voice_note'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="includesVoiceNote">Includes a voice note</label>
                    </div>
                    <div class="form-text mb-3">The booking form shows the voice recorder only to people who choose a package with this on.</div>

                    <div class="form-check form-switch">
                        <input type="checkbox" role="switch" name="is_active" value="1" class="form-check-input" id="isActive"
                               <?= (old('is_active') ?? $rateTier['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active (selectable in the booking flow)</label>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i><?= $rateTier ? 'Save changes' : 'Create package' ?></button>
                    <a href="<?= site_url('admin/rate-tiers') ?>" class="btn btn-light">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
