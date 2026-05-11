@echo off
REM Run SLA escalation script once. Edit php path if needed.
set PHP_EXE=php
cd /d "%~dp0"
"%PHP_EXE%" sla_escalate.php >> sla_escalate.log 2>&1
echo Completed. See sla_escalate.log for output.
