<?php
/**
 * Site header / banner.
 * Expects start_secure_session() to have already been called by the page.
 */
$user = current_user();
$currentTheme = $user['theme'] ?? ($_SESSION['theme'] ?? 'light');

$accessNames = [
    1 => 'Read Only',
    2 => 'Restricted',
    3 => 'Editor',
    4 => 'Web Dev',
    5 => 'Webmaster',
];
?>
<header class="site-header">
    <div class="brand">
        <img src="/assets/img/Logo_3.png" alt="Heisenberg Studios logo" class="brand-logo">
        <img src="/assets/img/wordmark.png" alt="Heisenberg Studios" class="brand-wordmark">
    </div>

    <div class="header-right">
        <div class="theme-switcher">
            <select id="theme-select" aria-label="Choose color theme">
                <option value="light">Light</option>
                <option value="dark">Dark</option>
            </select>
        </div>

        <?php if ($user): ?>
            <div class="user-box">
                <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                <span class="user-level"><?php echo htmlspecialchars($accessNames[$user['access_level']] ?? 'Unknown'); ?></span>
                <form action="/logout.php" method="post" style="display:inline;">
                    <button type="submit">Log Out</button>
                </form>
            </div>
        <?php else: ?>
            <div class="login-box">
                <form action="/login.php" method="post">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Log In</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>
<script>
    document.body.dataset.loggedIn = '<?php echo $user ? '1' : '0'; ?>';
    document.body.setAttribute('data-theme', '<?php echo htmlspecialchars($currentTheme); ?>');
</script>