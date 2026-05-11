<#
Creates a Windows Scheduled Task to run the `sla_escalate.php` script on a regular interval.
Edit `$phpPath` and `$workDir` as needed before running this script.
#>

$phpPath = 'C:\\path\\to\\php.exe' # <-- set your PHP executable path
$workDir = 'C:\\path\\to\\free-cmms 0.04' # <-- set your app directory
$phpPath = Read-Host 'Full path to php.exe (e.g. C:\\php\\php.exe)'
$workDir = Read-Host 'Full path to application directory (e.g. C:\\sites\\free-cmms)'

if (-not (Test-Path $phpPath)) { Write-Error "PHP executable not found: $phpPath"; exit 1 }
if (-not (Test-Path $workDir)) { Write-Error "Work dir not found: $workDir"; exit 1 }

$taskName = 'Maintenix_SLA_Escalate'
$action = "`"$phpPath`" -f `"$workDir\\sla_escalate.php`" 

Write-Host "Creating scheduled task '$taskName' to run every 15 minutes..."

$schtask = "schtasks /Create /SC MINUTE /MO 15 /TN \"$taskName\" /TR \"$action\" /F"
Invoke-Expression $schtask

Write-Host "Task created. Verify in Task Scheduler or run: schtasks /Query /TN $taskName"
