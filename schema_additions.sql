-- ============================================================
-- ADDITIONS to site_portal schema
-- Run this AFTER the original schema.sql has been imported.
-- ============================================================
USE site_portal;

-- ------------------------------------------------------------
-- Protected file folders
-- Each row maps a physical folder (relative to /files/) to a
-- required access level. Folders are served only through
-- files/download.php, which checks this table.
-- ------------------------------------------------------------
CREATE TABLE file_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folder_path VARCHAR(255) NOT NULL UNIQUE,   -- e.g. 'everquest/macroquest'
    display_name VARCHAR(150) NOT NULL,
    min_access_level INT NOT NULL DEFAULT 1,
    FOREIGN KEY (min_access_level) REFERENCES access_levels(id)
);

-- Seed folders matching the existing menu structure
INSERT INTO file_folders (folder_path, display_name, min_access_level) VALUES
    ('everquest/macros', 'EQ Macros', 1),
    ('everquest/macroquest', 'Macroquest Files', 2),
    ('everquest/e3', 'E3 Configs', 3),
    ('python/webscrapper', 'Web Scrapper Files', 3),
    ('python/marketresearch', 'Market Research Data', 2),
    ('python/fileautomation', 'File Automation Scripts', 4),
    ('documents/excel', 'Excel Documents', 1),
    ('documents/word', 'Word Documents', 1),
    ('documents/readmes', 'ReadMe Files', 1);
