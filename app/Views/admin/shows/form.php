<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => $show ? 'Edit show' : 'New show',
    'backUrl'   => site_url('admin/shows'),
    'backLabel' => 'Shows',
]) ?>

<?= view_cell('App\Cells\FormErrors::render') ?>

<?php
$selectedDays = old('days_of_week') ?? ($show && ! empty($show['days_of_week']) ? explode(',', $show['days_of_week']) : []);
$selectedDays = array_map('intval', $selectedDays);
$dayLabels    = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
?>

<form method="post"
      action="<?= $show ? site_url('admin/shows/' . $show['id'] . '/update') : site_url('admin/shows') ?>"
      class="card">
    <?= csrf_field() ?>

    <div class="card-body" style="max-width: 560px;">
        <div class="mb-3">
            <label class="form-label">Show name</label>
            <input type="text" name="name" class="form-control" required maxlength="100"
                   value="<?= esc(old('name', null, false) ?? $show['name'] ?? '') ?>" placeholder="e.g. Morning Express">
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6">
                <label class="form-label">Start time</label>
                <input type="time" name="start_time" class="form-control" required
                       value="<?= esc(old('start_time', null, false) ?? substr($show['start_time'] ?? '', 0, 5)) ?>">
            </div>
            <div class="col-6">
                <label class="form-label">End time</label>
                <input type="time" name="end_time" class="form-control" required
                       value="<?= esc(old('end_time', null, false) ?? substr($show['end_time'] ?? '', 0, 5)) ?>">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label d-block">Days this show runs</label>
            <div class="btn-group" role="group">
                <?php foreach ($dayLabels as $num => $label): ?>
                    <input type="checkbox" class="btn-check" name="days_of_week[]" value="<?= $num ?>"
                           id="day<?= $num ?>" autocomplete="off" <?= in_array($num, $selectedDays, true) ? 'checked' : '' ?>>
                    <label class="btn btn-outline-secondary btn-sm" for="day<?= $num ?>"><?= $label ?></label>
                <?php endforeach; ?>
            </div>
            <div class="form-text">Leave all unchecked for a show that runs every day.</div>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                   <?= (old('is_active') ?? $show['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active (selectable in the booking flow)</label>
        </div>

        <button type="submit" class="btn btn-cbs-primary"><?= $show ? 'Save changes' : 'Create show' ?></button>
    </div>
</form>

<?= $this->endSection() ?>
