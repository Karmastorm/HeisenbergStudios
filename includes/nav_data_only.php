<?php
/**
 * Builds the $menu array (categories => items) for use in the footer
 * sitemap, without rendering the <nav> HTML. Used on pages such as
 * login.php that include footer.php but not nav.php.
 */
$userLevel = $_SESSION['access_level'] ?? 0;

$pdo = get_db_connection();

$stmt = $pdo->prepare(
    'SELECT c.id AS cat_id, c.name AS cat_name, c.sort_order AS cat_sort,
            i.id AS item_id, i.name AS item_name, i.slug AS item_slug,
            i.sort_order AS item_sort
     FROM menu_categories c
     LEFT JOIN menu_items i ON i.category_id = c.id AND i.min_access_level <= :userLevel1
     WHERE c.min_access_level <= :userLevel2
     ORDER BY c.sort_order, i.sort_order'
);
$stmt->execute(['userLevel1' => $userLevel, 'userLevel2' => $userLevel]);
$rows = $stmt->fetchAll();

// Mirrors nav.php: menu items with exactly one card link straight to it.
$linkStmt = $pdo->query(
    'SELECT menu_item_id, MIN(link_url) AS link_url
     FROM cards
     WHERE menu_item_id IS NOT NULL
     GROUP BY menu_item_id
     HAVING COUNT(*) = 1'
);
$directLinks = $linkStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$menu = [];
foreach ($rows as $row) {
    $catId = $row['cat_id'];
    if (!isset($menu[$catId])) {
        $menu[$catId] = ['name' => $row['cat_name'], 'items' => []];
    }
    if ($row['item_id'] !== null) {
        $menu[$catId]['items'][] = [
            'name' => $row['item_name'],
            'slug' => $row['item_slug'],
            'link' => $directLinks[$row['item_id']] ?? null,
        ];
    }
}
