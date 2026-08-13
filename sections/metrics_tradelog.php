<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/trade_access.php';
require_once __DIR__ . '/../includes/trade_metrics.php';
start_secure_session();

require_access('read_only');

$pdo = get_db_connection();
$userId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// ------------------------------------------------------------
// Account add / edit / delete
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_account') {
    $brokerageName = trim($_POST['brokerage_name'] ?? '');
    $accountLabel = trim($_POST['account_label'] ?? '');
    $beginningBalance = trim($_POST['beginning_balance'] ?? '');
    $tradingYear = trim($_POST['trading_year'] ?? '');

    if ($brokerageName === '' || $accountLabel === '') {
        $error = 'Brokerage name and account label are required.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO brokerage_accounts (user_id, brokerage_name, account_label, beginning_balance, trading_year)
             VALUES (:user_id, :brokerage_name, :account_label, :beginning_balance, :trading_year)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'brokerage_name' => $brokerageName,
            'account_label' => $accountLabel,
            'beginning_balance' => $beginningBalance !== '' ? $beginningBalance : null,
            'trading_year' => $tradingYear !== '' ? (int)$tradingYear : null,
        ]);
        $message = 'Account added.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_account') {
    $accountId = (int)($_POST['account_id'] ?? 0);
    $existing = trade_log_get_account($pdo, $accountId, $userId);
    if (!$existing) {
        $error = 'Account not found.';
    } else {
        $brokerageName = trim($_POST['brokerage_name'] ?? '');
        $accountLabel = trim($_POST['account_label'] ?? '');
        $beginningBalance = trim($_POST['beginning_balance'] ?? '');
        $tradingYear = trim($_POST['trading_year'] ?? '');

        if ($brokerageName === '' || $accountLabel === '') {
            $error = 'Brokerage name and account label are required.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE brokerage_accounts
                 SET brokerage_name = :brokerage_name, account_label = :account_label,
                     beginning_balance = :beginning_balance, trading_year = :trading_year
                 WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                'brokerage_name' => $brokerageName,
                'account_label' => $accountLabel,
                'beginning_balance' => $beginningBalance !== '' ? $beginningBalance : null,
                'trading_year' => $tradingYear !== '' ? (int)$tradingYear : null,
                'id' => $accountId,
                'user_id' => $userId,
            ]);
            $message = 'Account updated.';
        }
    }
} elseif (isset($_GET['delete_account'])) {
    $accountId = (int)$_GET['delete_account'];
    $existing = trade_log_get_account($pdo, $accountId, $userId);
    if ($existing) {
        $stmt = $pdo->prepare('DELETE FROM brokerage_accounts WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $accountId, 'user_id' => $userId]);
        $message = 'Account and its trades deleted.';
    } else {
        $error = 'Account not found.';
    }
}

$accounts = [];
$stmt = $pdo->prepare('SELECT * FROM brokerage_accounts WHERE user_id = :user_id ORDER BY brokerage_name, account_label');
$stmt->execute(['user_id' => $userId]);
$accounts = $stmt->fetchAll();

$editAccount = null;
if (isset($_GET['edit_account'])) {
    $editAccount = trade_log_get_account($pdo, (int)$_GET['edit_account'], $userId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade Log - Heisenberg Studios</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=5">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=5">
    <link rel="stylesheet" href="../assets/css/main.css?v=5">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Trade Log</h1>

        <?php if ($message): ?><div class="msg-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <section class="trade-accounts">
            <h2>Your Brokerage Accounts</h2>

            <div class="admin-form">
                <h3><?php echo $editAccount ? 'Edit Account' : 'Add Account'; ?></h3>
                <form method="post" action="metrics_tradelog.php<?php echo $editAccount ? '?edit_account=' . (int)$editAccount['id'] : ''; ?>">
                    <input type="hidden" name="action" value="<?php echo $editAccount ? 'edit_account' : 'add_account'; ?>">
                    <?php if ($editAccount): ?>
                        <input type="hidden" name="account_id" value="<?php echo (int)$editAccount['id']; ?>">
                    <?php endif; ?>

                    <label for="brokerage_name">Brokerage</label>
                    <input type="text" id="brokerage_name" name="brokerage_name" list="brokerage-names" required
                           value="<?php echo htmlspecialchars($editAccount['brokerage_name'] ?? ''); ?>">
                    <datalist id="brokerage-names">
                        <?php foreach (array_unique(array_column($accounts, 'brokerage_name')) as $name): ?>
                            <option value="<?php echo htmlspecialchars($name); ?>">
                        <?php endforeach; ?>
                    </datalist>

                    <label for="account_label">Account Label</label>
                    <input type="text" id="account_label" name="account_label" required
                           placeholder="e.g. Roth IRA, Main Margin"
                           value="<?php echo htmlspecialchars($editAccount['account_label'] ?? ''); ?>">

                    <label for="beginning_balance">Beginning Balance (optional)</label>
                    <input type="number" step="0.01" id="beginning_balance" name="beginning_balance"
                           value="<?php echo htmlspecialchars($editAccount['beginning_balance'] ?? ''); ?>">

                    <label for="trading_year">Trading Year (optional)</label>
                    <input type="number" id="trading_year" name="trading_year"
                           value="<?php echo htmlspecialchars($editAccount['trading_year'] ?? ''); ?>">

                    <button type="submit"><?php echo $editAccount ? 'Update Account' : 'Add Account'; ?></button>
                    <?php if ($editAccount): ?>
                        <a href="metrics_tradelog.php" style="margin-left: 1rem; font-size: 0.9rem;">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($accounts)): ?>
                <p>Add a brokerage account above to start logging trades.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Brokerage</th>
                            <th>Label</th>
                            <th>Beginning Balance</th>
                            <th>Trading Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($account['brokerage_name']); ?></td>
                                <td><?php echo htmlspecialchars($account['account_label']); ?></td>
                                <td><?php echo $account['beginning_balance'] !== null ? '$' . number_format((float)$account['beginning_balance'], 2) : '&mdash;'; ?></td>
                                <td><?php echo htmlspecialchars((string)($account['trading_year'] ?? '—')); ?></td>
                                <td class="admin-actions">
                                    <a href="metrics_tradelog.php?edit_account=<?php echo (int)$account['id']; ?>">Edit</a>
                                    <a href="metrics_tradelog.php?delete_account=<?php echo (int)$account['id']; ?>"
                                       onclick="return confirm('Delete this account and ALL of its logged trades? This cannot be undone.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
