# CBS Radio Platform — Contributing Guide

Team: 3 developers (sNOW as lead). This guide exists so all three of us build
the same way without needing to ask each time. Read it once, follow it by
default; raise a change in standup if something here is slowing us down.

## 1. Branching model

Two long-lived branches, everything else is short-lived:

- `main` — always deployable. Protected: no direct pushes, no force-push.
  Only the lead merges into it, and only from `develop` at the end of a week
  or at launch.
- `develop` — integration branch. Protected: no direct pushes. All feature
  branches merge here via Pull Request.

Short-lived branches, cut from `develop`:

| Prefix | Use | Example |
|---|---|---|
| `feature/` | New functionality | `feature/booking-message-builder` |
| `fix/` | Bug fix found during the build | `fix/voice-note-upload-mime-check` |
| `chore/` | Tooling, config, non-feature work | `chore/ci-pipeline-setup` |

Naming: `prefix/short-kebab-case-description`. No ticket numbers needed yet
at our scale — the description should be clear on its own.

**Rule:** a branch maps to one piece of work. Don't pile unrelated changes
into one feature branch — it makes review slow and rollback dangerous.

## 2. Daily workflow

```bash
git checkout develop
git pull origin develop
git checkout -b feature/your-feature-name

# ...work, commit as you go...

git push -u origin feature/your-feature-name
# open a Pull Request into develop
```

Before opening a PR, rebase on the latest `develop` so the diff is clean:

```bash
git fetch origin
git rebase origin/develop
```

## 3. Commit messages

Conventional Commits, short and specific:

```
<type>: <short description>

[optional longer explanation]
```

Types: `feat`, `fix`, `chore`, `refactor`, `docs`, `test`.

Good: `feat: add voice note upload with 30s duration cap`
Avoid: `updates`, `fix stuff`, `wip`

Commit often locally; it's the PR that needs to be clean, not every commit.

## 4. Pull Requests

- Every PR targets `develop`, never `main` directly.
- PR description states: what changed, why, and how to test it manually.
- **At least one review required before merge** — the lead reviews all PRs
  touching shared/core code (auth, booking data model, payment flow); the
  other two developers can cross-review PRs on isolated feature areas.
- CI must pass (code style + static analysis + tests) before merge is
  allowed — see Section 6.
- Squash-merge into `develop` so the history on `develop` reads as one
  commit per feature, not every WIP commit.
- Delete the branch after merge.

## 5. Code ownership (to avoid collision with 3 people in one repo)

To keep three people from stepping on each other in the same modules during
the 6-week build, default ownership is:

- **Dev A:** Booking flow (category selection, message builder, voice note
  upload, scheduling)
- **Dev B:** Digital gifts catalog + Mobile Money checkout + admin
  reconciliation
- **Dev C:** Presenter portal + admin moderation panel
- **Lead (sNOW):** Auth/API starter-kit integration, shared services,
  DB schema changes, final review and merges to `main`

Crossing into someone else's module is fine — just flag it in the team
channel first so you don't both touch the same file at once.

## 6. Code standards & local checks

Run before every push:

```bash
composer cs-check   # PSR-12 style check (PHP_CodeSniffer, rules in phpcs.xml)
composer cs-fix      # auto-fixes what it can
composer analyse     # static analysis (PHPStan level 5, rules in phpstan.neon)
composer test        # PHPUnit
```

Both style and static analysis skip `app/Views` (templates are HTML mixed with
PHP, so PSR-12 and PHPStan only produce noise there) and `app/Config`
(framework-generated). Everything else under `app/` is checked.

These four also run in CI on every PR (see `.github/workflows/ci.yml`) — a
PR with a failing check cannot be merged, so running them locally first
saves everyone time.

A pre-commit hook that runs `cs-check` on staged files automatically is in
`scripts/git-hooks/pre-commit` — see its header comment for the one-line
setup command.

## 7. Database migrations

All schema changes go through CI4 migrations, never manual DB edits on
shared environments:

```bash
php spark make:migration AddStatusToBookings
php spark migrate
```

One migration per PR when possible, named for what it does, not who wrote
it. If two people need overlapping schema changes in the same week, say so
in standup — migrations are the one thing where a silent collision is
expensive to unwind.

## 8. Environment setup (new machine)

Clone the repo, then run the setup script for your OS — it installs
dependencies, creates your `.env`, enables the shared pre-commit hook, and
offers to check you out onto your module's feature branch.

```bash
git clone git@github.com:ephraitech/cbs-radio-platform.git
cd cbs-radio-platform

# Linux / macOS:
chmod +x scripts/dev-setup.sh
./scripts/dev-setup.sh

# Windows (PowerShell):
.\scripts\dev-setup.ps1
```

Then run migrations and start the server:

```bash
php spark migrate
php spark db:seed DemoSeeder   # local sample data — see Section 9
php spark serve
```

The repo itself (branches, protection rules) is bootstrapped once by the
lead using `scripts/bootstrap-repo.sh` (Linux/macOS) or
`scripts/bootstrap-repo.ps1` (Windows) — see the comments in each script.

## 9. Useful custom commands

Beyond CI4's built-ins (`migrate`, `serve`, `routes`), this project adds:

- `php spark make:module <Name>` — scaffolds a Controller, Model, and
  migration stub for a new feature area, following this project's existing
  naming conventions (see `app/Commands/MakeModule.php`).
- `php spark db:seed DemoSeeder` — seeds local dev data (the CBS rate card,
  shows, our own gifts, and sample bookings in every status) so nobody is
  testing against an empty DB. Skips itself if data already exists.
- `php spark gifts:sync` — syncs every active gift vendor's catalog into the
  gift catalog. Safe to run on a cron.
- `php spark gifts:reconcile` — checks partner shop (Katale) purchases that are
  waiting for payment or in delivery. Safe to run on a cron (every 10 minutes).

Propose new ones in the team channel before building — if two of us need
the same helper, it should exist once, not twice.

## 10. Admin views: structure and conventions

Admin views live in `app/Views/admin/<module>/index.php` and `form.php`
(`bookings` also has `show.php`), all extending `layouts/admin`. Build them from
the shared cells rather than re-writing markup:

| Need | Use |
|---|---|
| Page title, back link, primary button | `App\Cells\PageHeader` |
| Validation errors at the top of a form | `App\Cells\FormErrors` |
| Deactivate / Activate buttons on a list row | `App\Cells\ActiveToggle` |
| Prev / Next paging that keeps the filters | `App\Cells\Pagination` |
| Booking / payment / gift-order / notification / active badges | `App\Cells\StatusBadge` |
| "5 minutes ago" | `App\Cells\TimeAgo` |
| Confirm-then-submit button | `App\Cells\ConfirmButton` |
| One row of the settings form | `App\Cells\SettingField` |

A booking reference in any list should link to `admin/bookings/{id}`.

## 11. Pitfalls we have already hit (so you don't)

- **Every public property on a View Cell needs a default value.** The framework
  only fills properties that are initialised; one declared as
  `public string $title;` is silently skipped and throws on first use. It also
  fills in strict mode, so a property typed `string` rejects an int — use a
  union type (`string|int`) when a view might pass either.
- **`$model->like()` does not escape `%` and `_` in the value.** It adds the
  `ESCAPE` clause but leaves the value alone. Escape them yourself (see
  `BookingModel::adminBaseQuery()`), but don't also escape quotes — the value
  binding already does, and doing it twice breaks names like O'Brien.
- **`forge->modifyColumn()` redefines the whole column.** State `'null' => false`
  again, or a `NOT NULL` column quietly becomes nullable.
- **Adding a foreign key to a table that already exists** — use raw SQL
  (`ALTER TABLE ... ADD CONSTRAINT`), not `forge->addForeignKey()`.
- **A redirect after a POST is a `303`, not a `302`.** Don't assert on 302 in tests.
- **Read configurable values from the Config class, never from `env()`.** Settings
  are applied on top of the Config classes, so `config('CbsRadio')->messageMaxLength`
  honours an admin change and `env('content.messageMaxLength')` silently does not.
  (`env()` is only right for secrets.) The SMS sender name and the Mobile Money
  provider were being read straight from `.env` and would have ignored the settings
  screen; so was a hardcoded `max_size[voice_note,2048]` validation rule that would
  have rejected a file even after the limit was raised.
- **`old()` already HTML-escapes.** Wrapping it in `esc()` double-escapes (`&` becomes
  `&amp;amp;`) after a failed save. Use `esc(old('field', null, false) ?? ...)`.
- **A GET must not create records.** The pay page used to insert a payment row on
  every load, so a refresh made a duplicate. Reuse the pending one, and only start a
  payment for a booking that is actually waiting for it.
- **Don't put dots in form field names you read back with `old()`** — it treats them as
  array paths. The settings form uses `s[payments_mode]`, not `s[payments.mode]`.

## 12. Adding a setting

1. Make sure the value is read from a Config class property (add one if needed, with
   its `.env` fallback in the constructor).
2. Add an entry to `app/Libraries/Settings/Registry.php`: group, label, type
   (`bool`, `int`, `string`, `text`, `select`, `list`), limits, help text, and
   `'target' => ['ConfigClassName', 'property']`. A dotted path reaches into an array
   property (`'channelsEnabled.sms'`).
3. If the Config class doesn't use `AppliesSettingOverrides` yet, add the trait and call
   `$this->applySettingOverrides('ConfigClassName')` as the last line of its constructor.
4. Never register a secret. If something is sensitive, leave it in `.env` and, if it
   helps, list it under `Registry::secrets()` so the screen can show whether it's set.
- **CodeIgniter keeps the FIRST definition of a route and silently ignores later ones.**
  Defining `/` again in our routes file does nothing while the starter's own
  `$routes->get('/', 'Home::index')` exists. Delete the starter's route.
- **Never HTML-escape inside a `<style>` or `<script>` block.** Entities aren't decoded
  there, so `esc($url)` turns `http://` into `http&#x3A;&#x2F;&#x2F;` and the request fails.
  Values that are constants or built from the site's own base URL can be output as they are.
- **Use plain `esc()` for quoted HTML attributes**, not `esc($x, 'attr')`. The `attr`
  context is for *unquoted* attributes and encodes every space, slash and colon, which
  bloats the page and makes the source unreadable. Reserve `'url'` for a value placed
  inside a query string.
- **The public fonts cover Latin text only.** A symbol that isn't in them (an arrow, a
  tick, an emoji) shows as an empty box wherever no system font covers it. Use an inline
  SVG instead. Check with `fontTools` against the `.woff2` files if unsure; at the last
  check the public templates used only ©, ·, – and —, all covered.
- **Test on a clock the app agrees with.** The app runs on `app.appTimezone`
  (Africa/Kampala); a database server may not. When a test builds times in SQL, derive
  them from the app's zone (`TZ=Africa/Kampala date`), not `NOW()`.

- **PHP reports audio-only WebM and MP4 as `video/webm` and `video/mp4`.** Measured with real files: every
  Chrome, Edge and Android recording is `video/webm`, Safari's is `video/mp4`, phone voice memos are `audio/x-m4a`
  and `.aac` is `audio/x-hx-aac-adts`. A MIME allow-list of only `audio/*` silently rejects the most common
  recordings. Test uploads with real files (ffmpeg makes them in one line), never only a synthetic WAV.
- **An error that appears when a field loses focus can swallow the next tap.** It pushes everything below it
  down between press and release, so the control the person was tapping is no longer under their finger. Errors
  caused by leaving a field wait until the press is finished (see `guard()` in `booking.js`), and an *empty*
  required field isn't reported until the first Send. Keep it that way when adding fields.
- **Browser-recorded WebM has no duration in its header.** `<audio>` shows "0:00", and `ffprobe` returns `N/A` for
  the format length. `booking.js` seeks past the end to make the browser compute it; `VoiceNoteUploader` falls back
  to the last audio packet.
- **Don't test a microphone with an unconnected audio destination.** It delivers no audio at all, whereas a real
  muted microphone delivers silence. Connect a zero-gain oscillator to simulate a muted one.
- **Never silently drop a failed upload.** If PHP itself rejects a file (over `upload_max_filesize`), `isValid()` is
  false; checking only `isValid()` makes the booking go through without the voice note and without a word.

## 13. The public site

- **One shell for visitors:** `layouts/public.php`. Pass `'lean' => true` from the
  controller for a page that needs no Bootstrap (the homepage). Without it the page gets
  Bootstrap, jQuery and Toastr and a centred container, which is what the booking and
  gift pages are built with.
- **Don't invent content.** Every price, name, time and contact detail on a public page
  comes from the database or Settings. Copy that describes behaviour (how payment works,
  whether a text is sent) must follow what the platform really does, and change with the
  setting that controls it (payment mode, SMS on or off).
- **Staff links stay out of the public header.** The footer has one small "Staff" link.
- **One animation only** on the homepage (the radio dial on load), switched off for
  visitors who prefer reduced motion. Resist adding more.
- **Colours and type** are CSS variables at the top of `layouts/public.php` (`--ink`,
  `--gold`, `--lamp`, `--mist`, …). Text/background pairs there were checked against
  WCAG AA; re-check a pair before introducing a new one.

- **Every new public route must carry the `SiteStatus` filter**, or visitors can still
  reach it while the site is closed. Add `['filter' => \App\Filters\SiteStatus::class]`
  to the route or its group in `app/Config/Routes/cbs_radio.php`. The only routes left
  unfiltered on purpose are admin, the presenter portal and the payment callback; say so
  in a comment if you add another. `tests/feature/SiteStatusTest.php` lists the routes.
- **Status pages render with `SiteStatusPage::respond()`**, which sets the 503, the
  robots header and no-store caching in one place. Don't render `site_status` directly.

- **Hosted-checkout vendors (Katale) are driven by `gift_vendors.driver`.** Pick the client with
  `VendorClients::for($vendor)`/`::hosted($vendor)`, never `new` a concrete client, so a second hosted
  vendor is a new class and a new driver value. "Paid" for such a purchase comes only from the vendor's
  status endpoint, in `HostedCheckoutService::status()`; don't set `paid` anywhere else.

## 14. The booking form's script and styles

- Everything the message editor and recorder do is in `public/assets/js/booking.js`, in three marked parts (message
  editor, voice note, form checks). Add behaviour there, not in inline `<script>` tags in the view.
- Limits and allowed file types come from `data-*` attributes on `<form id="bookingForm">`; add a new one there
  rather than hardcoding a number in the script.
- The server checks everything again. The browser checks exist to save a round trip, not as the only defence.
- Class names in `booking.css` are prefixed `bk-` so they can't collide with Bootstrap.
- Example messages live in the `message_templates` table (Admin > Message templates). Their placeholders are
  limited to `MessageTemplateModel::TOKENS`; the admin form rejects any other `{name}`.

## 15. Writing and running tests

`tests/README.md` is the guide: setup, the three layers, the helpers and fakes. The rules of the road:

- **Every bug fix comes with the test that would have caught it**, written first so you see it fail. A third of
  the defects found while building this suite had no visible symptom until a test asked the right question.
- **`composer test` must pass before you open a pull request**, along with `composer cs-check` (which covers
  `tests/` too) and `composer analyse`.
- **Never let a test reach the outside world.** SMS, vendor and payment calls go through `Services::curlrequest()`;
  use `FakeHttpGateway`. Mail goes through the mocked email service. If you add a new outside call, give it a fake
  before you write the code that uses it.
- **Test with real files for anything file-shaped.** The voice-note bug that survived longest passed every test that
  used a synthetic WAV and failed for every real Chrome recording.

Traps we have already fallen into while testing (so you don't):

- **CodeIgniter's strict transaction mode persists a failure.** One failed query inside a transaction leaves
  `transStatus` false on that connection, and every later transaction on it then fails until the process ends.
  A web request ends at once, but a worker, a command or a test does not. After a failed transaction call
  `$db->resetTransStatus()` (see `BookingService`).
- **`find(null)` means "every row", not "no row".** Never pass an id that might be missing straight to `find()`;
  guard it (`empty($id) ? null : $model->find($id)`).
- **`update($id, $data)` does not fill the `{id}` placeholder in an `is_unique[...,id,{id}]` rule**, so saving a row
  with its unique value unchanged is refused as a duplicate of itself. See `GeneratesUniqueSlugs::update()`,
  and note that the model also needs a rule for `id` itself.
- **In the `testing` environment, a missing page throws `PageNotFoundException`** instead of returning a 404, so
  assert it with `expectException`.
- **Every `$this->get()` / `->post()` in one feature test shares one configuration object.** A setting saved by an
  earlier request in the same test is not seen by the next until you call `reloadConfig()`; a real next request is
  a fresh process and does see it.
- **The session is reset on every feature request**, so a flash message set before a redirect is read with
  `flash('toastr_error')` from what that request left behind, not by following the redirect.
- **Helper names that collide with the frameworks** (`call`, `callback`, `get`, `post`, `session`) kill the run with a
  fatal error before any test reports.
- **A list sorted only by a one-second timestamp can reshuffle itself.** Always add the id as a tie-breaker.

