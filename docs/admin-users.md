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

## Logo

Admin > Logo (superadmin only, same `role:superadmin` filter as Users) uploads the station logo and the
browser-tab icon. PNG, JPG or WebP for the logo (up to 1 MB), PNG or ICO for the icon (up to 256 KB). SVG is
refused on purpose: it can carry script and the files are public. Files are written to `public/uploads/branding/`
under generated names (the folder must be writable by PHP) and their paths are saved as settings, so
`config('CbsRadio')->logoPath` is what views read, through `cbs_brand()`. With nothing uploaded, or if a file goes
missing, every page shows the built-in CBS Radio name and lamp. The logo appears in the public header and footer,
both admin shells and the sign-in page. Replacing or removing a file deletes the old one.
