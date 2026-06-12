<?php
/**
 * Database connection settings.
 * Update these values for your environment.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'site_portal');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
