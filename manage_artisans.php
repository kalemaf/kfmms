<?php
/**
 * Artisan/Technician Management Interface
 * Manage technician profiles, skills, certifications, availability, and assignments
 */

require_once 'config.inc.php';
require_once 'libraries/artisanService.php';

$role = strtolower($_SESSION['role'] ?? '');

// Access control
if (!in_array($role, ['admin', 'manager', 'maintenance manager', 'supervisor', 'developer'], true)) {
    die("Access denied. Only supervisors, managers, maintenance managers, admins or developers can manage artisans.");
}

$tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
if ($tenant_id <= 0) {
    $tenant_id = 1;
}

$artisanService = new ArtisanService($pdo, $tenant_id);
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_artisan') {
        $artisan_data = [
            'artisan_id' => $_POST['artisan_id'] ?? null,
            'user_id' => $_POST['user_id'] ?? null,
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'employee_id' => $_POST['employee_id'] ?? null,
            'phone' => $_POST['phone'] ?? null,
            'mobile_phone' => $_POST['mobile_phone'] ?? null,
            'email' => $_POST['email'] ?? null,
            'birth_date' => $_POST['birth_date'] ?? null,
            'hire_date' => $_POST['hire_date'] ?? null,
            'vendor_id' => $_POST['vendor_id'] ?? null,
            'hourly_rate' => $_POST['hourly_rate'] ?? 0,
            'cost_center' => $_POST['cost_center'] ?? null,
            'sms_enabled' => isset($_POST['sms_enabled']),
            'push_notifications_enabled' => isset($_POST['push_notifications_enabled']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'availability_status' => $_POST['availability_status'] ?? 'available',
            'available_from_date' => $_POST['available_from_date'] ?? null,
            'available_to_date' => $_POST['available_to_date'] ?? null,
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
            'notes' => $_POST['notes'] ?? null
        ];
        
        // Process skills
        if (!empty($_POST['skills']) && is_array($_POST['skills'])) {
            $artisan_data['skills'] = [];
            foreach ($_POST['skills'] as $skill) {
                if (!empty($skill['skill_name'])) {
                    $artisan_data['skills'][] = [
                        'skill_name' => $skill['skill_name'],
                        'skill_category' => $skill['skill_category'] ?? null,
                        'proficiency_level' => $skill['proficiency_level'] ?? 'intermediate',
                        'years_of_experience' => $skill['years_of_experience'] ?? 0,
                        'is_verified' => false
                    ];
                }
            }
        }
        
        // Process certifications
        if (!empty($_POST['certifications']) && is_array($_POST['certifications'])) {
            $artisan_data['certifications'] = [];
            foreach ($_POST['certifications'] as $cert) {
                if (!empty($cert['certification_name'])) {
                    $artisan_data['certifications'][] = [
                        'certification_name' => $cert['certification_name'],
                        'certification_number' => $cert['certification_number'] ?? null,
                        'issuing_body' => $cert['issuing_body'] ?? null,
                        'issue_date' => $cert['issue_date'] ?? null,
                        'expiry_date' => $cert['expiry_date'] ?? null,
                        'is_active' => true,
                        'compliance_requirement' => false
                    ];
                }
            }
        }
        
        $result = $artisanService->save_artisan($artisan_data);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: manage_artisans.php?message=' . urlencode($message));
            exit;
        } else {
            $error = $result['message'];
        }
    }
    
    if ($action === 'add_skill' && !empty($_POST['artisan_id'])) {
        $skill_data = [
            'skill_name' => $_POST['skill_name'],
            'skill_category' => $_POST['skill_category'] ?? null,
            'proficiency_level' => $_POST['proficiency_level'] ?? 'intermediate',
            'years_of_experience' => $_POST['years_of_experience'] ?? 0,
            'is_verified' => isset($_POST['is_verified'])
        ];
        $artisanService->add_skill($_POST['artisan_id'], $skill_data);
        $message = "Skill added successfully";
        header('Location: manage_artisans.php?view=edit&artisan_id=' . intval($_POST['artisan_id']) . '&message=' . urlencode($message));
        exit;
    }
    
    if ($action === 'add_certification' && !empty($_POST['artisan_id'])) {
        $cert_data = [
            'certification_name' => $_POST['certification_name'],
            'certification_number' => $_POST['certification_number'] ?? null,
            'issuing_body' => $_POST['issuing_body'] ?? null,
            'issue_date' => $_POST['issue_date'] ?? null,
            'expiry_date' => $_POST['expiry_date'] ?? null,
            'is_active' => isset($_POST['is_active']),
            'compliance_requirement' => isset($_POST['compliance_requirement'])
        ];
        $artisanService->add_certification($_POST['artisan_id'], $cert_data);
        $message = "Certification added successfully";
        header('Location: manage_artisans.php?view=edit&artisan_id=' . intval($_POST['artisan_id']) . '&message=' . urlencode($message));
        exit;
    }
    
    if ($action === 'assign_site' && !empty($_POST['artisan_id'])) {
        $site_data = [
            'site_id' => $_POST['site_id'] ?? null,
            'company_id' => $_POST['company_id'] ?? null,
            'location_name' => $_POST['location_name'] ?? null,
            'assignment_start_date' => $_POST['assignment_start_date'] ?? null,
            'assignment_end_date' => $_POST['assignment_end_date'] ?? null,
            'is_primary_site' => isset($_POST['is_primary_site']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        $artisanService->assign_site($_POST['artisan_id'], $site_data);
        $message = "Site assignment added successfully";
        header('Location: manage_artisans.php?view=edit&artisan_id=' . intval($_POST['artisan_id']) . '&message=' . urlencode($message));
        exit;
    }
    
    if ($action === 'delete_artisan' && !empty($_POST['artisan_id'])) {
        $result = $artisanService->delete_artisan($_POST['artisan_id']);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: manage_artisans.php?message=' . urlencode($message));
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Get list of all artisans
$artisans = $artisanService->get_all_artisans();

// Get view mode
$view = $_GET['view'] ?? 'list';
$artisan_id = $_GET['artisan_id'] ?? null;
$artisan = null;
$artisan_skills = [];
$artisan_certs = [];
$artisan_sites = [];
$artisan_work_orders = [];

if ($view === 'edit' && $artisan_id) {
    $artisan = $artisanService->get_artisan($artisan_id);
    $artisan_skills = $artisanService->get_artisan_skills($artisan_id);
    $artisan_certs = $artisanService->get_artisan_certifications($artisan_id);
    $artisan_sites = $artisanService->get_artisan_sites($artisan_id);
    $artisan_work_orders = $artisanService->get_artisan_work_orders($artisan_id);
}

// Get list of all artisans
$artisans = $artisanService->get_all_artisans();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Artisan Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
        }
        
        h1 {
            font-size: 28px;
            color: #333;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1976D2;
        }
        
        .btn-secondary {
            background: #757575;
            color: white;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: #616161;
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #da190b;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row.full {
            grid-template-columns: 1fr;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        
        .table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .table tr:hover {
            background: #f9f9f9;
        }
        
        .table tbody tr {
            transition: background 0.2s ease;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #c8e6c9;
            color: #2e7d32;
        }
        
        .badge-inactive {
            background: #ffccbc;
            color: #d84315;
        }
        
        .badge-available {
            background: #b3e5fc;
            color: #01579b;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .tab {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: #2196F3;
            border-bottom-color: #2196F3;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-card {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-card h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        
        .badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .modal-header h2 {
            font-size: 20px;
            color: #333;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: color 0.2s ease;
        }
        
        .close-btn:hover {
            color: #333;
        }
        
        .performance-score {
            font-size: 28px;
            font-weight: bold;
            color: #2196F3;
        }
        
        .performance-rating {
            font-size: 16px;
            margin-top: 5px;
        }
        
        .rating-excellent {
            color: #4CAF50;
        }
        
        .rating-good {
            color: #2196F3;
        }
        
        .rating-satisfactory {
            color: #ff9800;
        }
        
        .rating-poor {
            color: #f44336;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>👨‍🔧 Artisan Management</h1>
        <div>
            <?php if ($view === 'list'): ?>
                <button class="btn btn-primary" onclick="openModal('newArtisanModal')">+ New Artisan</button>
            <?php else: ?>
                <a href="manage_artisans.php" class="btn btn-secondary">← Back to List</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success">✓ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">✗ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($view === 'list'): ?>
        <!-- Artisans List View -->
        <div style="margin-bottom: 20px;">
            <p style="color: #666;">Total Artisans: <strong><?php echo count($artisans); ?></strong></p>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Employee ID</th>
                    <th>Contact</th>
                    <th>Performance Score</th>
                    <th>Skills</th>
                    <th>Certifications</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($artisans as $artisan): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($artisan['first_name'] . ' ' . $artisan['last_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($artisan['employee_id'] ?? '-'); ?></td>
                        <td>
                            <small>
                                <?php echo htmlspecialchars($artisan['phone'] ?? $artisan['mobile_phone'] ?? '-'); ?><br>
                                <?php echo htmlspecialchars($artisan['email'] ?? '-'); ?>
                            </small>
                        </td>
                        <td>
                            <div class="performance-score"><?php echo number_format($artisan['performance_score'], 1); ?>%</div>
                            <small class="performance-rating <?php 
                                $score = $artisan['performance_score'];
                                echo $score >= 90 ? 'rating-excellent' : ($score >= 75 ? 'rating-good' : ($score >= 60 ? 'rating-satisfactory' : 'rating-poor'));
                            ?>">
                                <?php 
                                    echo $score >= 90 ? 'Excellent' : ($score >= 75 ? 'Good' : ($score >= 60 ? 'Satisfactory' : 'Needs Improvement'));
                                ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge"><?php echo $artisan['skill_count'] ?? 0; ?> skills</span>
                        </td>
                        <td>
                            <span class="badge"><?php echo $artisan['cert_count'] ?? 0; ?> certs</span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $artisan['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $artisan['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="manage_artisans.php?view=edit&artisan_id=<?php echo $artisan['artisan_id']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($artisans)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #999; padding: 30px;">
                            No artisans found. <a href="javascript:openModal('newArtisanModal')" style="color: #2196F3;">Create one now</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
    <?php elseif ($view === 'edit' && $artisan): ?>
        <!-- Artisan Detail View -->
        <div class="info-card">
            <h3><?php echo htmlspecialchars($artisan['first_name'] . ' ' . $artisan['last_name']); ?></h3>
            <p style="color: #666; margin: 10px 0;">
                Employee ID: <strong><?php echo htmlspecialchars($artisan['employee_id'] ?? 'N/A'); ?></strong> | 
                Status: <span class="status-badge <?php echo $artisan['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                    <?php echo $artisan['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </p>
            <p style="color: #666;">
                Performance Score: <strong class="performance-score"><?php echo number_format($artisan['performance_score'], 1); ?>%</strong>
            </p>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="switchTab('overview')">Overview</button>
            <button class="tab" onclick="switchTab('skills')">Skills</button>
            <button class="tab" onclick="switchTab('certifications')">Certifications</button>
            <button class="tab" onclick="switchTab('sites')">Sites</button>
            <button class="tab" onclick="switchTab('workorders')">Work Orders</button>
        </div>
        
        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <form method="POST" style="display: grid; gap: 15px;">
                <input type="hidden" name="action" value="save_artisan">
                <input type="hidden" name="artisan_id" value="<?php echo $artisan['artisan_id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($artisan['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($artisan['last_name']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($artisan['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Mobile Phone</label>
                        <input type="tel" name="mobile_phone" value="<?php echo htmlspecialchars($artisan['mobile_phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Employee ID</label>
                        <input type="text" name="employee_id" value="<?php echo htmlspecialchars($artisan['employee_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Hire Date</label>
                        <input type="date" name="hire_date" value="<?php echo htmlspecialchars($artisan['hire_date'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($artisan['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Birth Date</label>
                        <input type="date" name="birth_date" value="<?php echo htmlspecialchars($artisan['birth_date'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Hourly Rate ($)</label>
                        <input type="number" name="hourly_rate" step="0.01" value="<?php echo htmlspecialchars($artisan['hourly_rate'] ?? '0'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Cost Center</label>
                        <input type="text" name="cost_center" value="<?php echo htmlspecialchars($artisan['cost_center'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Availability Status</label>
                        <select name="availability_status">
                            <option value="available" <?php echo $artisan['availability_status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="on_leave" <?php echo $artisan['availability_status'] === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                            <option value="unavailable" <?php echo $artisan['availability_status'] === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                            <option value="part_time" <?php echo $artisan['availability_status'] === 'part_time' ? 'selected' : ''; ?>>Part-time</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Available From</label>
                        <input type="date" name="available_from_date" value="<?php echo htmlspecialchars($artisan['available_from_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Available Until</label>
                        <input type="date" name="available_to_date" value="<?php echo htmlspecialchars($artisan['available_to_date'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes"><?php echo htmlspecialchars($artisan['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="is_active" id="is_active" <?php echo $artisan['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" style="margin: 0;">Active</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="sms_enabled" id="sms_enabled" <?php echo $artisan['sms_enabled'] ? 'checked' : ''; ?>>
                            <label for="sms_enabled" style="margin: 0;">SMS Enabled</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="push_notifications_enabled" id="push_enabled" <?php echo $artisan['push_notifications_enabled'] ? 'checked' : ''; ?>>
                            <label for="push_enabled" style="margin: 0;">Push Notifications</label>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">💾 Save Changes</button>
                    <button type="button" class="btn btn-danger" onclick="if(confirm('Delete this artisan?')) { document.getElementById('deleteForm').submit(); }">🗑️ Delete</button>
                </div>
            </form>
        </div>
        
        <!-- Skills Tab -->
        <div id="skills" class="tab-content">
            <h3>Skills & Expertise</h3>
            <div style="margin-bottom: 20px; margin-top: 20px;">
                <button class="btn btn-primary" onclick="openModal('addSkillModal')">+ Add Skill</button>
            </div>
            
            <?php if (!empty($artisan_skills)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Skill</th>
                            <th>Category</th>
                            <th>Proficiency</th>
                            <th>Experience</th>
                            <th>Verified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artisan_skills as $skill): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($skill['skill_name']); ?></td>
                                <td><?php echo htmlspecialchars($skill['skill_category'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge" style="background: #fff9c4; color: #f57f17;">
                                        <?php echo ucfirst($skill['proficiency_level']); ?>
                                    </span>
                                </td>
                                <td><?php echo $skill['years_of_experience'] ? number_format($skill['years_of_experience'], 1) . ' years' : '-'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $skill['is_verified'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $skill['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999; padding: 20px; text-align: center;">No skills recorded yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- Certifications Tab -->
        <div id="certifications" class="tab-content">
            <h3>Certifications & Compliance</h3>
            <div style="margin-bottom: 20px; margin-top: 20px;">
                <button class="btn btn-primary" onclick="openModal('addCertModal')">+ Add Certification</button>
            </div>
            
            <?php if (!empty($artisan_certs)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Certification</th>
                            <th>Number</th>
                            <th>Issued By</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Compliance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artisan_certs as $cert): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cert['certification_name']); ?></td>
                                <td><?php echo htmlspecialchars($cert['certification_number'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($cert['issuing_body'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($cert['expiry_date']): ?>
                                        <strong><?php echo date('M d, Y', strtotime($cert['expiry_date'])); ?></strong>
                                        <?php if (strtotime($cert['expiry_date']) < time()): ?>
                                            <br><span class="status-badge badge-inactive">Expired</span>
                                        <?php elseif (strtotime($cert['expiry_date']) < time() + (30 * 24 * 60 * 60)): ?>
                                            <br><span class="status-badge" style="background: #fff3cd; color: #856404;">Expiring Soon</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $cert['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $cert['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $cert['compliance_requirement'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $cert['compliance_requirement'] ? 'Required' : 'Optional'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999; padding: 20px; text-align: center;">No certifications recorded yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- Sites Tab -->
        <div id="sites" class="tab-content">
            <h3>Site Assignments</h3>
            <div style="margin-bottom: 20px; margin-top: 20px;">
                <button class="btn btn-primary" onclick="openModal('assignSiteModal')">+ Assign Site</button>
            </div>
            
            <?php if (!empty($artisan_sites)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Primary</th>
                            <th>From Date</th>
                            <th>To Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artisan_sites as $site): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($site['location_name'] ?? 'Site ' . $site['site_id']); ?></td>
                                <td>
                                    <span class="badge <?php echo $site['is_primary_site'] ? 'badge-active' : ''; ?>" style="<?php echo $site['is_primary_site'] ? '' : 'background: #e0e0e0; color: #666;'; ?>">
                                        <?php echo $site['is_primary_site'] ? 'Primary' : 'Secondary'; ?>
                                    </span>
                                </td>
                                <td><?php echo $site['assignment_start_date'] ? date('M d, Y', strtotime($site['assignment_start_date'])) : '-'; ?></td>
                                <td><?php echo $site['assignment_end_date'] ? date('M d, Y', strtotime($site['assignment_end_date'])) : 'Ongoing'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $site['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $site['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999; padding: 20px; text-align: center;">No site assignments yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- Work Orders Tab -->
        <div id="workorders" class="tab-content">
            <h3>Work Order History</h3>
            
            <?php if (!empty($artisan_work_orders)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Work Order</th>
                            <th>Asset</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Est. Hours</th>
                            <th>Actual Hours</th>
                            <th>Assigned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artisan_work_orders as $wo): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($wo['work_order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($wo['asset_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge"><?php echo htmlspecialchars(ucfirst($wo['status'])); ?></span>
                                </td>
                                <td>
                                    <span class="badge" style="background: <?php
                                        echo $wo['priority'] === 'Critical' ? '#ffcdd2' : 
                                             ($wo['priority'] === 'High' ? '#ffe0b2' :
                                              ($wo['priority'] === 'Medium' ? '#c8e6c9' : '#bbdefb'));
                                    ?>; color: #333;">
                                        <?php echo htmlspecialchars($wo['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo $wo['estimated_hours'] ?? '-'; ?></td>
                                <td><?php echo $wo['actual_hours'] ?? '-'; ?></td>
                                <td><small><?php echo date('M d, Y', strtotime($wo['assignment_date'])); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999; padding: 20px; text-align: center;">No work orders assigned yet.</p>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
</div>

<!-- New Artisan Modal -->
<div id="newArtisanModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create New Artisan Profile</h2>
            <button class="close-btn" onclick="closeModal('newArtisanModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_artisan">
            
            <div class="form-row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Employee ID</label>
                    <input type="text" name="employee_id" placeholder="e.g., EMP001">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" placeholder="e.g., +1-555-0123">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="e.g., artisan@company.com">
                </div>
                <div class="form-group">
                    <label>Hire Date</label>
                    <input type="date" name="hire_date">
                </div>
            </div>
            
            <!-- Skills Section -->
            <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <h3 style="margin-bottom: 10px; color: #333;">Skills</h3>
                <div id="skills-container">
                    <div class="skill-entry" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: end;">
                        <div class="form-group" style="flex: 2;">
                            <label>Skill Name</label>
                            <input type="text" name="skills[0][skill_name]" placeholder="e.g., HVAC Repair">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Category</label>
                            <input type="text" name="skills[0][skill_category]" placeholder="e.g., Mechanical">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Proficiency</label>
                            <select name="skills[0][proficiency_level]">
                                <option value="beginner">Beginner</option>
                                <option value="intermediate" selected>Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Experience (years)</label>
                            <input type="number" name="skills[0][years_of_experience]" min="0" step="0.5" placeholder="2.5">
                        </div>
                        <button type="button" class="btn btn-danger" style="padding: 8px 12px;" onclick="removeSkill(this)">×</button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" style="margin-top: 10px;" onclick="addSkill()">+ Add Skill</button>
            </div>
            
            <!-- Certifications Section -->
            <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <h3 style="margin-bottom: 10px; color: #333;">Certifications</h3>
                <div id="certs-container">
                    <div class="cert-entry" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: end;">
                        <div class="form-group" style="flex: 2;">
                            <label>Certification Name</label>
                            <input type="text" name="certifications[0][certification_name]" placeholder="e.g., OSHA Safety Certification">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Issuing Body</label>
                            <input type="text" name="certifications[0][issuing_body]" placeholder="e.g., OSHA">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Issue Date</label>
                            <input type="date" name="certifications[0][issue_date]">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Expiry Date</label>
                            <input type="date" name="certifications[0][expiry_date]">
                        </div>
                        <button type="button" class="btn btn-danger" style="padding: 8px 12px;" onclick="removeCert(this)">×</button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" style="margin-top: 10px;" onclick="addCertification()">+ Add Certification</button>
            </div>
            
            <div class="form-group checkbox-group" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <div class="checkbox-item">
                    <input type="checkbox" name="is_active" id="new_is_active" checked>
                    <label for="new_is_active" style="margin: 0;">Active</label>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">✓ Create Artisan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('newArtisanModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Skill Modal -->
<div id="addSkillModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Skill</h2>
            <button class="close-btn" onclick="closeModal('addSkillModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_skill">
            <input type="hidden" name="artisan_id" value="<?php echo $artisan['artisan_id'] ?? ''; ?>">
            
            <div class="form-group">
                <label>Skill Name *</label>
                <input type="text" name="skill_name" placeholder="e.g., HVAC Repair, Electrical Troubleshooting" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="skill_category" placeholder="e.g., Mechanical, Electrical">
                </div>
                <div class="form-group">
                    <label>Proficiency Level</label>
                    <select name="proficiency_level">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate" selected>Intermediate</option>
                        <option value="advanced">Advanced</option>
                        <option value="expert">Expert</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Years of Experience</label>
                    <input type="number" name="years_of_experience" step="0.5" min="0">
                </div>
                <div class="form-group checkbox-item">
                    <input type="checkbox" name="is_verified" id="skill_verified">
                    <label for="skill_verified" style="margin: 0; width: auto;">Verified Skill</label>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">✓ Add Skill</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSkillModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Certification Modal -->
<div id="addCertModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Certification</h2>
            <button class="close-btn" onclick="closeModal('addCertModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_certification">
            <input type="hidden" name="artisan_id" value="<?php echo $artisan['artisan_id'] ?? ''; ?>">
            
            <div class="form-group">
                <label>Certification Name *</label>
                <input type="text" name="certification_name" placeholder="e.g., EPA Section 608 Certification" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Certification Number</label>
                    <input type="text" name="certification_number">
                </div>
                <div class="form-group">
                    <label>Issuing Body</label>
                    <input type="text" name="issuing_body">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date">
                </div>
            </div>
            
            <div class="form-group checkbox-group">
                <div class="checkbox-item">
                    <input type="checkbox" name="is_active" id="cert_active" checked>
                    <label for="cert_active" style="margin: 0;">Active</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="compliance_requirement" id="cert_compliance">
                    <label for="cert_compliance" style="margin: 0;">Compliance Required</label>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">✓ Add Certification</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCertModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Site Modal -->
<div id="assignSiteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Site</h2>
            <button class="close-btn" onclick="closeModal('assignSiteModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="assign_site">
            <input type="hidden" name="artisan_id" value="<?php echo $artisan['artisan_id'] ?? ''; ?>">
            
            <div class="form-group">
                <label>Location Name *</label>
                <input type="text" name="location_name" placeholder="e.g., Main Factory, Building A" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Assignment Start Date</label>
                    <input type="date" name="assignment_start_date">
                </div>
                <div class="form-group">
                    <label>Assignment End Date</label>
                    <input type="date" name="assignment_end_date">
                </div>
            </div>
            
            <div class="form-group checkbox-group">
                <div class="checkbox-item">
                    <input type="checkbox" name="is_primary_site" id="site_primary">
                    <label for="site_primary" style="margin: 0;">Primary Site</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" name="is_active" id="site_active" checked>
                    <label for="site_active" style="margin: 0;">Active</label>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">✓ Assign Site</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignSiteModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_artisan">
    <input type="hidden" name="artisan_id" value="<?php echo $artisan['artisan_id'] ?? ''; ?>">
</form>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}

// Skills and Certifications management
let skillIndex = 1;
let certIndex = 1;

function addSkill() {
    const container = document.getElementById('skills-container');
    const skillEntry = document.createElement('div');
    skillEntry.className = 'skill-entry';
    skillEntry.style = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: end;';
    skillEntry.innerHTML = `
        <div class="form-group" style="flex: 2;">
            <label>Skill Name</label>
            <input type="text" name="skills[${skillIndex}][skill_name]" placeholder="e.g., HVAC Repair">
        </div>
        <div class="form-group" style="flex: 1;">
            <label>Category</label>
            <input type="text" name="skills[${skillIndex}][skill_category]" placeholder="e.g., Mechanical">
        </div>
        <div class="form-group" style="flex: 1;">
            <label>Proficiency</label>
            <select name="skills[${skillIndex}][proficiency_level]">
                <option value="beginner">Beginner</option>
                <option value="intermediate" selected>Intermediate</option>
                <option value="advanced">Advanced</option>
                <option value="expert">Expert</option>
            </select>
        </div>
        <div class="form-group" style="flex: 1;">
            <label>Experience (years)</label>
            <input type="number" name="skills[${skillIndex}][years_of_experience]" min="0" step="0.5" placeholder="2.5">
        </div>
        <button type="button" class="btn btn-danger" style="padding: 8px 12px;" onclick="removeSkill(this)">×</button>
    `;
    container.appendChild(skillEntry);
    skillIndex++;
}

function removeSkill(button) {
    button.closest('.skill-entry').remove();
}

function addCertification() {
    const container = document.getElementById('certs-container');
    const certEntry = document.createElement('div');
    certEntry.className = 'cert-entry';
    certEntry.style = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: end;';
    certEntry.innerHTML = `
        <div class="form-group" style="flex: 2;">
            <label>Certification Name</label>
            <input type="text" name="certifications[${certIndex}][certification_name]" placeholder="e.g., OSHA Safety Certification">
        </div>
        <div class="form-group" style="flex: 1;">
            <label>Issuing Body</label>
            <input type="text" name="certifications[${certIndex}][issuing_body]" placeholder="e.g., OSHA">
        </div>
        <div class="form-group" style="flex: 1;">
            <label>Issue Date</label>
            <input type="date" name="certifications[${certIndex}][issue_date]">
        </div>
        <div class="form-group" style="flex: 1;">
            <label>Expiry Date</label>
            <input type="date" name="certifications[${certIndex}][expiry_date]">
        </div>
        <button type="button" class="btn btn-danger" style="padding: 8px 12px;" onclick="removeCert(this)">×</button>
    `;
    container.appendChild(certEntry);
    certIndex++;
}

function removeCert(button) {
    button.closest('.cert-entry').remove();
}
</script>

</body>
</html>
