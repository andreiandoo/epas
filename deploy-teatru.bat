@echo off
setlocal enabledelayedexpansion

:: ============================================================
:: Deploy teatru.tixello.ro
:: Pushes resources\marketplaces\teatru contents to `teatru` branch,
:: then triggers the server webhook to pull + swap files.
:: ============================================================

cd /d "%~dp0"

set "SOURCE_DIR=resources\tenant-demos\teatru"
set "TARGET_BRANCH=teatru"
set "TEMP_DIR=%TEMP%\teatru-deploy-%RANDOM%"
set "REPO_URL=https://github.com/andreiandoo/epas.git"
set "WEBHOOK_URL=https://teatru.tixello.ro/_webhook-deploy.php?test=1"

echo.
echo ========================================
echo   teatru.tixello.ro Deploy
echo ========================================
echo.

if not exist "%SOURCE_DIR%" (
    echo [ERROR] Source directory not found: %~dp0%SOURCE_DIR%
    exit /b 1
)

set "COMMIT_MSG=%~1"
if "%COMMIT_MSG%"=="" (
    for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set "datetime=%%I"
    set "COMMIT_MSG=Deploy teatru !datetime:~0,4!-!datetime:~4,2!-!datetime:~6,2! !datetime:~8,2!:!datetime:~10,2!"
)

echo [1/6] Creating temp directory...
mkdir "%TEMP_DIR%" 2>nul

echo [2/6] Cloning %TARGET_BRANCH% branch...
git clone --branch %TARGET_BRANCH% --single-branch --depth 1 "%REPO_URL%" "%TEMP_DIR%" 2>nul
if errorlevel 1 (
    echo       Branch doesn't exist, creating new orphan branch...
    git clone --depth 1 "%REPO_URL%" "%TEMP_DIR%"
    cd /d "%TEMP_DIR%"
    git checkout --orphan %TARGET_BRANCH%
    git rm -rf . >nul 2>&1
) else (
    cd /d "%TEMP_DIR%"
    for /f "delims=" %%i in ('dir /b /a-d 2^>nul') do del "%%i" 2>nul
    for /f "delims=" %%i in ('dir /b /ad 2^>nul ^| findstr /v /i "^\.git$ ^data$ ^cache$"') do rd /s /q "%%i" 2>nul
)

echo [3/6] Copying teatru skin files...
cd /d "%~dp0"
xcopy "%SOURCE_DIR%\*" "%TEMP_DIR%\" /E /Y /Q /H >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy files
    rd /s /q "%TEMP_DIR%"
    exit /b 1
)

cd /d "%TEMP_DIR%"
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set "now=%%I"
echo !now:~0,4!-!now:~4,2!-!now:~6,2! !now:~8,2!:!now:~10,2!:!now:~12,2! > .deploy-timestamp

echo [4/6] Committing...
git add -A
git commit -m "%COMMIT_MSG%"
if errorlevel 1 (
    echo [ERROR] Commit failed ^(nothing to commit?^)
    cd /d "%~dp0"
    rd /s /q "%TEMP_DIR%"
    exit /b 1
)

echo [5/6] Pushing to %TARGET_BRANCH%...
git push -u origin %TARGET_BRANCH% --force
if errorlevel 1 (
    echo [ERROR] Push failed. Check credentials.
    cd /d "%~dp0"
    rd /s /q "%TEMP_DIR%"
    exit /b 1
)

echo [6/6] Triggering server webhook (waits 5s)...
timeout /t 5 /nobreak >nul
curl -s -o nul -w "       webhook HTTP %%{http_code}\n" "%WEBHOOK_URL%" 2>nul

echo.
echo ========================================
echo   Deploy completed!
echo   Branch: %TARGET_BRANCH%  Target: teatru.tixello.ro
echo   Message: %COMMIT_MSG%
echo ========================================
echo.

cd /d "%~dp0"
rd /s /q "%TEMP_DIR%"
exit /b 0
