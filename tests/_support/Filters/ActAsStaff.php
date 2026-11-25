<?php

namespace Tests\Support\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Tests\Support\Libraries\StaffUser;

/**
 * Stands in for the starter kit's real `auth` filter in feature tests, so the suite checks what this project
 * does behind the login rather than depending on how the login works. It signs the request in as a real user row.
 *
 * (That the real filter really blocks the public is the starter kit's own concern, and is not tested here.)
 */
class ActAsStaff implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        session()->set('user_id', StaffUser::id());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
