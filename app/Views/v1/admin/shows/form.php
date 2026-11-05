<?= $this->extend(ui_layout('admin')) ?>

<?php
$pageTitle    = $show ? 'Edit show' : 'New show';
$selectedDays = old('days_of_week') ?? ($show && ! empty($show['days_of_week']) ? explode(',', $show['days_of_week']) : []);
$selectedDays = array_map('intval', (array) $selectedDays);
$dayLabels    = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
?>

<?= $this->section('title') ?><?= esc($pageTitle) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $pageTitle,
    'backUrl'   => site_url('admin/shows'),
    'backLabel' => 'Shows',
]) ?>

<div class="row">
    <div class="col-xl-7 col-lg-9">
        <?= view_cell('App\Cells\FormErrors::render') ?>

        <form method="post" action="<?= $show ? site_url('admin/shows/' . $show['id'] . '/update') : site_url('admin/shows') ?>" class="card custom-card">
            <?= csrf_field() ?>

            <div class="card-header"><div class="card-title">Show details</div></div>
            <div class="card-body pt-0">
                <div class="mb-3">
                    <label for="show-name" class="form-label">Show name</label>
                    <input type="text" id="show-name" name="name" class="form-control" required maxlength="100"
                           value="<?= esc(old('name', null, false) ?? $show['name'] ?? '', 'attr') ?>" placeholder="e.g. Morning Express">
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label for="show-start" class="form-label">Start time</label>
                        <input type="time" id="show-start" name="start_time" class="form-control" required
                               value="<?= esc(old('start_time', null, false) ?? substr($show['start_time'] ?? '', 0, 5), 'attr') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label for="show-end" class="form-label">End time</label>
                        <input type="time" id="show-end" name="end_time" class="form-control" required
                               value="<?= esc(old('end_time', null, false) ?? substr($show['end_time'] ?? '', 0, 5), 'attr') ?>">
                    </div>
                </div>

                <fieldset class="mb-3">
                    <legend class="form-label fs-14">Days this show runs</legend>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($dayLabels as $num => $label): ?>
                            <input type="checkbox" class="btn-check" name="days_of_week[]" value="<?= $num ?>"
                                   id="day<?= $num ?>" autocomplete="off" <?= in_array($num, $selectedDays, true) ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary btn-sm" style="min-width: 3.25rem;" for="day<?= $num ?>"><?= $label ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">Leave all unchecked for a show that runs every day.</div>
                </fieldset>

                <div class="form-check form-switch">
                    <input type="checkbox" role="switch" name="is_active" value="1" class="form-check-input" id="isActive"
                           <?= (old('is_active') ?? $show['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isActive">Active (selectable in the booking flow)</label>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i><?= $show ? 'Save changes' : 'Create show' ?></button>
                <a href="<?= site_url('admin/shows') ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
