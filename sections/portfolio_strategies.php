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
    <title>Portfolio Strategies - Heisenberg Studios</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=7">
    <link rel="stylesheet" href="../assets/css/fonts.css?v=7">
    <link rel="stylesheet" href="../assets/css/main.css?v=7">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">Portfolio Strategies</h1>
        <p>Notes and rationale behind current portfolio strategy.</p>
        <p>Content for this page goes here.</p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
