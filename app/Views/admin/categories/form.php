<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $category ? 'Edit category' : 'New category',
    'backUrl'   => site_url('admin/categories'),
    'backLabel' => 'Categories',
]) ?>

<?= view_cell('App\Cells\FormErrors::render') ?>

<form method="post"
      action="<?= $category ? site_url('admin/categories/' . $category['id'] . '/update') : site_url('admin/categories') ?>"
      class="card">
    <?= csrf_field() ?>

    <div class="card-body" style="max-width: 560px;">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required maxlength="100"
                   value="<?= esc(old('name', null, false) ?? $category['name'] ?? '') ?>">
            <?php if ($category): ?>
                <div class="form-text">Current URL: <code>/booking/<?= esc($category['slug']) ?></code> — changing the name regenerates this.</div>
            <?php else: ?>
                <div class="form-text">The booking URL is generated automatically from the name.</div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= esc(old('description', null, false) ?? $category['description'] ?? '') ?></textarea>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                   <?= (old('is_active') ?? $category['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active (visible in the booking flow)</label>
        </div>

        <button type="submit" class="btn btn-cbs-primary"><?= $category ? 'Save changes' : 'Create category' ?></button>
    </div>
</form>

<?= $this->endSection() ?>
