# =========================================================
#  Tixello - publica un bundle OTA (self-hosted, fara Capgo Cloud)
#  ATENTIE: fisier ASCII-only (PS 5.1 citeste .ps1 fara BOM ca ANSI).
#
#  Folosire:  .\publish-bundle.ps1 -Version 0.1.1
#             .\publish-bundle.ps1 -Version 0.1.1 -SkipWebBuild
#             .\publish-bundle.ps1 -Version 0.1.1 -MinVersionBuild 0.2.0
#
#  Ce face:
#    1. build web (tsc + vite)
#    2. impacheteaza dist\ intr-un zip cu index.html la radacina
#    3. calculeaza sha256
#    4. scrie public\tics-app\manifest.json (citit de updates.php)
#
#  Dupa asta: commit + push, apoi `git pull` pe core.tixello.com.
#  Telefoanele iau update-ul la urmatoarea deschidere a aplicatiei.
#  NU e nevoie de APK nou cat timp nu adaugi plugin-uri native.
# =========================================================
param(
  [Parameter(Mandatory = $true)][string]$Version,
  [switch]$SkipWebBuild,
  # Setezi asta cand bundle-ul cere un plugin nativ nou: telefoanele cu APK
  # mai vechi nu vor primi update-ul (altfel ar crapa la runtime).
  [string]$MinVersionBuild = '',
  # Checksum-ul e OPTIONAL in protocolul plugin-ului si e scos implicit:
  # daca versiunea de plugin il calculeaza altfel decat sha256 pe zip,
  # update-ul esueaza IN TACERE, ceea ce e greu de diagnosticat fara device.
  # Il repunem doar dupa ce confirmam pe telefon ca formatul e acceptat.
  [switch]$WithChecksum
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot

if ($Version -notmatch '^\d+\.\d+\.\d+$') {
  throw "Versiunea trebuie sa fie semver x.y.z (primit: '$Version')."
}

function Invoke-Native {
  param([string]$Exe, [string[]]$Arguments, [string]$FailMessage)
  $prev = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try { & $Exe @Arguments; $code = $LASTEXITCODE } finally { $ErrorActionPreference = $prev }
  if ($code -ne 0) { throw "$FailMessage (exit $code)" }
}

# ---- 1. build web ----
Push-Location $root
try {
  if (-not $SkipWebBuild) {
    Write-Host '[1/4] Build web (tsc + Vite)...' -ForegroundColor Cyan
    Invoke-Native 'npx' @('tsc', '--noEmit') 'Typecheck esuat.'
    Invoke-Native 'npx' @('vite', 'build') 'Build Vite esuat.'
  }
}
finally { Pop-Location }

$dist = Join-Path $root 'dist'
if (-not (Test-Path (Join-Path $dist 'index.html'))) {
  throw "Nu gasesc dist\index.html - ruleaza fara -SkipWebBuild."
}

# ---- 2. zip ----
# NU folosim Compress-Archive: in PowerShell 5.1 scrie separatorii de cale cu
# backslash in intrarile arhivei, iar dezarhivatorul de pe Android nu recreeaza
# subfolderele -> bundle-ul ajunge fara assets si aplicatia porneste alba.
# Construim arhiva intrare-cu-intrare, cu '/' explicit.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$publicDir = Join-Path $root '..\..\public\tics-app'
if (-not (Test-Path $publicDir)) { throw "Nu gasesc $publicDir (esti in afara repo-ului epas?)" }
$publicDir = (Resolve-Path $publicDir).Path

$bundleDir = Join-Path $publicDir 'bundles'
New-Item -ItemType Directory -Force $bundleDir | Out-Null
$zipPath = Join-Path $bundleDir "tixello-$Version.zip"
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

Write-Host '[2/4] Impachetez dist\ ...' -ForegroundColor Cyan
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
  $distFull = (Resolve-Path $dist).Path.TrimEnd('\')

  # Intrari de DIRECTOR, explicit. Fara ele, unele implementari de unzip (inclusiv
  # cele bazate pe ZipInputStream de pe Android) nu creeaza subfolderele si
  # fisierele din assets/ nu se scriu -> bundle incomplet -> ecran alb -> rollback,
  # totul fara niciun mesaj de eroare.
  $dirs = Get-ChildItem $distFull -Recurse -Directory
  foreach ($d in $dirs) {
    $rel = $d.FullName.Substring($distFull.Length + 1).Replace('\', '/') + '/'
    [void]$zip.CreateEntry($rel)
  }

  $files = Get-ChildItem $distFull -Recurse -File
  foreach ($f in $files) {
    $rel = $f.FullName.Substring($distFull.Length + 1).Replace('\', '/')
    $entry = $zip.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
    $in = $f.OpenRead()
    try {
      $out = $entry.Open()
      try { $in.CopyTo($out) } finally { $out.Dispose() }
    }
    finally { $in.Dispose() }
  }
  Write-Host ("  {0} directoare, {1} fisiere" -f $dirs.Count, $files.Count)
}
finally { $zip.Dispose() }

# ---- 3. checksum ----
Write-Host '[3/4] Calculez sha256...' -ForegroundColor Cyan
$sha = (Get-FileHash $zipPath -Algorithm SHA256).Hash.ToLower()
$sizeKb = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)

# ---- 4. manifest ----
Write-Host '[4/4] Scriu manifest.json...' -ForegroundColor Cyan
$manifest = [ordered]@{
  version   = $Version
  url       = "https://core.tixello.com/tics-app/bundles/tixello-$Version.zip"
  size      = (Get-Item $zipPath).Length
  published = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
}
if ($WithChecksum) { $manifest['checksum'] = $sha }
if ($MinVersionBuild -ne '') { $manifest['min_version_build'] = $MinVersionBuild }

$json = ($manifest | ConvertTo-Json -Depth 4)
[System.IO.File]::WriteAllText((Join-Path $publicDir 'manifest.json'), $json, [System.Text.UTF8Encoding]::new($false))

Write-Host ''
Write-Host 'BUNDLE PUBLICAT' -ForegroundColor Green
Write-Host ("  versiune : {0}" -f $Version)
Write-Host ("  zip      : {0}  ({1} KB)" -f $zipPath, $sizeKb)
Write-Host ("  sha256   : {0}" -f $sha)
Write-Host ''
Write-Host 'Urmatorul pas:' -ForegroundColor Yellow
Write-Host '  cd ..\..'
Write-Host ("  git add public/tics-app && git commit -m ""tics-app: bundle OTA {0}"" && git push origin core" -f $Version)
Write-Host '  apoi `git pull` pe core.tixello.com'
