<?php
require_once __DIR__ . '/../config.inc.php';
if (empty($debug_mode)) {
    http_response_code(404);
    exit;
}

echo '<h1>Developer tools are not available in production mode.</h1>';
