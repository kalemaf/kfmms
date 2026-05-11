<?php
// simple JSON API for KPI metrics
header('Content-Type: application/json');
require_once __DIR__ . '/../config.inc.php';
session_save_path($session_save_path);
session_start();
require_once __DIR__ . '/../libraries/metrics.php';

$start = isset($_GET['start']) ? $_GET['start'] : null;
$end   = isset($_GET['end']) ? $_GET['end'] : null;

$data = [];
$data['mttr_seconds'] = calculate_mttr($start,$end);
$data['mttr_hours']   = $data['mttr_seconds'] / 3600;
$data['mtbf_seconds'] = calculate_mtbf();
$data['mtbf_hours']   = $data['mtbf_seconds'] / 3600;
$data['availability'] = availability($start,$end);
$data['uptime_hours'] = total_uptime($start,$end)/3600;

echo json_encode($data);
