<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Filters\ActAsArea;
use Tests\Support\Filters\ActAsRole;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Admin > Roles is for superadmins only, and each admin area needs its permission. The real filters belong to the
 * auth package and to AreaAccess (tested on its own); these tests prove the project put them on every route.
 *
 * @internal
 */
final class RolesAccessTest extends AppFeatureTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rolesRoutes(): array
    {
        return [
            'the list'      => ['get', 'admin/roles'],
            'the new form'  => ['get', 'admin/roles/create'],
            'creating'      => ['post', 'admin/roles'],
            'one role'      => ['get', 'admin/roles/admin'],
            'saving'        => ['post', 'admin/roles/admin/update'],
            'releasing'     => ['post', 'admin/roles/admin/release'],
            'deleting'      => ['post', 'admin/roles/admin/delete'],
        ];
    }

    #[DataProvider('rolesRoutes')]
    public function testSomeoneWithoutTheSuperadminRoleIsRefused(string $verb, string $path): void
    {
        $result = $this->withSession([ActAsRole::DENY_KEY => true])->{$verb}($path);

        $this->assertSame(403, $result->response()->getStatusCode());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function areaRoutes(): array
    {
        return [
            'bookings'          => ['admin/bookings'],
            'moderation'        => ['admin/moderation'],
            'payments'          => ['admin/payments'],
            'gift orders'       => ['admin/gift-orders'],
            'shop purchases'    => ['admin/gift-purchases'],
            'gifts'             => ['admin/gifts'],
            'gift vendors'      => ['admin/gift-vendors'],
            'notifications'     => ['admin/notifications'],
            'categories'        => ['admin/categories'],
            'shows'             => ['admin/shows'],
            'packages'          => ['admin/rate-tiers'],
            'message templates' => ['admin/message-templates'],
            'settings'          => ['admin/settings'],
        ];
    }

    #[DataProvider('areaRoutes')]
    public function testAnAreaIsRefusedToARoleWithoutItsPermission(string $path): void
    {
        $result = $this->withSession([ActAsArea::DENY_KEY => true])->get($path);

        $this->assertSame(403, $result->response()->getStatusCode());
    }

    public function testTheDashboardStaysOpenToAllStaff(): void
    {
        $this->withSession([ActAsArea::DENY_KEY => true])->get('admin')->assertOK();
    }
}
