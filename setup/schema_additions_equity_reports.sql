-- ============================================================
-- ADDITIONS to site_portal schema
-- Adds a card for the new Equity Sector Reports page (investments/
-- equity_reports.php) alongside the existing Research Reports file
-- browser card, both under the Analysis > Research Reports menu item.
-- Once this is applied, that nav item leads to a two-card section
-- grid (via /index.php?section=analysis-research) instead of jumping
-- straight into the file browser.
-- Run this AFTER schema_additions_financial_rebrand.sql has been
-- imported.
-- ============================================================

INSERT INTO cards (menu_item_id, title, synopsis, link_url, min_access_level, sort_order)
SELECT id, 'Equity Sector Reports', '132 equity research reports, grouped by sector with a sector jump menu.', '/investments/equity_reports.php', 1, 2
FROM menu_items WHERE slug = 'analysis-research';
