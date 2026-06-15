-- ============================================================
-- Database schema for the access-controlled site
-- ============================================================
CREATE DATABASE IF NOT EXISTS site_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE site_portal;

-- ------------------------------------------------------------
-- Access levels (1 = lowest, 5 = highest)
-- ------------------------------------------------------------
CREATE TABLE access_levels (
    id INT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO access_levels (id, name) VALUES
    (1, 'read_only'),
    (2, 'restricted'),
    (3, 'editor'),
    (4, 'web_dev'),
    (5, 'webmaster');

-- ------------------------------------------------------------
-- Users
-- ------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    access_level INT NOT NULL DEFAULT 1,
    theme VARCHAR(20) NOT NULL DEFAULT 'default',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (access_level) REFERENCES access_levels(id)
);

-- ------------------------------------------------------------
-- Menu categories (top-level dropdown: Everquest, Python, Documents)
-- ------------------------------------------------------------
CREATE TABLE menu_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    min_access_level INT NOT NULL DEFAULT 1,
    FOREIGN KEY (min_access_level) REFERENCES access_levels(id)
);

-- ------------------------------------------------------------
-- Menu items (sub-items: Macros, Macroquest, E3, etc.)
-- ------------------------------------------------------------
CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    min_access_level INT NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (min_access_level) REFERENCES access_levels(id)
);

-- ------------------------------------------------------------
-- Cards (content cards shown on main body, linked to a section/page)
-- ------------------------------------------------------------
CREATE TABLE cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NULL,
    title VARCHAR(150) NOT NULL,
    synopsis TEXT NOT NULL,
    link_url VARCHAR(255) NOT NULL,
    image_url VARCHAR(255) NULL,
    min_access_level INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL,
    FOREIGN KEY (min_access_level) REFERENCES access_levels(id)
);

-- ------------------------------------------------------------
-- Seed data: menu structure
-- ------------------------------------------------------------
INSERT INTO menu_categories (name, sort_order, min_access_level) VALUES
    ('Everquest', 1, 1),
    ('Python', 2, 2),
    ('Documents', 3, 1);

INSERT INTO menu_items (category_id, name, slug, sort_order, min_access_level) VALUES
    (1, 'Macros', 'eq-macros', 1, 1),
    (1, 'Macroquest', 'eq-macroquest', 2, 2),
    (1, 'E3', 'eq-e3', 3, 3),
    (2, 'Web Scrapper', 'py-webscrapper', 1, 3),
    (2, 'Market Research', 'py-marketresearch', 2, 2),
    (2, 'File Automation', 'py-fileautomation', 3, 4),
    (3, 'Excel Docs', 'doc-excel', 1, 1),
    (3, 'Word Docs', 'doc-word', 2, 1),
    (3, 'ReadMes', 'doc-readmes', 3, 1);

-- ------------------------------------------------------------
-- Seed data: sample cards
-- ------------------------------------------------------------
INSERT INTO cards (menu_item_id, title, synopsis, link_url, min_access_level, sort_order) VALUES
    (1, 'EQ Macro Basics', 'A quick-start guide to writing and running simple in-game macros for everyday tasks.', 'sections/eq_macros.php', 1, 1),
    (3, 'E3 Bot Configuration', 'Step-by-step setup notes for configuring E3 bot profiles for group and raid scenarios.', 'sections/eq_e3.php', 3, 1),
    (4, 'Building a Price Scraper', 'Walkthrough of a Python scrapper that pulls item prices from in-game bazaar listings.', 'sections/py_webscrapper.php', 3, 1),
    (8, 'Word Templates Library', 'Collection of formatted Word document templates for reports and guides.', 'sections/doc_word.php', 1, 1),
    (7, 'Excel Tracker Sheets', 'Spreadsheets for tracking loot, raid attendance, and item progression.', 'sections/doc_excel.php', 1, 2),
    (6, 'Automated File Sorter', 'A Python utility that organizes downloaded files into folders by type and date.', 'sections/py_fileautomation.php', 4, 1),
    (5, 'Market Trend Reports', 'Weekly summaries of market trends gathered from scraped pricing data.', 'sections/py_marketresearch.php', 2, 1),
    (9, 'Project ReadMe Index', 'Central index of ReadMe files for all active scripts and tools.', 'sections/doc_readmes.php', 1, 3);

-- ------------------------------------------------------------
-- Sample users (passwords below are placeholders -- generate real
-- hashes with PHP password_hash() before inserting in production)
-- ------------------------------------------------------------
-- INSERT INTO users (username, password_hash, email, access_level) VALUES
--     ('admin', '$2y$10$REPLACE_WITH_REAL_HASH', 'admin@example.com', 5);
