# CBS Radio Platform — Developer Environment Setup (Windows PowerShell)
# Run this once after cloning the repo.
#
# Usage:
#   .\scripts\dev-setup.ps1
#
# If script execution is blocked, run once per machine (as the current user):
#   Set-ExecutionPolicy -Scope CurrentUser RemoteSigned

$ErrorActionPreference = "Stop"

Write-Host "== CBS Radio Platform — Developer Setup ==" -ForegroundColor Cyan

function Test-CommandExists {
    param([string]$Name)
    return [bool](Get-Command $Name -ErrorAction SilentlyContinue)
}

if (-not (Test-CommandExists "php"))      { Write-Error "PHP not found. Install PHP 8.1+ first."; exit 1 }
if (-not (Test-CommandExists "composer")) { Write-Error "Composer not found. Install Composer first."; exit 1 }
if (-not (Test-CommandExists "git"))      { Write-Error "Git not found. Install Git first."; exit 1 }

Write-Host "Installing PHP dependencies..." -ForegroundColor Yellow
composer install

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host ".env created from .env.example — fill in your local DB credentials and provider keys." -ForegroundColor Yellow
} else {
    Write-Host ".env already exists, leaving it as-is."
}

Write-Host "Enabling shared git hooks (pre-commit style check)..." -ForegroundColor Yellow
git config core.hooksPath scripts/git-hooks

Write-Host "Fetching latest branches..." -ForegroundColor Yellow
git fetch origin
git checkout develop
git pull origin develop

Write-Host ""
Write-Host "Which module are you starting on?"
Write-Host "  1) booking         -> feature/booking-flow"
Write-Host "  2) gifts-payments  -> feature/digital-gifts-payments"
Write-Host "  3) presenter-admin -> feature/presenter-admin-portal"
Write-Host "  4) auth            -> feature/auth-starter-kit-integration"
Write-Host "  5) skip            -> stay on develop, I'll create my own branch"
$choice = Read-Host "Choice [1-5]"

switch ($choice) {
    "1" { $branch = "feature/booking-flow" }
    "2" { $branch = "feature/digital-gifts-payments" }
    "3" { $branch = "feature/presenter-admin-portal" }
    "4" { $branch = "feature/auth-starter-kit-integration" }
    default { $branch = $null }
}

if ($branch) {
    $remoteRef = git show-ref --verify --quiet "refs/remotes/origin/$branch"
    if ($LASTEXITCODE -eq 0) {
        git checkout -B $branch "origin/$branch"
    } else {
        git checkout -b $branch develop
        git push -u origin $branch
    }
    Write-Host "Checked out: $branch" -ForegroundColor Green
} else {
    Write-Host "Staying on develop. Create your branch with:"
    Write-Host "  git checkout -b feature/<short-description>"
}

Write-Host ""
Write-Host "Setup complete. Next:" -ForegroundColor Cyan
Write-Host "  1. Run database migrations:  php spark migrate"
Write-Host "  2. Seed local demo data:     php spark db:seed DemoSeeder"
Write-Host "  3. Start the dev server:     php spark serve"
Write-Host ""
Write-Host "See CONTRIBUTING.md for the full git workflow and review rules."
