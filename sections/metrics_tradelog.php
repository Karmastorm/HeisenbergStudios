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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add_trade', 'edit_trade'], true)) {
    $isEdit = $_POST['action'] === 'edit_trade';
    $accountId = (int)($_POST['account_id'] ?? 0);
    $account = trade_log_get_account($pdo, $accountId, $userId);

    if (!$account) {
        $error = 'Select a valid account.';
    } else {
        $ticker = strtoupper(trim($_POST['ticker'] ?? ''));
        $side = $_POST['side'] ?? '';
        $tradeType = $_POST['trade_type'] ?? '';
        $strategy = trim($_POST['strategy'] ?? '') ?: null;
        $openDate = $_POST['open_date'] ?? '';
        $openPrice = trim($_POST['open_price'] ?? '');
        $openQty = trim($_POST['open_qty'] ?? '');
        $openFees = trim($_POST['open_fees'] ?? '') ?: '0';
        $closeDate = trim($_POST['close_date'] ?? '') ?: null;
        $closePrice = trim($_POST['close_price'] ?? '') ?: null;
        $closeFees = trim($_POST['close_fees'] ?? '') ?: null;
        $stopLoss = trim($_POST['stop_loss'] ?? '') ?: null;
        $takeProfit = trim($_POST['take_profit'] ?? '') ?: null;
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if ($ticker === '' || !in_array($side, ['long', 'short'], true) || !in_array($tradeType, ['day', 'swing'], true)
            || $openDate === '' || $openPrice === '' || $openQty === '') {
            $error = 'Ticker, side, trade type, open date, open price, and quantity are required.';
        } elseif ($isEdit) {
            $tradeId = (int)($_POST['trade_id'] ?? 0);
            $existingTrade = trade_log_get_trade($pdo, $tradeId, $userId);
            if (!$existingTrade) {
                $error = 'Trade not found.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE trades SET account_id = :account_id, ticker = :ticker, side = :side,
                            trade_type = :trade_type, strategy = :strategy, open_date = :open_date,
                            open_price = :open_price, open_qty = :open_qty, open_fees = :open_fees,
                            close_date = :close_date, close_price = :close_price, close_fees = :close_fees,
                            stop_loss = :stop_loss, take_profit = :take_profit, notes = :notes
                     WHERE id = :id'
                );
                $stmt->execute([
                    'account_id' => $accountId, 'ticker' => $ticker, 'side' => $side,
                    'trade_type' => $tradeType, 'strategy' => $strategy, 'open_date' => $openDate,
                    'open_price' => $openPrice, 'open_qty' => $openQty, 'open_fees' => $openFees,
                    'close_date' => $closeDate, 'close_price' => $closePrice, 'close_fees' => $closeFees,
                    'stop_loss' => $stopLoss, 'take_profit' => $takeProfit, 'notes' => $notes,
                    'id' => $tradeId,
                ]);
                $message = 'Trade updated.';
            }
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO trades (account_id, ticker, side, trade_type, strategy, open_date,
                        open_price, open_qty, open_fees, close_date, close_price, close_fees,
                        stop_loss, take_profit, notes, source)
                 VALUES (:account_id, :ticker, :side, :trade_type, :strategy, :open_date,
                        :open_price, :open_qty, :open_fees, :close_date, :close_price, :close_fees,
                        :stop_loss, :take_profit, :notes, 'manual')"
            );
            $stmt->execute([
                'account_id' => $accountId, 'ticker' => $ticker, 'side' => $side,
                'trade_type' => $tradeType, 'strategy' => $strategy, 'open_date' => $openDate,
                'open_price' => $openPrice, 'open_qty' => $openQty, 'open_fees' => $openFees,
                'close_date' => $closeDate, 'close_price' => $closePrice, 'close_fees' => $closeFees,
                'stop_loss' => $stopLoss, 'take_profit' => $takeProfit, 'notes' => $notes,
            ]);
            $message = 'Trade added.';
        }
    }
} elseif (isset($_GET['delete_trade'])) {
    $tradeId = (int)$_GET['delete_trade'];
    $existingTrade = trade_log_get_trade($pdo, $tradeId, $userId);
    if ($existingTrade) {
        $stmt = $pdo->prepare('DELETE FROM trades WHERE id = :id');
        $stmt->execute(['id' => $tradeId]);
        $message = 'Trade deleted.';
    } else {
        $error = 'Trade not found.';
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

$userAccountIds = trade_log_user_account_ids($pdo, $userId);
$selectedAccountIds = isset($_GET['accounts']) && is_array($_GET['accounts'])
    ? array_values(array_intersect($userAccountIds, array_map('intval', $_GET['accounts'])))
    : $userAccountIds;

$stats = fetch_trade_stats($pdo, $selectedAccountIds);

$balanceHistory = fetch_balance_history($pdo, $selectedAccountIds);
$avgWinSparkline = fetch_recent_trade_values($pdo, $selectedAccountIds, 'win');
$avgLossSparkline = fetch_recent_trade_values($pdo, $selectedAccountIds, 'loss');
$winRateSparkline = fetch_win_rate_trend($pdo, $selectedAccountIds);

function sparkline_trend_class(array $values): string {
    if (count($values) < 2) {
        return 'sparkline-trend-flat';
    }
    $first = reset($values);
    $last = end($values);
    if ($last > $first) return 'sparkline-trend-up';
    if ($last < $first) return 'sparkline-trend-down';
    return 'sparkline-trend-flat';
}

function sparkline_trend_arrow(array $values): string {
    if (count($values) < 2) {
        return '&ndash;';
    }
    $first = reset($values);
    $last = end($values);
    if ($last > $first) return '&#9650;';
    if ($last < $first) return '&#9660;';
    return '&ndash;';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$totalTrades = fetch_trades_count($pdo, $selectedAccountIds);
$totalPages = max(1, (int)ceil($totalTrades / $perPage));
$trades = fetch_trades_page($pdo, $selectedAccountIds, $page, $perPage);

$editTrade = null;
if (isset($_GET['edit_trade'])) {
    $editTrade = trade_log_get_trade($pdo, (int)$_GET['edit_trade'], $userId);
}

$strategySuggestions = [];
if (!empty($userAccountIds)) {
    $placeholders = implode(',', array_fill(0, count($userAccountIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT strategy FROM trades WHERE account_id IN ($placeholders) AND strategy IS NOT NULL AND strategy != ''"
    );
    $stmt->execute($userAccountIds);
    $strategySuggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function trade_stat_class(float $value): string {
    if ($value > 0.0001) return 'trade-stat-positive';
    if ($value < -0.0001) return 'trade-stat-negative';
    return '';
}

function format_money(?float $value): string {
    return $value === null ? '&mdash;' : '$' . number_format($value, 2);
}

function format_percent(?float $value): string {
    return $value === null ? '&mdash;' : number_format($value, 1) . '%';
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

        <?php if (count($accounts) > 1): ?>
            <form method="get" class="account-filter">
                <?php foreach ($accounts as $account): ?>
                    <label>
                        <input type="checkbox" name="accounts[]" value="<?php echo (int)$account['id']; ?>"
                               <?php echo in_array((int)$account['id'], $selectedAccountIds, true) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($account['brokerage_name'] . ' — ' . $account['account_label']); ?>
                    </label>
                <?php endforeach; ?>
                <button type="submit" class="admin-form-inline-submit" style="padding:0.3rem 0.9rem; background:var(--color-accent); color:var(--color-accent-fg); border:none; border-radius:4px; cursor:pointer;">Apply Filter</button>
            </form>
        <?php endif; ?>

        <section class="trade-dashboard">
            <div class="trade-stat-tile">
                <div class="stat-label">Total Trades</div>
                <div class="stat-value"><?php echo (int)$stats['overview']['total_trades']; ?></div>
            </div>
            <div class="trade-stat-tile">
                <div class="stat-label">Open Trades</div>
                <div class="stat-value"><?php echo (int)$stats['overview']['open_trades']; ?></div>
            </div>
            <div class="trade-stat-tile">
                <div class="stat-label">Win Rate</div>
                <div class="stat-value"><?php echo format_percent($stats['overview']['win_rate']); ?></div>
                <?php if (!empty($winRateSparkline)): ?>
                    <div class="sparkline-wrap sparkline-neutral">
                        <?php echo render_sparkline_svg($winRateSparkline); ?>
                        <span class="<?php echo sparkline_trend_class($winRateSparkline); ?>"><?php echo sparkline_trend_arrow($winRateSparkline); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="trade-stat-tile <?php echo trade_stat_class($stats['overview']['net_pnl_dollars']); ?>">
                <div class="stat-label">Net PnL</div>
                <div class="stat-value <?php echo trade_stat_class($stats['overview']['net_pnl_dollars']); ?>"><?php echo format_money($stats['overview']['net_pnl_dollars']); ?></div>
            </div>
            <div class="trade-stat-tile trade-stat-positive">
                <div class="stat-label">Avg Win</div>
                <div class="stat-value trade-stat-positive"><?php echo format_money($stats['overview']['avg_win_dollars']); ?></div>
                <?php if (!empty($avgWinSparkline)): ?>
                    <div class="sparkline-wrap sparkline-positive">
                        <?php echo render_sparkline_svg($avgWinSparkline); ?>
                        <span class="<?php echo sparkline_trend_class($avgWinSparkline); ?>"><?php echo sparkline_trend_arrow($avgWinSparkline); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="trade-stat-tile trade-stat-negative">
                <div class="stat-label">Avg Loss</div>
                <div class="stat-value trade-stat-negative"><?php echo format_money($stats['overview']['avg_loss_dollars']); ?></div>
                <?php if (!empty($avgLossSparkline)): ?>
                    <div class="sparkline-wrap sparkline-negative">
                        <?php echo render_sparkline_svg($avgLossSparkline); ?>
                        <span class="<?php echo sparkline_trend_class($avgLossSparkline); ?>"><?php echo sparkline_trend_arrow($avgLossSparkline); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="trade-stat-tile">
                <div class="stat-label">Avg R-Multiple</div>
                <div class="stat-value"><?php echo $stats['overview']['avg_r_multiple'] === null ? '&mdash;' : number_format($stats['overview']['avg_r_multiple'], 2) . 'R'; ?></div>
            </div>
            <?php if ($stats['account_growth_percent'] !== null): ?>
                <div class="trade-stat-tile <?php echo trade_stat_class($stats['account_growth_percent']); ?>">
                    <div class="stat-label">Account Growth</div>
                    <div class="stat-value <?php echo trade_stat_class($stats['account_growth_percent']); ?>"><?php echo format_percent($stats['account_growth_percent']); ?></div>
                </div>
            <?php endif; ?>
        </section>

        <?php if (count($balanceHistory) >= 2): ?>
            <section class="balance-chart-wrap">
                <h2>Account Balance</h2>
                <canvas id="balance-chart" role="img" aria-label="Account balance over time"></canvas>
                <script type="application/json" id="balance-chart-data"><?php echo json_encode($balanceHistory); ?></script>
            </section>
        <?php else: ?>
            <section class="balance-chart-wrap">
                <h2>Account Balance</h2>
                <p>Log a closed trade to see your balance history.</p>
            </section>
        <?php endif; ?>

        <section class="trade-breakdowns">
            <table class="admin-table">
                <caption style="text-align:left; font-weight:600; padding:0.5rem 0;">Day of Week</caption>
                <thead><tr><th>Day</th><th>Trades</th><th>PnL</th></tr></thead>
                <tbody>
                    <?php foreach ($stats['by_day_of_week'] as $day => $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($day); ?></td>
                            <td><?php echo (int)$row['trades']; ?></td>
                            <td class="<?php echo trade_stat_class($row['pnl']); ?>"><?php echo format_money($row['pnl']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="admin-table">
                <caption style="text-align:left; font-weight:600; padding:0.5rem 0;">Long vs. Short</caption>
                <thead><tr><th>Side</th><th>Trades</th><th>PnL</th></tr></thead>
                <tbody>
                    <?php foreach ($stats['by_side'] as $side => $row): ?>
                        <tr>
                            <td><?php echo ucfirst($side); ?></td>
                            <td><?php echo (int)$row['trades']; ?></td>
                            <td class="<?php echo trade_stat_class($row['pnl']); ?>"><?php echo format_money($row['pnl']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="admin-table">
                <caption style="text-align:left; font-weight:600; padding:0.5rem 0;">Day vs. Swing</caption>
                <thead><tr><th>Type</th><th>Trades</th><th>PnL</th></tr></thead>
                <tbody>
                    <?php foreach ($stats['by_trade_type'] as $type => $row): ?>
                        <tr>
                            <td><?php echo ucfirst($type); ?></td>
                            <td><?php echo (int)$row['trades']; ?></td>
                            <td class="<?php echo trade_stat_class($row['pnl']); ?>"><?php echo format_money($row['pnl']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="admin-table">
                <caption style="text-align:left; font-weight:600; padding:0.5rem 0;">By Strategy</caption>
                <thead><tr><th>Strategy</th><th>Trades</th><th>Win Rate</th><th>Net PnL</th><th>Avg Gain</th><th>Avg Loss</th></tr></thead>
                <tbody>
                    <?php foreach ($stats['by_strategy'] as $strategy => $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($strategy); ?></td>
                            <td><?php echo (int)$row['trades']; ?></td>
                            <td><?php echo format_percent($row['win_rate']); ?></td>
                            <td class="<?php echo trade_stat_class($row['net_pnl']); ?>"><?php echo format_money($row['net_pnl']); ?></td>
                            <td class="trade-stat-positive"><?php echo format_money($row['avg_gain']); ?></td>
                            <td class="trade-stat-negative"><?php echo format_money($row['avg_loss']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats['by_strategy'])): ?>
                        <tr><td colspan="6">No trades logged yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="trade-journal">
            <h2>Journal</h2>

            <?php if (empty($accounts)): ?>
                <p>Add a brokerage account above before logging trades.</p>
            <?php else: ?>
                <div class="admin-form">
                    <h3><?php echo $editTrade ? 'Edit Trade' : 'Add Trade'; ?></h3>
                    <form method="post" action="metrics_tradelog.php<?php echo $editTrade ? '?edit_trade=' . (int)$editTrade['id'] : ''; ?>">
                        <input type="hidden" name="action" value="<?php echo $editTrade ? 'edit_trade' : 'add_trade'; ?>">
                        <?php if ($editTrade): ?>
                            <input type="hidden" name="trade_id" value="<?php echo (int)$editTrade['id']; ?>">
                        <?php endif; ?>

                        <label for="account_id">Account</label>
                        <select id="account_id" name="account_id" required>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo (int)$account['id']; ?>"
                                    <?php echo (isset($editTrade['account_id']) && (int)$editTrade['account_id'] === (int)$account['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($account['brokerage_name'] . ' — ' . $account['account_label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="ticker">Ticker</label>
                        <input type="text" id="ticker" name="ticker" required
                               value="<?php echo htmlspecialchars($editTrade['ticker'] ?? ''); ?>">

                        <label for="side">Side</label>
                        <select id="side" name="side" required>
                            <option value="long" <?php echo (($editTrade['side'] ?? '') === 'long') ? 'selected' : ''; ?>>Long</option>
                            <option value="short" <?php echo (($editTrade['side'] ?? '') === 'short') ? 'selected' : ''; ?>>Short</option>
                        </select>

                        <label for="trade_type">Trade Type</label>
                        <select id="trade_type" name="trade_type" required>
                            <option value="day" <?php echo (($editTrade['trade_type'] ?? '') === 'day') ? 'selected' : ''; ?>>Day</option>
                            <option value="swing" <?php echo (($editTrade['trade_type'] ?? '') === 'swing') ? 'selected' : ''; ?>>Swing</option>
                        </select>

                        <label for="strategy">Strategy</label>
                        <input type="text" id="strategy" name="strategy" list="strategy-suggestions"
                               value="<?php echo htmlspecialchars($editTrade['strategy'] ?? ''); ?>">
                        <datalist id="strategy-suggestions">
                            <?php foreach ($strategySuggestions as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>">
                            <?php endforeach; ?>
                        </datalist>

                        <label for="open_date">Open Date</label>
                        <input type="date" id="open_date" name="open_date" required
                               value="<?php echo htmlspecialchars($editTrade['open_date'] ?? ''); ?>">

                        <label for="open_price">Open Price</label>
                        <input type="number" step="0.0001" id="open_price" name="open_price" required
                               value="<?php echo htmlspecialchars($editTrade['open_price'] ?? ''); ?>">

                        <label for="open_qty">Quantity</label>
                        <input type="number" step="0.0001" id="open_qty" name="open_qty" required
                               value="<?php echo htmlspecialchars($editTrade['open_qty'] ?? ''); ?>">

                        <label for="open_fees">Open Fees</label>
                        <input type="number" step="0.01" id="open_fees" name="open_fees"
                               value="<?php echo htmlspecialchars($editTrade['open_fees'] ?? '0'); ?>">

                        <label for="close_date">Close Date (leave blank if still open)</label>
                        <input type="date" id="close_date" name="close_date"
                               value="<?php echo htmlspecialchars($editTrade['close_date'] ?? ''); ?>">

                        <label for="close_price">Close Price</label>
                        <input type="number" step="0.0001" id="close_price" name="close_price"
                               value="<?php echo htmlspecialchars($editTrade['close_price'] ?? ''); ?>">

                        <label for="close_fees">Close Fees</label>
                        <input type="number" step="0.01" id="close_fees" name="close_fees"
                               value="<?php echo htmlspecialchars($editTrade['close_fees'] ?? ''); ?>">

                        <label for="stop_loss">Stop Loss</label>
                        <input type="number" step="0.0001" id="stop_loss" name="stop_loss"
                               value="<?php echo htmlspecialchars($editTrade['stop_loss'] ?? ''); ?>">

                        <label for="take_profit">Take Profit</label>
                        <input type="number" step="0.0001" id="take_profit" name="take_profit"
                               value="<?php echo htmlspecialchars($editTrade['take_profit'] ?? ''); ?>">

                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes"><?php echo htmlspecialchars($editTrade['notes'] ?? ''); ?></textarea>

                        <button type="submit"><?php echo $editTrade ? 'Update Trade' : 'Add Trade'; ?></button>
                        <?php if ($editTrade): ?>
                            <a href="metrics_tradelog.php" style="margin-left: 1rem; font-size: 0.9rem;">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>

                <table class="trade-journal-table">
                    <thead>
                        <tr>
                            <th>Account</th><th>Ticker</th><th>Side</th><th>Type</th><th>Strategy</th>
                            <th>Open</th><th>Close</th><th>PnL $</th><th>PnL %</th><th>R</th><th>Result</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trades as $trade): ?>
                            <?php $pnl = compute_trade_pnl($trade); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trade['brokerage_name'] . ' — ' . $trade['account_label']); ?></td>
                                <td><?php echo htmlspecialchars($trade['ticker']); ?></td>
                                <td><?php echo ucfirst($trade['side']); ?></td>
                                <td><?php echo ucfirst($trade['trade_type']); ?></td>
                                <td><?php echo htmlspecialchars($trade['strategy'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($trade['open_date']); ?></td>
                                <td><?php echo htmlspecialchars($trade['close_date'] ?? 'Open'); ?></td>
                                <td class="<?php echo $pnl ? trade_stat_class($pnl['pnl_dollars']) : ''; ?>"><?php echo $pnl ? format_money($pnl['pnl_dollars']) : '&mdash;'; ?></td>
                                <td><?php echo $pnl ? format_percent($pnl['pnl_percent']) : '&mdash;'; ?></td>
                                <td><?php echo ($pnl && $pnl['r_multiple'] !== null) ? number_format($pnl['r_multiple'], 2) . 'R' : '-'; ?></td>
                                <td><?php echo $pnl ? ucfirst($pnl['result']) : 'Open'; ?></td>
                                <td class="admin-actions">
                                    <a href="metrics_tradelog.php?edit_trade=<?php echo (int)$trade['id']; ?>">Edit</a>
                                    <a href="metrics_tradelog.php?delete_trade=<?php echo (int)$trade['id']; ?>"
                                       onclick="return confirm('Delete this trade?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($trades)): ?>
                            <tr><td colspan="12">No trades logged yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div class="trade-pagination">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p === $page): ?>
                                <strong><?php echo $p; ?></strong>
                            <?php else: ?>
                                <a href="metrics_tradelog.php?page=<?php echo $p; ?><?php foreach ($selectedAccountIds as $aid) echo '&accounts[]=' . (int)$aid; ?>"><?php echo $p; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

                <p style="margin-top:1rem;"><a href="metrics_trade_import.php">Import trades from a CSV file</a></p>
            <?php endif; ?>
        </section>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
    <script src="../assets/js/chart.min.js"></script>
    <script src="../assets/js/trade-balance-chart.js"></script>
</body>
</html>
