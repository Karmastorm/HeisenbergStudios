<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

// Only webmaster (5) can change folder access levels
require_access('webmaster');

$pdo = get_db_connection();
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
    $id = (int)($_POST['id'] ?? 0);
    $level = (int)($_POST['min_access_level'] ?? 1);
    if ($level >= 1 && $level <= 5) {
        $stmt = $pdo->prepare('UPDATE file_folders SET min_access_level = :level WHERE id = :id');
        $stmt->execute(['level' => $level, 'id' => $id]);
        $message = 'Folder access level updated.';
    } else {
        $error = 'Invalid access level.';
    }
}

$folders = $pdo->query('SELECT * FROM file_folders ORDER BY folder_path')->fetchAll();

// Check which folders physically exist and whether they're writable
foreach ($folders as &$f) {
    $path = __DIR__ . '/../files/' . $f['folder_path'];
    $f['exists'] = is_dir($path);
    $f['file_count'] = $f['exists'] ? count(array_filter(scandir($path), fn($x) => $x !== '.' && $x !== '..' && $x !== '.htaccess')) : 0;
}
unset($f);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Folders - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=4">
    <link rel="stylesheet" href="../assets/css/main.css?v=4">
    <style>
        .admin-table { width: 100%; border-collapse: collapse; background: var(--color-surface); border: 1px solid var(--color-border); }
        .admin-table th, .admin-table td { padding: 0.55rem 0.7rem; border-bottom: 1px solid var(--color-border); text-align: left; font-size: 0.88rem; }
        .admin-table th { background: var(--color-bg); }
        .admin-table select { padding: 0.3rem 0.5rem; border-radius: 4px; border: 1px solid var(--color-border); }
        .admin-table button { padding: 0.3rem 0.8rem; background: var(--color-accent); color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .msg-success { background: #e6f4ea; color: #1e7e34; padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        .msg-error { background: #fdecea; color: #b3261e; padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        .badge-ok { color: #1e7e34; }
        .badge-missing { color: #b3261e; }
    </style>
</head>
<body data-theme="default">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Manage Protected Folders</h1>
        <p style="margin-bottom:1rem; color:var(--color-text-soft); font-size:0.9rem;">
            Upload files into these folders via FTP at <code>/files/&lt;folder_path&gt;/</code>.
            Access is enforced by <code>files/download.php</code> &mdash; users below the required level cannot view or download files even with a direct link.
        </p>

        <?php if ($message): ?><div class="msg-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Folder Path</th>
                    <th>Display Name</th>
                    <th>Status</th>
                    <th>Files</th>
                    <th>Min Access Level</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($folders as $f): ?>
                    <tr>
                        <td><code>files/<?php echo htmlspecialchars($f['folder_path']); ?>/</code></td>
                        <td><?php echo htmlspecialchars($f['display_name']); ?></td>
                        <td>
                            <?php if ($f['exists']): ?>
                                <span class="badge-ok">&#10003; exists</span>
                            <?php else: ?>
                                <span class="badge-missing">&#10007; missing &mdash; create via FTP</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $f['file_count']; ?></td>
                        <td>
                            <form method="post" style="display:flex; gap:0.5rem; align-items:center;">
                                <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                <select name="min_access_level">
                                    <?php foreach ($accessNames as $level => $name): ?>
                                        <option value="<?php echo $level; ?>" <?php echo (int)$f['min_access_level'] === $level ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?> (<?php echo $level; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:1.5rem; font-size:0.85rem; color:var(--color-text-soft);">
            To add a brand-new protected folder, create the directory under <code>/files/</code> via FTP, then
            insert a row into the <code>file_folders</code> table (folder_path, display_name, min_access_level).
        </p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
