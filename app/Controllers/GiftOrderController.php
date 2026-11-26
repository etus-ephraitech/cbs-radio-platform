<?php

namespace App\Controllers;

use App\Libraries\GiftCart;
use App\Models\GiftModel;
use App\Models\GiftVendorModel;
use App\Models\GiftOrderModel;
use App\Services\GiftOrderService;
use App\Services\PaymentService;

/**
 * Public "Send a Gift" flow — standalone digital/vendor gift ordering,
 * separate from (but optionally attachable to) a greeting booking.
 */
class GiftOrderController extends BaseController
{
    protected GiftModel $giftModel;
    protected GiftOrderService $giftOrderService;
    protected PaymentService $paymentService;

    public function __construct()
    {
        $this->giftModel        = new GiftModel();
        $this->giftOrderService = new GiftOrderService();
        $this->paymentService   = new PaymentService();
    }

    /**
     * Digital/vendor gifts catalog (homepage "Send a Gift" quick action
     * lands here) — local and vendor-sourced gifts shown together.
     * Optional ?booking_id= attaches the gift to an existing booking
     * instead of standing alone.
     */
    public function index()
    {
        $hosted   = [];
        $standard = [];

        // Gifts from a partner shop with its own checkout (Katale) are bought through the gift cart and paid on the
        // shop's page; everything else is ordered with the form below and paid through us.
        foreach ($this->giftModel->active() as $gift) {
            if ($gift['source'] === GiftModel::SOURCE_VENDOR && in_array($gift['vendor_driver'] ?? '', GiftVendorModel::HOSTED_DRIVERS, true)) {
                $hosted[] = $gift;
            } else {
                $standard[] = $gift;
            }
        }

        return ui_view('gifts/catalog', [
            'gifts'       => $standard,
            'hostedGifts' => $hosted,
            'cartCount'   => (new GiftCart())->count(),
            'bookingId'   => $this->request->getGet('booking_id'),
        ]);
    }

    public function store()
    {
        $rules = [
            'gift_id'          => 'required|is_natural_no_zero',
            'sender_name'      => 'required|max_length[150]',
            'sender_phone'     => 'required|max_length[20]',
            'recipient_name'   => 'required|max_length[150]',
            'recipient_phone'  => 'permit_empty|max_length[20]',
            'delivery_details' => 'permit_empty',
            'quantity'         => 'permit_empty|is_natural_no_zero',
            'booking_id'       => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $input = $this->request->getPost([
            'gift_id', 'sender_name', 'sender_phone', 'recipient_name',
            'recipient_phone', 'delivery_details', 'quantity',
        ]);

        $bookingId = $this->request->getPost('booking_id');
        $bookingId = $bookingId !== null && $bookingId !== '' ? (int) $bookingId : null;

        $result = $this->giftOrderService->createOrder($input, $bookingId);

        if (! $result['success']) {
            return redirect()->back()->withInput()->with('toastr_error', $result['message']);
        }

        return redirect()->to(site_url('gifts/' . $result['giftOrder']['id'] . '/pay'));
    }

    public function pay(int $giftOrderId)
    {
        $giftOrder = (new GiftOrderModel())->find($giftOrderId);

        if ($giftOrder === null) {
            return redirect()->to(site_url('gifts'))->with('toastr_error', 'Gift order not found.');
        }

        // A gift chosen inside a greeting's form is paid for in that greeting's payment, never on its own:
        // a second payment here would charge for it twice.
        if ((int) ($giftOrder['bundled_with_booking'] ?? 0) === 1 && ! empty($giftOrder['booking_id'])) {
            return redirect()->to(site_url('booking/' . $giftOrder['booking_id'] . '/pay'));
        }

        // Only start a payment for an order that is waiting to be paid.
        if ($giftOrder['status'] !== GiftOrderModel::STATUS_PENDING_PAYMENT) {
            return redirect()->to(site_url('gifts/' . $giftOrderId . '/confirmation'));
        }

        $result = $this->paymentService->initiateForGiftOrder($giftOrder);

        return ui_view('gifts/pay', [
            'giftOrder' => $giftOrder,
            'result'    => $result,
            'payments'  => config('Payments'),
            'contact'   => config('CbsRadio'),
        ]);
    }

    public function confirmation(int $giftOrderId)
    {
        $giftOrder = (new GiftOrderModel())->find($giftOrderId);

        if ($giftOrder === null) {
            return redirect()->to(site_url('gifts'))->with('toastr_error', 'Gift order not found.');
        }

        return ui_view('gifts/confirmation', ['giftOrder' => $giftOrder]);
    }
}
