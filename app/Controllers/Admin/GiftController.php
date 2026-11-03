<?php

namespace App\Controllers\Admin;

use App\Models\GiftModel;
use Config\CbsRadio;

/**
 * Admin management of the gift catalog. Local gifts (e-cards, airtime,
 * shout-outs we run ourselves) are fully editable here. Vendor-sourced
 * gifts are not — their name/price/description come from
 * GiftVendorSyncService on each sync, so editing them here would just be
 * overwritten; an admin can still activate/deactivate a specific vendor
 * item to hide it from the catalog without touching the vendor connection
 * itself (see Admin\GiftVendorController for that).
 */
class GiftController extends BaseAdminController
{
    protected GiftModel $giftModel;
    protected CbsRadio $config;

    public function __construct()
    {
        $this->giftModel = new GiftModel();
        $this->config    = config('CbsRadio');
    }

    public function index()
    {
        return view('admin/gifts/index', [
            'localGifts'  => $this->giftModel->localOnly(),
            'vendorGifts' => $this->giftModel->vendorSourced(),
        ]);
    }

    public function create()
    {
        return view('admin/gifts/form', ['gift' => null, 'config' => $this->config]);
    }

    public function store()
    {
        if (! $this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = $this->giftModel->insert($this->collectInput());

        if ($id === false) {
            return redirect()->back()->withInput()->with('errors', $this->giftModel->errors());
        }

        return redirect()->to(site_url('admin/gifts'))->with('toastr_success', 'Gift created.');
    }

    public function edit(int $id)
    {
        $gift = $this->giftModel->find($id);

        if ($gift === null) {
            return redirect()->to(site_url('admin/gifts'))->with('toastr_error', 'Gift not found.');
        }

        if ($gift['source'] !== GiftModel::SOURCE_LOCAL) {
            return redirect()->to(site_url('admin/gifts'))->with('toastr_error', 'Vendor-sourced gifts are managed by the vendor sync, not edited here.');
        }

        return view('admin/gifts/form', ['gift' => $gift, 'config' => $this->config]);
    }

    public function update(int $id)
    {
        $gift = $this->giftModel->find($id);

        if ($gift === null || $gift['source'] !== GiftModel::SOURCE_LOCAL) {
            return redirect()->to(site_url('admin/gifts'))->with('toastr_error', 'This gift cannot be edited here.');
        }

        if (! $this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $ok = $this->giftModel->update($id, $this->collectInput());

        if (! $ok) {
            return redirect()->back()->withInput()->with('errors', $this->giftModel->errors());
        }

        return redirect()->to(site_url('admin/gifts'))->with('toastr_success', 'Gift updated.');
    }

    /**
     * Works for both local and vendor-sourced gifts — hiding a specific
     * vendor item from the catalog is a legitimate admin action even
     * though editing its details isn't.
     */
    public function deactivate(int $id)
    {
        $ok = $this->giftModel->update($id, ['is_active' => 0]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Gift deactivated.' : 'Could not deactivate this gift.'
        );
    }

    public function activate(int $id)
    {
        $ok = $this->giftModel->update($id, ['is_active' => 1]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Gift activated.' : 'Could not activate this gift.'
        );
    }

    protected function validationRules(): array
    {
        return [
            'name'  => 'required|max_length[150]',
            'type'  => 'required|in_list[ecard,airtime,shoutout,dedication,physical]',
            'price' => 'required|decimal|greater_than_equal_to[0]',
        ];
    }

    protected function collectInput(): array
    {
        return [
            'name'        => $this->request->getPost('name'),
            'type'        => $this->request->getPost('type'),
            'description' => $this->request->getPost('description'),
            'price'       => $this->request->getPost('price'),
            'currency'    => $this->request->getPost('currency') ?: $this->config->defaultCurrency,
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
            'source'      => GiftModel::SOURCE_LOCAL,
        ];
    }
}
