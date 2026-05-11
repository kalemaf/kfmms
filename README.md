# KFMMS / Free CMMS

## Release-ready Deployment Guide

This repository contains the KFMMS application and all deployment support files. The top-level `README.md` is now the central release-ready guide for production setup, security, and operational links.

### 1. Quickstart

1. Copy `.env.example` to `.env`.
2. Update `.env` with your environment-specific values.
3. Confirm the following production settings:
   - `APP_ENV=production`
   - `APP_SECRET=long-random-secret`
   - `FORCE_HTTPS=true`
   - `SESSION_COOKIE_SECURE=true`
   - `SESSION_COOKIE_SAMESITE=Strict`
   - `DEBUG_MODE=false`
   - `DISPLAY_ERRORS=false`
   - `ENABLE_DEBUG_PAGES=false`
   - `DEVELOPER_BYPASS_LICENSE=false`
4. Ensure your web server document root points at this repository and that PHP can write to `sessions/`, `logs/`, `backups/`, and `uploads/`.
5. Run schema migrations before first use:
   - `php migrations/run_pending_migrations.php`

### 2. Required Environment Variables

- `DB_TYPE` (`mysql` or `sqlite`) — internal SQLite is supported and is now the default.
- `DB_HOST`
- `DB_PORT`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`
- `DB_FILE` (for SQLite)
- `SESSION_SAVE_PATH`
- `SMTP_ENABLED`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USER`
- `SMTP_PASS`
- `SMTP_SECURE`
- `SMTP_FROM_EMAIL`
- `SMTP_FROM_NAME`
- `APP_URL`
- `APP_ENV=production`
- `APP_SECRET` (long random value used for production environment integrity checks)
- `PAYMENT_PROVIDER` (`stripe` or `manual`)
- `STRIPE_SECRET_KEY`
- `STRIPE_PUBLISHABLE_KEY`
- `STRIPE_WEBHOOK_SECRET`
- `ENABLE_DEBUG_PAGES=false`

### 3. Production Safety Rules

- Never run production with `DEBUG_MODE=true`.
- Never enable `DEVELOPER_BYPASS_LICENSE=true` in production.
- Disable all debug utilities by setting `ENABLE_DEBUG_PAGES=false`.
- Use `APP_ENV=production` for live deployments.
- Keep `.env` out of source control.

### 4. Gate Development Utilities

The application now denies access to dev/test pages such as:

- `session_debug.php`
- `server_test.php`
- `test.php`

These pages are only available when `ENABLE_DEBUG_PAGES=true`, when `DEBUG_MODE=true`, or when `APP_ENV` is not `production`.

### 5. Core Documentation Index

Use these release and operational docs first:

- `PRODUCTION_DEPLOYMENT_CHECKLIST.md` - production readiness checklist
- `QA_TESTING_CHECKLIST.md` - QA validation steps
- `SYSTEM_ADMIN_README.md` - administration and monitoring details
- `ROLE_MANAGEMENT_README.md` - user/role/license administration
- `INTEGRATION_AND_API_GUIDE.md` - integration and webhook guide

### 6. Operations and Support

- Backup and restore: `php tools/db_backup.php`, `php tools/db_restore.php`
- Run tests: `php tests/run_tests.php`
- Validate syntax: `php -l <file>` or `php tools/lint_all.php`
- Monitor logs in the `logs/` directory.

### 7. Deployment Notes

- For hosted deployments, set environment variables at the platform level instead of relying on `.env`.
- If using HTTPS, confirm `APP_URL` uses `https://` and `FORCE_HTTPS=true`.
- Confirm `SESSION_COOKIE_SECURE=true` in production.

### 8. Help and Support

If you need targeted troubleshooting, review the markdown guides in the repository before changing production configuration.
