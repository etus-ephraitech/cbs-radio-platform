<?php

namespace App\Libraries\Settings;

/**
 * Lets a Config class pick up admin-chosen settings. Call
 * applySettingOverrides('ClassName') as the LAST line of the constructor,
 * after .env has been applied, so precedence is:
 *
 *     admin setting  >  .env  >  code default
 *
 * Targets may reach into an array property with a dotted path
 * (e.g. "templates.aired.sms" or "channelsEnabled.email").
 */
trait AppliesSettingOverrides
{
    protected function applySettingOverrides(string $configName): void
    {
        if (Overrides::$suspended) {
            return;
        }

        $overrides = SettingsManager::overrides();

        if ($overrides === []) {
            return;
        }

        foreach (Registry::definitions() as $key => $def) {
            if ($def['target'][0] !== $configName || ! array_key_exists($key, $overrides)) {
                continue;
            }

            $this->assignSettingPath($def['target'][1], Registry::cast($def, $overrides[$key]));
        }
    }

    private function assignSettingPath(string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $property = array_shift($segments);

        if ($segments === []) {
            $this->{$property} = $value;

            return;
        }

        $ref = &$this->{$property};

        foreach ($segments as $segment) {
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }
}
