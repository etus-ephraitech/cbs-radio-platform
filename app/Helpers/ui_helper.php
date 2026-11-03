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
