<?php

namespace Tests\Support\TestCase;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters as FiltersConfig;
use Config\Services;
use Tests\Support\Filters\ActAsRole;
use Tests\Support\Filters\ActAsStaff;

/**
 * Base for tests that make whole requests: routes, filters, controllers and views.
 *
 * Three things are replaced, in the filter configuration only:
 *   - the `auth` filter, with one that signs the request in as a real staff user (see ActAsStaff), so these tests
 *     do not depend on how the starter kit's login works;
 *   - the package's `role` filter, with one that lets the signed-in test user through unless a test asks for a refusal
 *     (see ActAsRole);
 *   - the CSRF filter, removed, so a test can post a form without first fetching and echoing a token.
 *
 * Public pages are unaffected: they were never behind `auth`.
 */
abstract class AppFeatureTestCase extends AppTestCase
{
    use FeatureTestTrait {
        call as private callThroughTheFramework;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->installTestFilters();
    }

    protected function afterConfigReload(): void
    {
        $this->installTestFilters();
    }

    /**
     * Every request starts with a fresh response and a fresh view renderer, as it does for a real visitor.
     *
     * The framework keeps one shared response object and one shared renderer. In production they live for a single
     * request, but in a test they would carry the headers of the previous request, and the variables it gave its
     * view, into the next (even from the previous test), so a page could seem to send a header it never set, or lose
     * its navigation. get(), post() and the rest all come through here.
     *
     * @return \CodeIgniter\Test\TestResponse
     */
    public function call(string $method, string $path, ?array $params = null)
    {
        Services::resetSingle('response');
        Services::resetSingle('renderer');

        return $this->callThroughTheFramework($method, $path, $params);
    }

    /**
     * A flash message set by the last request ('toastr_success', 'toastr_error', 'errors').
     *
     * The framework resets the session at the start of every call, so a message set before a redirect is
     * read here, from what that request left behind, rather than by following the redirect.
     */
    protected function flash(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Switches off texts and emails for a test that makes bookings but is not about messages, so that nothing is
     * logged, and nothing could ever be sent, as a side effect.
     */
    protected function silenceNotifications(): void
    {
        config('Notifications')->channelsEnabled = ['sms' => false, 'email' => false];
    }

    private function installTestFilters(): void
    {
        $filters = new FiltersConfig();

        $filters->aliases['auth'] = ActAsStaff::class;
        $filters->aliases['role'] = ActAsRole::class;

        foreach (['before', 'after'] as $when) {
            $filters->globals[$when] = array_filter(
                $filters->globals[$when] ?? [],
                static fn ($value, $key) => $value !== 'csrf' && $key !== 'csrf',
                ARRAY_FILTER_USE_BOTH
            );
        }

        Factories::injectMock('config', 'Filters', $filters);
    }
}
