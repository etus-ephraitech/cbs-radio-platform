<?php

namespace App\Libraries;

/**
 * The rules for editing roles, kept apart from the framework and the database so they can be tested on their own.
 * The auth package enforces the same limits when it writes (RoleManager); these exist so the page can say why in
 * plain words, and refuse before anything is attempted.
 */
final class RoleRules
{
    /** Same pattern the package uses for a role's name. */
    public const NAME_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    /** Names the Roles pages use as addresses. */
    public const RESERVED = ['create'];

    /**
     * Why a new role's name cannot be used, or null when it can.
     *
     * @param list<string> $existing names already in use
     */
    public static function nameProblem(string $name, array $existing): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return 'Give the role a name.';
        }

        if (strlen($name) > 50) {
            return 'The name is too long (50 characters at most).';
        }

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return 'The name may use lowercase letters, digits, "-" and "_", and must start with a letter or digit.';
        }

        if (in_array($name, self::RESERVED, true)) {
            return "\"{$name}\" is used by the page itself; choose another name.";
        }

        if (in_array($name, $existing, true)) {
            return "A role named \"{$name}\" already exists.";
        }

        return null;
    }

    /**
     * Why a role's permissions cannot be changed, or null when they can. The super role passes every check on its
     * own, so there is nothing to grant it and nothing that could be taken away.
     */
    public static function permissionsBlocker(string $name, ?string $superRole): ?string
    {
        return $superRole !== null && $name === $superRole
            ? 'The super role can do everything, so its permissions are not edited.'
            : null;
    }

    /**
     * Why a role cannot be deleted, or null when it can.
     */
    public static function deleteBlocker(string $name, ?string $superRole, bool $definedInConfig, int $holders): ?string
    {
        if ($superRole !== null && $name === $superRole) {
            return 'The super role cannot be deleted.';
        }

        if ($definedInConfig) {
            return 'This role is defined in Config\\Auth, so deleting it here would be undone by the next sync. Remove it from the config instead.';
        }

        if ($holders > 0) {
            return $holders === 1
                ? 'One person still has this role. Take it off them in Users first.'
                : "{$holders} people still have this role. Take it off them in Users first.";
        }

        return null;
    }

    /**
     * Keep only permissions that exist, once each, in a stable order: what a form posts is never trusted.
     *
     * @param array<mixed> $posted
     * @param list<string> $known
     *
     * @return list<string>
     */
    public static function onlyKnown(array $posted, array $known): array
    {
        $wanted = array_flip(array_filter($posted, 'is_string'));
        $kept   = array_values(array_filter($known, static fn (string $name): bool => isset($wanted[$name])));

        return $kept;
    }

    /**
     * Permission labels grouped by area (the part before the first dot), areas and names sorted.
     *
     * @param array<string, string> $labels permission => label
     *
     * @return array<string, array<string, string>>
     */
    public static function groupByArea(array $labels): array
    {
        $groups = [];

        foreach ($labels as $name => $label) {
            $name = (string) $name;
            $area = str_contains($name, '.') ? substr($name, 0, (int) strpos($name, '.')) : 'other';

            $groups[$area][$name] = (string) $label;
        }

        ksort($groups);

        foreach ($groups as &$group) {
            ksort($group);
        }
        unset($group);

        return $groups;
    }
}
