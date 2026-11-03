<?php

namespace App\Controllers\Admin;

use App\Libraries\Settings\SettingsManager;
use App\Models\BookingModel;
use App\Models\CategoryModel;
use App\Models\GiftModel;
use App\Models\GiftOrderModel;
use App\Models\GiftVendorModel;
use App\Models\NotificationsLogModel;
use App\Models\PaymentModel;
use App\Models\RateTierModel;
use App\Models\ShowModel;

/**
 * Admin landing page: what needs attention right now (work queues), and
 * a quick health check on the catalog (is anything misconfigured or
 * empty). Every number here links through to the screen where it can be
 * acted on, so the dashboard is a router, not a report.
 */
class DashboardController extends BaseAdminController
{
    public function index()
    {
        $bookingModel   = new BookingModel();
        $paymentModel   = new PaymentModel();
        $giftOrderModel = new GiftOrderModel();
        $notifications  = new NotificationsLogModel();
        $giftModel      = new GiftModel();

        $lastSync = $giftModel->selectMax('synced_at')->where('source', GiftModel::SOURCE_VENDOR)->first();

        return ui_view('admin/dashboard', [
            'health'    => (new SettingsManager())->health(),
            'attention' => [
                'pendingModeration' => $bookingModel->where('status', BookingModel::STATUS_PENDING_MODERATION)->countAllResults(),
                'pendingPayments'   => $paymentModel->where('status', PaymentModel::STATUS_PENDING)->countAllResults(),
                'giftsToFulfill'    => $giftOrderModel->where('status', GiftOrderModel::STATUS_PAID)->countAllResults(),
                'failedNotices24h'  => $notifications
                    ->where('status', 'failed')
                    ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')))
                    ->countAllResults(),
            ],
            'catalog' => [
                'categories' => (new CategoryModel())->where('is_active', 1)->countAllResults(),
                'rateTiers'  => (new RateTierModel())->where('is_active', 1)->countAllResults(),
                'shows'      => (new ShowModel())->where('is_active', 1)->countAllResults(),
                'gifts'      => (new GiftModel())->where('is_active', 1)->countAllResults(),
                'vendors'    => (new GiftVendorModel())->where('is_active', 1)->countAllResults(),
                'lastSync'   => $lastSync['synced_at'] ?? null,
            ],
        ]);
    }
}
