<?php

namespace App\Filters;

use App\Libraries\SiteStatusPage;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Turns visitors away from the public site while it is "coming soon" or "under maintenance"
 * (Admin > Settings > Site status). In "live" mode, and for signed-in staff in every mode, it does nothing.
 *
 * It is attached to the public routes by class name in app/Config/Routes/cbs_radio.php, so it needs no alias in
 * app/Config/Filters.php. It is deliberately NOT attached to the admin area, the presenter portal, or the payment
 * callback: staff must be able to log in and switch the site back on, a presenter has a live show to run, and a
 * Mobile Money provider cannot be told to wait.
 *
 * Any new public route belongs in a group that carries this filter, or it stays open while the rest of the site
 * is closed.
 */
class SiteStatus implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $page = new SiteStatusPage();

        if (! $page->turnsAway()) {
            return null;
        }

        return $page->respond(service('response'), config('CbsRadio')->siteMode);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
