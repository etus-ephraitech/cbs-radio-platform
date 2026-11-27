<?= $this->extend(ui_layout('admin')) ?>

<?php $pageTitle = $template ? 'Edit template' : 'New template'; ?>

<?= $this->section('title') ?><?= esc($pageTitle) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $pageTitle,
    'backUrl'   => site_url('admin/message-templates'),
    'backLabel' => 'Message templates',
]) ?>

<div class="row">
    <div class="col-xl-8 col-lg-10">
        <?= view_cell('App\Cells\FormErrors::render') ?>

        <form method="post" action="<?= $template ? site_url('admin/message-templates/' . $template['id'] . '/update') : site_url('admin/message-templates') ?>" class="card custom-card">
            <?= csrf_field() ?>

            <div class="card-header"><div class="card-title">Template</div></div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <label for="template-title" class="form-label">Title</label>
                    <input type="text" id="template-title" name="title" class="form-control" required maxlength="80"
                           value="<?= esc(old('title', null, false) ?? $template['title'] ?? '', 'attr') ?>"
                           placeholder="e.g. Warm birthday wish">
                    <div class="form-text">The label on the button visitors tap. Keep it short.</div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label for="template-category" class="form-label">Occasion</label>
                        <select id="template-category" name="category_id" class="form-select">
                            <option value="">Any occasion</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= esc($category['id'], 'attr') ?>" <?= (string) (old('category_id') ?? $template['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                                    <?= esc($category['name']) ?><?= (int) $category['is_active'] === 1 ? '' : ' (inactive)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="template-language" class="form-label">Language</label>
                        <input type="text" id="template-language" name="language" class="form-control" required maxlength="40" list="languageList"
                               value="<?= esc(old('language', null, false) ?? $template['language'] ?? 'English', 'attr') ?>">
                        <datalist id="languageList">
                            <?php foreach ($languages as $language): ?>
                                <option value="<?= esc($language, 'attr') ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="template-body" class="form-label">Message</label>
                    <textarea id="template-body" name="body" class="form-control" rows="5" required><?= esc(old('body', null, false) ?? $template['body'] ?? '') ?></textarea>
                    <?= view(ui_view_path('admin/message_templates/_editor_tools'), ['maxLength' => $maxLength, 'tokens' => $tokens, 'wordsPerMinute' => $wordsPerMinute], ['saveData' => false, 'debug' => false]) ?>
                    <div class="form-text">
                        The booking form fills in the recipient's and the sender's names from what the visitor has typed,
                        and leaves a marker for them to complete if they haven't typed one yet.
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <label for="template-order" class="form-label">Order</label>
                        <input type="number" id="template-order" name="sort_order" class="form-control" min="0"
                               value="<?= esc(old('sort_order') ?? $template['sort_order'] ?? 0, 'attr') ?>">
                        <div class="form-text">Lower numbers come first.</div>
                    </div>
                </div>

                <div class="form-check form-switch">
                    <input type="checkbox" role="switch" name="is_active" value="1" class="form-check-input" id="isActive"
                           <?= (old('is_active') ?? $template['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isActive">Shown on the booking form</label>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i><?= $template ? 'Save changes' : 'Create template' ?></button>
                <a href="<?= site_url('admin/message-templates') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc(base_url('assets/js/template-editor.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
