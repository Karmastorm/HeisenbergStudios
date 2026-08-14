<?php
/**
 * Shared bearer-token auth for the Schwab Sync API (api/*.php).
 * These endpoints are hit by IAN's scheduled sync script, not a logged-in
 * browser session, so there's no user/ownership check available here --
 * the token itself is the entire authorization boundary. Accepted for
 * this single-operator personal site (see docs/superpowers/specs/
 * 2026-08-13-schwab-sync-api-design.md, Security section).
 */
require_once __DIR__ . '/config.php';

function require_post_method(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'method not allowed']);
        exit;
    }
}

function require_api_token(): void {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $expected = getenv('API_SYNC_TOKEN') ?: '';
    $provided = '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        $provided = trim($m[1]);
    }
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'invalid JSON body']);
        exit;
    }
    return $data;
}

function require_valid_account(PDO $pdo, $accountId): array {
    $stmt = $pdo->prepare('SELECT * FROM brokerage_accounts WHERE id = :id');
    $stmt->execute(['id' => (int)$accountId]);
    $account = $stmt->fetch();
    if (!$account) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'account not found']);
        exit;
    }
    return $account;
}
