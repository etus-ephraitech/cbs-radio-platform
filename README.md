# Tests

`composer test` runs everything (about 900 tests, under 30 seconds). Run one layer while you work:

| Command | Layer | Needs a database | What it covers |
|---|---|---|---|
| `composer test:unit` | `tests/unit` | No | Helpers, the message sanitiser, voice-note validation against **real audio**, the settings registry and every validation rule, `FileManager` (including path traversal) |
| `composer test:database` | `tests/database` | Yes | Every model and every service: bookings, scheduling, moderation, payments, gift orders, vendor sync, notifications, the settings engine |
| `composer test:feature` | `tests/feature` | Yes | Whole requests through routes, filters, controllers and views: the homepage, the booking and gift flows, the payment callback, the presenter, every admin screen |

## One-time setup

1. Create a database that is used for **nothing else**. The suite empties this project's tables before every test:
   `CREATE DATABASE cbs_radio_platform_test CHARACTER SET utf8mb4;`
2. Put its details in `.env` (the block is in `.env.example`): `database.tests.hostname`, `.database`,
   `.username`, `.password`. The driver (MySQLi) and the empty table prefix are fixed in `phpunit.xml.dist`.
3. Install **ffmpeg** (`sudo apt install ffmpeg`). It provides `ffprobe`, which the voice-note tests use to
   measure real audio. Without it those tests are *skipped*, and PHPUnit says so.

The schema is built by this project's own migrations the first time the suite runs, so there is nothing to import.
The starter kit's `users` table must already exist (a normal `php spark migrate` creates it).

## Why not SQLite

CodeIgniter's default `tests` database group is SQLite in memory with a `db_` table prefix. Our migrations use
raw `ALTER TABLE ... ADD CONSTRAINT` that names tables without a prefix, so the suite runs on MySQL.
`phpunit.xml.dist` overrides the driver and the prefix for you.

## How the pieces fit

- `tests/_support/TestCase/` - the base classes. `UnitTestCase` keeps the database out of reach.
  `AppTestCase` builds the schema once, empties this project's tables before each test, and gives you
  `setSetting()` (an admin override, exactly as the screen saves it) and `reloadConfig()`.
  `AppFeatureTestCase` adds request helpers: `flash('toastr_error')`, `silenceNotifications()`.
- `tests/_support/Concerns/BuildsCatalog.php` - `makeCategory()`, `makeTier()`, `makeShow()`, `makeGift()`,
  `makeVendor()`, `makeBooking()`, `makeGiftOrder()`, `makePayment()`. Each takes overrides and fails loudly if the
  row is invalid, rather than quietly building nothing.
- `tests/_support/Libraries/` - the fakes. `FakeHttpGateway` stands in for every outside HTTP call (the SMS gateway,
  gift vendors), `RecordingNotifier` records messages instead of sending them, `FakeVendorClient`,
  `FakeMobileMoneyPayments`, and `AudioFixtures` (real audio, see below). Nothing in the suite can reach the internet.
- `tests/_support/Filters/ActAsStaff.php` - stands in for the starter kit's `auth` filter in feature tests, so the
  suite checks what staff screens *do* and does not depend on how the login works. (That the real filter blocks
  the public is the starter kit's own concern and is not tested here.) CSRF is switched off for feature tests too.
- `tests/_support/overrides/uploaded_file_functions.php` - **test only.** PHP's `is_uploaded_file()` is false for
  anything that did not arrive through a real HTTP upload, so a test could never get past "didn't upload
  properly". A function of the same name in the `CodeIgniter\HTTP\Files` namespace takes precedence over the global
  one inside that namespace, which makes a fixture count as an upload, for the tests only.

## Audio fixtures

`tests/_support/Audio/` holds real audio made the way real devices make it (Chrome's WebM, Safari's MP4, WhatsApp's
Opus, a phone's M4A, ...), plus bad files (a video with a sound track, a text file, random bytes, a truncated MP3).
Regenerate them with `scripts/make-test-audio.sh` (needs ffmpeg). The container, not the file name, decides what PHP's
file-type detection reports, and audio-only WebM and MP4 are reported as **video** types, which is exactly what a
synthetic test file would hide.

## Writing a test

- Pick the lowest layer that can prove the thing. A rule about a value belongs in `unit`; a query or a service in
  `database`; something a visitor or an administrator does in a browser in `feature`.
- Test the contract, not the implementation, and say *why* in a comment when the reason is not obvious
  ("the regression this guards: ...").
- A test that finds a bug is a good test. Fix the code, keep the test.
- Never name a helper `call`, `callback`, `get`, `post` or `session` in a feature test: they collide with
  `FeatureTestTrait` or PHPUnit's final methods and the run dies with a fatal error before any test reports.
