<?php

namespace App\Controllers\Admin;

use App\Models\VendorCheckoutModel;
use App\Services\HostedCheckoutService;

/**
 * Admin view of the purchases visitors made through a partner shop's hosted checkout. We do not take or hold the
 * money for these, so this is where staff see what was bought, whether the shop says it was paid, and how the
 * delivery is going. "Check now" asks the shop again.
 */
class GiftPurchaseController extends BaseAdminController
{
    protected VendorCheckoutModel $checkoutModel;
    protected HostedCheckoutService $checkouts;

    public function __construct()
    {
        $this->checkoutModel = new VendorCheckoutModel();
        $this->checkouts     = new HostedCheckoutService();
    }

    public function index()
    {
        $state = $this->request->getGet('state');
        $state = in_array($state, ['open', 'in_checkout', 'awaiting_payment', 'paid', 'cancelled', 'expired', 'failed'], true) ? $state : null;

        return view('admin/gift_purchases/index', [
            'purchases' => $this->checkoutModel->adminList($state),
            'state'     => $state,
        ]);
    }

    public function show(int $id)
    {
        $purchase = $this->checkoutModel->withVendor($id);

        if ($purchase === null) {
            return redirect()->to(site_url('admin/gift-purchases'))->with('toastr_error', 'Purchase not found.');
        }

        $order = VendorCheckoutModel::order($purchase);

        return view('admin/gift_purchases/show', [
            'purchase'   => $purchase,
            'lines'      => VendorCheckoutModel::lines($purchase),
            'order'      => $order,
            'deliveries' => $order['deliveries'] ?? [],
        ]);
    }

    public function refresh(int $id)
    {
        $purchase = $this->checkoutModel->find($id);

        if ($purchase === null) {
            return redirect()->to(site_url('admin/gift-purchases'))->with('toastr_error', 'Purchase not found.');
        }

        if (empty($purchase['session_id'])) {
            return redirect()->back()->with('toastr_error', 'This purchase never reached the shop, so there is nothing to check.');
        }

        $after = $this->checkouts->status($purchase, true);

        if (! empty($after['last_error'])) {
            return redirect()->back()->with('toastr_error', 'The shop could not be asked: ' . $after['last_error']);
        }

        return redirect()->back()->with('toastr_success', 'Checked with the shop.');
    }
}
