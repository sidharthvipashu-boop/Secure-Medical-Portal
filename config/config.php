<?php

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access to configuration is forbidden.');
}

define('APP_NAME', 'Secure Medical Records Portal');
define('BASE_URL', '/SecureMedicalPortal/');
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

define('ENCRYPTION_KEY', 'S3cur3M3dP0rtal_AES256_Key_2025!');
define('ENCRYPTION_CIPHER', 'aes-256-cbc');

define('HOSPITAL_BRANCHES', [
    'Hospital A' => 'hospital_A',
    'Hospital B' => 'hospital_B',
    'Hospital C' => 'hospital_C',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
