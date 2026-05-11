<?php
// View log file content
if (empty($_GET['file'])) {
  echo "No file specified";
  exit;
}

$filename = basename($_GET['file']); // Prevent directory traversal
$logfile = __DIR__ . '/../logs/' . $filename;

if (!file_exists($logfile)) {
  echo "(Log file not found)";
  exit;
}

// Only show last 50 lines and tail
$lines = file($logfile, FILE_IGNORE_NEW_LINES);
$recent = array_slice($lines, -50);
echo implode("\n", $recent);
