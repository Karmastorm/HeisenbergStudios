<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/trade_access.php';
start_secure_session();

require_access('read_only');

$pdo = get_db_connection();
$userId = (int)$_SESSION['user_id'];

$accounts = [];
$stmt = $pdo->prepare('SELECT * FROM brokerage_accounts WHERE user_id = :user_id ORDER BY brokerage_name, account_label');
$stmt->execute(['user_id' => $userId]);
$accounts = $stmt->fetchAll();

$message = '';
$error = '';
$importErrors = [];
$importedCount = 0;

$expectedColumns = [
    'ticker', 'side', 'trade_type', 'strategy', 'open_date', 'open_price', 'open_qty',
    'open_fees', 'close_date', 'close_price', 'close_fees', 'stop_loss', 'take_profit', 'notes',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountId = (int)($_POST['account_id'] ?? 0);
    $account = trade_log_get_account($pdo, $accountId, $userId);

    if (!$account) {
        $error = 'Select a valid account.';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a CSV file to upload.';
    } else {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($handle === false) {
            $error = 'Could not read the uploaded file.';
        } else {
            $header = fgetcsv($handle);
            if ($header === false) {
                $error = 'The file appears to be empty.';
            } else {
                if (isset($header[0])) {
                    $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
                }
                $header = array_map(fn($h) => strtolower(trim($h)), $header);
                $colIndex = [];
                foreach ($expectedColumns as $col) {
                    $idx = array_search($col, $header, true);
                    $colIndex[$col] = $idx === false ? null : $idx;
                }

                if ($colIndex['ticker'] === null || $colIndex['side'] === null || $colIndex['trade_type'] === null
                    || $colIndex['open_date'] === null || $colIndex['open_price'] === null || $colIndex['open_qty'] === null) {
                    $error = 'The CSV header must include at least: ticker, side, trade_type, open_date, open_price, open_qty.';
                } else {
                    $rowNum = 1;
                    $insertStmt = $pdo->prepare(
                        "INSERT INTO trades (account_id, ticker, side, trade_type, strategy, open_date,
                                open_price, open_qty, open_fees, close_date, close_price, close_fees,
                                stop_loss, take_profit, notes, source)
                         VALUES (:account_id, :ticker, :side, :trade_type, :strategy, :open_date,
                                :open_price, :open_qty, :open_fees, :close_date, :close_price, :close_fees,
                                :stop_loss, :take_profit, :notes, 'csv')"
                    );

                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNum++;
                        $get = fn(string $col) => $colIndex[$col] !== null && isset($row[$colIndex[$col]]) ? trim($row[$colIndex[$col]]) : '';

                        $ticker = strtoupper($get('ticker'));
                        $side = strtolower($get('side'));
                        $tradeType = strtolower($get('trade_type'));
                        $openDate = $get('open_date');
                        $openPrice = $get('open_price');
                        $openQty = $get('open_qty');

                        if ($ticker === '') {
                            $importErrors[] = "row $rowNum: missing ticker";
                            continue;
                        }
                        if (!in_array($side, ['long', 'short'], true)) {
                            $importErrors[] = "row $rowNum: invalid side \"$side\" (must be long or short)";
                            continue;
                        }
                        if (!in_array($tradeType, ['day', 'swing'], true)) {
                            $importErrors[] = "row $rowNum: invalid trade_type \"$tradeType\" (must be day or swing)";
                            continue;
                        }
                        if ($openDate === '' || strtotime($openDate) === false) {
                            $importErrors[] = "row $rowNum: invalid or missing open_date";
                            continue;
                        }
                        if (!is_numeric($openPrice) || !is_numeric($openQty)) {
                            $importErrors[] = "row $rowNum: open_price and open_qty must be numbers";
                            continue;
                        }

                        $closeDate = $get('close_date');
                        $closePrice = $get('close_price');
                        if ($closeDate !== '' && strtotime($closeDate) === false) {
                            $importErrors[] = "row $rowNum: invalid close_date";
                            continue;
                        }

                        $closeFees = $get('close_fees');
                        $openFees = $get('open_fees');
                        $stopLoss = $get('stop_loss');
                        $takeProfit = $get('take_profit');

                        if ($closePrice !== '' && !is_numeric($closePrice)) {
                            $importErrors[] = "row $rowNum: close_price must be a number";
                            continue;
                        }
                        if ($closeFees !== '' && !is_numeric($closeFees)) {
                            $importErrors[] = "row $rowNum: close_fees must be a number";
                            continue;
                        }
                        if ($openFees !== '' && !is_numeric($openFees)) {
                            $importErrors[] = "row $rowNum: open_fees must be a number";
                            continue;
                        }
                        if ($stopLoss !== '' && !is_numeric($stopLoss)) {
                            $importErrors[] = "row $rowNum: stop_loss must be a number";
                            continue;
                        }
                        if ($takeProfit !== '' && !is_numeric($takeProfit)) {
                            $importErrors[] = "row $rowNum: take_profit must be a number";
                            continue;
                        }

                        $insertStmt->execute([
                            'account_id' => $accountId,
                            'ticker' => $ticker,
                            'side' => $side,
                            'trade_type' => $tradeType,
                            'strategy' => $get('strategy') ?: null,
                            'open_date' => date('Y-m-d', strtotime($openDate)),
                            'open_price' => $openPrice,
                            'open_qty' => $openQty,
                            'open_fees' => $openFees !== '' ? $openFees : 0,
                            'close_date' => $closeDate !== '' ? date('Y-m-d', strtotime($closeDate)) : null,
                            'close_price' => $closePrice !== '' ? $closePrice : null,
                            'close_fees' => $closeFees !== '' ? $closeFees : null,
                            'stop_loss' => $stopLoss !== '' ? $stopLoss : null,
                            'take_profit' => $takeProfit !== '' ? $takeProfit : null,
                            'notes' => $get('notes') ?: null,
                        ]);
                        $importedCount++;
                    }
                    fclose($handle);
                    $message = "$importedCount trade(s) imported.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Trades - Heisenberg Studios</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=7">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=7">
    <link rel="stylesheet" href="../assets/css/main.css?v=7">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Import Trades from CSV</h1>

        <?php if ($message): ?><div class="msg-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (!empty($importErrors)): ?>
            <div class="msg-error">
                <?php echo count($importErrors); ?> row(s) skipped:
                <ul>
                    <?php foreach ($importErrors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($accounts)): ?>
            <p>Add a brokerage account on the <a href="metrics_tradelog.php">Trade Log</a> page before importing.</p>
        <?php else: ?>
            <div class="admin-form">
                <form method="post" enctype="multipart/form-data">
                    <label for="account_id">Import into account</label>
                    <select id="account_id" name="account_id" required>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo (int)$account['id']; ?>">
                                <?php echo htmlspecialchars($account['brokerage_name'] . ' — ' . $account['account_label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="csv_file">CSV File</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>

                    <p style="font-size:0.85rem; color:var(--color-text-soft); margin-top:0.75rem;">
                        Expected header columns (case-insensitive, any order): ticker, side, trade_type,
                        strategy, open_date, open_price, open_qty, open_fees, close_date, close_price,
                        close_fees, stop_loss, take_profit, notes. Only ticker, side, trade_type,
                        open_date, open_price, and open_qty are required per row.
                    </p>

                    <button type="submit">Import</button>
                </form>
            </div>
        <?php endif; ?>

        <p><a href="metrics_tradelog.php">&larr; Back to Trade Log</a></p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
