# Professional Monochrome Redesign — Design

## Summary

Re-skin the site (currently a colorful 4-theme system: Default/Dark/Forest/
Royal) into a shadcn/Aceternity-inspired professional look: a neutral
black/white/grey palette, Bebas Neue + Nunito typography, and subtle
interaction polish. Two carve-outs keep color: the access-level badges on
homepage cards (unchanged), and page `<h1>` titles, which take a brand green
sampled from the logo. The 4-option theme switcher is simplified to
Light/Dark only.

## Background

- Reference sites: https://ui.shadcn.com/ (restrained, neutral, subtle
  borders/shadows, accessible focus states) and https://ui.aceternity.com/
  (animated flourishes) — the user wants shadcn's restraint as the base,
  with no dramatic Aceternity-style animation.
- Current system: `assets/css/themes.css` defines 4 selectable palettes
  (`body[data-theme="default|dark|forest|royal"]`), each a full set of CSS
  custom properties consumed throughout `assets/css/main.css`. Selected via
  a `<select>` in `includes/header.php`, persisted to `localStorage` and
  (for logged-in users) the `users.theme` DB column via `theme-save.php`
  (`assets/js/theme-switcher.js`).
- `assets/img/Logo_3.png` (a green chemistry-flask icon) and
  `assets/img/wordmark.png` ("HEISENBERG" in metallic silver, "STUDIOS" in
  olive-green) are raster images — unaffected by any CSS color change. The
  user's "keep color in the logo and title" therefore only has teeth for
  the *title* half: page `<h1>` headings (`.page-title`, and the Login/
  Register card `<h2>`), which currently use `var(--color-primary)`.
- An untracked `assets/fonts/use_fonts.txt` (already present in the repo,
  not yet wired up) specifies: `Headlines: Bebas Neue Regular`,
  `Text: Nunito Regular`. The actual font files did not exist yet — fetched
  and saved during this design pass as `assets/fonts/BebasNeue-Regular.woff2`
  and `assets/fonts/Nunito-Variable.woff2` (the latter is a variable font
  covering weights 400–700 in a single file).
- Every page independently includes `themes.css` and `main.css` with a
  `?v=4` cache-busting query string (added in the prior Investment
  Fundamentals / registration work, after a real incident where a stale
  30-day browser cache masked a CSS change). Any further CSS edit must bump
  this version again.
- `card-banner-level-1` through `-5` (in `main.css`) are the access-tier
  badge colors on homepage cards (blue/green/amber/terracotta/purple) —
  explicitly out of scope; they stay exactly as they are.

## Goals

- Replace the 4-theme color system with a neutral Light/Dark pair.
- Adopt Bebas Neue (headings) + Nunito (body/UI) site-wide, self-hosted.
- Keep the card access-level badges and page-title headings colored;
  everything else (backgrounds, nav, buttons, links, borders) goes
  black/white/grey.
- Add restrained, shadcn-style interaction polish (focus rings, hover
  underlines on links, slightly larger border-radius, smooth transitions)
  without introducing Aceternity-style animated effects.

## Non-goals

- Any Aceternity-style dramatic animation (spotlights, gradient beams,
  3D tilt, etc.).
- Changing the Forest/Royal themes' color values instead of removing them
  — they are being removed, not re-skinned.
- Restyling the card access-level badge colors.
- A full design-token/component-library rewrite — this reuses the existing
  CSS custom property architecture in `main.css`/`themes.css`, just retinted
  and extended with a couple of new tokens.

## Design

### Fonts

New `assets/css/fonts.css`:

```css
@font-face {
    font-family: 'Bebas Neue';
    src: url('../fonts/BebasNeue-Regular.woff2') format('woff2');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Nunito';
    src: url('../fonts/Nunito-Variable.woff2') format('woff2');
    font-weight: 400 700;
    font-style: normal;
    font-display: swap;
}
```

Linked from every page's `<head>`, alongside `themes.css`/`main.css`.

In `main.css`:
- `body { font-family: 'Nunito', -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }`
- Headings that currently read as the page's "title" get Bebas Neue with
  slight letter-spacing (condensed caps-style face reads better spaced
  out): `.page-title`, `.card-headline`, `.login-card h2`.

### Color tokens

`assets/css/themes.css` reduced to two blocks. New token `--color-accent-fg`
is added (the text color to place *on top of* an accent-colored background —
needed because the accent flips from dark-on-light to light-on-dark between
the two themes, and several existing rules hardcode `color: #fff` assuming
a dark accent, which would be illegible in dark mode's light-grey accent).
New token `--color-brand-accent` carries the green used only for headings.

```css
body[data-theme="light"] {
    --color-bg:        #fafafa;
    --color-surface:   #ffffff;
    --color-primary:   #18181b;
    --color-accent:    #27272a;
    --color-accent-fg: #ffffff;
    --color-text:      #18181b;
    --color-text-soft: #71717a;
    --color-border:    #e4e4e7;
    --color-header-bg: #18181b;
    --color-header-fg: #fafafa;
    --color-menu-bg:   #09090b;
    --color-menu-fg:   #d4d4d8;
    --color-menu-hover:#27272a;
    --color-card-shadow: rgba(0,0,0,0.06);
    --color-brand-accent: #7cb342;
}

body[data-theme="dark"] {
    --color-bg:        #09090b;
    --color-surface:   #18181b;
    --color-primary:   #fafafa;
    --color-accent:    #e4e4e7;
    --color-accent-fg: #09090b;
    --color-text:      #fafafa;
    --color-text-soft: #a1a1aa;
    --color-border:    #27272a;
    --color-header-bg: #000000;
    --color-header-fg: #fafafa;
    --color-menu-bg:   #09090b;
    --color-menu-fg:   #d4d4d8;
    --color-menu-hover:#27272a;
    --color-card-shadow: rgba(0,0,0,0.5);
    --color-brand-accent: #9ccc65;
}
```

In `main.css`, every place currently hardcoding `color: #fff` on an
accent-colored background (`.login-box button`, `.user-box button`,
`.user-box .user-level`, `.login-card button`, `.ticker-index a.active`)
switches to `color: var(--color-accent-fg)`.

`.page-title`, `.login-card h2` switch from `var(--color-primary)` to
`var(--color-brand-accent)`. `card-banner-level-*` rules are untouched.

### Component polish

- Buttons/inputs: border-radius 4px → 6px.
- `a { transition: color 0.15s ease; }` plus `a:hover { text-decoration: underline; }`
  (links no longer stand out by hue alone in a monochrome palette, so hover
  affordance needs a second signal).
- `:focus-visible` on links/buttons/inputs: a neutral 2px outline using
  `var(--color-text-soft)` — accessible, matches shadcn's emphasis on
  visible focus states, stays inside the monochrome palette (not brand
  green, not badge colors).
- `.main-nav .dropdown` gets a short opacity/transform transition instead
  of an instant `display` toggle.

### Theme switcher simplification

- `includes/header.php`: `<select>` options reduced to
  `<option value="light">Light</option>` / `<option value="dark">Dark</option>`.
  Fallback `$currentTheme` default changes from `'default'` to `'light'`.
- `theme-save.php`: `$allowedThemes = ['light', 'dark']`.
- Every page's initial `<body data-theme="default">` becomes
  `<body data-theme="light">` (16 files — the same set touched for the
  `?v=` cache-bust pass).
- DB migration (`setup/schema_additions_theme_rename.sql`):
  ```sql
  ALTER TABLE users MODIFY theme VARCHAR(20) NOT NULL DEFAULT 'light';
  UPDATE users SET theme = 'light' WHERE theme NOT IN ('light', 'dark');
  ```
  Applied the same way the Investment Fundamentals migration was — checked
  against the live DB state, then run directly, following the project's
  established (manual, confirmed-with-user) migration process.

### Cache-busting

All CSS `<link>` tags (`themes.css`, `main.css`, new `fonts.css`) bump to
`?v=5` across every page, continuing the versioning convention established
after the earlier stale-cache incident.

## Testing / verification

Manual, consistent with prior features (no test suite in this codebase):

1. Load the homepage in Light mode → neutral grey/white chrome, Bebas Neue
   green page title, Nunito body text, access-level card badges still
   colored.
2. Toggle to Dark mode → near-black chrome, button text legible against the
   light-grey accent (verifies the `--color-accent-fg` fix), theme
   persists across reload (localStorage) and across pages (DB-backed for a
   logged-in user).
3. Confirm the theme `<select>` only offers Light/Dark.
4. Confirm hover/focus states on a button, a link, and a nav dropdown show
   the new subtle transitions.
5. Confirm fonts actually load from `assets/fonts/*.woff2` (network tab —
   no request to fonts.googleapis.com/fonts.gstatic.com at runtime).
6. Spot-check `investments/index.php` and `register.php` render correctly
   under the new theme (both were recently added and have their own
   page-specific CSS on top of `main.css`).
