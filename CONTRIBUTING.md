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
composer cs-check   # PSR-12 style check (PHP_CodeSniffer)
composer cs-fix      # auto-fixes what it can
composer analyse     # static analysis (PHPStan)
composer test        # PHPUnit
```

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
- `php spark db:seed DemoSeeder` — seeds local dev data (sample categories,
  shows/slots, a few bookings) so nobody is testing against an empty DB.
- `php spark tokens:cleanup` — purges expired access tokens (from the API
  starter kit), safe to run on a cron.

Propose new ones in the team channel before building — if two of us need
the same helper, it should exist once, not twice.
