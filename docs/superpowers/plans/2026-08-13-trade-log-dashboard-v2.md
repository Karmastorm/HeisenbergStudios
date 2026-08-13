# Trade Log Dashboard v2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the Trade Log dashboard's stat tiles and add two charts — per-stat sparklines and an interactive account-balance area chart — using a self-hosted Chart.js for the interactive chart and hand-rolled SVG for sparklines.

**Architecture:** New pure calculation/rendering functions added to the existing `includes/trade_metrics.php`; new CSS tokens/classes appended to the existing `assets/css/themes.css`/`main.css`; a new shared vanilla-JS init script (`assets/js/trade-balance-chart.js`) consumed identically by both `sections/metrics_tradelog.php` and `admin/trades.php`; Chart.js itself vendored (not CDN-loaded) into `assets/js/chart.min.js`.

**Tech Stack:** PHP 8.2 (no framework), MySQL/MariaDB via PDO, vanilla CSS, vanilla JS, Chart.js (vendored, MIT licensed) for the one interactive chart.

**Spec:** `docs/superpowers/specs/2026-08-13-trade-log-dashboard-v2-design.md`

## Global Constraints

- No automated test framework exists in this repository. Verification per task is `"c:/xampp/php/php.exe" -l <file>` (full path, PHP not on shell `PATH`) for PHP files, plus careful code review against this plan's exact interfaces. There is no way to visually verify chart rendering without a live browser — the final task is a manual QA pass covering that.
- Every new win/loss visual indicator (sparkline color, trend arrow) must pair color with a non-color signal (an icon or the tile's existing text) — never color alone. This is a deliberate accessibility mitigation: the site's existing win/loss color pair (`#2e7d46`/`#b3261e`) fails a colorblind-separation check, and fixing that pair site-wide is out of scope for this plan (see spec's "Accessibility finding").
- Chart.js is vendored into `assets/js/chart.min.js` — no `<script src="https://...">` reference to any external CDN anywhere in this plan's changes. This matches the site's existing self-hosted-fonts convention.
- Sparkline color flows through CSS classes (`.sparkline-positive`/`.sparkline-negative`/`.sparkline-neutral`) using `currentColor` inside the SVG, never a color baked into server-rendered SVG output — consistent with how every other color on this site flows through CSS.
- `admin/trades.php` gets the account-balance chart added (same shared functions/JS as the user page) but does NOT gain the Avg Win/Avg Loss stat tiles or their sparklines — that page's dashboard was deliberately reduced in scope during the original Trade Log build (see that plan's final-review ruling) and this plan doesn't reopen that decision; it only adds the one new chart type to what's already there.
- Parts B (Daily Loss Limit) and C (Trading Objectives) from the spec are NOT built or migrated in this plan — schema-only, documented for future reference.
- Match existing conventions: raw PDO with named placeholders, `htmlspecialchars()` on every output of user-supplied or DB-sourced string data, dynamic `IN (...)` placeholder counts sized off `count($accountIds)`.

---

### Task 1: Vendor Chart.js (controller action, not a subagent dispatch)

**Files:**
- Create: `assets/js/chart.min.js`

This task has no code to hand an implementer — it's fetching a third-party binary/minified asset, not writing PHP/CSS/JS from a spec. The plan's controller performs this step directly (equivalent to a dependency-install step, like `npm install` in a Node project) before dispatching Task 2.

- [ ] **Step 1: Fetch the file**

Download the Chart.js UMD production build (a stable 4.x release) from its official npm-published CDN mirror (e.g. `https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.js`) and save it as `assets/js/chart.min.js`. This is the only network fetch in this plan — everything downstream reads the vendored local file.

- [ ] **Step 2: Sanity-check the file**

Confirm the file is non-empty, starts with a JS comment/UMD wrapper (not an HTML error page — a failed fetch sometimes silently saves an HTML 404 page instead of the expected JS), and is roughly 150-250KB (the actual size of a Chart.js 4.x UMD bundle). If the fetch returns something that doesn't look like the real library, try an alternate mirror before proceeding.

- [ ] **Step 3: Commit**

```bash
git add assets/js/chart.min.js
git commit -m "Vendor Chart.js for the Trade Log balance chart"
```

---

### Task 2: New calculation and sparkline-rendering functions

**Files:**
- Modify: `includes/trade_metrics.php`

**Interfaces:**
- Consumes: `compute_trade_pnl(array $trade): ?array` (already in this file).
- Produces:
  - `fetch_balance_history(PDO $pdo, array $accountIds): array` — ordered `[['date' => 'Y-m-d', 'balance' => float], ...]`, empty array if no closed trades.
  - `fetch_recent_trade_values(PDO $pdo, array $accountIds, string $result, int $limit = 10): array` — up to `$limit` floats, chronological order, `$result` is `'win'` or `'loss'`.
  - `fetch_win_rate_trend(PDO $pdo, array $accountIds, int $limit = 10): array` — up to `$limit` floats (cumulative win rate %), chronological order.
  - `render_sparkline_svg(array $values, int $width = 80, int $height = 24): string` — SVG markup string, or `''` if `count($values) < 2`.

- [ ] **Step 1: Insert the four functions at the end of `includes/trade_metrics.php`**

```php
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
```

- [ ] **Step 2: Syntax check**

Run: `"c:/xampp/php/php.exe" -l includes/trade_metrics.php`
Expected: `No syntax errors detected in includes/trade_metrics.php`

- [ ] **Step 3: Manual trace of `fetch_balance_history()`**

By hand, trace: two accounts with `beginning_balance` 1000 and 500 (sum 1000+500=1500), earliest `created_at` 2026-01-01. One closed trade on 2026-01-05 with `pnl_dollars` = 100 (computed via `compute_trade_pnl()`), another closed trade also on 2026-01-05 with `pnl_dollars` = -20. Expected result: `[['date' => '2026-01-01', 'balance' => 1500.0], ['date' => '2026-01-05', 'balance' => 1580.0]]` — the two same-day trades collapse into one point showing the day's final balance (1500 + 100 - 20 = 1580), not two separate points.

- [ ] **Step 4: Manual trace of `render_sparkline_svg()`**

By hand, trace `render_sparkline_svg([10, 20, 5])` with default width=80, height=24: `min=5, max=20, range=15`. Point 0: `x=0, y=24-((10-5)/15)*24=24-8=16`. Point 1: `x=40, y=24-((20-5)/15)*24=24-24=0`. Point 2: `x=80, y=24-((5-5)/15)*24=24`. Confirm the code produces `points="0,16 40,0 80,24"` (allowing for the `round(...,1)` formatting).

- [ ] **Step 5: Commit**

```bash
git add includes/trade_metrics.php
git commit -m "Add balance-history, sparkline-data, and SVG-rendering functions"
```

---

### Task 3: CSS — surface-elevated token, tile glow, sparkline/chart classes

**Files:**
- Modify: `assets/css/themes.css`
- Modify: `assets/css/main.css`

**Interfaces:**
- Produces: `--color-surface-elevated` custom property (both themes), `.trade-stat-tile.trade-stat-positive`/`.trade-stat-tile.trade-stat-negative` glow rules, `.sparkline-wrap`/`.sparkline-svg`/`.sparkline-positive`/`.sparkline-negative`/`.sparkline-neutral`/`.sparkline-trend-up`/`.sparkline-trend-down`/`.sparkline-trend-flat`, `.balance-chart-wrap`.

- [ ] **Step 1: Add `--color-surface-elevated` to `assets/css/themes.css`**

In the `body[data-theme="light"]` block, immediately after the existing `--color-surface:   #ffffff;` line, add:

```css
    --color-surface-elevated: #ffffff;
```

In the `body[data-theme="dark"]` block, immediately after the existing `--color-surface:   #18181b;` line, add:

```css
    --color-surface-elevated: #1f1f23;
```

(Light mode intentionally uses the same value as `--color-surface` — the spec calls for the elevated-card look only in dark mode, since it doesn't translate well to a light background. This keeps `main.css` able to reference `var(--color-surface-elevated)` unconditionally without a theme check.)

- [ ] **Step 2: Update `.trade-stat-tile`'s background in `assets/css/main.css`**

Find the existing rule:

```css
.trade-stat-tile { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; padding: 0.9rem 1rem; }
```

Change `background: var(--color-surface);` to `background: var(--color-surface-elevated);` — nothing else in this rule changes.

- [ ] **Step 3: Append new rules to the end of `assets/css/main.css`**

```css
/* ---------------- Trade Log Dashboard v2 (glow, sparklines, balance chart) ---------------- */
.trade-stat-tile.trade-stat-positive { box-shadow: 0 0 24px -8px rgba(46, 125, 70, 0.35); }
.trade-stat-tile.trade-stat-negative { box-shadow: 0 0 24px -8px rgba(179, 38, 30, 0.35); }

.sparkline-wrap { display: flex; align-items: center; gap: 0.4rem; margin-top: 0.4rem; }
.sparkline-svg { display: block; }
.sparkline-positive { color: #2e7d46; }
.sparkline-negative { color: #b3261e; }
.sparkline-neutral { color: var(--color-accent); }
.sparkline-trend-up { color: #2e7d46; font-size: 0.75rem; }
.sparkline-trend-down { color: #b3261e; font-size: 0.75rem; }
.sparkline-trend-flat { color: var(--color-text-soft); font-size: 0.75rem; }

.balance-chart-wrap { background: var(--color-surface-elevated); border: 1px solid var(--color-border); border-radius: 8px; padding: 1.25rem; margin-bottom: 2rem; }
.balance-chart-wrap h2 { margin-bottom: 1rem; }
.balance-chart-wrap canvas { max-height: 280px; width: 100%; }
```

- [ ] **Step 4: Confirm no duplicate token/class definitions were introduced**

Read back both modified files' relevant sections and confirm `--color-surface-elevated` appears exactly once per theme block, and none of the new class names (`.sparkline-wrap`, `.balance-chart-wrap`, etc.) already existed anywhere else in `main.css` before this change.

- [ ] **Step 5: Commit**

```bash
git add assets/css/themes.css assets/css/main.css
git commit -m "Add dashboard v2 CSS: elevated surface token, tile glow, sparkline and balance-chart styles"
```

---

### Task 4: Shared Chart.js init script

**Files:**
- Create: `assets/js/trade-balance-chart.js`

**Interfaces:**
- Consumes: a `<canvas id="balance-chart">` element and a `<script type="application/json" id="balance-chart-data">` element (both produced by Tasks 5 and 6's PHP templates) containing a JSON array of `{date, balance}` objects; the global `Chart` constructor from the vendored `assets/js/chart.min.js` (Task 1), which must be loaded via a `<script>` tag before this file's `<script>` tag on any page that uses it.
- Produces: initializes one Chart.js line/area chart into `#balance-chart` when both elements are present; does nothing (no error) when either is absent, so pages without a balance history don't need to guard against including this script.

- [ ] **Step 1: Write the file**

```javascript
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('balance-chart');
    var dataTag = document.getElementById('balance-chart-data');
    if (!canvas || !dataTag || typeof Chart === 'undefined') {
        return;
    }

    var points = JSON.parse(dataTag.textContent);
    var styles = getComputedStyle(document.body);
    var accentColor = styles.getPropertyValue('--color-accent').trim();
    var surfaceColor = styles.getPropertyValue('--color-surface').trim();
    var textColor = styles.getPropertyValue('--color-text').trim();
    var borderColor = styles.getPropertyValue('--color-border').trim();

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map(function (p) { return p.date; }),
            datasets: [{
                label: 'Balance',
                data: points.map(function (p) { return p.balance; }),
                borderColor: accentColor,
                backgroundColor: accentColor + '33',
                fill: true,
                tension: 0.2,
                pointRadius: 0,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: surfaceColor,
                    titleColor: textColor,
                    bodyColor: textColor,
                    borderColor: borderColor,
                    borderWidth: 1,
                    callbacks: {
                        label: function (context) {
                            return '$' + context.parsed.y.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            });
                        },
                    },
                },
            },
            scales: {
                x: { grid: { color: borderColor }, ticks: { color: textColor } },
                y: { grid: { color: borderColor }, ticks: { color: textColor } },
            },
        },
    });
});
```

- [ ] **Step 2: Review for correctness**

Confirm: the guard clause returns early (no thrown error) when `#balance-chart`, `#balance-chart-data`, or the global `Chart` object is missing — this matters because `admin/trades.php`'s "no user selected" state won't render the canvas at all, and this script must not error on that page load. Confirm colors are read via `getComputedStyle` at init time (not hardcoded), so the chart matches whichever theme (light/dark) is active when the page loads.

- [ ] **Step 3: Commit**

```bash
git add assets/js/trade-balance-chart.js
git commit -m "Add shared Chart.js init script for the account balance chart"
```

---

### Task 5: Wire sparklines and the balance chart into `sections/metrics_tradelog.php`

**Files:**
- Modify: `sections/metrics_tradelog.php`

**Interfaces:**
- Consumes: `fetch_balance_history()`, `fetch_recent_trade_values()`, `fetch_win_rate_trend()`, `render_sparkline_svg()` (Task 2); `.sparkline-*`/`.balance-chart-wrap`/`.trade-stat-tile.trade-stat-positive`/`.trade-stat-tile.trade-stat-negative` CSS (Task 3); `assets/js/chart.min.js` (Task 1) and `assets/js/trade-balance-chart.js` (Task 4).

- [ ] **Step 1: Insert new PHP data fetches and two small helper functions**

Insert immediately after the existing `$stats = fetch_trade_stats($pdo, $selectedAccountIds);` line, before the existing `$page = max(1, (int)($_GET['page'] ?? 1));` line:

```php
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
```

- [ ] **Step 2: Add glow classes to the Net PnL and Account Growth tiles' outer `<div>`**

Find:

```php
            <div class="trade-stat-tile">
                <div class="stat-label">Net PnL</div>
                <div class="stat-value <?php echo trade_stat_class($stats['overview']['net_pnl_dollars']); ?>"><?php echo format_money($stats['overview']['net_pnl_dollars']); ?></div>
            </div>
```

Replace with:

```php
            <div class="trade-stat-tile <?php echo trade_stat_class($stats['overview']['net_pnl_dollars']); ?>">
                <div class="stat-label">Net PnL</div>
                <div class="stat-value <?php echo trade_stat_class($stats['overview']['net_pnl_dollars']); ?>"><?php echo format_money($stats['overview']['net_pnl_dollars']); ?></div>
            </div>
```

Find:

```php
            <?php if ($stats['account_growth_percent'] !== null): ?>
                <div class="trade-stat-tile">
                    <div class="stat-label">Account Growth</div>
                    <div class="stat-value <?php echo trade_stat_class($stats['account_growth_percent']); ?>"><?php echo format_percent($stats['account_growth_percent']); ?></div>
                </div>
            <?php endif; ?>
```

Replace with:

```php
            <?php if ($stats['account_growth_percent'] !== null): ?>
                <div class="trade-stat-tile <?php echo trade_stat_class($stats['account_growth_percent']); ?>">
                    <div class="stat-label">Account Growth</div>
                    <div class="stat-value <?php echo trade_stat_class($stats['account_growth_percent']); ?>"><?php echo format_percent($stats['account_growth_percent']); ?></div>
                </div>
            <?php endif; ?>
```

- [ ] **Step 3: Add sparklines to the Win Rate, Avg Win, and Avg Loss tiles**

Find:

```php
            <div class="trade-stat-tile">
                <div class="stat-label">Win Rate</div>
                <div class="stat-value"><?php echo format_percent($stats['overview']['win_rate']); ?></div>
            </div>
```

Replace with:

```php
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
```

Find:

```php
            <div class="trade-stat-tile">
                <div class="stat-label">Avg Win</div>
                <div class="stat-value trade-stat-positive"><?php echo format_money($stats['overview']['avg_win_dollars']); ?></div>
            </div>
```

Replace with:

```php
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
```

Find:

```php
            <div class="trade-stat-tile">
                <div class="stat-label">Avg Loss</div>
                <div class="stat-value trade-stat-negative"><?php echo format_money($stats['overview']['avg_loss_dollars']); ?></div>
            </div>
```

Replace with:

```php
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
```

- [ ] **Step 4: Insert the Account Balance chart section**

Insert immediately after the `.trade-dashboard` section's closing `</section>`, before the `<section class="trade-breakdowns">` line:

```php
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
```

- [ ] **Step 5: Add the two new script tags**

Find the existing closing script line:

```php
    <script src="../assets/js/theme-switcher.js"></script>
```

Replace with:

```php
    <script src="../assets/js/theme-switcher.js"></script>
    <script src="../assets/js/chart.min.js"></script>
    <script src="../assets/js/trade-balance-chart.js"></script>
```

- [ ] **Step 6: Syntax check**

Run: `"c:/xampp/php/php.exe" -l sections/metrics_tradelog.php`
Expected: `No syntax errors detected in sections/metrics_tradelog.php`

- [ ] **Step 7: Review the full assembled dashboard section**

Read `sections/metrics_tradelog.php` end to end once more. Confirm: no tile lost its original content while gaining the new markup, the balance-chart section's `<script type="application/json">` tag correctly nests inside its own `<section>` (not accidentally left dangling outside), and the two new `<script src>` tags come after `theme-switcher.js` and before `</body>`.

- [ ] **Step 8: Commit**

```bash
git add sections/metrics_tradelog.php
git commit -m "Add sparklines and account balance chart to the Trade Log dashboard"
```

---

### Task 6: Wire the balance chart into `admin/trades.php`

**Files:**
- Modify: `admin/trades.php`

**Interfaces:**
- Consumes: `fetch_balance_history()` (Task 2); `assets/js/chart.min.js` (Task 1) and `assets/js/trade-balance-chart.js` (Task 4). Does NOT add Avg Win/Avg Loss tiles or sparklines — see Global Constraints.

- [ ] **Step 1: Fetch balance history**

Insert immediately after the existing `$stats = fetch_trade_stats($pdo, $accountIds);` line (inside the `if (!empty($accountIds))` block... note: re-check the exact existing structure — the line to insert after is inside the `if ($selectedUserId !== null)` block, specifically right after `$stats = fetch_trade_stats($pdo, $accountIds);` and it must be inside the same `if (!empty($accountIds))` guard that already wraps the `$trades = fetch_trades_page(...)` line below it, so it doesn't run against an empty `$accountIds` array):

```php
    $accountIds = array_map(fn($a) => (int)$a['id'], $accounts);
    $stats = fetch_trade_stats($pdo, $accountIds);
    $balanceHistory = [];

    if (!empty($accountIds)) {
        $trades = fetch_trades_page($pdo, $accountIds, 1, 100);
        $balanceHistory = fetch_balance_history($pdo, $accountIds);
    }
```

This replaces the existing:

```php
    $accountIds = array_map(fn($a) => (int)$a['id'], $accounts);
    $stats = fetch_trade_stats($pdo, $accountIds);

    if (!empty($accountIds)) {
        $trades = fetch_trades_page($pdo, $accountIds, 1, 100);
    }
```

- [ ] **Step 2: Insert the Account Balance chart section**

Insert immediately after the `.trade-dashboard` section's closing `</section>`, before the `<table class="trade-journal-table">` line:

```php
            <?php if (count($balanceHistory) >= 2): ?>
                <section class="balance-chart-wrap">
                    <h2>Account Balance</h2>
                    <canvas id="balance-chart" role="img" aria-label="Account balance over time"></canvas>
                    <script type="application/json" id="balance-chart-data"><?php echo json_encode($balanceHistory); ?></script>
                </section>
            <?php endif; ?>
```

(No "log a trade" empty-state message here, unlike the user-facing page — this is a read-only admin view of possibly-many users, most of whom won't have balance history yet, so a chart that simply doesn't render is less noisy than a message repeated for every empty selection.)

- [ ] **Step 3: Add the two new script tags**

Find the existing closing script line:

```php
    <script src="../assets/js/theme-switcher.js"></script>
```

Replace with:

```php
    <script src="../assets/js/theme-switcher.js"></script>
    <script src="../assets/js/chart.min.js"></script>
    <script src="../assets/js/trade-balance-chart.js"></script>
```

- [ ] **Step 4: Syntax check**

Run: `"c:/xampp/php/php.exe" -l admin/trades.php`
Expected: `No syntax errors detected in admin/trades.php`

- [ ] **Step 5: Confirm no Avg Win/Avg Loss tiles or sparkline code were added**

Read the full assembled file. Confirm the dashboard section still has exactly its original four tiles (Total Trades, Win Rate, Net PnL, conditional Account Growth) plus the new balance-chart section — no `sparkline` references anywhere in this file, per the Global Constraints note that this page's reduced scope isn't being reopened.

- [ ] **Step 6: Commit**

```bash
git add admin/trades.php
git commit -m "Add account balance chart to the Trade Oversight admin page"
```

---

### Task 7: Manual QA (site owner)

**Files:** none (verification-only task; no code changes)

No automated way to verify chart rendering — this requires a live browser. Perform after all previous tasks are committed and pushed and the deploy workflow has run.

- [ ] **Step 1: Confirm the vendored library loads locally**

View page source on the deployed Trade Log page; confirm `assets/js/chart.min.js` and `assets/js/trade-balance-chart.js` are requested from the site's own domain, not any external CDN.

- [ ] **Step 2: Empty-state check**

As a user with zero closed trades (or a fresh account), confirm: the Avg Win/Avg Loss/Win Rate tiles show no sparkline (not a broken image), and the Account Balance section shows "Log a closed trade to see your balance history." instead of a blank canvas.

- [ ] **Step 3: Populated check**

With a mix of wins, losses, and multiple trades closed on the same day across two accounts, confirm: sparklines show a visible trend line, the ▲/▼ indicator next to each matches the direction of the underlying data (compare the first and last logged values by hand), and the balance chart's line reflects the correct cumulative running balance (spot-check two or three points by hand against the journal).

- [ ] **Step 4: Hover tooltip check**

Hover over a few points on the account balance chart; confirm a tooltip appears showing the date and a correctly dollar-formatted balance.

- [ ] **Step 5: Theme check**

Toggle light/dark theme (reload the page after toggling, since the chart's colors are read once at page load) and confirm the stat tile glow, sparkline color, and balance chart colors all match the active theme — no leftover colors from the other theme.

- [ ] **Step 6: Account filter check**

With 2+ accounts, use the account filter to select a subset; confirm sparklines, trend arrows, and the balance chart all update to reflect only the filtered accounts' data, consistent with every other number on the page.

- [ ] **Step 7: Admin oversight check**

As webmaster, visit `admin/trades.php`, select a user with closed trades; confirm the balance chart renders there too, and confirm no Avg Win/Avg Loss tiles or sparklines appear (matching the deliberately reduced admin view).

- [ ] **Step 8: No-user-selected check on admin page**

Visit `admin/trades.php` with no user selected (the default state); confirm no JavaScript error appears in the browser console — this is the specific case Task 4's guard clause exists to handle.
