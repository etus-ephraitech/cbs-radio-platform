# CBS Radio Platform — Repository Bootstrap (Windows PowerShell)
# Run ONCE, by the lead, after the GitHub repo has been created (empty).
#
# Usage:
#   .\scripts\bootstrap-repo.ps1 -RemoteUrl "git@github.com:ephraitech/cbs-radio-platform.git"
#
# If script execution is blocked, run once per machine (as the current user):
#   Set-ExecutionPolicy -Scope CurrentUser RemoteSigned

param(
    [Parameter(Mandatory = $true)]
    [string]$RemoteUrl
)

$ErrorActionPreference = "Stop"

Write-Host "== CBS Radio Platform — Repository Bootstrap ==" -ForegroundColor Cyan

if (-not (Test-Path ".git")) {
    git init
    git branch -M main
}

$existingRemote = git remote
if ($existingRemote -notcontains "origin") {
    git remote add origin $RemoteUrl
}

# Ensure main has at least one commit before branching from it
git rev-parse --verify main 2>$null
if ($LASTEXITCODE -ne 0) {
    git add -A
    git commit -m "chore: initial project scaffold"
}

Write-Host "Pushing main..." -ForegroundColor Yellow
git push -u origin main

Write-Host "Creating develop from main..." -ForegroundColor Yellow
git checkout -B develop main
git push -u origin develop

# Starting feature branches, one per module owner (see README for reasons)
$branches = @(
    "feature/auth-starter-kit-integration",
    "feature/booking-flow",
    "feature/digital-gifts-payments",
    "feature/presenter-admin-portal"
)

foreach ($branch in $branches) {
    Write-Host "Creating $branch..." -ForegroundColor Yellow
    git checkout develop
    git checkout -B $branch
    git push -u origin $branch
}

git checkout develop

Write-Host ""
Write-Host "Done. Branches on origin:" -ForegroundColor Green
Write-Host "  main, develop, $($branches -join ', ')"
Write-Host ""
Write-Host "Next steps (in GitHub repo settings):" -ForegroundColor Cyan
Write-Host "  1. Settings > Branches > add protection rules for 'main' and 'develop':"
Write-Host "     - Require a pull request before merging"
Write-Host "     - Require status checks to pass (CI) before merging"
Write-Host "     - Restrict who can push directly (lead only, or nobody)"
Write-Host "  2. Share clone URL with the team and have each dev run scripts\dev-setup.ps1"
