<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

$pdo = get_db_connection();
$userLevel = $_SESSION['access_level'] ?? 0;

// Visibility rule:
//   - Cards at levels 1-4 are visible to EVERYONE (including logged-out visitors).
//   - Cards at the highest level (5 = webmaster) are reserved for admins and
//     only appear for users whose access level is >= ADMIN_CARD_LEVEL.
//   (Clicking a card still enforces that page's own require_access(), so a
//    visitor who lacks access is sent to the login page.)
const ADMIN_CARD_LEVEL = 5;          // who may SEE level-5 cards
const HIDDEN_CARD_LEVEL = 5;         // which card level is admin-only

$showAdminCards = $userLevel >= ADMIN_CARD_LEVEL;

// Optional ?section=slug filter from nav clicks
$sectionSlug = $_GET['section'] ?? null;
$pageTitle = 'Latest Content';
$menuItemId = null;

if ($sectionSlug) {
    $stmt = $pdo->prepare('SELECT id, name, min_access_level FROM menu_items WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $sectionSlug]);
    $section = $stmt->fetch();

    if ($section) {
        if ($userLevel < (int)$section['min_access_level']) {
            header('Location: login.php?denied=1');
            exit;
        }
        $menuItemId = $section['id'];
        $pageTitle = htmlspecialchars($section['name']);
    }
}

// Build the visibility clause: hide level-5 cards unless the user is an admin.
$visibilityClause = $showAdminCards
    ? '1 = 1'
    : 'c.min_access_level < ' . (int)HIDDEN_CARD_LEVEL;

// Fetch cards (joined to their category for the banner label),
// optionally filtered to one menu item.
if ($menuItemId) {
    $sql =
        "SELECT c.*, mc.name AS category_name
         FROM cards c
         LEFT JOIN menu_items mi ON mi.id = c.menu_item_id
         LEFT JOIN menu_categories mc ON mc.id = mi.category_id
         WHERE $visibilityClause AND c.menu_item_id = :mid
         ORDER BY c.sort_order, c.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['mid' => $menuItemId]);
} else {
    $sql =
        "SELECT c.*, mc.name AS category_name
         FROM cards c
         LEFT JOIN menu_items mi ON mi.id = c.menu_item_id
         LEFT JOIN menu_categories mc ON mc.id = mi.category_id
         WHERE $visibilityClause
         ORDER BY c.sort_order, c.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}
$cards = $stmt->fetchAll();

$accessNames = [
    1 => 'Read Only',
    2 => 'Restricted',
    3 => 'Editor',
    4 => 'Web Dev',
    5 => 'Webmaster',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HEISENBER STUDIOS</title>
    <link rel="stylesheet" href="assets/css/themes.css?v=5">
    <link rel="stylesheet" href="assets/css/fonts.css?v=5">
    <link rel="stylesheet" href="assets/css/main.css?v=5">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/includes/header.php'; ?>
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title"><?php echo $pageTitle; ?></h1>

        <?php if (empty($cards)): ?>
            <p>No content available yet. Check back soon.</p>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($cards as $card): ?>
                    <?php
                        $level = (int)$card['min_access_level'];
                        // Banner shows the top-level category; fall back to a
                        // generic label if the card isn't tied to a category.
                        $bannerLabel = $card['category_name'] ?: 'General';
                    ?>
                    <a class="card" href="<?php echo htmlspecialchars($card['link_url']); ?>">
                        <div class="card-banner card-banner-level-<?php echo $level; ?>">
                            <span class="card-banner-category"><?php echo htmlspecialchars($bannerLabel); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="card-headline"><?php echo htmlspecialchars($card['title']); ?></div>
                            <div class="card-synopsis"><?php echo htmlspecialchars($card['synopsis']); ?></div>
                            <div class="card-date"><?php echo htmlspecialchars(date('Y.m.d', strtotime($card['created_at']))); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="assets/js/theme-switcher.js"></script>
</body>
</html>