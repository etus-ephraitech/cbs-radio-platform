<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use RuntimeException;

/**
 * Shared base for every authenticated staff controller (the admin area and
 * the presenter portal).
 *
 * Route filtering (restricting /admin/* and /presenter/* to signed-in
 * staff) is done by the `auth:session` filter that ephraitech/ci4-auth
 * registers, applied to those route groups in Config/Routes/cbs_radio.php —
 * not duplicated here.
 */
abstract class BaseAdminController extends BaseController
{
    /**
     * The id of the signed-in staff member, recorded on everything they do
     * (moderation decisions, manual payments, settings changes, aired marks).
     *
     * ci4-auth's session guard is the source of truth. The plain
     * session('user_id') fallback is what the test suite's stand-in auth
     * filter (tests/_support/Filters/ActAsStaff.php) sets; a visitor cannot
     * write to the session, so it is not a way around the guard.
     *
     * @throws RuntimeException when nobody is signed in, which means the auth
     *                          filter is missing from the route group: failing
     *                          loudly beats recording the action against a
     *                          made-up user.
     */
    protected function currentUserId(): int
    {
        helper('auth');

        $userId = function_exists('user_id') ? user_id() : null;
        $userId ??= session()->get('user_id');

        if (empty($userId)) {
            throw new RuntimeException('No signed-in user — check the auth:session filter is applied to this route group.');
        }

        return (int) $userId;
    }
}
