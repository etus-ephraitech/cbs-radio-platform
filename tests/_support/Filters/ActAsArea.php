<?php

namespace Tests\Support\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Stands in for the project's `area` filter in feature tests: the signed-in test user may open every area unless
 * the test says otherwise with the session key `test_no_area`, which turns the request away with a 403. What the
 * real filter decides is tested on its own (AreaPermissionTest); these tests prove the right routes carry it.
 */
class ActAsArea implements FilterInterface
{
    public const DENY_KEY = 'test_no_area';

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
