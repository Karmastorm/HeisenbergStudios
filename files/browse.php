<?php
/**
 * files/browse.php?folder=everquest/macroquest
 *
 * Lists the contents of a protected folder. Access is checked
 * against the file_folders table. Actual file downloads go
 * through download.php (which re-checks access independently).
 */
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

$pdo = get_db_connection();
$userLevel = $_SESSION['access_level'] ?? 0;

$folder = $_GET['folder'] ?? '';
$folder = trim($folder, "/ \t\n\r\0\x0B");

// --- Validate folder against whitelist in DB (prevents path traversal) ---
$stmt = $pdo->prepare('SELECT * FROM file_folders WHERE folder_path = :folder LIMIT 1');
$stmt->execute(['folder' => $folder]);
$folderRow = $stmt->fetch();

if (!$folderRow) {
    http_response_code(404);
    $error = 'Folder not found.';
} elseif ($userLevel < (int)$folderRow['min_access_level']) {
    header('Location: ../login.php?denied=1');
    exit;
} else {
    $error = null;
    $fullPath = __DIR__ . '/' . $folderRow['folder_path'];
    $files = [];
    if (is_dir($fullPath)) {
        foreach (scandir($fullPath) as $f) {
            if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
            if (is_file($fullPath . '/' . $f)) {
                $files[] = [
                    'name' => $f,
                    'size' => filesize($fullPath . '/' . $f),
                    'modified' => filemtime($fullPath . '/' . $f),
                ];
            }
        }
    }
}

function format_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($folderRow['display_name'] ?? 'Files'); ?> - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=5">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=5">
    <link rel="stylesheet" href="../assets/css/main.css?v=5">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <?php if ($error): ?>
            <div class="access-denied">
                <h2>Not Found</h2>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php else: ?>
            <h1 class="page-title"><?php echo htmlspecialchars($folderRow['display_name']); ?></h1>

            <?php if (empty($files)): ?>
                <p>No files have been uploaded to this folder yet.</p>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse; background:var(--color-surface); border:1px solid var(--color-border);">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--color-border);">
                            <th style="padding:0.6rem 0.8rem;">File Name</th>
                            <th style="padding:0.6rem 0.8rem;">Size</th>
                            <th style="padding:0.6rem 0.8rem;">Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:0.6rem 0.8rem;">
                                    <a href="download.php?folder=<?php echo urlencode($folderRow['folder_path']); ?>&file=<?php echo urlencode($file['name']); ?>">
                                        <?php echo htmlspecialchars($file['name']); ?>
                                    </a>
                                </td>
                                <td style="padding:0.6rem 0.8rem;"><?php echo format_size($file['size']); ?></td>
                                <td style="padding:0.6rem 0.8rem;"><?php echo date('Y-m-d H:i', $file['modified']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
