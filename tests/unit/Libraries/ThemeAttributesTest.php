<?php

namespace Tests\Unit\Libraries;

use App\Libraries\ThemeAttributes;
use Config\CbsRadio;
use Tests\Support\TestCase\UnitTestCase;

/**
 * What Admin > Appearance writes onto the staff pages: the right attributes for the chosen layout, nothing that
 * does not apply, and nothing unsafe in the style attribute whatever is stored.
 *
 * @internal
 */
final class ThemeAttributesTest extends UnitTestCase
{
    private function config(array $set = []): CbsRadio
    {
        $cbs = new CbsRadio();

        foreach ($set as $prop => $value) {
            $cbs->{$prop} = $value;
        }

        return $cbs;
    }

    public function testTheDefaultsAreTheThemesOwnLook(): void
    {
        $attrs = ThemeAttributes::attributes($this->config());

        $this->assertSame('vertical', $attrs['data-nav-layout']);
        $this->assertSame('light', $attrs['data-theme-mode']);
        $this->assertSame('light', $attrs['data-header-styles']);
        $this->assertSame('dark', $attrs['data-menu-styles']);
        $this->assertSame('fullwidth', $attrs['data-width']);
        $this->assertSame('default', $attrs['data-vertical-style']);
        $this->assertArrayNotHasKey('data-nav-style', $attrs);
        $this->assertArrayNotHasKey('data-bg-img', $attrs);
        $this->assertStringNotContainsString('style=', ThemeAttributes::htmlAttributes($this->config()));
    }

    public function testATopMenuGetsItsStyleAndNotTheSideMenusOne(): void
    {
        $attrs = ThemeAttributes::attributes($this->config(['themeNavLayout' => 'horizontal', 'themeNavStyle' => 'icon-hover']));

        $this->assertSame('horizontal', $attrs['data-nav-layout']);
        $this->assertSame('icon-hover', $attrs['data-nav-style']);
        $this->assertArrayNotHasKey('data-vertical-style', $attrs);
    }

    public function testABackgroundPatternAndBrandColourAreWritten(): void
    {
        $html = ThemeAttributes::htmlAttributes($this->config(['themeBgImage' => 'bgimg3', 'themePrimary' => '214, 40, 40']));

        $this->assertStringContainsString('data-bg-img="bgimg3"', $html);
        $this->assertStringContainsString('style="--primary-rgb: 214, 40, 40;"', $html);
    }

    public function testOnlyThreeColourNumbersReachTheStyleAttribute(): void
    {
        foreach (['red', '1, 2', '1, 2, 3; background:url(x)', '300, 0, 0', '"><script>'] as $bad) {
            $this->assertSame('', ThemeAttributes::primaryRgb($this->config(['themePrimary' => $bad])), $bad);
        }
    }

    public function testRightToLeftSwitchesTheStylesheet(): void
    {
        $this->assertSame(ThemeAttributes::LTR_BOOTSTRAP, ThemeAttributes::bootstrapSheet($this->config()));
        $this->assertSame(ThemeAttributes::RTL_BOOTSTRAP, ThemeAttributes::bootstrapSheet($this->config(['themeDir' => 'rtl'])));
        $this->assertSame('rtl', ThemeAttributes::attributes($this->config(['themeDir' => 'rtl']))['dir']);
    }

    public function testAnUnknownDirectionOrModeFallsBackToTheSafeOne(): void
    {
        $attrs = ThemeAttributes::attributes($this->config(['themeDir' => 'sideways', 'themeMode' => 'neon']));

        $this->assertSame('ltr', $attrs['dir']);
        $this->assertSame('light', $attrs['data-theme-mode']);
    }
}
