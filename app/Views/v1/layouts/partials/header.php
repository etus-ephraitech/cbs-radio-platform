<?php
/**
 * Top bar of the v1 admin: brand (horizontal menu only), sidebar toggle,
 * booking search, colour-mode toggle, full screen, the signed-in staff
 * member's menu and the theme switcher.
 *
 * The search is a plain GET form onto the bookings list, which already
 * searches by reference, name, phone and message.
 */
helper('auth');

$staff = null;

try {
    $staff = function_exists('user') ? user() : null;
} catch (Throwable) {
    $staff = null; // the header must render even if the auth guard can't
}

$staffName  = $staff?->getUsername() ?: ($staff?->getEmail() ?: 'Staff');
$staffEmail = $staff?->getEmail() ?? '';
$initials   = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $staffName) ?: 'S', 0, 2));
$search     = (string) (service('request')->getGet('q') ?? '');
$logo       = static fn (string $file): string => esc(base_url('assets/images/brand-logos/' . $file), 'attr');
$icon       = static fn (string $path): string => '<svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="' . $path . '"/></svg>';
?>
<header class="app-header sticky" id="header">
    <div class="main-header-container container-fluid px-0">

        <div class="header-content-left">
            <div class="header-element">
                <div class="horizontal-logo">
                    <a href="<?= site_url('admin') ?>" class="header-logo" aria-label="CBS Radio admin dashboard">
                        <img src="<?= $logo('desktop-logo.svg') ?>" alt="CBS Radio" class="desktop-logo">
                        <img src="<?= $logo('toggle-logo.svg') ?>" alt="CBS Radio" class="toggle-logo">
                        <img src="<?= $logo('desktop-dark.svg') ?>" alt="CBS Radio" class="desktop-dark">
                        <img src="<?= $logo('toggle-dark.svg') ?>" alt="CBS Radio" class="toggle-dark">
                    </a>
                </div>
            </div>

            <div class="header-element mx-lg-0 mx-2">
                <a aria-label="Hide sidebar" class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle"
                    data-bs-toggle="sidebar" href="javascript:void(0);"><span></span></a>
            </div>

            <div class="header-element header-search d-md-block d-none my-auto">
                <form method="get" action="<?= site_url('admin/bookings') ?>" role="search">
                    <label for="header-search" class="visually-hidden">Search bookings</label>
                    <input type="search" name="q" class="header-search-bar form-control" id="header-search"
                        value="<?= esc($search, 'attr') ?>" placeholder="Search bookings: reference, name, phone…"
                        spellcheck="false" autocomplete="off" autocapitalize="off">
                    <button type="submit" class="header-search-icon border-0 bg-transparent" aria-label="Search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </div>

        <ul class="header-content-right">
            <li class="header-element d-md-none d-block">
                <a href="javascript:void(0);" class="header-link" data-bs-toggle="modal" data-bs-target="#header-responsive-search" aria-label="Search bookings">
                    <?= $icon('M17 17L21 21M19 11C19 6.58 15.42 3 11 3C6.58 3 3 6.58 3 11C3 15.42 6.58 19 11 19C15.42 19 19 15.42 19 11Z') ?>
                </a>
            </li>

            <li class="header-element header-theme-mode">
                <a href="javascript:void(0);" class="header-link layout-setting" aria-label="Switch light or dark mode">
                    <span class="light-layout"><?= $icon('M21.5 14.078A8.557 8.557 0 0 1 9.922 2.5C5.668 3.497 2.5 7.315 2.5 11.873a9.627 9.627 0 0 0 9.627 9.627c4.558 0 8.376-3.168 9.373-7.422') ?></span>
                    <span class="dark-layout"><?= $icon('M17 12a5 5 0 1 1-10 0a5 5 0 0 1 10 0M12 2v1.5m0 17V22m7.07-2.929l-1.06-1.06M5.99 5.989L4.928 4.93M22 12h-1.5m-17 0H2m17.071-7.071l-1.06 1.06M5.99 18.011l-1.06 1.06') ?></span>
                </a>
            </li>

            <li class="header-element header-fullscreen">
                <a onclick="openFullscreen();" href="javascript:void(0);" class="header-link" aria-label="Full screen">
                    <svg xmlns="http://www.w3.org/2000/svg" class="full-screen-open header-link-icon" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8V5a2 2 0 0 1 2-2h3m8 0h3a2 2 0 0 1 2 2v3m0 8v3a2 2 0 0 1-2 2h-3m-8 0H5a2 2 0 0 1-2-2v-3"/></svg>
                    <svg xmlns="http://www.w3.org/2000/svg" class="full-screen-close header-link-icon d-none" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/></svg>
                </a>
            </li>

            <li class="header-element dropdown">
                <a href="javascript:void(0);" class="header-link dropdown-toggle" id="mainHeaderProfile"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Account menu">
                    <span class="avatar avatar-sm avatar-rounded bg-primary-transparent fw-semibold"><?= esc($initials) ?></span>
                </a>
                <ul class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end" aria-labelledby="mainHeaderProfile">
                    <li class="p-3 border-bottom">
                        <div class="fw-semibold"><?= esc($staffName) ?></div>
                        <?php if ($staffEmail !== '' && $staffEmail !== $staffName): ?>
                            <div class="text-muted fs-12"><?= esc($staffEmail) ?></div>
                        <?php endif; ?>
                    </li>
                    <li><a class="dropdown-item d-flex align-items-center" href="<?= site_url('presenter') ?>"><i class="ri-mic-line me-2 fs-16" aria-hidden="true"></i>Presenter portal</a></li>
                    <li><a class="dropdown-item d-flex align-items-center" href="<?= site_url('admin/settings') ?>"><i class="ri-settings-3-line me-2 fs-16" aria-hidden="true"></i>Settings</a></li>
                    <li><a class="dropdown-item d-flex align-items-center" href="<?= site_url('/') ?>" target="_blank" rel="noopener"><i class="ri-external-link-line me-2 fs-16" aria-hidden="true"></i>View public site</a></li>
                    <li class="border-top">
                        <form method="post" action="<?= url_to('auth.logout') ?>" class="m-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item d-flex align-items-center text-danger"><i class="ri-logout-box-r-line me-2 fs-16" aria-hidden="true"></i>Sign out</button>
                        </form>
                    </li>
                </ul>
            </li>

            <li class="header-element">
                <a href="javascript:void(0);" class="header-link switcher-icon" data-bs-toggle="offcanvas" data-bs-target="#switcher-canvas" aria-label="Theme settings">
                    <?= $icon('M12 15.5a3.5 3.5 0 1 0 0-7a3.5 3.5 0 0 0 0 7m7.4-2.1l1.4 1.1l-1.5 2.6l-1.7-.7a8 8 0 0 1-2 1.2l-.3 1.8h-3l-.3-1.8a8 8 0 0 1-2-1.2l-1.7.7l-1.5-2.6l1.4-1.1a7 7 0 0 1 0-2.8L4.6 9.5l1.5-2.6l1.7.7a8 8 0 0 1 2-1.2l.3-1.8h3l.3 1.8a8 8 0 0 1 2 1.2l1.7-.7l1.5 2.6l-1.4 1.1a7 7 0 0 1 0 2.8') ?>
                </a>
            </li>
        </ul>

    </div>
</header>
