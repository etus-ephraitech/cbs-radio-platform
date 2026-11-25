<?php
/**
 * The shell for everything a visitor sees: the homepage, the booking flow
 * and the gift flow. (Staff screens use layouts/admin and layouts/main.)
 *
 * Two modes, chosen by the controller passing $lean:
 *
 *   lean = true   The homepage. No Bootstrap, no jQuery, no Toastr — just
 *                 this file's CSS and the page's own, so it loads fast on a
 *                 slow phone connection. Full-width sections; flash messages
 *                 show as a plain banner.
 *
 *   lean = false  The booking and gift pages, which are built with Bootstrap
 *                 components and use Toastr for messages. Content sits in a
 *                 centred container.
 *
 * Optional view variables: $fullTitle, $pageTitle, $metaDescription, and for the coming soon / maintenance page
 * (site_status): $minimal (no navigation and no links to pages that are closed) and $noindex (tell search engines
 * to leave the page out).
 */
$cbs   = config('CbsRadio');
$lean  = $lean ?? false;
$minimal = $minimal ?? false;
$noindex = $noindex ?? false;
$title = $fullTitle ?? (isset($pageTitle) ? $pageTitle . ' — CBS Radio' : 'CBS Radio');
$desc  = $metaDescription ?? '';
$home  = site_url('/');
$font  = static fn (string $file): string => base_url('assets/fonts/' . $file);

$hasContact = $cbs->contactPhone !== '' || $cbs->contactEmail !== '';

$notice = array_filter([
    'error'   => session()->getFlashdata('toastr_error'),
    'success' => session()->getFlashdata('toastr_success'),
]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($noindex): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <title><?= esc($title) ?></title>
    <?php if ($desc !== ''): ?>
        <meta name="description" content="<?= esc($desc) ?>">
    <?php endif; ?>
    <meta name="theme-color" content="#0E1B3D">
    <?php if (! $noindex): ?>
        <link rel="canonical" href="<?= esc(current_url()) ?>">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="CBS Radio">
        <meta property="og:title" content="<?= esc($title) ?>">
        <?php if ($desc !== ''): ?>
            <meta property="og:description" content="<?= esc($desc) ?>">
        <?php endif; ?>
        <meta property="og:url" content="<?= esc(current_url()) ?>">
    <?php endif; ?>

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230E1B3D'/%3E%3Ccircle cx='16' cy='17' r='4' fill='%23D62828'/%3E%3Cpath d='M8 17a8 8 0 0 1 16 0M4 17a12 12 0 0 1 24 0' fill='none' stroke='%23F5A800' stroke-width='2.4' stroke-linecap='round'/%3E%3C/svg%3E">

    <!-- The two faces used for the headline and for reading text, served from
         this site (no third-party request). Only the weights actually used. -->
    <link rel="preload" href="<?= esc($font('bricolage-grotesque-latin-800-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= esc($font('atkinson-hyperlegible-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>

    <?php if (! $lean): ?>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">
    <?php endif; ?>

    <style>
        @font-face { font-family: 'Bricolage Grotesque'; font-weight: 700; font-style: normal; font-display: swap;
            src: url('<?= $font('bricolage-grotesque-latin-700-normal.woff2') ?>') format('woff2'); }
        @font-face { font-family: 'Bricolage Grotesque'; font-weight: 800; font-style: normal; font-display: swap;
            src: url('<?= $font('bricolage-grotesque-latin-800-normal.woff2') ?>') format('woff2'); }
        @font-face { font-family: 'Atkinson Hyperlegible'; font-weight: 400; font-style: normal; font-display: swap;
            src: url('<?= $font('atkinson-hyperlegible-latin-400-normal.woff2') ?>') format('woff2'); }
        @font-face { font-family: 'Atkinson Hyperlegible'; font-weight: 700; font-style: normal; font-display: swap;
            src: url('<?= $font('atkinson-hyperlegible-latin-700-normal.woff2') ?>') format('woff2'); }

        :root {
            --ink: #0E1B3D;        /* deep navy: text, hero, footer */
            --panel: #17306E;      /* lifted navy: the radio body */
            --gold: #F5A800;       /* the action colour: buttons, the amber dial glass */
            --lamp: #D62828;       /* on-air red: the lamp and live states only */
            --mist: #EDF1F9;       /* tinted section background */
            --line: #D5DDEE;       /* hairlines on light backgrounds */
            --muted: #46557A;      /* secondary text on light backgrounds */
            --display: 'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif;
            --body: 'Atkinson Hyperlegible', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            --focus: var(--ink);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }

        body {
            margin: 0;
            font-family: var(--body);
            font-size: 1.0625rem;
            line-height: 1.65;
            color: var(--ink);
            background: #fff;
        }
        h1, h2, h3, h4 { font-family: var(--display); font-weight: 800; line-height: 1.1; letter-spacing: -0.02em; }
        a { color: inherit; }

        :focus-visible { outline: 3px solid var(--focus); outline-offset: 3px; border-radius: 4px; }
        .on-dark :focus-visible, .cbs-header :focus-visible, .cbs-footer :focus-visible { --focus: var(--gold); }

        .skip-link { position: absolute; left: -999px; top: 0; background: var(--gold); color: var(--ink);
            padding: .75rem 1.25rem; font-weight: 700; z-index: 100; }
        .skip-link:focus { left: .75rem; top: .75rem; }

        .cbs-wrap { width: min(1140px, 100% - 2.5rem); margin-inline: auto; }

        /* ---------- announcement ---------- */
        .cbs-announce { background: var(--gold); color: var(--ink); text-align: center; font-weight: 700;
            padding: .6rem 1.25rem; font-size: .975rem; line-height: 1.4; }
        .cbs-announce--staff { background: var(--lamp); color: #fff; }
        .cbs-announce--staff a { color: #fff; margin-left: .5rem; text-underline-offset: 3px; }

        /* ---------- header ---------- */
        .cbs-header { background: var(--ink); color: #fff; }
        .cbs-header .cbs-wrap { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 4.5rem; }
        .cbs-brand { display: inline-flex; align-items: center; gap: .6rem; text-decoration: none;
            font-family: var(--display); font-weight: 800; font-size: 1.45rem; letter-spacing: -0.02em; color: #fff; }
        .cbs-lamp { width: .7rem; height: .7rem; border-radius: 50%; background: var(--lamp); box-shadow: 0 0 0 3px rgba(214, 40, 40, .28); flex: none; }
        .cbs-nav { display: flex; align-items: center; gap: 1.75rem; }
        .cbs-nav a:not(.cbs-btn) { color: #D6DEF2; text-decoration: none; font-weight: 700; padding: .5rem 0; }
        .cbs-nav a:not(.cbs-btn):hover { color: #fff; text-decoration: underline; text-underline-offset: 6px; }
        .cbs-menu-btn { display: none; background: transparent; color: #fff; border: 2px solid rgba(255,255,255,.45);
            border-radius: 999px; padding: .5rem 1.1rem; font: inherit; font-weight: 700; min-height: 44px; cursor: pointer; }

        /* ---------- buttons ---------- */
        .cbs-btn { display: inline-flex; align-items: center; justify-content: center; gap: .55rem; min-height: 3.1rem;
            padding: .75rem 1.6rem; border-radius: 999px; border: 2px solid transparent; font: inherit; font-weight: 700;
            text-decoration: none; cursor: pointer; line-height: 1.2; }
        .cbs-btn--gold { background: var(--gold); color: var(--ink); }
        .cbs-btn--gold:hover { background: #FFB91F; }
        .cbs-btn--ghost { background: transparent; color: #fff; border-color: rgba(255,255,255,.55); }
        .cbs-btn--ghost:hover { border-color: #fff; background: rgba(255,255,255,.08); }
        .cbs-btn--ink { background: var(--ink); color: #fff; }
        .cbs-btn--ink:hover { background: var(--panel); }
        .cbs-btn--line { background: transparent; color: var(--ink); border-color: var(--ink); }
        .cbs-btn--line:hover { background: var(--ink); color: #fff; }
        .cbs-header .cbs-btn { min-height: 2.75rem; padding: .5rem 1.25rem; }

        /* ---------- notices (lean pages; the booking pages use Toastr) ---------- */
        .cbs-notice { padding: .85rem 1.25rem; text-align: center; font-weight: 700; }
        .cbs-notice--error { background: #FDE7E7; color: #7A1010; }
        .cbs-notice--success { background: #E2F5E9; color: #11522B; }

        /* ---------- footer ---------- */
        .cbs-footer { background: var(--ink); color: #D6DEF2; padding: 3.5rem 0 2rem; font-size: .975rem; }
        .cbs-footer .cbs-wrap { display: grid; gap: 2.5rem; grid-template-columns: 1.4fr 1fr 1fr; }
        .cbs-footer .cbs-wrap--two { grid-template-columns: 1.4fr 1fr; }
        .cbs-footer h2 { font-family: var(--display); font-size: 1rem; color: #fff; margin: 0 0 .75rem; letter-spacing: 0; }
        .cbs-footer ul { list-style: none; margin: 0; padding: 0; display: grid; gap: .4rem; }
        .cbs-footer a { color: #D6DEF2; }
        .cbs-footer a:hover { color: #fff; }
        .cbs-footer .cbs-brand { margin-bottom: .75rem; }
        .cbs-footer p { margin: 0; max-width: 34ch; }
        .cbs-fine { grid-column: 1 / -1; border-top: 1px solid rgba(255,255,255,.14); padding-top: 1.5rem;
            display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; justify-content: space-between; font-size: .875rem; color: #AEB9D6; }

        /* ---------- booking and gift pages (Bootstrap) ---------- */
        .cbs-page { padding-block: 2rem 4rem; }
        .page-header { border-bottom: 1px solid var(--line); margin-bottom: 1.5rem; padding-bottom: 1rem; }
        .page-header h1 { font-size: clamp(1.6rem, 4vw, 2.1rem); }
        .btn-cbs-primary { background: var(--gold); border-color: var(--gold); color: var(--ink); font-weight: 700; border-radius: 999px; padding-inline: 1.5rem; }
        .btn-cbs-primary:hover, .btn-cbs-primary:focus { background: #FFB91F; border-color: #FFB91F; color: var(--ink); }
        .cbs-page .display-6 { font-family: var(--display); font-weight: 800; letter-spacing: -0.02em; }
        .cbs-page h1, .cbs-page h2, .cbs-page .card-title { font-family: var(--display); }
        .category-card { border: 0; border-left: 4px solid var(--gold); background: #fff; border-radius: 10px; }
        .category-card:hover { background: #F7F9FD; }

        @media (max-width: 820px) {
            .cbs-menu-btn { display: inline-flex; align-items: center; }
            .cbs-nav { display: none; position: absolute; left: 0; right: 0; top: 100%; z-index: 20; background: var(--ink);
                flex-direction: column; align-items: stretch; gap: .25rem; padding: .75rem 1.25rem 1.5rem;
                border-top: 1px solid rgba(255,255,255,.14); }
            .cbs-nav.open { display: flex; }
            .cbs-nav a:not(.cbs-btn) { padding: .85rem 0; font-size: 1.1rem; }
            .cbs-header { position: relative; }
            .cbs-footer .cbs-wrap { grid-template-columns: 1fr; gap: 2rem; }
        }
    </style>
    <noscript><style>.cbs-menu-btn { display: none; } .cbs-nav { display: flex !important; position: static; flex-wrap: wrap; }</style></noscript>

    <?= $this->renderSection('head') ?>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <?php if ($cbs->announcement !== '' && ! $minimal): ?>
        <div class="cbs-announce" role="note"><?= esc($cbs->announcement) ?></div>
    <?php endif; ?>
    <?php if (! $minimal && \App\Libraries\SiteStatusPage::closes($cbs->siteMode)): ?>
        <?php /* Only staff can reach a public page while it is closed to visitors, so this is only ever seen by them. */ ?>
        <div class="cbs-announce cbs-announce--staff" role="note">
            Staff view: the site is
            <?= $cbs->siteMode === 'maintenance' ? 'under maintenance' : 'set to coming soon' ?>
            and visitors cannot see this page.
            <a href="<?= site_url('admin/settings/status') ?>">Change</a>
        </div>
    <?php endif; ?>

    <header class="cbs-header">
        <div class="cbs-wrap">
            <a class="cbs-brand" href="<?= esc($home) ?>"><span class="cbs-lamp" aria-hidden="true"></span>CBS Radio</a>

            <?php if (! $minimal): ?>
                <button class="cbs-menu-btn" id="menuBtn" type="button" aria-expanded="false" aria-controls="siteNav">Menu</button>

                <nav class="cbs-nav" id="siteNav" aria-label="Main">
                    <a href="<?= esc($home) ?>#greetings">Greetings</a>
                    <a href="<?= esc($home) ?>#how">How it works</a>
                    <a href="<?= esc($home) ?>#gifts">Gifts</a>
                    <a href="<?= esc($home) ?>#faq">Questions</a>
                    <a class="cbs-btn cbs-btn--gold" href="<?= site_url('booking') ?>">Send a greeting</a>
                </nav>
            <?php endif; ?>
        </div>
    </header>

    <?php foreach ($lean ? $notice : [] as $kind => $message): ?>
        <div class="cbs-notice cbs-notice--<?= esc($kind) ?>" role="status"><?= esc($message) ?></div>
    <?php endforeach; ?>

    <?php if ($lean): ?>
        <main id="main"><?= $this->renderSection('content') ?></main>
    <?php else: ?>
        <main id="main" class="container cbs-page"><?= $this->renderSection('content') ?></main>
    <?php endif; ?>

    <footer class="cbs-footer">
        <div class="cbs-wrap<?= ($hasContact && ! $minimal) ? '' : ' cbs-wrap--two' ?>">
            <div>
                <a class="cbs-brand" href="<?= esc($home) ?>"><span class="cbs-lamp" aria-hidden="true"></span>CBS Radio</a>
                <p>Greetings, tributes and gifts, read out live on CBS Radio.</p>
            </div>
            <?php if (! $minimal): ?>
                <div>
                    <h2>Send</h2>
                    <ul>
                        <li><a href="<?= site_url('booking') ?>">A greeting</a></li>
                        <li><a href="<?= site_url('gifts') ?>">A gift</a></li>
                        <li><a href="<?= esc($home) ?>#faq">Questions and answers</a></li>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($hasContact): ?>
                <div>
                    <h2>Get in touch</h2>
                    <ul>
                        <?php if ($cbs->contactPhone !== ''): ?>
                            <li>Call <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $cbs->contactPhone)) ?>"><?= esc($cbs->contactPhone) ?></a></li>
                        <?php endif; ?>
                        <?php if ($cbs->contactEmail !== ''): ?>
                            <li>Email <a href="mailto:<?= esc($cbs->contactEmail) ?>"><?= esc($cbs->contactEmail) ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="cbs-fine">
                <span>&copy; <?= date('Y') ?> CBS Radio. Built by Ephraitech Unified Solutions.</span>
                <a href="<?= site_url('admin') ?>">Staff</a>
            </div>
        </div>
    </footer>

    <script>
    (function () {
        var button = document.getElementById('menuBtn'), nav = document.getElementById('siteNav');
        if (!button || !nav) { return; }
        button.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();
    </script>

    <?php if (! $lean): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
        <?= view_cell('App\Cells\ToastrFlash::render') ?>
    <?php endif; ?>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
