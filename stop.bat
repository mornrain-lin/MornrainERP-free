@echo off
REM ============================================================
REM  晨雨ERP - stop the local dev server (Windows)
REM
REM  Usage : stop.bat
REM  Env   : PORT  listen port, default 8000
REM
REM  Looks up the PID listening on the port, SHOWS it, and asks
REM  for confirmation before terminating anything.
REM
REM  ASCII-only output, and no nested quotes inside `for /f`
REM  (cmd parses those at parse time, see setup.bat header).
REM ============================================================
setlocal enabledelayedexpansion

set "PORT=%PORT%"
if "!PORT!"=="" set "PORT=8000"

set "TMPF=%TEMP%\mornrainerp_stop.tmp"

netstat -ano | findstr LISTENING | findstr ":!PORT! " >"%TMPF%" 2>nul

set "PID="
for /f "usebackq tokens=5" %%p in ("%TMPF%") do (
    if "!PID!"=="" set "PID=%%p"
)

if "!PID!"=="" (
    echo.
    echo   No process is listening on port !PORT!. Nothing to stop.
    echo.
    if exist "%TMPF%" del "%TMPF%" >nul 2>&1
    endlocal
    exit /b 0
)

echo.
echo   Port !PORT! is in use by PID !PID!.
tasklist /fi "PID eq !PID!" /fo list 2>nul | findstr /i "Image"

set "ANSWER="
set /p "ANSWER=  Terminate this process? [y/N] "
if /i not "!ANSWER!"=="y" (
    echo   Cancelled, nothing was terminated.
    if exist "%TMPF%" del "%TMPF%" >nul 2>&1
    endlocal
    exit /b 0
)

taskkill /PID !PID! /F
if errorlevel 1 (
    echo [ERROR] Failed to terminate PID !PID!. Try an elevated terminal.
    if exist "%TMPF%" del "%TMPF%" >nul 2>&1
    exit /b 1
)
echo   Stopped PID !PID!.

if exist "%TMPF%" del "%TMPF%" >nul 2>&1
endlocal
