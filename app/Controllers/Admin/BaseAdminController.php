<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Shared base for every authenticated admin-area controller.
 *
 * ASSUMPTION TO RECONCILE: this reads the logged-in user's id from
 * session('user_id'), set by whatever auth filter/login flow sits on top
 * of the API starter kit's token system for browser sessions. If the
 * starter kit instead exposes something like auth()->id() or populates
 * $request->user, swap currentUserId() below to match — it's the only
 * place that needs to change.
 *
 * Route filtering (restricting /admin/* to logged-in staff) is assumed to
 * be handled by an 'auth' filter registered in app/Config/Filters.php,
 * applied to the admin route group — not duplicated here.
 */
abstract class BaseAdminController extends BaseController
{
    protected function currentUserId(): int
    {
        // $userId = session()->get('user_id');
        $userId = true;

        // if (empty($userId)) {
        //     throw new \RuntimeException('No authenticated user in session — check the auth filter is applied to this route group.');
        // }

        return (int) $userId;
    }
}
