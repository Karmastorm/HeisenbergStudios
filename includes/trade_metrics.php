<?php
/**
 * Trade performance calculations, shared by sections/metrics_tradelog.php
 * and admin/trades.php so the math exists exactly once. Nothing here is
 * stored -- every value is computed fresh from the trades table.
 */

function compute_trade_pnl(array $trade): ?array {
    if ($trade['close_date'] === null || $trade['close_price'] === null) {
        return null;
    }

    $qty = (float)$trade['open_qty'];
    $openPrice = (float)$trade['open_price'];
    $closePrice = (float)$trade['close_price'];
    $fees = (float)$trade['open_fees'] + (float)($trade['close_fees'] ?? 0);

    $gross = $trade['side'] === 'short'
        ? ($openPrice - $closePrice) * $qty
        : ($closePrice - $openPrice) * $qty;
    $pnlDollars = $gross - $fees;

    $basis = $openPrice * $qty;
    $pnlPercent = $basis != 0.0 ? ($pnlDollars / $basis) * 100 : 0.0;

    $rMultiple = null;
    if ($trade['stop_loss'] !== null) {
        $riskDollars = abs($openPrice - (float)$trade['stop_loss']) * $qty;
        if ($riskDollars > 0.0) {
            $rMultiple = $pnlDollars / $riskDollars;
        }
    }

    if ($pnlDollars > 0.0001) {
        $result = 'win';
    } elseif ($pnlDollars < -0.0001) {
        $result = 'loss';
    } else {
        $result = 'breakeven';
    }

    return [
        'pnl_dollars' => $pnlDollars,
        'pnl_percent' => $pnlPercent,
        'r_multiple'  => $rMultiple,
        'result'      => $result,
    ];
}

function fetch_trade_stats(PDO $pdo, array $accountIds): array {
    $stats = [
        'overview' => [
            'total_trades' => 0, 'open_trades' => 0, 'closed_trades' => 0,
            'win_trades' => 0, 'loss_trades' => 0, 'breakeven_trades' => 0,
            'win_rate' => null, 'net_pnl_dollars' => 0.0,
            'avg_win_dollars' => null, 'avg_loss_dollars' => null,
            'avg_r_multiple' => null,
        ],
        'by_day_of_week' => [
            'Mon' => ['trades' => 0, 'pnl' => 0.0], 'Tue' => ['trades' => 0, 'pnl' => 0.0],
            'Wed' => ['trades' => 0, 'pnl' => 0.0], 'Thu' => ['trades' => 0, 'pnl' => 0.0],
            'Fri' => ['trades' => 0, 'pnl' => 0.0],
        ],
        'by_side' => [
            'long' => ['trades' => 0, 'pnl' => 0.0], 'short' => ['trades' => 0, 'pnl' => 0.0],
        ],
        'by_trade_type' => [
            'day' => ['trades' => 0, 'pnl' => 0.0], 'swing' => ['trades' => 0, 'pnl' => 0.0],
        ],
        'by_strategy' => [],
        'account_growth_percent' => null,
    ];

    if (empty($accountIds)) {
        return $stats;
    }

    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM trades WHERE account_id IN ($placeholders)");
    $stmt->execute($accountIds);
    $trades = $stmt->fetchAll();

    $winSum = 0.0;
    $lossSum = 0.0;
    $rMultiples = [];

    foreach ($trades as $trade) {
        $stats['overview']['total_trades']++;
        $pnl = compute_trade_pnl($trade);

        if ($pnl === null) {
            $stats['overview']['open_trades']++;
            continue;
        }

        $stats['overview']['closed_trades']++;
        $stats['overview']['net_pnl_dollars'] += $pnl['pnl_dollars'];

        if ($pnl['result'] === 'win') {
            $stats['overview']['win_trades']++;
            $winSum += $pnl['pnl_dollars'];
        } elseif ($pnl['result'] === 'loss') {
            $stats['overview']['loss_trades']++;
            $lossSum += $pnl['pnl_dollars'];
        } else {
            $stats['overview']['breakeven_trades']++;
        }

        if ($pnl['r_multiple'] !== null) {
            $rMultiples[] = $pnl['r_multiple'];
        }

        $dow = date('D', strtotime($trade['close_date']));
        if (isset($stats['by_day_of_week'][$dow])) {
            $stats['by_day_of_week'][$dow]['trades']++;
            $stats['by_day_of_week'][$dow]['pnl'] += $pnl['pnl_dollars'];
        }

        if (isset($stats['by_side'][$trade['side']])) {
            $stats['by_side'][$trade['side']]['trades']++;
            $stats['by_side'][$trade['side']]['pnl'] += $pnl['pnl_dollars'];
        }

        if (isset($stats['by_trade_type'][$trade['trade_type']])) {
            $stats['by_trade_type'][$trade['trade_type']]['trades']++;
            $stats['by_trade_type'][$trade['trade_type']]['pnl'] += $pnl['pnl_dollars'];
        }

        $strategyKey = (isset($trade['strategy']) && trim((string)$trade['strategy']) !== '')
            ? $trade['strategy'] : 'Uncategorized';
        if (!isset($stats['by_strategy'][$strategyKey])) {
            $stats['by_strategy'][$strategyKey] = [
                'trades' => 0, 'win_trades' => 0, 'loss_trades' => 0, 'net_pnl' => 0.0,
                'gain_sum' => 0.0, 'gain_count' => 0, 'loss_sum' => 0.0, 'loss_count' => 0,
            ];
        }
        $bucket = &$stats['by_strategy'][$strategyKey];
        $bucket['trades']++;
        $bucket['net_pnl'] += $pnl['pnl_dollars'];
        if ($pnl['result'] === 'win') {
            $bucket['win_trades']++;
            $bucket['gain_sum'] += $pnl['pnl_dollars'];
            $bucket['gain_count']++;
        } elseif ($pnl['result'] === 'loss') {
            $bucket['loss_trades']++;
            $bucket['loss_sum'] += $pnl['pnl_dollars'];
            $bucket['loss_count']++;
        }
        unset($bucket);
    }

    $decided = $stats['overview']['win_trades'] + $stats['overview']['loss_trades'];
    if ($decided > 0) {
        $stats['overview']['win_rate'] = ($stats['overview']['win_trades'] / $decided) * 100;
    }
    if ($stats['overview']['win_trades'] > 0) {
        $stats['overview']['avg_win_dollars'] = $winSum / $stats['overview']['win_trades'];
    }
    if ($stats['overview']['loss_trades'] > 0) {
        $stats['overview']['avg_loss_dollars'] = $lossSum / $stats['overview']['loss_trades'];
    }
    if (!empty($rMultiples)) {
        $stats['overview']['avg_r_multiple'] = array_sum($rMultiples) / count($rMultiples);
    }

    foreach ($stats['by_strategy'] as $key => $bucket) {
        $stats['by_strategy'][$key]['avg_gain'] = $bucket['gain_count'] > 0 ? $bucket['gain_sum'] / $bucket['gain_count'] : null;
        $stats['by_strategy'][$key]['avg_loss'] = $bucket['loss_count'] > 0 ? $bucket['loss_sum'] / $bucket['loss_count'] : null;
        $decidedStrategy = $bucket['win_trades'] + $bucket['loss_trades'];
        $stats['by_strategy'][$key]['win_rate'] = $decidedStrategy > 0 ? ($bucket['win_trades'] / $decidedStrategy) * 100 : null;
    }

    $balPlaceholders = implode(',', array_fill(0, count($accountIds), '?'));
    $balStmt = $pdo->prepare(
        "SELECT SUM(beginning_balance) AS total FROM brokerage_accounts
         WHERE id IN ($balPlaceholders) AND beginning_balance IS NOT NULL"
    );
    $balStmt->execute($accountIds);
    $totalBeginningBalance = (float)($balStmt->fetch()['total'] ?? 0);
    if ($totalBeginningBalance > 0.0) {
        $stats['account_growth_percent'] = ($stats['overview']['net_pnl_dollars'] / $totalBeginningBalance) * 100;
    }

    return $stats;
}

function fetch_trades_count(PDO $pdo, array $accountIds): int {
    if (empty($accountIds)) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trades WHERE account_id IN ($placeholders)");
    $stmt->execute($accountIds);
    return (int)$stmt->fetchColumn();
}

function fetch_trades_page(PDO $pdo, array $accountIds, int $page, int $perPage = 25): array {
    if (empty($accountIds)) {
        return [];
    }
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $offset = ($page - 1) * $perPage;
    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT t.*, ba.brokerage_name, ba.account_label
         FROM trades t
         JOIN brokerage_accounts ba ON ba.id = t.account_id
         WHERE t.account_id IN ($placeholders)
         ORDER BY COALESCE(t.close_date, t.open_date) DESC, t.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($accountIds);
    return $stmt->fetchAll();
}

function fetch_balance_history(PDO $pdo, array $accountIds): array {
    if (empty($accountIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));

    $balStmt = $pdo->prepare(
        "SELECT SUM(beginning_balance) AS total, MIN(created_at) AS earliest
         FROM brokerage_accounts WHERE id IN ($placeholders)"
    );
    $balStmt->execute($accountIds);
    $balRow = $balStmt->fetch();
    $runningBalance = (float)($balRow['total'] ?? 0);
    $startDate = $balRow['earliest'] ? substr($balRow['earliest'], 0, 10) : date('Y-m-d');

    $tradesStmt = $pdo->prepare(
        "SELECT * FROM trades
         WHERE account_id IN ($placeholders) AND close_date IS NOT NULL AND close_price IS NOT NULL
         ORDER BY close_date ASC, id ASC"
    );
    $tradesStmt->execute($accountIds);
    $trades = $tradesStmt->fetchAll();

    if (empty($trades)) {
        return [];
    }

    if ($trades[0]['close_date'] < $startDate) {
        $startDate = $trades[0]['close_date'];
    }

    $points = [['date' => $startDate, 'balance' => $runningBalance]];
    $currentDate = $startDate;
    foreach ($trades as $trade) {
        $runningBalance += compute_trade_pnl($trade)['pnl_dollars'];

        if ($trade['close_date'] === $currentDate) {
            $points[count($points) - 1]['balance'] = $runningBalance;
        } else {
            $points[] = ['date' => $trade['close_date'], 'balance' => $runningBalance];
            $currentDate = $trade['close_date'];
        }
    }

    return $points;
}

function fetch_recent_trade_values(PDO $pdo, array $accountIds, string $result, int $limit = 10): array {
    if (empty($accountIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT * FROM trades
         WHERE account_id IN ($placeholders) AND close_date IS NOT NULL AND close_price IS NOT NULL
         ORDER BY close_date DESC, id DESC"
    );
    $stmt->execute($accountIds);

    $values = [];
    while (($trade = $stmt->fetch()) !== false && count($values) < $limit) {
        $pnl = compute_trade_pnl($trade);
        if ($pnl['result'] === $result) {
            $values[] = $pnl['pnl_dollars'];
        }
    }

    return array_reverse($values);
}

function fetch_win_rate_trend(PDO $pdo, array $accountIds, int $limit = 10): array {
    if (empty($accountIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT * FROM trades
         WHERE account_id IN ($placeholders) AND close_date IS NOT NULL AND close_price IS NOT NULL
         ORDER BY close_date ASC, id ASC"
    );
    $stmt->execute($accountIds);
    $trades = $stmt->fetchAll();

    $wins = 0;
    $decided = 0;
    $trend = [];
    foreach ($trades as $trade) {
        $pnl = compute_trade_pnl($trade);
        if ($pnl['result'] === 'win') {
            $wins++;
            $decided++;
        } elseif ($pnl['result'] === 'loss') {
            $decided++;
        }
        $trend[] = $decided > 0 ? ($wins / $decided) * 100 : 0.0;
    }

    return array_slice($trend, -$limit);
}

function render_sparkline_svg(array $values, int $width = 80, int $height = 24): string {
    if (count($values) < 2) {
        return '';
    }

    $min = min($values);
    $max = max($values);
    $range = $max - $min;
    $count = count($values);

    $points = [];
    foreach ($values as $i => $value) {
        $x = ($i / ($count - 1)) * $width;
        $y = $range > 0 ? $height - (($value - $min) / $range) * $height : $height / 2;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }

    $pointsAttr = htmlspecialchars(implode(' ', $points));

    return '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" '
        . 'class="sparkline-svg" aria-hidden="true">'
        . '<polyline points="' . $pointsAttr . '" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" /></svg>';
}

/**
 * True when every one of the given accounts has at least one imported
 * daily snapshot row. Used to decide whether the balance chart can use
 * real daily mark-to-market values (fetch_daily_snapshots) or must fall
 * back to the trade-event-only calculation (fetch_balance_history) --
 * deliberately all-or-nothing per selection, not a partial mix, since a
 * per-date SUM() across a set where only some accounts have data would
 * silently undercount on any date the others are missing.
 */
function has_daily_snapshots(PDO $pdo, array $accountIds): bool {
    if (empty($accountIds)) {
        return false;
    }
    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT account_id) FROM account_value_snapshots WHERE account_id IN ($placeholders)"
    );
    $stmt->execute($accountIds);
    return (int)$stmt->fetchColumn() === count($accountIds);
}

/**
 * Daily total account value (cash + mark-to-market value of held
 * positions) summed across the given accounts, one point per date that
 * has a snapshot for every account -- only call this after
 * has_daily_snapshots() confirms full coverage.
 */
function fetch_daily_snapshots(PDO $pdo, array $accountIds): array {
    if (empty($accountIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT snapshot_date, SUM(total_value) AS total
         FROM account_value_snapshots
         WHERE account_id IN ($placeholders)
         GROUP BY snapshot_date
         ORDER BY snapshot_date ASC"
    );
    $stmt->execute($accountIds);
    $rows = $stmt->fetchAll();
    return array_map(
        fn($row) => ['date' => $row['snapshot_date'], 'balance' => (float)$row['total']],
        $rows
    );
}
