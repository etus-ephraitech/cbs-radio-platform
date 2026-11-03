<?php

namespace App\Controllers\Admin;

use App\Models\NotificationsLogModel;
use App\Services\NotificationService;

/**
 * Admin view of every SMS/email the platform has tried to send, with
 * filters and a resend action for failures. This is where a gateway
 * outage becomes visible and recoverable: senders who never got their
 * confirmation show up here as failed rows, and can be re-sent once the
 * gateway is back, without anyone re-triggering the original booking
 * action.
 */
class NotificationController extends BaseAdminController
{
    protected NotificationsLogModel $notificationsLogModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->notificationsLogModel = new NotificationsLogModel();
        $this->notificationService   = new NotificationService();
    }

    public function index()
    {
        $perPage = config('CbsRadio')->adminPageSize;

        $status  = $this->request->getGet('status');
        $channel = $this->request->getGet('channel');
        $booking = $this->request->getGet('booking_id');

        // Whitelist every filter value — these come straight from the
        // query string and feed a WHERE clause.
        $filters = [
            'status'     => in_array($status, ['sent', 'failed'], true) ? $status : null,
            'channel'    => in_array($channel, ['sms', 'email'], true) ? $channel : null,
            'booking_id' => ctype_digit((string) $booking) && (int) $booking > 0 ? (int) $booking : null,
        ];

        $total      = $this->notificationsLogModel->countFiltered($filters);
        $totalPages = max((int) ceil($total / $perPage), 1);
        $page       = min(max((int) $this->request->getGet('page'), 1), $totalPages);

        return ui_view('admin/notifications/index', [
            'notifications' => $this->notificationsLogModel->filtered($filters, $perPage, ($page - 1) * $perPage),
            'filters'       => $filters,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'total'         => $total,
        ]);
    }

    /**
     * Re-sends one failed notification. Only failed rows are resendable —
     * resending something that already went out would just spam the
     * recipient.
     */
    public function resend(int $notificationId)
    {
        $row = $this->notificationsLogModel->find($notificationId);

        if ($row === null) {
            return redirect()->back()->with('toastr_error', 'Notification not found.');
        }

        if ($row['status'] !== 'failed') {
            return redirect()->back()->with('toastr_error', 'Only failed notifications can be re-sent.');
        }

        $ok = $this->notificationService->resend($row);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Notification re-sent.' : 'Re-send failed again — check the gateway settings in .env.'
        );
    }
}
