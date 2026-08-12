<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$theme = $_POST['theme'] ?? '';
$allowedThemes = ['light', 'dark'];

if (!in_array($theme, $allowedThemes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid theme']);
    exit;
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('UPDATE users SET theme = :theme WHERE id = :id');
$stmt->execute(['theme' => $theme, 'id' => $_SESSION['user_id']]);

$_SESSION['theme'] = $theme;

echo json_encode(['success' => true]);
