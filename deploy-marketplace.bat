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

echo [1/7] Creating temporary directory...
mkdir "%TEMP_DIR%" 2>nul
if errorlevel 1 (
    echo [ERROR] Failed to create temp directory
    exit /b 1
)

echo [2/7] Cloning marketplace branch...
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

echo [3/7] Copying marketplace files...
cd /d "%~dp0"
xcopy "%SOURCE_DIR%\*" "%TEMP_DIR%\" /E /Y /Q /H >nul
if errorlevel 1 (
    echo [ERROR] Failed to copy files
    rd /s /q "%TEMP_DIR%"
    exit /b 1
)

:: Rebuild Tailwind CSS (scans PHP/JS for used classes)
cd /d "%TEMP_DIR%"
call :rebuild_tailwind

:: Minify JS and CSS
cd /d "%TEMP_DIR%"
call :minify_assets

:: Write deploy timestamp so there's always a change to push (triggers server webhook)
cd /d "%TEMP_DIR%"
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set "now=%%I"
echo !now:~0,4!-!now:~4,2!-!now:~6,2! !now:~8,2!:!now:~10,2!:!now:~12,2! > .deploy-timestamp

echo [5/7] Staging changes...
git add -A

echo [6/7] Committing changes...
git commit -m "%COMMIT_MSG%"
if errorlevel 1 (
    echo [ERROR] Commit failed
    cd /d "%~dp0"
    rd /s /q "%TEMP_DIR%"
    exit /b 1
)

echo [7/7] Pushing to remote...
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


:: ============================================================
:: Subroutine: Rebuild Tailwind CSS per-page bundles
:: ============================================================
:rebuild_tailwind
where tailwindcss >nul 2>&1
if errorlevel 1 (
    echo [3.5/7] [SKIP] tailwindcss not found. Install with: npm install -g tailwindcss
    goto :eof
)
echo [3.5/7] Rebuilding Tailwind CSS bundles...

:: Create bundles output directory
if not exist "assets\css\bundles" mkdir "assets\css\bundles"

:: Build per-page CSS bundles (Tailwind + custom CSS combined)
set "BUNDLE_COUNT=0"
for %%b in (home event listing single checkout auth account organizer static error) do (
    if exist "assets\css\build\tailwind.%%b.cjs" (
        call tailwindcss -c assets\css\build\tailwind.%%b.cjs -i assets\css\build\input-%%b.css -o assets\css\bundles\%%b.css --minify 2>nul
        if exist "assets\css\bundles\%%b.css" (
            set /a BUNDLE_COUNT+=1
        ) else (
            echo       [WARN] Failed to build bundle: %%b
        )
    )
)
echo       !BUNDLE_COUNT! CSS bundles built.

:: Also build legacy fallback (full tailwind.min.css) in case any page misses $cssBundle
if exist "tailwind.config.cjs" (
    call tailwindcss -c tailwind.config.cjs -i assets\css\tailwind-input.css -o assets\css\tailwind.min.css --minify 2>nul
    if exist "assets\css\tailwind.min.css" (
        echo       Legacy tailwind.min.css also built as fallback.
    )
)
goto :eof


:: ============================================================
:: Subroutine: Minify JS and CSS assets
:: ============================================================
:minify_assets
echo [4/7] Minifying assets...

where terser >nul 2>&1
if errorlevel 1 (
    echo       [SKIP] terser not found. Install with: npm install -g terser
    goto :eof
)

set "MINIFIED=0"
for /r "assets\js" %%f in (*.js) do call :minify_one_js "%%f" "%%~nxf"

echo       JS: !MINIFIED! files minified.

:: Minify CSS (bundles are already minified by tailwindcss --minify, but minify custom.css fallback)
where cleancss >nul 2>&1
if not errorlevel 1 (
    if exist "assets\css\custom.css" (
        call cleancss -o "assets\css\custom.css.tmp" "assets\css\custom.css" 2>nul
        if exist "assets\css\custom.css.tmp" (
            move /y "assets\css\custom.css.tmp" "assets\css\custom.css" >nul
            echo       CSS: custom.css minified.
        )
    )
)

:: Combine custom.css + tailwind.min.css into single styles.css (reduces critical path by 1 request)
if exist "assets\css\custom.css" (
    if exist "assets\css\tailwind.min.css" (
        copy /b "assets\css\custom.css"+"assets\css\tailwind.min.css" "assets\css\styles.css" >nul
        echo       CSS: combined custom.css + tailwind.min.css into styles.css
    )
)
goto :eof


:: ============================================================
:: Subroutine: Minify a single JS file
:: ============================================================
:minify_one_js
call terser "%~1" --compress --mangle -o "%~1.tmp" 2>nul
if exist "%~1.tmp" (
    move /y "%~1.tmp" "%~1" >nul
    set /a MINIFIED+=1
) else (
    echo       [WARN] Failed to minify %~2
)
goto :eof
