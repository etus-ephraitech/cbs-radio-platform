<?= $this->extend(ui_layout('admin')) ?>

<?= $this->section('title') ?>Queue for <?= esc($date) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'     => 'Queue for ' . $date,
    'subtitle'  => 'Approved greetings for this show, oldest first.',
    'backUrl'   => site_url('presenter'),
    'backLabel' => "Today's queue",
]) ?>

<div class="card custom-card">
    <div class="card-header justify-content-between">
        <div class="card-title">
            Greetings
            <span class="badge bg-primary-transparent rounded-pill ms-1 align-middle"><?= count($queue) ?></span>
        </div>
        <form method="get" class="d-flex gap-2" action="<?= site_url('presenter/shows/' . (int) $showId) ?>">
            <label for="queue-date" class="visually-hidden">Date</label>
            <input type="date" id="queue-date" name="date" value="<?= esc($date, 'attr') ?>" class="form-control form-control-sm">
            <button type="submit" class="btn btn-sm btn-primary">Go</button>
        </form>
    </div>

    <?php if (empty($queue)): ?>
        <div class="cbs-empty">
            <span class="avatar avatar-xl avatar-rounded bg-success-transparent fs-3"><i class="ri-checkbox-circle-line" aria-hidden="true"></i></span>
            <h6>Nothing in the queue for this date.</h6>
            <p class="mb-0 fs-13">Greetings appear here once they are approved for this show and day.</p>
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($queue as $booking): ?>
                <?= view(ui_view_path('presenter/_queue_item'), ['booking' => $booking, 'excerpt' => false], ['saveData' => false, 'debug' => false]) ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
