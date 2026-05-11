# CMMS System Administration Module
## Professional System Management for CMMS v0.04+

This module provides comprehensive system administration capabilities for the CMMS (Computerized Maintenance Management System), including automated backups, maintenance, monitoring, and audit trails.

## Features

### 🔄 Automated Operations
- **Daily Database Backups**: Automated full database backups with compression and verification
- **Database Maintenance**: Weekly optimization and analysis of database tables
- **Data Archival**: Monthly archival of records older than 1 year
- **System Health Monitoring**: Hourly health checks with metrics collection

### 📊 System Monitoring Dashboard
- Real-time system health metrics (CPU, memory, disk usage)
- Database performance monitoring
- Active connection tracking
- Backup status and history
- Maintenance schedule tracking

### 📋 Audit & Logging
- Comprehensive activity audit trail (who changed what, when)
- Error logging with detailed information
- System event logging
- Automated log rotation and cleanup

### 🛡️ Disaster Recovery
- Automated backup verification
- Recovery procedure documentation
- Backup retention policies (30 days)
- Emergency restore capabilities

## Installation & Setup

### 1. Database Setup
Execute the SQL schema file to create required tables:

```sql
-- Run this in MySQL/MariaDB
source system_admin_tables.sql;
```

### 2. Directory Structure
Ensure these directories exist and are writable by PHP:
- `backups/` - Database backup storage
- `logs/` - System and error logs

### 3. Automated Tasks Setup (Windows)
Run the PowerShell script to set up scheduled tasks:

```powershell
# Run as Administrator
.\setup_automated_tasks.ps1
```

This creates the following scheduled tasks:
- **CMMS_Daily_Backup**: Runs at 2:00 AM daily
- **CMMS_Weekly_Maintenance**: Runs at 3:00 AM every Sunday
- **CMMS_Monthly_Archival**: Runs at 4:00 AM on the 1st of each month
- **CMMS_Hourly_Health_Check**: Runs every hour starting at midnight

### 4. Manual Execution
You can also run maintenance tasks manually:

```bash
# Run specific task
php automated_maintenance.php backup
php automated_maintenance.php maintenance
php automated_maintenance.php archival
php automated_maintenance.php health_check

# Run all tasks
php automated_maintenance.php all
```

## File Structure

```
system_admin.php              # Main administration dashboard
system_admin_tables.sql       # Database schema
automated_maintenance.php     # Automated maintenance script
setup_automated_tasks.ps1     # Windows task scheduler setup
backups/                      # Backup storage directory
logs/                         # Log files directory
```

## Database Tables

### Core Tables
- `backup_history` - Backup operation records
- `maintenance_schedule` - Scheduled maintenance tasks
- `system_health_metrics` - System performance metrics
- `error_logs` - System error tracking
- `system_activity_logs` - User activity audit trail

### Archive Tables
- `work_orders_archive` - Archived completed work orders
- `audit_logs_archive` - Archived audit log entries

## Dashboard Features

### Backup Management
- View backup history and status
- Manual backup execution
- Backup file management
- Automated cleanup of old backups

### Database Maintenance
- Table optimization and analysis
- Maintenance schedule management
- Performance monitoring
- Index maintenance

### Data Archival
- Automatic archival of old data
- Archive statistics and reporting
- Data retention policies
- Archive restoration capabilities

### System Health
- Real-time metrics dashboard
- Alert thresholds and notifications
- Historical performance data
- System resource monitoring

### Audit Trail
- Complete activity logging
- User action tracking
- Change history
- Security event monitoring

## Security Considerations

### Database Credentials
- Store database credentials securely
- Use environment variables for sensitive data
- Implement proper access controls

### File Permissions
- Restrict access to backup and log directories
- Use appropriate file permissions (755 for directories, 644 for files)
- Regular security audits

### Backup Security
- Encrypt sensitive backup data
- Secure backup storage locations
- Implement backup verification
- Regular backup testing

## Monitoring & Alerts

### System Alerts
- Database connection issues
- Backup failures
- High resource usage
- Disk space warnings

### Error Handling
- Comprehensive error logging
- Automatic error recovery
- Alert notifications
- Error trend analysis

## Troubleshooting

### Common Issues

**Backup Failures**
- Check database credentials
- Verify mysqldump installation
- Check disk space in backup directory
- Review backup logs

**Task Scheduler Issues**
- Run PowerShell as Administrator
- Check Task Scheduler service status
- Verify PHP path in system PATH
- Review task execution logs

**Permission Errors**
- Check PHP write permissions for directories
- Verify database user privileges
- Check file system permissions

### Log Files
- `logs/automated_maintenance.log` - Maintenance script logs
- `logs/error.log` - System error logs
- Windows Event Viewer - Task scheduler logs

## Performance Optimization

### Database Tuning
- Regular table optimization
- Index maintenance
- Query performance monitoring
- Connection pool management

### System Resources
- Monitor memory usage
- CPU utilization tracking
- Disk I/O performance
- Network connectivity

## Backup & Recovery

### Backup Strategy
- Daily full backups
- Compressed backup files
- Automatic verification
- 30-day retention policy

### Recovery Procedures
1. Identify the backup file to restore
2. Stop the CMMS application
3. Restore database from backup
4. Verify data integrity
5. Restart the application

### Emergency Contacts
- System administrator contact information
- Database administrator details
- Backup storage location access

## Maintenance Schedule

| Task | Frequency | Time | Description |
|------|-----------|------|-------------|
| Database Backup | Daily | 2:00 AM | Full database backup with compression |
| Database Maintenance | Weekly | 3:00 AM Sunday | Table optimization and analysis |
| Data Archival | Monthly | 4:00 AM 1st | Archive records older than 1 year |
| Health Check | Hourly | Every hour | System health monitoring |

## API Integration

The system administration module can be integrated with external monitoring systems:

- REST API endpoints for metrics
- Webhook notifications for alerts
- Integration with monitoring tools (Nagios, Zabbix)
- Email/SMS alert systems

## Future Enhancements

- Multi-server deployment support
- Advanced analytics and reporting
- Machine learning-based anomaly detection
- Automated scaling capabilities
- Cloud backup integration

## Support

For technical support or questions about the system administration module:

1. Check the troubleshooting section
2. Review log files for error details
3. Contact system administrator
4. Refer to CMMS documentation

## Version History

- **v1.0** - Initial release with core system administration features
- Automated backups, maintenance, and monitoring
- Comprehensive audit trail and error logging
- Windows Task Scheduler integration

---

*This documentation is part of the CMMS v0.04+ system administration module. For additional information, refer to the main CMMS documentation.*