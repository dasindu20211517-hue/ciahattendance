@echo off
REM ============================================
REM Auto Sync Setup Script for Windows
REM ============================================
REM This script helps set up the scheduled task
REM for automatic attendance synchronization
REM ============================================

setlocal enabledelayedexpansion

echo.
echo ========================================
echo  CIAH Auto Sync - Windows Setup
echo ========================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: This script must be run as Administrator
    echo.
    echo Please:
    echo 1. Right-click on this script
    echo 2. Select "Run as administrator"
    echo.
    pause
    exit /b 1
)

REM Get the current directory
set "CIAH_DIR=%~dp0"
set "PHP_EXE=C:\xampp\php\php.exe"
set "SCRIPT=%CIAH_DIR%cron\auto_sync.php"

echo.
echo Detected paths:
echo CIAH Directory: %CIAH_DIR%
echo PHP Executable: %PHP_EXE%
echo Script Path: %SCRIPT%
echo.

REM Verify PHP exists
if not exist "%PHP_EXE%" (
    echo ERROR: PHP executable not found at %PHP_EXE%
    echo.
    echo Please verify XAMPP installation path and edit this script to set the correct PHP_EXE path.
    echo.
    pause
    exit /b 1
)

REM Create logs directory
if not exist "%CIAH_DIR%logs" (
    mkdir "%CIAH_DIR%logs"
    echo Created logs directory
)

REM Create the scheduled task
echo.
echo Creating scheduled task: CIAH Auto Sync...
echo.

schtasks /create ^
    /tn "CIAH Auto Sync" ^
    /tr "\"%PHP_EXE%\" \"%SCRIPT%\"" ^
    /sc minute ^
    /mo 5 ^
    /f ^
    /rl highest ^
    /np

if %errorLevel% equ 0 (
    echo.
    echo SUCCESS: Task created successfully!
    echo.
    echo Task Details:
    echo - Name: CIAH Auto Sync
    echo - Schedule: Every 5 minutes
    echo - Status: Enabled
    echo.
    echo The task will now run automatically every 5 minutes.
    echo.
    echo Next steps:
    echo 1. Open Task Scheduler (taskschd.msc) to verify
    echo 2. Visit http://localhost/auto_sync.php to monitor
    echo 3. Check logs/sync.log for sync details
    echo.
) else (
    echo.
    echo ERROR: Failed to create task
    echo.
    echo Please create the task manually using Task Scheduler:
    echo 1. Open Task Scheduler (Win+R, type taskschd.msc)
    echo 2. Right-click "Task Scheduler Library" ^> "Create Task..."
    echo 3. Name: CIAH Auto Sync
    echo 4. Trigger: Repeat every 5 minutes, indefinitely
    echo 5. Action: "%PHP_EXE%" "%SCRIPT%"
    echo.
)

REM Test the script
echo Testing script execution...
"%PHP_EXE%" "%SCRIPT%" >nul 2>&1

if %errorLevel% equ 0 (
    echo Test: PASSED - Script executed successfully
) else (
    echo Test: WARNING - Script returned error code %errorLevel%
    echo This may be normal if device is not reachable
)

echo.
echo ========================================
echo  Setup Complete
echo ========================================
echo.

pause
