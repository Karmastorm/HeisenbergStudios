<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

$pdo = get_db_connection();
$userLevel = $_SESSION['access_level'] ?? 0;

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

// Fetch cards visible to this user, optionally filtered to one menu item
if ($menuItemId) {
    $stmt = $pdo->prepare(
        'SELECT * FROM cards WHERE min_access_level <= :level AND menu_item_id = :mid
         ORDER BY sort_order, created_at DESC'
    );
    $stmt->execute(['level' => $userLevel, 'mid' => $menuItemId]);
} else {
    $stmt = $pdo->prepare(
        'SELECT * FROM cards WHERE min_access_level <= :level
         ORDER BY sort_order, created_at DESC'
    );
    $stmt->execute(['level' => $userLevel]);
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
    <title>My Toolbox Site</title>
    <link rel="stylesheet" href="assets/css/themes.css">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body data-theme="default">
    <?php include __DIR__ . '/includes/header.php'; ?>
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title"><?php echo $pageTitle; ?></h1>

        <?php if (empty($cards)): ?>
            <p>No content available for your access level yet. Check back soon.</p>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($cards as $card): ?>
                    <a class="card" href="<?php echo htmlspecialchars($card['link_url']); ?>">
                        <div class="card-body">
                            <span class="card-badge"><?php echo htmlspecialchars($accessNames[$card['min_access_level']] ?? ''); ?></span>
                            <div class="card-headline"><?php echo htmlspecialchars($card['title']); ?></div>
                            <div class="card-synopsis"><?php echo htmlspecialchars($card['synopsis']); ?></div>
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
