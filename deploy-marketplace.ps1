<#
.SYNOPSIS
    Push marketplace files to GitHub marketplace branch.

.EXAMPLE
    .\deploy-marketplace.ps1
    .\deploy-marketplace.ps1 -Message "Add search feature"
#>

param([string]$Message = "Deploy $(Get-Date -Format 'yyyy-MM-dd HH:mm')")

$ErrorActionPreference = "Stop"
$SourceDir = "epas\resources\marketplaces\ambilet"
$TargetBranch = "marketplace"
$TempDir = Join-Path $env:TEMP "mp-deploy-$(Get-Random)"

Write-Host "`n=== Ambilet Deploy ===" -ForegroundColor Cyan

# Verify source
if (-not (Test-Path $SourceDir)) {
    Write-Host "ERROR: $SourceDir not found" -ForegroundColor Red
    exit 1
}

# Clone marketplace branch
Write-Host "[1/4] Cloning $TargetBranch branch..." -ForegroundColor Yellow
$null = New-Item -ItemType Directory -Path $TempDir -Force

$cloneResult = git clone --branch $TargetBranch --single-branch --depth 1 "https://github.com/andreiandoo/epas.git" $TempDir 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "      Creating new branch..." -ForegroundColor Gray
    git clone --depth 1 "https://github.com/andreiandoo/epas.git" $TempDir 2>&1 | Out-Null
    Push-Location $TempDir
    git checkout --orphan $TargetBranch
    git rm -rf . 2>&1 | Out-Null
    Pop-Location
} else {
    Push-Location $TempDir
    Get-ChildItem -Force | Where-Object { $_.Name -ne ".git" -and $_.Name -ne "data" } | Remove-Item -Recurse -Force
    Pop-Location
}

# Copy files
Write-Host "[2/4] Copying files..." -ForegroundColor Yellow
Copy-Item -Path "$SourceDir\*" -Destination $TempDir -Recurse -Force
$fileCount = (Get-ChildItem -Path $SourceDir -Recurse -File).Count
Write-Host "      $fileCount files" -ForegroundColor Gray

# Commit
Write-Host "[3/4] Committing..." -ForegroundColor Yellow
Push-Location $TempDir
git add -A
$hasChanges = git diff --cached --quiet; $hasChanges = $LASTEXITCODE -ne 0

if (-not $hasChanges) {
    Write-Host "      No changes to deploy" -ForegroundColor Gray
    Pop-Location
    Remove-Item -Path $TempDir -Recurse -Force
    exit 0
}

git commit -m $Message | Out-Null

# Push
Write-Host "[4/4] Pushing to GitHub..." -ForegroundColor Yellow
git push -u origin $TargetBranch --force 2>&1 | ForEach-Object { Write-Host "      $_" -ForegroundColor Gray }
Pop-Location

# Cleanup
Remove-Item -Path $TempDir -Recurse -Force -ErrorAction SilentlyContinue

Write-Host "`n=== Deploy complete! ===" -ForegroundColor Green
Write-Host "Branch: $TargetBranch"
Write-Host "Message: $Message"
Write-Host "Server will auto-update via webhook`n"
