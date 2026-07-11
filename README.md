<?php
/**
 * =============================================================================
 *  database.php  --  MySQL database connection (PDO)
 * =============================================================================
 *
 *  WHAT THIS FILE DOES
 *  -------------------
 *  Creates a single reusable connection to the MySQL database using PDO
 *  (PHP Data Objects). Every other page includes this file to get the $pdo
 *  object and run queries.
 *
 *  WHY PDO + PREPARED STATEMENTS?
 *  ------------------------------
 *  PDO lets us use PREPARED STATEMENTS. Prepared statements send the SQL
 *  structure and the user-supplied values to MySQL SEPARATELY, so user input can
 *  never be interpreted as SQL commands. This blocks SQL Injection attacks and
 *  supports Project Idea 2 (keeping stored data trustworthy). We use prepared
 *  statements for EVERY query in this project.
 * =============================================================================
 */

require_once __DIR__ . '/config.php';

/* -----------------------------------------------------------------------------
 * Database credentials -- default XAMPP values.
 * On a fresh XAMPP install the MySQL user is 'root' with an empty password.
 * ---------------------------------------------------------------------------*/
define('DB_HOST', 'localhost');
define('DB_NAME', 'secure_medical_portal');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default: empty password

try {
    // DSN = Data Source Name: tells PDO which driver, host, db and charset to use.
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    // PDO options that make the connection safer and easier to work with:
    $options = [
        // Throw exceptions on errors so we notice problems instead of silent failures.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Return rows as associative arrays (column-name => value).
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Use REAL prepared statements on the MySQL server (not emulated ones).
        // This is important for the SQL-injection protection to be genuine.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Create the connection. $pdo is now available to any file that includes this.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // If we cannot connect, stop and show a friendly message.
    // We do NOT print the raw error to users in production (it can leak details),
    // but for a student/dev environment a short hint is helpful.
    http_response_code(500);
    exit('Database connection failed. Is MySQL running in XAMPP, and did you import '
       . 'database/secure_medical_portal.sql? Details: ' . $e->getMessage());
}
