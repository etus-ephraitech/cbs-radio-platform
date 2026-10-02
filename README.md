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
- `php spark db:seed DemoSeeder` — seeds local sample data
- `php spark tokens:cleanup` — purges expired API tokens

## CI

Every PR into `develop` or `main` runs style check, static analysis, and
the test suite via GitHub Actions (`.github/workflows/ci.yml`). A PR with a
failing check cannot be merged.

## License

Proprietary — © Ephraitech Unified Solutions. Not for redistribution.

---
*Ephraitech Unified Solutions — Tech with a legacy of purpose.*