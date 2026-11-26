<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Filters\ActAsRole;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Admin > Users is for superadmins only. The real `role:superadmin` filter belongs to the auth package; these tests
 * prove the project put it on every route of the area, so a signed-in administrator who is not a superadmin is
 * refused on each of them (the controller is never reached), whatever the verb.
 *
 * @internal
 */
final class UsersAccessTest extends AppFeatureTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function usersRoutes(): array
    {
        return [
            'the list'            => ['get', 'admin/users'],
            'the new user form'   => ['get', 'admin/users/create'],
            'creating a user'     => ['post', 'admin/users'],
            'one user'            => ['get', 'admin/users/3'],
            'changing roles'      => ['post', 'admin/users/3/roles'],
            'changing status'     => ['post', 'admin/users/3/status'],
            'resetting a password' => ['post', 'admin/users/3/reset-password'],
            'deleting a user'     => ['post', 'admin/users/3/delete'],
        ];
    }

    #[DataProvider('usersRoutes')]
    public function testSomeoneWithoutTheSuperadminRoleIsRefused(string $verb, string $path): void
    {
        $result = $this->withSession([ActAsRole::DENY_KEY => true])->{$verb}($path);

        $this->assertSame(403, $result->response()->getStatusCode());
    }

    public function testTheRestOfTheAdminAreaStaysOpenToAnAdministrator(): void
    {
        $this->withSession([ActAsRole::DENY_KEY => true])->get('admin/settings')->assertOK();
    }
}
