<?php
/**
 * Developer License Generator
 * Allows developers to create licenses for companies during development/testing
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

session_start();

// Only allow developers and admins
$user_role = strtolower($_SESSION['role'] ?? '');
if ($user_role !== 'developer' && $user_role !== 'admin') {
    die('Access denied. Developer or Admin role required.');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_license'])) {
        $company_id = (int)($_POST['company_id'] ?? 0);
        $license_type = $_POST['license_type'] ?? 'basic';
        $purchased_seats = (int)($_POST['purchased_seats'] ?? 1);
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if ($company_id <= 0) {
            $error = 'Please select a company.';
        } else {
            // Check if company already has a license
            $check_stmt = $connection->prepare("SELECT license_id FROM company_licenses WHERE company_id = ? AND is_active = 1");
            $check_stmt->bind_param('i', $company_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $error = 'Company already has an active license.';
            } else {
                // Generate license key
                $license_key = generate_license_key();

                // Create license
                $stmt = $connection->prepare("INSERT INTO company_licenses (company_id, license_key, license_type, purchased_seats, used_seats, expires_at, is_active) VALUES (?, ?, ?, ?, 0, ?, 1)");
                if ($stmt) {
                    $stmt->bind_param('issis', $company_id, $license_key, $license_type, $purchased_seats, $expires_at);
                    if ($stmt->execute()) {
                        $message = "✅ License generated successfully!<br>License Key: <strong>$license_key</strong><br>Type: $license_type<br>Seats: $purchased_seats";
                    } else {
                        $error = 'Failed to create license: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Database error: ' . $connection->error;
                }
            }
            $check_stmt->close();
        }
    }
}

// Get companies without licenses
$companies = [];
if ($connection) {
    $result = $connection->query("
        SELECT c.company_id, c.company_name, c.company_email
        FROM companies c
        LEFT JOIN company_licenses cl ON c.company_id = cl.company_id AND cl.is_active = 1
        WHERE cl.license_id IS NULL
        ORDER BY c.company_name
    ");
    while ($row = $result->fetch_assoc()) {
        $companies[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer License Generator - KFMMS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, input[type="text"], input[type="number"], input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn:hover {
            background: #0056b3;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .companies-list {
            margin-top: 30px;
        }
        .companies-list h3 {
            margin-bottom: 15px;
            color: #666;
        }
        .company-item {
            padding: 10px;
            border: 1px solid #eee;
            margin-bottom: 5px;
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Developer License Generator</h1>

        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="company_id">Select Company:</label>
                <select name="company_id" id="company_id" required>
                    <option value="">Choose a company...</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company['company_id']; ?>">
                            <?php echo htmlspecialchars($company['company_name']); ?>
                            (<?php echo htmlspecialchars($company['company_email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="license_type">License Type:</label>
                <select name="license_type" id="license_type" required>
                    <option value="trial">Trial (5 users)</option>
                    <option value="basic" selected>Basic (25 users)</option>
                    <option value="professional">Professional (100 users)</option>
                    <option value="enterprise">Enterprise (Unlimited)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="purchased_seats">Number of Seats:</label>
                <input type="number" name="purchased_seats" id="purchased_seats" value="25" min="1" max="1000" required>
            </div>

            <div class="form-group">
                <label for="expires_at">Expiration Date (optional):</label>
                <input type="date" name="expires_at" id="expires_at">
                <small style="color: #666; display: block; margin-top: 5px;">Leave empty for no expiration</small>
            </div>

            <button type="submit" name="generate_license" class="btn">Generate License</button>
        </form>

        <div class="companies-list">
            <h3>Companies Without Licenses (<?php echo count($companies); ?>)</h3>
            <?php if (empty($companies)): ?>
                <p>All companies have licenses!</p>
            <?php else: ?>
                <?php foreach ($companies as $company): ?>
                    <div class="company-item">
                        <strong><?php echo htmlspecialchars($company['company_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($company['company_email']); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php?nav=admin_roles" style="color: #007bff; text-decoration: none;">← Back to Admin Panel</a>
        </div>
    </div>

    <script>
        // Auto-update seats based on license type
        document.getElementById('license_type').addEventListener('change', function() {
            const type = this.value;
            const seatsInput = document.getElementById('purchased_seats');

            switch(type) {
                case 'trial':
                    seatsInput.value = 5;
                    break;
                case 'basic':
                    seatsInput.value = 25;
                    break;
                case 'professional':
                    seatsInput.value = 100;
                    break;
                case 'enterprise':
                    seatsInput.value = 500;
                    break;
            }
        });
    </script>
</body>
</html>