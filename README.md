# Heisenberg Studios — Site

PHP/MySQL site with login-based access control (5 levels), themed UI, content
cards, and access-gated file folders.

## Access levels
1. read_only  2. restricted  3. editor  4. web_dev  5. webmaster

## Structure

```
index.php              Home — card grid (filtered by access level)
login.php / logout.php Auth
theme-save.php         Persists chosen theme for logged-in users

includes/
  config.php           DB credentials + PDO connection
  auth.php             Sessions, login, require_access()
  header.php           Banner: login box / user box, theme switcher
  nav.php              Dropdown menu (DB-driven, access-filtered)
  nav_data_only.php    Builds $menu for footer without rendering nav
  footer.php           Sitemap + copyright

sections/              One PHP page per content card
  eq_macros.php, eq_e3.php, py_webscrapper.php, py_marketresearch.php,
  py_fileautomation.php, doc_excel.php, doc_word.php, doc_readmes.php

files/
  .htaccess            Denies direct web access to everything here
  browse.php           Lists a folder's files (access-checked)
  download.php         Streams a file (access-checked, no path traversal)
  everquest/ python/ documents/   FTP-uploaded files live in these subfolders

admin/
  cards.php            Add/edit/delete content cards (editor+)
  folders.php          Set per-folder access levels (webmaster)

setup/                 NOT deployed — local helpers only
  schema.sql           Base schema + seed data
  schema_additions.sql file_folders table
  fix_card_links.sql   One-time fix to make card links root-relative
  make_hash.php        CLI: generate a password hash for new users
```

## One-time setup tasks (run in phpMyAdmin)
1. Import `setup/schema.sql` then `setup/schema_additions.sql` (if not already).
2. Run `setup/fix_card_links.sql` once so card links resolve correctly
   from any page (fixes the /sections/sections/ doubled-path issue).

## Deployment
`.github/workflows/deploy.yml` deploys via SFTP on push to `main`.
It excludes `setup/`, `.github/`, server-state files, and secrets.

Required GitHub secrets: `SFTP_HOST`, `SFTP_USER`, `SFTP_PASSWORD`.

## Notes
- Do NOT commit `error_log`, `.ftpquota`, `.ftp-deploy-sync-state.json`,
  or `*.phpupgrader.*` — these are server-generated (see `.gitignore`).
- `config.php` holds live DB credentials. If they are ever exposed, rotate
  the DB password in Bluehost and update config.php.
