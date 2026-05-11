CMMS Feature Updates
====================

This file documents the features added/changed to make the CMMS more professional and marketable.

New files / scripts:
- `migrate_add_features.php` — main migration adding columns and tables for SLA, attachments, materials, escalation
- `migrate_more.php` / `migrate_more2.php` — additional migrations (downtime table and QA fields)
- `add_material.php`, `delete_material.php` — endpoints to add/delete materials
- `add_downtime.php` — endpoint to add downtime entries
- `sla_escalate.php` — SLA breach detection and escalation script; schedule this via cron or Task Scheduler

UI changes:
- `work_order.php` now shows Impact/Urgency, SLA fields, materials list and quick-add form, planned vs actual hours, QA fields, and attachments upload
- `list.php` highlights overdue items in red (SLA/needed_date)

How to run migrations:
```powershell
php migrate_add_features.php
php migrate_more.php
php migrate_more2.php
```

Schedule SLA escalation (example cron):
```cron
*/15 * * * * php /path/to/free-cmms/sla_escalate.php
```

Notes:
- Ensure `attachments/` is writable by the webserver; the migration may create it when uploading files.
- Email notifications use PHP `mail()`; configure your mail transfer agent or adapt `sla_escalate.php` to use SMTP library.
