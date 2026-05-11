<?php
/**
 * API Handler: Work Orders
 * 
 * Endpoints:
 *   GET    /api/v1/work_orders              - List all WOs
 *   GET    /api/v1/work_orders/{id}         - Get single WO
 *   POST   /api/v1/work_orders              - Create WO
 *   PUT    /api/v1/work_orders/{id}         - Update WO
 *   DELETE /api/v1/work_orders/{id}         - Delete WO
 */

function api_get($c, $path, $query, $user_id, $client) {
    $parts = explode('/', $path);
    
    if (count($parts) === 1) {
        // List all work orders
        api_list_work_orders($c, $query);
    } else if (count($parts) === 2 && is_numeric($parts[1])) {
        // Get specific work order
        api_get_work_order($c, $parts[1]);
    } else {
        APIResponse::error('Invalid endpoint', 404);
    }
}

function api_post($c, $path, $body, $user_id, $client) {
    api_create_work_order($c, $body, $user_id);
}

function api_put($c, $path, $body, $user_id, $client) {
    $parts = explode('/', $path);
    if (count($parts) === 2 && is_numeric($parts[1])) {
        api_update_work_order($c, $parts[1], $body, $user_id);
    } else {
        APIResponse::error('Invalid endpoint', 404);
    }
}

function api_delete($c, $path, $user_id, $client) {
    $parts = explode('/', $path);
    if (count($parts) === 2 && is_numeric($parts[1])) {
        api_delete_work_order($c, $parts[1], $user_id);
    } else {
        APIResponse::error('Invalid endpoint', 404);
    }
}

// ===== Implementation Functions =====

function api_list_work_orders($c, $query) {
    $page = (int)($query['page'] ?? 1);
    $per_page = (int)($query['per_page'] ?? 50);
    $status = $query['status'] ?? null;
    $search = $query['search'] ?? null;

    $per_page = min($per_page, 100); // Max 100 per page
    $offset = ($page - 1) * $per_page;

    $where = "WHERE 1=1";
    
    if (!empty($status)) {
        $where .= " AND wo_status='" . mysqli_real_escape_string($c, $status) . "'";
    }

    if (!empty($search)) {
        $where .= " AND (wo_id LIKE '%" . mysqli_real_escape_string($c, $search) . "%' 
                        OR title LIKE '%" . mysqli_real_escape_string($c, $search) . "%')";
    }

    // Count total
    $count_result = mysqli_query($c, "SELECT COUNT(*) as total FROM work_orders $where");
    $count_row = mysqli_fetch_assoc($count_result);
    $total = $count_row['total'];

    // Get paginated results
    $result = mysqli_query($c, 
        "SELECT 
            id, wo_id, title, description, mechanic_id, create_date, 
            due_date, complete_date, wo_status, priority
         FROM work_orders 
         $where 
         ORDER BY create_date DESC 
         LIMIT $per_page OFFSET $offset"
    );

    $work_orders = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $work_orders[] = formatWorkOrder($row);
    }

    APIResponse::paginated($work_orders, $page, $per_page, $total);
}

function api_get_work_order($c, $wo_id) {
    $result = mysqli_query($c,
        "SELECT 
            id, wo_id, title, description, mechanic_id, create_date, 
            due_date, complete_date, wo_status, priority
         FROM work_orders 
         WHERE id=" . (int)$wo_id . " 
         LIMIT 1"
    );

    if (!$result || mysqli_num_rows($result) === 0) {
        APIResponse::error('Work order not found', 404);
    }

    $wo = mysqli_fetch_assoc($result);
    APIResponse::success(formatWorkOrder($wo));
}

function api_create_work_order($c, $body, $user_id) {
    // Validate required fields
    $errors = [];
    $required = ['title', 'mechanic_id', 'due_date'];
    
    foreach ($required as $field) {
        if (empty($body[$field])) {
            $errors[$field] = "Required field";
        }
    }

    if (!empty($errors)) {
        APIResponse::validationError($errors);
    }

    // Insert
    $insert = "INSERT INTO work_orders 
               (wo_id, title, description, mechanic_id, create_date, due_date, wo_status, priority, created_by)
               VALUES 
               ('WO-" . time() . "',
                '" . mysqli_real_escape_string($c, $body['title']) . "',
                '" . mysqli_real_escape_string($c, $body['description'] ?? '') . "',
                " . (int)$body['mechanic_id'] . ",
                NOW(),
                '" . mysqli_real_escape_string($c, $body['due_date']) . "',
                'Pending',
                '" . mysqli_real_escape_string($c, $body['priority'] ?? 'Medium') . "',
                " . (int)$user_id . ")";

    if (!mysqli_query($c, $insert)) {
        APIResponse::error('Failed to create work order: ' . mysqli_error($c), 500);
    }

    $id = mysqli_insert_id($c);
    
    // Fetch and return
    $result = mysqli_query($c, 
        "SELECT id, wo_id, title, description, mechanic_id, create_date, due_date, wo_status, priority 
         FROM work_orders WHERE id=$id LIMIT 1"
    );
    
    $wo = mysqli_fetch_assoc($result);
    APIResponse::created(formatWorkOrder($wo), "/api/v1/work_orders/$id");
}

function api_update_work_order($c, $wo_id, $body, $user_id) {
    // Check if exists
    $check = mysqli_query($c, "SELECT id FROM work_orders WHERE id=" . (int)$wo_id . " LIMIT 1");
    if (!$check || mysqli_num_rows($check) === 0) {
        APIResponse::error('Work order not found', 404);
    }

    $updates = [];
    $allowed_fields = ['title', 'description', 'mechanic_id', 'due_date', 'wo_status', 'priority', 'complete_date'];

    foreach ($allowed_fields as $field) {
        if (isset($body[$field])) {
            $value = $body[$field];
            if ($field === 'mechanic_id' || $field === 'priority') {
                $updates[] = "$field=" . (int)$value;
            } else {
                $updates[] = "$field='" . mysqli_real_escape_string($c, $value) . "'";
            }
        }
    }

    if (empty($updates)) {
        APIResponse::error('No fields to update', 400);
    }

    $update_sql = "UPDATE work_orders SET " . implode(', ', $updates) . " WHERE id=" . (int)$wo_id;
    
    if (!mysqli_query($c, $update_sql)) {
        APIResponse::error('Failed to update: ' . mysqli_error($c), 500);
    }

    // Fetch updated record
    $result = mysqli_query($c,
        "SELECT id, wo_id, title, description, mechanic_id, create_date, due_date, wo_status, priority 
         FROM work_orders WHERE id=" . (int)$wo_id . " LIMIT 1"
    );
    
    $wo = mysqli_fetch_assoc($result);
    APIResponse::success(formatWorkOrder($wo), 'Work order updated');
}

function api_delete_work_order($c, $wo_id, $user_id) {
    // Check if exists
    $check = mysqli_query($c, "SELECT id FROM work_orders WHERE id=" . (int)$wo_id . " LIMIT 1");
    if (!$check || mysqli_num_rows($check) === 0) {
        APIResponse::error('Work order not found', 404);
    }

    // For SQLite, temporarily disable foreign key constraints to avoid constraint violations
    mysqli_query($c, "PRAGMA foreign_keys=OFF");

    if (!mysqli_query($c, "DELETE FROM work_orders WHERE id=" . (int)$wo_id)) {
        // Re-enable FKs even on error
        mysqli_query($c, "PRAGMA foreign_keys=ON");
        APIResponse::error('Failed to delete: ' . mysqli_error($c), 500);
    }

    // Re-enable foreign key constraints
    mysqli_query($c, "PRAGMA foreign_keys=ON");

    APIResponse::noContent();
}

// Helper: Format work order for API
function formatWorkOrder($row) {
    return [
        'id' => (int)$row['id'],
        'wo_id' => $row['wo_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'mechanic_id' => (int)$row['mechanic_id'],
        'created_date' => $row['create_date'],
        'due_date' => $row['due_date'],
        'completed_date' => $row['complete_date'],
        'status' => $row['wo_status'],
        'priority' => $row['priority']
    ];
}

?>
