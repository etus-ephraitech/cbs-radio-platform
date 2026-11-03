<?php

namespace App\Controllers\Admin;

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
        $status = $this->request->getGet('status');

        return ui_view('admin/payments/index', [
            'payments' => $status ? $this->paymentModel->where('status', $status)->findAll() : $this->paymentModel->findAll(),
            'status'   => $status,
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
