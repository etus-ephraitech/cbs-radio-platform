<?= $this->extend(ui_layout('admin')) ?>

<?php $pageTitle = $vendor ? 'Edit vendor' : 'New vendor'; ?>

<?= $this->section('title') ?><?= esc($pageTitle) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $pageTitle,
    'backUrl'   => site_url('admin/gift-vendors'),
    'backLabel' => 'Gift vendors',
]) ?>

<div class="row">
    <div class="col-xl-7 col-lg-9">
        <?= view_cell('App\Cells\FormErrors::render') ?>

        <form method="post" action="<?= $vendor ? site_url('admin/gift-vendors/' . $vendor['id'] . '/update') : site_url('admin/gift-vendors') ?>" class="card custom-card">
            <?= csrf_field() ?>

            <div class="card-header"><div class="card-title">Vendor details</div></div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <label for="vendor-name" class="form-label">Vendor name</label>
                    <input type="text" id="vendor-name" name="name" class="form-control" required maxlength="150"
                           value="<?= esc(old('name', null, false) ?? $vendor['name'] ?? '', 'attr') ?>">
                </div>

                <div class="mb-3">
                    <label for="vendor-url" class="form-label">API base URL</label>
                    <input type="url" id="vendor-url" name="api_base_url" class="form-control"
                           value="<?= esc(old('api_base_url', null, false) ?? $vendor['api_base_url'] ?? '', 'attr') ?>"
                           placeholder="https://vendor.example.com/api">
                </div>

                <div class="alert alert-info d-flex gap-2 align-items-start">
                    <i class="ri-key-2-line fs-5" aria-hidden="true"></i>
                    <div>
                        <?php if ($vendor): ?>
                            The API key is set in <code>.env</code>, not here: <code>vendor.<?= esc($vendor['slug']) ?>.apiKey</code>
                        <?php else: ?>
                            After saving, add the vendor's API key to <code>.env</code> as <code>vendor.{slug}.apiKey</code>
                            (the slug is generated from the name) before running a sync.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-check form-switch">
                    <input type="checkbox" role="switch" name="is_active" value="1" class="form-check-input" id="isActive"
                           <?= (old('is_active') ?? $vendor['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isActive">Active (included in the next sync)</label>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i><?= $vendor ? 'Save changes' : 'Create vendor' ?></button>
                <a href="<?= site_url('admin/gift-vendors') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
