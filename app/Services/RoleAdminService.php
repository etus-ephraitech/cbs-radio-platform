<?php

namespace App\Services;

use App\Libraries\RoleRules;
use Ephraitech\Auth\Authorization\Authorizer;
use Ephraitech\Auth\Authorization\RoleManager;
use Ephraitech\Auth\Config\Auth;
use Ephraitech\Auth\Entities\Role;
use Ephraitech\Auth\Exceptions\AuthException;
use Ephraitech\Auth\Models\PermissionModel;

/**
 * Admin > Roles: what each role is called and what it may do. Superadmin only (the routes carry `role:superadmin`).
 *
 * Writing is the auth package's RoleManager (ephraitech/ci4-auth 0.11 and later, `service('authRoles')`): this
 * class never touches the role tables itself. A role changed here is marked "managed in the app", which tells
 * `php spark auth:sync` to leave it alone; Release hands a role from Config\Auth back to the config.
 *
 * Every public method that changes something returns a message when it refuses (null when it worked), in words for
 * the person who clicked.
 */
class RoleAdminService
{
    private Auth $config;
    private ?RoleManager $roles;
    private Authorizer $authorizer;

    public function __construct(?RoleManager $roles = null, ?Authorizer $authorizer = null)
    {
        $this->config     = StaffUserService::authConfig();
        $this->roles      = $roles ?? (function_exists('service') ? service('authRoles') : null);
        $this->authorizer = $authorizer ?? service('authAuthorizer');
    }

    /**
     * False on an ephraitech/ci4-auth older than 0.11, which has no way to edit roles in the app.
     */
    public function available(): bool
    {
        return $this->roles instanceof RoleManager;
    }

    public function superRole(): ?string
    {
        return $this->config->superRole;
    }

    /**
     * Every permission there is, name => label: those the config describes plus any the database holds (a module's).
     *
     * @return array<string, string>
     */
    public function permissionLabels(): array
    {
        $labels = [];

        foreach ($this->config->permissions as $name => $label) {
            $labels[(string) $name] = (string) $label;
        }

        try {
            foreach ((new PermissionModel())->allNames() as $name) {
                $labels[$name] ??= $name;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Reading permissions failed: ' . $e->getMessage());
        }

        return $labels;
    }

    /**
     * @return array<string, array<string, string>> area => [permission => label]
     */
    public function permissionGroups(): array
    {
        return RoleRules::groupByArea($this->permissionLabels());
    }

    /**
     * Every role with what the list needs to show.
     *
     * @return list<array<string, mixed>>
     */
    public function overview(): array
    {
        if (! $this->available()) {
            return [];
        }

        $rows = [];

        foreach ($this->roles->all() as $role) {
            $rows[] = $this->describe($role);
        }

        return $rows;
    }

    /**
     * One role for the edit page, or null when there is none by that name.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $name): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $role = $this->roles->find($name);

        return $role instanceof Role ? $this->describe($role) : null;
    }

    public function create(string $name, string $title, string $description, array $permissions): ?string
    {
        $name = trim($name);
        $problem = $this->unavailable() ?? RoleRules::nameProblem($name, array_map(static fn (array $r): string => (string) $r['name'], $this->overview()));

        if ($problem !== null) {
            return $problem;
        }

        return $this->attempt(function () use ($name, $title, $description, $permissions): void {
            $this->roles->createRole($name, $title, $description !== '' ? $description : null, $this->known($permissions));
        });
    }

    public function update(string $name, string $title, string $description, array $permissions): ?string
    {
        $problem = $this->unavailable();

        if ($problem !== null) {
            return $problem;
        }

        if ($this->roles->find($name) === null) {
            return 'That role was not found.';
        }

        return $this->attempt(function () use ($name, $title, $description, $permissions): void {
            $this->roles->updateRole($name, $title, $description);

            if (RoleRules::permissionsBlocker($name, $this->config->superRole) === null) {
                $this->roles->setPermissions($name, $this->known($permissions));
            }
        });
    }

    /**
     * Hand a role that Config\Auth defines back to the config: the next `auth:sync` restores its config version.
     */
    public function release(string $name): ?string
    {
        return $this->unavailable() ?? $this->attempt(function () use ($name): void {
            $this->roles->release($name);
        });
    }

    public function delete(string $name): ?string
    {
        $problem = $this->unavailable();

        if ($problem !== null) {
            return $problem;
        }

        if ($this->roles->find($name) === null) {
            return 'That role was not found.';
        }

        $blocker = RoleRules::deleteBlocker($name, $this->config->superRole, $this->roles->isInConfig($name), count($this->authorizer->usersWithRole($name)));

        return $blocker ?? $this->attempt(function () use ($name): void {
            $this->roles->deleteRole($name);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Role $role): array
    {
        $name    = (string) $role->name;
        $isSuper = $this->config->superRole !== null && $name === $this->config->superRole;

        return [
            'name'        => $name,
            'title'       => (string) ($role->title ?: $name),
            'description' => (string) ($role->description ?? ''),
            'isSuper'     => $isSuper,
            'inConfig'    => $this->roles->isInConfig($name),
            'appManaged'  => $role->isAppManaged(),
            'permissions' => $isSuper ? [] : $this->roles->permissionsOf($name),
            'holders'     => count($this->authorizer->usersWithRole($name)),
        ];
    }

    /**
     * @param array<mixed> $posted
     *
     * @return list<string>
     */
    private function known(array $posted): array
    {
        return RoleRules::onlyKnown($posted, array_keys($this->permissionLabels()));
    }

    private function unavailable(): ?string
    {
        return $this->available() ? null : 'Editing roles needs ephraitech/ci4-auth 0.11 or later. Run "composer update ephraitech/ci4-auth", then "php spark migrate -n \"Ephraitech\\Auth\"".';
    }

    /**
     * Run a change; a refusal from the package comes back as its own message, anything else is logged and kept
     * from the page.
     */
    private function attempt(callable $change): ?string
    {
        try {
            $change();
        } catch (AuthException $e) {
            return $e->getMessage();
        } catch (\Throwable $e) {
            log_message('error', 'Changing a role failed: ' . $e->getMessage());

            return 'The change could not be saved. Please try again.';
        }

        return null;
    }
}
