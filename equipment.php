<?php
/**
 * Equipment Management for CMMS
 * Enhanced with CRUD, photo upload, clear table, and spare links.
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
if (file_exists(__DIR__ . '/libraries/predictive_maintenance.php')) {
    require_once __DIR__ . '/libraries/predictive_maintenance.php';
}
if (file_exists(__DIR__ . '/libraries/predictive_integration.php')) {
    require_once __DIR__ . '/libraries/predictive_integration.php';
}

$message = '';

// Retrieve message from session if form was just submitted
if (isset($_SESSION['equipment_message'])) {
    $message = $_SESSION['equipment_message'];
    unset($_SESSION['equipment_message']);
}
$editing = null;

// Set tenant_id early to ensure consistency
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
if ($tenant_id <= 0) $tenant_id = 1;

// Now calculate upload directory with correct tenant
$uploadDir = __DIR__ . '/storage/uploads/tenant_' . $tenant_id . '/equipment_photos';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!function_exists('column_exists_equipment')) {
    function column_exists_equipment($connection, $table, $column)
    {
        global $db_type;
        if (!$connection) {
            return false;
        }

        if ($db_type === 'sqlite') {
            // SQLite version
            $stmt = $connection->query("PRAGMA table_info('$table')");
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if ($row['name'] === $column) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            // MySQL version
            $result = $connection->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $result && $result->fetch(PDO::FETCH_ASSOC) !== false;
        }
    }
}

if ($connection) {
    // Ensure extended columns exist
    $required = [
        'location' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'status' => "VARCHAR(50) NOT NULL DEFAULT ''",
        'manufacturer' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'model' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'serial_number' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'photo' => "VARCHAR(255) NOT NULL DEFAULT ''"
    ];
    foreach ($required as $col => $def) {
        if (!column_exists_equipment($connection, 'equipment', $col)) {
            $connection->query("ALTER TABLE equipment ADD COLUMN {$col} {$def}");
        }
    }

    // Clear all equipment: remove all table rows (user requested empty table ready)
    if (isset($_GET['action']) && $_GET['action'] === 'clear') {
        try {
            // Disable foreign key constraints for cleanup
            $connection->exec('PRAGMA foreign_keys = OFF');
            
            // Get all equipment IDs first
            $equipment_ids = $connection->query('SELECT id FROM equipment')->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete from dependent tables
            $dependent_tables = [
                'asset_health_metrics',
                'asset_lifecycle',
                'condition_monitoring',
                'equipment_spares',
                'hot_jobs',
                'maintenance_schedule',
                'part_lifecycle',
                'pm_schedules',
                'predictive_alerts'
            ];
            
            foreach ($dependent_tables as $table) {
                try {
                    $connection->exec("DELETE FROM {$table} WHERE equipment_id IS NOT NULL");
                } catch (Exception $e) {
                    // Table may not exist, continue
                }
            }
            
            // Now delete from equipment table
            $connection->exec('DELETE FROM equipment');
            
            // Re-enable foreign key constraints
            $connection->exec('PRAGMA foreign_keys = ON');
            
            $_SESSION['equipment_message'] = 'All equipment records have been removed; table is now empty.';
            header('Location: equipment.php');
            exit();
        } catch (Exception $e) {
            // Re-enable foreign keys even if there's an error
            try {
                $connection->exec('PRAGMA foreign_keys = ON');
            } catch (Exception $ex) {}
            
            $message = 'Error clearing equipment: ' . htmlspecialchars($e->getMessage());
        }
    }

    // CSV import
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_import']) && $_FILES['csv_import']['error'] === UPLOAD_ERR_OK) {
        try {
            $imported = 0;
            // Note: $tenant_id is already set at the top of the file
            
            if (($handle = fopen($_FILES['csv_import']['tmp_name'], 'r')) !== false) {
                $header = fgetcsv($handle);
                $stmt = $connection->prepare("
                    INSERT OR REPLACE INTO equipment 
                    (id, parent_id, description, location, status, manufacturer, model, serial_number, tenant_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                while (($data = fgetcsv($handle)) !== false) {
                    $row = array_combine($header, $data);
                    if (!$row) continue;
                    
                    $id = isset($row['id']) && is_numeric($row['id']) ? (int)$row['id'] : 0;
                    $parent_id = isset($row['parent_id']) && is_numeric($row['parent_id']) ? (int)$row['parent_id'] : 0;
                    $description = trim($row['description'] ?? '');
                    
                    if ($description === '') continue;
                    
                    $location = trim($row['location'] ?? '');
                    $status = trim($row['status'] ?? '');
                    $manufacturer = trim($row['manufacturer'] ?? '');
                    $model = trim($row['model'] ?? '');
                    $serial_number = trim($row['serial_number'] ?? '');
                    
                    if ($stmt->execute([$id, $parent_id, $description, $location, $status, $manufacturer, $model, $serial_number, $tenant_id])) {
                        $imported++;
                    }
                }
                fclose($handle);
            }
            $message = "Imported {$imported} equipment item(s) from CSV.";
        } catch (Exception $e) {
            $message = "CSV import error: " . $e->getMessage();
            error_log("CSV import error: " . $e->getMessage());
        }
    }

    // Add/update equipment from form
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES['csv_import']['name']) && !empty($_POST['description'])) {
        try {
            $equipment_id = isset($_POST['equipment_id']) && is_numeric($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
            $parent_id = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
            $description = trim($_POST['description']);
            $location = trim($_POST['location'] ?? '');
            $status = trim($_POST['status'] ?? '');
            $manufacturer = trim($_POST['manufacturer'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $serial_number = trim($_POST['serial_number'] ?? '');
            // Note: $tenant_id is already set at the top of the file

            $photoPath = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $newName = uniqid('equip_', true) . ($ext ? '.' . $ext : '');
                
                // Upload to the correct directory
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . '/' . $newName)) {
                    // Store relative path that matches the uploadDir location
                    // uploadDir is something like: c:\free-cmms 0.04\storage\uploads\tenant_1\equipment_photos
                    // We need to store: storage/uploads/tenant_1/equipment_photos/filename.png
                    $photoPath = 'storage/uploads/tenant_' . $tenant_id . '/equipment_photos/' . $newName;
                }
            }

            if ($equipment_id) {
                // Update existing equipment
                $stmt = $connection->prepare("
                    UPDATE equipment 
                    SET parent_id = ?, description = ?, location = ?, status = ?, 
                        manufacturer = ?, model = ?, serial_number = ?" . 
                        ($photoPath ? ", photo = ?" : "") . "
                    WHERE id = ? AND tenant_id = ?
                ");
                
                $params = [$parent_id, $description, $location, $status, $manufacturer, $model, $serial_number];
                if ($photoPath) {
                    $params[] = $photoPath;
                }
                $params[] = $equipment_id;
                $params[] = $tenant_id;
                
                if ($stmt->execute($params)) {
                    $_SESSION['equipment_message'] = 'Equipment updated successfully.';
                    header('Location: equipment.php');
                    exit();
                } else {
                    $message = 'Update failed: ' . implode(' ', $stmt->errorInfo());
                }
            } else {
                // Insert new equipment
                $stmt = $connection->prepare("
                    INSERT INTO equipment 
                    (parent_id, description, location, status, manufacturer, model, serial_number, photo, tenant_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$parent_id, $description, $location, $status, $manufacturer, $model, $serial_number, $photoPath, $tenant_id])) {
                    $equipment_id = $connection->lastInsertId();
                    
                    // Initialize asset lifecycle tracking for new equipment
                    if (function_exists('create_predictive_maintenance_tables')) {
                        try {
                            create_predictive_maintenance_tables();
                        } catch (Exception $e) {
                            // Tables may already exist
                        }
                    }
                    
                    // Initialize asset lifecycle record
                    if (function_exists('table_exists') && table_exists('asset_lifecycle')) {
                        try {
                            $connection->prepare("
                                INSERT OR IGNORE INTO asset_lifecycle 
                                (equipment_id, asset_category, installation_date, expected_lifecycle_hours, 
                                 expected_lifecycle_days, criticality, tenant_id)
                                VALUES (?, ?, CURRENT_DATE, 10000, 2555, 'Medium', ?)
                            ")->execute([$equipment_id, 'General Equipment', $tenant_id]);
                        } catch (Exception $e) {
                            // Lifecycle record may already exist
                        }
                    }
                    
                    // Also call the sync function to ensure it's in asset_lifecycle
                    if (function_exists('init_equipment_lifecycle')) {
                        try {
                            init_equipment_lifecycle($equipment_id);
                        } catch (Exception $e) {
                            // Sync may have issues, but insertion succeeded
                        }
                    }
                    
                    // Store message in session and redirect to prevent form resubmission on refresh
                    $_SESSION['equipment_message'] = 'Equipment added successfully.';
                    header('Location: equipment.php');
                    exit();
                } else {
                    $message = 'Insert failed: ' . implode(' ', $stmt->errorInfo());
                }
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            error_log("Equipment form error: " . $e->getMessage());
        }
    }

    // Edit mode
    if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $id = (int)$_GET['edit'];
        $row = safe_query_row("SELECT * FROM equipment WHERE id=" . $id . " AND tenant_id=" . (int)($_SESSION['tenant_id'] ?? 1) . " LIMIT 1");
        if ($row) {
            $editing = $row;
        }
    }

    // Load equipment list (filtered by tenant_id)
    $equipment = [];
    $res = safe_query_all('SELECT * FROM equipment WHERE tenant_id=' . (int)($_SESSION['tenant_id'] ?? 1) . ' ORDER BY description');
    foreach ($res as $r) {
        $equipment[] = $r;
    }

    // Load warehouses for location dropdown
    $warehouses = get_warehouses($connection);
}
?>

<h2>Equipment Management</h2>

<?php if ($message): ?>
    <p style="color: green;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" style="margin-bottom: 15px; padding: 10px; border: 1px dashed #999;">
    <input type="hidden" name="equipment_id" value="<?php echo $editing ? (int)$editing['id'] : ''; ?>">
    <label>Description: <input type="text" name="description" required value="<?php echo $editing ? htmlspecialchars($editing['description']) : ''; ?>" style="width: 300px;"></label><br>
    <label>Parent ID: <input type="number" name="parent_id" value="<?php echo $editing ? (int)$editing['parent_id'] : 0; ?>" style="width: 80px;"></label><br>
    <label>Location: 
        <select name="location" style="width: 300px;">
            <option value="">-- Select Warehouse --</option>
            <?php foreach ($warehouses as $wh): ?>
                <option value="<?php echo htmlspecialchars($wh['warehouse_name']); ?>" <?php echo ($editing && $editing['location'] === $wh['warehouse_name']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($wh['warehouse_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label><br>
    <label>Status: <input type="text" name="status" value="<?php echo $editing ? htmlspecialchars($editing['status']) : ''; ?>" style="width: 150px;"></label><br>
    <label>Manufacturer: <input type="text" name="manufacturer" value="<?php echo $editing ? htmlspecialchars($editing['manufacturer']) : ''; ?>" style="width: 240px;"></label><br>
    <label>Model: <input type="text" name="model" value="<?php echo $editing ? htmlspecialchars($editing['model']) : ''; ?>" style="width: 180px;"></label><br>
    <label>Serial #: <input type="text" name="serial_number" value="<?php echo $editing ? htmlspecialchars($editing['serial_number']) : ''; ?>" style="width: 180px;"></label><br>
    <label>Photo: <input type="file" name="photo" accept="image/*"></label><br>
    <?php if ($editing && !empty($editing['photo'])): ?>
        <img src="<?php 
            $photo = $editing['photo'];
            // Convert to absolute path if it's relative
            if (!str_starts_with($photo, '/') && !str_starts_with($photo, 'http')) {
                $photo = '/' . $photo;
            }
            echo htmlspecialchars($photo); 
        ?>" style="max-height:80px; margin:5px;" alt="Equipment Photo">
    <?php endif; ?>
    <button type="submit"><?php echo $editing ? 'Update Equipment' : 'Add Equipment'; ?></button>
</form>

<form method="post" enctype="multipart/form-data" style="margin-bottom: 15px; padding: 10px; border: 1px dashed #999;">
    <strong>Import equipment CSV</strong> (headers: id,parent_id,description,location,status,manufacturer,model,serial_number)
    <input type="file" name="csv_import" accept=".csv" required style="margin-left:10px;">
    <button type="submit">Import Equipment CSV</button>
</form>

<p>
    <a href="index.php?nav=equipment&action=clear" onclick="return confirm('Empty all equipment records?');">Clear all equipment (starts empty)</a>
</p>

<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%; margin-top: 10px;">
    <tr style="background-color: #f0f0f0;">
        <th>ID</th>
        <th>Description</th>
        <th>Parent</th>
        <th>Location</th>
        <th>Status</th>
        <th>Manufacturer</th>
        <th>Model</th>
        <th>Serial Number</th>
        <th>Health Status</th>
        <th>Photo</th>
        <th>Actions</th>
    </tr>
    <?php if (!$equipment): ?>
        <tr><td colspan="11" style="text-align:center;">No equipment yet. Add new equipment above.</td></tr>
    <?php else: ?>
        <?php foreach ($equipment as $item): ?>
            <tr>
                <td><?php echo (int)$item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['description']); ?></td>
                <td><?php echo (int)$item['parent_id']; ?></td>
                <td><?php echo htmlspecialchars($item['location']); ?></td>
                <td><?php echo htmlspecialchars($item['status']); ?></td>
                <td><?php echo htmlspecialchars($item['manufacturer']); ?></td>
                <td><?php echo htmlspecialchars($item['model']); ?></td>
                <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                <td>
                    <?php 
                    if (function_exists('equipment_health_badge')) {
                        echo equipment_health_badge($item['id']);
                    } else {
                        echo '<span class="badge bg-secondary">N/A</span>';
                    }
                    ?>
                </td>
                <td>
                    <?php if (!empty($item['photo'])): ?>
                        <img src="<?php 
                            $photo = $item['photo'];
                            // Convert to absolute path if it's relative
                            if (!str_starts_with($photo, '/') && !str_starts_with($photo, 'http')) {
                                $photo = '/' . $photo;
                            }
                            echo htmlspecialchars($photo); 
                        ?>" style="max-height: 60px; max-width: 80px;" alt="photo">
                    <?php endif; ?>
                </td>
                <td>
                    <a href="index.php?nav=equipment&edit=<?php echo (int)$item['id']; ?>">Edit</a> |
                    <a href="index.php?nav=equipment_spares&equipment_id=<?php echo (int)$item['id']; ?>">Spares</a>
                    <?php if (function_exists('get_equipment_health_status')): ?>
                    | <a href="equipment_health.php?id=<?php echo (int)$item['id']; ?>" target="_blank">Health Details</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<p style="margin-top: 20px;"><a href="index.php?nav=dashboard">Back to Dashboard</a></p>
