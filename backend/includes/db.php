<?php
/**
 * Database Connection (Shared)
 * Uses PDO for secure, prepared statement support
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');         // Change if you set a MySQL password
define('DB_NAME', 'placement_db');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed. Please try again later.']));
}
