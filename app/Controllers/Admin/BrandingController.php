<?php

namespace App\Controllers\Admin;

use App\Services\BrandingService;

/**
 * Admin > Logo: upload, replace or remove the station logo and the browser-tab icon. Superadmin only: the routes
 * carry the package's `role:superadmin` filter (Routes/cbs_radio.php). The rules for what is accepted, and where
 * files go, are in BrandingService.
 */
class BrandingController extends BaseAdminController
{
    protected BrandingService $branding;

    public function __construct()
    {
        $this->branding = new BrandingService();
    }

    public function index()
    {
        $cbs = config('CbsRadio');

        return ui_view('admin/branding/index', [
            'logoUrl'    => BrandingService::url($cbs->logoPath),
            'faviconUrl' => BrandingService::url($cbs->faviconPath),
            'logoAlt'    => $cbs->logoAlt,
            'logoMaxKb'  => BrandingService::LOGO_MAX_KB,
            'iconMaxKb'  => BrandingService::FAVICON_MAX_KB,
        ]);
    }

    public function upload(string $kind)
    {
        $result = $this->branding->upload($kind, $this->request->getFile('image'), $this->currentUserId());

        return redirect()->to(site_url('admin/branding'))->with(
            $result['success'] ? 'toastr_success' : 'toastr_error',
            $result['success'] ? BrandingService::KINDS[$kind]['label'] . ' updated.' : $result['error']
        );
    }

    public function remove(string $kind)
    {
        $ok = $this->branding->remove($kind, $this->currentUserId());

        return redirect()->to(site_url('admin/branding'))->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? BrandingService::KINDS[$kind]['label'] . ' removed. The built-in one is showing again.' : 'Unknown image.'
        );
    }

    public function saveAlt()
    {
        $error = $this->branding->saveAlt((string) $this->request->getPost('alt'), $this->currentUserId());

        return redirect()->to(site_url('admin/branding'))->with(
            $error === null ? 'toastr_success' : 'toastr_error',
            $error ?? 'Logo description saved.'
        );
    }
}
