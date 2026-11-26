<?php
/**
 * Admin > Appearance (superadmin only).
 *
 * @var array<string, string[]> $sections heading => setting keys
 * @var array<string, array>    $items    SettingsManager::forGroup() entries, by key
 * @var array<int, array>       $changes  recent appearance changes
 */
?>
<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Appearance<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', ['title' => 'Appearance', 'subtitle' => 'How the staff area looks. One choice for everyone: it is not a personal setting.']) ?>

<?= view_cell('App\Cells\FormErrors::render') ?>

<form method="post" action="<?= site_url('admin/appearance') ?>">
    <?= csrf_field() ?>

    <div class="row">
        <div class="col-xl-8">
            <?php foreach ($sections as $heading => $keys): ?>
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title"><?= esc($heading) ?></div></div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($keys as $key): ?>
                                <?php if (isset($items[$key])): ?>
                                    <div class="col-md-6"><?= view_cell('App\Cells\SettingField::render', ['item' => $items[$key]]) ?></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary">Save appearance</button>
                <a href="<?= site_url('admin/appearance') ?>" class="btn btn-light">Discard changes</a>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card custom-card">
                <div class="card-header"><div class="card-title">Good to know</div></div>
                <div class="card-body fs-13">
                    <ul class="ps-3 mb-0">
                        <li class="mb-2">Applies to every staff member on their next page. Nobody has a personal switcher any more.</li>
                        <li class="mb-2">A side-menu style is ignored with a top menu, and the other way round.</li>
                        <li class="mb-2">Tick "Reset to default" on a field to go back to the standard look.</li>
                        <li>This does not change the public website.</li>
                    </ul>
                </div>
            </div>

            <div class="card custom-card">
                <div class="card-header"><div class="card-title">Recent changes</div></div>
                <div class="card-body pt-0">
                    <?php if ($changes === []): ?>
                        <p class="text-muted fs-13 mb-0">Nothing changed yet.</p>
                    <?php else: ?>
                        <ul class="list-unstyled fs-13 mb-0">
                            <?php foreach (array_slice($changes, 0, 8) as $c): ?>
                                <li class="py-1 border-bottom">
                                    <strong><?= esc($c['label']) ?></strong>:
                                    <?= $c['action'] === 'reset' ? 'back to default' : esc((string) $c['new_value'] === '' ? 'cleared' : (string) $c['new_value']) ?>
                                    <div class="text-muted"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => (string) $c['created_at']]) ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
