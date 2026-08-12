-- ============================================================
-- ADDITIONS to site_portal schema
-- Rebrands the site's nav from Everquest / Python / Documents to
-- Analysis / Portfolio / Metrics, retiring the old gaming/scripting
-- content and folding the existing Investment Fundamentals section
-- into Analysis.
-- Run this AFTER schema.sql, schema_additions.sql, and
-- schema_additions_investment_fundamentals.sql have been imported.
-- ============================================================

-- ------------------------------------------------------------
-- Rename the three top-level categories
-- ------------------------------------------------------------
UPDATE menu_categories SET name = 'Analysis'  WHERE name = 'Everquest';
UPDATE menu_categories SET name = 'Portfolio' WHERE name = 'Python';
UPDATE menu_categories SET name = 'Metrics'   WHERE name = 'Documents';

-- ------------------------------------------------------------
-- Move the existing Investment Fundamentals item into Analysis
-- ------------------------------------------------------------
UPDATE menu_items mi
JOIN menu_categories c ON c.name = 'Analysis'
SET mi.category_id = c.id, mi.sort_order = 2
WHERE mi.slug = 'investment-fundamentals';

-- ------------------------------------------------------------
-- Remove old EQ/Python/Documents homepage cards, menu items, and
-- protected file folders
-- ------------------------------------------------------------
-- Matched with LIKE (not IN) because fix_card_links.sql root-relativized
-- these links, so the live value has a leading "/" the original seed didn't.
DELETE FROM cards WHERE link_url LIKE '%eq_macros.php' OR link_url LIKE '%eq_e3.php'
    OR link_url LIKE '%py_webscrapper.php' OR link_url LIKE '%doc_word.php'
    OR link_url LIKE '%doc_excel.php' OR link_url LIKE '%py_fileautomation.php'
    OR link_url LIKE '%py_marketresearch.php' OR link_url LIKE '%doc_readmes.php';

DELETE FROM menu_items WHERE slug IN (
    'eq-macros', 'eq-macroquest', 'eq-e3',
    'py-webscrapper', 'py-marketresearch', 'py-fileautomation',
    'doc-excel', 'doc-word', 'doc-readmes'
);

DELETE FROM file_folders WHERE folder_path IN (
    'everquest/macros', 'everquest/macroquest', 'everquest/e3',
    'python/webscrapper', 'python/marketresearch', 'python/fileautomation',
    'documents/excel', 'documents/word', 'documents/readmes'
);

-- ------------------------------------------------------------
-- New protected folder backing the Analysis > Research Reports
-- file browser (upload research docs here via FTP)
-- ------------------------------------------------------------
INSERT INTO file_folders (folder_path, display_name, min_access_level) VALUES
    ('analysis/research', 'Research Documents', 1);

-- ------------------------------------------------------------
-- New menu items
-- ------------------------------------------------------------
INSERT INTO menu_items (category_id, name, slug, sort_order, min_access_level)
SELECT id, 'Research Reports', 'analysis-research', 1, 1 FROM menu_categories WHERE name = 'Analysis';

INSERT INTO menu_items (category_id, name, slug, sort_order, min_access_level)
SELECT id, 'Allocations', 'portfolio-allocations', 1, 2 FROM menu_categories WHERE name = 'Portfolio';

INSERT INTO menu_items (category_id, name, slug, sort_order, min_access_level)
SELECT id, 'Strategies', 'portfolio-strategies', 2, 2 FROM menu_categories WHERE name = 'Portfolio';

INSERT INTO menu_items (category_id, name, slug, sort_order, min_access_level)
SELECT id, 'Trade Log', 'metrics-tradelog', 1, 2 FROM menu_categories WHERE name = 'Metrics';

INSERT INTO menu_items (category_id, name, slug, sort_order, min_access_level)
SELECT id, 'Asset Allocation', 'metrics-assetallocation', 2, 2 FROM menu_categories WHERE name = 'Metrics';

-- ------------------------------------------------------------
-- New homepage cards
-- ------------------------------------------------------------
INSERT INTO cards (menu_item_id, title, synopsis, link_url, min_access_level, sort_order)
SELECT id, 'Research Reports', 'Browse uploaded research documents and reference materials.', '/files/browse.php?folder=analysis/research', 1, 1
FROM menu_items WHERE slug = 'analysis-research';

INSERT INTO cards (menu_item_id, title, synopsis, link_url, min_access_level, sort_order)
SELECT id, 'Portfolio Allocations', 'Current allocation breakdowns across asset classes and holdings.', '/sections/portfolio_allocations.php', 2, 1
FROM menu_items WHERE slug = 'portfolio-allocations';

INSERT INTO cards (menu_item_id, title, synopsis, link_url, min_access_level, sort_order)
SELECT id, 'Portfolio Strategies', 'Notes and rationale behind current portfolio strategy.', '/sections/portfolio_strategies.php', 2, 2
FROM menu_items WHERE slug = 'portfolio-strategies';

INSERT INTO cards (menu_item_id, title, synopsis, link_url, min_access_level, sort_order)
SELECT id, 'Trade Log', 'Quantitative results of trades, pulled from uploaded spreadsheets and connected-account APIs. No balances or personal account details.', '/sections/metrics_tradelog.php', 2, 1
FROM menu_items WHERE slug = 'metrics-tradelog';

INSERT INTO cards (menu_item_id, title, synopsis, link_url, min_access_level, sort_order)
SELECT id, 'Asset Allocation Metrics', 'Quantitative asset allocation breakdowns. No balances or personal account details.', '/sections/metrics_assetallocation.php', 2, 2
FROM menu_items WHERE slug = 'metrics-assetallocation';
