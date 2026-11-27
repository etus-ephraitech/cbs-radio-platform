<?php

namespace App\Controllers\Admin;

use App\Services\RoleAdminService;

/**
 * Admin > Roles: create roles and choose what each may do. Superadmin only: the routes carry the package's
 * `role:superadmin` filter (Routes/cbs_radio.php). The rules and the writing are RoleAdminService's.
 */
class RoleController extends BaseAdminController
{
    protected RoleAdminService $roles;

    public function __construct()
    {
        $this->roles = new RoleAdminService();
    }

    public function index()
    {
        return ui_view('admin/roles/index', [
            'available' => $this->roles->available(),
            'roles'     => $this->roles->overview(),
            'groups'    => $this->roles->permissionGroups(),
        ]);
    }

    public function create()
    {
        return ui_view('admin/roles/form', [
            'role'      => null,
            'groups'    => $this->roles->permissionGroups(),
            'available' => $this->roles->available(),
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->rules(true))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name    = trim((string) $this->request->getPost('name'));
        $problem = $this->roles->create(
            $name,
            trim((string) $this->request->getPost('title')),
            trim((string) $this->request->getPost('description')),
            (array) $this->request->getPost('permissions'),
        );

        if ($problem !== null) {
            return redirect()->back()->withInput()->with('errors', [$problem]);
        }

        return redirect()->to(site_url('admin/roles/' . $name))->with('toastr_success', 'Role created.');
    }

    public function edit(string $name)
    {
        $role = $this->roles->find($name);

        if ($role === null) {
            return redirect()->to(site_url('admin/roles'))->with('toastr_error', 'That role was not found.');
        }

        return ui_view('admin/roles/form', [
            'role'      => $role,
            'groups'    => $this->roles->permissionGroups(),
            'available' => $this->roles->available(),
        ]);
    }

    public function update(string $name)
    {
        if (! $this->validate($this->rules(false))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $problem = $this->roles->update(
            $name,
            trim((string) $this->request->getPost('title')),
            trim((string) $this->request->getPost('description')),
            (array) $this->request->getPost('permissions'),
        );

        if ($problem !== null) {
            return redirect()->back()->withInput()->with('errors', [$problem]);
        }

        return redirect()->to(site_url('admin/roles/' . $name))->with('toastr_success', 'Role saved.');
    }

    public function release(string $name)
    {
        $problem = $this->roles->release($name);

        return redirect()->to(site_url('admin/roles/' . $name))->with(
            $problem === null ? 'toastr_success' : 'toastr_error',
            $problem ?? 'Handed back to the configuration. Run "php spark auth:sync" to restore its configured version.',
        );
    }

    public function destroy(string $name)
    {
        $problem = $this->roles->delete($name);

        if ($problem !== null) {
            return redirect()->to(site_url('admin/roles/' . $name))->with('toastr_error', $problem);
        }

        return redirect()->to(site_url('admin/roles'))->with('toastr_success', 'Role deleted.');
    }

    /**
     * @return array<string, string>
     */
    private function rules(bool $withName): array
    {
        $rules = [
            'title'       => 'required|max_length[100]',
            'description' => 'permit_empty|max_length[255]',
        ];

        if ($withName) {
            $rules['name'] = 'required|max_length[50]';
        }

        return $rules;
    }
}
