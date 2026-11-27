<?php

namespace App\Controllers\Admin;

use App\Libraries\AdminFilters;
use App\Models\PaymentModel;
use App\Services\PaymentService;

/**
 * Admin payment reconciliation: view payments by status, and the manual
 * "mark as paid" fallback agreed in the project scope — so a Mobile Money
 * hiccup never blocks a booking from airing.
 */
class PaymentController extends BaseAdminController
{
    protected PaymentModel $paymentModel;
    protected PaymentService $paymentService;

    public function __construct()
    {
        $this->paymentModel   = new PaymentModel();
        $this->paymentService = new PaymentService();
    }

    public function index()
    {
        $providers = $this->paymentModel->providers();
        $filters   = AdminFilters::orderedRange(AdminFilters::read($this->request, [
            'q'        => 'search',
            'status'   => [PaymentModel::STATUS_PENDING, PaymentModel::STATUS_SUCCESS, PaymentModel::STATUS_FAILED],
            'provider' => $providers,
            'from'     => 'date',
            'to'       => 'date',
        ]));

        $perPage             = config('CbsRadio')->adminPageSize;
        $total               = $this->paymentModel->adminCount($filters);
        [$page, $totalPages] = AdminFilters::page($this->request, $total, $perPage);

        return ui_view('admin/payments/index', [
            'payments'   => $this->paymentModel->adminSearch($filters, $perPage, ($page - 1) * $perPage),
            'providers'  => $providers,
            'filters'    => $filters,
            'status'     => $filters['status'] === '' ? null : $filters['status'],
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    /**
     * Admin manual override — marks a payment paid and pushes the linked
     * booking/gift order forward, without waiting on the Mobile Money
     * callback.
     */
    public function markManual(int $paymentId)
    {
        $ok = $this->paymentService->markManualPayment($paymentId, $this->currentUserId());

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Payment marked as paid.' : 'Could not mark this payment as paid.'
        );
    }
}
