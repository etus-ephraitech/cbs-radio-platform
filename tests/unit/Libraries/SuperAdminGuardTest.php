<?php

namespace Tests\Unit\Libraries;

use App\Libraries\SuperAdminGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The rules that stop a superadmin locking everyone out. No framework, no database: just the decisions.
 *
 * @internal
 */
final class SuperAdminGuardTest extends TestCase
{
    public function testSomeoneElsesAccessCanBeChangedFreely(): void
    {
        $this->assertNull(SuperAdminGuard::violation('roles', 1, 2, false, true, 1, ['admin']));
        $this->assertNull(SuperAdminGuard::violation('status', 1, 2, false, true, 1, [], 'suspended'));
        $this->assertNull(SuperAdminGuard::violation('delete', 1, 2, false, true, 1));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function actions(): array
    {
        return ['roles' => ['roles'], 'status' => ['status'], 'delete' => ['delete']];
    }

    #[DataProvider('actions')]
    public function testNobodyChangesTheirOwnAccess(string $action): void
    {
        $message = SuperAdminGuard::violation($action, 5, 5, true, true, 3, ['superadmin'], 'active');

        $this->assertIsString($message);
        $this->assertStringContainsString('your own', $message);
    }

    public function testTheLastActiveSuperadminCannotLoseTheRole(): void
    {
        $message = SuperAdminGuard::violation('roles', 1, 2, true, true, 0, ['admin']);

        $this->assertStringContainsString('without an active superadmin', (string) $message);
    }

    public function testTheLastActiveSuperadminCannotBeSuspendedOrDeleted(): void
    {
        $this->assertNotNull(SuperAdminGuard::violation('status', 1, 2, true, true, 0, [], 'suspended'));
        $this->assertNotNull(SuperAdminGuard::violation('delete', 1, 2, true, true, 0));
    }

    public function testASuperadminCanBeRemovedWhenAnotherActiveOneRemains(): void
    {
        $this->assertNull(SuperAdminGuard::violation('roles', 1, 2, true, true, 1, ['admin']));
        $this->assertNull(SuperAdminGuard::violation('status', 1, 2, true, true, 1, [], 'suspended'));
        $this->assertNull(SuperAdminGuard::violation('delete', 1, 2, true, true, 1));
    }

    public function testKeepingTheSuperadminRoleIsNotALoss(): void
    {
        $this->assertNull(SuperAdminGuard::violation('roles', 1, 2, true, true, 0, ['superadmin', 'admin']));
    }

    public function testAnInactiveSuperadminCanBeDeletedWhenNoOtherIsActive(): void
    {
        // They were not keeping the site reachable anyway.
        $this->assertNull(SuperAdminGuard::violation('delete', 1, 2, true, false, 0));
    }
}
