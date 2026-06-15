<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

require_access('restricted');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Trend Reports - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body data-theme="default">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Market Trend Reports</h1>
        <p>Weekly summaries of market trends gathered from scraped pricing data.</p>
        <p>Content for this page goes here.</p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
