<?php
/**
 * Authentication and access-control helpers.
 * Include this file at the top of every protected page (after config.php).
 */

require_once __DIR__ . '/config.php';

// Numeric access levels -- higher number = more privileges
const ACCESS_LEVELS = [
    'read_only'  => 1,
    'restricted' => 2,
    'editor'     => 3,
    'web_dev'    => 4,
    'webmaster'  => 5,
];

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'           => $_SESSION['user_id'],
        'username'     => $_SESSION['username'],
        'access_level' => $_SESSION['access_level'],
        'theme'        => $_SESSION['theme'] ?? 'default',
    ];
}

/**
 * Returns true if the logged-in user's access level is >= the required level.
 * $required can be an integer (1-5) or a level name string.
 */
function user_has_access(int|string $required): bool {
    if (!is_logged_in()) {
        return false;
    }
    $requiredLevel = is_string($required) ? (ACCESS_LEVELS[$required] ?? 99) : $required;
    return (int)$_SESSION['access_level'] >= $requiredLevel;
}

/**
 * Call at the top of a protected page. Redirects to login if the
 * user is not authenticated or does not meet the required level.
 */
function require_access(int|string $required): void {
    start_secure_session();
    if (!user_has_access($required)) {
        header('Location: /login.php?denied=1');
        exit;
    }
}

/**
 * Attempt to log a user in. Returns true on success.
 */
function attempt_login(string $username, string $password): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare(
        'SELECT id, username, password_hash, access_level, theme, is_active
         FROM users WHERE username = :username LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['access_level'] = (int)$user['access_level'];
    $_SESSION['theme']        = $user['theme'];

    $update = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
    $update->execute(['id' => $user['id']]);

    return true;
}

function logout(): void {
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Create a new user with a securely hashed password.
 */
function create_user(string $username, string $password, string $email, int $accessLevel = 1): bool {
    $pdo = get_db_connection();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, email, access_level)
         VALUES (:username, :hash, :email, :access_level)'
    );
    return $stmt->execute([
        'username'     => $username,
        'hash'         => $hash,
        'email'        => $email,
        'access_level' => $accessLevel,
    ]);
}
