<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $template ? 'Edit template' : 'New template',
    'backUrl'   => site_url('admin/message-templates'),
    'backLabel' => 'Message templates',
]) ?>

<?= view_cell('App\Cells\FormErrors::render') ?>

<form method="post"
      action="<?= $template ? site_url('admin/message-templates/' . $template['id'] . '/update') : site_url('admin/message-templates') ?>"
      class="card">
    <?= csrf_field() ?>

    <div class="card-body" style="max-width: 640px;">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required maxlength="80"
                   value="<?= esc(old('title', null, false) ?? $template['title'] ?? '') ?>"
                   placeholder="e.g. Warm birthday wish">
            <div class="form-text">The label on the button visitors tap. Keep it short.</div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-7">
                <label class="form-label">Occasion</label>
                <select name="category_id" class="form-select">
                    <option value="">Any occasion</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= esc($category['id']) ?>"
                            <?= (old('category_id') ?? $template['category_id'] ?? null) == $category['id'] ? 'selected' : '' ?>>
                            <?= esc($category['name']) ?><?= (int) $category['is_active'] === 1 ? '' : ' (inactive)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Language</label>
                <input type="text" name="language" class="form-control" required maxlength="40" list="languageList"
                       value="<?= esc(old('language', null, false) ?? $template['language'] ?? 'English') ?>">
                <datalist id="languageList">
                    <?php foreach ($languages as $language): ?>
                        <option value="<?= esc($language) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea name="body" id="template-body" class="form-control" rows="5" required><?= esc(old('body', null, false) ?? $template['body'] ?? '') ?></textarea>
            <?= view('admin/message_templates/_editor_tools', ['maxLength' => $maxLength, 'tokens' => $tokens, 'wordsPerMinute' => $wordsPerMinute], ['saveData' => false, 'debug' => false]) ?>
            <div class="form-text">
                The booking form fills in the recipient's and the sender's names from what the visitor has typed,
                and leaves a marker for them to complete if they haven't typed one yet.
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Order</label>
                <input type="number" name="sort_order" class="form-control" min="0"
                       value="<?= esc(old('sort_order') ?? $template['sort_order'] ?? 0) ?>">
                <div class="form-text">Lower numbers come first.</div>
            </div>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                   <?= (old('is_active') ?? $template['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Shown on the booking form</label>
        </div>

        <button type="submit" class="btn btn-cbs-primary"><?= $template ? 'Save changes' : 'Create template' ?></button>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc(base_url('assets/js/template-editor.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
