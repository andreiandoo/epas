# =========================================================
#  Tixello Widget - build APK debug (Windows / PowerShell 5.1)
#  ATENTIE: fisier ASCII-only. PowerShell 5.1 citeste .ps1 fara BOM ca ANSI,
#  deci diacriticele si em-dash-urile strica parsarea.
#
#  Folosire:  .\build-apk.ps1              build + copiere in .\out
#             .\build-apk.ps1 -Install     si instaleaza pe telefonul conectat
#             .\build-apk.ps1 -Clean       gradlew clean inainte
#             .\build-apk.ps1 -SkipTests   sare peste testDebugUnitTest
#
#  Diferente fata de tics-app/mobile/build-apk.ps1: aici nu-i Capacitor,
#  deci nu-i web build (Vite/tsc) si nu-i cap sync. Doar gradle.
#
#  Particularitati ale acestei masini (mostenit de la tics build):
#   1. GRADLE_USER_HOME din mediu poate arata spre D:\ inexistent - il fortam
#      pe %USERPROFILE%\.gradle.
#   2. Avast intercepteaza TLS - Java n-are CA-ul lui, PKIX fail la
#      maven/google. Reutilizam tixello-cacerts.jks din tics-app/mobile/android/.
#   3. Wrapper-ul Gradle avea networkTimeout=10s si nu apuca sa descarce cele
#      ~130 MB de distributie 8.7-bin. Am urcat la 1800s in gradle-wrapper.properties
#      (fisier commit-uit) deci wrapper-ul isi termina download-ul in liniste.
# =========================================================
param(
  [switch]$Install,
  [switch]$Clean,
  [switch]$SkipTests,
  [switch]$Publish,
  [string]$Version = '0.1.0'
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$androidDir = $root  # widget-ul are gradlew la radacina, nu in /android

# Executabilele native (gradlew) scriu warning-uri pe stderr. In PS 5.1 devin
# ErrorRecord si opresc scriptul chiar cu exit code 0. Rulam cu 'Continue' si
# verificam $LASTEXITCODE.
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
# Widget-ul n-are truststore propriu, il refolosim pe cel din tics-app -
# aceleasi CA-uri, aceeasi parola. Cale relativa din tixello-widget-android/.
$truststore = Resolve-Path (Join-Path $root '..\tics-app\mobile\android\tixello-cacerts.jks') -ErrorAction SilentlyContinue
if ($truststore) {
  $env:GRADLE_OPTS = "-Djavax.net.ssl.trustStore=$($truststore.Path) -Djavax.net.ssl.trustStorePassword=changeit"
  Write-Host "TRUSTSTORE       = $($truststore.Path)" -ForegroundColor DarkGray
}
else {
  Write-Host "TRUSTSTORE       = lipseste - daca apar erori PKIX vezi tics-app README-BUILD.md" -ForegroundColor Yellow
}

# ---- 1/2: teste unitare JVM ----
Push-Location $androidDir
try {
  if (-not $SkipTests) {
    Write-Host ''
    Write-Host '[1/2] Gradle testDebugUnitTest...' -ForegroundColor Cyan
    Invoke-Native '.\gradlew.bat' @('testDebugUnitTest', '--no-daemon') 'Testele unitare au esuat.'
  }

  # ---- 2/2: Build APK ----
  Write-Host ''
  Write-Host '[2/2] Gradle assembleDebug...' -ForegroundColor Cyan
  if ($Clean) { Invoke-Native '.\gradlew.bat' @('clean', '--no-daemon') 'Gradle clean esuat.' }
  Invoke-Native '.\gradlew.bat' @('assembleDebug', '--no-daemon') 'Build Gradle esuat.'
}
finally { Pop-Location }

# ---- rezultat ----
$apk = Join-Path $androidDir 'app\build\outputs\apk\debug\app-debug.apk'
if (-not (Test-Path $apk)) { throw "APK negasit la $apk" }

$outDir = Join-Path $root 'out'
New-Item -ItemType Directory -Force $outDir | Out-Null
$copy = Join-Path $outDir 'tixello-widget-debug.apk'
Copy-Item $apk $copy -Force

$size = [math]::Round((Get-Item $apk).Length / 1MB, 2)
Write-Host ''
Write-Host 'BUILD OK' -ForegroundColor Green
Write-Host ("  {0}  ({1} MB)" -f $apk, $size)
Write-Host ("  copiat si la: {0}" -f $copy)

# ---- publicare la core.tixello.com/andrei-apk ----
# Scrie APK-ul + apk.json in public/andrei-apk/ (folder servit de Laravel
# public/, deci accesibil la https://core.tixello.com/andrei-apk).
# Pagina de descarcare (index.html) citeste apk.json la load. Metadata
# (versiune, marime, sha256, publicat_la) e generata aici, nu batuta de
# mana — asa pagina nu ramane in urma de build.
if ($Publish) {
  $publicDir = Resolve-Path (Join-Path $root '..\public\andrei-apk') -ErrorAction SilentlyContinue
  if (-not $publicDir) { throw "Nu gasesc epas\public\andrei-apk (esti in afara repo-ului epas?)" }

  $fileName = "tixello-widget-$Version.apk"
  $target = Join-Path $publicDir.Path $fileName

  # APK-urile vechi din folderul de publicare se sterg — evita confuzia
  # cand cineva descarca versiunea gresita dintr-un folder cu 3 APK-uri.
  Get-ChildItem -Path $publicDir.Path -Filter 'tixello-widget-*.apk' -ErrorAction SilentlyContinue |
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
    min_sdk   = 'Android 8.0+'
    published = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
  }

  $metaPath = Join-Path $publicDir.Path 'apk.json'
  $json = $meta | ConvertTo-Json
  [System.IO.File]::WriteAllText($metaPath, $json, [System.Text.UTF8Encoding]::new($false))

  Write-Host ''
  Write-Host 'PUBLICAT' -ForegroundColor Green
  Write-Host ("  {0}  ({1} MB)" -f $target, $sizeMb)
  Write-Host ("  sha256: {0}" -f $sha)
  Write-Host ("  {0}" -f $metaPath)
  Write-Host '  Pagina https://core.tixello.com/andrei-apk se actualizeaza singura din apk.json.'
  Write-Host '  Mai ramane: commit + push, apoi git pull pe live.'
}

if ($Install) {
  $adb = Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
  if (-not (Test-Path $adb)) { throw "adb negasit la $adb - seteaza ANDROID_HOME sau instaleaza platform-tools" }
  Write-Host ''
  Write-Host 'Instalez pe telefon...' -ForegroundColor Cyan
  Invoke-Native $adb @('install', '-r', $apk) 'Instalare esuata (telefon conectat? depanare USB activa?)'
}
else {
  Write-Host ''
  Write-Host ("Instalare:  adb install -r `"{0}`"" -f $apk) -ForegroundColor DarkGray
}
