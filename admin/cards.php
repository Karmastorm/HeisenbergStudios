<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

// Only editor (3) and above can manage cards
require_access('editor');

$pdo = get_db_connection();
$userLevel = (int)$_SESSION['access_level'];
$message = '';
$error = '';

// Fetch menu items for the dropdown (section assignment)
$menuItemsStmt = $pdo->query(
    'SELECT mi.id, mi.name, mc.name AS category_name
     FROM menu_items mi
     JOIN menu_categories mc ON mc.id = mi.category_id
     ORDER BY mc.sort_order, mi.sort_order'
);
$menuItems = $menuItemsStmt->fetchAll();

$accessNames = [
    1 => 'Read Only',
    2 => 'Restricted',
    3 => 'Editor',
    4 => 'Web Dev',
    5 => 'Webmaster',
];

// ------------------------------------------------------------
// Handle form submission (add or update)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $title       = trim($_POST['title'] ?? '');
    $synopsis    = trim($_POST['synopsis'] ?? '');
    $link_url    = trim($_POST['link_url'] ?? '');
    $image_url   = trim($_POST['image_url'] ?? '') ?: null;
    $menu_item_id = ($_POST['menu_item_id'] ?? '') !== '' ? (int)$_POST['menu_item_id'] : null;
    $min_access_level = (int)($_POST['min_access_level'] ?? 1);
    $sort_order  = (int)($_POST['sort_order'] ?? 0);

    // Validation
    if ($title === '' || $synopsis === '' || $link_url === '') {
        $error = 'Title, synopsis, and link URL are required.';
    } elseif ($min_access_level < 1 || $min_access_level > 5) {
        $error = 'Invalid access level.';
    } elseif ($min_access_level > $userLevel) {
        // A user cannot create content gated above their own level
        $error = 'You cannot set a minimum access level higher than your own.';
    } else {
        if ($action === 'add') {
            $stmt = $pdo->prepare(
                'INSERT INTO cards (menu_item_id, title, synopsis, link_url, image_url, min_access_level, sort_order)
                 VALUES (:menu_item_id, :title, :synopsis, :link_url, :image_url, :min_access_level, :sort_order)'
            );
            $stmt->execute([
                'menu_item_id' => $menu_item_id,
                'title' => $title,
                'synopsis' => $synopsis,
                'link_url' => $link_url,
                'image_url' => $image_url,
                'min_access_level' => $min_access_level,
                'sort_order' => $sort_order,
            ]);
            $message = 'Card added successfully.';
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);

            // Check the existing card's level too -- don't let a lower-level
            // editor modify a card gated above their own access
            $check = $pdo->prepare('SELECT min_access_level FROM cards WHERE id = :id');
            $check->execute(['id' => $id]);
            $existing = $check->fetch();

            if (!$existing) {
                $error = 'Card not found.';
            } elseif ((int)$existing['min_access_level'] > $userLevel) {
                $error = 'You do not have permission to edit this card.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE cards SET menu_item_id = :menu_item_id, title = :title, synopsis = :synopsis,
                            link_url = :link_url, image_url = :image_url,
                            min_access_level = :min_access_level, sort_order = :sort_order
                     WHERE id = :id'
                );
                $stmt->execute([
                    'menu_item_id' => $menu_item_id,
                    'title' => $title,
                    'synopsis' => $synopsis,
                    'link_url' => $link_url,
                    'image_url' => $image_url,
                    'min_access_level' => $min_access_level,
                    'sort_order' => $sort_order,
                    'id' => $id,
                ]);
                $message = 'Card updated successfully.';
            }
        }
    }
}

// ------------------------------------------------------------
// Handle delete (separate GET-confirmed action)
// ------------------------------------------------------------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $check = $pdo->prepare('SELECT min_access_level FROM cards WHERE id = :id');
    $check->execute(['id' => $id]);
    $existing = $check->fetch();

    if ($existing && (int)$existing['min_access_level'] <= $userLevel) {
        $del = $pdo->prepare('DELETE FROM cards WHERE id = :id');
        $del->execute(['id' => $id]);
        $message = 'Card deleted.';
    } else {
        $error = 'You do not have permission to delete this card.';
    }
}

// ------------------------------------------------------------
// Load card for editing if ?edit=ID is set
// ------------------------------------------------------------
$editCard = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM cards WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $editCard = $stmt->fetch();
    if ($editCard && (int)$editCard['min_access_level'] > $userLevel) {
        $editCard = null;
        $error = 'You do not have permission to edit that card.';
    }
}

// ------------------------------------------------------------
// Fetch all cards (visible to this user's level for editing purposes)
// ------------------------------------------------------------
$cardsStmt = $pdo->prepare(
    'SELECT c.*, mi.name AS item_name FROM cards c
     LEFT JOIN menu_items mi ON mi.id = c.menu_item_id
     WHERE c.min_access_level <= :level
     ORDER BY c.sort_order, c.created_at DESC'
);
$cardsStmt->execute(['level' => $userLevel]);
$allCards = $cardsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cards - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=5">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=5">
    <link rel="stylesheet" href="../assets/css/main.css?v=5">
    <style>
        .admin-form { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px; padding: 1.25rem; margin-bottom: 2rem; max-width: 700px; }
        .admin-form label { display: block; font-size: 0.85rem; color: var(--color-text-soft); margin-bottom: 0.25rem; margin-top: 0.75rem; }
        .admin-form input, .admin-form select, .admin-form textarea {
            width: 100%; padding: 0.5rem 0.6rem; border: 1px solid var(--color-border); border-radius: 4px; font-family: inherit; font-size: 0.9rem;
        }
        .admin-form textarea { resize: vertical; min-height: 80px; }
        .admin-form button {
            margin-top: 1rem; padding: 0.55rem 1.2rem; background: var(--color-accent); color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 0.95rem;
        }
        .admin-table { width: 100%; border-collapse: collapse; background: var(--color-surface); border: 1px solid var(--color-border); }
        .admin-table th, .admin-table td { padding: 0.55rem 0.7rem; border-bottom: 1px solid var(--color-border); text-align: left; font-size: 0.88rem; }
        .admin-table th { background: var(--color-bg); }
        .admin-actions a { margin-right: 0.6rem; font-size: 0.85rem; }
        .msg-success { background: #e6f4ea; color: #1e7e34; padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        .msg-error { background: #fdecea; color: #b3261e; padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Manage Cards</h1>

        <?php if ($message): ?><div class="msg-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="admin-form">
            <h3><?php echo $editCard ? 'Edit Card' : 'Add New Card'; ?></h3>
            <form method="post" action="cards.php<?php echo $editCard ? '?edit=' . $editCard['id'] : ''; ?>">
                <input type="hidden" name="action" value="<?php echo $editCard ? 'edit' : 'add'; ?>">
                <?php if ($editCard): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$editCard['id']; ?>">
                <?php endif; ?>

                <label for="title">Title / Headline</label>
                <input type="text" id="title" name="title" required
                       value="<?php echo htmlspecialchars($editCard['title'] ?? ''); ?>">

                <label for="synopsis">Synopsis</label>
                <textarea id="synopsis" name="synopsis" required><?php echo htmlspecialchars($editCard['synopsis'] ?? ''); ?></textarea>

                <label for="link_url">Link URL (where the card goes when clicked)</label>
                <input type="text" id="link_url" name="link_url" required
                       value="<?php echo htmlspecialchars($editCard['link_url'] ?? ''); ?>"
                       placeholder="sections/example.php or files/browse.php?folder=...">

                <label for="image_url">Image URL (optional)</label>
                <input type="text" id="image_url" name="image_url"
                       value="<?php echo htmlspecialchars($editCard['image_url'] ?? ''); ?>">

                <label for="menu_item_id">Section (menu item)</label>
                <select id="menu_item_id" name="menu_item_id">
                    <option value="">-- None / General --</option>
                    <?php foreach ($menuItems as $mi): ?>
                        <option value="<?php echo $mi['id']; ?>"
                            <?php echo (isset($editCard['menu_item_id']) && $editCard['menu_item_id'] == $mi['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mi['category_name'] . ' > ' . $mi['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="min_access_level">Minimum Access Level</label>
                <select id="min_access_level" name="min_access_level">
                    <?php foreach ($accessNames as $level => $name): ?>
                        <?php if ($level <= $userLevel): ?>
                            <option value="<?php echo $level; ?>"
                                <?php echo (isset($editCard['min_access_level']) && (int)$editCard['min_access_level'] === $level) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?> (<?php echo $level; ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <label for="sort_order">Sort Order</label>
                <input type="number" id="sort_order" name="sort_order"
                       value="<?php echo htmlspecialchars((string)($editCard['sort_order'] ?? 0)); ?>">

                <button type="submit"><?php echo $editCard ? 'Update Card' : 'Add Card'; ?></button>
                <?php if ($editCard): ?>
                    <a href="cards.php" style="margin-left: 1rem; font-size: 0.9rem;">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <h3 style="margin-bottom: 0.75rem;">Existing Cards</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Section</th>
                    <th>Min Level</th>
                    <th>Sort</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allCards as $card): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($card['title']); ?></td>
                        <td><?php echo htmlspecialchars($card['item_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($accessNames[$card['min_access_level']] ?? $card['min_access_level']); ?></td>
                        <td><?php echo (int)$card['sort_order']; ?></td>
                        <td class="admin-actions">
                            <a href="cards.php?edit=<?php echo $card['id']; ?>">Edit</a>
                            <a href="cards.php?delete=<?php echo $card['id']; ?>"
                               onclick="return confirm('Delete this card?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
