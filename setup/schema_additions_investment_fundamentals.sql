-- ============================================================
-- ADDITIONS to site_portal schema
-- Adds the "Investment Fundamentals" entry to the Documents nav
-- dropdown and a matching homepage card, pointing at the new
-- investments/index.php ticker report browser.
-- Run this AFTER schema.sql and schema_additions.sql have been imported.
-- Uses whichever database the connection is already pointed at (the schema
-- was originally scaffolded as "site_portal" but the live database has
-- since been provisioned under the host's own naming, e.g. via cPanel).
-- ============================================================

INSERT INTO menu_items (category_id, name, slug, sort_order, min_access_level)
VALUES (3, 'Investment Fundamentals', 'investment-fundamentals', 4, 2);

INSERT INTO cards (menu_item_id, title, synopsis, link_url, min_access_level, sort_order)
VALUES (
    LAST_INSERT_ID(),
    'Investment Fundamentals',
    'Fundamental analysis reports for tracked tickers.',
    'investments/index.php',
    2,
    1
);
