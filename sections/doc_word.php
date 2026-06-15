<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

require_access('read_only');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Word Templates Library - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body data-theme="default">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Word Templates Library</h1>
        <p>Collection of formatted Word document templates for reports and guides.</p>
        <p>Download files from the <a href="/files/browse.php?folder=documents/word">Word Docs folder</a>.</p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
