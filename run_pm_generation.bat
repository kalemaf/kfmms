@echo off
REM Windows Batch Script to run PM Generation daily
REM This script is triggered by Windows Task Scheduler

cd /d "C:\free-cmms 0.04"
php generate_pm.php >> logs\scheduler.log 2>&1
echo [%date% %time%] PM Generation completed >> logs\scheduler.log
