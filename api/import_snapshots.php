<?php
require_once __DIR__ . '/../includes/api_auth.php';

require_post_method();
require_api_token();

$pdo = get_db_connection();
$data = read_json_body();

$accountId = (int)($data['account_id'] ?? 0);
require_valid_account($pdo, $accountId);

$rows = $data['rows'] ?? null;
if (!is_array($rows)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'rows must be an array']);
    exit;
}

$upsertStmt = $pdo->prepare(
    'INSERT INTO account_value_snapshots (account_id, snapshot_date, total_value)
     VALUES (:account_id, :snapshot_date, :total_value)
     ON DUPLICATE KEY UPDATE total_value = VALUES(total_value)'
);

$imported = 0;
$errors = [];
foreach ($rows as $i => $row) {
    $date = isset($row['date']) ? trim((string)$row['date']) : '';
    $totalValue = $row['total_value'] ?? null;

    if ($date === '' || strtotime($date) === false) {
        $errors[] = "row $i: invalid or missing date";
        continue;
    }
    if (!is_numeric($totalValue)) {
        $errors[] = "row $i: total_value must be a number";
        continue;
    }

    $upsertStmt->execute([
        'account_id' => $accountId,
        'snapshot_date' => date('Y-m-d', strtotime($date)),
        'total_value' => $totalValue,
    ]);
    $imported++;
}

header('Content-Type: application/json');
echo json_encode(['imported' => $imported, 'errors' => $errors]);
