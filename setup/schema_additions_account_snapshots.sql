-- ============================================================
-- ADDITIONS to site_portal schema
-- Adds daily account-value snapshots (cash + mark-to-market value of
-- held positions, one row per account per day), imported via a CSV
-- generated outside this site (see IAN's scripts/export_daily_snapshots.py).
-- Lets the Account Balance chart show true daily equity instead of only
-- moving at trade-close events -- see
-- docs/superpowers/specs/2026-08-13-daily-account-snapshots-design.md.
-- Run this AFTER schema_additions_trade_log.sql has been imported.
-- ============================================================

CREATE TABLE account_value_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    snapshot_date DATE NOT NULL,
    total_value DECIMAL(14,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_account_date (account_id, snapshot_date),
    FOREIGN KEY (account_id) REFERENCES brokerage_accounts(id) ON DELETE CASCADE
);
