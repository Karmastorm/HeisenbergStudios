<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

require_access('editor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Building a Price Scraper - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=5">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=5">
    <link rel="stylesheet" href="../assets/css/main.css?v=5">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Building a Price Scraper</h1>
        <p>Walkthrough of a Python scrapper that pulls item prices from in-game bazaar listings.</p>
        <p>Content for this page goes here.</p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
