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

$expectedColumns = ['date', 'total_value'];

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

                if ($colIndex['date'] === null || $colIndex['total_value'] === null) {
                    $error = 'The CSV header must include: date, total_value.';
                } else {
                    $rowNum = 1;
                    $upsertStmt = $pdo->prepare(
                        'INSERT INTO account_value_snapshots (account_id, snapshot_date, total_value)
                         VALUES (:account_id, :snapshot_date, :total_value)
                         ON DUPLICATE KEY UPDATE total_value = VALUES(total_value)'
                    );

                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNum++;
                        $get = fn(string $col) => $colIndex[$col] !== null && isset($row[$colIndex[$col]]) ? trim($row[$colIndex[$col]]) : '';

                        $date = $get('date');
                        $totalValue = $get('total_value');

                        if ($date === '' || strtotime($date) === false) {
                            $importErrors[] = "row $rowNum: invalid or missing date";
                            continue;
                        }
                        if ($totalValue === '' || !is_numeric($totalValue)) {
                            $importErrors[] = "row $rowNum: total_value must be a number";
                            continue;
                        }

                        $upsertStmt->execute([
                            'account_id' => $accountId,
                            'snapshot_date' => date('Y-m-d', strtotime($date)),
                            'total_value' => $totalValue,
                        ]);
                        $importedCount++;
                    }
                    fclose($handle);
                    $message = "$importedCount day(s) imported (existing dates for this account were updated, not duplicated).";
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
    <title>Import Daily Account Values - Heisenberg Studios</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=7">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=7">
    <link rel="stylesheet" href="../assets/css/main.css?v=7">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Import Daily Account Values</h1>
        <p style="margin-bottom:1rem; color:var(--color-text-soft); font-size:0.9rem;">
            Once an account has a full set of daily values imported, the Account Balance chart
            switches from trade-close-only points to this true daily curve automatically.
        </p>

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
                        Expected header columns (case-insensitive, any order): date, total_value.
                        Re-importing a date that already exists for this account updates it rather
                        than creating a duplicate.
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
