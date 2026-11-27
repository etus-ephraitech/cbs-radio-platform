<?php

namespace Tests\Unit\Libraries;

use App\Libraries\RoleRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\UnitTestCase;

/**
 * The limits on editing roles, as the page words them.
 *
 * @internal
 */
final class RoleRulesTest extends UnitTestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function names(): array
    {
        return [
            'plain'           => ['moderator', true],
            'with a dash'     => ['gift-desk', true],
            'with underscore' => ['gift_desk', true],
            'with digits'     => ['team2', true],
            'empty'           => ['', false],
            'capital'         => ['Moderator', false],
            'space'           => ['gift desk', false],
            'leading dash'    => ['-desk', false],
            'taken'           => ['admin', false],
            'reserved'        => ['create', false],
            'too long'        => [str_repeat('a', 51), false],
        ];
    }

    #[DataProvider('names')]
    public function testNames(string $name, bool $fine): void
    {
        $problem = RoleRules::nameProblem($name, ['admin', 'user']);

        $fine ? $this->assertNull($problem) : $this->assertNotNull($problem);
    }

    public function testTheSuperRolesPermissionsAreNotEdited(): void
    {
        $this->assertNotNull(RoleRules::permissionsBlocker('superadmin', 'superadmin'));
        $this->assertNull(RoleRules::permissionsBlocker('admin', 'superadmin'));
        $this->assertNull(RoleRules::permissionsBlocker('admin', null));
    }

    public function testWhenARoleCanBeDeleted(): void
    {
        $this->assertNotNull(RoleRules::deleteBlocker('superadmin', 'superadmin', true, 0), 'the super role');
        $this->assertNotNull(RoleRules::deleteBlocker('admin', 'superadmin', true, 0), 'defined in config');
        $this->assertStringContainsString('One person', (string) RoleRules::deleteBlocker('desk', 'superadmin', false, 1));
        $this->assertStringContainsString('3 people', (string) RoleRules::deleteBlocker('desk', 'superadmin', false, 3));
        $this->assertNull(RoleRules::deleteBlocker('desk', 'superadmin', false, 0));
    }

    public function testOnlyKnownPermissionsSurviveAndOnceEach(): void
    {
        $kept = RoleRules::onlyKnown(['gifts.manage', 'nope', 'gifts.manage', 7, ['x'], 'settings.manage'], ['bookings.manage', 'gifts.manage', 'settings.manage']);

        $this->assertSame(['gifts.manage', 'settings.manage'], $kept);
        $this->assertSame([], RoleRules::onlyKnown([], ['gifts.manage']));
    }

    public function testPermissionsAreGroupedByAreaAndSorted(): void
    {
        $groups = RoleRules::groupByArea([
            'users.view'      => 'View users',
            'gifts.manage'    => 'Gifts',
            'users.create'    => 'Create users',
            'legacy'          => 'No dot',
        ]);

        $this->assertSame(['gifts', 'other', 'users'], array_keys($groups));
        $this->assertSame(['users.create', 'users.view'], array_keys($groups['users']));
        $this->assertSame(['legacy' => 'No dot'], $groups['other']);
    }
}
