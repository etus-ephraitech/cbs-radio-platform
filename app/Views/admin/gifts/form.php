<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $gift ? 'Edit gift' : 'New local gift',
    'backUrl'   => site_url('admin/gifts'),
    'backLabel' => 'Gifts',
]) ?>

<?= view_cell('App\Cells\FormErrors::render') ?>

<form method="post"
      action="<?= $gift ? site_url('admin/gifts/' . $gift['id'] . '/update') : site_url('admin/gifts') ?>"
      class="card">
    <?= csrf_field() ?>

    <div class="card-body" style="max-width: 560px;">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required maxlength="150"
                   value="<?= esc(old('name', null, false) ?? $gift['name'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="type" class="form-select" required>
                <?php foreach (['ecard' => 'E-card', 'airtime' => 'Airtime top-up', 'shoutout' => 'Shout-out', 'dedication' => 'Song dedication', 'physical' => 'Physical (our own fulfillment)'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (old('type') ?? $gift['type'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2"><?= esc(old('description', null, false) ?? $gift['description'] ?? '') ?></textarea>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label class="form-label">Price</label>
                <input type="number" step="0.01" min="0" name="price" class="form-control" required
                       value="<?= esc(old('price', null, false) ?? $gift['price'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Currency</label>
                <input type="text" name="currency" class="form-control" maxlength="3"
                       value="<?= esc(old('currency', null, false) ?? $gift['currency'] ?? $config->defaultCurrency) ?>">
            </div>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                   <?= (old('is_active') ?? $gift['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active (shown in the gift catalog)</label>
        </div>

        <button type="submit" class="btn btn-cbs-primary"><?= $gift ? 'Save changes' : 'Create gift' ?></button>
    </div>
</form>

<?= $this->endSection() ?>
