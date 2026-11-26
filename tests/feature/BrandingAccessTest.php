<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Filters\ActAsRole;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * Admin > Logo is for superadmins only, like Users: every route carries the `role:superadmin` filter, so a signed-in
 * administrator without the role is refused before any controller runs, on the page and on each action.
 *
 * @internal
 */
final class BrandingAccessTest extends AppFeatureTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function brandingRoutes(): array
    {
        return [
            'the page'              => ['get', 'admin/branding'],
            'uploading the logo'    => ['post', 'admin/branding/upload/logo'],
            'uploading the icon'    => ['post', 'admin/branding/upload/favicon'],
            'removing the logo'     => ['post', 'admin/branding/remove/logo'],
            'saving the description' => ['post', 'admin/branding/alt'],
        ];
    }

    #[DataProvider('brandingRoutes')]
    public function testSomeoneWithoutTheSuperadminRoleIsRefused(string $verb, string $path): void
    {
        $result = $this->withSession([ActAsRole::DENY_KEY => true])->{$verb}($path);

        $this->assertSame(403, $result->response()->getStatusCode());
    }

    public function testASuperadminSeesThePage(): void
    {
        $this->get('admin/branding')->assertOK()->assertSee('Browser tab icon');
    }
}
