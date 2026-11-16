<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $vendor ? 'Edit vendor' : 'New vendor',
    'backUrl'   => site_url('admin/gift-vendors'),
    'backLabel' => 'Gift Vendors',
]) ?>

<?= view_cell('App\Cells\FormErrors::render') ?>

<form method="post"
      action="<?= $vendor ? site_url('admin/gift-vendors/' . $vendor['id'] . '/update') : site_url('admin/gift-vendors') ?>"
      class="card">
    <?= csrf_field() ?>

    <div class="card-body" style="max-width: 560px;">
        <div class="mb-3">
            <label class="form-label">Vendor name</label>
            <input type="text" name="name" class="form-control" required maxlength="150"
                   value="<?= esc(old('name', null, false) ?? $vendor['name'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">API base URL</label>
            <input type="url" name="api_base_url" class="form-control"
                   value="<?= esc(old('api_base_url', null, false) ?? $vendor['api_base_url'] ?? '') ?>"
                   placeholder="https://vendor.example.com/api">
        </div>

        <?php if ($vendor): ?>
            <div class="alert alert-secondary small">
                The API key is set in <code>.env</code>, not here: <code>vendor.<?= esc($vendor['slug']) ?>.apiKey</code>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary small">
                After saving, add the vendor's API key to <code>.env</code> as <code>vendor.{slug}.apiKey</code>
                (the slug is generated from the name) before running a sync.
            </div>
        <?php endif; ?>

        <div class="form-check mb-3">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                   <?= (old('is_active') ?? $vendor['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active (included in the next sync)</label>
        </div>

        <button type="submit" class="btn btn-cbs-primary"><?= $vendor ? 'Save changes' : 'Create vendor' ?></button>
    </div>
</form>

<?= $this->endSection() ?>
