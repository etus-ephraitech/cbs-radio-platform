#!/usr/bin/env bash
#
# CBS Radio Platform — Repository Bootstrap (Linux/macOS)
# Run ONCE, by the lead, after the GitHub repo has been created (empty).
#
# Usage:
#   chmod +x scripts/bootstrap-repo.sh
#   ./scripts/bootstrap-repo.sh git@github.com:ephraitech/cbs-radio-platform.git

set -e

REMOTE_URL="$1"

if [ -z "$REMOTE_URL" ]; then
    echo "Usage: ./scripts/bootstrap-repo.sh <git-remote-url>"
    exit 1
fi

echo "== CBS Radio Platform — Repository Bootstrap =="

if [ ! -d .git ]; then
    git init
    git branch -M main
fi

if ! git remote | grep -q '^origin$'; then
    git remote add origin "$REMOTE_URL"
fi

# Ensure main has at least one commit before branching from it
if ! git rev-parse --verify main >/dev/null 2>&1; then
    git add -A
    git commit -m "chore: initial project scaffold"
fi

echo "Pushing main..."
git push -u origin main

echo "Creating develop from main..."
git checkout -B develop main
git push -u origin develop

# Starting feature branches, one per module owner (see README for reasons)
BRANCHES=(
    "feature/auth-starter-kit-integration"
    "feature/booking-flow"
    "feature/digital-gifts-payments"
    "feature/presenter-admin-portal"
)

for branch in "${BRANCHES[@]}"; do
    echo "Creating ${branch}..."
    git checkout develop
    git checkout -B "$branch"
    git push -u origin "$branch"
done

git checkout develop

echo ""
echo "Done. Branches on origin:"
echo "  main, develop, ${BRANCHES[*]}"
echo ""
echo "Next steps (in GitHub repo settings):"
echo "  1. Settings > Branches > add protection rules for 'main' and 'develop':"
echo "     - Require a pull request before merging"
echo "     - Require status checks to pass (CI) before merging"
echo "     - Restrict who can push directly (lead only, or nobody)"
echo "  2. Share clone URL with the team and have each dev run scripts/dev-setup.sh"
