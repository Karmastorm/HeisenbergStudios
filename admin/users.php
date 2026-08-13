<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

// Only webmaster (5) can approve accounts or change access levels
require_access('webmaster');

$pdo = get_db_connection();
$currentUserId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

$accessNames = [
    1 => 'Read Only',
    2 => 'Restricted',
    3 => 'Editor',
    4 => 'Web Dev',
    5 => 'Webmaster',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'approve') {
        $level = (int)($_POST['access_level'] ?? 1);
        if ($level < 1 || $level > 5) {
            $error = 'Invalid access level.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET is_active = 1, access_level = :level WHERE id = :id');
            $stmt->execute(['level' => $level, 'id' => $id]);
            $message = 'Account approved.';
        }
    } elseif ($action === 'deactivate') {
        if ($id === $currentUserId) {
            $error = 'You cannot deactivate your own account.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $message = 'Account deactivated.';
        }
    } elseif ($action === 'set_level') {
        $level = (int)($_POST['access_level'] ?? 1);
        if ($level < 1 || $level > 5) {
            $error = 'Invalid access level.';
        } elseif ($id === $currentUserId && $level < 5) {
            $error = 'You cannot lower your own access level below Webmaster.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET access_level = :level WHERE id = :id');
            $stmt->execute(['level' => $level, 'id' => $id]);
            $message = 'Access level updated.';
        }
    }
}

$users = $pdo->query(
    'SELECT id, username, email, access_level, is_active, created_at, last_login
     FROM users ORDER BY is_active ASC, created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Heisenberg Studios</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=5">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=5">
    <link rel="stylesheet" href="../assets/css/main.css?v=5">
    <style>
        .admin-table { width: 100%; border-collapse: collapse; background: var(--color-surface); border: 1px solid var(--color-border); }
        .admin-table th, .admin-table td { padding: 0.55rem 0.7rem; border-bottom: 1px solid var(--color-border); text-align: left; font-size: 0.88rem; }
        .admin-table th { background: var(--color-bg); }
        .admin-table select { padding: 0.3rem 0.5rem; border-radius: 4px; border: 1px solid var(--color-border); }
        .admin-table button { padding: 0.3rem 0.8rem; background: var(--color-accent); color: var(--color-accent-fg); border: none; border-radius: 4px; cursor: pointer; }
        .admin-table .row-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .msg-success { background: #e6f4ea; color: #1e7e34; padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        .msg-error { background: #fdecea; color: #b3261e; padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        .badge-pending { color: #b3261e; font-weight: 600; }
        .badge-active { color: #1e7e34; }
    </style>
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Manage Users</h1>
        <p style="margin-bottom:1rem; color:var(--color-text-soft); font-size:0.9rem;">
            New self-registered accounts start inactive and cannot log in until approved here.
            Deactivating an account (including a rejected registration) can be reversed later by approving it again.
        </p>

        <?php if ($message): ?><div class="msg-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Access Level</th>
                    <th>Created</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge-pending">Pending / Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($accessNames[(int)$u['access_level']] ?? $u['access_level']); ?></td>
                        <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($u['last_login'] ?? 'Never'); ?></td>
                        <td>
                            <div class="row-actions">
                                <?php if ($u['is_active']): ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="set_level">
                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                        <select name="access_level">
                                            <?php foreach ($accessNames as $level => $name): ?>
                                                <option value="<?php echo $level; ?>" <?php echo (int)$u['access_level'] === $level ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($name); ?> (<?php echo $level; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Update</button>
                                    </form>
                                    <?php if ((int)$u['id'] !== $currentUserId): ?>
                                        <form method="post" onsubmit="return confirm('Deactivate this account?');">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                            <button type="submit">Deactivate</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                        <select name="access_level">
                                            <?php foreach ($accessNames as $level => $name): ?>
                                                <option value="<?php echo $level; ?>" <?php echo $level === 1 ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($name); ?> (<?php echo $level; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Approve</button>
                                    </form>
                                <?php endif; ?>
                            </div>
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
