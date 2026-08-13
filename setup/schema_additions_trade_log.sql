-- ============================================================
-- ADDITIONS to site_portal schema
-- Adds the Trade Log data model: brokerage_accounts (a user can have
-- several, across several brokerages) and trades (owned implicitly via
-- their account, not a redundant user_id). Also corrects the Trade Log
-- menu item / card to level 1, since ownership -- not access tier -- is
-- the security boundary for this feature (see design spec).
-- Run this AFTER schema.sql, schema_additions.sql,
-- schema_additions_investment_fundamentals.sql, and
-- schema_additions_financial_rebrand.sql have been imported.
-- ============================================================

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

UPDATE menu_items SET min_access_level = 1 WHERE slug = 'metrics-tradelog';
UPDATE cards SET min_access_level = 1 WHERE link_url = '/sections/metrics_tradelog.php';
