<?php

namespace App\Controllers\Admin;

use App\Libraries\AdminFilters;
use App\Models\GiftOrderModel;
use App\Services\GiftOrderService;

/**
 * Admin view of gift orders — fulfilling a paid order (local: mark done
 * by hand; vendor-sourced: actually places the order with the vendor via
 * GiftOrderService::fulfill()) or cancelling one.
 */
class GiftOrderController extends BaseAdminController
{
    protected GiftOrderModel $giftOrderModel;
    protected GiftOrderService $giftOrderService;

    public function __construct()
    {
        $this->giftOrderModel   = new GiftOrderModel();
        $this->giftOrderService = new GiftOrderService();
    }

    public function index()
    {
        $filters = AdminFilters::orderedRange(AdminFilters::read($this->request, [
            'q'      => 'search',
            'status' => ['pending_payment', 'paid', 'fulfilled', 'cancelled'],
            'source' => ['local', 'vendor'],
            'from'   => 'date',
            'to'     => 'date',
        ]));

        $perPage             = config('CbsRadio')->adminPageSize;
        $total               = $this->giftOrderModel->adminCount($filters);
        [$page, $totalPages] = AdminFilters::page($this->request, $total, $perPage);

        return ui_view('admin/gift_orders/index', [
            'giftOrders' => $this->giftOrderModel->adminSearch($filters, $perPage, ($page - 1) * $perPage),
            'filters'    => $filters,
            'status'     => $filters['status'] === '' ? null : $filters['status'],
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    public function fulfill(int $giftOrderId)
    {
        $result = $this->giftOrderService->fulfill($giftOrderId);

        return redirect()->back()->with(
            $result['success'] ? 'toastr_success' : 'toastr_error',
            $result['message']
        );
    }

    public function cancel(int $giftOrderId)
    {
        $ok = $this->giftOrderService->cancel($giftOrderId);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Gift order cancelled.' : 'Could not cancel this gift order.'
        );
    }
}
