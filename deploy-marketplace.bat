@echo off
setlocal enabledelayedexpansion

:: ============================================================
:: Deploy Marketplace Script
:: Pushes ambilet folder contents to marketplace branch root
:: ============================================================

set "SOURCE_DIR=epas\resources\marketplaces\ambilet"
set "TARGET_BRANCH=marketplace"
set "TEMP_DIR=%TEMP%\marketplace-deploy-%RANDOM%"
set "REPO_URL=https://github.com/andreiandoo/epas.git"

echo.
echo ========================================
echo   Ambilet Marketplace Deploy Script
echo ========================================
echo.

:: Check if we're in the right directory
if not exist "%SOURCE_DIR%" (
    echo [ERROR] Source directory not found: %SOURCE_DIR%
    echo Make sure you run this script from the eventpilot root folder.
    exit /b 1
)

:: Get commit message from argument or use default
set "COMMIT_MSG=%~1"
if "%COMMIT_MSG%"=="" (
    for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set "datetime=%%I"
    set "COMMIT_MSG=Deploy marketplace !datetime:~0,4!-!datetime:~4,2!-!datetime:~6,2! !datetime:~8,2!:!datetime:~10,2!"
)

echo [1/6] Creating temporary directory...
mkdir "%TEMP_DIR%" 2>nul
if errorlevel 1 (
    echo [ERROR] Failed to create temp directory
    exit /b 1
)

echo [2/6] Cloning marketplace branch...
git clone --branch %TARGET_BRANCH% --single-branch --depth 1 "%REPO_URL%" "%TEMP_DIR%" 2>nul
if errorlevel 1 (
    echo       Branch doesn't exist, creating new orphan branch...
    git clone --depth 1 "%REPO_URL%" "%TEMP_DIR%"
    cd /d "%TEMP_DIR%"
    git checkout --orphan %TARGET_BRANCH%
    git rm -rf . >nul 2>&1
) else (
    cd /d "%TEMP_DIR%"
    :: Clean existing files except .git
    for /f "delims=" %%i in ('dir /b /a-d 2^>nul') do del "%%i" 2>nul
    for /f "delims=" %%i in ('dir /b /ad 2^>nul ^| findstr /v /i "^\.git$ ^data$"') do rd /s /q "%%i" 2>nul
)

echo [3/6] Copying marketplace files...
cd /d "%~dp0"
xcopy "%SOURCE_DIR%\*" "%TEMP_DIR%\" /E /Y /Q /H >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy files
    rd /s /q "%TEMP_DIR%"
    exit /b 1
)

:: Minify JS files using terser (if available)
echo [3.5/6] Minifying JavaScript files...
cd /d "%TEMP_DIR%"
where npx >nul 2>&1
if not errorlevel 1 (
    set "MINIFIED=0"
    for /r "assets\js" %%f in (*.js) do (
        echo       Minifying: %%~nxf
        npx terser "%%f" --compress --mangle -o "%%f.tmp" 2>nul
        if not errorlevel 1 (
            move /y "%%f.tmp" "%%f" >nul
            set /a MINIFIED+=1
        ) else (
            del "%%f.tmp" 2>nul
            echo       [WARN] Failed to minify %%~nxf, keeping original
        )
    )
    echo       Minified !MINIFIED! JS files.
) else (
    echo       [SKIP] npx not found, skipping JS minification
)

:: Minify custom.css using clean-css-cli or simple whitespace removal
echo [3.6/6] Minifying CSS files...
if exist "assets\css\custom.css" (
    npx clean-css-cli -o "assets\css\custom.css.tmp" "assets\css\custom.css" 2>nul
    if not errorlevel 1 (
        move /y "assets\css\custom.css.tmp" "assets\css\custom.css" >nul
        echo       Minified custom.css
    ) else (
        del "assets\css\custom.css.tmp" 2>nul
        echo       [SKIP] clean-css-cli not available, keeping original CSS
    )
)

:: Write deploy timestamp so there's always a change to push (triggers server webhook)
cd /d "%TEMP_DIR%"
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set "now=%%I"
echo !now:~0,4!-!now:~4,2!-!now:~6,2! !now:~8,2!:!now:~10,2!:!now:~12,2! > .deploy-timestamp

echo [4/6] Staging changes...
git add -A

echo [5/6] Committing changes...
git commit -m "%COMMIT_MSG%"
if errorlevel 1 (
    echo [ERROR] Commit failed
    cd /d "%~dp0"
    rd /s /q "%TEMP_DIR%"
    exit /b 1
)

echo [6/6] Pushing to remote...
git push -u origin %TARGET_BRANCH%
if errorlevel 1 (
    echo [ERROR] Push failed. Check your credentials.
    cd /d "%~dp0"
    rd /s /q "%TEMP_DIR%"
    exit /b 1
)

echo.
echo ========================================
echo   Deploy completed successfully!
echo   Branch: %TARGET_BRANCH%
echo   Message: %COMMIT_MSG%
echo ========================================
echo.

:: Cleanup
cd /d "%~dp0"
rd /s /q "%TEMP_DIR%"

exit /b 0
