<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Filters\ActAsRole;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Admin > Appearance is for superadmins only: both routes carry the `role:superadmin` filter, so an administrator
 * without the role can neither see the page nor change the look for everyone.
 *
 * @internal
 */
final class AppearanceAccessTest extends AppFeatureTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function appearanceRoutes(): array
    {
        return [
            'the page' => ['get', 'admin/appearance'],
            'saving'   => ['post', 'admin/appearance'],
        ];
    }

    #[DataProvider('appearanceRoutes')]
    public function testSomeoneWithoutTheSuperadminRoleIsRefused(string $verb, string $path): void
    {
        $result = $this->withSession([ActAsRole::DENY_KEY => true])->{$verb}($path);

        $this->assertSame(403, $result->response()->getStatusCode());
    }

    public function testASuperadminSeesThePage(): void
    {
        $this->get('admin/appearance')->assertOK()->assertSee('Colour mode');
    }
}
