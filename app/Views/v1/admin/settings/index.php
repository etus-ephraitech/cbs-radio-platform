<?= $this->extend(ui_layout('admin')) ?>

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

// An icon per settings group; a group without one gets the generic cog, so a new group needs no change here.
$groupIcons = [
    'general'       => 'ri-settings-3-line',
    'site'          => 'ri-global-line',
    'payments'      => 'ri-wallet-3-line',
    'notifications' => 'ri-notification-3-line',
    'templates'     => 'ri-chat-quote-line',
    'bookings'      => 'ri-calendar-check-line',
];
?>

<?= $this->section('title') ?>Settings — <?= esc($groups[$group] ?? $group) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view_cell('App\Cells\PageHeader::render', [
    'title'    => 'Settings',
    'subtitle' => 'Changes take effect straight away. Passwords, API keys and tokens are not settings — they stay in .env.',
]) ?>

<div class="row">
    <div class="col-xl-3 col-lg-4">
        <div class="card custom-card">
            <div class="card-body p-2">
                <nav class="nav nav-pills flex-column cbs-settings-nav" aria-label="Settings groups">
                    <?php foreach ($groups as $key => $label): ?>
                        <?php $active = $key === $group; ?>
                        <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= site_url('admin/settings/' . $key) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                            <i class="<?= esc($groupIcons[$key] ?? 'ri-settings-3-line', 'attr') ?> fs-16" aria-hidden="true"></i><?= esc($label) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <?php if (! empty($secrets)): ?>
            <div class="card custom-card">
                <div class="card-header"><div class="card-title">Credentials in <code>.env</code></div></div>
                <div class="card-body pt-0">
                    <p class="text-muted fs-12">Set by whoever deploys the site. Only whether each one is present is shown here — never its value.</p>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($secrets as $secret): ?>
                            <li class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <span class="fs-13">
                                    <?= esc($secret['label']) ?>
                                    <code class="d-block text-muted fs-11"><?= esc($secret['env']) ?></code>
                                </span>
                                <?php if ($secret['configured']): ?>
                                    <span class="badge bg-success-transparent"><i class="ri-check-line me-1" aria-hidden="true"></i>Set</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-transparent">Not set</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-9 col-lg-8">
        <?= view_cell('App\Cells\FormErrors::render') ?>

        <form method="post" action="<?= site_url('admin/settings/' . $group) ?>" class="card custom-card">
            <?= csrf_field() ?>
            <div class="card-header"><div class="card-title"><?= esc($groups[$group] ?? $group) ?></div></div>
            <div class="card-body pt-0">
                <?php if ($group === 'templates'): ?>
                    <div class="alert alert-info">
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
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1" aria-hidden="true"></i>Save changes</button>
            </div>
        </form>

        <?php if ($group === 'notifications'): ?>
            <div class="card custom-card">
                <div class="card-header"><div class="card-title">Send a test message</div></div>
                <div class="card-body pt-0">
                    <p class="text-muted fs-13">Uses the settings as last saved, and ignores the on/off switch so you can check a channel before turning it on. The attempt appears in the <a href="<?= site_url('admin/notifications') ?>">notifications log</a>.</p>
                    <div class="row g-3">
                        <form method="post" action="<?= site_url('admin/settings/test/sms') ?>" class="col-md-6">
                            <?= csrf_field() ?>
                            <label for="test-sms" class="form-label">SMS to</label>
                            <div class="input-group">
                                <input type="tel" id="test-sms" name="recipient" class="form-control" placeholder="0772 000 000" required>
                                <button type="submit" class="btn btn-primary-light"><i class="ri-send-plane-line me-1" aria-hidden="true"></i>Send</button>
                            </div>
                        </form>
                        <form method="post" action="<?= site_url('admin/settings/test/email') ?>" class="col-md-6">
                            <?= csrf_field() ?>
                            <label for="test-email" class="form-label">Email to</label>
                            <div class="input-group">
                                <input type="email" id="test-email" name="recipient" class="form-control" placeholder="you@example.com" required>
                                <button type="submit" class="btn btn-primary-light"><i class="ri-send-plane-line me-1" aria-hidden="true"></i>Send</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Recent changes</div></div>
            <?php if (empty($changes)): ?>
                <div class="card-body pt-0"><p class="text-muted fs-13 mb-0">No settings have been changed yet.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0 table-cbs">
                        <thead><tr><th>When</th><th>Setting</th><th>Change</th><th>By</th></tr></thead>
                        <tbody>
                            <?php foreach ($changes as $change): ?>
                                <tr>
                                    <td class="text-nowrap fs-13"><?= view_cell('App\Cells\TimeAgo::render', ['datetime' => $change['created_at']]) ?></td>
                                    <td class="fw-semibold"><?= esc($change['label']) ?></td>
                                    <td class="fs-13">
                                        <?php if ($change['action'] === 'reset'): ?>
                                            Reset to default
                                            <span class="text-muted">(was <?= esc($show($change['type'], $change['old_value'])) ?>)</span>
                                        <?php else: ?>
                                            <span class="text-muted"><?= esc($show($change['type'], $change['old_value'])) ?></span>
                                            &rarr; <?= esc($show($change['type'], $change['new_value'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap fs-13"><?= $change['changed_by'] ? 'user #' . esc($change['changed_by']) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
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

    if (!out) {
        return;
    }

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
