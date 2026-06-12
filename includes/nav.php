<?php
/**
 * Main navigation menu.
 * Pulls categories + items from the database, filtering anything
 * the current user doesn't have access to.
 */
$userLevel = $_SESSION['access_level'] ?? 0; // 0 = guest, sees only public (level 1) items... 
// Note: if you want guests to see NOTHING, change the line above to: $userLevel = $_SESSION['access_level'] ?? 0;
// and ensure min_access_level for guest-visible items is 0 or adjust the WHERE clause below.

$pdo = get_db_connection();

$stmt = $pdo->prepare(
    'SELECT c.id AS cat_id, c.name AS cat_name, c.sort_order AS cat_sort, c.min_access_level AS cat_level,
            i.id AS item_id, i.name AS item_name, i.slug AS item_slug,
            i.sort_order AS item_sort, i.min_access_level AS item_level
     FROM menu_categories c
     LEFT JOIN menu_items i ON i.category_id = c.id AND i.min_access_level <= :userLevel1
     WHERE c.min_access_level <= :userLevel2
     ORDER BY c.sort_order, i.sort_order'
);
$stmt->execute(['userLevel1' => $userLevel, 'userLevel2' => $userLevel]);
$rows = $stmt->fetchAll();

// Group rows into categories => items
$menu = [];
foreach ($rows as $row) {
    $catId = $row['cat_id'];
    if (!isset($menu[$catId])) {
        $menu[$catId] = [
            'name'  => $row['cat_name'],
            'items' => [],
        ];
    }
    if ($row['item_id'] !== null) {
        $menu[$catId]['items'][] = [
            'name' => $row['item_name'],
            'slug' => $row['item_slug'],
        ];
    }
}
?>
<nav class="main-nav">
    <ul>
        <li><a href="index.php">Home</a></li>
        <?php foreach ($menu as $category): ?>
            <li>
                <span><?php echo htmlspecialchars($category['name']); ?> &#9662;</span>
                <?php if (!empty($category['items'])): ?>
                    <ul class="dropdown">
                        <?php foreach ($category['items'] as $item): ?>
                            <li>
                                <a href="index.php?section=<?php echo urlencode($item['slug']); ?>">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
