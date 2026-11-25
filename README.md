# CBS Radio Platform

Digital congratulatory & tribute platform for **CBS Radio** — a web
application where listeners submit, schedule, and pay for on-air greetings,
tributes, and milestone announcements, purchase digital gifts, and where
CBS presenters and admin staff manage and air submissions.

Built by **Ephraitech Unified Solutions** on the Ephraitech CodeIgniter 4
API starter kit.

- **Client:** CBS Radio
- **Engagement:** Phase 1, 6-week web application build
- **Status:** In development

## What's in Phase 1

- Web booking flow: category selection → message builder → optional voice
  note → slot scheduling
- Digital gifts catalog (e-cards, airtime top-up, shout-outs)
- Single-provider Mobile Money checkout, with a manual "mark as paid"
  admin fallback
- Presenter portal — live, approved-queue feed sorted by show/time-slot
- Admin panel — moderation, payment reconciliation, reporting
- SMS/email confirmation on submission and on-air status
- A settings screen where staff change payment mode, notification channels and
  message wording, contact details, and upload/message limits without a deploy

Native mobile app, physical gift fulfillment, multi-language templates, and
diaspora card payments are deferred to Phase 2 — see the signed scope
document for the full breakdown.

## Tech stack

- PHP 8.1+, CodeIgniter 4
- MySQL
- Bootstrap 5 (frontend)
- Ephraitech API starter kit (DB-backed token auth)
- `ephraitech/ci4-error-logger` for error logging + webhook alerts

## Getting started

```bash
git clone git@github.com:ephraitech/cbs-radio-platform.git
cd cbs-radio-platform
composer install
cp .env.example .env
# fill in local DB credentials and any provider keys you have
php spark migrate
php spark db:seed DemoSeeder
php spark serve
```

The app will be available at `http://localhost:8080`.

## One-time wiring (first developer, once)

This repository holds the project's `app/` code. Dropping it into a CodeIgniter 4
app needs five edits to files the framework owns:

1. **Helpers** — in `app/Config/Autoload.php`:
   `public $helpers = ['text', 'number', 'ui', 'form'];`
   (`form` is needed for `csrf_field()` and `old()` in every view.)
2. **Routes** — in `app/Config/Routes.php`, **delete** the starter's own homepage
   route, `$routes->get('/', 'Home::index');` (CodeIgniter keeps the first definition
   of a route and silently ignores later ones, so if it stays, the framework's welcome
   page keeps answering at `/` and the real homepage never appears). Then add, at the
   bottom: `require APPPATH . 'Config/Routes/cbs_radio.php';`. You can also delete the
   starter's `Controllers/Home.php` and `Views/welcome_message.php`.
   The fonts the public site uses are in `public/assets/fonts/` — keep that folder.
3. **Auth filter** — the routes use a filter called `auth` for presenter and
   admin pages. Register it in `app/Config/Filters.php` (`$aliases`) pointing at
   your starter kit's real authentication filter, and make
   `Admin\BaseAdminController::currentUserId()` read the logged-in user the way
   that filter exposes it (it currently reads `session('user_id')`).
4. **`users` table** — the migrations reference `users(id)` as an
   `INT UNSIGNED`. Your starter kit must have created it before you run
   `php spark migrate`; if its `id` is a different type, adjust the foreign
   keys in the bookings, payments and moderation_logs migrations.
5. **`.env`** — copy `.env.example` and fill in the database, SMS, Mobile Money
   and email settings. Vendor API keys go in as `vendor.{slug}.apiKey`. The
   non-secret values in it are only *starting values*: anything an admin saves
   under Admin > Settings overrides them (see Settings, below).

6. **CSRF and the payment callback** — if your starter turns CSRF protection on for every POST (look at
   `Config\Filters::$globals`), exempt `payments/callback`. A Mobile Money provider calls that address from its own
   servers and cannot send a CSRF token, so without the exemption every callback is refused (the address is
   protected by a secret token instead: `momo.callbackToken` in `.env`).

Then: `php spark migrate`, optionally `php spark db:seed DemoSeeder`, and
`php spark serve`.

## Settings

Staff change operational values under **Admin > Settings** (General, Public site,
Payments, Notifications, Message templates, Bookings & content). Precedence, highest first:

1. a value saved in Admin > Settings (stored in the `settings` table)
2. the value in `.env`
3. the default in the Config class

"Reset to default" on any field removes the admin value. Every change is
recorded (who, what, from, to) and shown under *Recent changes*.

**Secrets are never settings.** API keys, secrets, the callback token and the SMTP
password stay in `.env`; the Payments and Notifications tabs show only whether each
is set, never its value. Payments start in **manual** mode (the payer is told how to
pay and staff mark it paid) until a Mobile Money provider is confirmed; online mode
cannot be switched on until a provider and its credentials are in place.

Before the `settings` table exists (a fresh install before `php spark migrate`) the app
simply runs on `.env` and the defaults.

**To make another value configurable**, add an entry to
`app/Libraries/Settings/Registry.php` that points at an existing Config property
(`'target' => ['CbsRadio', 'someProperty']`) — the type, limits, help text and group
are all declared there. Nothing else changes: the Config class applies the override,
code that already reads the Config class picks it up, and the form builds itself.

## Partner shop gifts (Katale Plus)

Gifts from Katale Plus are delivered by Katale, so they are bought differently from our
own e-cards and airtime: the customer pays **Katale**, not us.

1. **Catalogue.** Add the vendor under Admin > Gift Vendors with Integration
   *Katale Plus*, name it `Katale` (so the slug is `katale`), leave the address empty
   (it defaults to `https://kataleplus.com/api/v1/partner`) and put the partner key in
   `.env` as `vendor.katale.apiKey`. `php spark gifts:sync` (or the *Sync now* button)
   mirrors every page of the catalogue into the gifts table: title, picture, the offered
   price (or the selling price when there is no offer), the notice Katale needs, and the
   units left. A sync that cannot read the whole catalogue is reported as failed; it never
   stores a partial one as if it were complete.
2. **Cart.** Katale gifts appear on the gifts page under *Gifts delivered to their door*,
   with an *Add to cart* button, instead of in the pay-us form. The cart is kept in the
   visitor's session (up to 20 different gifts, 100 of each).
3. **Checkout.** *Continue to delivery and payment* re-reads each gift from Katale (stock,
   price, inventory), opens a checkout session with an `Idempotency-Key`, and sends the
   visitor to the address Katale returns. The visitor gives the delivery address and
   arrival dates and pays on Katale. The final price is Katale's; ours is an estimate.
4. **Coming back.** `gifts/checkout/{token}` asks Katale for the real state (at most every
   8 seconds per purchase) and shows payment and delivery progress. Returning to the
   site, or an order id in the address, never counts as payment. Only Katale's status
   endpoint saying `paid` does, and a paid purchase is never put back to unpaid.
5. **Staff.** Admin > Shop Purchases lists every purchase with its state, final amount,
   Katale's reference and each gift's delivery state, with *Check with the shop now*.
   `php spark gifts:reconcile` (cron, every 10 minutes) finds customers who paid and
   never came back, because Katale sends no webhook.

Things to know:

- Keep the partner key in `.env` only. It is never sent to the browser, stored, or logged.
- Ask Katale to register the site's https address for the key, so the return link works.
  On a machine without https the return link is left out and Katale uses the registered one.
- Katale allows 60 requests a minute per IP, and 10 checkouts a minute. The cart reads each
  gift once per checkout, so a 20-gift cart uses 21 requests.
- A hosted-checkout gift cannot be ordered through the pay-us form, even by a hand-made
  request. A greeting booking cannot have a Katale gift attached (they are separate checkouts).
- Test a purchase with Katale before enabling it for customers, as their guide asks.

## Coming soon and maintenance modes

Under **Admin > Settings > Site status** staff can close the public site without a
deploy:

- **Live** (default): everything works.
- **Under maintenance**: visitors get a "Back soon" page with HTTP 503 and a
  `Retry-After` header (counting down to the expected return time, clamped to between
  one minute and 24 hours, one hour when no time is set), so search engines treat it as
  temporary.
- **Coming soon**: visitors get an "Opening soon" page with HTTP 200.

Both pages show the optional message and the expected time (`Expected back` or
`Opening`), are marked `noindex`, are never cached, and carry no navigation or links to
the closed pages. The station's contact details are shown when set. The **Preview**
buttons on that tab show each page without changing anything.

What stays working in every mode: the admin area, the presenter portal and the payment
provider callback (so a payment already in flight is still recorded). Signed-in staff
see the real site with a red reminder banner; every admin page shows a yellow reminder
while the site is closed, and the dashboard's Configuration row flags it.

The expected time is entered and stored in the station's timezone, so
`app.appTimezone` in `.env` must be `Africa/Kampala`. Reaching the time does **not**
open the site by itself: staff switch it to Live when ready.

## The public site

The homepage (`LandingController`, `app/Views/landing.php`) and the booking and gift
pages share one shell, `app/Views/layouts/public.php`. Staff screens use
`layouts/admin`; the presenter portal uses `layouts/main`.

- **Everything on the homepage is live.** Occasions, packages and prices, the show
  schedule, gifts and contact details come from the database and Settings, so it can't
  advertise something the booking pages would refuse. An inactive package or category
  never appears, and a section with nothing to show is left out.
- **Staff control the copy** under Admin > Settings > Public site: the headline, the
  introduction, an announcement banner shown on every public page, and an optional live
  stream address (when set, a *Listen live* button appears; when empty, it's hidden).
- **It is deliberately light.** The homepage loads no Bootstrap, jQuery or Toastr: about
  120 KB in five requests the first time (44 KB of HTML, 77 KB of fonts that are then
  cached). The booking pages still use Bootstrap, so they load it.
- **Fonts are self-hosted** from `public/assets/fonts/` (Bricolage Grotesque for
  headlines, Atkinson Hyperlegible for reading text; both SIL Open Font License, licence
  files alongside). Keep that folder when you deploy. They cover Latin text only.
- **There is no invented content.** No statistics, testimonials or claims about the
  station. The one illustrative item, the greeting under the radio, is labelled
  "Example greeting".

## The booking form: message editor and voice notes

`app/Views/booking/create.php`, with **one** script (`public/assets/js/booking.js`) and **one** stylesheet
(`public/assets/css/booking.css`). No inline JavaScript, no libraries. Every limit the script uses is read from
`data-*` attributes on the form, which the page fills from Admin > Settings, so changing a setting changes the form.

- **Message editor.** Live character count and an "about N seconds on air" estimate, a warning near and over the
  limit, Clear and Undo, drafts saved on the visitor's own device (offered back after a refresh, never forced), and
  **"Start from an example"** buttons. The examples are managed under **Admin > Message templates**, per occasion,
  in any language (English, Luganda, ...), and may use `{recipient}` and `{sender}`, which are filled from what the
  visitor has typed, or left as `[their name]` / `[your name]` markers for them to complete. A message that still
  contains a marker is refused by the server, so one can never be read on air.
- **Voice notes.** Record in the browser (with a timer, a level meter, an automatic stop at the limit, and a warning
  if the microphone seems muted) or choose a file. Length, size and type are checked *before* the note is attached,
  with plain-language messages. A recording is named for what it really is (Safari records MP4; Chrome and Firefox
  record WebM or Ogg).
- **Server-side checks** (`VoiceNoteUploader`) never trust the browser: file type is taken from the file's contents,
  size and extension are checked, and when **ffprobe** is installed the file is opened to measure its true length and
  to confirm it is audio and not video. The measured length is what is stored. Install it on production
  (`sudo apt install ffmpeg`); without it the browser's reported length is used and a disguised video is not caught.
- **Without JavaScript** the form still works: a plain file picker replaces the buttons, and the browser's own
  required-field checks apply.

Staff controls: Admin > Settings > Bookings & content (message length, voice length and size, reading pace) and
Admin > Message templates. After pulling this change run `php spark migrate` (it adds `message_templates`) and keep
the `public/assets/css` and `public/assets/js` folders when deploying.

## Tests

`composer test` runs about 900 automated tests in under 30 seconds: unit tests (no database), database tests (every
model and service) and feature tests (whole requests through the real routes, filters and views). They need a
database used for nothing else, and ffmpeg for the voice-note tests; `tests/README.md` has the one-time setup, what
each layer covers, and the helpers for writing more. CI runs them, together with the style and static-analysis
checks, on every pull request.

## Working on this project

Three developers, sNOW as lead. Before opening your first PR, read
[`CONTRIBUTING.md`](./CONTRIBUTING.md) — it covers:

- Branching model (`main` / `develop` / `feature/...`)
- Commit message convention
- PR review rules
- Default module ownership (so we don't collide on the same files)
- Local code-style and static-analysis checks

```bash
composer cs-check   # PSR-12 style check
composer cs-fix      # auto-fix style issues
composer analyse     # static analysis
composer test        # run the test suite
```

### Useful custom commands

- `php spark make:module <Name>` — scaffolds a Controller, Model, and
  migration stub for a new feature area
- `php spark db:seed DemoSeeder` — seeds local sample data (the CBS rate
  card, two shows, our own gifts, and sample bookings in every status).
  Safe to run twice; it skips if categories already exist. Dev only.
- `php spark gifts:sync` — pulls every active gift vendor's catalog into the
  gift catalog (schedule it with cron; also available as a button under
  Admin > Gift Vendors)
- `php spark gifts:reconcile` — asks each partner shop (Katale) about purchases still
  waiting to be paid or being delivered. Schedule it every 10 minutes.

## CI

Every PR into `develop` or `main` runs, via GitHub Actions
(`.github/workflows/ci.yml`), and cannot merge if any step fails:

- **Style** — PSR-12 on application code. Rules and scope are in
  `phpcs.xml`: `app/Views` (templates) and `app/Config` (framework-generated)
  are excluded. Lines over 120 characters are reported as warnings only.
- **Static analysis** — PHPStan level 5, configured in `phpstan.neon` with the
  same two exclusions.
- **Tests** — PHPUnit. There are no automated tests for this project's own
  code yet; the end-to-end flows were checked with scripts instead. Turning
  those into PHPUnit feature tests is the obvious next investment.

## License

Proprietary — © Ephraitech Unified Solutions. Not for redistribution.

---
*Ephraitech Unified Solutions — Tech with a legacy of purpose.*
