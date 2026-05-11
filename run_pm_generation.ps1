#!/usr/bin/env powershell
# Windows PowerShell Script to run PM Generation daily
# This is an alternative to run_pm_generation.bat with better logging

$script_dir = "C:\free-cmms 0.04"
$log_file = "$script_dir\logs\scheduler.log"
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

# Ensure logs directory exists
if (!(Test-Path "$script_dir\logs")) {
    New-Item -ItemType Directory -Path "$script_dir\logs" -Force | Out-Null
}

# Append to log
Add-Content -Path $log_file -Value "[$timestamp] PM Generation Task Started"

try {
    # Run PHP script
    $output = & php -f "$script_dir\generate_pm.php" 2>&1
    
    # Log output
    $output | Out-File -FilePath $log_file -Append
    
    $end_timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $log_file -Value "[$end_timestamp] PM Generation Task Completed Successfully"
    Add-Content -Path $log_file -Value "========================================"
    
    Write-Host "✓ PM Generation completed. Check $log_file for details."
    exit 0
}
catch {
    $end_timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $log_file -Value "[ERROR] $($_.Exception.Message)"
    Add-Content -Path $log_file -Value "[$end_timestamp] PM Generation Task Failed"
    Add-Content -Path $log_file -Value "========================================"
    
    Write-Host "✗ PM Generation failed. Check $log_file for error details."
    exit 1
}
