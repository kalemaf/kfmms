<?php
/**
 * Purchase Orders Management for CMMS
 * Plain HTML table-based design (old style)
 */

require_once 'config.inc.php';
require_once 'common.inc.php';

$queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: inventory/purchase_orders.php' . $queryString);
exit;
?>
</div>

<p style="margin-top: 20px;">
    <a href="index.php?nav=dashboard">Back to Dashboard</a>
</p>