SLA Escalation scheduler
=========================

This project includes `sla_escalate.php` which scans work orders and logs/sends escalations. Use one of the helper options below to schedule it.

Windows (Task Scheduler)
- Edit `create_sla_task.ps1` or run it and enter the path to `php.exe` and your application folder. It will create a task named `Maintenix_SLA_Escalate` that runs every 15 minutes.
- Or use the `run_sla.bat` file in the app root and schedule that batch in Task Scheduler.

Linux / cron
- Add a cron entry to run every 15 minutes (edit path to php and app directory):

```bash
*/15 * * * * /usr/bin/php -f /path/to/maintenix/sla_escalate.php >> /var/log/freecmms/sla_escalate.log 2>&1
```

Notes
- Ensure the PHP executable used has mysqli and required extensions.
- If you want email notifications, configure SMTP in `config.inc.php` and enable PHPMailer.
- The scheduler will write to the `work_order_escalations` table and `audit_logs` for actions taken.
