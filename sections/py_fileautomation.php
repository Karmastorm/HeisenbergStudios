<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

// This page requires 'web_dev' (level 4) or higher
require_access('web_dev');

$pdo = get_db_connection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Automation - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=4">
    <link rel="stylesheet" href="../assets/css/main.css?v=4">
</head>
<body data-theme="default">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Automated File Sorter</h1>
        <p>This page is restricted to <strong>Web Dev</strong> level (4) and above.</p>
        <p>Content for this tool goes here&mdash;scripts, download links, configuration notes, etc.</p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
