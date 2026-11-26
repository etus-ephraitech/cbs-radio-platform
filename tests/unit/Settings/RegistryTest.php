<?php

namespace Tests\Unit\Settings;

use App\Libraries\Settings\Registry;
use App\Libraries\Settings\SettingsManager;
use Config\Notifications;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\TestCase\UnitTestCase;

/**
 * The registry is the one list of everything an administrator may change. These tests keep it honest: every entry
 * points at a real setting in a real config class, and the values the code ships with are values the screen would
 * itself accept.
 *
 * @internal
 */
final class RegistryTest extends UnitTestCase
{
    private const TYPES = ['string', 'text', 'int', 'bool', 'select', 'list'];

    public function testThereAreSettingsAndGroupsToShowThem(): void
    {
        $this->assertNotEmpty(Registry::definitions());
        $this->assertSame(
            ['general', 'status', 'site', 'payments', 'notifications', 'templates', 'bookings'],
            array_keys(Registry::groups())
        );
    }

    public function testEveryGroupHasAtLeastOneSetting(): void
    {
        foreach (array_keys(Registry::groups()) as $group) {
            $this->assertNotEmpty(Registry::forGroup($group), "The \"{$group}\" tab would be empty.");
        }
    }

    public function testEverySettingBelongsToAKnownGroup(): void
    {
        foreach (Registry::definitions() as $key => $def) {
            $this->assertArrayHasKey($def['group'], Registry::groups(), "{$key} is in an unknown group.");
        }
    }

    public function testEverySettingIsCompletelyDescribed(): void
    {
        foreach (Registry::definitions() as $key => $def) {
            $this->assertContains($def['type'], self::TYPES, "{$key} has an unknown type.");
            $this->assertNotSame('', trim($def['label']), "{$key} has no label.");
            $this->assertCount(2, $def['target'], "{$key} must target [Config class, property path].");

            match ($def['type']) {
                'int'    => $this->assertTrue(isset($def['min'], $def['max']) && $def['min'] <= $def['max'], "{$key} needs a sensible min and max."),
                'select' => $this->assertNotEmpty($def['options'] ?? [], "{$key} needs options."),
                'list'   => $this->assertTrue(isset($def['maxItems'], $def['maxLength']), "{$key} needs maxItems and maxLength."),
                'string', 'text' => $this->assertTrue(isset($def['maxLength']), "{$key} needs a maxLength."),
                default  => null,
            };
        }
    }

    public function testEveryTargetIsARealPropertyOfARealConfigClass(): void
    {
        foreach (Registry::definitions() as $key => $def) {
            [$class, $path] = $def['target'];
            $fqcn           = 'Config\\' . $class;

            $this->assertTrue(class_exists($fqcn), "{$key} targets {$fqcn}, which does not exist.");

            $segments = explode('.', $path);
            $value    = (new $fqcn())->{array_shift($segments)} ?? null;

            $this->assertNotNull($value, "{$key} targets {$fqcn}::{$path}, which does not exist.");

            foreach ($segments as $segment) {
                $this->assertIsArray($value, "{$key}: {$path} goes into something that is not an array.");
                $this->assertArrayHasKey($segment, $value, "{$key} targets {$fqcn}::{$path}, which does not exist.");
                $value = $value[$segment];
            }
        }
    }

    public function testTwoSettingsNeverShareATarget(): void
    {
        $seen = [];

        foreach (Registry::definitions() as $key => $def) {
            $target = implode('::', $def['target']);

            $this->assertArrayNotHasKey($target, $seen, "{$key} and " . ($seen[$target] ?? '?') . " both change {$target}.");
            $seen[$target] = $key;
        }
    }

    public function testEverySettingHasItsOwnFormFieldName(): void
    {
        $fields = [];

        foreach (array_keys(Registry::definitions()) as $key) {
            $field = Registry::fieldName($key);

            $this->assertArrayNotHasKey($field, $fields, "{$key} and " . ($fields[$field] ?? '?') . " would share a form field.");
            $this->assertSame($key, Registry::keyForField($field), 'A field name must lead back to its own key.');
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_]+$/', $field);

            $fields[$field] = $key;
        }

        $this->assertNull(Registry::keyForField('no_such_setting'));
    }

    /**
     * What the code ships with must be something the administrators' own screen would accept, or the first
     * time someone saved an unrelated change on that tab it would refuse because of a default nobody set.
     */
    public function testEveryDefaultValuePassesItsOwnValidation(): void
    {
        $validate = new ReflectionMethod(SettingsManager::class, 'validate');
        $manager  = new SettingsManager();

        foreach (Registry::definitions() as $key => $def) {
            [$class, $path] = $def['target'];
            $fqcn           = 'Config\\' . $class;

            $segments = explode('.', $path);
            $value    = (new $fqcn())->{array_shift($segments)};

            foreach ($segments as $segment) {
                $value = $value[$segment];
            }

            $error = $validate->invoke($manager, $def, Registry::toRaw($def, $value));

            $this->assertNull($error, "The default for {$key} fails its own check: {$error}");
        }
    }

    /**
     * Credentials live in .env and are only ever reported as "configured: yes/no". If one ever became an entry
     * in the registry it would be shown on screen and stored in the database.
     */
    public function testNoSecretIsEditableOnScreen(): void
    {
        foreach (Registry::definitions() as $key => $def) {
            $this->assertDoesNotMatchRegularExpression(
                '/api_?key|secret|password|token/i',
                $def['target'][1],
                "{$key} changes something that looks like a credential."
            );
        }

        $secretEnvKeys = [];

        foreach (Registry::secrets() as $group => $secrets) {
            $this->assertArrayHasKey($group, Registry::groups());

            foreach ($secrets as $secret) {
                $this->assertArrayHasKey('env', $secret);
                $this->assertArrayHasKey('label', $secret);
                $secretEnvKeys[] = $secret['env'];
            }
        }

        $this->assertContains('momo.apiKey', $secretEnvKeys);
        $this->assertContains('sms.apiKey', $secretEnvKeys);
        $this->assertSame([], array_values(array_intersect($secretEnvKeys, array_keys(Registry::definitions()))));
    }

    public function testEveryShippedMessageTemplateOnlyUsesKnownPlaceholders(): void
    {
        $templates = (new Notifications())->templates;

        $this->assertNotEmpty($templates);

        foreach ($templates as $event => $fields) {
            foreach ($fields as $field => $text) {
                preg_match_all('/\{([^{}]*)\}/', $text, $found);

                $this->assertSame(
                    [],
                    array_values(array_diff($found[1], Registry::TEMPLATE_TOKENS)),
                    "The shipped {$event}/{$field} template uses a placeholder the settings screen would refuse."
                );
            }
        }
    }

    // ------------------------------------------------------------------ cast / toRaw / normalize

    #[DataProvider('casts')]
    public function testCast(string $type, ?string $raw, mixed $expected): void
    {
        $this->assertSame($expected, Registry::cast(['type' => $type], $raw));
    }

    /**
     * @return array<string, array{0: string, 1: string|null, 2: mixed}>
     */
    public static function casts(): array
    {
        return [
            'bool "1"'               => ['bool', '1', true],
            'bool "true"'            => ['bool', 'true', true],
            'bool "on"'              => ['bool', 'on', true],
            'bool "0"'               => ['bool', '0', false],
            'bool "false"'           => ['bool', 'false', false],
            'bool empty'             => ['bool', '', false],
            'bool null'              => ['bool', null, false],
            'int'                    => ['int', '45', 45],
            'int null is zero'       => ['int', null, 0],
            'string untouched'       => ['string', ' keep  me ', ' keep  me '],
            'null string is empty'   => ['string', null, ''],
            'list by lines'          => ['list', "a\nb\nc", ['a', 'b', 'c']],
            'list by commas'         => ['list', 'a, b ,c', ['a', 'b', 'c']],
            'list drops blanks'      => ['list', "a\n\n , b", ['a', 'b']],
            'list drops duplicates'  => ['list', "a\nb\na", ['a', 'b']],
            'empty list'             => ['list', '', []],
        ];
    }

    public function testTheSiteStatusSettingsAreDeclaredAsTheFilterReadsThem(): void
    {
        $mode = Registry::definitions()['status.mode'];

        $this->assertSame('select', $mode['type']);
        $this->assertSame(['live', 'coming_soon', 'maintenance'], array_keys($mode['options']), 'These are the values App\\Filters\\SiteStatus and SiteStatusPage act on.');
        $this->assertSame(['CbsRadio', 'siteMode'], $mode['target']);

        $this->assertSame('datetime', Registry::definitions()['status.returnAt']['format']);
        $this->assertSame(['CbsRadio', 'siteReturnAt'], Registry::definitions()['status.returnAt']['target']);
        $this->assertSame(['CbsRadio', 'siteMessage'], Registry::definitions()['status.message']['target']);
    }

    public function testTheSiteIsLiveUnlessStaffSayOtherwise(): void
    {
        $config = new \Config\CbsRadio();

        $this->assertSame('live', $config->siteMode);
        $this->assertSame('', $config->siteMessage);
        $this->assertSame('', $config->siteReturnAt);
    }

    public function testToRawIsTheInverseOfCast(): void
    {
        $this->assertSame('1', Registry::toRaw(['type' => 'bool'], true));
        $this->assertSame('0', Registry::toRaw(['type' => 'bool'], false));
        $this->assertSame("a\nb", Registry::toRaw(['type' => 'list'], ['a', 'b']));
        $this->assertSame('', Registry::toRaw(['type' => 'list'], []));
        $this->assertSame('45', Registry::toRaw(['type' => 'int'], 45));
        $this->assertSame('hello', Registry::toRaw(['type' => 'string'], 'hello'));
    }

    #[DataProvider('normalisations')]
    public function testNormalize(array $def, mixed $input, string $expected): void
    {
        $this->assertSame($expected, Registry::normalize($def, $input));
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: mixed, 2: string}>
     */
    public static function normalisations(): array
    {
        return [
            'checkbox "on"'                => [['type' => 'bool'], 'on', '1'],
            'checkbox "YES"'               => [['type' => 'bool'], ' YES ', '1'],
            'checkbox "1"'                 => [['type' => 'bool'], '1', '1'],
            'checkbox "no"'                => [['type' => 'bool'], 'no', '0'],
            'checkbox left out'            => [['type' => 'bool'], '', '0'],
            'checkbox given an array'      => [['type' => 'bool'], ['1'], '0'],
            'list is tidied'               => [['type' => 'list'], "b, a\n\nb", "b\na"],
            'text: line endings unified'   => [['type' => 'text'], "one\r\ntwo\rthree  ", "one\ntwo\nthree"],
            'currency is upper-cased'      => [['type' => 'string', 'format' => 'currency'], ' ugx ', 'UGX'],
            'strings are trimmed'          => [['type' => 'string'], "  hello \n", 'hello'],
            'datetime: a browser picker value' => [['type' => 'string', 'format' => 'datetime'], '2026-12-01T09:00', '2026-12-01 09:00'],
            'datetime: seconds are dropped'    => [['type' => 'string', 'format' => 'datetime'], '2026-12-01 09:00:30', '2026-12-01 09:00'],
            'datetime: picker with seconds'    => [['type' => 'string', 'format' => 'datetime'], '2026-12-01T09:00:30', '2026-12-01 09:00'],
            'datetime: trimmed'                => [['type' => 'string', 'format' => 'datetime'], '  2026-12-01 09:00 ', '2026-12-01 09:00'],
            'datetime: empty stays empty'      => [['type' => 'string', 'format' => 'datetime'], '', ''],
            'datetime: nonsense is left for validation' => [['type' => 'string', 'format' => 'datetime'], 'next friday', 'next friday'],
            'a non-scalar becomes empty'   => [['type' => 'string'], ['x'], ''],
        ];
    }
}
