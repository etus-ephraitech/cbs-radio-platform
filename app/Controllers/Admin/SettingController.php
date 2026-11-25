<?php

namespace App\Controllers\Admin;

use App\Libraries\Settings\Registry;
use App\Libraries\Settings\SettingsManager;
use App\Libraries\SiteStatusPage;
use App\Services\NotificationService;

/**
 * The settings screen: one tab per group in App\Libraries\Settings\Registry.
 * Nothing here knows what any individual setting is — the form is built from
 * the registry and the rules live in SettingsManager, so adding a setting
 * never means touching this controller.
 *
 * Note: any logged-in admin can change any setting here, including payment
 * mode. The project has no roles yet; if presenters and moderators later
 * share the admin area, this is the screen to restrict first.
 */
class SettingController extends BaseAdminController
{
    protected SettingsManager $settings;

    public function __construct()
    {
        $this->settings = new SettingsManager();
    }

    public function index()
    {
        return redirect()->to(site_url('admin/settings/general'));
    }

    public function show(string $group)
    {
        if (! array_key_exists($group, Registry::groups())) {
            return redirect()->to(site_url('admin/settings'))->with('toastr_error', 'Unknown settings section.');
        }

        return view('admin/settings/index', [
            'groups'  => Registry::groups(),
            'group'   => $group,
            'items'   => $this->settings->forGroup($group),
            'secrets' => $this->settings->secretStatus($group),
            'changes' => $this->settings->recentChanges(10),
            'tokens'  => Registry::TEMPLATE_TOKENS,
        ]);
    }

    public function save(string $group)
    {
        if (! array_key_exists($group, Registry::groups())) {
            return redirect()->to(site_url('admin/settings'))->with('toastr_error', 'Unknown settings section.');
        }

        $result = $this->settings->save(
            $group,
            (array) $this->request->getPost('s'),
            (array) $this->request->getPost('reset'),
            $this->currentUserId()
        );

        if (! $result['success']) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        $message = $result['changed'] === 0
            ? 'Nothing was changed.'
            : $result['changed'] . ' setting(s) saved.';

        return redirect()->to(site_url('admin/settings/' . $group))->with('toastr_success', $message);
    }

    /**
     * Sends one test SMS or email through the real send path, so staff can
     * check the gateway settings without making a booking. The attempt is
     * recorded in the notifications log like any other.
     */
    public function sendTest(string $channel)
    {
        $back      = redirect()->to(site_url('admin/settings/notifications'));
        $recipient = trim((string) $this->request->getPost('recipient'));

        if (! in_array($channel, ['sms', 'email'], true)) {
            return $back->with('toastr_error', 'Unknown channel.');
        }

        $valid = $channel === 'email'
            ? filter_var($recipient, FILTER_VALIDATE_EMAIL)
            : preg_match('/^\+?[0-9][0-9 ()\-]{4,19}$/', $recipient);

        if (! $valid) {
            return $back->with('toastr_error', $channel === 'email'
                ? 'Enter a valid email address to send the test to.'
                : 'Enter a valid phone number to send the test to.');
        }

        $ok = (new NotificationService())->sendTest($channel, $recipient);

        return $back->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok
                ? 'Test ' . strtoupper($channel) . ' sent. Check the recipient, and the notifications log.'
                : 'The test ' . strtoupper($channel) . ' failed. Check the credentials in .env, then the notifications log for the attempt.'
        );
    }

    /**
     * Staff preview of the coming soon or maintenance page, as it is with the settings last saved. It is an ordinary
     * page here (not a 503, and not marked noindex) because the person looking is staff, in a browser tab.
     */
    public function previewStatus(string $mode)
    {
        if (! in_array($mode, [SiteStatusPage::COMING_SOON, SiteStatusPage::MAINTENANCE], true)) {
            return redirect()->to(site_url('admin/settings/status'))->with('toastr_error', 'Unknown page to preview.');
        }

        return (new SiteStatusPage())->respond($this->response, $mode, true);
    }
}
