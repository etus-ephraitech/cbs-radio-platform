<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Shows',
    'subtitle'    => 'The time slots listeners can pick when scheduling a greeting.',
    'actionUrl'   => site_url('admin/shows/create'),
    'actionLabel' => '+ New show',
]) ?>

<?php $dayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; ?>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr><th>Name</th><th>Time</th><th>Days</th><th>Status</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
        <?php if (empty($shows)): ?>
            <tr><td colspan="5" class="text-muted">No shows yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($shows as $show): ?>
            <tr>
                <td><?= esc($show['name']) ?></td>
                <td><?= esc(substr($show['start_time'], 0, 5)) ?>&ndash;<?= esc(substr($show['end_time'], 0, 5)) ?></td>
                <td>
                    <?php if (empty($show['days_of_week'])): ?>
                        <span class="text-muted">Daily</span>
                    <?php else: ?>
                        <?= esc(implode(', ', array_map(static fn ($d) => $dayLabels[(int) $d] ?? $d, explode(',', $show['days_of_week'])))) ?>
                    <?php endif; ?>
                </td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $show['is_active']]) ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?= site_url('admin/shows/' . $show['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <?= view_cell('App\Cells\ActiveToggle::render', [
                        'baseUrl'      => 'admin/shows',
                        'id'           => $show['id'],
                        'isActive'     => $show['is_active'],
                        'confirmTitle' => 'Deactivate this show?',
                        'confirmText'  => "It will no longer appear in the booking flow's scheduling step.",
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= $this->endSection() ?>
