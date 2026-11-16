<?= $this->extend(ui_layout('admin')) ?>

<?php $pageTitle = $category ? 'Edit category' : 'New category'; ?>

<?= $this->section('title') ?><?= esc($pageTitle) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $pageTitle,
    'backUrl'   => site_url('admin/categories'),
    'backLabel' => 'Categories',
]) ?>

<div class="row">
    <div class="col-xl-7 col-lg-9">
        <?= view_cell('App\Cells\FormErrors::render') ?>

        <form method="post" action="<?= $category ? site_url('admin/categories/' . $category['id'] . '/update') : site_url('admin/categories') ?>" class="card custom-card">
            <?= csrf_field() ?>

            <div class="card-header"><div class="card-title">Category details</div></div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <label for="category-name" class="form-label">Name</label>
                    <input type="text" id="category-name" name="name" class="form-control" required maxlength="100"
                           value="<?= esc(old('name', null, false) ?? $category['name'] ?? '', 'attr') ?>">
                    <?php if ($category): ?>
                        <div class="form-text">Current URL: <code>/booking/<?= esc($category['slug']) ?></code> — changing the name regenerates this.</div>
                    <?php else: ?>
                        <div class="form-text">The booking URL is generated automatically from the name.</div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="category-description" class="form-label">Description</label>
                    <textarea id="category-description" name="description" class="form-control" rows="3"><?= esc(old('description', null, false) ?? $category['description'] ?? '') ?></textarea>
                </div>

                <div class="form-check form-switch">
                    <input type="checkbox" role="switch" name="is_active" value="1" class="form-check-input" id="isActive"
                           <?= (old('is_active') ?? $category['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isActive">Active (visible in the booking flow)</label>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i><?= $category ? 'Save changes' : 'Create category' ?></button>
                <a href="<?= site_url('admin/categories') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
