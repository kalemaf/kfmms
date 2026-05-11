# 🚀 KFMMS Multi-Tenant Deployment Checklist

## Pre-Deployment Phase

### Database Migration
- [ ] Backup current database before migration
  ```bash
  # For SQLite
  cp database/maintenix.db database/maintenix.db.backup
  
  # For MySQL
  mysqldump -u root -p kfmms > kfmms_backup.sql
  ```

- [ ] Run migration script
  ```bash
  php migrations/run_multi_tenant_migration.php
  ```

- [ ] Verify migration completed without errors

- [ ] Check all tables have `tenant_id` column
  ```sql
  SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_NAME = 'work_orders' AND COLUMN_NAME = 'tenant_id';
  ```

- [ ] Verify `companies` table created successfully
  ```sql
  SELECT COUNT(*) FROM companies;
  ```

### Data Migration (Existing Companies)
- [ ] Migrate existing company data to `companies` table
  - If you have existing companies, assign them company_id = 1, 2, etc.
  - Update all existing records to have the correct `tenant_id`

- [ ] Migrate existing users
  ```sql
  -- Assign all existing users to a company
  UPDATE users SET tenant_id = 1 WHERE tenant_id = 0;
  ```

- [ ] Assign tenant_id to all existing data
  ```sql
  UPDATE work_orders SET tenant_id = 1 WHERE tenant_id = 0;
  UPDATE equipment SET tenant_id = 1 WHERE tenant_id = 0;
  UPDATE inventory SET tenant_id = 1 WHERE tenant_id = 0;
  ```

### Code Updates
- [ ] Create `/app` directory structure
  ```
  /app
  ├── /Middleware → TenantMiddleware.php
  ├── /Models → BaseModel.php + specific models
  ├── /Controllers → Controllers for each entity
  ├── AuthenticationManager.php
  └── CompanyService.php
  ```

- [ ] Create `/migrations` directory with migration scripts

- [ ] Update `config.inc.php` to include middleware
  ```php
  require_once __DIR__ . '/app/Middleware/TenantMiddleware.php';
  require_once __DIR__ . '/app/AuthenticationManager.php';
  ```

### Configuration
- [ ] Create `.env` file with multi-tenant settings
  ```env
  DB_TYPE=sqlite
  ALLOW_PUBLIC_REGISTRATION=false
  MAX_COMPANIES=unlimited
  ```

- [ ] Set up logging for tenant isolation violations
  ```php
  // Log when tenant context missing
  error_reporting(E_ALL);
  ini_set('log_errors', 1);
  ini_set('error_log', 'logs/errors.log');
  ```

- [ ] Create `/storage/uploads` directory with proper permissions
  ```bash
  mkdir -p storage/uploads
  chmod 755 storage/uploads
  ```

---

## Core Files Verification

### Required Files Created
- [ ] `app/Middleware/TenantMiddleware.php`
- [ ] `app/BaseModel.php`
- [ ] `app/Models/WorkOrder.php`
- [ ] `app/Models/Equipment.php`
- [ ] `app/Models/Inventory.php`
- [ ] `app/Controllers/WorkOrderController.php`
- [ ] `app/Controllers/EquipmentController.php`
- [ ] `app/AuthenticationManager.php`
- [ ] `app/CompanyService.php`
- [ ] `migrations/multi_tenant_schema.php`
- [ ] `migrations/run_multi_tenant_migration.php`
- [ ] `register.php` (new)
- [ ] `login_multi_tenant.php` (new)
- [ ] `MULTI_TENANT_IMPLEMENTATION_GUIDE.md`

### Existing Files to Update
- [ ] `config.inc.php` - Add middleware includes
- [ ] `auth.php` - Update login logic
- [ ] `index.php` - Update to use new authentication
- [ ] `dashboard.php` - Add tenant verification
- [ ] All API endpoints - Add tenant filtering

---

## Testing Phase

### Unit Tests
- [ ] Test TenantMiddleware
  ```php
  $tenant_id = tenant();
  assert($tenant_id == 1);
  ```

- [ ] Test BaseModel filtering
  ```php
  $model = new WorkOrder($connection, $db_type);
  $orders = $model->all();
  // Verify all have tenant_id = 1
  ```

- [ ] Test Company registration
  ```php
  $service = new CompanyService($connection, $db_type);
  $result = $service->register(['name' => 'Test Co', 'email' => 'test@co.com']);
  assert($result['success'] == true);
  ```

- [ ] Test Authentication
  ```php
  $auth = new AuthenticationManager($connection, $db_type);
  $result = $auth->authenticate('user@company.com', 'password');
  assert($result['success'] == true);
  ```

### Integration Tests
- [ ] Register first company
  - [ ] Verify company_id created
  - [ ] Verify storage directory created
  - [ ] Verify admin user created with correct tenant_id

- [ ] Create second company
  - [ ] Verify separate storage directory
  - [ ] Verify data isolation

- [ ] Test login as User A
  - [ ] Verify can only see Company A data
  - [ ] Verify cannot access Company B data

- [ ] Test login as User B
  - [ ] Verify can only see Company B data
  - [ ] Verify cannot access Company A data

- [ ] Test data endpoints
  ```bash
  # Get work orders (only Company A's)
  curl -H "Cookie: PHPSESSID=..." http://localhost/api/work_orders.php
  ```

### Security Tests
- [ ] Test unauthorized access
  ```bash
  curl http://localhost/api/work_orders.php
  # Should return 401 Unauthorized
  ```

- [ ] Test cross-tenant access attempt
  ```php
  // Simulate user from company 1 trying to access company 2's data
  $_SESSION['tenant_id'] = 1;
  $model = new WorkOrder($connection, $db_type);
  $data = $model->find(100); // Work order from company 2
  // Should return null or throw error
  ```

- [ ] Test SQL injection
  ```php
  // Verify parameterized queries are used everywhere
  grep -r "SELECT.*\$" app/ | grep -v "?" 
  # Should find nothing
  ```

- [ ] Test session hijacking
  - [ ] Verify CSRF tokens on forms
  - [ ] Verify session timeout
  - [ ] Verify secure session headers

---

## Performance Optimization

### Database Indexes
- [ ] Verify indexes created on tenant_id
  ```sql
  SHOW INDEX FROM work_orders;
  -- Should show idx_tenant_id
  ```

- [ ] Add composite indexes
  ```sql
  CREATE INDEX idx_tenant_status ON work_orders(tenant_id, status);
  CREATE INDEX idx_tenant_user ON users(tenant_id, email);
  ```

- [ ] Monitor query performance
  ```sql
  EXPLAIN SELECT * FROM work_orders WHERE tenant_id = 1;
  ```

### Caching Strategy
- [ ] Set up caching for tenant context
- [ ] Cache company settings
- [ ] Cache user roles

### Load Testing
- [ ] Test with 100 companies
- [ ] Test with 1000 users
- [ ] Measure response times
- [ ] Monitor resource usage

---

## Production Deployment

### Environment Setup
- [ ] Set environment to production
  ```env
  APP_ENV=production
  DEBUG=false
  ```

- [ ] Enable HTTPS
  ```php
  // Force HTTPS
  if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
      header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
      exit;
  }
  ```

- [ ] Configure security headers
  ```php
  header("X-Content-Type-Options: nosniff");
  header("X-Frame-Options: DENY");
  header("X-XSS-Protection: 1; mode=block");
  ```

- [ ] Set up firewalls
  - [ ] Whitelist admin IPs
  - [ ] Rate limit login attempts
  - [ ] Enable WAF (Web Application Firewall)

### Backup & Recovery
- [ ] Set up automated daily backups
  ```bash
  # Daily backup script
  0 2 * * * /usr/local/bin/backup_kfmms.sh
  ```

- [ ] Test backup restore
  - [ ] Verify backup integrity
  - [ ] Practice restoring from backup

- [ ] Document disaster recovery procedure

### Monitoring & Logging
- [ ] Set up error logging
  - [ ] Log all authentication attempts
  - [ ] Log data access
  - [ ] Log admin actions

- [ ] Set up performance monitoring
  - [ ] Monitor database query times
  - [ ] Monitor disk usage
  - [ ] Monitor memory usage

- [ ] Set up alerts
  - [ ] Alert on failed logins
  - [ ] Alert on errors
  - [ ] Alert on resource limits

### Documentation
- [ ] Document API endpoints
- [ ] Document deployment process
- [ ] Document troubleshooting guide
- [ ] Document admin procedures

---

## Post-Deployment

### Monitoring
- [ ] Monitor for errors in logs
- [ ] Check database performance
- [ ] Monitor user activity
- [ ] Monitor resource usage

### User Onboarding
- [ ] Train admins on new system
- [ ] Create user documentation
- [ ] Set up support process
- [ ] Monitor user feedback

### Optimization
- [ ] Analyze database query logs
- [ ] Optimize slow queries
- [ ] Add missing indexes
- [ ] Adjust caching strategy

---

## Rollback Plan

If deployment fails:

### Step 1: Stop the Application
```bash
# If using PHP development server
kill -9 $(lsof -t -i:8000)

# If using Apache/Nginx
sudo systemctl stop nginx
```

### Step 2: Restore Database
```bash
# For SQLite
cp database/maintenix.db.backup database/maintenix.db

# For MySQL
mysql -u root -p kfmms < kfmms_backup.sql
```

### Step 3: Restore Code
```bash
git revert <commit-hash>
# OR
cp -r backup_directory/* .
```

### Step 4: Verify
```bash
php index.php
# Should work with old system
```

---

## Success Criteria

✅ All migration scripts completed without errors  
✅ No data loss occurred  
✅ All tests passed (unit, integration, security)  
✅ Login works for first company  
✅ Register works for new companies  
✅ Data isolation verified  
✅ Performance acceptable  
✅ Security tests passed  
✅ Monitoring and alerts working  
✅ Documentation complete  
✅ Team trained and ready  

---

## Support & Troubleshooting

### Common Issues

**Issue: "Undefined tenant_id in columns"**
- Solution: Run migration script again
- Verify: `php migrations/run_multi_tenant_migration.php`

**Issue: "Users can see other companies' data"**
- Solution: Check all queries have `WHERE tenant_id = ?`
- Verify: Search codebase for raw SELECT statements
- Fix: Convert to use BaseModel

**Issue: "Login fails for existing users"**
- Solution: Verify users have tenant_id assigned
- Check: `SELECT COUNT(*) FROM users WHERE tenant_id = 0;`
- Fix: `UPDATE users SET tenant_id = 1 WHERE tenant_id = 0;`

**Issue: "Performance degradation"**
- Solution: Add indexes on tenant_id columns
- Check: `SHOW INDEX FROM work_orders;`
- Verify: Query execution plan with EXPLAIN

---

## Contact & Support

For issues during deployment:
1. Check logs in `/logs/`
2. Review error messages carefully
3. Consult troubleshooting guide above
4. Contact development team with:
   - Error log output
   - Database type (SQLite/MySQL)
   - Steps to reproduce
   - Current system state

---

**Deployment Status:** 🟢 Ready to Deploy
**Last Updated:** April 2026
**Version:** KFMMS 2.0 Multi-Tenant
