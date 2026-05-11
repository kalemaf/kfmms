<?php
/**
 * Equipment Spares Management
 */
require_once 'config.inc.php';
require_once 'common.inc.php';

$message = '';

global $connection, $db_type;
if (!$connection) {
    echo '<p style="color:red;">Database connection error.</p>';
    return;
}

// ensure spares table exists
if ($db_type === 'sqlite') {
    $connection->exec("CREATE TABLE IF NOT EXISTS equipment_spares (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        equipment_id INTEGER NOT NULL,
        part_id INTEGER DEFAULT NULL,
        part_name TEXT NOT NULL,
        part_number TEXT DEFAULT '',
        quantity INTEGER DEFAULT 0,
        notes TEXT
    )");
} else {
    $connection->query("CREATE TABLE IF NOT EXISTS equipment_spares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipment_id INT NOT NULL,
        part_id INT DEFAULT NULL,
        part_name VARCHAR(255) NOT NULL,
        part_number VARCHAR(255) DEFAULT '',
        quantity INT DEFAULT 0,
        notes TEXT,
        INDEX(equipment_id),
        INDEX(part_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

$equipment_id = isset($_REQUEST['equipment_id']) && is_numeric($_REQUEST['equipment_id']) ? (int)$_REQUEST['equipment_id'] : 0;
$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);

if (!$equipment_id) {
    echo '<p style="color:red;">Missing equipment_id</p>';
    echo '<p><a href="index.php?nav=equipment">Back to Equipment</a></p>';
    exit;
}

// validate equipment exists (filtered by tenant_id)
if ($db_type === 'sqlite') {
    $stmt = $connection->prepare("SELECT * FROM equipment WHERE id = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$equipment_id, $tenant_id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$equipment) {
        echo '<p style="color:red;">Equipment not found.</p>';
        echo '<p><a href="index.php?nav=equipment">Back to Equipment</a></p>';
        exit;
    }
} else {
    $equipResult = $connection->query("SELECT * FROM equipment WHERE id={$equipment_id} AND tenant_id={$tenant_id} LIMIT 1");
    if (!$equipResult || $equipResult->num_rows === 0) {
        echo '<p style="color:red;">Equipment not found.</p>';
        echo '<p><a href="index.php?nav=equipment">Back to Equipment</a></p>';
        exit;
    }
    $equipment = $equipResult->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['part_name'])) {
    $part_name = trim($_POST['part_name']);
    $part_number = trim($_POST['part_number'] ?? '');
    $quantity = isset($_POST['quantity']) && is_numeric($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $notes = trim($_POST['notes'] ?? '');

    $spare_id = null;
    
    if ($db_type === 'sqlite') {
        $stmt = $connection->prepare("INSERT INTO equipment_spares (equipment_id, part_name, part_number, quantity, notes, tenant_id) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$equipment_id, $part_name, $part_number, $quantity, $notes, $tenant_id])) {
            $message = 'Spare item added.';
            $spare_id = $connection->lastInsertId();
        } else {
            $message = 'Failed to add spare item.';
        }
    } else {
        $part_name = $connection->real_escape_string($part_name);
        $part_number = $connection->real_escape_string($part_number);
        $notes = $connection->real_escape_string($notes);

        $sql = "INSERT INTO equipment_spares (equipment_id, part_name, part_number, quantity, notes, tenant_id) VALUES ({$equipment_id}, '{$part_name}', '{$part_number}', {$quantity}, '{$notes}', {$tenant_id})";
        if ($connection->query($sql)) {
            $message = 'Spare item added.';
            $spare_id = $connection->insert_id;
        } else {
            $message = 'Failed to add spare item: ' . $connection->error;
        }
    }
    
    // Auto-link to parts_master and sync inventory
    if ($spare_id) {
        require_once 'spare_integration_functions.php';
        if (function_exists('link_spare_to_parts_master')) {
            $result = link_spare_to_parts_master($spare_id, $part_name, $part_number, $connection, 0);
            if ($result) {
                $part_id = intval($result);
                
                // Use generic update function to avoid database-specific syntax
                if ($db_type === 'sqlite' && $connection instanceof PDO) {
                    $stmt = $connection->prepare("UPDATE parts_master SET total_on_hand = ? WHERE id = ?");
                    $stmt->execute([$quantity, $part_id]);
                    
                    // Add to stock_locales
                    $stmt = $connection->prepare("
                        SELECT id FROM stock_locales 
                        WHERE part_id = ? AND warehouse_location_id = 1
                    ");
                    $stmt->execute([$part_id]);
                    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($exists) {
                        $stmt = $connection->prepare("UPDATE stock_locales SET quantity_on_hand = ? WHERE id = ?");
                        $stmt->execute([$quantity, $exists['id']]);
                    } else {
                        $stmt = $connection->prepare("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand) VALUES (?, 1, ?)");
                        $stmt->execute([$part_id, $quantity]);
                    }
                } else {
                    $connection->query("UPDATE parts_master SET total_on_hand = $quantity WHERE id = $part_id");
                    
                    // Check if stock_locale exists
                    $check = $connection->query("SELECT id FROM stock_locales WHERE part_id = $part_id AND warehouse_location_id = 1");
                    if ($check && $check->num_rows > 0) {
                        $connection->query("UPDATE stock_locales SET quantity_on_hand = $quantity WHERE part_id = $part_id AND warehouse_location_id = 1");
                    } else {
                        $connection->query("INSERT INTO stock_locales (part_id, warehouse_location_id, quantity_on_hand) VALUES ($part_id, 1, $quantity)");
                    }
                }
            }
        }
    }
}


if (isset($_GET['delete_spare']) && is_numeric($_GET['delete_spare'])) {
    $id = (int)$_GET['delete_spare'];
    if ($db_type === 'sqlite') {
        $stmt = $connection->prepare("DELETE FROM equipment_spares WHERE id = ? AND equipment_id = ? AND tenant_id = ?");
        $stmt->execute([$id, $equipment_id, $tenant_id]);
    } else {
        $connection->query("DELETE FROM equipment_spares WHERE id={$id} AND equipment_id={$equipment_id} AND tenant_id={$tenant_id}");
    }
    $message = 'Spare item removed.';
}

$spares = [];
if ($db_type === 'sqlite') {
    $stmt = $connection->prepare("SELECT * FROM equipment_spares WHERE equipment_id = ? AND tenant_id = ? ORDER BY id DESC");
    $stmt->execute([$equipment_id, $tenant_id]);
    $spares = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sparesRes = $connection->query("SELECT * FROM equipment_spares WHERE equipment_id={$equipment_id} AND tenant_id={$tenant_id} ORDER BY id DESC");
    if ($sparesRes) {
        while ($row = $sparesRes->fetch_assoc()) {
            $spares[] = $row;
        }
    }
}

?>

<!-- Equipment Spares Content -->
<div class="container-fluid">
    <!-- Header -->
    <div class="card mb-4" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><i class="fas fa-cogs me-2"></i>Equipment Spares Management</h3>
                    <small class="mt-2 d-block" style="opacity: 0.9;">
                        <i class="fas fa-wrench me-2"></i>
                        <?php echo htmlspecialchars($equipment['description'] ?? ''); ?> (#<?php echo $equipment_id; ?>)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add Spare Form -->
    <div class="card mb-4">
        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
            <i class="fas fa-plus-circle me-2"></i>Add New Spare Part
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-tag me-2"></i>Part Name *
                        </label>
                        <input type="text" class="form-control" name="part_name" required
                               placeholder="Enter part name (e.g., Ball Bearing)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-hashtag me-2"></i>Part Number
                        </label>
                        <input type="text" class="form-control" name="part_number"
                               placeholder="Enter part number (optional)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-sort-numeric-up me-2"></i>Quantity
                        </label>
                        <input type="number" class="form-control" name="quantity" value="1" min="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="fas fa-sticky-note me-2"></i>Notes
                        </label>
                        <textarea class="form-control" name="notes" rows="1"
                                  placeholder="Additional notes or specifications"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add Spare Item
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Spares Table -->
    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none;">
            <i class="fas fa-list me-2"></i>Current Spare Parts (<?php echo count($spares); ?> items)
        </div>
        <div class="card-body">
            <?php if (empty($spares)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-box-open" style="font-size: 3rem; opacity: 0.5;"></i>
                    <h5 class="mt-3">No spare parts added yet</h5>
                    <p>Use the form above to add spare parts for this equipment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <tr>
                                <th><i class="fas fa-id-badge me-2"></i>ID</th>
                                <th><i class="fas fa-tag me-2"></i>Part Name</th>
                                <th><i class="fas fa-hashtag me-2"></i>Part Number</th>
                                <th><i class="fas fa-sort-numeric-up me-2"></i>Quantity</th>
                                <th><i class="fas fa-sticky-note me-2"></i>Notes</th>
                                <th><i class="fas fa-cogs me-2"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($spares as $s): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">#<?php echo (int)$s['id']; ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($s['part_name'] ?? ''); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($s['part_number']): ?>
                                            <code><?php echo htmlspecialchars($s['part_number']); ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?php echo (int)$s['quantity']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($s['notes']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($s['notes']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="index.php?nav=equipment_spares&equipment_id=<?php echo $equipment_id; ?>&delete_spare=<?php echo (int)$s['id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this spare item?');">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// This file is now included from index.php
