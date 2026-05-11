<?php
/**
 * Test: Capture actual POST data for debugging
 * 
 * This script helps understand what data is actually being sent in the POST request
 * when the user submits a work order update form.
 */

error_log("═══════════════════════════════════════════════════════════════════");
error_log("WO FORM SUBMISSION - DEBUG CAPTURE");
error_log("═══════════════════════════════════════════════════════════════════");

$post_spares = [];
$post_parts = [];
$post_consumables = [];

foreach ($_POST as $key => $val) {
    if (strpos($key, 'spares_') === 0) {
        $post_spares[$key] = $val;
    } elseif (strpos($key, 'part_') === 0) {
        $post_parts[$key] = $val;
    } elseif (strpos($key, 'consumable_') === 0 || $key === 'consumables_table_body') {
        $post_consumables[$key] = $val;
    }
}

error_log("POST spares found: " . json_encode($post_spares));
error_log("POST parts found: " . json_encode($post_parts));
error_log("POST consumables found: " . json_encode($post_consumables));
error_log("Total POST keys: " . count($_POST));

// List all POST keys that don't match common patterns
$other_keys = [];
$common_patterns = ['descriptive_text', 'equipment', 'mechanic_id', 'maintenance_type', 'description', 'status', 'priority', 'submit_date', 'needed_date', 'est_hours', 'assigned_to', 'action', 'wo_status', 'down_time_hours', 'response_time', 'resolution_time', 'audited', 'audited_by', 'audited_date', 'notes', 'inspected_by', 'inspected_date', 'approved_by', 'approved_date', 'sla_due_date', 'csv_import', 'consumables_table_body'];
foreach ($_POST as $key => $val) {
    $matches_pattern = false;
    foreach ($common_patterns as $pattern) {
        if ($key === $pattern || strpos($key, 'spares_') === 0 || strpos($key, 'part_') === 0 || strpos($key, 'consumable_') === 0) {
            $matches_pattern = true;
            break;
        }
    }
    if (!$matches_pattern) {
        $other_keys[$key] = $val;
    }
}

if (!empty($other_keys)) {
    error_log("Other POST keys: " . json_encode($other_keys));
}

error_log("═══════════════════════════════════════════════════════════════════\n");
?>
