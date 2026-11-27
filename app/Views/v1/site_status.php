<?= $this->extend('layouts/public') ?>

<?php
/**
 * What a visitor sees while the public site is "coming soon" or "under maintenance"
 * (Admin > Settings > Site status). Rendered by App\Libraries\SiteStatusPage.
 *
 * Variables: $mode, $copy (headline, kicker, when), $message, $returnText, $returnIso, $returnPast, $preview, $cbs.
 *
 * Nothing here needs JavaScript: the date is written out in full. The countdown is an enhancement on top of it.
 * The on-air lamp on the dial is dark in both modes, because the station is not taking greetings; it lights up
 * on the live homepage.
 */
$isMaintenance = $mode === 'maintenance';
$hasContact    = $cbs->contactPhone !== '' || $cbs->contactEmail !== '';

if ($returnText === '') {
    $whenText = '';
} elseif ($returnPast) {
    $whenText = $isMaintenance ? "Taking a little longer than planned" : 'Any moment now';
} else {
    $whenText = $returnText;
}

// The tick marks of the dial: every 20 degrees across a 240 degree sweep, longer at the ends and the middle.
$ticks = [];
for ($angle = -120; $angle <= 120; $angle += 20) {
    $long    = $angle % 120 === 0 || $angle === 0;
    $radians = deg2rad($angle - 90);
    $inner   = $long ? 82 : 88;
    $ticks[] = [
        round(120 + $inner * cos($radians), 2), round(120 + $inner * sin($radians), 2),
        round(120 + 98 * cos($radians), 2), round(120 + 98 * sin($radians), 2),
        $long,
    ];
}
?>

<?= $this->section('head') ?>
<style>
    .cbs-status { background: var(--ink); color: #fff; padding: clamp(3rem, 9vw, 6rem) 0; min-height: 60vh; display: grid; align-items: center; }
    .cbs-status .cbs-wrap { display: grid; gap: 3rem; grid-template-columns: minmax(0, 1.3fr) minmax(0, .7fr); align-items: center; }
    .cbs-status-preview { background: var(--gold); color: var(--ink); font-weight: 700; padding: .7rem 1rem; border-radius: .5rem; margin: 0 0 2rem; font-size: .95rem; }
    .cbs-status-preview a { color: var(--ink); }
    .cbs-status-kicker { display: inline-flex; align-items: center; gap: .65rem; color: var(--gold); font-weight: 700; letter-spacing: .1em; text-transform: uppercase; font-size: .85rem; }
    .cbs-status h1 { color: #fff; font-size: clamp(2.75rem, 8vw, 5rem); margin: .5rem 0 1.25rem; }
    .cbs-status-message { font-size: 1.2rem; color: #D6DEF2; max-width: 36rem; margin: 0 0 2rem; }
    .cbs-status-when { display: inline-block; border-left: 4px solid var(--gold); padding: .25rem 0 .25rem 1rem; margin: 0 0 1.75rem; }
    .cbs-status-when span { display: block; color: #AEBBDD; font-size: .85rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .cbs-status-when strong { font-family: var(--display); font-size: 1.5rem; color: #fff; }
    .cbs-countdown { color: var(--gold); font-weight: 700; font-size: 1.15rem; margin: -.75rem 0 1.75rem; }
    .cbs-status-contact { color: #D6DEF2; margin: 0; }
    .cbs-status-contact a { color: #fff; font-weight: 700; text-underline-offset: 4px; }
    .cbs-status-dial svg { display: block; width: 100%; max-width: 21rem; margin-inline: auto; }
    .cbs-needle { transform-origin: 120px 120px; transform: rotate(-120deg); }
    .cbs-needle--sweep { animation: cbs-sweep 9s ease-in-out infinite alternate; }
    @keyframes cbs-sweep { from { transform: rotate(-95deg); } to { transform: rotate(70deg); } }
    @media (prefers-reduced-motion: reduce) { .cbs-needle--sweep { animation: none; transform: rotate(-40deg); } }
    @media (max-width: 820px) {
        .cbs-status .cbs-wrap { grid-template-columns: 1fr; gap: 2.5rem; }
        .cbs-status-dial { order: -1; }
        .cbs-status-dial svg { max-width: 11rem; }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="cbs-status" aria-labelledby="statusTitle">
    <div class="cbs-wrap">
        <div>
            <?php if ($preview): ?>
                <p class="cbs-status-preview" role="note">
                    Staff preview: this is what visitors see while the status is
                    &ldquo;<?= $isMaintenance ? 'Under maintenance' : 'Coming soon' ?>&rdquo;.
                    <a href="<?= site_url('admin/settings/status') ?>">Back to settings</a>
                </p>
            <?php endif; ?>

            <span class="cbs-status-kicker"><span class="cbs-lamp" aria-hidden="true"></span><?= esc($copy['kicker']) ?></span>
            <h1 id="statusTitle"><?= esc($copy['headline']) ?></h1>
            <p class="cbs-status-message"><?= nl2br(esc($message)) ?></p>

            <?php if ($whenText !== ''): ?>
                <div class="cbs-status-when">
                    <span><?= esc($copy['when']) ?></span>
                    <strong><?= esc($whenText) ?></strong>
                </div>
            <?php endif; ?>

            <?php if (! $isMaintenance && $returnIso !== '' && ! $returnPast): ?>
                <p class="cbs-countdown" id="countdown" data-until="<?= esc($returnIso, 'attr') ?>" hidden></p>
            <?php endif; ?>

            <?php if ($hasContact): ?>
                <p class="cbs-status-contact">
                    Need to reach us?
                    <?php if ($cbs->contactPhone !== ''): ?>
                        Call <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $cbs->contactPhone)) ?>"><?= esc($cbs->contactPhone) ?></a>
                    <?php endif; ?>
                    <?php if ($cbs->contactPhone !== '' && $cbs->contactEmail !== ''): ?>or<?php endif; ?>
                    <?php if ($cbs->contactEmail !== ''): ?>
                        email <a href="mailto:<?= esc($cbs->contactEmail) ?>"><?= esc($cbs->contactEmail) ?></a>
                    <?php endif; ?>.
                </p>
            <?php endif; ?>
        </div>

        <div class="cbs-status-dial" aria-hidden="true">
            <svg viewBox="0 0 240 240" role="presentation" focusable="false">
                <circle cx="120" cy="120" r="112" fill="#17306E" stroke="#2A4A98" stroke-width="3"/>
                <circle cx="120" cy="120" r="100" fill="#0E1B3D"/>
                <?php foreach ($ticks as [$x1, $y1, $x2, $y2, $long]): ?>
                    <line x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>" stroke="<?= $long ? '#F5A800' : '#6E82B8' ?>" stroke-width="<?= $long ? 3 : 2 ?>" stroke-linecap="round"/>
                <?php endforeach; ?>
                <g class="cbs-needle<?= $isMaintenance ? '' : ' cbs-needle--sweep' ?>">
                    <line x1="120" y1="120" x2="120" y2="40" stroke="#F5A800" stroke-width="4" stroke-linecap="round"/>
                </g>
                <circle cx="120" cy="120" r="9" fill="#F5A800"/>
                <circle cx="120" cy="120" r="3.5" fill="#0E1B3D"/>
                <circle cx="120" cy="172" r="7" fill="#D62828" opacity=".28"/>
                <circle cx="120" cy="172" r="12" fill="none" stroke="#D62828" stroke-opacity=".25" stroke-width="2"/>
            </svg>
        </div>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var el = document.getElementById('countdown');
    if (!el) { return; }
    var until = Date.parse(el.getAttribute('data-until'));
    if (isNaN(until)) { return; }
    function plural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }
    function tick() {
        var left = Math.floor((until - Date.now()) / 1000);
        if (left <= 0) {
            el.textContent = 'Opening any moment now.';
            el.hidden = false;
            return;
        }
        var days = Math.floor(left / 86400), hours = Math.floor(left % 86400 / 3600), minutes = Math.floor(left % 3600 / 60);
        el.textContent = (days ? plural(days, 'day') + ', ' : '') + plural(hours, 'hour') + ', ' + plural(minutes, 'minute') + ' to go';
        el.hidden = false;
        setTimeout(tick, 30000);
    }
    tick();
})();
</script>
<?= $this->endSection() ?>
