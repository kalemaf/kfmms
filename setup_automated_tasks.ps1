# CMMS System Administration - Automated Task Setup
# This script creates Windows Scheduled Tasks for automated system maintenance

# Configuration
$CMMSPath = "C:\free-cmms 0.04"
$PHPPath = "php"  # Assumes PHP is in PATH, adjust if needed
$TaskUser = "SYSTEM"  # Run as system account

# Task definitions
$tasks = @(
    @{
        Name = "CMMS_Daily_Backup"
        Description = "Automated daily database backup for CMMS system"
        Command = "$PHPPath"
        Arguments = "`"$CMMSPath\automated_maintenance.php`" backup"
        Schedule = "DAILY"
        StartTime = "02:00"  # 2 AM daily
        DaysInterval = 1
    },
    @{
        Name = "CMMS_Weekly_Maintenance"
        Description = "Weekly database maintenance and optimization"
        Command = "$PHPPath"
        Arguments = "`"$CMMSPath\automated_maintenance.php`" maintenance"
        Schedule = "WEEKLY"
        StartTime = "03:00"  # 3 AM weekly
        DaysOfWeek = "SUNDAY"
    },
    @{
        Name = "CMMS_Monthly_Archival"
        Description = "Monthly data archival for old records"
        Command = "$PHPPath"
        Arguments = "`"$CMMSPath\automated_maintenance.php`" archival"
        Schedule = "MONTHLY"
        StartTime = "04:00"  # 4 AM monthly
        DaysOfMonth = 1  # First day of month
    },
    @{
        Name = "CMMS_Hourly_Health_Check"
        Description = "Hourly system health monitoring"
        Command = "$PHPPath"
        Arguments = "`"$CMMSPath\automated_maintenance.php`" health_check"
        Schedule = "DAILY"
        StartTime = "00:00"  # Every hour starting at midnight
        RepetitionInterval = "PT1H"  # Every 1 hour
        RepetitionDuration = "P1D"   # For 1 day
    }
)

Write-Host "Setting up CMMS automated maintenance tasks..." -ForegroundColor Green

foreach ($task in $tasks) {
    try {
        # Remove existing task if it exists
        Unregister-ScheduledTask -TaskName $task.Name -Confirm:$false -ErrorAction SilentlyContinue

        # Create new scheduled task
        $action = New-ScheduledTaskAction -Execute $task.Command -Argument $task.Arguments -WorkingDirectory $CMMSPath

        $trigger = switch ($task.Schedule) {
            "DAILY" {
                if ($task.ContainsKey("RepetitionInterval")) {
                    New-ScheduledTaskTrigger -Daily -At $task.StartTime -RepetitionInterval $task.RepetitionInterval -RepetitionDuration $task.RepetitionDuration
                } else {
                    New-ScheduledTaskTrigger -Daily -At $task.StartTime
                }
            }
            "WEEKLY" {
                New-ScheduledTaskTrigger -Weekly -At $task.StartTime -DaysOfWeek $task.DaysOfWeek
            }
            "MONTHLY" {
                New-ScheduledTaskTrigger -Monthly -At $task.StartTime -DaysOfMonth $task.DaysOfMonth
            }
        }

        $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RunOnlyIfNetworkAvailable

        $principal = New-ScheduledTaskPrincipal -UserId $TaskUser -LogonType ServiceAccount

        Register-ScheduledTask -TaskName $task.Name -Description $task.Description -Action $action -Trigger $trigger -Settings $settings -Principal $principal

        Write-Host "Created task: $($task.Name)" -ForegroundColor Green

    } catch {
        Write-Host "Failed to create task $($task.Name): $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host "`nAutomated task setup completed!" -ForegroundColor Green
Write-Host "Tasks created:"
$tasks | ForEach-Object { Write-Host "  - $($_.Name)" }

Write-Host "`nTo view tasks in Task Scheduler:"
Write-Host "  1. Open Task Scheduler (taskschd.msc)"
Write-Host "  2. Navigate to Task Scheduler Library"
Write-Host "  3. Look for tasks starting with 'CMMS_'"

Write-Host "`nTo test a task manually:"
Write-Host "  schtasks /run /tn `"CMMS_Daily_Backup`""

Write-Host "`nTo view task history:"
Write-Host "  schtasks /query /tn `"CMMS_Daily_Backup`" /v /fo list"