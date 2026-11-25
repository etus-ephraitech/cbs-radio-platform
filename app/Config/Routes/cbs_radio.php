<?php

/**
 * Routes for the CBS Radio platform's booking, gifts, payment, presenter,
 * and admin flows. Kept in its own file rather than inline in
 * app/Config/Routes.php so each dev's area maps to one file, matching the
 * module-ownership split in CONTRIBUTING.md.
 *
 * Wire this in from app/Config/Routes.php with one line, after the
 * $routes object is created:
 *
 *   require APPPATH . 'Config/Routes/cbs_radio.php';
 *
 * ASSUMPTION TO RECONCILE: the 'auth' filter name below is a placeholder
 * for whatever filter the API starter kit's auth system registers in
 * app/Config/Filters.php for browser-session-authenticated staff routes.
 * Rename every ['filter' => 'auth'] below to match once confirmed. If
 * presenter and admin need different permission levels (not just
 * "logged in"), a second filter (e.g. 'role:admin') can be added
 * alongside 'auth' in the same array — see the commented example under
 * the admin group.
 *
 * Every PUBLIC route below carries the SiteStatus filter, so the whole public site closes together when staff
 * set it to coming soon or under maintenance (Admin > Settings > Site status). A new public route belongs in a
 * group that carries it. The Mobile Money callback, the presenter portal and the admin area are deliberately
 * without it: a provider cannot be told to wait, and staff must be able to switch the site back on.
 *
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

use App\Filters\SiteStatus;

// ---------------------------------------------------------------------
// Public: the homepage (LandingController).
//
// IMPORTANT: CodeIgniter keeps the FIRST definition of a route and silently
// ignores any later one for the same path. The starter app already defines
// "/" (`$routes->get('/', 'Home::index');` in app/Config/Routes.php), so
// that line must be DELETED or the framework's welcome page will keep
// answering at "/" and this route will never run. See the README, step 2.
// ---------------------------------------------------------------------
$routes->get('/', 'LandingController::index', ['filter' => SiteStatus::class]);

// ---------------------------------------------------------------------
// Public: booking flow (BookingController) — Dev A's area
// ---------------------------------------------------------------------
$routes->group('booking', ['filter' => SiteStatus::class], static function ($routes) {
    $routes->get('/', 'BookingController::index');
    $routes->post('/', 'BookingController::store');
    $routes->get('(:num)/pay', 'BookingController::pay/$1');
    $routes->get('(:num)/confirmation', 'BookingController::confirmation/$1');
    // Catch-all by category slug — declared last so it never shadows the
    // two-segment numeric routes above (:segment matches exactly one
    // path segment, so it can't match "123/pay" anyway, but keeping this
    // last is the clearer convention to follow as more routes are added).
    $routes->get('(:segment)', 'BookingController::category/$1');
});

// ---------------------------------------------------------------------
// Public: digital gifts flow (GiftOrderController) — Dev B's area
// ---------------------------------------------------------------------
$routes->group('gifts', ['filter' => SiteStatus::class], static function ($routes) {
    $routes->get('/', 'GiftOrderController::index');
    $routes->post('/', 'GiftOrderController::store');
    // The gift cart and hosted checkout (partner shop gifts, paid on the shop's own page). Declared before the
    // numeric routes below; none of these paths can match (:num).
    $routes->get('cart', 'GiftCartController::show');
    $routes->post('cart/add', 'GiftCartController::add');
    $routes->post('cart/update', 'GiftCartController::update');
    $routes->post('cart/remove/(:num)', 'GiftCartController::remove/$1');
    $routes->post('checkout', 'GiftCartController::checkout');
    $routes->get('checkout/(:segment)', 'GiftCartController::purchase/$1');
    $routes->get('(:num)/pay', 'GiftOrderController::pay/$1');
    $routes->get('(:num)/confirmation', 'GiftOrderController::confirmation/$1');
});

// ---------------------------------------------------------------------
// Public: Mobile Money callback (PaymentController) — Dev B's area
// Must stay unauthenticated (the provider calls this directly, with no
// browser session) — protected instead by the token check inside the
// controller. See PaymentController::callback()'s docblock.
// ---------------------------------------------------------------------
$routes->post('payments/callback', 'PaymentController::callback');

// ---------------------------------------------------------------------
// Presenter portal (Presenter\QueueController) — Dev C's area
// ---------------------------------------------------------------------
$routes->group('presenter', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Presenter\QueueController::index');
    $routes->get('shows/(:num)', 'Presenter\QueueController::show/$1');
    $routes->post('bookings/(:num)/aired', 'Presenter\QueueController::markAired/$1');
    $routes->get('bookings/(:num)/voice-note', 'Presenter\QueueController::playVoiceNote/$1');
});

// ---------------------------------------------------------------------
// Admin area (Admin\ModerationController, Admin\PaymentController,
// Admin\GiftOrderController) — Dev C's area, lead reviews changes here
// per CONTRIBUTING.md since it touches cross-cutting admin access.
// ---------------------------------------------------------------------
$routes->group('admin', ['filter' => 'auth'], static function ($routes) {
    // Landing page for the whole admin area — see Admin\DashboardController.
    $routes->get('/', 'Admin\DashboardController::index');

    // If moderator/admin roles need separating later, a per-group filter
    // can be added without restructuring these routes, e.g.:
    //   $routes->group('moderation', ['filter' => 'auth:moderator'], ...)
    // Admin-editable settings, one tab per group. The test-send route is
    // declared before the (:segment) catch-alls so it can never be shadowed.
    $routes->group('settings', static function ($routes) {
        $routes->get('/', 'Admin\SettingController::index');
        $routes->post('test/(:segment)', 'Admin\SettingController::sendTest/$1');
        $routes->get('preview/(:segment)', 'Admin\SettingController::previewStatus/$1');
        $routes->get('(:segment)', 'Admin\SettingController::show/$1');
        $routes->post('(:segment)', 'Admin\SettingController::save/$1');
    });

    $routes->group('bookings', static function ($routes) {
        $routes->get('/', 'Admin\BookingController::index');
        $routes->get('(:num)', 'Admin\BookingController::show/$1');
        $routes->get('(:num)/voice-note', 'Admin\BookingController::voiceNote/$1');
        $routes->post('(:num)/cancel', 'Admin\BookingController::cancel/$1');
    });

    $routes->group('moderation', static function ($routes) {
        $routes->get('/', 'Admin\ModerationController::index');
        $routes->post('(:num)/approve', 'Admin\ModerationController::approve/$1');
        $routes->post('(:num)/reject', 'Admin\ModerationController::reject/$1');
        $routes->post('(:num)/flag', 'Admin\ModerationController::flag/$1');
        $routes->get('(:num)/history', 'Admin\ModerationController::history/$1');
        $routes->get('(:num)/voice-note', 'Admin\ModerationController::playVoiceNote/$1');
    });

    $routes->group('payments', static function ($routes) {
        $routes->get('/', 'Admin\PaymentController::index');
        $routes->post('(:num)/mark-manual', 'Admin\PaymentController::markManual/$1');
    });

    // What visitors bought through a partner shop's hosted checkout (they pay the shop, so this is read-only
    // apart from asking the shop again).
    $routes->group('gift-purchases', static function ($routes) {
        $routes->get('/', 'Admin\GiftPurchaseController::index');
        $routes->get('(:num)', 'Admin\GiftPurchaseController::show/$1');
        $routes->post('(:num)/refresh', 'Admin\GiftPurchaseController::refresh/$1');
    });

    $routes->group('gift-orders', static function ($routes) {
        $routes->get('/', 'Admin\GiftOrderController::index');
        $routes->post('(:num)/fulfill', 'Admin\GiftOrderController::fulfill/$1');
        $routes->post('(:num)/cancel', 'Admin\GiftOrderController::cancel/$1');
    });

    $routes->group('notifications', static function ($routes) {
        $routes->get('/', 'Admin\NotificationController::index');
        $routes->post('(:num)/resend', 'Admin\NotificationController::resend/$1');
    });

    // Reference-data CRUD: categories and shows. Each action is its own
    // POST route (no PUT+_method spoofing) to stay consistent with every
    // other admin form in this project.
    $routes->group('categories', static function ($routes) {
        $routes->get('/', 'Admin\CategoryController::index');
        $routes->get('create', 'Admin\CategoryController::create');
        $routes->post('/', 'Admin\CategoryController::store');
        $routes->get('(:num)/edit', 'Admin\CategoryController::edit/$1');
        $routes->post('(:num)/update', 'Admin\CategoryController::update/$1');
        $routes->post('(:num)/deactivate', 'Admin\CategoryController::deactivate/$1');
        $routes->post('(:num)/activate', 'Admin\CategoryController::activate/$1');
    });

    $routes->group('shows', static function ($routes) {
        $routes->get('/', 'Admin\ShowController::index');
        $routes->get('create', 'Admin\ShowController::create');
        $routes->post('/', 'Admin\ShowController::store');
        $routes->get('(:num)/edit', 'Admin\ShowController::edit/$1');
        $routes->post('(:num)/update', 'Admin\ShowController::update/$1');
        $routes->post('(:num)/deactivate', 'Admin\ShowController::deactivate/$1');
        $routes->post('(:num)/activate', 'Admin\ShowController::activate/$1');
    });

    $routes->group('rate-tiers', static function ($routes) {
        $routes->get('/', 'Admin\RateTierController::index');
        $routes->get('create', 'Admin\RateTierController::create');
        $routes->post('/', 'Admin\RateTierController::store');
        $routes->get('(:num)/edit', 'Admin\RateTierController::edit/$1');
        $routes->post('(:num)/update', 'Admin\RateTierController::update/$1');
        $routes->post('(:num)/deactivate', 'Admin\RateTierController::deactivate/$1');
        $routes->post('(:num)/activate', 'Admin\RateTierController::activate/$1');
    });

    // Example messages offered on the booking form.
    $routes->group('message-templates', static function ($routes) {
        $routes->get('/', 'Admin\MessageTemplateController::index');
        $routes->get('create', 'Admin\MessageTemplateController::create');
        $routes->post('/', 'Admin\MessageTemplateController::store');
        $routes->get('(:num)/edit', 'Admin\MessageTemplateController::edit/$1');
        $routes->post('(:num)/update', 'Admin\MessageTemplateController::update/$1');
        $routes->post('(:num)/deactivate', 'Admin\MessageTemplateController::deactivate/$1');
        $routes->post('(:num)/activate', 'Admin\MessageTemplateController::activate/$1');
    });

    // Local gifts (fully editable) — vendor-sourced gifts are listed here
    // too but only activate/deactivate apply to them.
    $routes->group('gifts', static function ($routes) {
        $routes->get('/', 'Admin\GiftController::index');
        $routes->get('create', 'Admin\GiftController::create');
        $routes->post('/', 'Admin\GiftController::store');
        $routes->get('(:num)/edit', 'Admin\GiftController::edit/$1');
        $routes->post('(:num)/update', 'Admin\GiftController::update/$1');
        $routes->post('(:num)/deactivate', 'Admin\GiftController::deactivate/$1');
        $routes->post('(:num)/activate', 'Admin\GiftController::activate/$1');
    });

    // Third-party gift vendor connections.
    $routes->group('gift-vendors', static function ($routes) {
        $routes->get('/', 'Admin\GiftVendorController::index');
        $routes->get('create', 'Admin\GiftVendorController::create');
        $routes->post('/', 'Admin\GiftVendorController::store');
        $routes->get('(:num)/edit', 'Admin\GiftVendorController::edit/$1');
        $routes->post('(:num)/update', 'Admin\GiftVendorController::update/$1');
        $routes->post('(:num)/deactivate', 'Admin\GiftVendorController::deactivate/$1');
        $routes->post('(:num)/activate', 'Admin\GiftVendorController::activate/$1');
        $routes->post('sync', 'Admin\GiftVendorController::syncNow');
    });
});
