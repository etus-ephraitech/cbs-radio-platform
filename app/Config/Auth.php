<?php

namespace Config;

use Ephraitech\Auth\Config\Auth as BaseAuth;
use Ephraitech\Auth\Notifications\AfricasTalkingNotifier;
use Ephraitech\Auth\Notifications\EmailNotifier;

/**
 * Ephraitech Auth settings for this application.
 *
 * Only properties declared here override the package defaults.
 * Every option is documented in vendor/ephraitech/ci4-auth/src/Config/Auth.php;
 * copy any other property here to change it.
 *
 * After changing $roles, $permissions or $matrix, run: php spark auth:sync
 */
class Auth extends BaseAuth
{
    public bool $allowRegistration = false;

    public array $identifiers = [
        'email',
    ];

    public array $loginIdentifiers = [
        'email',
    ];

    public array $requiredIdentifiers = [
        'email',
    ];

    public array $requireVerifiedToLogin = [
        'email' => true,
        'username' => false,
        'phone' => false,
    ];

    public array $userAllowedFields = [];

    public string $phoneDefaultCountryCode = '256';

    public string $tenancy = 'single';

    public array $defaultRoles = [
        'user',
    ];

    public array $roles = [
        'superadmin' => [
            'title' => 'Super Admin',
            'description' => 'Unrestricted access to everything.',
        ],
        'admin' => [
            'title' => 'Administrator',
            'description' => 'Manages users, roles and access tokens.',
        ],
        'user' => [
            'title' => 'User',
            'description' => 'Standard authenticated user.',
        ],
    ];

    public array $permissions = [
        'users.view' => 'View users',
        'users.create' => 'Create users',
        'users.update' => 'Update users',
        'users.delete' => 'Delete users',
        'roles.manage' => 'Manage roles and permissions',
        'tokens.manage' => 'Manage access tokens and API keys',
    ];

    public array $matrix = [
        'superadmin' => [
            '*',
        ],
        'admin' => [
            'users.*',
            'roles.manage',
            'tokens.manage',
        ],
        'user' => [],
    ];

    public array $redirects = [
        'login' => '/login',
        'afterLogin' => '/admin', // staff sign in to work, not to the public homepage
        'afterLogout' => '/login',
        'forbidden' => '/',
        'afterRegister' => '/login',
    ];

    public string $appName = 'CBS Radio';

    /**
     * The account pages are drawn by app/Views/auth/* in the station's own style (assets/css/auth.css),
     * not by the package's plain Bootstrap pages.
     */
    public string $viewLayout = 'auth/layout';

    /**
     * @var array<string, string>
     */
    public array $views = [
        'login'           => 'auth/login',
        'register'        => 'auth/register',
        'forgot'          => 'auth/forgot',
        'reset'           => 'auth/reset',
        'verify'          => 'auth/verify',
        'change_password' => 'auth/change_password',
        'messages'        => 'auth/_messages',
    ];

    // public string $emailFromAddress = 'no-reply@example.com';
    // public string $emailFromName    = 'Field Tracker';

    public array $notifiers = [
        'email' => EmailNotifier::class,           
        // 'sms'   => AfricasTalkingNotifier::class,  
    ];
}
