# Schwab Sync API Design

## Purpose

Replace the manual "run an IAN export script → download a CSV → upload it
in the browser" workflow (used for trade history, daily account snapshots,
and current position market values) with an automated push from IAN to
HeisenbergStudios, scheduled to run on its own.

This is a two-repository change:
- **HeisenbergStudios** (this repo): three new authenticated JSON API
  endpoints that accept the same data the CSV importers already accept.
- **IAN** (`C:\GitHub\AI-Coding\IAN`): a new sync script that fetches from
  Schwab and POSTs to those endpoints, plus a Windows Task Scheduler entry
  to run it automatically.

The existing CSV importer pages are unaffected and remain available as a
manual fallback.

## Context

Three CSV import pipelines exist today, each with its own page:
- `sections/metrics_trade_import.php` — trade history (blind INSERT, no
  dedupe; `source` column already has an unused `'api'` ENUM value)
- `sections/metrics_snapshot_import.php` — daily account value
  (`INSERT ... ON DUPLICATE KEY UPDATE` on `(account_id, snapshot_date)`)
- `sections/metrics_market_value_import.php` — current position values
  (DELETE-then-INSERT per account — a full replace, not a merge)

IAN already has Schwab OAuth wired up (`src/orchestrator/schwab_auth.py`,
`get_client()`) and three scripts that compute this same data and write it
to CSV (`scripts/export_schwab_trades.py`, `export_daily_snapshots.py`,
`export_current_positions.py`). This spec reuses their computation logic;
it does not change how the data is derived, only how it reaches the site.

`brokerage_accounts` stores only a user-chosen `brokerage_name` and
`account_label` — no real account number is stored on the site today, and
this design does not change that.

## Non-Goals

- No changes to the CSV importer pages or their behavior.
- No UI for managing the API token or account mapping (both are config
  files/`.env`, edited by hand — this is a single-operator tool).
- No per-account token scoping (see Security below — explicitly accepted).
- No change to how historical backfill was already done; this covers
  *ongoing* automated sync going forward.

## Site-Side Changes (HeisenbergStudios)

### Auth

- New `.env` variable `API_SYNC_TOKEN` — a random 64-hex-char secret,
  generated once, added to `.env` (git-ignored, same pattern as `DB_PASS`)
  and to `.env.example` as a placeholder.
- New `includes/api_auth.php`:
  ```php
  function require_api_token(): void {
      $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
      $expected = getenv('API_SYNC_TOKEN') ?: '';
      $provided = '';
      if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
          $provided = trim($m[1]);
      }
      if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
          http_response_code(401);
          header('Content-Type: application/json');
          echo json_encode(['error' => 'unauthorized']);
          exit;
      }
  }
  ```
  Every endpoint calls this before touching `$_POST`/the request body or
  the database.

### Schema change

`trades` gets a nullable `external_id` column so API-synced rows can be
upserted instead of blindly inserted (manual/CSV rows are untouched —
MySQL unique constraints permit multiple NULLs):

```sql
ALTER TABLE trades ADD COLUMN external_id VARCHAR(64) NULL AFTER source;
ALTER TABLE trades ADD UNIQUE KEY unique_account_external (account_id, external_id);
```

Saved as `setup/schema_additions_trade_external_id.sql`.

### Endpoints

All three live in a new `api/` directory at the project root, are
POST-only (405 otherwise), call `require_api_token()` first, then
`json_decode(file_get_contents('php://input'), true)`, expecting:

```json
{ "account_id": 3, "rows": [ { ... }, { ... } ] }
```

`account_id` must exist in `brokerage_accounts` (404 if not — no
ownership check is possible here, see Security). Each endpoint validates
rows with the same rules its CSV-importer twin already uses, skips
invalid rows into an `errors` array (same "reported but non-fatal" model
as the CSV importers), and returns:

```json
{ "imported": 12, "errors": ["row 4: invalid ticker"] }
```

- **`api/import_trades.php`** — row shape:
  `{external_id, ticker, side, trade_type, strategy, open_date, open_price,
  open_qty, open_fees, close_date, close_price, close_fees, stop_loss,
  take_profit, notes}` (same fields as the CSV importer, plus
  `external_id`, required and non-empty for this endpoint since it's the
  upsert key). Uses:
  ```sql
  INSERT INTO trades (account_id, external_id, ticker, side, trade_type,
      strategy, open_date, open_price, open_qty, open_fees, close_date,
      close_price, close_fees, stop_loss, take_profit, notes, source)
  VALUES (:account_id, :external_id, :ticker, :side, :trade_type,
      :strategy, :open_date, :open_price, :open_qty, :open_fees,
      :close_date, :close_price, :close_fees, :stop_loss, :take_profit,
      :notes, 'api')
  ON DUPLICATE KEY UPDATE
      ticker = VALUES(ticker), side = VALUES(side), trade_type = VALUES(trade_type),
      strategy = VALUES(strategy), open_date = VALUES(open_date),
      open_price = VALUES(open_price), open_qty = VALUES(open_qty),
      open_fees = VALUES(open_fees), close_date = VALUES(close_date),
      close_price = VALUES(close_price), close_fees = VALUES(close_fees),
      stop_loss = VALUES(stop_loss), take_profit = VALUES(take_profit),
      notes = VALUES(notes)
  ```
- **`api/import_snapshots.php`** — row shape: `{date, total_value}`. Same
  `ON DUPLICATE KEY UPDATE` on `(account_id, snapshot_date)` the CSV
  importer already uses.
- **`api/import_market_values.php`** — row shape: `{ticker, market_value}`.
  Same transaction-wrapped DELETE-then-INSERT-per-account the CSV importer
  already uses (full replace, not a merge — a ticker missing from the
  batch is "no longer held").

## IAN-Side Changes

### Account mapping

New `config/heisenberg_accounts.json` (git-ignored — contains no secrets,
but is local deployment config, not portable):

```json
{ "1": 3, "2": 5, "3": 7 }
```

Keys are the same 1-based index the existing export scripts already use
(order of `get_account_numbers()`); values are the target
`brokerage_accounts.id` on the site, copied by hand once from the site's
account list.

### Sync script

New `scripts/sync_to_heisenberg.py`:
- Reads `HEISENBERG_API_BASE` and `HEISENBERG_API_TOKEN` from `.env`
  (new variables, alongside the existing Schwab ones).
- Reads `config/heisenberg_accounts.json` for the account mapping.
- For each mapped Schwab account:
  - **Market values**: reuses `fetch_current_positions()` from
    `export_current_positions.py` (imported as a module, not
    reimplemented), POSTs to `api/import_market_values.php`.
  - **Today's snapshot**: computed directly from `get_account` — 
    `total_value = cashBalance + sum(position['marketValue'] for position in positions)`.
    No price-history lookups (that complexity in
    `export_daily_snapshots.py` only exists for *historical*
    reconstruction; today's values are already live from Schwab). POSTs a
    single-row batch `{date: today, total_value}` to
    `api/import_snapshots.php`.
  - **Trades**: reuses the reconstruction logic from
    `export_schwab_trades.py` (imported as a module), with each row's
    `positionId` carried through as `external_id`. POSTs to
    `api/import_trades.php`.
- Logs a one-line summary per account per data type (imported count +
  any errors returned by the endpoint) to stdout, which Task Scheduler
  captures.
- Never calls `place_order`/`replace_order`/`cancel_order` (same
  read-only guarantee as the existing scripts).

`export_schwab_trades.py`, `export_daily_snapshots.py`, and
`export_current_positions.py` are refactored minimally — only enough to
expose their core compute functions as importable, without changing their
existing CLI/CSV behavior — so they keep working standalone for manual
backfill.

### Scheduling

A Windows Task Scheduler entry (`schtasks /create ...`, documented in the
implementation plan) runs `sync_to_heisenberg.py` once daily via the
project's `.venv` Python. Failures (network error, 401, etc.) exit
non-zero and are visible in Task Scheduler's history; no separate
alerting is in scope.

## Security

- The bearer token is the entire authorization boundary — any holder of
  `API_SYNC_TOKEN` can write to any `account_id` on the site. No
  per-account scoping, no session/user tie-in. **Explicitly accepted** for
  this single-operator personal site (confirmed during design).
- No Schwab account number, cash balance, or other raw account identifier
  ever leaves IAN — only ticker/value/date data and the site's own integer
  `account_id`, matching the original "no account information, just
  quantitative results" constraint from the Trade Log's original design.
- Auth failure responses are generic (`401 {"error":"unauthorized"}`) —
  no distinction between "bad token" and "token missing" that could help
  an attacker.
- HTTPS is assumed (the live site already serves over HTTPS); the token
  travels as a header, never as a URL parameter or in a GET request.

## Testing

- `php -l` on all new/modified PHP files.
- Manual `curl`/Python-`requests` smoke tests against each endpoint with
  valid and invalid tokens, and valid/invalid row payloads, before wiring
  the real sync script to them.
- One real end-to-end run of `sync_to_heisenberg.py` against live Schwab
  data, followed by a visual check that both allocation rings and the
  balance chart update correctly on the site.

## Open Questions

None — all four design sections (architecture, site-side, IAN-side,
security) were reviewed and approved during brainstorming.
