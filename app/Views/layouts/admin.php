<?php

/**
 * Admin shell: top bar + grouped sidebar. Every Admin\* controller's
 * views extend this instead of layouts/main (the public booking shell),
 * so internal tools never share navigation with the public site.
 *
 * $activeNav marks the current sidebar item — each view can set it with
 * `<?php $activeNav = 'payments'; ?>` before extending, but the sidebar
 * also auto-detects from the current URL as a fallback, so views that
 * don't set it still highlight correctly.
 */
$uri = service('request')->getUri();
$currentPath = '/' . trim($uri->getPath(), '/');

$isActive = static function (string $segment) use ($currentPath): string {
    // 'admin' alone (the dashboard) must match exactly; every other
    // section matches by prefix so /admin/payments and
    // /admin/payments?status=pending both light up "Payments".
    if ($segment === 'admin') {
        return $currentPath === '/admin' ? 'active' : '';
    }

    return str_starts_with($currentPath, '/admin/' . $segment) ? 'active' : '';
};
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? esc($pageTitle) . ' — CBS Radio Admin' : 'CBS Radio Admin' ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">

    <style>
        :root {
            --cbs-navy: #101B33;
            --cbs-navy-deep: #0A1226;
            --cbs-on-air: #D62828;
            --cbs-gold: #F4A300;
            --cbs-paper: #F5F6F8;
            --cbs-ink: #1B1B1F;
            --sidebar-width: 240px;
        }

        body {
            background: var(--cbs-paper);
            color: var(--cbs-ink);
        }

        h1,
        h2,
        h3,
        h4,
        .brand-font {
            font-family: 'Space Grotesk', system-ui, sans-serif;
        }

        .admin-shell {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: var(--sidebar-width);
            background: var(--cbs-navy);
            color: rgba(255, 255, 255, .8);
            flex-shrink: 0;
            padding: 1.25rem 0;
        }

        .admin-sidebar .brand {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            font-weight: 700;
            color: #fff;
            padding: 0 1.25rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, .1);
            margin-bottom: 1rem;
            display: block;
            text-decoration: none;
        }

        .on-air-tag {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: var(--cbs-on-air);
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
            padding: .15rem .45rem;
            border-radius: 3px;
            letter-spacing: .04em;
            margin-left: .35rem;
        }

        .on-air-tag::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #fff;
        }

        .nav-group-label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(255, 255, 255, .45);
            padding: .75rem 1.25rem .35rem;
        }

        .admin-sidebar .nav-link {
            color: rgba(255, 255, 255, .78);
            padding: .45rem 1.25rem;
            border-left: 3px solid transparent;
            font-size: .92rem;
        }

        .admin-sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, .05);
        }

        .admin-sidebar .nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, .08);
            border-left-color: var(--cbs-gold);
        }

        .admin-main {
            flex: 1;
            min-width: 0;
        }

        .admin-topbar {
            background: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, .08);
            padding: .6rem 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            font-size: .85rem;
        }

        .admin-content {
            padding: 1.5rem;
        }

        .btn-cbs-primary {
            background: var(--cbs-navy);
            border-color: var(--cbs-navy);
            color: #fff;
        }

        .btn-cbs-primary:hover {
            background: var(--cbs-navy-deep);
            border-color: var(--cbs-navy-deep);
            color: #fff;
        }

        .page-header {
            border-bottom: 1px solid rgba(0, 0, 0, .08);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
        }

        .stat-card {
            border: none;
            border-left: 4px solid var(--cbs-gold);
            background: #fff;
            border-radius: 6px;
        }

        .stat-card.urgent {
            border-left-color: var(--cbs-on-air);
        }

        @media (max-width: 991.98px) {
            .admin-shell {
                flex-direction: column;
            }

            .admin-sidebar {
                width: 100%;
                padding: .75rem 0;
            }
        }
    </style>

    <?= $this->renderSection('head') ?>
</head>

<body>
    <?= cbs_environment_banner() ?>

    <div class="admin-shell">
        <aside class="admin-sidebar">
            <?php $brand = cbs_brand(); ?>
            <a href="<?= site_url('admin') ?>" class="brand"><?php if ($brand['logo'] !== ''): ?><img src="<?= esc($brand['logo'], 'attr') ?>" alt="<?= esc($brand['alt'], 'attr') ?>" style="height:34px;width:auto;max-width:100%;background:#fff;border-radius:6px;padding:3px 6px"> <span class="on-air-tag">ADMIN</span><?php else: ?>CBS Radio <span class="on-air-tag">ADMIN</span><?php endif; ?></a>

            <nav class="nav flex-column">
                <a class="nav-link <?= $isActive('admin') ?>" href="<?= site_url('admin') ?>">Dashboard</a>

                <div class="nav-group-label">Operations</div>
                <a class="nav-link <?= $isActive('bookings') ?>" href="<?= site_url('admin/bookings') ?>">Bookings</a>
                <a class="nav-link <?= $isActive('moderation') ?>" href="<?= site_url('admin/moderation') ?>">Moderation Queue</a>
                <a class="nav-link <?= $isActive('payments') ?>" href="<?= site_url('admin/payments') ?>">Payments</a>
                <a class="nav-link <?= $isActive('gift-orders') ?>" href="<?= site_url('admin/gift-orders') ?>">Gift Orders</a>
                <a class="nav-link <?= $isActive('gift-purchases') ?>" href="<?= site_url('admin/gift-purchases') ?>">Shop Purchases</a>
                <a class="nav-link <?= $isActive('notifications') ?>" href="<?= site_url('admin/notifications') ?>">Notifications</a>

                <div class="nav-group-label">Catalog</div>
                <a class="nav-link <?= $isActive('categories') ?>" href="<?= site_url('admin/categories') ?>">Categories</a>
                <a class="nav-link <?= $isActive('rate-tiers') ?>" href="<?= site_url('admin/rate-tiers') ?>">Packages</a>
                <a class="nav-link <?= $isActive('shows') ?>" href="<?= site_url('admin/shows') ?>">Shows</a>
                <a class="nav-link <?= $isActive('gifts') ?>" href="<?= site_url('admin/gifts') ?>">Gifts</a>
                <a class="nav-link <?= $isActive('gift-vendors') ?>" href="<?= site_url('admin/gift-vendors') ?>">Gift Vendors</a>

                <div class="nav-group-label">System</div>
                <a class="nav-link <?= $isActive('settings') ?>" href="<?= site_url('admin/settings') ?>">Settings</a>
                <?php if (cbs_is_superadmin()): ?>
                    <a class="nav-link <?= $isActive('users') ?>" href="<?= site_url('admin/users') ?>">Users</a>
                    <a class="nav-link <?= $isActive('branding') ?>" href="<?= site_url('admin/branding') ?>">Logo</a>
                <?php endif; ?>
            </nav>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <form method="post" action="<?= url_to('auth.logout') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-link">Sign out</button>
                </form>
                <a href="<?= site_url('presenter') ?>" class="text-decoration-none">Presenter portal</a>
                <a href="<?= site_url('/') ?>" class="text-decoration-none">View public site &nearr;</a>
            </div>

            <div class="admin-content">
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
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.1/sweetalert2.all.min.js"></script>

    <?= view_cell('App\Cells\ToastrFlash::render') ?>

    <?= $this->renderSection('scripts') ?>
</body>

</html>