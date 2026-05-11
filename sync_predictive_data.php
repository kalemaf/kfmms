<?php
/**
 * Sync Predictive Data
 * 
 * Synchronizes equipment from the equipment table to the asset_lifecycle table
 * Enables the predictive maintenance dashboard to show actual equipment data
 */

require_once 'config.inc.php';
require_once 'common.inc.php';
require_once __DIR__ . '/libraries/predictive_integration.php';

// Prevent access without authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$sync_status = null;
$sync_results = null;

// Handle sync request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_equipment') {
    // Call the sync function
    $sync_results = sync_all_equipment_to_asset_lifecycle();
    
    if ($sync_results['success']) {
        $message = "✅ " . $sync_results['message'];
        $sync_status = 'success';
    } else {
        $message = "❌ " . $sync_results['message'];
        $sync_status = 'error';
    }
}

// Get current status
$total_equipment = 0;
$synced_equipment = 0;
$unsync_equipment = 0;
$equipment_list = [];

try {
    $tenant_id = $_SESSION['tenant_id'] ?? 1;
    
    // Get total equipment
    $result = $connection->query("SELECT COUNT(*) as cnt FROM equipment WHERE tenant_id = $tenant_id");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $total_equipment = $row['cnt'] ?? 0;
    
    // Get synced equipment
    $result = $connection->query("SELECT COUNT(*) as cnt FROM asset_lifecycle WHERE tenant_id = $tenant_id");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $synced_equipment = $row['cnt'] ?? 0;
    
    $unsync_equipment = $total_equipment - $synced_equipment;
    
    // Get equipment list
    $result = $connection->query("
        SELECT e.id, e.description, 
               (SELECT COUNT(*) FROM asset_lifecycle WHERE equipment_id = e.id) as has_lifecycle
        FROM equipment e
        WHERE e.tenant_id = $tenant_id 
        ORDER BY e.description
    ");
    $equipment_list = $result->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error in sync page: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Predictive Data</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .status-card {
            border-left: 4px solid;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .status-card.success {
            border-left-color: #28a745;
            background-color: #d4edda;
            color: #155724;
        }
        .status-card.warning {
            border-left-color: #ffc107;
            background-color: #fff3cd;
            color: #856404;
        }
        .status-card.danger {
            border-left-color: #dc3545;
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-card.info {
            border-left-color: #17a2b8;
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .equipment-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .equipment-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .equipment-item:last-child {
            border-bottom: none;
        }
        .badge-synced {
            background-color: #28a745;
        }
        .badge-pending {
            background-color: #ffc107;
        }
        .btn-sync {
            padding: 15px 30px;
            font-size: 16px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Sync Predictive Maintenance Data</h1>
        <p class="text-muted">Synchronize equipment from your database to the predictive maintenance system</p>
        
        <hr>
        
        <!-- Status Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $sync_status === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Current Status -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $total_equipment; ?></div>
                    <div class="stat-label">Total Equipment</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $synced_equipment; ?></div>
                    <div class="stat-label">Synced to Predictive</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $unsync_equipment; ?></div>
                    <div class="stat-label">Pending Sync</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $total_equipment > 0 ? round(($synced_equipment / $total_equipment) * 100) : 0; ?>%</div>
                    <div class="stat-label">Sync Complete</div>
                </div>
            </div>
        </div>
        
        <hr>
        
        <!-- Status Messages -->
        <?php if ($unsync_equipment > 0): ?>
            <div class="status-card warning">
                <strong>⚠️ Pending Sync</strong>
                <p><?php echo $unsync_equipment; ?> equipment item(s) not yet synchronized to the predictive system</p>
                <p><small>This is why the Predictive Dashboard shows 0 equipment. Click the button below to sync all equipment.</small></p>
            </div>
        <?php else: ?>
            <div class="status-card success">
                <strong>✅ All Synced</strong>
                <p>All equipment is synchronized to the predictive system</p>
                <p><small>The Predictive Dashboard should now display all equipment data.</small></p>
            </div>
        <?php endif; ?>
        
        <!-- Sync Button -->
        <div style="margin: 30px 0;">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="sync_equipment">
                <button type="submit" class="btn btn-primary btn-sync" 
                        onclick="return confirm('This will synchronize all equipment to the predictive maintenance system. Continue?')">
                    🔄 Sync All Equipment
                </button>
                <a href="predictive_maintenance_dashboard.php" class="btn btn-secondary btn-sync">
                    📊 View Dashboard
                </a>
                <a href="dashboard.php" class="btn btn-info btn-sync">
                    🏠 Back to Dashboard
                </a>
            </form>
        </div>
        
        <hr>
        
        <!-- Equipment List -->
        <h3>Equipment Status</h3>
        <div class="equipment-list">
            <?php if (empty($equipment_list)): ?>
                <div class="alert alert-info">No equipment found in the system</div>
            <?php else: ?>
                <?php foreach ($equipment_list as $eq): ?>
                    <div class="equipment-item">
                        <div>
                            <strong><?php echo htmlspecialchars($eq['description'] ?? 'Unknown'); ?></strong>
                            <br>
                            <small class="text-muted">Equipment ID: <?php echo (int)$eq['id']; ?></small>
                        </div>
                        <div>
                            <?php if ($eq['has_lifecycle']): ?>
                                <span class="badge badge-synced">✓ Synced</span>
                            <?php else: ?>
                                <span class="badge badge-pending">⏳ Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <hr>
        
        <h3>What Does This Do?</h3>
        <div class="card">
            <div class="card-body">
                <p><strong>The sync process:</strong></p>
                <ul>
                    <li>Reads all equipment from your equipment database</li>
                    <li>Creates predictive lifecycle records for each equipment item</li>
                    <li>Sets default lifecycle expectations (40,000 hours, 5 years)</li>
                    <li>Enables the Predictive Dashboard to display equipment health status</li>
                    <li>Allows work orders to update equipment usage metrics</li>
                    <li>Calculates MTTR, MTBF, and other predictive metrics</li>
                </ul>
                <p><strong>After syncing:</strong></p>
                <ul>
                    <li>Visit the <a href="predictive_maintenance_dashboard.php">Predictive Dashboard</a> to see equipment health</li>
                    <li>Click on equipment to see detailed health analytics</li>
                    <li>Work order completion will now update equipment usage data</li>
                    <li>The system will alert you when equipment needs maintenance</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
