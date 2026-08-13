# =========================================================
#  Tics - build APK debug (Windows / PowerShell 5.1)
#  ATENTIE: fisier ASCII-only. PowerShell 5.1 citeste .ps1 fara BOM ca ANSI,
#  deci diacriticele si em-dash-urile strica parsarea. Nu adauga caractere
#  non-ASCII aici (documentatia cu diacritice sta in README-BUILD.md).
#
#  Folosire:  .\build-apk.ps1              build + copiere in .\out
#             .\build-apk.ps1 -Install     si instaleaza pe telefonul conectat
#             .\build-apk.ps1 -Clean       gradlew clean inainte
#             .\build-apk.ps1 -SkipWebBuild  doar Gradle (web deja construit)
#
#  De ce exista scriptul (particularitati ale acestei masini) - detalii in README-BUILD.md:
#   1. GRADLE_USER_HOME din mediu arata spre D:\01.CACHE\GradleCache, iar
#      discul D: nu exista -> orice build Gradle crapa la lock file.
#   2. Avast Web/Mail Shield intercepteaza TLS. Java nu are CA-ul lui root
#      in cacerts -> PKIX failure la maven/google. Folosim tixello-cacerts.jks.
#   3. Wrapper-ul Gradle are read-timeout de 10s si nu apuca sa descarce
#      distributia de 193MB -> o descarcam noi cu Invoke-WebRequest.
# =========================================================
param(
  [switch]$Install,
  [switch]$Clean,
  [switch]$SkipWebBuild,
  [switch]$Publish,
  [string]$Version = '0.1.0'
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$androidDir = Join-Path $root 'android'

# Executabilele native (npx, gradlew) scriu warning-uri pe stderr. In PS 5.1
# asta devine ErrorRecord si, cu ErrorActionPreference='Stop', opreste scriptul
# desi exit code-ul e 0. Rulam nativele cu 'Continue' si verificam $LASTEXITCODE.
function Invoke-Native {
  param([string]$Exe, [string[]]$Arguments, [string]$FailMessage)
  $prev = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try {
    & $Exe @Arguments
    $code = $LASTEXITCODE
  }
  finally { $ErrorActionPreference = $prev }
  if ($code -ne 0) { throw "$FailMessage (exit $code)" }
}

# ---- JDK 17 ----
if (-not $env:JAVA_HOME -or -not (Test-Path $env:JAVA_HOME)) {
  $jdk = Get-ChildItem 'C:\Program Files\Eclipse Adoptium' -Directory -ErrorAction SilentlyContinue |
         Where-Object { $_.Name -like 'jdk-17*' } | Select-Object -First 1
  if (-not $jdk) { throw 'JDK 17 negasit. Instaleaza Temurin 17 sau seteaza JAVA_HOME.' }
  $env:JAVA_HOME = $jdk.FullName
}
Write-Host "JAVA_HOME        = $env:JAVA_HOME" -ForegroundColor DarkGray

# ---- Gradle home (ocoleste D:\ inexistent) ----
$gradleHome = Join-Path $env:USERPROFILE '.gradle'
if (-not (Test-Path $gradleHome)) { New-Item -ItemType Directory -Force $gradleHome | Out-Null }
$env:GRADLE_USER_HOME = $gradleHome
Write-Host "GRADLE_USER_HOME = $env:GRADLE_USER_HOME" -ForegroundColor DarkGray

# ---- Truststore (ocoleste interceptarea TLS a Avast) ----
$truststore = Join-Path $androidDir 'tixello-cacerts.jks'
if (Test-Path $truststore) {
  $env:GRADLE_OPTS = "-Djavax.net.ssl.trustStore=$truststore -Djavax.net.ssl.trustStorePassword=changeit"
  Write-Host "TRUSTSTORE       = $truststore" -ForegroundColor DarkGray
}
else {
  Write-Host "TRUSTSTORE       = lipseste (vezi README-BUILD.md daca apar erori PKIX)" -ForegroundColor Yellow
}

# ---- Distributia Gradle (wrapper-ul are timeout prea mic) ----
$distDir = Join-Path $gradleHome 'wrapper\dists\gradle-8.2.1-all\d8pvvlun5bx6sdtwqhf8y9z4b'
$distZip = Join-Path $distDir 'gradle-8.2.1-all.zip'
if (-not (Test-Path (Join-Path $distDir 'gradle-8.2.1')) -and -not (Test-Path $distZip)) {
  Write-Host 'Descarc distributia Gradle 8.2.1 (~193 MB)...' -ForegroundColor Cyan
  New-Item -ItemType Directory -Force $distDir | Out-Null
  Remove-Item (Join-Path $distDir 'gradle-8.2.1-all.zip.part') -ErrorAction SilentlyContinue
  $ProgressPreference = 'SilentlyContinue'
  Invoke-WebRequest -Uri 'https://services.gradle.org/distributions/gradle-8.2.1-all.zip' -OutFile $distZip -UseBasicParsing -TimeoutSec 1800
}

# ---- 1/3 + 2/3: web build + capacitor sync ----
Push-Location $root
try {
  if (-not $SkipWebBuild) {
    Write-Host ''
    Write-Host '[1/3] Build web (tsc + Vite)...' -ForegroundColor Cyan
    Invoke-Native 'npx' @('tsc', '--noEmit') 'Typecheck esuat.'
    Invoke-Native 'npx' @('vite', 'build') 'Build Vite esuat.'
  }
  Write-Host ''
  Write-Host '[2/3] Capacitor sync android...' -ForegroundColor Cyan
  Invoke-Native 'npx' @('cap', 'sync', 'android') 'cap sync esuat.'
}
finally { Pop-Location }

# ---- 3/3: Gradle ----
Write-Host ''
Write-Host '[3/3] Gradle assembleDebug...' -ForegroundColor Cyan
Push-Location $androidDir
try {
  if ($Clean) { Invoke-Native '.\gradlew.bat' @('clean', '--no-daemon') 'Gradle clean esuat.' }
  Invoke-Native '.\gradlew.bat' @('assembleDebug', '--no-daemon') 'Build Gradle esuat.'
}
finally { Pop-Location }

# ---- rezultat ----
$apk = Join-Path $androidDir 'app\build\outputs\apk\debug\app-debug.apk'
if (-not (Test-Path $apk)) { throw "APK negasit la $apk" }

$outDir = Join-Path $root 'out'
New-Item -ItemType Directory -Force $outDir | Out-Null
$copy = Join-Path $outDir 'tics-debug.apk'
Copy-Item $apk $copy -Force

$size = [math]::Round((Get-Item $apk).Length / 1MB, 2)
Write-Host ''
Write-Host 'BUILD OK' -ForegroundColor Green
Write-Host ("  {0}  ({1} MB)" -f $apk, $size)
Write-Host ("  copiat si la: {0}" -f $copy)

# ---- publicare la core.tixello.com/tics-app ----
#
# Scrie si `apk.json`, pe care pagina de descarcare il citeste la incarcare.
# Inainte, versiunea, marimea si hash-ul erau scrise DE MANA in index.html, iar
# scriptul doar reamintea sa fie actualizate - ceea ce s-a si uitat: pagina a
# ramas la 0.17.0 dupa ce APK-ul trecuse la 0.18.0. Un fisier scris de build nu
# poate ramane in urma de build.
if ($Publish) {
  $publicDir = Resolve-Path (Join-Path $root '..\..\public\tics-app') -ErrorAction SilentlyContinue
  if (-not $publicDir) { throw "Nu gasesc epas\public\tics-app (esti in afara repo-ului epas?)" }

  $fileName = "tics-debug-$Version.apk"
  $target = Join-Path $publicDir $fileName

  # APK-urile vechi se sterg: doua fisiere diferite in acelasi folder inseamna,
  # inevitabil, ca cineva il instaleaza pe cel gresit.
  Get-ChildItem -Path $publicDir -Filter '*-debug-*.apk' -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne $fileName } |
    Remove-Item -Force

  Copy-Item $apk $target -Force

  $sha = (Get-FileHash $target -Algorithm SHA256).Hash.ToLower()
  $sizeMb = [math]::Round((Get-Item $target).Length / 1MB, 2)

  $meta = [ordered]@{
    version   = $Version
    file      = $fileName
    size_mb   = $sizeMb
    sha256    = $sha
    min_sdk   = 'Android 6.0+'
    published = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
  }

  $metaPath = Join-Path $publicDir 'apk.json'
  $json = $meta | ConvertTo-Json
  [System.IO.File]::WriteAllText($metaPath, $json, [System.Text.UTF8Encoding]::new($false))

  Write-Host ''
  Write-Host 'PUBLICAT' -ForegroundColor Green
  Write-Host ("  {0}  ({1} MB)" -f $target, $sizeMb)
  Write-Host ("  sha256: {0}" -f $sha)
  Write-Host ("  {0}" -f $metaPath)
  Write-Host '  Pagina de descarcare se actualizeaza singura din apk.json.'
  Write-Host '  Mai ramane: commit + push, apoi `git pull` pe server.'
}

if ($Install) {
  $adb = Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
  if (-not (Test-Path $adb)) { throw "adb negasit la $adb" }
  Write-Host ''
  Write-Host 'Instalez pe telefon...' -ForegroundColor Cyan
  Invoke-Native $adb @('install', '-r', $apk) 'Instalare esuata (telefon conectat? depanare USB activa?)'
}
else {
  Write-Host ''
  Write-Host ("Instalare:  adb install -r `"{0}`"" -f $apk) -ForegroundColor DarkGray
}
