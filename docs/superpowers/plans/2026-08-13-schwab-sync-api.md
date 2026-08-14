# Schwab Sync API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the manual CSV export/upload workflow with a scheduled, authenticated push from IAN directly into HeisenbergStudios, covering trades, daily account snapshots, and current position market values.

**Architecture:** Three new POST-only JSON endpoints under `api/` on HeisenbergStudios, guarded by a shared bearer-token check, accept the same row shapes the existing CSV importers already validate. A new IAN script (`scripts/sync_to_heisenberg.py`) reuses the existing Schwab-reconstruction functions from the three export scripts and POSTs their output to those endpoints instead of writing CSV, run daily via Windows Task Scheduler.

**Tech Stack:** PHP 8.2 / PDO (site), Python 3 / httpx / pytest (IAN), MySQL (MariaDB, Bluehost-hosted, remote).

**Spec:** `docs/superpowers/specs/2026-08-13-schwab-sync-api-design.md`

## Global Constraints

- Every `api/*.php` endpoint is POST-only (405 for anything else) and calls the shared `require_api_token()` before touching `$_POST`/the request body or the database.
- Auth is a single bearer token compared with `hash_equals()` against `API_SYNC_TOKEN` from `.env` — no per-account scoping, no session/ownership check (explicitly accepted in the spec's Security section; this is a single-operator personal site).
- `trades.external_id` is nullable; the unique key is `(account_id, external_id)` so existing manual/CSV rows (external_id NULL) are unaffected — MySQL unique constraints permit multiple NULLs.
- `api/import_trades.php` requires `external_id` on every row (rejects rows missing it) since it's the sole upsert key; the other two endpoints don't need one (snapshots upsert on date, market values fully replace per account).
- IAN's Schwab-facing code never calls `place_order`/`replace_order`/`cancel_order` — read-only throughout, matching every existing script in this project.
- Real secrets (`API_SYNC_TOKEN`, `DB_PASS`, etc.) are never written into any file this plan's steps commit to git — `.env` files stay local and git-ignored; only placeholder lines go into `.env.example`.
- No existing CSV importer page or its behavior changes.

---

### Task 1: `trades.external_id` schema migration

**Files:**
- Create: `setup/schema_additions_trade_external_id.sql`

**Interfaces:**
- Produces: `trades.external_id VARCHAR(64) NULL` column and `unique_account_external (account_id, external_id)` unique key, consumed by Task 4's upsert.

- [ ] **Step 1: Write the migration file**

```sql
-- ============================================================
-- ADDITIONS to site_portal schema
-- Adds trades.external_id so the Schwab Sync API (api/import_trades.php)
-- can upsert instead of blindly inserting -- a daily automated sync must
-- be safe to re-run without creating duplicate trade rows. NULL for every
-- existing manual/CSV-imported trade; MySQL unique constraints permit
-- multiple NULLs, so this doesn't constrain those rows against each other.
-- Run this AFTER schema_additions_trade_log.sql has been imported.
-- ============================================================

ALTER TABLE trades ADD COLUMN external_id VARCHAR(64) NULL AFTER source;
ALTER TABLE trades ADD UNIQUE KEY unique_account_external (account_id, external_id);
```

- [ ] **Step 2: Apply the migration to production and verify**

Read `DB_HOST` (use `162.241.224.233`, the remote hostname — `.env`'s
`DB_HOST=localhost` is only correct when running on the server itself),
`DB_NAME`, `DB_USER`, `DB_PASS` from `c:/GitHub/AI-Coding/HeisenbergStudios/.env`.
Do not print these values or write them into any file that gets committed.
Write them to a `[client]`-section temp file (host/user/password/database
keys) in the OS scratch/temp directory, then:

```bash
"c:/xampp/mysql/bin/mysql.exe" --defaults-extra-file="<temp file path>" < "c:/GitHub/AI-Coding/HeisenbergStudios/setup/schema_additions_trade_external_id.sql"
"c:/xampp/mysql/bin/mysql.exe" --defaults-extra-file="<temp file path>" -e "DESCRIBE trades;"
```

Expected: `DESCRIBE trades` shows an `external_id` column (`varchar(64)`,
nullable) after `source`, and `SHOW INDEX FROM trades;` shows
`unique_account_external` covering `(account_id, external_id)`.

Delete the temp credentials file immediately after.

- [ ] **Step 3: Commit**

```bash
git add setup/schema_additions_trade_external_id.sql
git commit -m "Add trades.external_id for idempotent API-synced upserts"
```

---

### Task 2: Shared API auth helper + `api/import_market_values.php`

**Files:**
- Create: `includes/api_auth.php`
- Create: `api/import_market_values.php`
- Modify: `.env.example`

**Interfaces:**
- Produces: `require_post_method(): void`, `require_api_token(): void`,
  `read_json_body(): array`, `require_valid_account(PDO $pdo, $accountId): array`
  in `includes/api_auth.php` — consumed by Tasks 3 and 4.
- Consumes: `get_db_connection(): PDO` from `includes/config.php` (existing).

- [ ] **Step 1: Write `includes/api_auth.php`**

```php
<?php
/**
 * Shared bearer-token auth for the Schwab Sync API (api/*.php).
 * These endpoints are hit by IAN's scheduled sync script, not a logged-in
 * browser session, so there's no user/ownership check available here --
 * the token itself is the entire authorization boundary. Accepted for
 * this single-operator personal site (see docs/superpowers/specs/
 * 2026-08-13-schwab-sync-api-design.md, Security section).
 */
require_once __DIR__ . '/config.php';

function require_post_method(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'method not allowed']);
        exit;
    }
}

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

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'invalid JSON body']);
        exit;
    }
    return $data;
}

function require_valid_account(PDO $pdo, $accountId): array {
    $stmt = $pdo->prepare('SELECT * FROM brokerage_accounts WHERE id = :id');
    $stmt->execute(['id' => (int)$accountId]);
    $account = $stmt->fetch();
    if (!$account) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'account not found']);
        exit;
    }
    return $account;
}
```

- [ ] **Step 2: Write `api/import_market_values.php`**

Same replace-per-account semantics as `sections/metrics_market_value_import.php`'s importer, reading JSON instead of a CSV upload:

```php
<?php
require_once __DIR__ . '/../includes/api_auth.php';

require_post_method();
require_api_token();

$pdo = get_db_connection();
$data = read_json_body();

$accountId = (int)($data['account_id'] ?? 0);
require_valid_account($pdo, $accountId);

$rows = $data['rows'] ?? null;
if (!is_array($rows)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'rows must be an array']);
    exit;
}

$validRows = [];
$errors = [];
foreach ($rows as $i => $row) {
    $ticker = isset($row['ticker']) ? strtoupper(trim((string)$row['ticker'])) : '';
    $marketValue = $row['market_value'] ?? null;

    if ($ticker === '') {
        $errors[] = "row $i: missing ticker";
        continue;
    }
    if (!is_numeric($marketValue)) {
        $errors[] = "row $i: market_value must be a number";
        continue;
    }

    $validRows[] = ['ticker' => $ticker, 'market_value' => $marketValue];
}

$pdo->beginTransaction();
$deleteStmt = $pdo->prepare('DELETE FROM position_market_values WHERE account_id = :account_id');
$deleteStmt->execute(['account_id' => $accountId]);

$insertStmt = $pdo->prepare(
    'INSERT INTO position_market_values (account_id, ticker, market_value)
     VALUES (:account_id, :ticker, :market_value)'
);
$imported = 0;
foreach ($validRows as $row) {
    $insertStmt->execute([
        'account_id' => $accountId,
        'ticker' => $row['ticker'],
        'market_value' => $row['market_value'],
    ]);
    $imported++;
}
$pdo->commit();

header('Content-Type: application/json');
echo json_encode(['imported' => $imported, 'errors' => $errors]);
```

- [ ] **Step 3: Add the new env var placeholder to `.env.example`**

Append (do not touch the existing lines):

```
API_SYNC_TOKEN=
```

- [ ] **Step 4: Syntax-check**

```bash
"c:/xampp/php/php.exe" -l "c:/GitHub/AI-Coding/HeisenbergStudios/includes/api_auth.php"
"c:/xampp/php/php.exe" -l "c:/GitHub/AI-Coding/HeisenbergStudios/api/import_market_values.php"
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Generate a real token, add it to `.env`, apply it to production, and deploy**

Generate a random 64-hex-char token: `python -c "import secrets; print(secrets.token_hex(32))"`.
Add `API_SYNC_TOKEN=<generated value>` to `c:/GitHub/AI-Coding/HeisenbergStudios/.env`
(git-ignored — never commit this file).

`.env` is git-ignored, so it is never deployed by `git push` — the
production server has its own separate `.env` file that must be edited
directly (via cPanel File Manager, SSH, or however you normally reach the
server's filesystem) to add the *same* `API_SYNC_TOKEN=<generated value>`
line. Auth fails unless both copies match exactly.

- [ ] **Step 6: Commit, push, and deploy**

```bash
git add includes/api_auth.php api/import_market_values.php .env.example
git commit -m "Add Schwab Sync API auth helper and market-value endpoint"
git push
```

Then deploy per your existing process so `api/import_market_values.php`
is live — the curl tests below hit the live URL and need the deployed
code in place first.

- [ ] **Step 7: Create a throwaway test account fixture**

Using the same temp-credentials-file pattern:

```sql
INSERT INTO brokerage_accounts (user_id, brokerage_name, account_label)
VALUES ((SELECT id FROM users ORDER BY id LIMIT 1), '__API_SYNC_TEST__', 'temp');

SELECT id FROM brokerage_accounts WHERE brokerage_name = '__API_SYNC_TEST__';
```

Note the returned `id` — Tasks 3 and 4 reuse this same fixture account by
re-running that `SELECT` (don't hardcode the numeric id anywhere; look it
up by the `__API_SYNC_TEST__` marker each time, since a fresh subagent in
a later task won't have this task's output in context).

- [ ] **Step 8: Test the live endpoint with curl**

Using the fixture account's id (call it `$TEST_ID`) and the token from `.env` (call it `$TOKEN`):

```bash
# No auth header -> 401
curl -s -o /dev/null -w "%{http_code}\n" -X POST https://www.heisenbergstudios.com/api/import_market_values.php \
  -H "Content-Type: application/json" -d "{\"account_id\":$TEST_ID,\"rows\":[]}"
# Expected: 401

# Nonexistent account -> 404
curl -s -o /dev/null -w "%{http_code}\n" -X POST https://www.heisenbergstudios.com/api/import_market_values.php \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"account_id":999999999,"rows":[]}'
# Expected: 404

# Valid request -> 200, imported:1
curl -s -X POST https://www.heisenbergstudios.com/api/import_market_values.php \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d "{\"account_id\":$TEST_ID,\"rows\":[{\"ticker\":\"TEST\",\"market_value\":123.45}]}"
# Expected: {"imported":1,"errors":[]}
```

Verify with `SELECT * FROM position_market_values WHERE account_id = $TEST_ID;`
via the same mysql pattern — expect one row, ticker `TEST`, value `123.45`.
Leave this row in place; Task 4's cleanup step removes the whole fixture
account (cascading to this row) at the end. If any curl test doesn't match
its expected result, fix the code and repeat Step 6's commit/push/deploy
as a new follow-up commit (never amend) before re-testing.

---

### Task 3: `api/import_snapshots.php`

**Files:**
- Create: `api/import_snapshots.php`

**Interfaces:**
- Consumes: `require_post_method()`, `require_api_token()`, `read_json_body()`,
  `require_valid_account(PDO, $accountId): array` from `includes/api_auth.php` (Task 2).

- [ ] **Step 1: Write `api/import_snapshots.php`**

Same upsert-on-`(account_id, snapshot_date)` semantics as
`sections/metrics_snapshot_import.php`'s importer:

```php
<?php
require_once __DIR__ . '/../includes/api_auth.php';

require_post_method();
require_api_token();

$pdo = get_db_connection();
$data = read_json_body();

$accountId = (int)($data['account_id'] ?? 0);
require_valid_account($pdo, $accountId);

$rows = $data['rows'] ?? null;
if (!is_array($rows)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'rows must be an array']);
    exit;
}

$upsertStmt = $pdo->prepare(
    'INSERT INTO account_value_snapshots (account_id, snapshot_date, total_value)
     VALUES (:account_id, :snapshot_date, :total_value)
     ON DUPLICATE KEY UPDATE total_value = VALUES(total_value)'
);

$imported = 0;
$errors = [];
foreach ($rows as $i => $row) {
    $date = isset($row['date']) ? trim((string)$row['date']) : '';
    $totalValue = $row['total_value'] ?? null;

    if ($date === '' || strtotime($date) === false) {
        $errors[] = "row $i: invalid or missing date";
        continue;
    }
    if (!is_numeric($totalValue)) {
        $errors[] = "row $i: total_value must be a number";
        continue;
    }

    $upsertStmt->execute([
        'account_id' => $accountId,
        'snapshot_date' => date('Y-m-d', strtotime($date)),
        'total_value' => $totalValue,
    ]);
    $imported++;
}

header('Content-Type: application/json');
echo json_encode(['imported' => $imported, 'errors' => $errors]);
```

- [ ] **Step 2: Syntax-check**

```bash
"c:/xampp/php/php.exe" -l "c:/GitHub/AI-Coding/HeisenbergStudios/api/import_snapshots.php"
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit, push, and deploy**

```bash
git add api/import_snapshots.php
git commit -m "Add Schwab Sync API daily-snapshot endpoint"
git push
```

Deploy per your existing process so `api/import_snapshots.php` is live —
the curl tests below hit the live URL and need the deployed code in place
first.

- [ ] **Step 4: Test with curl**

Look up the fixture account's id again: `SELECT id FROM brokerage_accounts WHERE brokerage_name = '__API_SYNC_TEST__';`

```bash
curl -s -X POST https://www.heisenbergstudios.com/api/import_snapshots.php \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d "{\"account_id\":$TEST_ID,\"rows\":[{\"date\":\"2026-08-01\",\"total_value\":1000.00}]}"
# Expected: {"imported":1,"errors":[]}

# Re-run the same request -- must upsert, not duplicate
curl -s -X POST https://www.heisenbergstudios.com/api/import_snapshots.php \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d "{\"account_id\":$TEST_ID,\"rows\":[{\"date\":\"2026-08-01\",\"total_value\":1050.00}]}"
# Expected: {"imported":1,"errors":[]}
```

Verify with `SELECT * FROM account_value_snapshots WHERE account_id = $TEST_ID;`
— expect exactly ONE row for `2026-08-01`, with `total_value = 1050.00`
(the second call's value, proving upsert not duplication). If either curl
test doesn't match, fix the code and repeat Step 3's commit/push/deploy as
a new follow-up commit (never amend) before re-testing.

---

### Task 4: `api/import_trades.php` + fixture cleanup

**Files:**
- Create: `api/import_trades.php`

**Interfaces:**
- Consumes: `require_post_method()`, `require_api_token()`, `read_json_body()`,
  `require_valid_account(PDO, $accountId): array` from `includes/api_auth.php` (Task 2);
  `trades.external_id` column from Task 1.

- [ ] **Step 1: Write `api/import_trades.php`**

Same field validation as `sections/metrics_trade_import.php`'s importer,
plus a required `external_id` used as the upsert key:

```php
<?php
require_once __DIR__ . '/../includes/api_auth.php';

require_post_method();
require_api_token();

$pdo = get_db_connection();
$data = read_json_body();

$accountId = (int)($data['account_id'] ?? 0);
require_valid_account($pdo, $accountId);

$rows = $data['rows'] ?? null;
if (!is_array($rows)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'rows must be an array']);
    exit;
}

$upsertStmt = $pdo->prepare(
    "INSERT INTO trades (account_id, external_id, ticker, side, trade_type,
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
         notes = VALUES(notes)"
);

$imported = 0;
$errors = [];
foreach ($rows as $i => $row) {
    $get = fn(string $key) => isset($row[$key]) && $row[$key] !== null ? trim((string)$row[$key]) : '';

    $externalId = $get('external_id');
    $ticker = strtoupper($get('ticker'));
    $side = strtolower($get('side'));
    $tradeType = strtolower($get('trade_type'));
    $openDate = $get('open_date');
    $openPrice = $row['open_price'] ?? null;
    $openQty = $row['open_qty'] ?? null;

    if ($externalId === '') {
        $errors[] = "row $i: missing external_id";
        continue;
    }
    if ($ticker === '') {
        $errors[] = "row $i: missing ticker";
        continue;
    }
    if (!in_array($side, ['long', 'short'], true)) {
        $errors[] = "row $i: invalid side \"$side\" (must be long or short)";
        continue;
    }
    if (!in_array($tradeType, ['day', 'swing'], true)) {
        $errors[] = "row $i: invalid trade_type \"$tradeType\" (must be day or swing)";
        continue;
    }
    if ($openDate === '' || strtotime($openDate) === false) {
        $errors[] = "row $i: invalid or missing open_date";
        continue;
    }
    if (!is_numeric($openPrice) || !is_numeric($openQty)) {
        $errors[] = "row $i: open_price and open_qty must be numbers";
        continue;
    }

    $closeDate = $get('close_date');
    if ($closeDate !== '' && strtotime($closeDate) === false) {
        $errors[] = "row $i: invalid close_date";
        continue;
    }

    $closePrice = $row['close_price'] ?? null;
    $closeFees = $row['close_fees'] ?? null;
    $openFees = $row['open_fees'] ?? null;
    $stopLoss = $row['stop_loss'] ?? null;
    $takeProfit = $row['take_profit'] ?? null;

    foreach (['close_price' => $closePrice, 'close_fees' => $closeFees, 'open_fees' => $openFees,
              'stop_loss' => $stopLoss, 'take_profit' => $takeProfit] as $field => $value) {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $errors[] = "row $i: $field must be a number";
            continue 2;
        }
    }

    $upsertStmt->execute([
        'account_id' => $accountId,
        'external_id' => $externalId,
        'ticker' => $ticker,
        'side' => $side,
        'trade_type' => $tradeType,
        'strategy' => $get('strategy') ?: null,
        'open_date' => date('Y-m-d', strtotime($openDate)),
        'open_price' => $openPrice,
        'open_qty' => $openQty,
        'open_fees' => ($openFees !== null && $openFees !== '') ? $openFees : 0,
        'close_date' => $closeDate !== '' ? date('Y-m-d', strtotime($closeDate)) : null,
        'close_price' => ($closePrice !== null && $closePrice !== '') ? $closePrice : null,
        'close_fees' => ($closeFees !== null && $closeFees !== '') ? $closeFees : null,
        'stop_loss' => ($stopLoss !== null && $stopLoss !== '') ? $stopLoss : null,
        'take_profit' => ($takeProfit !== null && $takeProfit !== '') ? $takeProfit : null,
        'notes' => $get('notes') ?: null,
    ]);
    $imported++;
}

header('Content-Type: application/json');
echo json_encode(['imported' => $imported, 'errors' => $errors]);
```

- [ ] **Step 2: Syntax-check**

```bash
"c:/xampp/php/php.exe" -l "c:/GitHub/AI-Coding/HeisenbergStudios/api/import_trades.php"
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit, push, and deploy**

```bash
git add api/import_trades.php
git commit -m "Add Schwab Sync API trades endpoint with external_id upsert"
git push
```

Deploy per your existing process so `api/import_trades.php` is live — the
curl tests below hit the live URL and need the deployed code in place
first.

- [ ] **Step 4: Test with curl**

Look up the fixture account id again the same way as Task 3.

```bash
curl -s -X POST https://www.heisenbergstudios.com/api/import_trades.php \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d "{\"account_id\":$TEST_ID,\"rows\":[{\"external_id\":\"pos-1\",\"ticker\":\"TEST\",\"side\":\"long\",\"trade_type\":\"swing\",\"open_date\":\"2026-08-01\",\"open_price\":10,\"open_qty\":5}]}"
# Expected: {"imported":1,"errors":[]}

# Re-send the same external_id with a close added -- must UPDATE the same row, not insert a second one
curl -s -X POST https://www.heisenbergstudios.com/api/import_trades.php \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d "{\"account_id\":$TEST_ID,\"rows\":[{\"external_id\":\"pos-1\",\"ticker\":\"TEST\",\"side\":\"long\",\"trade_type\":\"swing\",\"open_date\":\"2026-08-01\",\"open_price\":10,\"open_qty\":5,\"close_date\":\"2026-08-05\",\"close_price\":12}]}"
# Expected: {"imported":1,"errors":[]}
```

Verify with `SELECT * FROM trades WHERE account_id = $TEST_ID AND external_id = 'pos-1';`
— expect exactly ONE row, with `close_date = 2026-08-05` and `close_price = 12.0000`
(the second call's values, proving upsert not duplication). If either curl
test doesn't match, fix the code and repeat Step 3's commit/push/deploy as
a new follow-up commit (never amend) before re-testing.

- [ ] **Step 5: Delete the test fixture (cleans up all three endpoints' test data)**

```sql
DELETE FROM brokerage_accounts WHERE brokerage_name = '__API_SYNC_TEST__';
```

This cascades to the `trades`, `position_market_values`, and
`account_value_snapshots` rows created during Tasks 2-4's tests (all
three tables' `account_id` foreign keys are `ON DELETE CASCADE`). Verify
with a `SELECT` on all three tables filtered to the (now-deleted) test
account id — expect zero rows. Delete the temp mysql credentials file.

---

### Task 5: IAN — carry `external_id` through trade reconstruction

**Files:**
- Modify: `C:\GitHub\AI-Coding\IAN\scripts\export_schwab_trades.py`
- Test: `C:\GitHub\AI-Coding\IAN\tests\test_export_schwab_trades.py` (new)

**Interfaces:**
- Modifies: `reconstruct_trades(transactions: list[dict]) -> tuple[list[dict], int]`
  — each row dict gains an `"external_id"` key (the position's Schwab
  `positionId`, as a string). Consumed by Task 6's sync script.

- [ ] **Step 1: Write the failing test**

```python
from scripts.export_schwab_trades import reconstruct_trades


def _trade_txn(activity_id, position_id, trade_date, effect, symbol, amount, price):
    return {
        "activityId": activity_id,
        "type": "TRADE",
        "positionId": position_id,
        "tradeDate": trade_date,
        "transferItems": [
            {
                "instrument": {"assetType": "EQUITY", "symbol": symbol},
                "positionEffect": effect,
                "amount": amount,
                "price": price,
            },
        ],
    }


def test_reconstruct_trades_carries_position_id_as_external_id():
    transactions = [
        _trade_txn(1, 555, "2026-08-01T00:00:00+0000", "OPENING", "TEST", 10, 100.0),
    ]

    rows, skipped = reconstruct_trades(transactions)

    assert skipped == 0
    assert len(rows) == 1
    assert rows[0]["external_id"] == "555"


def test_reconstruct_trades_external_id_stable_across_open_and_close():
    transactions = [
        _trade_txn(1, 555, "2026-08-01T00:00:00+0000", "OPENING", "TEST", 10, 100.0),
        _trade_txn(2, 555, "2026-08-05T00:00:00+0000", "CLOSING", "TEST", -10, 110.0),
    ]

    rows, _ = reconstruct_trades(transactions)

    assert len(rows) == 1
    assert rows[0]["external_id"] == "555"
    assert rows[0]["close_date"] == "2026-08-05"
```

- [ ] **Step 2: Run the test to verify it fails**

Run (from `C:\GitHub\AI-Coding\IAN`): `.venv\Scripts\python.exe -m pytest tests/test_export_schwab_trades.py -v`
Expected: FAIL — `KeyError: 'external_id'`.

- [ ] **Step 3: Implement**

In `scripts/export_schwab_trades.py`:

1. Add `"external_id"` to `CSV_COLUMNS` (at the end of the list), so the
   CSV writer accepts the new dict key without erroring (the existing
   site CSV importer looks up columns by name and ignores any it doesn't
   recognize, so this is a harmless additive column for manual CSV use):

```python
CSV_COLUMNS = [
    "ticker", "side", "trade_type", "strategy", "open_date", "open_price",
    "open_qty", "open_fees", "close_date", "close_price", "close_fees",
    "stop_loss", "take_profit", "notes", "external_id",
]
```

2. In `reconstruct_trades()`, change the loop variable and add the field
   to the appended row (currently `for _position_id, txns in by_position.items():`
   discards the id — rename it and use it):

```python
    for position_id, txns in by_position.items():
        opening_pairs = []
        closing_pairs = []
        for txn in txns:
            for item in _security_items(txn):
                effect = item.get("positionEffect")
                if effect == "OPENING":
                    opening_pairs.append((txn, item))
                elif effect == "CLOSING":
                    closing_pairs.append((txn, item))

        if not opening_pairs:
            skipped += 1
            continue
```

   and in the `rows.append({...})` call, add:

```python
            "external_id": str(position_id),
```

   as a new key in that dict (position doesn't matter, but keep it near
   `"notes"` for readability).

- [ ] **Step 4: Run the test to verify it passes**

Run: `.venv\Scripts\python.exe -m pytest tests/test_export_schwab_trades.py -v`
Expected: PASS (2 tests).

- [ ] **Step 5: Run the full existing test suite to check nothing broke**

Run: `.venv\Scripts\python.exe -m pytest tests/ -v`
Expected: all tests pass (no regressions from the `by_position.items()` rename).

- [ ] **Step 6: Re-run the script against real data as a smoke test**

Run: `.venv\Scripts\python.exe scripts/export_schwab_trades.py`
Expected: same account/trade counts as before, and the output CSVs in
`workspace/schwab_exports/` now have an `external_id` column with a
numeric-looking value in every row.

- [ ] **Step 7: Commit**

```bash
git add scripts/export_schwab_trades.py tests/test_export_schwab_trades.py
git commit -m "Carry Schwab positionId through as external_id for API upserts"
```

---

### Task 6: IAN — `scripts/sync_to_heisenberg.py`

**Files:**
- Create: `C:\GitHub\AI-Coding\IAN\scripts\sync_to_heisenberg.py`
- Test: `C:\GitHub\AI-Coding\IAN\tests\test_sync_to_heisenberg.py` (new)
- Modify: `C:\GitHub\AI-Coding\IAN\.env.example`

**Interfaces:**
- Consumes: `fetch_current_positions(client, account_hash) -> list[tuple[str, float]]`
  from `scripts/export_current_positions.py`; `reconstruct_trades(transactions) -> tuple[list[dict], int]`
  from `scripts/export_schwab_trades.py` (Task 5's version, rows include `external_id`);
  `get_client()` from `src.orchestrator.schwab_auth`.
- Produces: `post_json(base_url, token, path, payload) -> dict`,
  `fetch_today_total_value(client, account_hash) -> float`,
  `sync_account(client, base_url, token, account_hash, site_account_id) -> dict`
  (a per-account summary dict) — used by this task's own `main()`, not by
  any other task.

- [ ] **Step 1: Write the failing tests**

```python
import httpx
import pytest

from scripts.sync_to_heisenberg import fetch_today_total_value, post_json


class _FakeAccountResponse:
    def __init__(self, cash, positions):
        self._payload = {
            "securitiesAccount": {
                "currentBalances": {"cashBalance": cash},
                "positions": positions,
            }
        }

    def raise_for_status(self):
        pass

    def json(self):
        return self._payload


class _FakeClient:
    def __init__(self, cash, positions):
        self._cash = cash
        self._positions = positions

    def get_account(self, account_hash, fields=None):
        return _FakeAccountResponse(self._cash, self._positions)


def test_fetch_today_total_value_sums_cash_and_market_values():
    client = _FakeClient(
        cash=500.0,
        positions=[
            {"instrument": {"symbol": "AAA"}, "marketValue": 100.0},
            {"instrument": {"symbol": "BBB"}, "marketValue": 250.0},
        ],
    )

    total = fetch_today_total_value(client, "hash123")

    assert total == 850.0


def test_fetch_today_total_value_handles_no_positions():
    client = _FakeClient(cash=1000.0, positions=[])

    total = fetch_today_total_value(client, "hash123")

    assert total == 1000.0


def test_post_json_sends_bearer_token_and_returns_parsed_body(monkeypatch):
    captured = {}

    def fake_post(url, json, headers, timeout):
        captured["url"] = url
        captured["json"] = json
        captured["headers"] = headers
        return httpx.Response(200, json={"imported": 1, "errors": []}, request=httpx.Request("POST", url))

    monkeypatch.setattr(httpx, "post", fake_post)

    result = post_json("https://example.test", "secret-token", "api/import_snapshots.php",
                        {"account_id": 3, "rows": [{"date": "2026-08-01", "total_value": 100}]})

    assert result == {"imported": 1, "errors": []}
    assert captured["url"] == "https://example.test/api/import_snapshots.php"
    assert captured["headers"]["Authorization"] == "Bearer secret-token"
    assert captured["json"]["account_id"] == 3


def test_post_json_raises_on_error_status(monkeypatch):
    def fake_post(url, json, headers, timeout):
        return httpx.Response(401, json={"error": "unauthorized"}, request=httpx.Request("POST", url))

    monkeypatch.setattr(httpx, "post", fake_post)

    with pytest.raises(httpx.HTTPStatusError):
        post_json("https://example.test", "bad-token", "api/import_snapshots.php", {"account_id": 3, "rows": []})
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `.venv\Scripts\python.exe -m pytest tests/test_sync_to_heisenberg.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'scripts.sync_to_heisenberg'`.

- [ ] **Step 3: Implement `scripts/sync_to_heisenberg.py`**

```python
"""Pushes today's Schwab data directly into HeisenbergStudios via its
Schwab Sync API, replacing the manual "export CSV -> upload in browser"
workflow for ongoing (non-backfill) updates.

For each account mapped in conf/heisenberg_accounts.json:
- Market values: reuses export_current_positions.fetch_current_positions(),
  POSTed to api/import_market_values.php (full replace per account, same
  as that endpoint's CSV-importer twin).
- Today's account value: computed directly from a single get_account call
  (cashBalance + sum of live marketValue) -- no historical price-history
  lookups needed, unlike the one-time backfill in export_daily_snapshots.py.
  POSTed to api/import_snapshots.php (upsert on date, safe to re-run
  same-day).
- Trades: reuses export_schwab_trades.reconstruct_trades(), whose rows now
  carry Schwab's positionId as external_id, POSTed to api/import_trades.php
  (upsert by external_id -- a still-open position gets its close fields
  filled in automatically once it closes on a later run).

Never calls place_order/replace_order/cancel_order -- read-only throughout,
same as every other tool in schwab_tools.py's static (non-order) surface.

Usage:
    python scripts/sync_to_heisenberg.py

Requires HEISENBERG_API_BASE and HEISENBERG_API_TOKEN in .env, and
conf/heisenberg_accounts.json mapping each Schwab account's 1-based index
(same order as get_account_numbers(), matching the existing export
scripts' numbering) to its HeisenbergStudios brokerage_accounts.id.
"""
import json
import os
import sys
from datetime import date, timedelta
from pathlib import Path

import httpx
from schwab.client import Client

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from scripts.export_current_positions import fetch_current_positions  # noqa: E402
from scripts.export_schwab_trades import LOOKBACK_DAYS, reconstruct_trades  # noqa: E402
from src.orchestrator.schwab_auth import get_client  # noqa: E402


def load_account_map() -> dict[str, int]:
    map_path = Path(__file__).resolve().parents[1] / "conf" / "heisenberg_accounts.json"
    with map_path.open() as f:
        return json.load(f)


def post_json(base_url: str, token: str, path: str, payload: dict) -> dict:
    url = f"{base_url.rstrip('/')}/{path}"
    resp = httpx.post(url, json=payload, headers={"Authorization": f"Bearer {token}"}, timeout=30)
    resp.raise_for_status()
    return resp.json()


def fetch_today_total_value(client, account_hash: str) -> float:
    account_resp = client.get_account(account_hash, fields=Client.Account.Fields.POSITIONS)
    account_resp.raise_for_status()
    account = account_resp.json().get("securitiesAccount", {})
    cash = account["currentBalances"]["cashBalance"]
    market_value_total = sum(p.get("marketValue", 0.0) for p in account.get("positions", []))
    return round(cash + market_value_total, 2)


def sync_account(client, base_url: str, token: str, account_hash: str, site_account_id: int) -> dict:
    summary = {}

    positions = fetch_current_positions(client, account_hash)
    market_value_rows = [{"ticker": symbol, "market_value": value} for symbol, value in positions]
    summary["market_values"] = post_json(
        base_url, token, "api/import_market_values.php",
        {"account_id": site_account_id, "rows": market_value_rows},
    )

    today_value = fetch_today_total_value(client, account_hash)
    summary["snapshot"] = post_json(
        base_url, token, "api/import_snapshots.php",
        {"account_id": site_account_id, "rows": [{"date": date.today().isoformat(), "total_value": today_value}]},
    )

    end = date.today()
    start = end - timedelta(days=LOOKBACK_DAYS)
    txn_resp = client.get_transactions(account_hash, start_date=start, end_date=end)
    txn_resp.raise_for_status()
    trade_rows, _skipped = reconstruct_trades(txn_resp.json())
    summary["trades"] = post_json(
        base_url, token, "api/import_trades.php",
        {"account_id": site_account_id, "rows": trade_rows},
    )

    return summary


def main() -> None:
    base_url = os.environ.get("HEISENBERG_API_BASE", "")
    token = os.environ.get("HEISENBERG_API_TOKEN", "")
    if not base_url or not token:
        print("HEISENBERG_API_BASE and HEISENBERG_API_TOKEN must be set in .env")
        sys.exit(1)

    account_map = load_account_map()

    client = get_client()
    accounts_resp = client.get_account_numbers()
    accounts_resp.raise_for_status()
    accounts = accounts_resp.json()

    for i, account in enumerate(accounts, start=1):
        site_account_id = account_map.get(str(i))
        if site_account_id is None:
            print(f"Account {i}: no mapping in conf/heisenberg_accounts.json, skipping")
            continue

        summary = sync_account(client, base_url, token, account["hashValue"], site_account_id)
        for kind, result in summary.items():
            errors = result.get("errors", [])
            print(f"Account {i} -> site account {site_account_id} [{kind}]: "
                  f"{result.get('imported', 0)} imported"
                  + (f", {len(errors)} error(s): {errors}" if errors else ""))


if __name__ == "__main__":
    main()
```

Note: this reads `.env` values via `os.environ`, which requires whatever
already loads IAN's `.env` into the process environment (check how
`src/orchestrator/schwab_auth.py` or `scripts/export_daily_snapshots.py`
gets its own env vars — if IAN uses `python-dotenv` or a similar loader
elsewhere, add the same loading call at the top of `main()` here; if the
existing scripts rely on the environment already being loaded by the
caller, match that same assumption rather than inventing a new pattern.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `.venv\Scripts\python.exe -m pytest tests/test_sync_to_heisenberg.py -v`
Expected: PASS (4 tests).

- [ ] **Step 5: Add the new env vars to `.env.example`**

Append this new section at the very end of the file (don't insert it
mid-file — simplest and avoids disturbing any existing section):

```
# --- HeisenbergStudios Schwab Sync API ---
# Base URL of the live site and the shared token from its .env
# (API_SYNC_TOKEN there must match this value exactly).
HEISENBERG_API_BASE=https://www.heisenbergstudios.com
HEISENBERG_API_TOKEN=
```

- [ ] **Step 6: Create the local (git-ignored) account mapping**

`conf/` is already fully git-ignored in this repo. Create
`conf/heisenberg_accounts.json` by hand, mapping each Schwab account's
1-based export index to its real `brokerage_accounts.id` on the site
(look these up via the site's admin account list, or query
`SELECT id, brokerage_name, account_label FROM brokerage_accounts;`
using the same temp-credentials-file pattern used in earlier tasks):

```json
{
  "1": 0,
  "2": 0,
  "3": 0
}
```

Replace the `0` placeholders with the real site account ids before
running the script for real (Step 7 needs this filled in correctly, or
data will be pushed into the wrong account).

- [ ] **Step 7: Real end-to-end smoke test against your live Schwab accounts**

Fill in `.env` locally (`HEISENBERG_API_TOKEN` matching production's
`API_SYNC_TOKEN`), fill in `conf/heisenberg_accounts.json` with real ids
mapped to a throwaway or a real account you're prepared to overwrite, then:

```bash
.venv\Scripts\python.exe scripts/sync_to_heisenberg.py
```

Expected: one summary line per account per data type, all showing
`imported > 0` and no errors. Then check the live site — the account's
allocation rings and balance chart should reflect today's real data.

- [ ] **Step 8: Commit**

```bash
git add scripts/sync_to_heisenberg.py tests/test_sync_to_heisenberg.py .env.example
git commit -m "Add scheduled Schwab-to-HeisenbergStudios sync script"
```

(`conf/heisenberg_accounts.json` stays uncommitted — it's git-ignored by
the existing `conf/` rule in `.gitignore`.)

---

### Task 7: Windows Task Scheduler registration

**Files:**
- None (system configuration, not a repo file)

**Interfaces:**
- Consumes: `scripts/sync_to_heisenberg.py` (Task 6).

- [ ] **Step 1: Register the scheduled task**

```powershell
$action = New-ScheduledTaskAction -Execute "C:\GitHub\AI-Coding\IAN\.venv\Scripts\python.exe" `
    -Argument "scripts\sync_to_heisenberg.py" -WorkingDirectory "C:\GitHub\AI-Coding\IAN"
$trigger = New-ScheduledTaskTrigger -Daily -At 6am
Register-ScheduledTask -TaskName "IAN-HeisenbergSync" -Action $action -Trigger $trigger `
    -Description "Pushes today's Schwab trades/snapshot/positions to HeisenbergStudios"
```

- [ ] **Step 2: Verify registration**

```powershell
Get-ScheduledTask -TaskName "IAN-HeisenbergSync"
```

Expected: shows the task, `State: Ready`.

- [ ] **Step 3: Run it once manually to confirm it works outside an interactive session**

```powershell
Start-ScheduledTask -TaskName "IAN-HeisenbergSync"
# Wait a few seconds, then check the result:
Get-ScheduledTaskInfo -TaskName "IAN-HeisenbergSync"
```

Expected: `LastTaskResult: 0` (success). If non-zero, the task likely
can't see the same environment as an interactive shell (e.g., `.env`
wasn't loaded) — investigate via Task Scheduler's history/Event Viewer
before considering this task done.

---

## Final Verification

After all 7 tasks: confirm the CSV importer pages still work unchanged
(spot-check one), confirm the two allocation rings and balance chart on
`sections/metrics_tradelog.php` show today's live-synced data, and confirm
`Get-ScheduledTask -TaskName "IAN-HeisenbergSync"` shows `Ready` for
tomorrow's automatic run.
