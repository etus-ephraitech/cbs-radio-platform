<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Shows<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Shows',
    'subtitle'    => 'The time slots listeners can pick when scheduling a greeting.',
    'actionUrl'   => site_url('admin/shows/create'),
    'actionLabel' => 'New show',
    'actionIcon'  => 'ri-add-line',
]) ?>

<?php $dayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; ?>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">
            Shows
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= count($shows) ?></span>
        </div>
    </div>

    <?php if (empty($shows)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-radio-2-line" aria-hidden="true"></i></span>
            <h6>No shows yet.</h6>
            <p class="mb-3 fs-13">Without a show, listeners can't pick when their greeting airs.</p>
            <a href="<?= site_url('admin/shows/create') ?>" class="btn btn-primary"><i class="ri-add-line me-1" aria-hidden="true"></i>New show</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
                <thead>
                    <tr><th>Name</th><th>Time</th><th>Days</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($shows as $show): ?>
                        <?php $days = empty($show['days_of_week']) ? [] : array_map('intval', explode(',', $show['days_of_week'])); ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($show['name']) ?></td>
                            <td><i class="ri-time-line text-muted me-1 align-middle" aria-hidden="true"></i><?= esc(substr($show['start_time'], 0, 5)) ?>&ndash;<?= esc(substr($show['end_time'], 0, 5)) ?></td>
                            <td>
                                <?php if ($days === []): ?>
                                    <span class="badge bg-success-transparent">Daily</span>
                                <?php else: ?>
                                    <span class="visually-hidden"><?= esc(implode(', ', array_map(static fn ($d) => $dayLabels[$d] ?? $d, $days))) ?></span>
                                    <span class="d-inline-flex gap-1" aria-hidden="true">
                                        <?php foreach ($dayLabels as $num => $label): ?>
                                            <span class="badge <?= in_array($num, $days, true) ? 'bg-primary-transparent' : 'bg-light text-muted' ?>"><?= $label ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $show['is_active']]) ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('admin/shows/' . $show['id'] . '/edit') ?>" class="btn btn-sm btn-primary-light"><i class="ri-pencil-line me-1" aria-hidden="true"></i>Edit</a>
                                    <?= view_cell('App\Cells\ActiveToggle::render', [
                                        'baseUrl'      => 'admin/shows',
                                        'id'           => $show['id'],
                                        'isActive'     => $show['is_active'],
                                        'confirmTitle' => 'Deactivate this show?',
                                        'confirmText'  => "It will no longer appear in the booking flow's scheduling step.",
                                    ]) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
