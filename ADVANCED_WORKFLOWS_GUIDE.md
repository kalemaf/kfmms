# Advanced Workflows Implementation Guide
## CMMS v0.04+ - Complex Approval Chains, Conditional Routing, Custom Fields & SOP Management

---

## Table of Contents
1. [Overview](#overview)
2. [Features](#features)
3. [Database Setup](#database-setup)
4. [Core Components](#core-components)
5. [Workflow Management](#workflow-management)
6. [Custom Fields](#custom-fields)
7. [SOP Management](#sop-management)
8. [Approval Chains](#approval-chains)
9. [Conditional Routing](#conditional-routing)
10. [API Reference](#api-reference)
11. [Examples](#examples)
12. [Best Practices](#best-practices)

---

## Overview

The Advanced Workflows system provides a comprehensive framework for managing complex business processes in CMMS. It supports:

- **Multi-step approval chains** with parallel and sequential approvers
- **Conditional routing logic** based on field values
- **Dynamic custom fields** per equipment type and workflow
- **Standard Operating Procedures (SOPs)** linked to workflow steps
- **Complete audit trail** with workflow history and escalations
- **Workflow status dashboards** for real-time monitoring

---

## Features

### 1. **Complex Approval Chains**
- Sequential and parallel approval steps
- Multiple approvers per step with configurable rules
- Approval delegation and reassignment
- Approval time limits with automatic escalation
- Rejection workflows with return routing

### 2. **Conditional Routing**
- Route workflows based on field values
- Support for multiple conditions (AND/OR logic)
- Dynamic step routing based on work order properties
- Equipment type-specific routing

### 3. **Custom Field Definitions**
- Create fields specific to equipment types
- Support for multiple field types:
  - Text, Textarea
  - Number, Date, DateTime
  - Select, Radio, Checkbox
  - File Upload
  - Currency, Percentage
- Field validation (required, regex, min/max values)
- Field dependencies and conditional visibility

### 4. **SOP (Standard Operating Procedure) Linking**
- Link SOPs to workflow steps
- Track SOP acknowledgements
- Reference tasks within SOPs
- Multiple SOPs per workflow step
- Required vs. optional SOP reading

### 5. **Workflow Status Dashboards**
- Real-time workflow status tracking
- Approver workload visibility
- Workflow instance history
- Escalation tracking
- Performance metrics

---

## Database Setup

### Execute the Schema

```bash
mysql -u your_username -p your_database < workflow_advanced_schema.sql
```

### Key Tables

| Table | Purpose |
|-------|---------|
| `workflow_definitions` | Define workflow templates |
| `workflow_steps` | Define steps within workflows |
| `approval_chains` | Configure approvers for steps |
| `workflow_conditions` | Define conditional routing rules |
| `custom_field_definitions` | Define dynamic form fields |
| `workflow_instances` | Track active/completed workflows |
| `workflow_approvals` | Track individual approvals |
| `workflow_history` | Audit trail of workflow actions |
| `sop_definitions` | Standard operating procedures |
| `workflow_sop_mappings` | Link SOPs to workflow steps |

---

## Core Components

### 1. **WorkflowEngine Class**

Manages workflow execution, approval routing, and step advancement.

```php
require 'WorkflowEngine.php';

$engine = new WorkflowEngine($connection, $current_user);
```

**Key Methods:**
- `initiate_workflow()` - Start a new workflow instance
- `approve_step()` - Approve a workflow step
- `reject_step()` - Reject and route back
- `evaluate_conditions()` - Process conditional routing
- `delegate_approval()` - Delegate to another approver
- `get_pending_approvals()` - Get user's pending approvals
- `get_workflow_status()` - Get current workflow state
- `get_workflow_history()` - Get workflow audit trail

### 2. **CustomFieldManager Class**

Manages custom field definitions and rendering.

```php
require 'CustomFieldManager.php';

$field_manager = new CustomFieldManager($connection);
```

**Key Methods:**
- `get_fields()` - Retrieve fields for workflow/equipment type
- `create_field()` - Create new custom field
- `update_field()` - Update field definition
- `validate_field_value()` - Validate user input
- `render_field()` - Generate HTML form field
- `delete_field()` - Remove field definition

### 3. **SOPManager Class**

Manages Standard Operating Procedures.

```php
require 'SOPManager.php';

$sop_manager = new SOPManager($connection);
```

**Key Methods:**
- `create_sop()` - Create new SOP
- `get_sop()` - Retrieve SOP details
- `link_sop_to_workflow()` - Attach SOP to workflow step
- `get_sops_for_workflow_step()` - Get SOPs for step
- `link_sop_to_task()` - Link SOP to specific task
- `render_sop_display()` - Generate SOP HTML
- `delete_sop()` - Delete SOP

---

## Workflow Management

### Creating a Workflow

1. **Define Workflow**
```sql
INSERT INTO workflow_definitions 
(workflow_name, workflow_code, description, workflow_type, is_active, created_by)
VALUES 
('Purchase Order Approval', 'PO_APPROVAL', 'Multi-level PO approval', 'purchase_order', 1, 'SYSTEM');
```

2. **Add Workflow Steps**
```sql
INSERT INTO workflow_steps 
(workflow_id, step_number, step_name, step_type, sequence_order, time_limit_hours, escalation_enabled)
VALUES 
(1, 1, 'Department Manager Review', 'approval', 1, 24, 1);
```

3. **Configure Approval Chains**
```sql
INSERT INTO approval_chains 
(workflow_step_id, approver_group, approval_order, is_required, can_reject)
VALUES
(1, 'manager', 1, 1, 1);
```

### Initiating a Workflow in Code

```php
$engine = new WorkflowEngine($connection, $_SESSION['user']);

$result = $engine->initiate_workflow(
    $workflow_id = 1,              // Workflow definition ID
    $reference_type = 'work_order', // Type of item being approved
    $reference_id = 123            // ID of work order
);

if ($result['success']) {
    $instance_id = $result['instance_id'];
    // Workflow started successfully
}
```

---

## Custom Fields

### Creating Custom Fields per Equipment Type

```php
$field_manager = new CustomFieldManager($connection);

// Create a text field
$result = $field_manager->create_field([
    'equipment_type_id' => 5,
    'field_name' => 'inspection_date',
    'field_label' => 'Last Inspection Date',
    'field_type' => 'date',
    'is_required' => true,
    'help_text' => 'Date of last equipment inspection',
    'sort_order' => 1,
    'created_by' => 'SYSTEM'
]);

// Create a select field
$result = $field_manager->create_field([
    'equipment_type_id' => 5,
    'field_name' => 'inspection_status',
    'field_label' => 'Inspection Status',
    'field_type' => 'select',
    'is_required' => true,
    'sort_order' => 2,
    'created_by' => 'SYSTEM'
]);

// Add options to select field
$field_manager->add_field_option($field_id, 'Passed', 'passed', 1);
$field_manager->add_field_option($field_id, 'Failed', 'failed', 2);
$field_manager->add_field_option($field_id, 'Needs Repair', 'repair', 3);
```

### Rendering Custom Fields in Forms

```php
$fields = $field_manager->get_fields($workflow_id = 1, null, 'Pump');

foreach ($fields as $field) {
    echo $field_manager->render_field($field, $_POST[$field['field_name']] ?? '');
}
```

### Validating Custom Field Values

```php
foreach ($fields as $field) {
    $value = $_POST[$field['field_name']] ?? '';
    $validation = $field_manager->validate_field_value($field, $value);
    
    if (!$validation['valid']) {
        echo "Error: " . $validation['error'];
    }
}
```

---

## SOP Management

### Creating an SOP

```php
$sop_manager = new SOPManager($connection);

$result = $sop_manager->create_sop([
    'sop_code' => 'SOP-PUMP-001',
    'sop_title' => 'Centrifugal Pump Maintenance Procedure',
    'sop_description' => 'Standard procedure for maintaining centrifugal pumps',
    'sop_version' => '2.0',
    'equipment_type_id' => 5,
    'equipment_type_name' => 'Pump',
    'created_by' => 'SYSTEM',
    'is_active' => 1,
    'is_required' => 1
]);

$sop_id = $result['sop_id'];
```

### Linking SOP to Workflow Step

```php
$sop_manager->link_sop_to_workflow(
    $workflow_step_id = 2,
    $sop_id = 1,
    $is_required = true,
    $acknowledgement_required = true
);
```

### Linking Tasks to SOP

```php
$sop_manager->link_sop_to_task(
    $sop_id = 1,
    $task_id = null,
    $task_type = 'maintenance',
    $task_description = 'Check pump bearings for wear',
    $sop_section = 'Inspection',
    $sort_order = 1,
    $is_required = true
);
```

### Displaying SOP

```php
echo $sop_manager->render_sop_display(
    $sop_id = 1,
    $show_acknowledgement = true  // Show checkbox for acknowledgement
);
```

---

## Approval Chains

### Sequential Approvals

Multiple approvers must approve in order:

```sql
-- Step requires sequential approvals
INSERT INTO approval_chains (workflow_step_id, approver_username, approval_order, is_required)
VALUES
(1, 'supervisor1', 1, 1),  -- Approves first
(1, 'manager1', 2, 1),     -- Approves second
(1, 'director1', 3, 1);    -- Approves third
```

### Parallel Approvals

Multiple approvers must all approve (order doesn't matter):

```sql
-- Step requires all approvers to approve (parallel)
UPDATE workflow_steps SET is_parallel = 1, requires_all_approvers = 1 WHERE id = 2;

INSERT INTO approval_chains (workflow_step_id, approver_username, approval_order, is_required)
VALUES
(2, 'lead_mechanic', 1, 1),
(2, 'safety_officer', 1, 1),
(2, 'maintenance_mgr', 1, 1);
```

### Approval Delegation

```php
$engine->delegate_approval(
    $approval_id = 123,
    $delegate_to_username = 'backup_approver',
    $reason = 'On vacation through Friday'
);
```

### Approval with Comments

```php
$result = $engine->approve_step(
    $approval_id = 123,
    $comments = 'Approved. Please note the equipment needs calibration before use.'
);
```

---

## Conditional Routing

### Define Routing Conditions

```sql
-- Route based on work order priority
INSERT INTO workflow_conditions 
(workflow_step_id, condition_name, field_name, operator, field_value, routes_to_step_id)
VALUES
(1, 'High Priority Route', 'priority', '>=', '5', 3),  -- Skip to step 3 if priority >= 5
(1, 'Normal Priority', 'priority', '<', '5', 2);        -- Go to step 2 if priority < 5
```

### Supported Operators

- `=` - Equal to
- `!=` - Not equal to
- `>` - Greater than
- `<` - Less than
- `>=` - Greater than or equal
- `<=` - Less than or equal
- `contains` - String contains
- `not_contains` - String doesn't contain
- `in` - Value in list
- `not_in` - Value not in list
- `is_null` - Field is null
- `is_not_null` - Field is not null

### Evaluate Conditions in Code

```php
$field_values = [
    'priority' => 7,
    'equipment_type' => 'pump',
    'cost' => 2500
];

$result = $engine->evaluate_conditions($instance_id, $field_values);
```

---

## API Reference

### WorkflowEngine

```php
// Initiate workflow
initiate_workflow($workflow_id, $reference_type, $reference_id)

// Approve step
approve_step($approval_id, $comments = '')

// Reject step
reject_step($approval_id, $rejection_reason = '')

// Evaluate conditions
evaluate_conditions($instance_id, $field_values)

// Delegate approval
delegate_approval($approval_id, $delegate_to_username, $reason = '')

// Get workflow status
get_workflow_status($instance_id)

// Get pending approvals
get_pending_approvals($username = null)

// Get workflow history
get_workflow_history($instance_id)
```

### CustomFieldManager

```php
// Get fields
get_fields($workflow_id = null, $equipment_type_id = null, $equipment_type_name = null)

// Create field
create_field($field_data)

// Update field
update_field($field_id, $field_data)

// Add options
add_field_option($field_id, $option_label, $option_value, $sort_order)

// Validate
validate_field_value($field_definition, $value)

// Render HTML
render_field($field_definition, $value = '', $id_prefix = '')

// Delete
delete_field($field_id)
```

### SOPManager

```php
// Create SOP
create_sop($sop_data)

// Get SOP
get_sop($sop_id)

// Link to workflow
link_sop_to_workflow($workflow_step_id, $sop_id, $is_required, $acknowledgement_required)

// Get SOPs for step
get_sops_for_workflow_step($workflow_step_id)

// Link to task
link_sop_to_task($sop_id, $task_id, $task_type, $task_description, $sop_section, $sort_order, $is_required)

// Get tasks
get_tasks_for_sop($sop_id)

// Render
render_sop_display($sop_id, $show_acknowledgement = false)

// Delete
delete_sop($sop_id)
```

---

## Examples

### Complete Workflow Example

```php
// 1. Create workflow instance
$engine = new WorkflowEngine($connection, $_SESSION['user']);

$workflow = $engine->initiate_workflow(
    $workflow_id = 5,
    'work_order',
    $work_order_id = 123
);

if (!$workflow['success']) {
    die("Cannot start workflow: " . $workflow['error']);
}

// 2. Get custom fields for work order form
$field_manager = new CustomFieldManager($connection);
$fields = $field_manager->get_fields($workflow_id = 5);

// 3. Render form with custom fields
echo '<form method="post">';
foreach ($fields as $field) {
    echo $field_manager->render_field($field, $_POST[$field['field_name']] ?? '');
}
echo '<button type="submit">Submit for Approval</button>';
echo '</form>';

// 4. Get SOPs for the workflow
$sop_manager = new SOPManager($connection);
$status = $engine->get_workflow_status($workflow['instance_id']);
$sops = $sop_manager->get_sops_for_workflow_step($status['current_step_id']);

// 5. Display SOPs
foreach ($sops as $sop) {
    echo $sop_manager->render_sop_display($sop['id'], true);
}

// 6. Process approval (when approver reviews)
if ($_POST['action'] == 'approve') {
    $result = $engine->approve_step($_POST['approval_id'], $_POST['comments']);
    if ($result['success']) {
        echo "Approval recorded. " . $result['message'];
    }
}
```

### Multi-Step Approval with Escalation

```php
// Define workflow with 3 approval steps
// Step 1: Team Lead (24-hour limit, escalate to Manager)
// Step 2: Manager (48-hour limit, escalate to Director)
// Step 3: Director (24-hour limit, escalate to VP)

// Schedule escalation check
$escalations = mysqli_query($connection, "
    SELECT wi.*, ws.escalation_hours, ws.escalation_enabled
    FROM workflow_instances wi
    JOIN workflow_steps ws ON wi.current_step_id = ws.id
    WHERE ws.escalation_enabled = 1
    AND DATE_ADD(wi.updated_at, INTERVAL ws.escalation_hours HOUR) <= NOW()
");

while ($escalation = mysqli_fetch_assoc($escalations)) {
    // Create escalation record
    mysqli_query($connection, "
        INSERT INTO workflow_escalations 
        (workflow_instance_id, workflow_step_id, escalation_level, escalated_to_username)
        VALUES (?, ?, 1, 'next_level_approver')
    ");
    
    // Send notification to escalation approver
    send_escalation_notification($escalation['current_approver_username']);
}
```

---

## Best Practices

### 1. **Workflow Design**
- Keep approval chains simple (3-4 steps maximum)
- Use parallel approvals judiciously
- Define clear escalation paths
- Document approval reason requirements

### 2. **Custom Fields**
- Group related fields logically
- Use clear, user-friendly labels
- Validate early and often
- Provide help text for complex fields
- Use appropriate field types for data

### 3. **SOPs**
- Version control your SOPs
- Link SOPs to specific equipment types
- Require acknowledgement for critical SOPs
- Keep SOPs current and relevant
- Categorize tasks within SOPs

### 4. **Performance**
- Index workflow instance lookups
- Archive old workflow instances regularly
- Monitor approval queue depth
- Set appropriate escalation timeouts
- Clean up delegated approvals

### 5. **Audit & Compliance**
- Track all workflow changes
- Maintain approval signatures
- Document rejection reasons
- Generate compliance reports
- Review escalation trends

### 6. **User Experience**
- Make approval process mobile-friendly
- Send timely notifications
- Provide clear approval instructions
- Allow bulk approvals when appropriate
- Show workflow progress visually

---

## Workflow Dashboard

Access the workflow management dashboard at:

```
http://your-cmms/workflow_management.php
```

Features:
- **Dashboard**: Overview of workflow statistics
- **My Approvals**: Pending approvals requiring your action
- **Workflows**: View defined workflows and steps
- **SOPs**: Browse standard operating procedures

---

## Troubleshooting

### Workflow Won't Advance
- Check approval status: All required approvals must be completed
- Verify no conditional routing is blocking advancement
- Check for time limits or escalation holds

### Custom Field Validation Fails
- Verify field definition constraints (min/max, regex)
- Check field type matches data being submitted
- Ensure required fields have values

### SOP Not Displaying
- Verify SOP is active in database
- Check workflow-SOP mapping exists
- Ensure SOP file path is valid

### Approval Notifications Missing
- Check notification system configuration
- Verify approver email/contact is correct
- Review notification logs for errors

---

## Support & Documentation

For additional help:
1. Review database schema comments
2. Check class docstrings in code
3. Review workflow_management.php examples
4. Contact system administrator

---

*Advanced Workflows v2.0 - CMMS System*
*Last Updated: March 2026*
