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
