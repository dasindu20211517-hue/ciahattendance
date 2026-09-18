@echo off
echo CIAH Attendance - Daily Report Scheduler
echo ========================================
echo.
echo This will run the daily attendance report for Dasindu
echo and send it to WhatsApp +94783788180
echo.

:MENU
echo Select an option:
echo 1. Send report now (Test)
echo 2. Set up daily automation (Windows Task Scheduler)
echo 3. Set up hourly automation (Every hour)
echo 4. Exit
echo.
set /p choice="Enter your choice (1-4): "

if "%choice%"=="1" goto SENDNOW
if "%choice%"=="2" goto SETUPDAILY
if "%choice%"=="3" goto SETUPHOURLY
if "%choice%"=="4" goto EXIT
goto MENU

:SENDNOW
echo.
echo Sending daily report now...
C:\xampp\php\php.exe auto_daily_report.php
pause
goto MENU

:SETUPDAILY
echo.
echo Setting up daily automation...
echo.
echo Creating Windows Task to run daily at 5:00 PM...

schtasks /create /tn "CIAH Daily Attendance Report" /tr "C:\xampp\php\php.exe \"%~dp0auto_daily_report.php\"" /sc daily /st 17:00 /ru SYSTEM

echo.
echo ✅ Daily task created successfully!
echo Report will be sent automatically at 5:00 PM every day.
echo.
echo To modify or remove the task:
echo - Open Task Scheduler (taskschd.msc)
echo - Look for "CIAH Daily Attendance Report"
echo.
pause
goto MENU

:SETUPHOURLY
echo.
echo Setting up hourly automation...
echo.
echo Creating Windows Task to run every hour during work hours (8 AM - 6 PM)...

schtasks /create /tn "CIAH Hourly Attendance Report" /tr "C:\xampp\php\php.exe \"%~dp0auto_daily_report.php\"" /sc hourly /mo 1 /st 08:00 /et 18:00 /ru SYSTEM

echo.
echo ✅ Hourly task created successfully!
echo Report will be sent every hour from 8 AM to 6 PM.
echo.
pause
goto MENU

:EXIT
echo.
echo Goodbye!
echo.