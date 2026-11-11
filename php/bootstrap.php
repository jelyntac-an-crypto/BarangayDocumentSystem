<?php
declare(strict_types=1);

//Load privilege configuration outside the web root.
$configPath = dirname(__DIR__,3)
    . DIRECTORY_SEPARATOR . 'private'
    . DIRECTORY_SEPARATOR . 'BarangayDocumentSystem'
    . DIRECTORY_SEPARATOR . 'config.php';

if (!is_file($configPath)) {
    error_log('Private configuration file is missing.');
    http_response_code(500);
    exit('System configuration error.');
}

$config = require $configPath;