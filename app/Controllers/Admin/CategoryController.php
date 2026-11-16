<?php

namespace App\Controllers\Admin;

use App\Models\CategoryModel;

/**
 * Admin CRUD for booking categories (Birthdays, Tributes, Milestones,
 * etc.). Slugs are generated automatically from the name — never entered
 * by hand — so the public booking URLs (/booking/{slug}) stay predictable.
 */
class CategoryController extends BaseAdminController
{
    protected CategoryModel $categoryModel;

    public function __construct()
    {
        $this->categoryModel = new CategoryModel();
    }

    public function index()
    {
        return ui_view('admin/categories/index', [
            'categories' => $this->categoryModel->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function create()
    {
        return ui_view('admin/categories/form', [
            'category' => null,
        ]);
    }

    public function store()
    {
        $rules = [
            'name'        => 'required|max_length[100]',
            'description' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name = $this->request->getPost('name');

        $id = $this->categoryModel->insert([
            'name'        => $name,
            'slug'        => $this->categoryModel->generateUniqueSlug($name),
            'description' => $this->request->getPost('description'),
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
        ]);

        if ($id === false) {
            return redirect()->back()->withInput()->with('errors', $this->categoryModel->errors());
        }

        return redirect()->to(site_url('admin/categories'))->with('toastr_success', 'Category created.');
    }

    public function edit(int $id)
    {
        $category = $this->categoryModel->find($id);

        if ($category === null) {
            return redirect()->to(site_url('admin/categories'))->with('toastr_error', 'Category not found.');
        }

        return ui_view('admin/categories/form', ['category' => $category]);
    }

    public function update(int $id)
    {
        $category = $this->categoryModel->find($id);

        if ($category === null) {
            return redirect()->to(site_url('admin/categories'))->with('toastr_error', 'Category not found.');
        }

        $rules = [
            'name'        => 'required|max_length[100]',
            'description' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name = $this->request->getPost('name');

        // Only regenerate the slug if the name actually changed, so
        // existing public links to this category don't silently break
        // every time an admin tweaks the description.
        $slug = $name === $category['name']
            ? $category['slug']
            : $this->categoryModel->generateUniqueSlug($name, $id);

        $ok = $this->categoryModel->update($id, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $this->request->getPost('description'),
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
        ]);

        if (! $ok) {
            return redirect()->back()->withInput()->with('errors', $this->categoryModel->errors());
        }

        return redirect()->to(site_url('admin/categories'))->with('toastr_success', 'Category updated.');
    }

    /**
     * Categories are deactivated, not deleted — existing bookings and
     * rate tiers reference category_id, and a hard delete would orphan
     * that history. "Delete" in the UI performs this.
     */
    public function deactivate(int $id)
    {
        $ok = $this->categoryModel->update($id, ['is_active' => 0]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Category deactivated.' : 'Could not deactivate this category.'
        );
    }

    public function activate(int $id)
    {
        $ok = $this->categoryModel->update($id, ['is_active' => 1]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Category activated.' : 'Could not activate this category.'
        );
    }
}
