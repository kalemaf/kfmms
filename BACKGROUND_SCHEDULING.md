# Background Scheduling Guide for Maintenix

This guide shows how to schedule automated tasks (PM generation, notifications, etc.) to run in the background.

## Overview

The following tasks should be automated:
- **PM Generation** (`generate_pm.php`) — creates PM work orders based on schedules (daily or more frequent)
- **PM Notifications** (`notify_pm.php`) — sends email reminders for upcoming/overdue PMs (daily)
- **Equipment Maintenance** — periodic cleanup or reporting (weekly/monthly)

## Linux/Unix (Crontab)

### Setup

1. Open your crontab editor:
   ```bash
   crontab -e
   ```

2. Add the following lines to schedule tasks (adjust paths and times as needed):

   **Generate PMs every day at 2 AM:**
   ```cron
   0 2 * * * /usr/bin/php /path/to/free-cmms/generate_pm.php >> /var/log/free-cmms-pm-gen.log 2>&1
   ```

   **Send PM notifications every morning at 8 AM:**
   ```cron
   0 8 * * * /usr/bin/php /path/to/free-cmms/notify_pm.php >> /var/log/free-cmms-notify.log 2>&1
   ```

   **Log rotation (keep last 30 days):**
   ```cron
   0 0 * * * find /var/log -name "free-cmms-*.log" -mtime +30 -delete
   ```

3. Save and exit. Cron will run these tasks automatically.

### Verify

List your crontab:
```bash
crontab -l
```

## Windows (Task Scheduler)

### Setup

1. **Open Task Scheduler:**
   - Press `Win + R`, type `taskschd.msc`, press Enter
   - Or: Admin Tools → Task Scheduler

2. **Create a new task:**
   - Right-click "Task Scheduler (Local)" → Create Basic Task
   - Name: "Maintenix - Generate PMs"
   - Trigger: Daily, 2:00 AM
   - Action: Program/script: `C:\php\php.exe`
   - Arguments: `C:\path\to\free-cmms\generate_pm.php`
   - Advanced options: ✓ Run with highest privileges, ✓ Run whether user is logged in or not
   - Repeat every 1 day, for 1 day
   - Enabled: Yes

3. **Create another task for notifications:**
   - Name: "Maintenix - Send PM Notifications"
   - Trigger: Daily, 8:00 AM
   - Action: Program/script: `C:\php\php.exe`
   - Arguments: `C:\path\to\free-cmms\notify_pm.php`
   - Same advanced options

### Verify

- Task Scheduler → View all tasks → find your tasks
- Test manually: Right-click task → Run
- Check output: Open `C:\free-cmms\notify_pm.php` and add logging if needed

## Adding Logging

To capture output and debug issues, add logging to the PHP scripts:

**Example: notify_pm.php with logging**
```php
$log_file = __DIR__ . '/logs/notify_pm.log';
@mkdir(__DIR__ . '/logs', 0755, true);
$msg = "[" . date('Y-m-d H:i:s') . "] Notifications sent.\n";
@file_put_contents($log_file, $msg, FILE_APPEND);
```

Create a `logs/` directory with write permissions:
```bash
mkdir -p logs
chmod 755 logs
```

## Testing

1. **Manual test of PM generation:**
   ```bash
   php generate_pm.php
   ```

2. **Manual test of notifications:**
   ```bash
   php notify_pm.php
   ```

3. **Check database for new work orders:**
   ```sql
   SELECT * FROM pm_instances ORDER BY created_date DESC LIMIT 5;
   ```

## Troubleshooting

- **No PMs generated:** Check `pm_schedules` table for active schedules with next_due <= today
- **Emails not sent:** Verify SMTP config in `config.inc.php` and check `log/` for errors
- **Permission denied:** Ensure PHP has read/write access to the project directory and `logs/` folder
- **Cron not running:** Check that crontab entry is valid with `crontab -l` and verify PHP path with `which php`
- **Windows Task not running:** Check Task Scheduler History, verify user has permissions, test script manually

## Production Best Practices

1. **Use SMTP email** instead of `mail()` (see `config.inc.php` and `notify_pm.php`)
2. **Rotate logs** to prevent disk space issues (see cron examples above)
3. **Monitor execution** by checking logs and database changes
4. **Test on schedule** to verify tasks run at the expected time
5. **Set up alerts** if a task fails (send admin an email on errors)
6. **Document your schedule** (keep a record of which tasks run when)

## Advanced: Error Handling in Scripts

Modify `generate_pm.php` or `notify_pm.php` to handle errors gracefully:

```php
<?php
include_once('config.inc.php');
$output = "Starting PM generation at " . date('Y-m-d H:i:s') . "...\n";

try {
    // ... your PM logic here ...
    $output .= "PM generation complete.\n";
} catch (Exception $e) {
    $output .= "ERROR: " . $e->getMessage() . "\n";
    // Send alert email to admin
    @mail($admincontact, "Maintenix Error", $output);
}

// Log output
@file_put_contents(__DIR__ . '/logs/pm_generation.log', $output, FILE_APPEND);

echo $output;
?>
```

---

**Last Updated:** February 2026
**For more help:** Check the Maintenix README and config documentation.
