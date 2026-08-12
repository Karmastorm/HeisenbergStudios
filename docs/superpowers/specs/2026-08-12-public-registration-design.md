# Public Self-Registration — Design

## Summary

Add a working `register.php` at the site root so the existing (currently
dead) "Register" link in `includes/footer.php` leads somewhere real. Visitors
can create their own account at the lowest access tier (`read_only`); an
admin upgrades their tier later if needed. No email verification, no CAPTCHA
service — matches the site's existing bare-bones `login.php` conventions,
plus a honeypot field since this form will be public on the open internet.

## Background

- `includes/footer.php` already links to `/register.php` on every page, but
  the file has never existed — it's a dead 404 link today.
- `includes/auth.php` already defines `create_user()` (hashes the password,
  inserts into `users` with a default access level) but nothing in the
  codebase calls it — there is currently no way to create an account except
  by hand in the database.
- `login.php` is the closest existing pattern to follow: a single-purpose
  page using `.login-card` / `.error-msg` styling from `assets/css/main.css`,
  no nav bar (just `header.php` + a footer built via `nav_data_only.php`),
  one `$error` string shown above the form, no CSRF token.
- `users` table (`setup/schema.sql`): `username` and `email` are both
  `UNIQUE`; `access_level` defaults to 1 (`read_only`) if not specified.

## Goals

- Make `/register.php` a real, working page.
- Let a visitor create a `read_only`-tier account themselves.
- Keep the same security/complexity posture as `login.php` (no CSRF token,
  no external services) — this is a small personal/team toolbox site, not
  a public product.
- Add lightweight bot resistance appropriate for a public internet-facing
  form, without pulling in a third-party CAPTCHA dependency the site has no
  existing infrastructure for.

## Non-goals

- Email verification (no mail-sending infrastructure exists in this
  codebase today; out of scope for this pass).
- CSRF protection (not present on `login.php` either; not introducing an
  inconsistent security posture for this one form).
- CAPTCHA / third-party anti-spam services.
- Any admin-side "add user" UI (a separate, different feature).

## Design

### `register.php` (new file, site root, alongside `login.php`)

Mirrors `login.php`'s structure:

```php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();
if (is_logged_in()) { redirect to index.php }
```

On `POST`:

1. **Honeypot check** — a hidden text field named `website` that real users
   never see or fill (visually hidden via CSS, not `display:none`, with
   `tabindex="-1" autocomplete="off"` so it's skipped by keyboard/screen
   readers but still visible to naive bots that fill every field). If
   non-empty, redirect to `login.php` as if registration succeeded — no
   error, no account created, no signal to the bot that it was caught.
2. **Field validation**, first failure wins, shown via the same
   `.error-msg` div `login.php` uses:
   - `username`: trimmed, required, 3–50 characters (matches
     `VARCHAR(50)`).
   - `email`: required, `filter_var(..., FILTER_VALIDATE_EMAIL)`, ≤150
     characters (matches `VARCHAR(150)`).
   - `password`: required, minimum 8 characters.
   - `confirm_password`: must equal `password`.
3. **Uniqueness check** — query `users` for an existing row matching the
   submitted username or email; if found, a specific friendly error
   ("Username already taken." / "Email already registered.").
4. **Create + auto-login** — call
   `create_user($username, $password, $email, ACCESS_LEVELS['read_only'])`;
   on success, call `attempt_login($username, $password)` (reusing existing,
   already-tested logic — session regeneration, etc.) and redirect to
   `index.php`. No separate "please log in" step.

### Markup / styling

Reuses `.login-card`, `.error-msg`, and the `login-page-wrapper` container
class already in `assets/css/main.css` — no new CSS needed for the card
itself. One small addition: a `.honeypot-field` rule (visually hidden,
off-screen) for the bot-trap input.

Page structure (matching `login.php`):

```html
<?php include header.php ?>
<div class="login-page-wrapper">
  <div class="login-card">
    <h2>Register</h2>
    [.error-msg if $error]
    <form method="post" action="register.php">
      Username, Email, Password, Confirm Password, honeypot field
      <button type="submit">Register</button>
    </form>
  </div>
</div>
<?php include nav_data_only.php; include footer.php; ?>
```

### Access control note

New accounts are always created at `ACCESS_LEVELS['read_only']` (1) —
the form does not expose an access-level field. There is no path for a
self-registering visitor to grant themselves a higher tier.

## Testing / verification

Manual, consistent with how the Investment Fundamentals feature was
verified (no test suite in this codebase):

1. Visit `/register.php` while logged out → form renders.
2. Submit with a duplicate username → friendly "already taken" error, no
   account created.
3. Submit with a duplicate email → friendly "already registered" error.
4. Submit with mismatched passwords / too-short password / invalid email →
   appropriate error, no account created.
5. Submit a valid, unique registration → account appears in `users` with
   `access_level = 1`, user is auto-logged-in and lands on `index.php`.
6. Fill the honeypot field (via curl, simulating a bot) → redirected as if
   successful, but no row added to `users`.
7. Confirm the footer's "Register" link no longer 404s from any page.
