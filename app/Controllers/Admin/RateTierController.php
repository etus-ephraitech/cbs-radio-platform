<?php

namespace App\Controllers\Admin;

use App\Models\CategoryModel;
use App\Models\RateTierModel;
use Config\CbsRadio;

/**
 * Admin CRUD for rate tiers (the pricing packages under each category —
 * Basic Text, Voice Note + Text, Prime Slot, etc.). The includes_on_air
 * field here is the direct fix for the Milestone/Achievement ambiguity
 * flagged in the scope sign-off doc: it's now an explicit, editable flag
 * per tier instead of something buried in a rate-card PDF.
 */
class RateTierController extends BaseAdminController
{
    protected RateTierModel $rateTierModel;
    protected CategoryModel $categoryModel;
    protected CbsRadio $config;

    public function __construct()
    {
        $this->rateTierModel = new RateTierModel();
        $this->categoryModel = new CategoryModel();
        $this->config        = config('CbsRadio');
    }

    public function index()
    {
        return ui_view('admin/rate_tiers/index', [
            'rateTiers' => $this->rateTierModel->allWithCategory(),
        ]);
    }

    public function create()
    {
        return ui_view('admin/rate_tiers/form', [
            'rateTier'   => null,
            'categories' => $this->categoryModel->active(),
            'config'     => $this->config,
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = $this->rateTierModel->insert($this->collectInput());

        if ($id === false) {
            return redirect()->back()->withInput()->with('errors', $this->rateTierModel->errors());
        }

        return redirect()->to(site_url('admin/rate-tiers'))->with('toastr_success', 'Package created.');
    }

    public function edit(int $id)
    {
        $rateTier = $this->rateTierModel->find($id);

        if ($rateTier === null) {
            return redirect()->to(site_url('admin/rate-tiers'))->with('toastr_error', 'Package not found.');
        }

        return ui_view('admin/rate_tiers/form', [
            'rateTier'   => $rateTier,
            'categories' => $this->categoryModel->active(),
            'config'     => $this->config,
        ]);
    }

    public function update(int $id)
    {
        $rateTier = $this->rateTierModel->find($id);

        if ($rateTier === null) {
            return redirect()->to(site_url('admin/rate-tiers'))->with('toastr_error', 'Package not found.');
        }

        if (! $this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $ok = $this->rateTierModel->update($id, $this->collectInput());

        if (! $ok) {
            return redirect()->back()->withInput()->with('errors', $this->rateTierModel->errors());
        }

        return redirect()->to(site_url('admin/rate-tiers'))->with('toastr_success', 'Package updated.');
    }

    /**
     * Rate tiers are deactivated, not deleted — bookings.rate_tier_id
     * references them, and a hard delete would orphan that history.
     */
    public function deactivate(int $id)
    {
        $ok = $this->rateTierModel->update($id, ['is_active' => 0]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Package deactivated.' : 'Could not deactivate this package.'
        );
    }

    public function activate(int $id)
    {
        $ok = $this->rateTierModel->update($id, ['is_active' => 1]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Package activated.' : 'Could not activate this package.'
        );
    }

    protected function validationRules(): array
    {
        return [
            'category_id'   => 'required|is_natural_no_zero',
            'package_level' => 'required|max_length[100]',
            'price'         => 'required|decimal|greater_than_equal_to[0]',
        ];
    }

    protected function collectInput(): array
    {
        return [
            'category_id'     => (int) $this->request->getPost('category_id'),
            'package_level'   => $this->request->getPost('package_level'),
            'features'        => $this->request->getPost('features'),
            'includes_on_air' => $this->request->getPost('includes_on_air') ? 1 : 0,
            'price'           => $this->request->getPost('price'),
            'currency'        => $this->request->getPost('currency') ?: $this->config->defaultCurrency,
            'is_active'       => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }
}
