<?php
require_once __DIR__ . '/../includes/api_auth.php';

require_post_method();
require_api_token();

$pdo = get_db_connection();
$data = read_json_body();

$accountId = (int)($data['account_id'] ?? 0);
log_api_request(basename(__FILE__), $accountId);
require_valid_account($pdo, $accountId);

$rows = $data['rows'] ?? null;
if (!is_array($rows)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'rows must be an array']);
    exit;
}

$validRows = [];
$errors = [];
foreach ($rows as $i => $row) {
    $ticker = isset($row['ticker']) ? strtoupper(trim((string)$row['ticker'])) : '';
    $marketValue = $row['market_value'] ?? null;

    if ($ticker === '') {
        $errors[] = "row $i: missing ticker";
        continue;
    }
    if (!is_numeric($marketValue)) {
        $errors[] = "row $i: market_value must be a number";
        continue;
    }

    $validRows[] = ['ticker' => $ticker, 'market_value' => $marketValue];
}

$pdo->beginTransaction();
$deleteStmt = $pdo->prepare('DELETE FROM position_market_values WHERE account_id = :account_id');
$deleteStmt->execute(['account_id' => $accountId]);

$insertStmt = $pdo->prepare(
    'INSERT INTO position_market_values (account_id, ticker, market_value)
     VALUES (:account_id, :ticker, :market_value)'
);
$imported = 0;
foreach ($validRows as $row) {
    $insertStmt->execute([
        'account_id' => $accountId,
        'ticker' => $row['ticker'],
        'market_value' => $row['market_value'],
    ]);
    $imported++;
}
$pdo->commit();

header('Content-Type: application/json');
echo json_encode(['imported' => $imported, 'errors' => $errors]);
