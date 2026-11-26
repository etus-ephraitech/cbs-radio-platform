<?php

namespace Tests\Support\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Stands in for the auth package's `role` filter in feature tests. Whoever the test signs in is allowed through,
 * unless the test said otherwise with the session key `test_not_in_role`: then the request is refused with a 403,
 * which is how a signed-in person without the role is turned away. That the real filter checks the real role
 * tables is the package's concern; what these tests prove is that the project put the filter on the right routes.
 */
class ActAsRole implements FilterInterface
{
    public const DENY_KEY = 'test_not_in_role';

    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get(self::DENY_KEY)) {
            return service('response')->setStatusCode(403)->setBody('Forbidden');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
