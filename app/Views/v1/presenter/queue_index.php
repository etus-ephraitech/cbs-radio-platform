<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Today's queue<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => "Today's queue",
    'subtitle' => 'Approved greetings, ready to read on air — ' . date('l, F j') . '.',
]) ?>

<?php if (empty($showQueues)): ?>
    <div class="card custom-card">
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent fs-3"><i class="ri-radio-2-line" aria-hidden="true"></i></span>
            <h6>No active shows are configured.</h6>
            <p class="mb-0 fs-13">An administrator adds shows under Catalog &rsaquo; Shows.</p>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <?php foreach ($showQueues as $entry): ?>
        <?php $show = $entry['show']; $count = count($entry['queue']); ?>
        <div class="col-xxl-6">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-md avatar-rounded <?= $count > 0 ? 'bg-primary-transparent' : 'bg-light text-muted' ?>"><i class="ri-mic-line fs-5" aria-hidden="true"></i></span>
                        <div>
                            <div class="card-title"><?= esc($show['name']) ?></div>
                            <div class="text-muted fs-12"><?= esc(substr($show['start_time'], 0, 5)) ?>&ndash;<?= esc(substr($show['end_time'], 0, 5)) ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge <?= $count > 0 ? 'bg-warning-transparent' : 'bg-light text-muted' ?> rounded-pill"><?= $count ?> in queue</span>
                        <a href="<?= site_url('presenter/shows/' . $show['id']) ?>" class="btn btn-sm btn-light" title="Open this show's queue, or another day's">
                            <i class="ri-calendar-line me-1" aria-hidden="true"></i>Other days
                        </a>
                    </div>
                </div>

                <?php if ($entry['queue'] === []): ?>
                    <div class="card-body pt-0 text-muted fs-13">Nothing in the queue for this show yet.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($entry['queue'] as $booking): ?>
                            <?= view(ui_view_path('presenter/_queue_item'), ['booking' => $booking, 'excerpt' => true], ['saveData' => false, 'debug' => false]) ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
