<?php

namespace App\Libraries\Settings;

use App\Models\SettingChangeModel;
use App\Models\SettingModel;

/**
 * Everything the settings screen needs: reading the current and default
 * value of each setting, validating and saving changes, recording who
 * changed what, and reporting whether the .env credentials are in place.
 *
 * The values are stored in the `settings` table but applied through the
 * Config classes (see AppliesSettingOverrides), so code never calls this
 * class to read a setting — it just reads config('CbsRadio') and friends.
 */
class SettingsManager
{
    private const CACHE_KEY = 'cbs_setting_overrides';
    private const CACHE_TTL = 3600;

    /** Per-request copy, so building several Config classes costs one lookup. */
    private static ?array $memo = null;

    protected SettingModel $settings;
    protected SettingChangeModel $changes;

    public function __construct(?SettingModel $settings = null, ?SettingChangeModel $changes = null)
    {
        $this->settings = $settings ?? new SettingModel();
        $this->changes  = $changes ?? new SettingChangeModel();
    }

    /**
     * Every admin override as key => raw text. Used by the Config classes
     * on every request, so it is cached, and it fails soft: before the
     * migrations have run (or if the database is unreachable) it returns
     * nothing and the app simply runs on .env and the code defaults.
     *
     * @return array<string, string>
     */
    public static function overrides(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            $cached = cache(self::CACHE_KEY);

            if (is_array($cached)) {
                return self::$memo = $cached;
            }

            $all = (new SettingModel())->allKeyed();
            cache()->save(self::CACHE_KEY, $all, self::CACHE_TTL);

            return self::$memo = $all;
        } catch (\Throwable $e) {
            // Deliberately not cached: a failure shouldn't stick for an hour.
            return self::$memo = [];
        }
    }

    public static function flush(): void
    {
        self::$memo = null;

        try {
            cache()->delete(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // Nothing to clear if there is no cache.
        }
    }

    /**
     * The fields of one settings tab, each with its effective value, its
     * default (what .env or the code gives it), and whether an admin has
     * overridden it.
     *
     * @return array<int, array{key: string, field: string, def: array, current: mixed, base: mixed, overridden: bool}>
     */
    public function forGroup(string $group): array
    {
        $overrides = $this->settings->allKeyed();
        $bases     = $this->baseConfigs();
        $items     = [];

        foreach (Registry::forGroup($group) as $key => $def) {
            $base       = $this->readPath($bases[$def['target'][0]], $def['target'][1]);
            $overridden = array_key_exists($key, $overrides);

            $items[] = [
                'key'        => $key,
                'field'      => Registry::fieldName($key),
                'def'        => $def,
                'base'       => $base,
                'overridden' => $overridden,
                'current'    => $overridden ? Registry::cast($def, $overrides[$key]) : $base,
            ];
        }

        return $items;
    }

    /**
     * Validates and saves one tab. All-or-nothing: if any field is invalid
     * nothing is written.
     *
     * @param array<string, mixed> $input submitted values, keyed by form field name
     * @param array<string, mixed> $reset form field names ticked "reset to default"
     *
     * @return array{success: bool, errors: string[], changed: int}
     */
    public function save(string $group, array $input, array $reset, ?int $userId): array
    {
        $items   = $this->forGroup($group);
        $errors  = [];
        $pending = [];

        foreach ($items as $item) {
            $key = $item['key'];
            $def = $item['def'];

            if (! empty($reset[$item['field']]) && $item['overridden']) {
                $pending[$key] = ['action' => 'reset', 'raw' => null];

                continue;
            }

            if (! array_key_exists($item['field'], $input)) {
                continue; // field was not on the submitted form
            }

            $raw   = Registry::normalize($def, $input[$item['field']]);
            $error = $this->validate($def, $raw);

            if ($error !== null) {
                $errors[$key] = $def['label'] . ': ' . $error;

                continue;
            }

            if (Registry::cast($def, $raw) == $item['current']) {
                continue; // unchanged — don't create an override that pins today's value
            }

            $pending[$key] = ['action' => 'set', 'raw' => $raw];
        }

        $errors += $this->crossValidate($group, $items, $pending);

        if ($errors !== []) {
            return ['success' => false, 'errors' => array_values($errors), 'changed' => 0];
        }

        foreach ($items as $item) {
            if (! isset($pending[$item['key']])) {
                continue;
            }

            $change = $pending[$item['key']];
            $before = Registry::toRaw($item['def'], $item['current']);

            if ($change['action'] === 'reset') {
                $this->settings->clearOverride($item['key']);
                $this->changes->record($item['key'], 'reset', $before, Registry::toRaw($item['def'], $item['base']), $userId);
            } else {
                $this->settings->setOverride($item['key'], $change['raw'], $userId);
                $this->changes->record($item['key'], 'set', $before, $change['raw'], $userId);
            }
        }

        self::flush();

        return ['success' => true, 'errors' => [], 'changed' => count($pending)];
    }

    /**
     * Recent changes with a readable label for each setting.
     */
    public function recentChanges(int $limit = 10): array
    {
        $defs = Registry::definitions();

        return array_map(static function (array $row) use ($defs) {
            $row['label'] = $defs[$row['setting_key']]['label'] ?? $row['setting_key'];
            $row['type']  = $defs[$row['setting_key']]['type'] ?? 'string';

            return $row;
        }, $this->changes->recent($limit));
    }

    /**
     * Whether each .env credential for a tab is present. Reports presence
     * only — a value is never read out.
     *
     * @return array<int, array{label: string, env: string, configured: bool}>
     */
    public function secretStatus(string $group): array
    {
        return array_map(
            fn (array $s) => $s + ['configured' => $this->envConfigured($s['env'])],
            Registry::secrets()[$group] ?? []
        );
    }

    /**
     * One line per area for the dashboard: is it set up, and if not, what is missing.
     *
     * @return array<int, array{label: string, status: string, detail: string, url: string}>
     */
    public function health(): array
    {
        $payments = config('Payments');
        $notify   = config('Notifications');

        if ($payments->mode === 'mobile_money') {
            $ready = $payments->provider !== '' && $this->envConfigured('momo.apiKey') && $this->envConfigured('momo.apiSecret');
            $pay   = ['warn', $ready ? 'Mobile Money (online)' : 'Online payments are on but provider or credentials are missing'];

            if ($ready) {
                $pay[0] = 'ok';
            }
        } else {
            $pay = ['info', 'Manual — staff confirm each payment'];
        }

        $sms = $notify->channelsEnabled['sms']
            ? ($this->envConfigured('sms.apiKey') ? ['ok', 'On'] : ['warn', 'On, but no gateway key in .env'])
            : ['info', 'Off'];

        $email = $notify->channelsEnabled['email']
            ? ($this->envConfigured('email.SMTPHost') ? ['ok', 'On'] : ['warn', 'On, but no SMTP host in .env'])
            : ['info', 'Off'];

        return [
            ['label' => 'Payments', 'status' => $pay[0], 'detail' => $pay[1], 'url' => site_url('admin/settings/payments')],
            ['label' => 'SMS', 'status' => $sms[0], 'detail' => $sms[1], 'url' => site_url('admin/settings/notifications')],
            ['label' => 'Email', 'status' => $email[0], 'detail' => $email[1], 'url' => site_url('admin/settings/notifications')],
        ];
    }

    public function envConfigured(string $envKey): bool
    {
        return trim((string) env($envKey, '')) !== '';
    }

    /**
     * Fresh Config instances with admin overrides switched off — their
     * values are what .env and the code defaults give.
     *
     * @return array<string, object>
     */
    protected function baseConfigs(): array
    {
        $names = array_unique(array_map(static fn (array $d) => $d['target'][0], Registry::definitions()));
        $bases = [];

        Overrides::$suspended = true;

        try {
            foreach ($names as $name) {
                $class        = 'Config\\' . $name;
                $bases[$name] = new $class();
            }
        } finally {
            Overrides::$suspended = false;
        }

        return $bases;
    }

    protected function readPath(object $config, string $path): mixed
    {
        $segments = explode('.', $path);
        $value    = $config->{array_shift($segments)};

        foreach ($segments as $segment) {
            $value = $value[$segment] ?? null;
        }

        return $value;
    }

    /**
     * @param string $raw the normalised text about to be stored
     */
    protected function validate(array $def, string $raw): ?string
    {
        if (($def['required'] ?? false) && $raw === '') {
            return 'cannot be empty. Use "Reset to default" instead.';
        }

        switch ($def['type']) {
            case 'int':
                if (! ctype_digit($raw)) {
                    return 'must be a whole number.';
                }

                if ((int) $raw < $def['min'] || (int) $raw > $def['max']) {
                    return "must be between {$def['min']} and {$def['max']}.";
                }

                break;

            case 'select':
                if (! array_key_exists($raw, $def['options'])) {
                    return 'is not one of the available choices.';
                }

                break;

            case 'list':
                $items = Registry::cast($def, $raw);

                if (count($items) > $def['maxItems']) {
                    return "can have at most {$def['maxItems']} entries.";
                }

                foreach ($items as $item) {
                    if (mb_strlen($item) > $def['maxLength']) {
                        return "entries can be at most {$def['maxLength']} characters (\"" . mb_substr($item, 0, 20) . '…" is longer).';
                    }
                }

                break;

            case 'string':
            case 'text':
                if (mb_strlen($raw) > ($def['maxLength'] ?? 255)) {
                    return "is too long (max {$def['maxLength']} characters).";
                }

                if ($raw !== '' && ($error = $this->validateFormat($def['format'] ?? '', $raw)) !== null) {
                    return $error;
                }

                if (! empty($def['tokens']) && ($error = $this->validateTokens($raw)) !== null) {
                    return $error;
                }

                break;
        }

        return null;
    }

    protected function validateFormat(string $format, string $raw): ?string
    {
        return match ($format) {
            'email'    => filter_var($raw, FILTER_VALIDATE_EMAIL) ? null : 'is not a valid email address.',
            'phone'    => preg_match('/^\+?[0-9][0-9 ()\-]{4,19}$/', $raw) ? null : 'is not a valid phone number.',
            'currency' => preg_match('/^[A-Z]{3}$/', $raw) ? null : 'must be a three-letter code such as UGX.',
            'senderid' => preg_match('/^[A-Za-z0-9]{1,11}$/', $raw) ? null : 'must be 1–11 letters or numbers, with no spaces.',
            // http(s) only — this ends up in an audio src, so javascript: and data: must never get through.
            'url'      => (filter_var($raw, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $raw)) ? null : 'must be a web address starting with http:// or https://.',
            default    => null,
        };
    }

    /**
     * A typo like {referense} would otherwise go out to a real person
     * verbatim, so unknown placeholders are rejected up front.
     */
    protected function validateTokens(string $raw): ?string
    {
        preg_match_all('/\{([^{}]*)\}/', $raw, $matches);

        $unknown = array_values(array_diff(array_unique($matches[1]), Registry::TEMPLATE_TOKENS));

        if ($unknown === []) {
            return null;
        }

        $allowed = '{' . implode('}, {', Registry::TEMPLATE_TOKENS) . '}';

        return 'uses {' . $unknown[0] . "}, which is not a known placeholder. You can use: {$allowed}.";
    }

    /**
     * Rules that involve more than one setting.
     *
     * @param array<int, array> $items
     * @param array<string, array> $pending
     *
     * @return array<string, string>
     */
    protected function crossValidate(string $group, array $items, array $pending): array
    {
        $effective = [];

        foreach ($items as $item) {
            $change = $pending[$item['key']] ?? null;

            $effective[$item['key']] = match ($change['action'] ?? null) {
                'set'   => Registry::cast($item['def'], $change['raw']),
                'reset' => $item['base'],
                default => $item['current'],
            };
        }

        $errors = [];

        if ($group === 'bookings') {
            if ($effective['uploads.voiceNoteMinSeconds'] >= $effective['uploads.voiceNoteMaxSeconds']) {
                $errors['uploads.voiceNoteMaxSeconds'] = 'Longest voice note: must be longer than the shortest.';
            }
        }

        if ($group === 'payments') {
            // Only check when this save touches the mode or provider, so
            // an unrelated edit on the tab can't be blocked by it.
            $touched = isset($pending['payments.mode']) || isset($pending['payments.provider']);

            if ($touched && $effective['payments.mode'] === 'mobile_money') {
                if ($effective['payments.provider'] === '') {
                    $errors['payments.provider'] = 'Mobile Money provider: choose one before switching on online payments.';
                }

                if (! $this->envConfigured('momo.apiKey') || ! $this->envConfigured('momo.apiSecret')) {
                    $errors['payments.mode'] = 'Payment mode: online payments need momo.apiKey and momo.apiSecret in .env first.';
                }
            }
        }

        return $errors;
    }
}
