# Trade Log Section — Design

## Summary

Replace the placeholder `sections/metrics_tradelog.php` stub with a real,
multi-user trade journal that emulates the "GRAY Simple Stock Trading
Journal" spreadsheet the site owner already uses: per-trade entry (manual
form or CSV import), a calculated performance dashboard (win rate, PnL,
R-multiple, day-of-week / long-short / day-swing / per-strategy
breakdowns), and per-user ownership so any approved account can keep a
private journal that only they and the webmaster admin can see. Trades are
scoped to brokerage accounts (a user can have several, across several
brokerages) so metrics can be computed across any, some, or all of them.

## Background

The site (`c:\GitHub\AI-Coding\HeisenbergStudios`) is a PHP + MySQL portal.
Relevant existing pieces:

- `includes/auth.php` — session-based auth. `require_access($level)` gates
  a page by numeric access level (1 read_only … 5 webmaster).
  `is_logged_in()` / `current_user()` give lighter-weight checks. As of the
  approval-gate change (2026-08-13), `users.is_active` must also be 1 for a
  session to exist at all — self-registered accounts start inactive and
  need a webmaster's approval via `admin/users.php` before they can log in.
- `includes/nav.php` / DB tables `menu_categories` / `menu_items` — the nav
  is entirely DB-driven, filtered by access level. The "Metrics" category
  already has a `metrics-tradelog` item (slug) pointing at
  `sections/metrics_tradelog.php`, currently gated at level 2 (restricted)
  — see "Access level correction" below.
- `index.php` — homepage card grid from the `cards` table. A "Trade Log"
  card already exists, linking to the same page, also currently gated at
  level 2.
- `admin/cards.php` / `admin/folders.php` / `admin/users.php` — existing
  admin pages share one pattern: single PHP file, `require_access()` gate
  at top, POST handling for add/edit/delete actions inline above an HTML
  table, no framework, raw PDO with prepared statements throughout. This
  design follows the same pattern for the new admin/user-facing pages.
- No ORM, no test suite, no JS framework — plain PHP/PDO/MySQL, vanilla JS
  only where needed (there currently isn't any beyond the theme switcher).

### Source material

The user's real trading journal (`GRAY Simple Stock Trading Journal`,
Google Sheets) was read in full to derive the data model. It has:

- A **SET-UP** sheet: beginning balance, current trading year, and a
  user-editable list of named strategies with definitions, each rolling up
  total trades / net gain-loss / win-loss counts / avg gain / avg loss /
  win rate / reward-risk per strategy.
- A **JOURNAL** sheet: the actual trade-entry table (status, side, open/
  close date, strategy, buy/sell, ticker, qty, open/close price, open/
  close fees, notes, stop-loss, take-profit, R, plus many formula-derived
  helper columns for the dashboard pivots) alongside an overview panel
  (win/loss/breakeven counts, account balance, growth %, max/min/avg win
  and loss, day-of-week and monthly breakdowns, long-vs-short and
  day-vs-swing splits) and a full monthly calendar heatmap.

## Goals

- Let any **approved** logged-in user maintain their own private trade
  journal across one or more brokerage accounts, with account-level
  beginning balance / trading year tracking.
- Reproduce the spreadsheet's core calculated dashboard (win rate, PnL,
  R-multiple, day-of-week, long/short, day/swing, per-strategy breakdown)
  computed at query time from stored trade data — no stored derived values
  to drift out of sync.
- Support manual trade entry and CSV import.
- Give the webmaster a read-only oversight view across any user's data.
- Keep the schema forward-compatible with a future brokerage-API
  auto-import (explicitly not built now).

## Non-goals (explicitly deferred)

- The spreadsheet's monthly calendar heatmap view.
- Brokerage API integration / automated trade import. The `trades.source`
  column exists so API-sourced rows can slot in later without a schema
  change, but no API client is built in this pass.
- The "Asset Allocation" Metrics sub-item — untouched, stays a stub.
- Editing another user's trades (even the admin view is read-only).

## Privacy / ownership model (context for future readers)

Earlier in this project, "Metrics" was scoped with a rule of "no account
balances or personal details, only quantitative trade results." That rule
has since been explicitly superseded for this feature: the site owner
confirmed balance/account-value data is acceptable here as long as it's
visible only to the account's owner and the webmaster admin — not
sitewide. That's why `brokerage_accounts` stores `beginning_balance`
without restriction beyond the ownership check below.

## Data model

New migration file `setup/schema_additions_trade_log.sql`, applied after
all prior `schema_additions_*.sql` files.

```sql
CREATE TABLE brokerage_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    brokerage_name VARCHAR(100) NOT NULL,
    account_label VARCHAR(100) NOT NULL,
    beginning_balance DECIMAL(14,2) NULL,
    trading_year INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    ticker VARCHAR(20) NOT NULL,
    side ENUM('long','short') NOT NULL,
    trade_type ENUM('day','swing') NOT NULL,
    strategy VARCHAR(100) NULL,
    open_date DATE NOT NULL,
    open_price DECIMAL(14,4) NOT NULL,
    open_qty DECIMAL(14,4) NOT NULL,
    open_fees DECIMAL(10,2) NOT NULL DEFAULT 0,
    close_date DATE NULL,
    close_price DECIMAL(14,4) NULL,
    close_fees DECIMAL(10,2) NULL,
    stop_loss DECIMAL(14,4) NULL,
    take_profit DECIMAL(14,4) NULL,
    notes TEXT NULL,
    source ENUM('manual','csv','api') NOT NULL DEFAULT 'manual',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES brokerage_accounts(id) ON DELETE CASCADE
);
```

A trade's owner is implicit through its account (`trades.account_id` →
`brokerage_accounts.user_id`) — no redundant `user_id` on `trades` itself,
so ownership can't drift out of sync between the two.

Nothing is stored for PnL $, PnL %, R-multiple, or win/loss result — all
computed at query time (see Calculations).

### Access level correction

`metrics-tradelog`'s `menu_items` row and matching `cards` row are
currently gated at level 2 (restricted), left over from before the
per-user ownership model was decided. Since ownership (not access tier) is
now the security boundary — any approved account, even a fresh level-1
read_only registration, should be able to keep their own journal — this
migration also lowers both to level 1:

```sql
UPDATE menu_items SET min_access_level = 1 WHERE slug = 'metrics-tradelog';
UPDATE cards SET min_access_level = 1 WHERE link_url = '/sections/metrics_tradelog.php';
```

## Access control

| Who | What they can do |
|---|---|
| Not logged in, or logged in but not yet approved (`is_active = 0`) | Redirected to login, same as any other gated page |
| Any approved user (level 1+) | Full CRUD on their own `brokerage_accounts` and `trades` (rows reachable through their own accounts only) |
| Webmaster (level 5) | Read-only view of any user's accounts/trades via `admin/trades.php`; cannot edit or delete another user's data |

Every query against `trades` or `brokerage_accounts` on the user-facing
page filters `WHERE ba.user_id = :sessionUserId` via a join — there is no
endpoint that trusts a client-supplied account or trade ID without also
checking it belongs to the current session user (mirrors how
`files/download.php` re-validates ownership server-side rather than
trusting the query string).

## Pages

### `sections/metrics_tradelog.php`

Gated with `require_access('read_only')` (i.e., must be logged in and
approved — the lowest tier, since ownership is the real boundary). Single
page, several sections top to bottom:

1. **Manage Accounts** — small table of the user's `brokerage_accounts`
   (brokerage name, label, beginning balance, trading year) with inline
   add/edit/delete forms, same pattern as `admin/cards.php`. If the user
   has zero accounts, this is the only thing shown, with a prompt to add
   one before anything else becomes available. Deleting an account cascades
   to delete every trade logged under it (`ON DELETE CASCADE`), so its
   delete action requires an explicit confirm step naming how many trades
   will be removed.
2. **Account filter** — checkboxes, one per account, defaulting to all
   checked. Only rendered when the user has 2+ accounts. Selection
   determines which accounts feed everything below (via a GET param
   array, re-submitted on each filter change).
3. **Dashboard** — see Calculations below.
4. **Journal table** — paginated (25 trades per page), most recent first,
   with Edit / Delete per row and an "Add Trade" form (ticker, side,
   trade_type, strategy [`<datalist>` autocomplete sourced from the user's
   own distinct past `strategy` values], account [dropdown, required],
   open_date, open_price, open_qty, open_fees, close_date, close_price,
   close_fees, stop_loss, take_profit, notes). Close fields are optional —
   omitting close_date leaves the trade "Open."
5. **Import CSV** link to `sections/metrics_trade_import.php`.

### `sections/metrics_trade_import.php`

`require_access('read_only')`. Form: pick one of the user's own accounts,
upload a `.csv`. Expected header row (case-insensitive, order-independent):
`ticker, side, trade_type, strategy, open_date, open_price, open_qty,
open_fees, close_date, close_price, close_fees, stop_loss, take_profit,
notes`. Each row is validated independently — a bad row (missing
required field, unparseable number/date, `side`/`trade_type` not matching
the enum) is skipped and listed in a report at the end (`row 14: invalid
side "Long/Short"`) rather than failing the whole import. Valid rows are
inserted with `source = 'csv'`. Redirects back to `metrics_tradelog.php`
with a summary message on completion.

### `admin/trades.php`

`require_access('webmaster')`. A user-picker dropdown (all users, most
recently active first) at the top; selecting one renders that user's
accounts, the same dashboard, and the same journal table — but with no
Edit/Delete/Add controls anywhere on the page. Reuses the same rendering
and calculation code as the user-facing page, just fed a different
`user_id` and with edit affordances stripped, to avoid the dashboard math
drifting between the two views over time.

### `includes/trade_metrics.php`

New shared helper, included by both `sections/metrics_tradelog.php` and
`admin/trades.php`, so the stats math exists exactly once. Holds:

- `compute_trade_pnl(array $trade): ?array` — returns
  `['pnl_dollars' => ..., 'pnl_percent' => ..., 'r_multiple' => ?float,
  'result' => 'win'|'loss'|'breakeven'|null]` for a single trade row, or
  `null` for an open trade (no close data yet).
- `fetch_trade_stats(PDO $pdo, array $accountIds): array` — runs the
  aggregate queries (overview counts, day-of-week, long/short, day/swing,
  per-strategy) scoped to the given account IDs and returns a structured
  array the page templates render from.

## Calculations

All computed at query/render time, never stored:

- **PnL $** — long: `(close_price − open_price) × open_qty − open_fees −
  close_fees`. Short: `(open_price − close_price) × open_qty − open_fees −
  close_fees`. Only defined once `close_date`/`close_price` are set.
- **PnL %** — `pnl_dollars / (open_price × open_qty) × 100`.
- **R-multiple** — `pnl_dollars / (|open_price − stop_loss| × open_qty)`,
  only when `stop_loss` is set; displayed as `-` otherwise (matches the
  spreadsheet).
- **Result** — win/loss/breakeven from `pnl_dollars`'s sign; `null`
  (shown as "Open") when the trade has no close data.
- **Dashboard sections**: overview (total/open/win/loss/breakeven counts,
  win rate, net PnL $, avg win, avg loss); day-of-week PnL + trade count
  (grouped by `DAYOFWEEK(close_date)`, Mon–Fri); long vs. short (trade
  count + PnL); day vs. swing (trade count + PnL); per-strategy breakdown
  (grouped by the free-text `strategy` column: total/win/loss trades, net
  PnL, avg gain, avg loss, win rate; trades with no strategy set are
  grouped under an "Uncategorized" bucket rather than excluded).
- **Monthly/yearly views**, if shown, group by the trade's own
  `open_date`/`close_date` — **not** by each account's `trading_year`
  column, since accounts being viewed together (via the account filter)
  can have different trading years. `trading_year` is informational only
  (what year the account/profile "started"), not a filter.
- **Account growth %**, where shown: `(net PnL $ across selected accounts)
  / (sum of selected accounts' beginning_balance) × 100`. Only meaningful
  when at least one selected account has a non-null `beginning_balance`.

## Styling

Scoped rules appended to `assets/css/main.css` (matches existing
convention — no new stylesheet): `.trade-dashboard` (stat-tile grid,
reusing `--color-*` tokens), `.trade-journal-table` (dense data table,
similar to `.admin-table`), `.account-filter` (checkbox row), `.trade-form`
(reuses `.admin-form` styling from `admin/cards.php` where possible rather
than duplicating it).

## Testing / verification

Manual, no test suite on this project:

1. Register a new account, confirm it can't log in until approved
   (existing behavior, unaffected by this change).
2. As that approved level-1 user, confirm `sections/metrics_tradelog.php`
   is reachable and prompts to add a brokerage account first.
3. Add two brokerage accounts; confirm the account filter appears once
   there are 2+.
4. Add several manual trades (mix of open/closed, long/short, day/swing,
   with and without stop-loss) across both accounts; confirm dashboard
   numbers match hand-calculated expectations, and that unchecking one
   account in the filter changes the numbers accordingly.
5. Import a CSV with a mix of valid and intentionally-broken rows; confirm
   valid rows appear and the broken-row report is accurate.
6. Edit and delete a trade; confirm dashboard numbers update.
7. Log in as a second user, confirm they see none of the first user's
   accounts or trades.
8. As webmaster, visit `admin/trades.php`, select the first user, confirm
   their data displays with no edit/delete controls present, and that
   selecting a different user shows different data.
9. Attempt to submit a trade/account edit for an ID that belongs to
   another user (e.g. by tampering with a hidden form field) — confirm
   the server-side ownership check rejects it rather than trusting the
   submitted ID.
