#!/usr/bin/env bash
#
# CBS Radio Platform — Developer Environment Setup (Linux/macOS)
# Run this once after cloning the repo.
#
# Usage:
#   chmod +x scripts/dev-setup.sh
#   ./scripts/dev-setup.sh

set -e

echo "== CBS Radio Platform — Developer Setup =="

command -v php >/dev/null 2>&1 || { echo "PHP not found. Install PHP 8.1+ first."; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "Composer not found. Install Composer first."; exit 1; }
command -v git >/dev/null 2>&1 || { echo "Git not found. Install Git first."; exit 1; }

echo "Installing PHP dependencies..."
composer install

if [ ! -f .env ]; then
    cp .env.example .env
    echo ".env created from .env.example — fill in your local DB credentials and provider keys."
else
    echo ".env already exists, leaving it as-is."
fi

echo "Enabling shared git hooks (pre-commit style check)..."
git config core.hooksPath scripts/git-hooks
chmod +x scripts/git-hooks/pre-commit

echo "Fetching latest branches..."
git fetch origin
git checkout develop
git pull origin develop

echo ""
echo "Which module are you starting on?"
echo "  1) booking        -> feature/booking-flow"
echo "  2) gifts-payments -> feature/digital-gifts-payments"
echo "  3) presenter-admin-> feature/presenter-admin-portal"
echo "  4) auth           -> feature/auth-starter-kit-integration"
echo "  5) skip           -> stay on develop, I'll create my own branch"
read -p "Choice [1-5]: " choice

case $choice in
    1) BRANCH="feature/booking-flow" ;;
    2) BRANCH="feature/digital-gifts-payments" ;;
    3) BRANCH="feature/presenter-admin-portal" ;;
    4) BRANCH="feature/auth-starter-kit-integration" ;;
    *) BRANCH="" ;;
esac

if [ -n "$BRANCH" ]; then
    if git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
        git checkout -B "$BRANCH" "origin/$BRANCH"
    else
        git checkout -b "$BRANCH" develop
        git push -u origin "$BRANCH"
    fi
    echo "Checked out: $BRANCH"
else
    echo "Staying on develop. Create your branch with:"
    echo "  git checkout -b feature/<short-description>"
fi

echo ""
echo "Setup complete. Next:"
echo "  1. Run database migrations:  php spark migrate"
echo "  2. Seed local demo data:     php spark db:seed DemoSeeder"
echo "  3. Start the dev server:     php spark serve"
echo ""
echo "See CONTRIBUTING.md for the full git workflow and review rules."
