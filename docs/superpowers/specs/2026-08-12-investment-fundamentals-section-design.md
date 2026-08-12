# Investment Fundamentals Section — Design

## Summary

Add a new, sign-in-gated section of the site that lets logged-in users browse
the per-ticker fundamental-analysis reports that already live in
`investments/investment_fundamentals/*.html`. The section has its own
left-hand ticker index (built by scanning the folder, not database-driven)
and is reachable from the existing "Documents" nav dropdown and the homepage
card grid, matching how Excel Docs / Word Docs / ReadMes work today.

## Background

The site (`c:\GitHub\AI-Coding\HeisenbergStudios`) is a PHP + MySQL portal:

- `includes/auth.php` provides `require_access($level)`, gating pages by a
  numeric access level (1 = read_only … 5 = webmaster).
- `includes/nav.php` / `nav_data_only.php` render the top nav and footer
  sitemap from the `menu_categories` / `menu_items` DB tables, filtered by
  the visitor's access level. The existing "Documents" category (id 3) has
  items `doc-excel`, `doc-word`, `doc-readmes`.
- `index.php` (homepage) shows a card grid from the `cards` table; each card
  links to a dedicated page (e.g. `sections/doc_excel.php`). Cards are
  visible to everyone (including logged-out visitors) except level-5 cards;
  clicking through still enforces that page's own `require_access()`.
- `files/` holds downloadable assets and is fully blocked from direct web
  access via `files/.htaccess` (`Require all denied`); all access goes
  through `files/browse.php` / `files/download.php`, which re-validate
  access level and reject path traversal before streaming a file.

Seven ticker report files already exist in
`investments/investment_fundamentals/`, named like:

```
20260812 - INTC Intel Corporation - fundamental_analysis.html
```

Each is a full standalone HTML document (own `<head>`, embedded CSS with
light/dark theme variables, embedded JS for tabs) — not styled to match the
site's theme, and not wrapped in any PHP access check. Served as static
files, they would currently be reachable by anyone who guesses the URL,
bypassing login entirely.

## Goals

- Gate the reports behind sign-in, consistent with how `files/` is
  protected (deny direct access at the web-server level; serve only through
  a PHP script that re-checks access).
- Provide a left-side index of ticker symbols, generated from whatever
  files currently exist in the folder (no hardcoded list, no DB table) —
  scoped only to this new section.
- Make the section reachable via the "Documents" nav dropdown and, like the
  other Documents entries, also as a homepage card.
- Leave the existing report files untouched.

## Non-goals

- Restyling the reports to match the site theme.
- Building a general-purpose "documents from a folder" system — this is a
  one-off, static-per-section index, not a reusable component.
- Syncing the site's theme selector into the embedded report (the report
  keeps its own independent light/dark behavior via `prefers-color-scheme`).

## Design

### 1. File protection

Add `investments/investment_fundamentals/.htaccess`:

```
Require all denied
```

This blocks direct browser access to the raw report files. The controller
(below) lives one directory up (`investments/index.php`), so it is not
covered by this rule and reads the files straight off disk with `readfile()`
— which is a filesystem operation, not an HTTP request, so the deny rule
doesn't apply to it.

### 2. Controller — `investments/index.php`

Single new PHP file, responsible for both the sidebar page and streaming the
selected report:

- `require_once __DIR__ . '/../includes/auth.php'; start_secure_session();
  require_access('restricted');` — gates the whole page at level 2+.
- Builds the ticker list by globbing `investment_fundamentals/*.html` and
  parsing each filename with a regex matching the existing naming
  convention: `^\d{8} - ([A-Z.]+) (.+?) - fundamental_analysis\.html$`,
  capturing ticker and company name. Sorted alphabetically by ticker.
- Normal mode (no `raw` param): renders the standard site chrome
  (`includes/header.php`, `includes/nav.php`, `includes/footer.php`) plus a
  two-column `<main>`:
  - `<aside class="ticker-index">` — list of ticker links
    (`?ticker=INTC`), each showing the ticker and company name.
  - `<section class="ticker-viewer">` — if a ticker is selected, an
    `<iframe>` pointing at `?ticker=INTC&raw=1`; otherwise a prompt to pick
    one from the list.
- Raw mode (`raw=1` present): re-checks access, looks up the requested
  ticker **against the scanned list** (never trusts the query string as a
  filename/path — this is what prevents path traversal), and on a match
  sends `Content-Type: text/html; charset=UTF-8` plus
  `X-Frame-Options: SAMEORIGIN`, then `readfile()`s that report and exits.
  No match → `404`.

This mirrors `files/download.php`'s validate-then-stream pattern, adapted
to render inline (iframe) rather than force a download.

### 3. Nav & homepage integration

New migration file `setup/schema_additions_investment_fundamentals.sql`
(applied after `schema.sql` and `schema_additions.sql`, same convention):

```sql
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
```

- `menu_items` row makes "Investment Fundamentals" appear in the Documents
  dropdown, visible to users with access level ≥ 2 (restricted).
- `cards` row makes it appear on the homepage grid, same as Excel/Word/
  ReadMes. Per the existing visibility rule, the card itself is visible to
  everyone (including logged-out visitors) since it's below the level-5
  admin-only threshold; clicking through still hits `require_access()` in
  `investments/index.php` and redirects to login if needed.

### 4. Styling

Append scoped rules to `assets/css/main.css` (not a new stylesheet — matches
the existing convention of page-specific classes like `.login-card` living
in the one shared file):

- `.investments-layout` — flex/grid two-column container.
- `.ticker-index` — sidebar list styling (active-ticker highlight, ticker +
  company name).
- `.ticker-viewer` — main pane, full-height `<iframe>` with a border,
  using existing `--color-*` theme variables.

## Access control summary

| Path | Check |
|---|---|
| `investments/index.php` (normal) | `require_access('restricted')` → redirects to login if not met |
| `investments/index.php?raw=1` | Same check, re-run independently, plus scanned-list validation before streaming |
| `investments/investment_fundamentals/*.html` direct URL | Blocked at the web-server level (`Require all denied`) |

## Testing / verification

Manual, since this is a small PHP site with no test suite:

1. Log out (or use a level-1 read-only account) → confirm visiting
   `investments/index.php` redirects to `login.php?denied=1`.
2. Log in as level-2+ → confirm the sidebar lists all seven current tickers,
   clicking one loads that report in the iframe, and the active ticker is
   highlighted.
3. Attempt to load a report file's URL directly
   (`investments/investment_fundamentals/<file>.html`) → confirm it's
   blocked (403 or connection refused, depending on server config).
4. Confirm "Investment Fundamentals" appears in the Documents nav dropdown
   and links correctly.
5. Confirm the homepage card appears and links to `investments/index.php`.
6. Add/remove a report file in the folder → confirm the sidebar list
   updates without any code change.
