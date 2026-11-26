<?php

namespace App\Controllers\Admin;

use App\Services\StaffUserService;
use Ephraitech\Auth\Exceptions\AuthException;
use Ephraitech\Auth\Exceptions\RegistrationException;
use Ephraitech\Auth\Exceptions\WeakPasswordException;

/**
 * Admin > Users: who can sign in, and as what. Superadmin only: the routes carry the package's `role:superadmin`
 * filter (Routes/cbs_radio.php), so nothing here re-checks it, and an administrator never sees these pages.
 *
 * A new account gets a generated password that is shown once, on the page you land on; it is never stored in clear
 * or sent anywhere. The same goes for a reset. Changing your own access, and removing the last active superadmin,
 * are refused (SuperAdminGuard).
 */
class UserController extends BaseAdminController
{
    protected StaffUserService $users;

    public function __construct()
    {
        $this->users = new StaffUserService();
    }

    public function index()
    {
        $filters = [
            'q'      => trim((string) $this->request->getGet('q')),
            'status' => (string) $this->request->getGet('status'),
            'role'   => (string) $this->request->getGet('role'),
        ];

        $result = $this->users->search($filters, (int) ($this->request->getGet('page') ?: 1));

        return ui_view('admin/users/index', [
            'users'      => $result['rows'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'totalPages' => (int) max(1, ceil($result['total'] / $result['perPage'])),
            'filters'    => $filters,
            'roles'      => $this->users->roleOptions(),
            'statuses'   => $this->users->statusOptions(),
            'meId'       => $this->currentUserId(),
        ]);
    }

    public function create()
    {
        return ui_view('admin/users/form', ['roles' => $this->users->roleOptions()]);
    }

    public function store()
    {
        if (! $this->validate(['email' => 'required|valid_email|max_length[254]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $made = $this->users->create(trim((string) $this->request->getPost('email')), (array) $this->request->getPost('roles'));
        } catch (RegistrationException $e) {
            return redirect()->back()->withInput()->with('errors', array_map(static fn (array $m): string => implode(' ', $m), $e->getErrors()));
        } catch (AuthException $e) {
            log_message('error', 'Creating a user failed: ' . $e->getMessage());

            return redirect()->back()->withInput()->with('toastr_error', 'The account could not be created. Please try again.');
        }

        // Shown once on the next page, then gone: flash data lives for exactly one request.
        return redirect()->to(site_url('admin/users/' . $made['user']->id))
            ->with('toastr_success', 'Account created.')
            ->with('generated_password', $made['password']);
    }

    public function show(int $id)
    {
        $user = $this->users->find($id);

        if ($user === null) {
            return redirect()->to(site_url('admin/users'))->with('toastr_error', 'That user was not found.');
        }

        return ui_view('admin/users/show', [
            'user'     => $user,
            'roles'    => $this->users->roleOptions(),
            'statuses' => $this->users->statusOptions(),
            'isMe'     => $id === $this->currentUserId(),
            'password' => session()->getFlashdata('generated_password'),
        ]);
    }

    public function updateRoles(int $id)
    {
        $problem = $this->users->setRoles($this->currentUserId(), $id, (array) $this->request->getPost('roles'));

        return $this->back($id, $problem, 'Roles updated.');
    }

    public function updateStatus(int $id)
    {
        $problem = $this->users->setStatus(
            $this->currentUserId(),
            $id,
            (string) $this->request->getPost('status'),
            (string) $this->request->getPost('reason'),
        );

        return $this->back($id, $problem, 'Status updated.');
    }

    public function resetPassword(int $id)
    {
        try {
            $result = $this->users->resetPassword($id);
        } catch (WeakPasswordException|AuthException $e) {
            log_message('error', 'Resetting a password failed: ' . $e->getMessage());

            return $this->back($id, 'The password could not be reset. Please try again.', '');
        }

        if ($result['error'] !== null) {
            return $this->back($id, $result['error'], '');
        }

        return redirect()->to(site_url('admin/users/' . $id))
            ->with('toastr_success', 'New password set. Their other sign-ins were ended.')
            ->with('generated_password', $result['password']);
    }

    public function destroy(int $id)
    {
        $problem = $this->users->delete($this->currentUserId(), $id);

        if ($problem !== null) {
            return $this->back($id, $problem, '');
        }

        return redirect()->to(site_url('admin/users'))->with('toastr_success', 'Account deleted.');
    }

    private function back(int $id, ?string $problem, string $done)
    {
        return redirect()->to(site_url('admin/users/' . $id))->with($problem === null ? 'toastr_success' : 'toastr_error', $problem ?? $done);
    }
}
