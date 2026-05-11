<?php
/**
 * Web runner to execute professional PM migration and conversion in one click.
 * WARNING: This runs DB migrations. Protect access appropriately.
 */
include_once __DIR__ . '/../config.inc.php';
require_once __DIR__ . '/../csrf.php';
session_save_path($session_save_path);
session_start();

function run_script_capture($path) {
    ob_start();
    include $path;
    $out = ob_get_clean();
    return $out;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = "CSRF token invalid.";
    } else {
        $out1 = run_script_capture(__DIR__ . '/add_pm_professional_structure.php');
        $out2 = run_script_capture(__DIR__ . '/convert_old_pm_to_professional.php');
        $message = "<h3>Migration Output</h3><pre>" . htmlspecialchars($out1 . "\n\n" . $out2) . "</pre>";
    }
}

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Run PM Migrations</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;padding:20px;">
<h1>Run PM Migrations (Professional)</h1>
<p><strong>Warning:</strong> This will create/alter PM tables and convert legacy data.</p>
<?php if ($message): ?>
    <?= $message ?>
<?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(generate_csrf_token())?>">
    <button type="submit">Run Migrations & Convert Legacy PM Data</button>
</form>
<p><a href="../pm.php">Return to PM Dashboard</a></p>
</body>
</html>
