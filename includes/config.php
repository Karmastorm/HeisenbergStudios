<?php
/**
 * Database connection settings.
 *
 * Credentials are NOT stored in this file. They are read from
 * environment variables, which can be supplied either:
 *   - natively by the hosting environment (preferred for production), or
 *   - via a ".env" file in the project root (for local development).
 *
 * The .env file is git-ignored and must never be committed.
 * Copy ".env.example" to ".env" and fill in real values to get started.
 */

function load_env_file(string $path): void {
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

load_env_file(__DIR__ . '/../.env');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        if (DB_NAME === '' || DB_USER === '') {
            throw new RuntimeException(
                'Database is not configured. Set DB_HOST, DB_NAME, DB_USER, ' .
                'and DB_PASS via environment variables or a .env file ' .
                '(see .env.example).'
            );
        }

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
