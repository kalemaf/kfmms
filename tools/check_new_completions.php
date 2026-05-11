<?php
include_once(__DIR__ . '/../config.inc.php');
if (session_status() == PHP_SESSION_NONE) { @session_start(); }

header('Content-Type: application/json');

$last = $_SESSION['last_notif_check'] ?? $_SESSION['last_login'] ?? null;
$safe_last = $last ? mysqli_real_escape_string($connection, $last) : null;

// fetch recent completion audit entries and exclude those already seen by this user
$sql = "SELECT a.id, a.created_at, a.target_id as wo_id FROM audit_logs a WHERE a.action='complete' " .
       ($safe_last ? "AND a.created_at > '$safe_last' " : "AND a.created_at > '" . mysqli_real_escape_string($connection, date('Y-m-d H:i:s', strtotime('-24 hours'))) . "' ") .
       "ORDER BY a.created_at DESC";

$res = mysqli_query($connection, $sql);
$count = 0;
// load seen list for this user
$seen_file = __DIR__ . '/../logs/seen_completions_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', ($_SESSION['user'] ?? 'unknown')) . '.json';
$seen = array();
if (file_exists($seen_file)) {
  $j = @file_get_contents($seen_file);
  $seen = $j ? json_decode($j, true) : array();
  if (!is_array($seen)) $seen = array();
}

if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $aid = (int)$r['id'];
    if (!in_array($aid, $seen)) $count++;
  }
}

echo json_encode(['count' => $count]);

?>
