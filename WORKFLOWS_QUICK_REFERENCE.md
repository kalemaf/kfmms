# Advanced Workflows - Quick Reference Card

## 📋 File Locations

| File | Purpose |
|------|---------|
| `WorkflowEngine.php` | Core workflow execution engine |
| `CustomFieldManager.php` | Dynamic field management |
| `SOPManager.php` | SOP definition and linking |
| `workflow_management.php` | Web dashboard (access `/workflow_management.php`) |
| `ADVANCED_WORKFLOWS_GUIDE.md` | Complete documentation |
| `test_advanced_workflows.php` | System verification test |

---

## 🚀 Quick Start

### Initialize Classes
```php
require 'WorkflowEngine.php';
require 'CustomFieldManager.php';
require 'SOPManager.php';

$engine = new WorkflowEngine($connection, $_SESSION['user']);
$fields = new CustomFieldManager($connection);
$sops = new SOPManager($connection);
```

### Start a Workflow
```php
$result = $engine->initiate_workflow(
    $workflow_id = 1,
    $reference_type = 'work_order',
    $reference_id = 123
);
```

### Get Pending Approvals
```php
$pending = $engine->get_pending_approvals($_SESSION['user']);
foreach ($pending as $approval) {
    echo $approval['workflow_name'] . " - " . $approval['step_name'];
}
```

### Approve a Step
```php
$result = $engine->approve_step($approval_id, "Approved");
if ($result['success']) {
    echo "Approved successfully";
}
```

---

## 🎯 WorkflowEngine Methods

| Method | Purpose | Parameters |
|--------|---------|-----------|
| `initiate_workflow()` | Start new workflow | workflow_id, reference_type, reference_id |
| `approve_step()` | Approve workflow step | approval_id, comments |
| `reject_step()` | Reject and route back | approval_id, reason |
| `delegate_approval()` | Delegate to another user | approval_id, delegate_to, reason |
| `evaluate_conditions()` | Process conditional routing | instance_id, field_values |
| `get_workflow_status()` | Get current status | instance_id |
| `get_pending_approvals()` | Get user's approvals | username |
| `get_workflow_history()` | Get audit trail | instance_id |

---

## 🛠️ CustomFieldManager Methods

| Method | Purpose | Parameters |
|--------|---------|-----------|
| `get_fields()` | Get fields for context | workflow_id, equipment_type_id, equipment_type_name |
| `create_field()` | Create new field | field_data array |
| `update_field()` | Update field | field_id, field_data |
| `add_field_option()` | Add select option | field_id, label, value, sort_order |
| `validate_field_value()` | Validate input | field_definition, value |
| `render_field()` | Generate HTML | field_definition, value |
| `delete_field()` | Delete field | field_id |

---

## 📚 SOPManager Methods

| Method | Purpose | Parameters |
|--------|---------|-----------|
| `create_sop()` | Create new SOP | sop_data array |
| `get_sop()` | Retrieve SOP | sop_id |
| `get_sops_for_equipment()` | Get SOPs by type | equipment_type_id or equipment_type_name |
| `link_sop_to_workflow()` | Attach to workflow | workflow_step_id, sop_id, is_required, acknowledgement |
| `get_sops_for_workflow_step()` | Get step's SOPs | workflow_step_id |
| `link_sop_to_task()` | Attach to task | sop_id, task_id, task_type, description |
| `get_tasks_for_sop()` | Get task list | sop_id |
| `render_sop_display()` | Generate HTML | sop_id, show_acknowledgement |
| `delete_sop()` | Delete SOP | sop_id |

---

## 📊 Common Queries

### Get Workflow Progress
```php
$status = $engine->get_workflow_status($instance_id);
echo "Current: " . $status['step_name'];
echo "Status: " . $status['workflow_status'];
echo "By: " . $status['current_approver_username'];
```

### Get All Approvals for Instance
```sql
SELECT * FROM workflow_approvals 
WHERE workflow_instance_id = ? 
ORDER BY approval_order;
```

### Get Overdue Approvals
```sql
SELECT * FROM workflow_instances wi
JOIN workflow_approvals wa ON wi.id = wa.workflow_instance_id
JOIN workflow_steps ws ON wi.current_step_id = ws.id
WHERE wa.approval_status = 'pending'
AND DATE_ADD(wa.created_at, INTERVAL ws.time_limit_hours HOUR) < NOW();
```

---

## 🎨 Database Tables Summary

### Workflow Tables
- `workflow_definitions` - Workflow templates (name, code, type)
- `workflow_steps` - Workflow steps (sequence, type, time limits)
- `approval_chains` - Approvers (user/group/role, order)
- `workflow_conditions` - Conditional routing rules
- `workflow_instances` - Active/completed workflows
- `workflow_approvals` - Individual approval records
- `workflow_history` - Audit trail entries

### Field Tables
- `custom_field_definitions` - Field definitions
- `custom_field_options` - Select/radio options

### SOP Tables
- `sop_definitions` - SOP records
- `sop_task_links` - SOP tasks
- `workflow_sop_mappings` - SOP-workflow links

### Tracking Tables
- `workflow_escalations` - Escalation records
- `workflow_delegations` - Delegation records

---

## 🔍 Debugging Tips

### Enable SQL Logging
```php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
```

### Check Workflow Status
```php
$status = $engine->get_workflow_status($instance_id);
echo json_encode($status, JSON_PRETTY_PRINT);
```

### View Workflow History
```php
$history = $engine->get_workflow_history($instance_id);
foreach ($history as $entry) {
    echo "{$entry['action_type']} by {$entry['action_by']}\n";
}
```

### Validation Results
```php
$field = $fields->get_fields(...)[0];
$result = $fields->validate_field_value($field, $_POST['value']);
if (!$result['valid']) {
    echo "Error: " . $result['error'];
}
```

---

## 🔧 Field Types Reference

```
Text Types:    text, textarea
Numbers:       number, currency, percentage
Dates:         date, datetime
Selection:     select, radio, checkbox
Files:         file
```

## 🔐 Security Notes

- Always validate custom field input
- Use prepared statements (PSes used throughout)
- Check user permissions before approval
- Log all workflow actions
- Validate field values server-side
- Sanitize HTML in SOP content

---

## 📞 Support

- **Full Guide**: `ADVANCED_WORKFLOWS_GUIDE.md`
- **Implementation Details**: `ADVANCED_WORKFLOWS_IMPLEMENTATION_SUMMARY.md`
- **Test System**: `php test_advanced_workflows.php`
- **Dashboard**: `/workflow_management.php`

---

**Last Updated:** March 8, 2026  
**Version:** 2.0
