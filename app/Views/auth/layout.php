<?php
/**
 * Shell for the staff account pages (ephraitech/ci4-auth renders them; Config\Auth points $viewLayout and $views
 * here). A brand panel on the left, the form on the right; on a phone the brand panel shrinks to a header.
 *
 * Self-contained: assets/css/auth.css and assets/js/auth.js, no framework. The pages fill the section named by
 * $authSection.
 *
 * @var \Ephraitech\Auth\Config\Auth $authConfig
 * @var string                       $authSection
 * @var string                       $pageTitle
 */
$asset   = static fn (string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');
$appName = $authConfig->appName !== '' ? $authConfig->appName : 'CBS Radio';
$cbs     = config('CbsRadio');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0E1B3D">
    <title><?= esc(($pageTitle ?? 'Account') . ' · ' . $appName . ' staff') ?></title>
    <link rel="icon" href="<?= esc(base_url('assets/images/brand-logos/toggle-logo.svg'), 'attr') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= esc($asset('assets/css/auth.css'), 'attr') ?>">
</head>

<body>
    <a class="au-skip" href="#auth-main">Skip to the form</a>
    <?= cbs_environment_banner() ?>

    <div class="au">
        <aside class="au-brand" aria-label="<?= esc($appName, 'attr') ?>">
            <a class="au-logo" href="<?= site_url('/') ?>">
                <img src="<?= esc(base_url('assets/images/brand-logos/toggle-dark.svg'), 'attr') ?>" alt="" width="40" height="40">
                <?= esc($appName) ?> <span>STAFF</span>
            </a>

            <div class="au-pitch">
                <p class="au-onair" style="margin:0 0 1.25rem"><i aria-hidden="true"></i>Studio access</p>
                <h2>Keep every greeting on air.</h2>
                <p>Review messages, run the presenter queue and confirm payments and gifts, all in one place.</p>
                <ul class="au-points">
                    <li>
                        <span class="au-ic"><?= cbs_icon_svg('shield', 1.15) ?></span>
                        <span><strong>Moderate before it airs</strong>Approve, flag or reject each greeting.</span>
                    </li>
                    <li>
                        <span class="au-ic"><?= cbs_icon_svg('mic', 1.15) ?></span>
                        <span><strong>Presenter queue</strong>Today's greetings for each show, ready to read.</span>
                    </li>
                    <li>
                        <span class="au-ic"><?= cbs_icon_svg('gift', 1.15) ?></span>
                        <span><strong>Payments and gifts</strong>Confirm Mobile Money and send gifts on their way.</span>
                    </li>
                </ul>
            </div>

            <p class="au-foot-note">
                Looking to send a greeting? <a href="<?= site_url('/') ?>">Go to the public site</a>.
                <?php if ($cbs->contactPhone !== ''): ?><br>Locked out? Call <?= esc($cbs->contactPhone) ?>.<?php endif; ?>
            </p>
        </aside>

        <main class="au-main" id="auth-main">
            <?= $this->renderSection($authSection) ?>
        </main>
    </div>

    <script src="<?= esc($asset('assets/js/auth.js'), 'attr') ?>" defer></script>
</body>

</html>
