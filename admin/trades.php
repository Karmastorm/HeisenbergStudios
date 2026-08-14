<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/trade_metrics.php';
start_secure_session();

require_access('webmaster');

$pdo = get_db_connection();

function admin_trade_stat_class(float $value): string {
    if ($value > 0.0001) return 'trade-stat-positive';
    if ($value < -0.0001) return 'trade-stat-negative';
    return '';
}

function admin_format_money(?float $value): string {
    return $value === null ? '&mdash;' : '$' . number_format($value, 2);
}

function admin_format_percent(?float $value): string {
    return $value === null ? '&mdash;' : number_format($value, 1) . '%';
}

$users = $pdo->query(
    "SELECT id, username FROM users WHERE id IN (SELECT DISTINCT user_id FROM brokerage_accounts) ORDER BY username"
)->fetchAll();

$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

$accounts = [];
$stats = null;
$trades = [];

if ($selectedUserId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM brokerage_accounts WHERE user_id = :user_id ORDER BY brokerage_name, account_label');
    $stmt->execute(['user_id' => $selectedUserId]);
    $accounts = $stmt->fetchAll();

    $accountIds = array_map(fn($a) => (int)$a['id'], $accounts);
    $stats = fetch_trade_stats($pdo, $accountIds);
    $balanceHistory = [];
    $allocation = [];

    if (!empty($accountIds)) {
        $trades = fetch_trades_page($pdo, $accountIds, 1, 100);
        $balanceHistory = has_daily_snapshots($pdo, $accountIds)
            ? fetch_daily_snapshots($pdo, $accountIds)
            : fetch_balance_history($pdo, $accountIds);
        $allocation = fetch_allocation_by_cost_basis($pdo, $accountIds);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade Oversight - Heisenberg Studios</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=6">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=6">
    <link rel="stylesheet" href="../assets/css/main.css?v=6">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Trade Oversight</h1>
        <p style="margin-bottom:1rem; color:var(--color-text-soft); font-size:0.9rem;">
            Read-only. Only users with at least one brokerage account appear below.
        </p>

        <form method="get" class="admin-form" style="max-width:400px;">
            <label for="user_id">User</label>
            <select id="user_id" name="user_id" onchange="this.form.submit()">
                <option value="">-- Select a user --</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>" <?php echo $selectedUserId === (int)$u['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($stats !== null): ?>
            <section class="trade-dashboard">
                <div class="trade-stat-tile">
                    <div class="stat-label">Total Trades</div>
                    <div class="stat-value"><?php echo (int)$stats['overview']['total_trades']; ?></div>
                </div>
                <div class="trade-stat-tile">
                    <div class="stat-label">Win Rate</div>
                    <div class="stat-value"><?php echo admin_format_percent($stats['overview']['win_rate']); ?></div>
                </div>
                <div class="trade-stat-tile">
                    <div class="stat-label">Net PnL</div>
                    <div class="stat-value <?php echo admin_trade_stat_class($stats['overview']['net_pnl_dollars']); ?>"><?php echo admin_format_money($stats['overview']['net_pnl_dollars']); ?></div>
                </div>
                <?php if ($stats['account_growth_percent'] !== null): ?>
                    <div class="trade-stat-tile">
                        <div class="stat-label">Account Growth</div>
                        <div class="stat-value <?php echo admin_trade_stat_class($stats['account_growth_percent']); ?>"><?php echo admin_format_percent($stats['account_growth_percent']); ?></div>
                    </div>
                <?php endif; ?>
            </section>

            <?php if (count($balanceHistory) >= 2): ?>
                <section class="balance-chart-wrap">
                    <h2>Account Balance</h2>
                    <canvas id="balance-chart" role="img" aria-label="Account balance over time"></canvas>
                    <script type="application/json" id="balance-chart-data"><?php echo json_encode($balanceHistory); ?></script>
                </section>
            <?php endif; ?>

            <?php if (!empty($allocation)): ?>
                <section class="allocation-ring-wrap">
                    <h2>Allocation</h2>
                    <canvas id="allocation-ring-chart" role="img" aria-label="Portfolio allocation by cost basis"></canvas>
                    <script type="application/json" id="allocation-ring-data"><?php echo json_encode($allocation); ?></script>
                </section>
            <?php endif; ?>

            <table class="trade-journal-table">
                <thead>
                    <tr><th>Account</th><th>Ticker</th><th>Side</th><th>Open</th><th>Close</th><th>PnL $</th><th>Result</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($trades as $trade): ?>
                        <?php $pnl = compute_trade_pnl($trade); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trade['brokerage_name'] . ' — ' . $trade['account_label']); ?></td>
                            <td><?php echo htmlspecialchars($trade['ticker']); ?></td>
                            <td><?php echo ucfirst($trade['side']); ?></td>
                            <td><?php echo htmlspecialchars($trade['open_date']); ?></td>
                            <td><?php echo htmlspecialchars($trade['close_date'] ?? 'Open'); ?></td>
                            <td class="<?php echo $pnl ? admin_trade_stat_class($pnl['pnl_dollars']) : ''; ?>"><?php echo $pnl ? admin_format_money($pnl['pnl_dollars']) : '&mdash;'; ?></td>
                            <td><?php echo $pnl ? ucfirst($pnl['result']) : 'Open'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($trades)): ?>
                        <tr><td colspan="7">No trades for this user.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
    <script src="../assets/js/chart.min.js"></script>
    <script src="../assets/js/trade-balance-chart.js"></script>
    <script src="../assets/js/trade-allocation-chart.js"></script>
</body>
</html>
