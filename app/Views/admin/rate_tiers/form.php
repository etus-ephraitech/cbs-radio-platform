<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $rateTier ? 'Edit package' : 'New package',
    'backUrl'   => site_url('admin/rate-tiers'),
    'backLabel' => 'Packages',
]) ?>

<?= view_cell('App\Cells\FormErrors::render') ?>

<?php if (empty($categories)): ?>
    <div class="alert alert-warning">
        No active categories yet — <a href="<?= site_url('admin/categories/create') ?>">create one first</a>.
    </div>
<?php else: ?>

<form method="post"
      action="<?= $rateTier ? site_url('admin/rate-tiers/' . $rateTier['id'] . '/update') : site_url('admin/rate-tiers') ?>"
      class="card">
    <?= csrf_field() ?>

    <div class="card-body" style="max-width: 560px;">
        <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
                <option value="">Choose a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= esc($category['id']) ?>"
                        <?= (old('category_id') ?? $rateTier['category_id'] ?? null) == $category['id'] ? 'selected' : '' ?>>
                        <?= esc($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Package level</label>
            <input type="text" name="package_level" class="form-control" required maxlength="100"
                   value="<?= esc(old('package_level', null, false) ?? $rateTier['package_level'] ?? '') ?>"
                   placeholder="e.g. Basic Text, Voice Note + Text, Prime Slot">
        </div>

        <div class="mb-3">
            <label class="form-label">What's included</label>
            <textarea name="features" class="form-control" rows="2"
                      placeholder="e.g. On-air readout during standard programming"><?= esc(old('features', null, false) ?? $rateTier['features'] ?? '') ?></textarea>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="includes_on_air" value="1" class="form-check-input" id="includesOnAir"
                   <?= (old('includes_on_air') ?? $rateTier['includes_on_air'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="includesOnAir">Includes an on-air readout</label>
            <div class="form-text">Set explicitly per package — this replaces the "is the Milestone tier on-air or not" ambiguity from the original proposal.</div>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="includes_voice_note" value="1" class="form-check-input" id="includesVoiceNote"
                   <?= (old('includes_voice_note') ?? $rateTier['includes_voice_note'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="includesVoiceNote">Includes a voice note</label>
            <div class="form-text">The booking form shows the voice recorder only to people who choose a package with this on.</div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label class="form-label">Price</label>
                <input type="number" step="0.01" min="0" name="price" class="form-control" required
                       value="<?= esc(old('price', null, false) ?? $rateTier['price'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Currency</label>
                <input type="text" name="currency" class="form-control" maxlength="3"
                       value="<?= esc(old('currency', null, false) ?? $rateTier['currency'] ?? $config->defaultCurrency) ?>">
            </div>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                   <?= (old('is_active') ?? $rateTier['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active (selectable in the booking flow)</label>
        </div>

        <button type="submit" class="btn btn-cbs-primary"><?= $rateTier ? 'Save changes' : 'Create package' ?></button>
    </div>
</form>

<?php endif; ?>

<?= $this->endSection() ?>
