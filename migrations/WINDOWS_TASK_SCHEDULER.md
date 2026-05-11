Windows Task Scheduler — run `generate_pm.php` daily

This file shows two safe options to schedule the PM generator daily on Windows.

Prerequisites
- PHP CLI installed and accessible (`php` in PATH) or know `php.exe` full path.
- Run PowerShell as Administrator when registering tasks that require elevated privileges.

Option A — one-line PowerShell helper (recommended)
1. Open PowerShell as Administrator.
2. Allow script execution for the session and run the helper (from repo root):

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
.\migrations\create_pm_task.ps1 -Time "03:00"
```

Optional parameters:
- `-PhpPath "C:\\php\\php.exe"` — full path to `php.exe` if not in PATH.
- `-ScriptPath "C:\\path\\to\\free-cmms\\generate_pm.php"` — if repo is elsewhere.
- `-TaskName "My_Task_Name"` — custom scheduled task name.
- `-Time "HH:mm"` — time of day, 24-hour format (default `03:00`).

The helper uses the modern `Register-ScheduledTask` cmdlet; if that's not available it falls back to `schtasks.exe`.

Option B — Manual via Task Scheduler UI
1. Open Task Scheduler (`taskschd.msc`).
2. Create Basic Task → name it (e.g., `Maintenix_Generate_PM`).
3. Trigger: Daily → set start time.
4. Action: Start a program.
   - Program/script: full path to `php.exe` (e.g., `C:\php\php.exe`).
   - Add arguments: full path to `generate_pm.php` (e.g., `C:\path\to\free-cmms\generate_pm.php`).
5. Finish and test with `Run` in Task Scheduler.

Verifying the task
- Check Task Scheduler UI or run:

```powershell
schtasks /Query /TN "Maintenix_Generate_PM"
schtasks /Run /TN "Maintenix_Generate_PM"  # to run immediately
```

Notes
- The scheduled task runs the CLI PHP script which will create PM instances and work orders when schedules are due.
- If you prefer running under `SYSTEM`, set the `-User` in the PowerShell helper accordingly (requires Admin credentials).
- Logs: the script prints to stdout when run from CLI; to capture logs, change the scheduled task action to: `php.exe "C:\path\to\generate_pm.php" >> C:\path\to\free-cmms\logs\pm_generate.log 2>&1`.
