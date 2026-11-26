<?php
/**
 * Admin > Logo (superadmin only).
 *
 * @var string $logoUrl    public URL of the uploaded logo, or ''
 * @var string $faviconUrl public URL of the uploaded tab icon, or ''
 * @var string $logoAlt
 * @var int    $logoMaxKb
 * @var int    $iconMaxKb
 */
$panels = [
    'logo' => [
        'title'  => 'Logo',
        'url'    => $logoUrl,
        'accept' => 'image/png,image/jpeg,image/webp',
        'help'   => 'PNG, JPG or WebP, up to ' . $logoMaxKb . ' KB. A wide logo on a transparent background works best; it is shown about 40 pixels tall.',
        'empty'  => 'No logo uploaded. The site shows the built-in CBS Radio name and lamp.',
        'bg'     => '#FFF8EE',
    ],
    'favicon' => [
        'title'  => 'Browser tab icon',
        'url'    => $faviconUrl,
        'accept' => 'image/png,image/x-icon',
        'help'   => 'A square PNG or ICO, up to ' . $iconMaxKb . ' KB.',
        'empty'  => 'No icon uploaded. Browsers show the built-in one.',
        'bg'     => '#ffffff',
    ],
];
?>
<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Logo<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', ['title' => 'Logo', 'subtitle' => 'The station logo and browser-tab icon, used on the public site, the admin area and the sign-in page.']) ?>

<div class="row">
    <?php foreach ($panels as $kind => $panel): ?>
        <div class="col-xl-6">
            <div class="card custom-card">
                <div class="card-header"><div class="card-title"><?= esc($panel['title']) ?></div></div>
                <div class="card-body pt-0">
                    <div class="d-flex align-items-center justify-content-center rounded border mb-3" style="min-height:110px;background:<?= $panel['bg'] ?>">
                        <?php if ($panel['url'] !== ''): ?>
                            <img src="<?= esc($panel['url'], 'attr') ?>" alt="Current <?= esc(strtolower($panel['title']), 'attr') ?>" style="max-height:72px;max-width:100%">
                        <?php else: ?>
                            <span class="text-muted fs-13 p-3 text-center"><?= esc($panel['empty']) ?></span>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="<?= site_url('admin/branding/upload/' . $kind) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <label for="img-<?= $kind ?>" class="form-label"><?= $panel['url'] !== '' ? 'Replace' : 'Upload' ?></label>
                        <input type="file" id="img-<?= $kind ?>" name="image" class="form-control" accept="<?= esc($panel['accept'], 'attr') ?>" required>
                        <div class="form-text"><?= esc($panel['help']) ?></div>
                        <button type="submit" class="btn btn-primary mt-3">Upload</button>
                    </form>

                    <?php if ($panel['url'] !== ''): ?>
                        <form method="post" action="<?= site_url('admin/branding/remove/' . $kind) ?>" class="mt-2" id="remove-<?= $kind ?>">
                            <?= csrf_field() ?>
                            <?= view_cell('App\Cells\ConfirmButton::render', [
                                'formId'      => 'remove-' . $kind,
                                'label'       => 'Remove and use the built-in one',
                                'title'       => 'Remove the ' . strtolower($panel['title']) . '?',
                                'text'        => 'The uploaded file is deleted and the built-in one shows again.',
                                'buttonClass' => 'btn btn-sm btn-outline-danger',
                            ]) ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="col-xl-6">
        <form method="post" action="<?= site_url('admin/branding/alt') ?>" class="card custom-card">
            <?= csrf_field() ?>
            <div class="card-header"><div class="card-title">Logo description</div></div>
            <div class="card-body pt-0">
                <label for="logo-alt" class="form-label">Read out by screen readers</label>
                <input type="text" id="logo-alt" name="alt" class="form-control" maxlength="80" value="<?= esc($logoAlt, 'attr') ?>">
                <div class="form-text">Also shown if the image cannot load. Leave empty for "CBS Radio".</div>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
