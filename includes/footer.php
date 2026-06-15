<?php
/**
 * Site footer with sitemap columns and copyright.
 * $menu is expected to already be populated by nav.php (included earlier on the page).
 */
$year = date('Y');
?>
<footer class="site-footer">
    <div class="footer-grid">
        <div>
            <h4>Site</h4>
            <ul>
                <li><a href="/index.php">Home</a></li>
                <li><a href="/login.php">Login</a></li>
                <li><a href="/about.php">About</a></li>
                <li><a href="/contact.php">Contact</a></li>
            </ul>
        </div>

        <?php if (!empty($menu)): ?>
            <?php foreach ($menu as $category): ?>
                <?php if (!empty($category['items'])): ?>
                    <div>
                        <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                        <ul>
                            <?php foreach ($category['items'] as $item): ?>
                                <li>
                                    <a href="/index.php?section=<?php echo urlencode($item['slug']); ?>">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <div>
            <h4>Account</h4>
            <ul>
                <?php if (is_logged_in()): ?>
                    <li><a href="/profile.php">My Profile</a></li>
                    <li><a href="/logout.php">Log Out</a></li>
                <?php else: ?>
                    <li><a href="/login.php">Log In</a></li>
                    <li><a href="/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        &copy; <?php echo $year; ?> Heisenberg Studios. All rights reserved.
    </div>
</footer>
