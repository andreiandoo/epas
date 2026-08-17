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
  [switch]$SkipTests
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
