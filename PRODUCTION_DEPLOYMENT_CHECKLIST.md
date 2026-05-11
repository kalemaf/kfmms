# Production Deployment & Operations Checklist

This guide consolidates secure deployment, database migration/versioning, logging, monitoring, backup, restore, and failover practices for the CMMS application.

## 1. Preparation

- Copy `.env.example` to `.env` and set production values.
- Confirm the following environment variables are set:
  - `DB_TYPE` (`mysql` or `sqlite`)
  - `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` for MySQL
  - `DB_FILE` for SQLite
  - `FORCE_HTTPS=true`
  - `SESSION_COOKIE_SECURE=true`
  - `SESSION_COOKIE_SAMESITE=Strict`
  - `SECURITY_FRAME_OPTIONS=SAMEORIGIN`
  - `APP_SECRET=long-random-secret`
  - `DEBUG_MODE=false`
  - `DISPLAY_ERRORS=false`
  - `ENABLE_DEBUG_PAGES=false`
  - `DEVELOPER_BYPASS_LICENSE=false`
  - `SESSION_SAVE_PATH=./sessions`
  - `LOG_DIR=./logs`
  - `BACKUP_DIR=./backups`
  - `LOG_RETENTION_DAYS=30`
  - `BACKUP_RETENTION_DAYS=30`
- Keep `.env` and all secret files out of version control.
- Ensure web server and PHP process run with least privilege on `sessions`, `logs`, `uploads`, and `backups` directories.
- Install any required system packages for database tools if using MySQL backups/restores (`mysqldump`, `mysql`).

## 2. Database migration and versioning

- The application now stores migration history in the `schema_migrations` table.
- Versioned SQL migrations are located in `migrations/` using numeric prefixes.
- Apply pending migrations from the command line:

```bash
php migrations/run_pending_migrations.php
```

- Preview pending migrations without applying them:

```bash
php migrations/run_pending_migrations.php --dry-run
```

- Only numeric-versioned migrations are automatically tracked by this runner. Manual conversion scripts remain separate.

## 3. Backup and restore strategy

- Back up the database before any production deployment or schema change.
- Use the helper script:

```bash
php tools/db_backup.php
```

- Optionally specify a file name:

```bash
php tools/db_backup.php --file=predeploy_backup.sql
```

- Restore only after careful validation and with explicit confirmation:

```bash
php tools/db_restore.php --file=backup_filename.sql
```

- For SQLite, backups are file copies. For MySQL, helpers use secure environment credentials and `mysqldump`/`mysql`.
- Retain backups for a minimum period and store copies offsite or in a separate storage tier.
- Regularly validate restore procedures in a staging environment.

## 4. Logging and monitoring

- Application logs now use the configurable log directory: `LOG_DIR` (`./logs` by default).
- Log retention is configured with `LOG_RETENTION_DAYS`.
- The application rotates older `.log` files automatically based on retention.
- Review and monitor:
  - `logs/app.log`
  - `logs/security.log` (future use)
  - `logs/audit.log` (future use)
  - `server.log`
- Use external monitoring for:
  - uptime / health check
  - PHP errors and crash alerts
  - disk utilization for `logs`, `sessions`, and `backups`
  - database connectivity and slow query alerts
- Verify system health with `health_check.php` after deployment.

## 5. Deployment checklist

Before deployment:

1. Confirm `.env` values and secure file permissions.
2. Create a database backup with `php tools/db_backup.php`.
3. Run pending migrations with `php migrations/run_pending_migrations.php`.
4. Verify the application is configured for HTTPS and secure session cookies.
5. Ensure `DEBUG_MODE=false` and `DISPLAY_ERRORS=false`.

After deployment:

1. Validate key pages and workflows.
2. Run `health_check.php` and confirm essential checks pass.
3. Review the latest application log entries.
4. Confirm backup retention and offsite copy status.
5. Document any deployment anomalies and next steps.

## 6. Failover guidance

- Maintain a recent backup copy for immediate restore.
- For database failover, use a managed replica or restore from the latest backup.
- Keep file attachments and upload directories replicated or backed up.
- If the web application becomes unavailable:
  - redirect traffic to a standby environment if available
  - restore the database from backup and redeploy code to a new host
- Test failover and recovery procedures periodically in a non-production environment.

## 7. Notes

- Existing docs such as `INTEGRATION_DEPLOYMENT_CHECKLIST.md` and `DEPLOYMENT_CHECKLIST_PROFESSIONAL_PO.md` remain useful for feature-specific deployments.- A dedicated QA checklist is available at `QA_TESTING_CHECKLIST.md` to validate authentication, license activation, purchase requests, inventory, and work order workflows.
- The repository now includes automated validation via `php tests/run_tests.php` and a GitHub Actions workflow at `.github/workflows/php-ci.yml`.- `PRODUCTION_DEPLOYMENT_CHECKLIST.md` is the unified, high-level operational guide for production readiness.
