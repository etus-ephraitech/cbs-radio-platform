<?php

namespace App\Libraries;

use Config\CbsRadio;

/**
 * Turns the appearance settings (Admin > Appearance) into what the v1 admin theme reads: data-* attributes on <html>
 * and the brand colour as a CSS variable. Rendered by the server on every staff page, so everyone sees the same
 * thing from the first paint, with no flash of the wrong theme.
 *
 * The theme only reads the attributes that apply to the chosen layout (a side menu style means nothing with a top
 * menu), so those are left out rather than written with a value that would be ignored.
 */
final class ThemeAttributes
{
    /** Theme stylesheet used for right-to-left reading. */
    public const RTL_BOOTSTRAP = 'libs/bootstrap/css/bootstrap.rtl.min.css';
    public const LTR_BOOTSTRAP = 'libs/bootstrap/css/bootstrap.min.css';

    /**
     * @return array<string, string> attribute name => value, in the order they are written
     */
    public static function attributes(CbsRadio $cbs): array
    {
        $horizontal = $cbs->themeNavLayout === 'horizontal';

        $attrs = [
            'lang'              => 'en',
            'dir'               => $cbs->themeDir === 'rtl' ? 'rtl' : 'ltr',
            'data-nav-layout'   => $horizontal ? 'horizontal' : 'vertical',
            'data-theme-mode'   => $cbs->themeMode === 'dark' ? 'dark' : 'light',
            'data-header-styles' => $cbs->themeHeaderStyles,
            'data-menu-styles'  => $cbs->themeMenuStyles,
            'data-width'        => $cbs->themeWidth,
            'data-page-style'   => $cbs->themePageStyle,
            'data-menu-position'   => $cbs->themeMenuPosition,
            'data-header-position' => $cbs->themeHeaderPosition,
        ];

        $attrs['data-toggled'] = 'close';

        if ($horizontal) {
            $attrs['data-nav-style'] = $cbs->themeNavStyle;
        } else {
            $attrs['data-vertical-style'] = $cbs->themeVerticalStyle;
        }

        if ($cbs->themeBgImage !== '') {
            $attrs['data-bg-img'] = $cbs->themeBgImage;
        }

        return $attrs;
    }

    /**
     * The attributes as they go inside the <html> tag, each value escaped.
     */
    public static function htmlAttributes(CbsRadio $cbs): string
    {
        $out = [];

        foreach (self::attributes($cbs) as $name => $value) {
            $out[] = $name . '="' . esc($value, 'attr') . '"';
        }

        $primary = self::primaryRgb($cbs);

        if ($primary !== '') {
            $out[] = 'style="--primary-rgb: ' . esc($primary, 'attr') . ';"';
        }

        return implode(' ', $out);
    }

    /**
     * "r, g, b", or '' when the theme's own colour is used. Only three numbers 0-255 pass, so nothing else can
     * reach the style attribute whatever is in the database.
     */
    public static function primaryRgb(CbsRadio $cbs): string
    {
        return preg_match('/^(?:\d{1,3}), (?:\d{1,3}), (?:\d{1,3})$/', $cbs->themePrimary)
            && max(array_map('intval', explode(',', $cbs->themePrimary))) <= 255
            ? $cbs->themePrimary
            : '';
    }

    public static function bootstrapSheet(CbsRadio $cbs): string
    {
        return $cbs->themeDir === 'rtl' ? self::RTL_BOOTSTRAP : self::LTR_BOOTSTRAP;
    }
}
