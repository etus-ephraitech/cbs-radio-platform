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

## Appearance

Admin > Appearance (superadmin only) sets how the staff area looks, for everyone: colour mode, reading direction,
side or top menu and its style, page width and style, header and menu styles and behaviour, brand colour and
background pattern. The choices are ordinary settings (group `appearance`, validated and logged like the rest of
Admin > Settings; each has "Reset to default"), applied by `App\Libraries\ThemeAttributes` as `data-*` attributes on
`<html>` by the server, so there is no flash of the wrong theme. The theme's own floating switcher and the header
light/dark toggle are hidden, and any per-browser theme state left by them is cleared on load, so the saved look
always wins. It does not change the public website.

## List filters

Payments, Gift orders, Shop purchases and Gifts now have the same filter bar as Users (search, status, supplier or
provider, and a from/to date range) and, apart from Gifts, the same paging, at "Rows per page in admin lists" from
Admin > Settings. A filtered list can be bookmarked or shared. Everything in the query string is checked by
`App\Libraries\AdminFilters` (choices must be on the list, dates must exist, text is capped), so an unknown value
shows the whole list rather than an error. Bookings and the notifications log already had their own filters.

## Message editors

**Packages and voice notes.** Admin > Packages has an "Includes a voice note" switch per package. The booking form
shows the voice recorder only after a visitor chooses a package that has it (and drops anything recorded if they
switch to one that does not); a recording sent with another package is refused by the server. The migration turns
the switch on for packages that already mention a voice note in their name or description, so nothing already sold
changes; tick it for any other package that should have one.

**Template editor.** Admin > Message templates has buttons that insert `{recipient}` and `{sender}` at the cursor, a
live count against the booking form's limit, a warning for unknown placeholders, and a preview with sample names and
the on-air time.

**Visitor's message box.** "Their name" and "A sign-off with my name" add text at the cursor, using the names typed
so far or the same `[their name]` / `[your name]` markers the examples use. Undo covers them.

## Roles and permissions

**Admin > Roles** (superadmin only, same `role:superadmin` filter as Users) lists every role with where it comes
from, how many people have it and how many permissions it grants, and a matrix of who may do what. A role is made,
renamed, described and given permissions on its own page.

- It needs `ephraitech/ci4-auth` 0.12 (roles edited in the app arrived in 0.11): `composer update ephraitech/ci4-auth`,
  then `php spark migrate -n "Ephraitech\Auth"` (adds `auth_roles.app_managed`) and `php spark auth:sync`.
- Writing goes through the package's `service('authRoles')`. A role saved here is "managed in the app": `auth:sync`
  leaves it alone. **Hand back to configuration** returns a role that `Config\Auth` defines to the config, and the next
  `auth:sync` restores its configured title, description and permissions.
- The super role (`superadmin`) passes every check, so its permissions are not edited and it cannot be deleted. A role
  defined in `Config\Auth` cannot be deleted here (remove it from the config and run `auth:sync --prune`), and a role
  that people still have cannot be deleted until it is taken off them in Users.

### What a permission does

Five permissions guard the admin areas, one each, and the routes check them (`area:<permission>`, `App\Filters\AreaAccess`):

| Permission | Opens |
| --- | --- |
| `bookings.manage` | Bookings, Moderation queue, Payments |
| `gifts.manage` | Gifts, Gift vendors, Gift orders, Shop purchases |
| `catalog.manage` | Categories, Shows, Packages, Message templates |
| `notifications.manage` | Notifications |
| `settings.manage` | Settings |

The dashboard stays open to all staff. Users, Roles, Logo and Appearance are always superadmin-only. The side menu hides
the areas a person's roles do not include, and the routes refuse them too. `Config\Auth` grants all five to `administrator`
(`admin`), so nothing changes for them once `php spark auth:sync` has run.

Until `auth:sync` has created a permission, its area stays open to signed-in staff (as before roles had a say), so a deploy
cannot lock administrators out in the gap. Once the permission exists, it is enforced.
