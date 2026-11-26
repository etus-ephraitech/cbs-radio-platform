# Admin > Users (superadmin only)

Under **Admin > Users** a superadmin sees everyone who can sign in, filters them (email search, role, status), adds
people, changes their roles and status, resets a password and deletes an account. An administrator never sees the
menu link, and the routes carry the auth package's `role:superadmin` filter, so typing the address is refused too.

- Users, roles and passwords are `ephraitech/ci4-auth`'s. Roles come from `Config\Auth::$roles` (run
  `php spark auth:sync` after changing them); this screen only assigns them.
- A new account, and a reset, gets a **generated password shown once** on the next page. It is never stored in clear or
  sent anywhere. A reset also ends the person's other sign-ins.
- Safety rules (`App\Libraries\SuperAdminGuard`): nobody changes their own roles, status or account, and the last
  active superadmin cannot be demoted, suspended or deleted.
- The first superadmin is made on the command line:
  `php spark auth:user:create --role=superadmin --generate`.
