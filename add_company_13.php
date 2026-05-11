<?php
require_once 'config.inc.php';

$stmt = $connection->prepare('INSERT INTO companies (company_id, company_name, company_email, contact_name, contact_phone, is_active) VALUES (?, ?, ?, ?, ?, 1)');
$stmt->execute([13, 'Moses and Brothers Enterprises Ltd', 'info@mosesbrothers.com', 'Moses', '0772123456']);
echo "Added company_id 13: Moses and Brothers Enterprises Ltd\n";