<?php
/**
 * Goods Receipt Management for CMMS
 * Plain HTML table-based design (old style)
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: inventory/goods_receipt.php' . $queryString);
exit;
?>