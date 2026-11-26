<?php

namespace App\Controllers\Admin;

use App\Libraries\Settings\SettingsManager;

/**
 * Admin > Appearance: how the staff area looks, for everyone. Superadmin only: the routes carry the package's
 * `role:superadmin` filter (Routes/cbs_radio.php). The options, their allowed values and their defaults are
 * described in the settings registry (group "appearance"); validation and the change log are SettingsManager's,
 * exactly as for the other settings, and App\Libraries\ThemeAttributes turns the saved values into the page.
 */
class AppearanceController extends BaseAdminController
{
    private const GROUP = 'appearance';

    /** The page's sections, in order: heading => setting keys. */
    public const SECTIONS = [
        'Layout' => [
            'appearance.themeMode', 'appearance.themeDir', 'appearance.themeNavLayout', 'appearance.themeVerticalStyle',
            'appearance.themeNavStyle', 'appearance.themeWidth', 'appearance.themePageStyle',
        ],
        'Header and menu' => [
            'appearance.themeHeaderPosition', 'appearance.themeHeaderStyles',
            'appearance.themeMenuPosition', 'appearance.themeMenuStyles',
        ],
        'Colour' => ['appearance.themePrimary', 'appearance.themeBgImage'],
    ];

    protected SettingsManager $settings;

    public function __construct()
    {
        $this->settings = new SettingsManager();
    }

    public function index()
    {
        $byKey = [];

        foreach ($this->settings->forGroup(self::GROUP) as $item) {
            $byKey[$item['key']] = $item;
        }

        return ui_view('admin/appearance/index', [
            'sections' => self::SECTIONS,
            'items'    => $byKey,
            'changes'  => array_values(array_filter(
                $this->settings->recentChanges(60),
                static fn (array $c) => str_starts_with($c['setting_key'], 'appearance.')
            )),
        ]);
    }

    public function save()
    {
        $result = $this->settings->save(
            self::GROUP,
            (array) $this->request->getPost('s'),
            (array) $this->request->getPost('reset'),
            $this->currentUserId()
        );

        if (! $result['success']) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        return redirect()->to(site_url('admin/appearance'))->with(
            'toastr_success',
            $result['changed'] === 0 ? 'Nothing was changed.' : 'Appearance saved. Everyone sees it from their next page.'
        );
    }
}
