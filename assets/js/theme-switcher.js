/**
 * Theme switcher.
 * Persists choice in localStorage and (if logged in) saves to
 * the server via theme-save.php so it follows the user across devices.
 */
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('theme-select');
    if (!select) return;

    const body = document.body;
    const saved = localStorage.getItem('site-theme');
    if (saved) {
        body.setAttribute('data-theme', saved);
        select.value = saved;
    }

    select.addEventListener('change', function () {
        const theme = select.value;
        body.setAttribute('data-theme', theme);
        localStorage.setItem('site-theme', theme);

        // Persist server-side for logged-in users
        if (document.body.dataset.loggedIn === '1') {
            fetch('theme-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'theme=' + encodeURIComponent(theme)
            });
        }
    });
});
