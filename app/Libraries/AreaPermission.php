<?php

namespace App\Libraries;

use Ephraitech\Auth\Models\PermissionModel;

/**
 * Whether the signed-in person may use an area of the admin (bookings, gifts, catalog, notifications, settings).
 * Each area is one permission (`gifts.manage`, ...) that Admin > Roles grants to a role; a superadmin passes every
 * check by the package's own rule.
 *
 * Rolling out: until `php spark auth:sync` has created a permission, there is nothing to grant, so an area whose
 * permission does not exist yet stays open to signed-in staff, exactly as it was before roles had a say. Once the
 * permission exists it is enforced. This keeps a deploy from locking every administrator out in the window between
 * the new code and the sync.
 */
final class AreaPermission
{
    /** area permission => the words used for it in messages */
    public const AREAS = [
        'bookings.manage'      => 'bookings',
        'gifts.manage'         => 'gifts',
        'catalog.manage'       => 'the catalog',
        'notifications.manage' => 'notifications',
        'settings.manage'      => 'settings',
    ];

    /**
     * The rule itself, free of the framework so it can be tested alone.
     */
    public static function decide(bool $permissionExists, bool $personHasIt): bool
    {
        return ! $permissionExists || $personHasIt;
    }

    /**
     * Whether the signed-in person may use the area. Anything that goes wrong reading the auth tables means "yes"
     * for a missing permission and "no" for a failed check on one that exists.
     */
    public static function allows(string $permission): bool
    {
        try {
            helper('auth');

            $exists = (new PermissionModel())->findByName($permission) !== null;

            return self::decide($exists, $exists && can($permission));
        } catch (\Throwable $e) {
            log_message('error', 'Checking {permission} failed: {message}', ['permission' => $permission, 'message' => $e->getMessage()]);

            return false;
        }
    }
}
