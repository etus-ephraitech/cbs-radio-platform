<?php

namespace App\Services;

use App\Libraries\SuperAdminGuard;
use Config\Database;
use Ephraitech\Auth\Authorization\Authorizer;
use Ephraitech\Auth\Config\Auth;
use Ephraitech\Auth\Config\ConfigResolver;
use Ephraitech\Auth\Entities\User;
use Ephraitech\Auth\Exceptions\AuthException;
use Ephraitech\Auth\Exceptions\RegistrationException;
use Ephraitech\Auth\Models\UserModel;
use Ephraitech\Auth\Passwords\PasswordChanger;
use Ephraitech\Auth\Registration\UserRegistrar;

/**
 * Who can sign in to the admin area and what they may do: the one place the app talks to ephraitech/ci4-auth's
 * users, roles and passwords. Only a superadmin reaches it (see the `role:superadmin` filter on the routes).
 *
 * It never invents its own idea of a user or a role: users are the package's users, roles are the package's roles
 * (Config\Auth::$roles, `php spark auth:sync`), and passwords go through the package's hasher and policy. What it
 * adds is a list that can be filtered, the safety rules in SuperAdminGuard, and passwords that are generated, shown
 * once, and never stored in clear.
 */
class StaffUserService
{
    public const PER_PAGE = 20;

    private Auth $config;
    private UserModel $users;
    private Authorizer $authorizer;

    /**
     * The auth config the application really defines. Package 0.10.3 and later has a resolver for this; before
     * that, the application's own Config\Auth is the one to read (a fully qualified package class would skip it).
     */
    private static function authConfig(): Auth
    {
        if (class_exists(ConfigResolver::class)) {
            return ConfigResolver::resolve();
        }

        $app = \Config\Auth::class;

        return class_exists($app) && is_subclass_of($app, Auth::class) ? config($app) : config(Auth::class);
    }

    public function __construct(?Authorizer $authorizer = null, ?UserModel $users = null)
    {
        $this->config     = self::authConfig();
        $this->authorizer = $authorizer ?? service('authAuthorizer');
        $this->users      = $users ?? new UserModel();
    }

    /**
     * The roles that can be given, name => title.
     *
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        $roles = [];

        // Newer package versions keep roles in the database (service 'authRoles'); older ones only know the roles
        // in Config\Auth::$roles, which `php spark auth:sync` copies to the database.
        $manager = service('authRoles');

        if ($manager !== null) {
            foreach ($manager->all() as $role) {
                $roles[(string) $role->name] = (string) ($role->title ?: $role->name);
            }

            return $roles;
        }

        foreach ($this->config->roles as $name => $definition) {
            $roles[(string) $name] = (string) (($definition['title'] ?? '') ?: $name);
        }

        return $roles;
    }

    /**
     * @return array<string, string> status => label
     */
    public function statusOptions(): array
    {
        return [
            'active'    => 'Active',
            'suspended' => 'Suspended',
            'inactive'  => 'Inactive',
            'pending'   => 'Pending',
        ];
    }

    /**
     * One page of users, newest first, with the email, status, sign-in times and roles of each.
     *
     * @param array{q?: string, status?: string, role?: string} $filters
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function search(array $filters, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $db       = Database::connect($this->config->DBGroup);
        $users    = $this->config->table('users');
        $idents   = $this->config->table('identities');
        $page     = max(1, $page);
        $builder  = $db->table($users . ' u')
            ->select('u.id, u.uuid, u.status, u.status_reason, u.last_login_at, u.created_at, i.identifier AS email')
            ->join($idents . ' i', "i.user_id = u.id AND i.type = 'email'", 'left')
            ->where('u.deleted_at', null);

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $builder->like('i.identifier', $q);
        }

        $status = (string) ($filters['status'] ?? '');

        if ($status !== '' && array_key_exists($status, $this->statusOptions())) {
            $builder->where('u.status', $status);
        }

        $role = (string) ($filters['role'] ?? '');

        if ($role !== '' && array_key_exists($role, $this->roleOptions())) {
            $builder->whereIn('u.id', $this->authorizer->usersWithRole($role) ?: [0]);
        }

        $total = (clone $builder)->countAllResults(false);
        $rows  = $builder->orderBy('u.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['roles'] = $this->authorizer->rolesFor((int) $row['id']);
        }
        unset($row);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    /**
     * One user with the email and roles, or null.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $user = $this->users->find($id);

        if (! $user instanceof User) {
            return null;
        }

        return [
            'id'            => (int) $user->id,
            'uuid'          => $user->uuid,
            'email'         => $user->email ?? '',
            'status'        => $user->status,
            'status_reason' => $user->status_reason,
            'last_login_at' => $user->last_login_at,
            'created_at'    => $user->created_at,
            'roles'         => $this->authorizer->rolesFor($id),
        ];
    }

    /**
     * Creates an account with a generated password, returned once so the superadmin can pass it on.
     *
     * @param list<string> $roles
     *
     * @return array{user: User, password: string}
     *
     * @throws RegistrationException the field errors (an email already in use, say)
     * @throws AuthException
     */
    public function create(string $email, array $roles): array
    {
        $password = self::generatePassword();
        $roles    = $this->onlyKnownRoles($roles) ?: $this->config->defaultRoles;

        $user = UserRegistrar::create($this->config, $this->authorizer)->register(
            ['email' => $email],
            $password,
            [],
            ['roles' => $roles, 'status' => 'active', 'verified' => ['email'], 'enforcePolicy' => false],
        );

        return ['user' => $user, 'password' => $password];
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(int $actorId, int $userId, array $roles): ?string
    {
        $roles   = $this->onlyKnownRoles($roles);
        $problem = $this->guard('roles', $actorId, $userId, $roles);

        if ($problem !== null) {
            return $problem;
        }

        $this->authorizer->syncRoles($userId, $roles);

        return null;
    }

    public function setStatus(int $actorId, int $userId, string $status, ?string $reason = null): ?string
    {
        if (! array_key_exists($status, $this->statusOptions())) {
            return 'Choose a status from the list.';
        }

        $problem = $this->guard('status', $actorId, $userId, [], $status);

        if ($problem !== null) {
            return $problem;
        }

        $this->users->setStatus($userId, $status, $reason !== null && trim($reason) !== '' ? trim($reason) : null);

        return null;
    }

    /**
     * A new generated password, returned once. The person's other sign-ins and tokens are revoked by the package.
     *
     * @return array{password: ?string, error: ?string}
     */
    public function resetPassword(int $userId): array
    {
        if ($this->users->find($userId) === null) {
            return ['password' => null, 'error' => 'That user no longer exists.'];
        }

        $password = self::generatePassword();

        /** @var PasswordChanger $changer */
        $changer = service('authPasswordChanger');
        $changer->reset($userId, $password);

        return ['password' => $password, 'error' => null];
    }

    public function delete(int $actorId, int $userId): ?string
    {
        $problem = $this->guard('delete', $actorId, $userId);

        if ($problem !== null) {
            return $problem;
        }

        $this->users->delete($userId);

        return null;
    }

    /**
     * A password that satisfies any sensible policy: 16 characters with upper and lower case, a digit and a symbol,
     * without the look-alikes (0 O 1 l I) that make it hard to read out over the phone.
     */
    public static function generatePassword(): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%&*?'];
        $all  = implode('', $sets);
        $out  = [];

        foreach ($sets as $set) {
            $out[] = $set[random_int(0, strlen($set) - 1)];
        }

        while (count($out) < 16) {
            $out[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($out) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$out[$i], $out[$j]] = [$out[$j], $out[$i]];
        }

        return implode('', $out);
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function onlyKnownRoles(array $roles): array
    {
        $known = array_keys($this->roleOptions());

        return array_values(array_unique(array_filter(array_map('strval', $roles), static fn (string $r): bool => in_array($r, $known, true))));
    }

    /**
     * @param list<string> $newRoles
     */
    private function guard(string $action, int $actorId, int $userId, array $newRoles = [], string $newStatus = 'active'): ?string
    {
        $target = $this->users->find($userId);

        if (! $target instanceof User) {
            return 'That user no longer exists.';
        }

        $supers      = $this->authorizer->usersWithRole(SuperAdminGuard::ROLE);
        $otherActive = 0;

        foreach ($supers as $superId) {
            if ($superId === $userId) {
                continue;
            }

            $other = $this->users->find($superId);

            if ($other instanceof User && $other->isActive()) {
                $otherActive++;
            }
        }

        return SuperAdminGuard::violation(
            $action,
            $actorId,
            $userId,
            in_array($userId, $supers, true),
            $target->isActive(),
            $otherActive,
            $newRoles,
            $newStatus,
        );
    }
}
