<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Message templates',
    'subtitle'    => 'Example messages offered under the message box on the booking form, so nobody starts from a blank page. Add them in any language.',
    'actionUrl'   => site_url('admin/message-templates/create'),
    'actionLabel' => '+ New template',
]) ?>

<table class="table table-sm table-hover bg-white align-middle">
    <thead>
        <tr><th>Title</th><th>Occasion</th><th>Language</th><th>Message</th><th>Status</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
        <?php if (empty($templates)): ?>
            <tr><td colspan="6" class="text-muted">No templates yet. Visitors will see an empty message box.</td></tr>
        <?php endif; ?>
        <?php foreach ($templates as $template): ?>
            <tr>
                <td class="fw-semibold"><?= esc($template['title']) ?></td>
                <td><?= $template['category_name'] ? esc($template['category_name']) : '<span class="text-muted">Any occasion</span>' ?></td>
                <td><?= esc($template['language']) ?></td>
                <td class="small text-muted" style="max-width: 360px;"><?= esc(cbs_excerpt($template['body'], 90)) ?></td>
                <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $template['is_active']]) ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?= site_url('admin/message-templates/' . $template['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <?= view_cell('App\Cells\ActiveToggle::render', [
                        'baseUrl'         => 'admin/message-templates',
                        'id'              => $template['id'],
                        'isActive'        => $template['is_active'],
                        'confirm'         => false,
                        'deactivateLabel' => 'Hide',
                        'activateLabel'   => 'Show',
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= $this->endSection() ?>
