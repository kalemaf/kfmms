<?php
$body = file_get_contents('http://localhost:8001/work_order.php?edit=1');
if ($body === false) {
    $error = error_get_last();
    echo 'ERROR: ' . ($error['message'] ?? 'unknown');
} else {
    echo $body;
}
?>