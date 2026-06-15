-- Make card link_url values root-relative so they resolve correctly
-- no matter which page they are clicked from. Run once.
USE johnsonk_heisenbergstudios;
UPDATE cards SET link_url = CONCAT('/', link_url) WHERE link_url NOT LIKE '/%';
