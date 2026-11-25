<?php

namespace App\Controllers;

use App\Libraries\GiftCart;
use App\Models\GiftModel;
use App\Models\VendorCheckoutModel;
use App\Services\HostedCheckoutService;

/**
 * The gift cart and the hosted checkout behind it: the gifts a partner shop (Katale Plus) delivers. The visitor
 * picks gifts here, is sent to the shop's own page to give delivery details and pay, and comes back to a status
 * page that asks the shop, from our server, whether it was paid. Our own gifts and the older vendor gifts keep
 * the "Send a Gift" form (GiftOrderController) and are paid through us.
 */
class GiftCartController extends BaseController
{
    protected GiftModel $giftModel;
    protected GiftCart $cart;
    protected HostedCheckoutService $checkouts;
    protected VendorCheckoutModel $checkoutModel;

    public function __construct()
    {
        $this->giftModel     = new GiftModel();
        $this->cart          = new GiftCart();
        $this->checkouts     = new HostedCheckoutService();
        $this->checkoutModel = new VendorCheckoutModel();
    }

    public function show()
    {
        $lines   = $this->cart->lines();
        $gifts   = $this->giftModel->hostedByIds(array_keys($lines));
        $rows    = [];
        $total   = 0.0;
        $removed = false;

        foreach ($lines as $giftId => $quantity) {
            if (! isset($gifts[$giftId])) {
                // Switched off, or no longer sold, since it was added: drop it rather than show a line that cannot be bought.
                $this->cart->remove($giftId);
                $removed = true;

                continue;
            }

            $gift     = $gifts[$giftId];
            $subtotal = (float) $gift['price'] * $quantity;
            $total   += $subtotal;
            $rows[]   = ['gift' => $gift, 'quantity' => $quantity, 'subtotal' => $subtotal];
        }

        if ($removed) {
            session()->setFlashdata('toastr_error', 'We took out a gift that is no longer available.');
        }

        return view('gifts/cart', [
            'rows'     => $rows,
            'total'    => $total,
            'currency' => $rows !== [] ? $rows[0]['gift']['currency'] : 'UGX',
            'maxLines' => GiftCart::MAX_LINES,
            'maxQty'   => GiftCart::MAX_QUANTITY,
        ], ['saveData' => false]);
    }

    public function add()
    {
        if (! $this->validate([
            'gift_id'  => 'required|is_natural_no_zero',
            'quantity' => 'permit_empty|is_natural_no_zero|less_than_equal_to[' . GiftCart::MAX_QUANTITY . ']',
        ])) {
            return redirect()->back()->with('toastr_error', 'Please choose a quantity from 1 to ' . GiftCart::MAX_QUANTITY . '.');
        }

        $giftId   = (int) $this->request->getPost('gift_id');
        $quantity = (int) ($this->request->getPost('quantity') ?: 1);
        $gift     = $this->giftModel->hostedByIds([$giftId])[$giftId] ?? null;

        if ($gift === null) {
            return redirect()->to(site_url('gifts'))->with('toastr_error', 'That gift is not available.');
        }

        $limit = $gift['stock_available'] === null ? null : (int) $gift['stock_available'];

        if ($limit === 0) {
            return redirect()->back()->with('toastr_error', '"' . $gift['name'] . '" is out of stock.');
        }

        if (! $this->cart->add($giftId, $quantity, $limit)) {
            return redirect()->back()->with('toastr_error', 'A cart can hold up to ' . GiftCart::MAX_LINES . ' different gifts. Please check out first.');
        }

        $kept = $this->cart->lines()[$giftId] ?? 0;
        $note = $limit !== null && $kept === $limit && $quantity > 0
            ? '"' . $gift['name'] . '" added. Only ' . $limit . ' in stock, so that is the most you can have.'
            : '"' . $gift['name'] . '" added to your cart.';

        return redirect()->to(site_url('gifts/cart'))->with('toastr_success', $note);
    }

    public function update()
    {
        $quantities = (array) $this->request->getPost('quantities');
        $gifts      = $this->giftModel->hostedByIds(array_keys($this->cart->lines()));

        foreach ($quantities as $giftId => $quantity) {
            $giftId = (int) $giftId;

            if (! isset($this->cart->lines()[$giftId])) {
                continue; // only lines already in the cart can be changed
            }

            $limit = isset($gifts[$giftId]) && $gifts[$giftId]['stock_available'] !== null ? (int) $gifts[$giftId]['stock_available'] : null;

            $this->cart->set($giftId, (int) $quantity, $limit);
        }

        return redirect()->to(site_url('gifts/cart'))->with('toastr_success', 'Cart updated.');
    }

    public function remove(int $giftId)
    {
        $this->cart->remove($giftId);

        return redirect()->to(site_url('gifts/cart'))->with('toastr_success', 'Removed from your cart.');
    }

    /**
     * Opens the checkout with the shop and sends the visitor there. The shop's address is the one it returned,
     * used as given and only when it is https; it is never built from an id.
     */
    public function checkout()
    {
        $result = $this->checkouts->start(
            $this->cart->lines(),
            static fn (string $token): string => site_url('gifts/checkout/' . $token)
        );

        if (! $result['success']) {
            return redirect()->to(site_url('gifts/cart'))->with('toastr_error', $result['message']);
        }

        $this->cart->sentAs($result['checkout']['public_token']);

        return redirect()->to($result['checkout']['checkout_url']);
    }

    /**
     * Where the shop sends the visitor back to, and where our own "check my order" link goes. It asks the shop
     * for the real state each time it is opened (at most every few seconds); what is in the address bar is not
     * trusted for anything.
     */
    public function purchase(string $token)
    {
        $checkout = $this->checkoutModel->findByToken($token);

        if ($checkout === null) {
            return redirect()->to(site_url('gifts'))->with('toastr_error', 'We could not find that order.');
        }

        $checkout = $this->checkouts->status($checkout);

        if ($checkout['state'] === VendorCheckoutModel::STATE_PAID && $this->cart->wasSentAs($token)) {
            $this->cart->clear();
        }

        $order = VendorCheckoutModel::order($checkout);

        return view('gifts/purchase', [
            'checkout'   => $checkout,
            'lines'      => VendorCheckoutModel::lines($checkout),
            'order'      => $order,
            'deliveries' => $order['deliveries'] ?? [],
            'waiting'    => in_array($checkout['state'], [VendorCheckoutModel::STATE_IN_CHECKOUT, VendorCheckoutModel::STATE_AWAITING_PAYMENT], true)
                && strtotime($checkout['created_at']) > strtotime('-1 hour'),
            'contact'    => config('CbsRadio'),
            'noindex'    => true,
        ], ['saveData' => false]);
    }
}
