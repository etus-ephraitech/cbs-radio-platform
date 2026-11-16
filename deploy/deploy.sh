#!/usr/bin/env bash
#
# Deploy (or update) a copy of the CBS Radio platform on a cPanel account: staging now, production later.
#
# Run it from the server, in the cloned repository:
#
#     bash deploy/deploy.sh                 # update the branch that is checked out
#     bash deploy/deploy.sh feature/ui-theme   # switch to and deploy another branch
#
# It checks the server first and stops before touching anything if something is missing. Then it pulls the
# branch (fast-forward only: it never overwrites changes made on the server), installs the Composer packages
# (without the test tools), runs new database migrations and clears the cache. Running it twice is harmless.
#
# Settings, through environment variables:
#   PHP=/opt/cpanel/ea-php83/root/usr/bin/php   the PHP the site runs on, when plain `php` is a different version
#   COMPOSER_BIN="php ~/bin/composer.phar"      how to run Composer, when `composer` isn't on the PATH
#                                               (not COMPOSER: Composer itself reads that as the composer.json name)

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP="${PHP:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"
LOG="$ROOT/writable/logs/deploy.log"

say()  { printf '\n\033[1m==> %s\033[0m\n' "$*"; }
ok()   { printf '    \033[32m✓\033[0m %s\n' "$*"; }
fail() { printf '    \033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }
trap 'printf "\n\033[31mDeploy stopped at line %s. Nothing after that step ran.\033[0m\n" "$LINENO" >&2' ERR

# ----------------------------------------------------------------------------------------------- 1. checks
say "Checking the server"

command -v "$PHP" >/dev/null 2>&1 || fail "PHP not found as '$PHP'. Set PHP=/opt/cpanel/ea-php83/root/usr/bin/php (or your version)."
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
    || fail "PHP $("$PHP" -r 'echo PHP_VERSION;') is too old: 8.2 or newer is needed. Pick it in cPanel > MultiPHP Manager, and set PHP= to match."
ok "PHP $("$PHP" -r 'echo PHP_VERSION;')"

for ext in intl mbstring mysqli json curl fileinfo; do
    "$PHP" -r "exit(extension_loaded('$ext') ? 0 : 1);" || fail "PHP extension '$ext' is missing. Turn it on in cPanel > Select PHP Version > Extensions."
done
ok "PHP extensions: intl mbstring mysqli json curl fileinfo"

[ -f .env ] || fail ".env is missing. Copy deploy/staging.env.example to .env and fill it in."
grep -Eq '^\s*CI_ENVIRONMENT\s*=\s*development' .env && fail ".env says CI_ENVIRONMENT = development: that shows debug pages to the public. Use production."
grep -Eq '^\s*app\.baseURL\s*=' .env || fail ".env has no app.baseURL."
ok ".env present"

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    git status --short --untracked-files=no
    fail "Files tracked by git were changed on the server (listed above). Commit them elsewhere or run 'git checkout -- .', then deploy again."
fi
ok "No local changes to tracked files"

# ----------------------------------------------------------------------------------------------- 2. code
say "Getting branch $BRANCH"
BEFORE="$(git rev-parse --short HEAD)"
git fetch --prune origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH" || fail "The branch can't be fast-forwarded (its history was rewritten). Check with: git log --oneline HEAD..origin/$BRANCH"
AFTER="$(git rev-parse --short HEAD)"
ok "At $AFTER ($(git log -1 --format=%s))"

say "Installing Composer packages"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --no-progress
ok "vendor/ is up to date"

# ----------------------------------------------------------------------------------------------- 3. files
say "Checking folders the site writes to"
for dir in writable writable/cache writable/logs writable/session writable/uploads writable/debugbar; do
    mkdir -p "$dir"
done
chmod -R u+rwX writable
ok "writable/ is writable"

# ----------------------------------------------------------------------------------------------- 4. database
say "Running database migrations"
"$PHP" spark migrate --all
ok "Database schema is current"

say "Clearing the cache"
"$PHP" spark cache:clear
ok "Cache cleared"

# ----------------------------------------------------------------------------------------------- done
mkdir -p "$(dirname "$LOG")"
printf '%s  %s  %s -> %s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$BRANCH" "$BEFORE" "$AFTER" "$(whoami)" >> "$LOG"
trap - ERR

say "Deployed $BRANCH at $AFTER"
if [ "$BEFORE" != "$AFTER" ]; then
    echo "    Changes since $BEFORE:"
    git log --oneline "$BEFORE..$AFTER" | sed 's/^/      /'
fi
echo
echo "    Open the site and run through deploy/STAGING-TEST-CHECKLIST.md."
