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

$upsertStmt = $pdo->prepare(
    "INSERT INTO trades (account_id, external_id, ticker, side, trade_type,
            strategy, open_date, open_price, open_qty, open_fees, close_date,
            close_price, close_fees, stop_loss, take_profit, notes, source)
     VALUES (:account_id, :external_id, :ticker, :side, :trade_type,
            :strategy, :open_date, :open_price, :open_qty, :open_fees,
            :close_date, :close_price, :close_fees, :stop_loss, :take_profit,
            :notes, 'api')
     ON DUPLICATE KEY UPDATE
         close_date = VALUES(close_date),
         close_price = VALUES(close_price),
         close_fees = VALUES(close_fees)"
);

$imported = 0;
$errors = [];
foreach ($rows as $i => $row) {
    $get = fn(string $key) => isset($row[$key]) && $row[$key] !== null ? trim((string)$row[$key]) : '';

    $externalId = $get('external_id');
    $ticker = strtoupper($get('ticker'));
    $side = strtolower($get('side'));
    $tradeType = strtolower($get('trade_type'));
    $openDate = $get('open_date');
    $openPrice = $row['open_price'] ?? null;
    $openQty = $row['open_qty'] ?? null;

    if ($externalId === '') {
        $errors[] = "row $i: missing external_id";
        continue;
    }
    if ($ticker === '') {
        $errors[] = "row $i: missing ticker";
        continue;
    }
    if (!in_array($side, ['long', 'short'], true)) {
        $errors[] = "row $i: invalid side \"$side\" (must be long or short)";
        continue;
    }
    if (!in_array($tradeType, ['day', 'swing'], true)) {
        $errors[] = "row $i: invalid trade_type \"$tradeType\" (must be day or swing)";
        continue;
    }
    if ($openDate === '' || strtotime($openDate) === false) {
        $errors[] = "row $i: invalid or missing open_date";
        continue;
    }
    if (!is_numeric($openPrice) || !is_numeric($openQty)) {
        $errors[] = "row $i: open_price and open_qty must be numbers";
        continue;
    }

    $closeDate = $get('close_date');
    if ($closeDate !== '' && strtotime($closeDate) === false) {
        $errors[] = "row $i: invalid close_date";
        continue;
    }

    $closePrice = $row['close_price'] ?? null;
    $closeFees = $row['close_fees'] ?? null;
    $openFees = $row['open_fees'] ?? null;
    $stopLoss = $row['stop_loss'] ?? null;
    $takeProfit = $row['take_profit'] ?? null;

    foreach (['close_price' => $closePrice, 'close_fees' => $closeFees, 'open_fees' => $openFees,
              'stop_loss' => $stopLoss, 'take_profit' => $takeProfit] as $field => $value) {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $errors[] = "row $i: $field must be a number";
            continue 2;
        }
    }

    $upsertStmt->execute([
        'account_id' => $accountId,
        'external_id' => $externalId,
        'ticker' => $ticker,
        'side' => $side,
        'trade_type' => $tradeType,
        'strategy' => $get('strategy') ?: null,
        'open_date' => date('Y-m-d', strtotime($openDate)),
        'open_price' => $openPrice,
        'open_qty' => $openQty,
        'open_fees' => ($openFees !== null && $openFees !== '') ? $openFees : 0,
        'close_date' => $closeDate !== '' ? date('Y-m-d', strtotime($closeDate)) : null,
        'close_price' => ($closePrice !== null && $closePrice !== '') ? $closePrice : null,
        'close_fees' => ($closeFees !== null && $closeFees !== '') ? $closeFees : null,
        'stop_loss' => ($stopLoss !== null && $stopLoss !== '') ? $stopLoss : null,
        'take_profit' => ($takeProfit !== null && $takeProfit !== '') ? $takeProfit : null,
        'notes' => $get('notes') ?: null,
    ]);
    $imported++;
}

header('Content-Type: application/json');
echo json_encode(['imported' => $imported, 'errors' => $errors]);
