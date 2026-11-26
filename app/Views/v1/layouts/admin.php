<?php
/**
 * The v1 admin shell (dashboard theme): header, side menu, footer, the
 * theme's settings panel and its scripts. Every v1 admin and presenter
 * screen extends it with `$this->extend(ui_layout('admin'))`.
 *
 * Sections a screen can fill:
 *   title    the page's name, used in the browser tab ("Bookings — CBS Radio Admin")
 *   head     extra <head> content (rarely needed: page styles belong in assets/css/admin.css)
 *   content  the page itself
 *   scripts  page scripts, run after the theme, jQuery, Toastr and SweetAlert2 have loaded
 *
 * Section names match the original admin layout's, so a screen's sections mean the same in both UIs.
 */
$pageTitle = trim(strip_tags($this->renderSection('title')));
$this->setVar('documentTitle', $pageTitle === '' ? 'CBS Radio Admin' : $pageTitle . ' — CBS Radio Admin');

$asset = static fn (string $path): string => esc(base_url('assets/' . ltrim($path, '/')), 'attr');
?>
<?= $this->include(ui_view_path('layouts/partials/head')) ?>
    <?= $this->renderSection('head') ?>
</head>

<body>
    <?= cbs_environment_banner(true) ?>
    <div class="progress-top-bar"></div>

    <?= $this->include(ui_view_path('layouts/partials/switcher')) ?>
    <?= $this->include(ui_view_path('layouts/partials/loader')) ?>

    <div class="page">
        <?= $this->include(ui_view_path('layouts/partials/header')) ?>
        <?= $this->include(ui_view_path('layouts/partials/sidebar')) ?>

        <div class="main-content app-content">
            <div class="container-fluid page-container main-body-container">
                <?php $siteMode = config('CbsRadio')->siteMode; ?>
                <?php if (\App\Libraries\SiteStatusPage::closes($siteMode)): ?>
                    <div class="alert alert-warning d-flex justify-content-between align-items-center py-2 small" role="status">
                        <span>
                            <strong>The public site is <?= $siteMode === 'maintenance' ? 'under maintenance' : 'set to coming soon' ?>.</strong>
                            Visitors cannot book or send gifts. You can still use everything.
                        </span>
                        <a href="<?= site_url('admin/settings/status') ?>" class="alert-link ms-3 text-nowrap">Change</a>
                    </div>
                <?php endif; ?>
                <?= $this->renderSection('content') ?>
            </div>
        </div>

        <?= $this->include(ui_view_path('layouts/partials/footer')) ?>

        <div class="modal fade" id="header-responsive-search" tabindex="-1" aria-labelledby="header-responsive-search-label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <form method="get" action="<?= site_url('admin/bookings') ?>" role="search">
                            <label id="header-responsive-search-label" for="header-responsive-search-input" class="form-label fw-semibold">Search bookings</label>
                            <div class="input-group">
                                <input type="search" name="q" id="header-responsive-search-input" class="form-control border-end-0"
                                    placeholder="Reference, name, phone or message">
                                <button class="btn btn-primary" type="submit" aria-label="Search"><i class="bi bi-search" aria-hidden="true"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="scrollToTop">
        <span class="arrow lh-1"><i class="ti ti-caret-up fs-20" aria-hidden="true"></i></span>
    </div>
    <div id="responsive-overlay"></div>

    <script src="<?= $asset('libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= $asset('js/defaultmenu.min.js') ?>"></script>
    <script src="<?= $asset('libs/node-waves/waves.min.js') ?>"></script>
    <script src="<?= $asset('js/sticky.js') ?>"></script>
    <script src="<?= $asset('libs/simplebar/simplebar.min.js') ?>"></script>
    <script src="<?= $asset('js/simplebar.js') ?>"></script>
    <script src="<?= $asset('libs/@simonwep/pickr/pickr.es5.min.js') ?>"></script>
    <script src="<?= $asset('libs/flatpickr/flatpickr.min.js') ?>"></script>
    <script src="<?= $asset('js/custom-switcher.min.js') ?>"></script>
    <script src="<?= $asset('js/custom.js') ?>"></script>

    <!-- Toastr needs jQuery; both are what the flash messages and the original admin already use. -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
    <script src="<?= $asset('libs/sweetalert2/sweetalert2.min.js') ?>"></script>

    <?= view_cell('App\Cells\ToastrFlash::render') ?>
    <?= $this->renderSection('scripts') ?>
</body>

</html>
