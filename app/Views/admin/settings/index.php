<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php
/**
 * How a stored value reads in the change history.
 */
$show = static function (string $type, ?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }

    if ($type === 'bool') {
        return $value === '1' ? 'on' : 'off';
    }

    return cbs_excerpt(str_replace("\n", ' / ', $value), 60);
};
?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Settings',
    'subtitle' => 'Changes take effect straight away. Passwords, API keys and tokens are not settings — they stay in .env.',
]) ?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($groups as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $key === $group ? 'active' : '' ?>" href="<?= site_url('admin/settings/' . $key) ?>"><?= esc($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?= view_cell('App\Cells\FormErrors::render') ?>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="post" action="<?= site_url('admin/settings/' . $group) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-body">

                <?php if ($group === 'templates'): ?>
                    <div class="alert alert-secondary small">
                        Each message can include these placeholders, which are filled in for every booking:
                        <?php foreach ($tokens as $token): ?>
                            <code>{<?= esc($token) ?>}</code>
                        <?php endforeach; ?>
                        A placeholder that isn't on this list is rejected when you save, so a typo can't reach a real person.
                    </div>
                <?php endif; ?>

                <?php foreach ($items as $item): ?>
                    <?= view_cell('App\Cells\SettingField::render', ['item' => $item]) ?>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-cbs-primary">Save changes</button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">

        <?php if (! empty($secrets)): ?>
            <div class="card mb-3">
                <div class="card-body small">
                    <h2 class="h6">Credentials in <code>.env</code></h2>
                    <p class="text-muted">Set by whoever deploys the site. Only whether each one is present is shown here — never its value.</p>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($secrets as $secret): ?>
                            <li class="d-flex justify-content-between align-items-start mb-2">
                                <span><?= esc($secret['label']) ?><br><code class="text-muted"><?= esc($secret['env']) ?></code></span>
                                <?php if ($secret['configured']): ?>
                                    <span class="badge bg-success">Set</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not set</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($group === 'status'): ?>
            <div class="card mb-3">
                <div class="card-body small">
                    <h2 class="h6">Preview</h2>
                    <p class="text-muted">
                        See the page exactly as visitors will, using the settings as last saved. Staff can always see the
                        real site, so this is the only way to check these pages before switching one on.
                    </p>
                    <div class="d-grid gap-2">
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="<?= site_url('admin/settings/preview/coming_soon') ?>">Preview the coming soon page &nearr;</a>
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="<?= site_url('admin/settings/preview/maintenance') ?>">Preview the maintenance page &nearr;</a>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-body small">
                    <h2 class="h6">What stays working</h2>
                    <ul class="mb-0 ps-3">
                        <li>The admin area and the presenter portal, so staff can still work and switch the site back on.</li>
                        <li>Payment confirmations from the Mobile Money provider, so a payment made just before you closed the site is still recorded.</li>
                        <li>Anyone signed in as staff sees the whole public site, with a red banner as a reminder.</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($group === 'notifications'): ?>
            <div class="card mb-3">
                <div class="card-body small">
                    <h2 class="h6">Send a test message</h2>
                    <p class="text-muted">Uses the settings as last saved, and ignores the on/off switch so you can check a channel before turning it on. The attempt appears in the <a href="<?= site_url('admin/notifications') ?>">notifications log</a>.</p>

                    <form method="post" action="<?= site_url('admin/settings/test/sms') ?>" class="mb-3">
                        <?= csrf_field() ?>
                        <label class="form-label mb-1">SMS to</label>
                        <div class="input-group input-group-sm">
                            <input type="tel" name="recipient" class="form-control" placeholder="0772 000 000" required>
                            <button type="submit" class="btn btn-outline-secondary">Send</button>
                        </div>
                    </form>

                    <form method="post" action="<?= site_url('admin/settings/test/email') ?>">
                        <?= csrf_field() ?>
                        <label class="form-label mb-1">Email to</label>
                        <div class="input-group input-group-sm">
                            <input type="email" name="recipient" class="form-control" placeholder="you@example.com" required>
                            <button type="submit" class="btn btn-outline-secondary">Send</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h2 class="h6">Recent changes</h2>
        <?php if (empty($changes)): ?>
            <p class="text-muted small mb-0">No settings have been changed yet.</p>
        <?php else: ?>
            <table class="table table-sm align-middle mb-0 small">
                <thead><tr><th>When</th><th>Setting</th><th>Change</th><th>By</th></tr></thead>
                <tbody>
                    <?php foreach ($changes as $change): ?>
                        <tr>
                            <td class="text-nowrap"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $change['created_at']]) ?></td>
                            <td><?= esc($change['label']) ?></td>
                            <td>
                                <?php if ($change['action'] === 'reset'): ?>
                                    Reset to default
                                    <span class="text-muted">(was <?= esc($show($change['type'], $change['old_value'])) ?>)</span>
                                <?php else: ?>
                                    <span class="text-muted"><?= esc($show($change['type'], $change['old_value'])) ?></span>
                                    &rarr; <?= esc($show($change['type'], $change['new_value'])) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= $change['changed_by'] ? 'user #' . esc($change['changed_by']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Live character / SMS-segment count under each SMS template. Approximate:
// plain text fits 160 characters in one SMS (153 per part once it is split);
// anything outside basic ASCII switches the message to a 70 (67) limit.
document.querySelectorAll('textarea[data-sms-counter]').forEach(function (box) {
    var out = document.querySelector('[data-sms-counter-for="' + box.id + '"]');

    function update() {
        var text = box.value;
        var unicode = /[^\x00-\x7F]/.test(text);
        var single = unicode ? 70 : 160, multi = unicode ? 67 : 153;
        var parts = text.length <= single ? 1 : Math.ceil(text.length / multi);
        out.textContent = text.length + ' characters · about ' + parts + ' SMS' + (parts > 1 ? ' messages' : '')
            + (unicode ? ' (contains non-standard characters, so a shorter limit applies)' : '');
    }

    box.addEventListener('input', update);
    update();
});
</script>
<?= $this->endSection() ?>
