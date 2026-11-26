<?php

namespace Tests\Database\Settings;

use App\Libraries\Settings\SettingsManager;
use Config\CbsRadio;
use Tests\Support\TestCase\AppTestCase;

/**
 * Saving the staff-area look: a choice is stored and reaches the config the layout reads, a value the theme does
 * not know is refused with nothing saved, and "reset to default" goes back to the standard look.
 *
 * @internal
 */
final class AppearanceSettingsTest extends AppTestCase
{
    private SettingsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new SettingsManager();
        SettingsManager::flush();
    }

    protected function tearDown(): void
    {
        SettingsManager::flush();
        parent::tearDown();
    }

    public function testAChoiceIsSavedAndReachesTheConfig(): void
    {
        $result = $this->manager->save('appearance', ['appearance_themeMode' => 'dark', 'appearance_themeNavLayout' => 'horizontal'], [], 1);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['changed']);

        $cbs = new CbsRadio();
        $this->assertSame('dark', $cbs->themeMode);
        $this->assertSame('horizontal', $cbs->themeNavLayout);
    }

    public function testAValueTheThemeDoesNotKnowIsRefusedAndNothingIsSaved(): void
    {
        $result = $this->manager->save('appearance', ['appearance_themeMode' => 'dark', 'appearance_themeWidth' => 'enormous'], [], 1);

        $this->assertFalse($result['success']);
        $this->assertSame('light', (new CbsRadio())->themeMode);
    }

    public function testResettingGoesBackToTheStandardLook(): void
    {
        $this->manager->save('appearance', ['appearance_themePrimary' => '214, 40, 40'], [], 1);
        $this->assertSame('214, 40, 40', (new CbsRadio())->themePrimary);

        $this->manager->save('appearance', [], ['appearance_themePrimary' => '1'], 1);

        $this->assertSame('', (new CbsRadio())->themePrimary);
    }
}
