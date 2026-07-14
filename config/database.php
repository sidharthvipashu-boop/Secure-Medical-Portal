<?php

require_once __DIR__ . '/config.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'secure_medical_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Is MySQL running in XAMPP, and did you import '
       . 'database/secure_medical_portal.sql? Details: ' . $e->getMessage());
}
