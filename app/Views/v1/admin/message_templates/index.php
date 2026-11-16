<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Message templates<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'       => 'Message templates',
    'subtitle'    => 'Example messages offered under the message box on the booking form, so nobody starts from a blank page. Add them in any language.',
    'actionUrl'   => site_url('admin/message-templates/create'),
    'actionLabel' => 'New template',
    'actionIcon'  => 'ri-add-line',
]) ?>

<div class="card custom-card">
    <div class="card-header">
        <div class="card-title">
            Templates
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= count($templates) ?></span>
        </div>
    </div>

    <?php if (empty($templates)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-chat-quote-line" aria-hidden="true"></i></span>
            <h6>No templates yet. Visitors will see an empty message box.</h6>
            <p class="mb-3 fs-13">A few warm examples per occasion help listeners get started.</p>
            <a href="<?= site_url('admin/message-templates/create') ?>" class="btn btn-primary"><i class="ri-add-line me-1" aria-hidden="true"></i>New template</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover text-nowrap mb-0 table-cbs">
                <thead>
                    <tr><th>Title</th><th>Occasion</th><th>Language</th><th>Message</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($template['title']) ?></td>
                            <td><?= $template['category_name'] ? esc($template['category_name']) : '<span class="text-muted">Any occasion</span>' ?></td>
                            <td><span class="badge bg-light text-default"><?= esc($template['language']) ?></span></td>
                            <td class="text-wrap text-muted fs-13" style="min-width: 16rem; max-width: 26rem;"><?= esc(cbs_excerpt($template['body'], 90)) ?></td>
                            <td><?= view_cell('App\Cells\StatusBadge::render', ['type' => 'active', 'status' => $template['is_active']]) ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= site_url('admin/message-templates/' . $template['id'] . '/edit') ?>" class="btn btn-sm btn-primary-light"><i class="ri-pencil-line me-1" aria-hidden="true"></i>Edit</a>
                                    <?= view_cell('App\Cells\ActiveToggle::render', [
                                        'baseUrl'         => 'admin/message-templates',
                                        'id'              => $template['id'],
                                        'isActive'        => $template['is_active'],
                                        'confirm'         => false,
                                        'deactivateLabel' => 'Hide',
                                        'activateLabel'   => 'Show',
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
