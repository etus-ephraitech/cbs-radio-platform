<?php

namespace App\Libraries;

/**
 * The safety rules for changing another person's access, kept apart from the framework so they can be tested on
 * their own. A superadmin can do everything, including lock everyone out, so two things are never allowed:
 *
 *   - changing your own roles, status or account (ask another superadmin: it stops a slip of the mouse from
 *     removing the one person who could undo it);
 *   - leaving the site without an active superadmin.
 */
final class SuperAdminGuard
{
    public const ROLE = 'superadmin';

    /**
     * Why a change must not happen, in words for the person who tried it, or null when it is fine.
     *
     * @param string       $action          'roles', 'status' or 'delete'
     * @param list<string> $newRoles        the roles the person would have afterwards (for 'roles')
     * @param string       $newStatus       the status they would have afterwards (for 'status')
     * @param bool         $targetIsSuper   whether the person is a superadmin now
     * @param bool         $targetIsActive  whether the person is active now
     * @param int          $otherActiveSupers how many OTHER active superadmins there are
     */
    public static function violation(
        string $action,
        int $actorId,
        int $targetId,
        bool $targetIsSuper,
        bool $targetIsActive,
        int $otherActiveSupers,
        array $newRoles = [],
        string $newStatus = 'active',
    ): ?string {
        if ($actorId === $targetId) {
            return match ($action) {
                'roles'  => 'You cannot change your own roles. Ask another superadmin.',
                'status' => 'You cannot suspend or deactivate your own account.',
                default  => 'You cannot delete your own account.',
            };
        }

        $losesSuper = match ($action) {
            'roles'  => $targetIsSuper && ! in_array(self::ROLE, $newRoles, true),
            'status' => $targetIsSuper && $targetIsActive && $newStatus !== 'active',
            default  => $targetIsSuper && $targetIsActive,
        };

        if ($losesSuper && $otherActiveSupers < 1) {
            return 'That would leave the site without an active superadmin. Make someone else a superadmin first.';
        }

        return null;
    }
}
