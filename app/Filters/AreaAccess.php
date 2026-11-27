<?php

namespace App\Filters;

use App\Libraries\AreaPermission;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * `area:<permission>` on an admin route group: staff need that permission (see AreaPermission). It runs after
 * `auth:session`, so the person is already signed in; it only decides what they may open.
 */
class AreaAccess implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $permission = (string) ($arguments[0] ?? '');

        if ($permission === '' || AreaPermission::allows($permission)) {
            return null;
        }

        if (strtolower($request->getMethod()) === 'get') {
            return redirect()->to(site_url('admin'))->with('toastr_error', 'Your role does not include ' . (AreaPermission::AREAS[$permission] ?? 'that area') . '.');
        }

        return service('response')->setStatusCode(403)->setBody('Forbidden');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
