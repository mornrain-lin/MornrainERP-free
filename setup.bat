@echo off
REM ============================================================
REM  港湾ERP - Windows one-click setup
REM
REM  Usage : setup.bat
REM  Env   : PHP_BIN       full path to php.exe       (optional)
REM          COMPOSER_BIN  full path to composer.phar (optional)
REM          FORCE_SEED=1  re-run seeders even if data exists
REM
REM  NOTES : - Every string printed here is ASCII-only on purpose.
REM            cmd.exe decodes batch files with the active OEM
REM            codepage, so non-ASCII text gets garbled. Chinese
REM            documentation lives in README.md instead.
REM          - No `for /f ... in ('command with "quotes"')` anywhere.
REM            cmd mis-parses nested quotes at PARSE time (it fails
REM            even on branches that never execute). Every lookup
REM            goes through a temp file plus `usebackq` instead.
REM ============================================================
setlocal enabledelayedexpansion

set "TMPF=%TEMP%\gangwanerp_setup.tmp"

echo.
echo ============================================================
echo   港湾ERP  -  one-click setup (Windows)
echo ============================================================
echo.

REM ---------- 1. locate PHP ----------
set "PHP_BIN=%PHP_BIN%"
if "!PHP_BIN!"=="" (
    if exist "%USERPROFILE%\.workbuddy\binaries\php\8.3.33\php.exe" (
        set "PHP_BIN=%USERPROFILE%\.workbuddy\binaries\php\8.3.33\php.exe"
    )
)
if "!PHP_BIN!"=="" (
    where php >"%TMPF%" 2>nul
    set "PHP_BIN="
    for /f "usebackq delims=" %%i in ("%TMPF%") do (
        if "!PHP_BIN!"=="" set "PHP_BIN=%%i"
    )
)
if "!PHP_BIN!"=="" (
    echo [ERROR] PHP executable not found.
    echo         Set PHP_BIN and run again:
    echo             set PHP_BIN=C:\path\to\php.exe
    exit /b 1
)
if not exist "!PHP_BIN!" (
    echo [ERROR] PHP_BIN does not exist: !PHP_BIN!
    exit /b 1
)
echo [1/7] PHP runtime  : !PHP_BIN!

REM ---------- 2. version + extensions ----------
if not exist "scripts\requirements-check.php" (
    echo [ERROR] scripts\requirements-check.php not found.
    echo         Run setup.bat from the project root.
    exit /b 1
)
"%PHP_BIN%" scripts\requirements-check.php
if errorlevel 1 (
    echo [ERROR] Environment check failed, see message above.
    exit /b 1
)

REM ---------- 3. composer dependencies ----------
if exist "vendor\autoload.php" (
    echo [3/7] Dependencies: vendor/ present, skipping composer install
) else (
    set "COMPOSER_BIN=%COMPOSER_BIN%"
    if "!COMPOSER_BIN!"=="" (
        if exist "%USERPROFILE%\.workbuddy\binaries\composer\composer.phar" (
            set "COMPOSER_BIN=%USERPROFILE%\.workbuddy\binaries\composer\composer.phar"
        )
    )
    if "!COMPOSER_BIN!"=="" (
        where composer >"%TMPF%" 2>nul
        set "COMPOSER_BIN="
        for /f "usebackq delims=" %%i in ("%TMPF%") do (
            if "!COMPOSER_BIN!"=="" set "COMPOSER_BIN=%%i"
        )
    )
    if "!COMPOSER_BIN!"=="" (
        echo [ERROR] vendor/ missing and no composer found.
        echo         Set COMPOSER_BIN to composer.phar and retry.
        exit /b 1
    )
    echo [3/7] Dependencies: installing via composer ...
    "%PHP_BIN%" "!COMPOSER_BIN!" install --no-interaction --prefer-dist
    if errorlevel 1 (
        echo [ERROR] composer install failed.
        exit /b 1
    )
)

REM ---------- 4. environment file ----------
if not exist ".env" (
    if not exist ".env.example" (
        echo [ERROR] Neither .env nor .env.example found.
        exit /b 1
    )
    copy /y ".env.example" ".env" >nul
    echo [4/7] .env         : created from .env.example
) else (
    echo [4/7] .env         : already present, keeping existing config
)

REM ---------- 5. app key ----------
findstr /b "APP_KEY=base64:" ".env" >"%TMPF%" 2>nul
set "HASKEY="
for /f "usebackq delims=" %%a in ("%TMPF%") do set "HASKEY=1"
if "!HASKEY!"=="" (
    "%PHP_BIN%" artisan key:generate --force
    if errorlevel 1 (
        echo [ERROR] Failed to generate APP_KEY.
        exit /b 1
    )
    echo [5/7] APP_KEY      : generated
) else (
    echo [5/7] APP_KEY      : already set
)

REM ---------- 6. sqlite file ----------
findstr /b "DB_CONNECTION=" ".env" >"%TMPF%" 2>nul
set "DBCONN=sqlite"
for /f "usebackq tokens=1,2 delims==" %%a in ("%TMPF%") do set "DBCONN=%%b"
set "FRESHDB=0"
if /i "!DBCONN!"=="sqlite" (
    if not exist "database\database.sqlite" (
        if not exist "database" mkdir "database"
        type nul > "database\database.sqlite"
        set "FRESHDB=1"
        echo [6/7] SQLite file  : database\database.sqlite created
    ) else (
        echo [6/7] SQLite file  : database\database.sqlite present
    )
) else (
    echo [6/7] Database     : !DBCONN! ^(make sure the server is reachable^)
)

REM ---------- migrations ----------
echo.
echo ---- Running migrations ----
"%PHP_BIN%" artisan migrate --force
if errorlevel 1 (
    echo [ERROR] Migration failed. Fix the error above and rerun setup.bat.
    exit /b 1
)

REM ---------- 7. seed ----------
set "DOSEED=0"
if "!FRESHDB!"=="1" set "DOSEED=1"
if /i "%FORCE_SEED%"=="1" set "DOSEED=1"
if "!DOSEED!"=="1" (
    echo ---- Seeding demo data ----
    "%PHP_BIN%" artisan db:seed --force
    if errorlevel 1 (
        echo [WARN] Seeding failed, but the schema is ready. Continuing.
    )
) else (
    echo [7/7] Demo data    : skipped, database already has data
    echo         Set FORCE_SEED=1 to re-seed from scratch.
)

REM ---------- clear caches ----------
"%PHP_BIN%" artisan config:clear >nul 2>&1
"%PHP_BIN%" artisan cache:clear  >nul 2>&1
"%PHP_BIN%" artisan route:clear  >nul 2>&1
"%PHP_BIN%" artisan view:clear   >nul 2>&1

REM ---------- summary ----------
findstr /b "INIT_ADMIN_EMAIL=" ".env" >"%TMPF%" 2>nul
set "ADMINMAIL=admin@gangwan.local"
for /f "usebackq tokens=1,2 delims==" %%a in ("%TMPF%") do set "ADMINMAIL=%%b"

echo.
echo ============================================================
echo   Setup complete.
echo ============================================================
echo   Admin account : !ADMINMAIL!
echo   Password      : the INIT_ADMIN_PASSWORD value in .env
echo                   change it before any real use
echo.
echo   Start the app : start.bat
echo   Then open     : http://127.0.0.1:8000
echo ============================================================
echo.

if exist "%TMPF%" del "%TMPF%" >nul 2>&1
endlocal
