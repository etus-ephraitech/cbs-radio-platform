<?php

namespace App\Controllers\Admin;

use App\Models\GiftVendorModel;
use App\Services\GiftVendorSyncService;

/**
 * Admin CRUD for third-party gift vendors. Adding a vendor here only
 * registers the connection (name, slug, API base URL) — the vendor's own
 * API key is added separately to .env as vendor.{slug}.apiKey, which this
 * controller deliberately never asks for or stores in the database.
 */
class GiftVendorController extends BaseAdminController
{
    protected GiftVendorModel $giftVendorModel;
    protected GiftVendorSyncService $syncService;

    public function __construct()
    {
        $this->giftVendorModel = new GiftVendorModel();
        $this->syncService     = new GiftVendorSyncService();
    }

    public function index()
    {
        return ui_view('admin/gift_vendors/index', [
            'vendors' => $this->giftVendorModel->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function create()
    {
        return ui_view('admin/gift_vendors/form', ['vendor' => null]);
    }

    public function store()
    {
        if (! $this->validate(['name' => 'required|max_length[150]', 'api_base_url' => 'permit_empty|valid_url'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name = $this->request->getPost('name');

        $id = $this->giftVendorModel->insert([
            'name'         => $name,
            'slug'         => $this->giftVendorModel->generateUniqueSlug($name),
            'api_base_url' => $this->request->getPost('api_base_url'),
            'is_active'    => $this->request->getPost('is_active') ? 1 : 0,
        ]);

        if ($id === false) {
            return redirect()->back()->withInput()->with('errors', $this->giftVendorModel->errors());
        }

        $vendor = $this->giftVendorModel->find($id);

        return redirect()->to(site_url('admin/gift-vendors'))->with(
            'toastr_success',
            'Vendor created. Add vendor.' . $vendor['slug'] . '.apiKey to .env before syncing.'
        );
    }

    public function edit(int $id)
    {
        $vendor = $this->giftVendorModel->find($id);

        if ($vendor === null) {
            return redirect()->to(site_url('admin/gift-vendors'))->with('toastr_error', 'Vendor not found.');
        }

        return ui_view('admin/gift_vendors/form', ['vendor' => $vendor]);
    }

    public function update(int $id)
    {
        $vendor = $this->giftVendorModel->find($id);

        if ($vendor === null) {
            return redirect()->to(site_url('admin/gift-vendors'))->with('toastr_error', 'Vendor not found.');
        }

        if (! $this->validate(['name' => 'required|max_length[150]', 'api_base_url' => 'permit_empty|valid_url'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name = $this->request->getPost('name');
        $slug = $name === $vendor['name'] ? $vendor['slug'] : $this->giftVendorModel->generateUniqueSlug($name, $id);

        $ok = $this->giftVendorModel->update($id, [
            'name'         => $name,
            'slug'         => $slug,
            'api_base_url' => $this->request->getPost('api_base_url'),
            'is_active'    => $this->request->getPost('is_active') ? 1 : 0,
        ]);

        if (! $ok) {
            return redirect()->back()->withInput()->with('errors', $this->giftVendorModel->errors());
        }

        return redirect()->to(site_url('admin/gift-vendors'))->with('toastr_success', 'Vendor updated.');
    }

    /**
     * Vendors are deactivated, not deleted — gifts.vendor_id references
     * them, and a hard delete would orphan synced products.
     */
    public function deactivate(int $id)
    {
        $ok = $this->giftVendorModel->update($id, ['is_active' => 0]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Vendor deactivated — their products stay but will no longer re-sync.' : 'Could not deactivate this vendor.'
        );
    }

    public function activate(int $id)
    {
        $ok = $this->giftVendorModel->update($id, ['is_active' => 1]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Vendor activated.' : 'Could not activate this vendor.'
        );
    }

    /**
     * Manual "sync now" trigger from the admin UI, in addition to the
     * scheduled `php spark gifts:sync` cron run.
     */
    public function syncNow()
    {
        $result = $this->syncService->syncAll();

        $message = "Synced {$result['synced']} product(s).";

        if (! empty($result['errors'])) {
            $message .= ' Errors: ' . implode('; ', $result['errors']);
        }

        return redirect()->back()->with(empty($result['errors']) ? 'toastr_success' : 'toastr_error', $message);
    }
}
