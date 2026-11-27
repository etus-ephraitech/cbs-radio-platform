<?php

namespace Tests\Unit\Libraries;

use App\Libraries\AreaPermission;
use Tests\Support\TestCase\UnitTestCase;

/**
 * The area rule: a permission that exists is enforced, one that has not been synced yet leaves the area open.
 *
 * @internal
 */
final class AreaPermissionTest extends UnitTestCase
{
    public function testAnExistingPermissionIsEnforced(): void
    {
        $this->assertTrue(AreaPermission::decide(true, true));
        $this->assertFalse(AreaPermission::decide(true, false));
    }

    public function testAPermissionNotSyncedYetLeavesTheAreaOpen(): void
    {
        $this->assertTrue(AreaPermission::decide(false, false));
    }

    public function testEveryAreaPermissionIsDefinedAndGrantedToAdministrators(): void
    {
        $config = new \Config\Auth();

        foreach (array_keys(AreaPermission::AREAS) as $permission) {
            $this->assertArrayHasKey($permission, $config->permissions, $permission . ' is not defined in Config\Auth');
            $this->assertContains($permission, $config->matrix['admin'], $permission . ' is not granted to administrators');
        }
    }
}
