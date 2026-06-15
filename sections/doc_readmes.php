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
    <title>Project ReadMe Index - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body data-theme="default">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Project ReadMe Index</h1>
        <p>Central index of ReadMe files for all active scripts and tools.</p>
        <p>Download files from the <a href="/files/browse.php?folder=documents/readmes">ReadMes folder</a>.</p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
