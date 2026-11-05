@echo off
REM ============================================================
REM  晨雨ERP - start local dev server (Windows)
REM
REM  Usage : start.bat
REM  Env   : PHP_BIN  full path to php.exe (optional)
REM          PORT     listen port, default 8000
REM
REM  ASCII-only output, see setup.bat header for the reason.
REM ============================================================
setlocal enabledelayedexpansion

set "PHP_BIN=%PHP_BIN%"
if "!PHP_BIN!"=="" (
    if exist "%USERPROFILE%\.workbuddy\binaries\php\8.3.33\php.exe" (
        set "PHP_BIN=%USERPROFILE%\.workbuddy\binaries\php\8.3.33\php.exe"
    )
)
if "!PHP_BIN!"=="" (
    for /f "delims=" %%i in ('where php 2^>nul') do (
        set "PHP_BIN=%%i"
        goto :php_done
    )
)
:php_done
if "!PHP_BIN!"=="" (
    echo [ERROR] PHP executable not found. Set PHP_BIN and retry.
    exit /b 1
)

if not exist ".env" (
    echo [ERROR] .env not found. Run setup.bat first.
    exit /b 1
)
if not exist "vendor\autoload.php" (
    echo [ERROR] vendor/ not found. Run setup.bat first.
    exit /b 1
)

set "PORT=%PORT%"
if "!PORT!"=="" set "PORT=8000"

echo.
echo   晨雨ERP starting at http://127.0.0.1:!PORT!
echo   Press Ctrl+C to stop.
echo.
"%PHP_BIN%" artisan serve --host=127.0.0.1 --port=!PORT!
endlocal
