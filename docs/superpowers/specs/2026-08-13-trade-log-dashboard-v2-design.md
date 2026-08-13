# Trade Log Dashboard v2 — Design

## Summary

Restyle the Trade Log dashboard's stat tiles and add two charts (per-stat
sparklines, an interactive account-balance area chart), adapting a reference
screenshot's dark card aesthetic into the site's existing dark theme rather
than importing its literal purple palette. This is Part A of a three-part
follow-up to the shipped Trade Log feature; Parts B (Daily Loss Limit
tracker) and C (Trading Objectives tracker) are designed here for schema
coherence but not built or migrated in this pass — see "Deferred: Parts B
and C" below.

## Background

The Trade Log feature (`sections/metrics_tradelog.php`, `admin/trades.php`,
`includes/trade_metrics.php`, `includes/trade_access.php`) shipped in a prior
pass — see `docs/superpowers/specs/2026-08-13-trade-log-design.md` and
`docs/superpowers/plans/2026-08-13-trade-log.md`. Relevant existing pieces:

- `fetch_trade_stats(PDO $pdo, array $accountIds): array` (`includes/trade_metrics.php`)
  returns the dashboard's aggregate stats (`overview`, `by_day_of_week`,
  `by_side`, `by_trade_type`, `by_strategy`, `account_growth_percent`) but no
  per-trade time series — this design adds new fetch functions for that.
- `.trade-dashboard` (`assets/css/main.css`) currently renders 7-8 plain
  stat tiles in a 4-column grid (just fixed via a separate small task) with
  no charts.
- The site has zero JS dependencies beyond `assets/js/theme-switcher.js`
  (a small vanilla script). Recent site history shows a deliberate
  self-hosted-assets convention: fonts were moved to self-hosted specifically
  to avoid third-party requests (see the "professional monochrome redesign"
  spec). This design follows that convention for its one new dependency.
- The reference screenshot (a prop-trading-challenge dashboard) uses a dark
  purple/violet palette with neon green/red accents, sparkline-bearing stat
  cards, a large account-balance area chart, an info panel, a daily-loss-limit
  gauge, and a trading-history table. Confirmed with the site owner: only the
  visual *style* (cards, colors, charts, table) carries over, adapted to the
  site's existing dark theme rather than cloned; the challenge-platform-specific
  concepts (payout, leaderboard, "Phase 1") don't apply and are not built.

### Accessibility finding (informational, not actioned here)

Running this project's palette validator
(`node scripts/validate_palette.js "#2e7d46,#b3261e" --mode light`, from the
`dataviz` skill) against the site's existing win/loss colors
(`--trade-stat-positive` / `--trade-stat-negative`, already used site-wide
including the just-shipped stat tiles) returns a FAIL on CVD separation
(ΔE 5.0, below even the 6-8 floor that requires secondary encoding).
Redesigning that site-wide color pair is out of scope for this dashboard
restyle — it would ripple across pages this design doesn't touch. Instead,
every new win/loss indicator this design adds pairs color with an icon
(▲/▼) or text label, never color alone, satisfying the "status colors ship
with icon + label" rule even with an imperfect underlying color pair.

## Goals

- Restyle `.trade-stat-tile` to a darker card treatment with a subtle accent
  glow, still using the site's existing `--color-*` custom properties (so it
  correctly re-themes with the light/dark switcher) rather than hardcoded
  colors from the reference.
- Add sparklines to the Average Win, Average Loss, and Win Rate tiles
  (matching which tiles the reference sparks — not all 7-8 tiles).
- Add an interactive account-balance area chart (beginning balance +
  cumulative closed-trade PnL over time), with a hover tooltip.
- Do this only for `sections/metrics_tradelog.php`'s dashboard section (and
  the equivalent read-only rendering on `admin/trades.php`, reusing the same
  new fetch functions) — no other page changes.

## Non-goals

- Any site-wide theme change. Nav, header, other pages, and every non-Trade-Log
  page are untouched.
- Redesigning the win/loss color pair site-wide (see Accessibility finding).
- Building the Daily Loss Limit tracker (Part B) or Trading Objectives
  tracker (Part C) — designed below for coherence, not implemented, no
  migration written this pass.
- The reference's payout/leaderboard/challenge-phase concepts.
- Interactive hover on the sparklines — they're small, decorative trend
  indicators inside a stat tile; only the account-balance chart gets a
  hover tooltip.

## Design

### 1. Chart data source

New function in `includes/trade_metrics.php`:

```php
function fetch_balance_history(PDO $pdo, array $accountIds): array
```

Returns an ordered array of `['date' => 'YYYY-MM-DD', 'balance' => float]`
points: starts from the sum of the selected accounts' `beginning_balance`
(treating NULL as 0) on the earliest account's `created_at` date, then walks
every closed trade across the selected accounts in `close_date` order,
adding each trade's `compute_trade_pnl()['pnl_dollars']` to a running total,
emitting one point per trade (multiple trades closed the same day collapse
to that day's final running balance, not one point per trade). Returns an
empty array when there are no closed trades — callers render an empty-state
message, not a broken chart.

New function, same file:

```php
function fetch_recent_trade_values(PDO $pdo, array $accountIds, string $result, int $limit = 10): array
```

`$result` is `'win'` or `'loss'`. Returns up to `$limit` PnL-dollar values
(most recent first, then reversed for chronological sparkline order) from
closed trades across the selected accounts whose `compute_trade_pnl()`
result matches `$result`. Used by the Average Win / Average Loss sparklines.

New function, same file:

```php
function fetch_win_rate_trend(PDO $pdo, array $accountIds, int $limit = 10): array
```

Returns up to `$limit` float percentages (most recent first, then reversed),
each the *cumulative* win rate (wins / (wins+losses) among all closed trades
up to and including that point in the account's history) as of each of the
last `$limit` closed trades, in chronological order. Cumulative rather than
a trailing window — no extra window-size parameter to design around, and it
directly shows whether the account's win rate is trending up or down over
its life.

All three functions take the same `$accountIds` array the existing dashboard
functions already use (the account-filter selection), so sparklines and the
balance chart respect the filter exactly like every other number on the page.

### 2. Sparklines — hand-rolled inline SVG

New function, `includes/trade_metrics.php`:

```php
function render_sparkline_svg(array $values, int $width = 80, int $height = 24): string
```

Pure rendering helper (no DB access): given a flat array of floats, scales
them to fit the given viewBox, and returns a `<svg>` string containing one
`<polyline stroke="currentColor">` (2px stroke, matching the dataviz skill's
thin-mark spec, no markers, no gridlines — this is a sparkline, not a full
chart) plus rounded `stroke-linecap="round"` data-ends. Returns an empty
string (rendered as nothing, not a broken image) when `count($values) < 2` —
there's no line to draw from zero or one point.

The function takes no color parameter — resolving a CSS custom property to
a literal color server-side isn't possible in plain PHP, so the SVG uses
`stroke="currentColor"` and the caller wraps the returned markup in a
`<span class="sparkline-positive">` or `<span class="sparkline-negative">`
(new CSS: `.sparkline-positive { color: var(--trade-stat-positive); }` and
the negative equivalent) — keeping color themable through CSS the same way
every other color on this site already works, rather than baking a resolved
color into server-rendered SVG output.

Sparkline color: Average Win always wraps in `.sparkline-positive` (wins are
positive by definition) and Average Loss always wraps in
`.sparkline-negative` (same reasoning) — neither varies by direction. Win
Rate isn't inherently a win/loss value, so its sparkline uses a neutral
`.sparkline-neutral` class (`color: var(--color-accent)`), and each of the
three tiles gets a small `▲`/`▼` (or `–` for flat/insufficient data) trend
indicator next to the sparkline comparing the first and last value in the
series — this is what actually conveys direction, satisfying the "status
color never alone" rule from the Accessibility finding above.

### 3. Account Balance chart — self-hosted Chart.js

- Vendor `chart.js` (the UMD bundle, MIT licensed) into `assets/js/chart.min.js`
  — no CDN reference anywhere, matching the self-hosted-fonts convention.
- `sections/metrics_tradelog.php` (and `admin/trades.php`) render a
  `<canvas id="balance-chart">` inside the dashboard section, plus a small
  inline `<script>` block that reads the balance-history data (output as a
  `<script type="application/json" id="balance-chart-data">` tag containing
  `json_encode(fetch_balance_history(...))`, avoiding inline PHP-to-JS string
  interpolation) and initializes a single-series `line` chart with `fill: true`
  (area fill), the site's `--color-accent` for the line, tooltip enabled
  (Chart.js's built-in tooltip, styled via its `options.plugins.tooltip`
  config to use the site's surface/text colors rather than Chart.js defaults),
  and `responsive: true` so it resizes with the page.
- When `fetch_balance_history()` returns fewer than 2 points, the canvas is
  not rendered at all — an empty-state message ("Log a closed trade to see
  your balance history") takes its place, matching how the rest of this
  page already handles empty states (e.g. "No trades logged yet." in the
  by-strategy table).
- No new CSS framework, no chart plugins beyond Chart.js core — the
  reference's more elaborate visual flourishes (glowing point markers,
  gradient fills) are approximated with Chart.js's built-in `backgroundColor`
  gradient support, not a separate library.

### 4. Card restyle

`.trade-stat-tile` (currently a flat `--color-surface` box) gets:
- A slightly darker background in dark mode specifically (a new
  `--color-surface-elevated` token, defined alongside the existing
  `--color-surface` in `themes.css`'s dark block only — light mode keeps
  the current flat surface, since the reference's dramatic dark-card look
  doesn't translate to a light background) — kept as a proper theme
  token, not a one-off hardcoded color, so it participates in the existing
  light/dark switch correctly.
- A subtle `box-shadow` glow using the tile's accent color at low opacity
  (e.g. `0 0 24px -8px var(--trade-stat-positive)` on tiles whose value is
  positive, negative equivalent for negative tiles, none for neutral tiles
  like Total Trades) — approximates the reference's glow without a hard
  dependency on a specific hue.
- Otherwise keeps the existing padding/border-radius/typography from the
  shipped `.trade-stat-tile` — this is a refinement of the existing card,
  not a rebuild.

### 5. `admin/trades.php` reuse

The admin oversight page already deliberately duplicates three small
formatting helpers rather than requiring the user-facing page file (see the
original Trade Log spec's rationale). The new chart/sparkline functions live
in `includes/trade_metrics.php` (already shared, no duplication needed) —
`admin/trades.php` calls the same `fetch_balance_history()` /
`fetch_recent_trade_values()` / `fetch_win_rate_trend()` /
`render_sparkline_svg()` functions the user page does, keeping the admin
view's charts identical in behavior to the user's, just fed a different
`user_id`.

## Deferred: Parts B and C (designed, not built)

Recorded here so the schema decisions are coherent with what Part A ships,
per the site owner's request — **no migration file is written this pass**;
each gets its own migration when actually built.

### Part B — Daily Loss Limit tracker

Add a nullable `daily_loss_limit DECIMAL(14,2)` column to `brokerage_accounts`,
alongside the existing `beginning_balance`/`trading_year` columns (same
per-account shape, editable in the same "Manage Accounts" form). Displayed
as a gauge: today's closed-trade PnL (only negative values count toward the
limit) summed across the currently-filtered accounts, compared against the
summed `daily_loss_limit` of those same accounts (accounts with a NULL
limit are excluded from the sum and don't block the gauge from rendering
for the accounts that do have one set).

### Part C — Trading Objectives tracker

New table:

```sql
CREATE TABLE trading_objectives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rule_type ENUM('min_win_rate', 'max_consecutive_losses') NOT NULL,
    threshold DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

Two rule types only, per the site owner's confirmed scope (win rate
threshold, max consecutive losses) — R-multiple and consistency rules were
considered and explicitly not selected. Evaluated against the currently
filtered accounts' closed trades: `min_win_rate` compares
`fetch_trade_stats()['overview']['win_rate']` against `threshold`;
`max_consecutive_losses` walks closed trades in chronological order
counting the longest consecutive-loss streak and compares against
`threshold`. Rendered as a pass/fail checklist, one row per objective the
user has defined, with a small management UI (add/delete a rule) similar in
shape to the existing Manage Accounts panel.

## Testing / verification

Manual, no test suite on this project (consistent with every prior Trade
Log task):

1. `php -l` on every changed/new PHP file.
2. With zero closed trades: confirm the three sparkline tiles render with no
   sparkline (not a broken/empty SVG) and the account-balance chart shows
   the empty-state message instead of a blank canvas.
3. Log a mix of wins/losses/opens across two accounts: confirm sparklines
   show a real trend line, the trend indicator (▲/▼) matches the direction
   of the underlying data, and the balance chart's hover tooltip shows the
   correct date/balance at each point.
4. Toggle light/dark theme: confirm stat tile glow, sparkline color, and the
   balance chart's colors all re-theme correctly (the chart's colors are
   read from CSS custom properties at render/init time, not hardcoded).
5. Use the account filter to select a subset of accounts: confirm all three
   new chart/sparkline data sources re-filter along with the rest of the
   dashboard.
6. Confirm `assets/js/chart.min.js` is served from the site's own domain
   (view page source / network tab) — no request to any external CDN.
7. On `admin/trades.php`, confirm the same charts render for a selected
   user's data, read-only (no controls added).
