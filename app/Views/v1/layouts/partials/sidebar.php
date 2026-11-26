<?php
/**
 * Side menu of the v1 admin. The menu is the $menu list below; the current
 * page is marked active here on the server (by URL prefix, so a booking's
 * own page still lights up "Bookings"), not left to the theme's script,
 * which only matches exact URLs.
 *
 * Icons are the symbols in the sprite below, drawn with currentColor so
 * they follow the theme's menu colours in light and dark mode.
 */
$currentPath = trim(uri_string(), '/');

$isActive = static function (string $path) use ($currentPath): bool {
    if ($path === 'admin') {
        return $currentPath === 'admin';
    }

    return $currentPath === $path || str_starts_with($currentPath, $path . '/');
};

$menu = [
    ['link' => 'admin', 'label' => 'Dashboard', 'icon' => 'dashboard'],
    ['category' => 'Operations'],
    ['link' => 'admin/bookings', 'label' => 'Bookings', 'icon' => 'bookings'],
    ['link' => 'admin/moderation', 'label' => 'Moderation queue', 'icon' => 'moderation'],
    ['link' => 'admin/payments', 'label' => 'Payments', 'icon' => 'payments'],
    ['link' => 'admin/gift-orders', 'label' => 'Gift orders', 'icon' => 'gift-orders'],
    ['link' => 'admin/gift-purchases', 'label' => 'Shop purchases', 'icon' => 'gift-orders'],
    ['link' => 'admin/notifications', 'label' => 'Notifications', 'icon' => 'notifications'],
    ['link' => 'presenter', 'label' => 'Presenter portal', 'icon' => 'presenter'],
    ['category' => 'Catalog'],
    ['link' => 'admin/categories', 'label' => 'Categories', 'icon' => 'categories'],
    ['link' => 'admin/rate-tiers', 'label' => 'Packages', 'icon' => 'packages'],
    ['link' => 'admin/shows', 'label' => 'Shows', 'icon' => 'shows'],
    ['link' => 'admin/message-templates', 'label' => 'Message templates', 'icon' => 'templates'],
    ['link' => 'admin/gifts', 'label' => 'Gifts', 'icon' => 'gifts'],
    ['link' => 'admin/gift-vendors', 'label' => 'Gift vendors', 'icon' => 'vendors'],
    ['category' => 'System'],
    ['link' => 'admin/settings', 'label' => 'Settings', 'icon' => 'settings'],
];

$logo = static fn (string $file): string => esc(base_url('assets/images/brand-logos/' . $file), 'attr');
?>
<svg
    xmlns="http://www.w3.org/2000/svg"
    style="position: absolute; width: 0; height: 0; overflow: hidden"
    aria-hidden="true">
    <!-- Dashboard -->
    <symbol id="icon-dashboard" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="3" width="7" height="7" rx="1.5" />
        <rect x="14" y="3" width="7" height="5" rx="1.5" />
        <rect x="14" y="11" width="7" height="10" rx="1.5" />
        <rect x="3" y="13" width="7" height="8" rx="1.5" />
    </symbol>

    <!-- Bookings -->
    <symbol id="icon-bookings" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="5" width="18" height="16" rx="2" />
        <path d="M16 3v4M8 3v4M3 10h18" />
        <path d="m8 15 2 2 5-4" />
    </symbol>

    <!-- Moderation Queue -->
    <symbol id="icon-moderation" viewBox="0 0 24 24" fill="none">
        <path d="M8 4H5a2 2 0 0 0-2 2v14h18V6a2 2 0 0 0-2-2h-3" />
        <rect x="8" y="2" width="8" height="5" rx="1.5" />
        <path d="m7 13 2 2 4-4M15 14h3M15 17h3" />
    </symbol>

    <!-- Payments -->
    <symbol id="icon-payments" viewBox="0 0 24 24" fill="none">
        <rect x="2.5" y="5" width="19" height="16" rx="2" />
        <path d="M16 5V3H5a2 2 0 0 0-2 2M3 9h18" />
        <path d="M16 14h.01" />
        <circle cx="16" cy="14" r="2.5" />
    </symbol>

    <!-- Gift Orders -->
    <symbol id="icon-gift-orders" viewBox="0 0 24 24" fill="none">
        <path d="M3 10h18v11H3zM2 7h20v4H2zM12 7v14" />
        <path d="M12 7H7.5a2.5 2.5 0 1 1 2.5-2.5C10 6 12 7 12 7Z" />
        <path d="M12 7h4.5a2.5 2.5 0 1 0-2.5-2.5C14 6 12 7 12 7Z" />
    </symbol>

    <!-- Notifications -->
    <symbol id="icon-notifications" viewBox="0 0 24 24" fill="none">
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
        <path d="M10 21h4" />
    </symbol>

    <!-- Categories -->
    <symbol id="icon-categories" viewBox="0 0 24 24" fill="none">
        <path
            d="M3 6a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
        <path d="M3 9h18" />
    </symbol>

    <!-- Packages -->
    <symbol id="icon-packages" viewBox="0 0 24 24" fill="none">
        <path d="m12 3 9 5-9 5-9-5 9-5Z" />
        <path d="m3 8 9 5 9-5M3 8v9l9 5 9-5V8" />
        <path d="M12 13v9" />
    </symbol>

    <!-- Radio Shows -->
    <symbol id="icon-shows" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="7" width="18" height="14" rx="2" />
        <path d="m7 7 10-4M7 12h5M7 16h5" />
        <circle cx="17" cy="14" r="2.5" />
    </symbol>

    <!-- Gifts -->
    <symbol id="icon-gifts" viewBox="0 0 24 24" fill="none">
        <path d="M3 10h18v11H3zM2 7h20v4H2zM12 7v14" />
        <path d="M12 7H7.5a2.5 2.5 0 1 1 2.5-2.5C10 6 12 7 12 7Z" />
        <path d="M12 7h4.5a2.5 2.5 0 1 0-2.5-2.5C14 6 12 7 12 7Z" />
    </symbol>

    <!-- Gift Vendors -->
    <symbol id="icon-vendors" viewBox="0 0 24 24" fill="none">
        <path d="M3 10v11h18V10M2 10l2-7h16l2 7" />
        <path d="M2 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0 2 2 0 0 0 2 0" />
        <path d="M9 21v-7h6v7" />
    </symbol>

    <!-- Settings -->
    <symbol id="icon-settings" viewBox="0 0 24 24" fill="none">
        <path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z" />
        <path
            d="m19.4 15 .1.1 1.4 1.1-1.5 2.6-1.7-.7a8 8 0 0 1-2 1.2l-.3 1.8h-3l-.3-1.8a8 8 0 0 1-2-1.2l-1.7.7-1.5-2.6 1.4-1.1a7 7 0 0 1 0-2.2l-1.4-1.1 1.5-2.6 1.7.7a8 8 0 0 1 2-1.2l.3-1.8h3l.3 1.8a8 8 0 0 1 2 1.2l1.7-.7 1.5 2.6-1.4 1.1a7 7 0 0 1 0 2.1Z"
            transform="translate(-1 -1)" />
    </symbol>

    <!-- Message templates -->
    <symbol id="icon-templates" viewBox="0 0 24 24" fill="none">
        <path d="M4 4h16v12H8l-4 4z" />
        <path d="M8 8h8M8 12h5" />
    </symbol>

    <!-- Presenter portal -->
    <symbol id="icon-presenter" viewBox="0 0 24 24" fill="none">
        <rect x="9" y="2" width="6" height="11" rx="3" />
        <path d="M5 10a7 7 0 0 0 14 0M12 17v4M8 21h8" />
    </symbol>
</svg>

<aside class="app-sidebar sticky" id="sidebar">
    <div class="main-sidebar-header">
        <a href="<?= site_url('admin') ?>" class="header-logo" aria-label="CBS Radio admin dashboard">
            <img src="<?= $logo('desktop-logo.svg') ?>" alt="CBS Radio" class="desktop-logo">
            <img src="<?= $logo('toggle-dark.svg') ?>" alt="CBS Radio" class="toggle-dark">
            <img src="<?= $logo('desktop-dark.svg') ?>" alt="CBS Radio" class="desktop-dark">
            <img src="<?= $logo('toggle-logo.svg') ?>" alt="CBS Radio" class="toggle-logo">
        </a>
    </div>

    <div class="main-sidebar" id="sidebar-scroll">
        <nav class="main-menu-container nav nav-pills flex-column sub-open" aria-label="Admin">
            <div class="slide-left" id="slide-left">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path></svg>
            </div>
            <ul class="main-menu">
                <?php foreach ($menu as $item): ?>
                    <?php if (isset($item['category'])): ?>
                        <li class="slide__category"><span class="category-name"><?= esc($item['category']) ?></span></li>
                    <?php else: ?>
                        <?php $active = $isActive($item['link']); ?>
                        <li class="slide<?= $active ? ' active' : '' ?>">
                            <a href="<?= site_url($item['link']) ?>" class="side-menu__item<?= $active ? ' active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <svg class="side-menu__icon" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <use href="#icon-<?= esc($item['icon'], 'attr') ?>"></use>
                                </svg>
                                <span class="side-menu__label"><?= esc($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div class="slide-right" id="slide-right">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path></svg>
            </div>
        </nav>
    </div>
</aside>
