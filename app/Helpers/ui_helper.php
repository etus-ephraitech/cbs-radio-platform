<?php

/**
 * UI helpers for the CBS Radio platform — Bootstrap 5, Toastr, SweetAlert2.
 * Autoload via app/Config/Autoload.php -> $helpers = ['ui', ...]
 * or load on demand with helper('ui').
 */

if (! function_exists('cbs_booking_status_badge')) {
    /**
     * Bootstrap 5 badge for a bookings.status value.
     * Statuses must match the ENUM in CreateBookingsTable exactly.
     */
    function cbs_booking_status_badge(string $status): string
    {
        $map = [
            'pending_payment'    => ['label' => 'Pending Payment',    'class' => 'bg-secondary'],
            'pending_moderation' => ['label' => 'Pending Moderation', 'class' => 'bg-warning text-dark'],
            'approved'           => ['label' => 'Approved',           'class' => 'bg-info text-dark'],
            'rejected'           => ['label' => 'Rejected',           'class' => 'bg-danger'],
            'aired'              => ['label' => 'Aired',              'class' => 'bg-success'],
            'cancelled'          => ['label' => 'Cancelled',          'class' => 'bg-dark'],
        ];

        $entry = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-secondary'];

        return '<span class="badge ' . $entry['class'] . '">' . esc($entry['label']) . '</span>';
    }
}

if (! function_exists('cbs_payment_status_badge')) {
    /**
     * Bootstrap 5 badge for a payments.status value.
     * Statuses must match the ENUM in CreatePaymentsTable exactly.
     */
    function cbs_payment_status_badge(string $status): string
    {
        $map = [
            'pending' => ['label' => 'Pending', 'class' => 'bg-secondary'],
            'success' => ['label' => 'Success', 'class' => 'bg-success'],
            'failed'  => ['label' => 'Failed',  'class' => 'bg-danger'],
        ];

        $entry = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-secondary'];

        return '<span class="badge ' . $entry['class'] . '">' . esc($entry['label']) . '</span>';
    }
}

if (! function_exists('cbs_gift_order_status_badge')) {
    /**
     * Bootstrap 5 badge for a gift_orders.status value.
     * Statuses must match the ENUM in CreateGiftOrdersTable exactly.
     */
    function cbs_gift_order_status_badge(string $status): string
    {
        $map = [
            'pending_payment' => ['label' => 'Pending Payment', 'class' => 'bg-secondary'],
            'paid'            => ['label' => 'Paid',            'class' => 'bg-info text-dark'],
            'fulfilled'       => ['label' => 'Fulfilled',       'class' => 'bg-success'],
            'cancelled'       => ['label' => 'Cancelled',       'class' => 'bg-dark'],
        ];

        $entry = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-secondary'];

        return '<span class="badge ' . $entry['class'] . '">' . esc($entry['label']) . '</span>';
    }
}

if (! function_exists('cbs_checkout_state_label')) {
    /**
     * What a visitor is told about a hosted checkout (vendor_checkouts.state), in plain words.
     */
    function cbs_checkout_state_label(string $state): string
    {
        return [
            'draft'            => 'Starting',
            'open'             => 'Waiting for you at checkout',
            'in_checkout'      => 'Checkout in progress',
            'awaiting_payment' => 'Waiting for payment',
            'paid'             => 'Payment received',
            'cancelled'        => 'Cancelled',
            'expired'          => 'Expired',
            'failed'           => 'Could not be started',
        ][$state] ?? ucfirst(str_replace('_', ' ', $state));
    }
}

if (! function_exists('cbs_checkout_state_badge')) {
    /**
     * Bootstrap 5 badge for a vendor_checkouts.state value.
     * States must match the ENUM in CreateVendorCheckoutsTable exactly.
     */
    function cbs_checkout_state_badge(string $state): string
    {
        $classes = [
            'draft'            => 'bg-secondary',
            'open'             => 'bg-secondary',
            'in_checkout'      => 'bg-info text-dark',
            'awaiting_payment' => 'bg-warning text-dark',
            'paid'             => 'bg-success',
            'cancelled'        => 'bg-dark',
            'expired'          => 'bg-secondary',
            'failed'           => 'bg-danger',
        ];

        return '<span class="badge ' . ($classes[$state] ?? 'bg-secondary') . '">' . esc(cbs_checkout_state_label($state)) . '</span>';
    }
}

if (! function_exists('cbs_delivery_state_label')) {
    /**
     * A gift's delivery progress as the vendor reports it (an order's deliveries[].state).
     */
    function cbs_delivery_state_label(string $state): string
    {
        return [
            'pending'           => 'Waiting to be prepared',
            'preparing'         => 'Being prepared',
            'ready'             => 'Ready',
            'on_the_way'        => 'On the way',
            'delivery_reported' => 'Delivery reported',
            'delivered'         => 'Delivered',
            'cancelled'         => 'Cancelled',
        ][$state] ?? ucfirst(str_replace('_', ' ', $state));
    }
}

if (! function_exists('cbs_is_superadmin')) {
    /**
     * Whether the signed-in person is a superadmin, for menus that show a link only to them. The route filter
     * (`role:superadmin`) is what actually protects a page; this only decides whether to offer the link. Anything
     * that goes wrong (no one signed in, the auth tables missing) means "no".
     */
    function cbs_is_superadmin(): bool
    {
        try {
            helper('auth');

            return function_exists('has_role') && has_role('superadmin');
        } catch (\Throwable) {
            return false;
        }
    }
}

if (! function_exists('cbs_brand')) {
    /**
     * The uploaded logo and tab icon (Admin > Logo), as public URLs. An empty string means "use the built-in
     * one", which is also what you get when the file has gone missing from disk. Read per request, so a change
     * shows at once.
     *
     * @return array{logo: string, favicon: string, alt: string}
     */
    function cbs_brand(): array
    {
        static $brand = null;

        if ($brand !== null && ENVIRONMENT !== 'testing') {
            return $brand;
        }

        $cbs = config('CbsRadio');

        return $brand = [
            'logo'    => \App\Services\BrandingService::url($cbs->logoPath),
            'favicon' => \App\Services\BrandingService::url($cbs->faviconPath),
            'alt'     => $cbs->logoAlt !== '' ? $cbs->logoAlt : 'CBS Radio',
        ];
    }
}

if (! function_exists('cbs_notification_status_badge')) {
    /**
     * Bootstrap 5 badge for a notifications_log.status value.
     * Statuses must match the ENUM in CreateNotificationsLogTable exactly.
     */
    function cbs_notification_status_badge(string $status): string
    {
        $map = [
            'sent'   => ['label' => 'Sent',   'class' => 'bg-success'],
            'failed' => ['label' => 'Failed', 'class' => 'bg-danger'],
        ];

        $entry = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-secondary'];

        return '<span class="badge ' . $entry['class'] . '">' . esc($entry['label']) . '</span>';
    }
}

if (! function_exists('cbs_active_badge')) {
    /**
     * Active / Inactive badge for any table with an is_active flag
     * (categories, shows, rate tiers, gifts, vendors).
     */
    function cbs_active_badge(int|string|bool $isActive): string
    {
        return (int) $isActive === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';
    }
}

if (! function_exists('cbs_time_ago')) {
    /**
     * Human-readable relative time, e.g. "5 minutes ago", for the
     * presenter portal's live queue and admin activity lists.
     */
    function cbs_time_ago(string $datetime): string
    {
        $timestamp = strtotime($datetime);

        if ($timestamp === false) {
            return $datetime;
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }

        $intervals = [
            31536000 => 'year',
            2592000  => 'month',
            604800   => 'week',
            86400    => 'day',
            3600     => 'hour',
            60       => 'minute',
        ];

        foreach ($intervals as $seconds => $label) {
            $count = intdiv($diff, $seconds);

            if ($count >= 1) {
                return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
            }
        }

        return 'just now';
    }
}

if (! function_exists('cbs_toastr_flash')) {
    /**
     * Renders queued Toastr calls from session flashdata.
     *
     * Set flashdata before redirecting, e.g.:
     *   session()->setFlashdata('toastr_success', 'Booking approved.');
     *   session()->setFlashdata('toastr_error', 'Payment could not be verified.');
     *
     * Then echo cbs_toastr_flash() once, near the end of the layout body,
     * after toastr.min.js has loaded.
     */
    function cbs_toastr_flash(): string
    {
        $session = session();
        $types   = ['success', 'error', 'warning', 'info'];
        $calls   = [];

        foreach ($types as $type) {
            $message = $session->getFlashdata('toastr_' . $type);

            if ($message) {
                $calls[] = 'toastr.' . $type . '(' . json_encode($message) . ');';
            }
        }

        if (empty($calls)) {
            return '';
        }

        return "<script>\n" . implode("\n", $calls) . "\n</script>";
    }
}

if (! function_exists('cbs_sweetalert_confirm')) {
    /**
     * Wires a button to a SweetAlert2 confirm dialog before submitting a
     * form — used for destructive/irreversible actions (reject a booking,
     * cancel a gift order, delete a category).
     *
     * Usage in a view:
     *   <form id="reject-form-<?= $booking['id'] ?>" method="post"
     *         action="<?= site_url('admin/bookings/' . $booking['id'] . '/reject') ?>">
     *   </form>
     *   <button type="button"
     *     onclick="<?= cbs_sweetalert_confirm('reject-form-' . $booking['id'], 'Reject this booking?', 'This cannot be undone.') ?>">
     *     Reject
     *   </button>
     */
    function cbs_sweetalert_confirm(string $formId, string $title, string $text = '', string $confirmButtonText = 'Yes, continue'): string
    {
        $payload = json_encode([
            'title'              => $title,
            'text'               => $text,
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonText'  => $confirmButtonText,
        ]);

        return "Swal.fire({$payload}).then(function(result) { "
            . "if (result.isConfirmed) { document.getElementById('" . esc($formId, 'js') . "').submit(); } "
            . "}); return false;";
    }
}

/*
 * ---------------------------------------------------------------------------
 * Versioned views (UI themes)
 * ---------------------------------------------------------------------------
 * `ui_version` in .env names a folder under app/Views (e.g. "v1") holding a
 * themed version of some views. A view is taken from that folder when the
 * folder has it, and from app/Views otherwise — so a theme only needs to
 * contain the screens it changes, and an empty ui_version (or one naming a
 * folder that does not exist) gives the original UI untouched.
 *
 * Controllers render with ui_view('admin/bookings/index', $data); themed
 * views extend ui_layout('admin'). Views in the base set keep extending their
 * own layout by name, so the original UI stays a complete, consistent set.
 */

if (! function_exists('ui_version')) {
    /**
     * The active UI version: a single folder name, or '' for the base views.
     * Anything that is not a plain folder name (a path, "..", spaces) is
     * ignored rather than trusted, since it ends up in a file path.
     */
    function ui_version(): string
    {
        $version = trim((string) env('ui_version', ''));

        return preg_match('/^[A-Za-z0-9_-]+$/', $version) === 1 ? $version : '';
    }
}

if (! function_exists('ui_view_path')) {
    /**
     * The view name to render for $view under the active UI version: the
     * versioned one when it exists, the base one otherwise.
     */
    function ui_view_path(string $view): string
    {
        static $exists = [];

        $view    = ltrim($view, '/');
        $version = ui_version();

        if ($version === '') {
            return $view;
        }

        $candidate = $version . '/' . $view;
        $file      = APPPATH . 'Views/' . $candidate . (str_ends_with($candidate, '.php') ? '' : '.php');

        $exists[$file] ??= is_file($file);

        return $exists[$file] ? $candidate : $view;
    }
}

if (! function_exists('ui_view')) {
    /**
     * view(), resolved through the active UI version.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    function ui_view(string $view, array $data = [], array $options = []): string
    {
        return view(ui_view_path($view), $data, $options);
    }
}

if (! function_exists('ui_layout')) {
    /**
     * The layout a themed view extends: <version>/layouts/<name> when the
     * version has it, layouts/<name> otherwise.
     */
    function ui_layout(string $layout = 'main'): string
    {
        return ui_view_path('layouts/' . $layout);
    }
}

/*
 * ---------------------------------------------------------------------------
 * Pictures for occasions and gifts (public flow)
 * ---------------------------------------------------------------------------
 * Categories and gifts carry no image, so the visitor's pages give each one an
 * icon and a colour from what it is: an occasion by the words in its slug or
 * name, a gift by its type. Inline SVG, drawn with currentColor, sized 1.6em.
 */

if (! function_exists('cbs_icon_svg')) {
    /**
     * One of the flow's line icons as inline SVG, or '' for an unknown name.
     */
    function cbs_icon_svg(string $name, float $size = 1.6): string
    {
        static $paths = [
            'cake'     => '<path d="M4 21h16M5 21v-7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7"/><path d="M5 16c1.5 1 3 1 4.5 0s3-1 4.5 0 3 1 4.5 0"/><path d="M12 12V8M12 5.5c.8-.7.8-1.8 0-2.5-.8.7-.8 1.8 0 2.5Z"/>',
            'candle'   => '<path d="M9 21h6M10 21V11h4v10"/><path d="M12 8c1.3-1 1.3-2.7 0-4-1.3 1.3-1.3 3 0 4Z"/><path d="M7 21h10"/>',
            'star'     => '<path d="m12 3 2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.6 6.7 19.4l1.2-6L3.4 9.3l6-.7Z"/>',
            'trophy'   => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0Z"/><path d="M7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3"/>',
            'sparkle'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6.3 6.3l2.5 2.5M15.2 15.2l2.5 2.5M6.3 17.7l2.5-2.5M15.2 8.8l2.5-2.5"/>',
            'rings'    => '<circle cx="9" cy="14" r="5"/><circle cx="15" cy="14" r="5"/><path d="m10 4 2-2 2 2-2 3Z"/>',
            'heart'    => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/>',
            'mic'      => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
            'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'phone'    => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
            'megaphone' => '<path d="M3 11v2a2 2 0 0 0 2 2h2l5 4V5L7 9H5a2 2 0 0 0-2 2Z"/><path d="M16 9a4 4 0 0 1 0 6M19 6a8 8 0 0 1 0 12"/>',
            'music'    => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
            'gift'     => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13"/><path d="M12 8C10 4 7 4 7 6s3 2 5 2c2 0 5 0 5-2s-3-2-5 2Z"/>',
            'check'    => '<path d="m5 12 5 5L20 7"/>',
            'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'back'     => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
            'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6Z"/><path d="m9 12 2 2 4-4"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'radio'    => '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="m7 8 9-5"/><circle cx="15" cy="14" r="2.5"/><path d="M7 12h3M7 15h3"/>',
            'alert'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/>',
            'copy'     => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
            'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
            'truck'    => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
            'eye-off'  => '<path d="M3 3l18 18M10.6 5.1A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17.8 17.8 0 0 1-3.2 4.2M6.6 6.6C3.9 8.3 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
            'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
            'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'key'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3M14 9l2 2"/>',
        ];

        if (! isset($paths[$name])) {
            return '';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . 'em" height="' . $size . 'em" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
}

if (! function_exists('cbs_occasion_look')) {
    /**
     * The icon and colour for an occasion, chosen from the words in its slug and name.
     *
     * @param array{slug?: string, name?: string} $category
     *
     * @return array{icon: string, tone: string}
     */
    function cbs_occasion_look(array $category): array
    {
        $words = strtolower(($category['slug'] ?? '') . ' ' . ($category['name'] ?? ''));

        $looks = [
            ['birthday', 'cake', 'rose'],
            ['tribute', 'candle', 'slate'],
            ['memoriam', 'candle', 'slate'],
            ['condolence', 'candle', 'slate'],
            ['wedding', 'rings', 'violet'],
            ['anniversar', 'heart', 'red'],
            ['love', 'heart', 'red'],
            ['milestone', 'trophy', 'gold'],
            ['achievement', 'trophy', 'gold'],
            ['graduat', 'trophy', 'gold'],
            ['holiday', 'star', 'teal'],
            ['calendar', 'star', 'teal'],
            ['christmas', 'star', 'teal'],
            ['greeting', 'sparkle', 'navy'],
            ['celebrat', 'sparkle', 'navy'],
        ];

        foreach ($looks as [$needle, $icon, $tone]) {
            if (str_contains($words, $needle)) {
                return ['icon' => $icon, 'tone' => $tone];
            }
        }

        return ['icon' => 'mic', 'tone' => 'navy'];
    }
}

if (! function_exists('cbs_gift_look')) {
    /**
     * The icon, colour and plain name for a gift type.
     *
     * @return array{icon: string, tone: string, label: string}
     */
    function cbs_gift_look(string $type): array
    {
        return match ($type) {
            'ecard'      => ['icon' => 'mail', 'tone' => 'rose', 'label' => 'E-card'],
            'airtime'    => ['icon' => 'phone', 'tone' => 'teal', 'label' => 'Airtime top-up'],
            'shoutout'   => ['icon' => 'megaphone', 'tone' => 'gold', 'label' => 'Shout-out'],
            'dedication' => ['icon' => 'music', 'tone' => 'violet', 'label' => 'Song dedication'],
            'physical'   => ['icon' => 'truck', 'tone' => 'red', 'label' => 'Delivered gift'],
            default      => ['icon' => 'gift', 'tone' => 'navy', 'label' => ucfirst($type)],
        };
    }
}

/*
 * ---------------------------------------------------------------------------
 * Form field errors (staff account pages)
 * ---------------------------------------------------------------------------
 */

if (! function_exists('cbs_field_invalid')) {
    /**
     * The attributes that mark a field as invalid and point it at its message, or '' when it has no error.
     *
     * @param array<string, string> $errors
     */
    function cbs_field_invalid(array $errors, string $field): string
    {
        return isset($errors[$field])
            ? ' aria-invalid="true" aria-describedby="' . esc($field, 'attr') . '-error"'
            : '';
    }
}

if (! function_exists('cbs_field_error')) {
    /**
     * A field's error message, tied to the field by id, or '' when it has none.
     *
     * @param array<string, string> $errors
     */
    function cbs_field_error(array $errors, string $field): string
    {
        if (! isset($errors[$field]) || ! is_string($errors[$field])) {
            return '';
        }

        return '<p class="au-error" id="' . esc($field, 'attr') . '-error">' . cbs_icon_svg('alert', 1) . '<span>' . esc($errors[$field]) . '</span></p>';
    }
}

/*
 * ---------------------------------------------------------------------------
 * Environment label (staging and other test copies)
 * ---------------------------------------------------------------------------
 * `app.environmentLabel` in .env (e.g. "Staging") marks a copy of the site that
 * is not the live one: every page shows a striped bar saying so, and the public
 * pages ask search engines not to index them. Empty (the default, and what the
 * live site uses) shows nothing.
 */

if (! function_exists('cbs_environment_label')) {
    function cbs_environment_label(): string
    {
        $label = trim(preg_replace('/\s+/', ' ', (string) env('app.environmentLabel', '')));

        return mb_substr($label, 0, 40);
    }
}

if (! function_exists('cbs_environment_banner')) {
    /**
     * The bar shown on every page of a non-live copy, or '' on the live site. Inline styles, so it looks the same
     * in the public, admin and sign-in layouts whatever stylesheets they load. $pinned keeps it fixed to the
     * bottom of the window, for the admin theme, whose header and side menu are fixed to the top and left.
     */
    function cbs_environment_banner(bool $pinned = false): string
    {
        $label = cbs_environment_label();

        if ($label === '') {
            return '';
        }

        $position = $pinned ? 'position:fixed;left:0;right:0;bottom:0;border-top:2px solid #1b1b1b;' : 'position:relative;border-bottom:2px solid #1b1b1b;';

        return '<div class="cbs-env-banner" role="note" style="' . $position . 'z-index:9999;padding:.45rem 1rem;text-align:center;'
            . 'font:700 .9rem/1.3 system-ui,-apple-system,\'Segoe UI\',sans-serif;letter-spacing:.02em;color:#1b1b1b;'
            . 'background:repeating-linear-gradient(135deg,#FFD24A 0 18px,#FFC21A 18px 36px)">'
            . esc(mb_strtoupper($label)) . ' &middot; test copy of the site. Bookings, payments and messages here are not real.'
            . '</div>'
            // Room at the bottom of the page, so the pinned bar never covers the last line.
            . ($pinned ? '<style>body{padding-bottom:2.6rem}</style>' : '');
    }
}
