Param(
    [string]$PhpPath = (Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source -ErrorAction SilentlyContinue),
    [string]$ScriptPath = "$(Resolve-Path "$PSScriptRoot\..\generate_pm.php")",
    [string]$TaskName = "Maintenix_Generate_PM",
    [string]$Time = "03:00"
)

if (-not $PhpPath) {
    Write-Host "php.exe not found in PATH. Provide -PhpPath 'C:\\path\\to\\php.exe'" -ForegroundColor Yellow
    exit 1
}

try {
    $PhpPath = $PhpPath.Trim('"')
    $ScriptPath = (Resolve-Path $ScriptPath).Path
} catch {
    Write-Error "Failed to resolve paths: $_"
    exit 1
}

Write-Host "Creating scheduled task '$TaskName' to run:`n  $PhpPath $ScriptPath`nDaily at $Time" -ForegroundColor Green

try {
    $action = New-ScheduledTaskAction -Execute $PhpPath -Argument "`"$ScriptPath`""
    $trigger = New-ScheduledTaskTrigger -Daily -At $Time
    Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Description "Run Free-CMMS PM generator daily" -User $env:USERNAME -RunLevel Highest -Force
    Write-Host "Scheduled task created successfully." -ForegroundColor Green
} catch {
    Write-Warning "Register-ScheduledTask failed or not available. Falling back to schtasks.exe: $_"
    $quotedPhp = '"' + $PhpPath + '"'
    $quotedScript = '"' + $ScriptPath + '"'
    $taskCmd = "schtasks /Create /SC DAILY /TN `"$TaskName`" /TR $quotedPhp $quotedScript /ST $Time /F"
    Write-Host "Running: $taskCmd"
    cmd.exe /c $taskCmd
    if ($LASTEXITCODE -eq 0) { Write-Host "Scheduled task created via schtasks." -ForegroundColor Green } else { Write-Error "Failed to create scheduled task via schtasks." }
}

Write-Host "Done. Verify in Task Scheduler (taskschd.msc) or run:`n  schtasks /Query /TN $TaskName" -ForegroundColor Cyan
