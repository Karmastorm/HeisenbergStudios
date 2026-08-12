-- ============================================================
-- ADDITIONS to site_portal schema
-- The 4-theme system (default/dark/forest/royal) was replaced with a
-- neutral Light/Dark pair as part of the professional monochrome
-- redesign. Renames the "default" theme key to "light" and normalizes
-- any user still on a removed theme (forest/royal) back to light.
-- ============================================================

ALTER TABLE users MODIFY theme VARCHAR(20) NOT NULL DEFAULT 'light';
UPDATE users SET theme = 'light' WHERE theme NOT IN ('light', 'dark');
