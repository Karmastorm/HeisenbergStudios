-- ============================================================
-- ADDITIONS to site_portal schema
-- Adds current per-ticker market value snapshots (one row per account
-- per currently-held ticker), imported via a CSV generated outside this
-- site (see IAN's scripts/export_current_positions.py). Feeds a second
-- Allocation ring chart weighted by real market value instead of cost
-- basis. Each import replaces all rows for that account (delete + insert)
-- so closed-out positions drop off automatically -- this table always
-- represents "as of the last import", not a history.
-- Run this AFTER schema_additions_trade_log.sql has been imported.
-- ============================================================

CREATE TABLE position_market_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    ticker VARCHAR(20) NOT NULL,
    market_value DECIMAL(14,2) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_account_ticker (account_id, ticker),
    FOREIGN KEY (account_id) REFERENCES brokerage_accounts(id) ON DELETE CASCADE
);
