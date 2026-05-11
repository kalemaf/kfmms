# CMMS Production Deployment Guide

**Version:** 1.0  
**Last Updated:** May 5, 2026  
**Status:** 🟢 Production Ready

---

## Table of Contents
1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Infrastructure Setup](#infrastructure-setup)
3. [Database Configuration](#database-configuration)
4. [Backup & Recovery Setup](#backup--recovery-setup)
5. [HTTPS/TLS Configuration](#httpstls-configuration)
6. [Environment Configuration](#environment-configuration)
7. [Security Hardening](#security-hardening)
8. [Initial Data Load](#initial-data-load)
9. [Deployment Steps](#deployment-steps)
10. [Post-Deployment Verification](#post-deployment-verification)
11. [Monitoring & Maintenance](#monitoring--maintenance)
12. [Emergency Procedures](#emergency-procedures)

---

## Pre-Deployment Checklist

### System Requirements
- [ ] PHP 7.4+ installed
- [ ] SQLite support enabled (default in PHP)
- [ ] Apache 2.4+ with mod_rewrite enabled
- [ ] 2GB+ disk space for database and backups
- [ ] SSL certificate (self-signed or CA-signed)
- [ ] Cron daemon available for scheduled tasks

### Code Readiness
- [ ] All syntax validation passed (`php -l`)
- [ ] Database schema created
- [ ] Backup system tested
- [ ] Security hardening applied
- [ ] Role standardization complete

### Documentation Ready
- [ ] Database backup procedures documented
- [ ] Incident response plan created
- [ ] User account creation process tested
- [ ] HTTPS certificate deployed

---

## Infrastructure Setup

### 1. Server Preparation
```bash
# Create CMMS application directory
sudo mkdir -p /var/www/cmms
sudo chown -R www-data:www-data /var/www/cmms
sudo chmod 755 /var/www/cmms

# Create database directory
sudo mkdir -p /var/www/cmms/database
sudo chmod 700 /var/www/cmms/database

# Create backup directory
sudo mkdir -p /var/www/cmms/database/backups
sudo chmod 755 /var/www/cmms/database/backups

# Create logs directory
sudo mkdir -p /var/www/cmms/logs
sudo chmod 755 /var/www/cmms/logs

# Create temp session directory
sudo mkdir -p /var/www/cmms/sessions
sudo chmod 700 /var/www/cmms/sessions
```

### 2. Web Server Configuration (Apache)
```apache
# /etc/apache2/sites-available/cmms.conf
<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    
    DocumentRoot /var/www/cmms
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/yourdomain.crt
    SSLCertificateKeyFile /etc/ssl/private/yourdomain.key
    SSLCertificateChainFile /etc/ssl/certs/yourdomain.ca-bundle
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    
    # Enable mod_rewrite
    <Directory /var/www/cmms>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        
        <IfModule mod_rewrite.c>
            RewriteEngine On
        </IfModule>
        
        Require all granted
    </Directory>
    
    # Deny access to sensitive directories
    <Directory /var/www/cmms/database>
        Require all denied
    </Directory>
    
    <Directory /var/www/cmms/logs>
        Require all denied
    </Directory>
    
    # PHP Configuration
    <IfModule mod_php.c>
        php_value upload_max_filesize 10M
        php_value post_max_size 10M
        php_value max_execution_time 300
        php_value memory_limit 256M
        php_value session.save_path "/var/www/cmms/sessions"
    </IfModule>
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/cmms-error.log
    CustomLog ${APACHE_LOG_DIR}/cmms-access.log combined
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite cmms.conf
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl
sudo systemctl restart apache2
```

### 3. PHP Configuration
```ini
# /var/www/cmms/php.ini or /etc/php/7.4/fpm/php.ini (if using PHP-FPM)

; Security
display_errors = Off
log_errors = On
error_log = /var/www/cmms/logs/php-error.log

; Performance
max_execution_time = 300
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M

; Sessions
session.save_path = "/var/www/cmms/sessions"
session.gc_probability = 1
session.gc_divisor = 100
session.gc_maxlifetime = 3600
```

---

## Database Configuration

### 1. Initialize Database
```bash
cd /var/www/cmms
php setup_saas_db.php
php setup_role_management.php

# Verify database creation
ls -lh database/maintenix.db
```

### 2. Set Database Permissions
```bash
# Database file: readable/writable by web server only
sudo chmod 600 /var/www/cmms/database/maintenix.db
sudo chown www-data:www-data /var/www/cmms/database/maintenix.db

# Database directory: web server access only
sudo chmod 700 /var/www/cmms/database
sudo chown www-data:www-data /var/www/cmms/database
```

### 3. Verify Database Integrity
```bash
php backup_manager.php verify
```

Expected output:
```
[2026-05-05 15:07:36] [INFO] ✓ Database integrity check passed
✓ Database is healthy
```

---

## Backup & Recovery Setup

### 1. Create Initial Backup
```bash
cd /var/www/cmms
php backup_manager.php backup
```

### 2. Configure Automated Backups

**Option A: Using Cron (Linux/Unix)**
```bash
# Edit crontab
sudo crontab -e

# Add these lines:
# Daily backup at 2 AM
0 2 * * * /usr/bin/php /var/www/cmms/backup_manager.php backup

# Weekly integrity check (Sundays at 3 AM)
0 3 * * 0 /usr/bin/php /var/www/cmms/backup_manager.php verify

# Monthly cleanup (1st of month at 4 AM)
0 4 1 * * /usr/bin/php /var/www/cmms/backup_manager.php cleanup
```

**Option B: Using Windows Task Scheduler**
```
Task: CMMS Daily Backup
Trigger: Daily at 2:00 AM
Action: C:\PHP\php.exe C:\free-cmms 0.04\backup_manager.php backup

Task: CMMS Integrity Check
Trigger: Weekly on Sunday at 3:00 AM
Action: C:\PHP\php.exe C:\free-cmms 0.04\backup_manager.php verify

Task: CMMS Backup Cleanup
Trigger: Monthly on 1st at 4:00 AM
Action: C:\PHP\php.exe C:\free-cmms 0.04\backup_manager.php cleanup
```

### 3. Test Backup/Restore Cycle
```bash
# Create backup
php backup_manager.php list

# Restore from backup (if needed)
php backup_manager.php restore maintenix_2026-05-05_15-07-41.db

# Verify restored database
php backup_manager.php verify
```

---

## HTTPS/TLS Configuration

### 1. Obtain SSL Certificate

**Option A: Let's Encrypt (Free, Recommended)**
```bash
sudo apt-get install certbot python3-certbot-apache
sudo certbot certonly --apache -d yourdomain.com -d www.yourdomain.com
```

**Option B: Self-Signed Certificate (Development Only)**
```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/yourdomain.key \
  -out /etc/ssl/certs/yourdomain.crt
```

### 2. Update .env Configuration
```bash
# Edit .env file
nano /var/www/cmms/.env

# Change:
APP_URL=https://yourdomain.com

# Verify:
APP_ENV=production
SECURE_COOKIES=true
SAMESITE_COOKIES=Strict
```

### 3. Auto-Renewal (Let's Encrypt)
```bash
# Add to crontab for automatic renewal
0 3 1 * * /usr/bin/certbot renew --quiet && /usr/bin/systemctl reload apache2
```

---

## Environment Configuration

### 1. Configure .env File
```bash
# Copy from template
cp /var/www/cmms/.env.example /var/www/cmms/.env

# Edit production values
nano /var/www/cmms/.env
```

**Production .env:**
```env
APP_ENV=production
APP_URL=https://yourdomain.com
DB_TYPE=sqlite
DB_FILE=database/maintenix.db

# Security Settings
DISPLAY_ERRORS=false
DEBUG_MODE=false
DEVELOPER_BYPASS_LICENSE=false
SECURE_COOKIES=true
SAMESITE_COOKIES=Strict

# Email Configuration
SMTP_ENABLED=true
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_ADDRESS=noreply@yourdomain.com

# Payment Configuration
PAYMENT_PROVIDER=paypal
PAYPAL_CLIENT_ID=your_live_paypal_client_id
PAYPAL_SECRET=your_live_paypal_secret
PAYPAL_ENVIRONMENT=live
PAYPAL_WEBHOOK_ID=your_live_webhook_id
PAYMENT_NOTIFICATION_EMAIL=billing@yourdomain.com
```

### 2. Set .env File Permissions
```bash
sudo chmod 600 /var/www/cmms/.env
sudo chown www-data:www-data /var/www/cmms/.env
```

---

## Security Hardening

### 1. Firewall Rules
```bash
# Allow only necessary ports
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP (redirect to HTTPS)
sudo ufw allow 443/tcp   # HTTPS

# Enable firewall
sudo ufw enable
```

### 2. Disable Unnecessary Services
```bash
# Keep only required services running
sudo systemctl disable avahi-daemon
sudo systemctl disable bluetooth
```

### 3. File Permissions Review
```bash
# Correct permissions for production
sudo chmod 644 /var/www/cmms/*.php
sudo chmod 644 /var/www/cmms/.htaccess
sudo chmod 755 /var/www/cmms/app
sudo chmod 755 /var/www/cmms/libraries
sudo chmod 755 /var/www/cmms/inventory

# Database and backups (web server only)
sudo chmod 600 /var/www/cmms/database/maintenix.db
sudo chmod 700 /var/www/cmms/database/backups
```

### 4. Log Monitoring
```bash
# Create log monitoring script
tail -f /var/www/cmms/logs/php-error.log
tail -f /var/log/apache2/cmms-error.log
tail -f /var/log/apache2/cmms-access.log
```

---

## Initial Data Load

### 1. Create Admin User
```bash
php add_users.php
```

### 2. Load Sample Data (Optional)
```bash
php add_sample_equipment.php
```

### 3. Verify Data
```bash
php check_users.php
php check_current_roles.php
```

---

## Deployment Steps

### Step 1: Pre-Deployment
```bash
# 1. Backup current system (if migrating)
php backup_manager.php backup

# 2. Verify all syntax
php -l *.php

# 3. Run security verification
php verify_role_consistency.php
```

### Step 2: Code Deployment
```bash
# 1. Copy application files
sudo cp -r /path/to/local/cmms/* /var/www/cmms/

# 2. Set ownership
sudo chown -R www-data:www-data /var/www/cmms

# 3. Set permissions
sudo chmod 755 /var/www/cmms
find /var/www/cmms -type f -exec chmod 644 {} \;
find /var/www/cmms -type d -exec chmod 755 {} \;
```

### Step 3: Configuration
```bash
# 1. Copy and configure .env
sudo cp /var/www/cmms/.env.example /var/www/cmms/.env
sudo nano /var/www/cmms/.env  # Update with production values

# 2. Set permissions
sudo chmod 600 /var/www/cmms/.env
sudo chown www-data:www-data /var/www/cmms/.env
```

### Step 4: Database
```bash
# 1. Initialize database
cd /var/www/cmms
php setup_saas_db.php
php setup_role_management.php

# 2. Set database permissions
sudo chmod 600 /var/www/cmms/database/maintenix.db
sudo chown www-data:www-data /var/www/cmms/database/maintenix.db

# 3. Verify
php backup_manager.php verify
```

### Step 5: Backup System
```bash
# 1. Create initial backup
php backup_manager.php backup

# 2. Configure cron jobs (see Backup & Recovery Setup)
```

### Step 6: HTTPS/SSL
```bash
# 1. Obtain certificate (Let's Encrypt)
sudo certbot certonly --apache -d yourdomain.com

# 2. Configure Apache (see HTTPS/TLS Configuration)
sudo systemctl reload apache2
```

### Step 7: Final Verification
```bash
# 1. Check site accessibility
curl -I https://yourdomain.com

# 2. Verify HTTPS certificate
openssl s_client -connect yourdomain.com:443

# 3. Check logs for errors
tail -f /var/www/cmms/logs/php-error.log
```

---

## Post-Deployment Verification

### Immediate Tests (First Hour)

- [ ] **Homepage loads** - https://yourdomain.com
- [ ] **HTTPS certificate valid** - No browser warnings
- [ ] **Login page accessible** - https://yourdomain.com/auth.php
- [ ] **Admin user creation works** - Create test admin user
- [ ] **User management functional** - Add/edit/delete user
- [ ] **Role assignment works** - Assign all 5 roles
- [ ] **Backup system functional** - `php backup_manager.php backup`
- [ ] **Database integrity** - `php backup_manager.php verify`
- [ ] **Debug pages blocked** - Try accessing /check_users.php (should 403)
- [ ] **Error logging working** - Check `logs/php-error.log`

### First Day Tests

- [ ] **Email notifications** - Test SMTP configuration
- [ ] **Login rate limiting** - Attempt 5 failed logins (should lock)
- [ ] **Session timeout** - Sit idle for 1 hour, verify logout
- [ ] **Multi-tenant isolation** - Create 2 tenants, verify data separation
- [ ] **Work order creation** - Create and complete work orders
- [ ] **Spares tracking** - Add equipment spares, verify inventory
- [ ] **Audit logging** - Verify all actions logged
- [ ] **Backup restore** - Restore from backup, verify data
- [ ] **Report generation** - Generate sample reports
- [ ] **API connectivity** - Test any external integrations

### First Week Tests

- [ ] **Load testing** - Concurrent user load test (100+ users)
- [ ] **Backup rotation** - Verify old backups deleted after 30 days
- [ ] **Cron job execution** - Verify automated backups created
- [ ] **Performance** - Page load times under 2 seconds
- [ ] **Security scanning** - Run security audit tools
- [ ] **Log rotation** - Verify logs don't grow unbounded
- [ ] **Database size** - Monitor database file growth

---

## Monitoring & Maintenance

### Daily Checks
```bash
# 1. Check backup logs
tail -20 /var/www/cmms/logs/backup.log

# 2. Monitor error log
tail -20 /var/www/cmms/logs/php-error.log

# 3. Check database integrity
php backup_manager.php verify
```

### Weekly Tasks
- Review access logs for suspicious activity
- Check disk space for database/backups
- Verify automated backup completion
- Test login with multiple browsers
- Review user account activity

### Monthly Tasks
- Full security audit
- Review and update access controls
- Backup verification (restore test)
- Performance analysis and optimization
- Update third-party libraries/dependencies

### Quarterly Tasks
- Disaster recovery drill (full restore from backup)
- Security penetration testing
- User training and documentation update
- Infrastructure capacity planning

---

## Emergency Procedures

### Database Corruption
```bash
# 1. Verify corruption
php backup_manager.php verify

# 2. Restore latest good backup
php backup_manager.php restore maintenix_2026-05-05_15-07-41.db

# 3. Verify restoration
php backup_manager.php verify

# 4. Notify users of data loss window
```

### Lost Backup Files
```bash
# 1. Check backup directory
ls -la /var/www/cmms/database/backups/

# 2. List available backups
php backup_manager.php list

# 3. If no backups, enable recovery mode and restore from off-site backup
```

### SSL Certificate Expiration
```bash
# 1. Check expiration
openssl x509 -in /etc/ssl/certs/yourdomain.crt -noout -dates

# 2. Renew if needed
sudo certbot renew

# 3. Reload Apache
sudo systemctl reload apache2
```

### High Disk Usage
```bash
# 1. Check disk usage
df -h /var/www/cmms

# 2. Clean up old backups
php backup_manager.php cleanup

# 3. Check log file sizes
ls -lh /var/www/cmms/logs/

# 4. Archive/rotate logs if necessary
gzip /var/www/cmms/logs/*.log
```

### Website Not Responding
```bash
# 1. Check Apache status
sudo systemctl status apache2

# 2. Restart Apache
sudo systemctl restart apache2

# 3. Check error logs
tail -50 /var/www/cmms/logs/php-error.log
tail -50 /var/log/apache2/cmms-error.log

# 4. Check database connectivity
php backup_manager.php verify

# 5. Check file permissions
ls -la /var/www/cmms/database/
ls -la /var/www/cmms/.env
```

---

## Support & Escalation

### Resources
- **Documentation:** See README.md files in each directory
- **Logs:** `/var/www/cmms/logs/` directory
- **Backups:** `/var/www/cmms/database/backups/`
- **Error Log:** `tail -f /var/www/cmms/logs/php-error.log`

### Contact Support
- Email: support@efficraft.com
- Phone: +256 (xxx) xxx-xxxx
- Ticket System: https://support.efficraft.com

---

**Document Version:** 1.0  
**Last Updated:** May 5, 2026  
**Next Review:** May 5, 2027

---

## Appendix: Useful Commands

```bash
# Database operations
php backup_manager.php backup                          # Create backup
php backup_manager.php list                            # List backups
php backup_manager.php restore <filename>              # Restore backup
php backup_manager.php verify                          # Check integrity

# Security verification
php verify_role_consistency.php                        # Verify role setup
php check_users.php                                    # List all users
php check_current_roles.php                            # List all roles

# File management
find /var/www/cmms -type f -name "*.php" | wc -l      # Count PHP files
du -sh /var/www/cmms/database/                         # Database size
du -sh /var/www/cmms/database/backups/                 # Backups size

# Monitoring
ps aux | grep apache2                                  # Apache processes
ps aux | grep php                                      # PHP processes
netstat -tulpn | grep :443                             # HTTPS connections

# Log analysis
grep ERROR /var/www/cmms/logs/php-error.log            # Find errors
tail -f /var/www/cmms/logs/backup.log                  # Watch backup log
wc -l /var/www/cmms/logs/*.log                         # Count log entries
```

---

**🟢 Ready for Production Deployment**
