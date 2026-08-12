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
    <title>E3 Bot Configuration - My Toolbox Site</title>
    <link rel="stylesheet" href="../assets/css/themes.css?v=4">
    <link rel="stylesheet" href="../assets/css/main.css?v=4">
</head>
<body data-theme="default">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <main class="main-content">
        <h1 class="page-title">E3 Bot Configuration</h1>
        <p>Step-by-step setup notes for configuring E3 bot profiles for group and raid scenarios.</p>
        <p>Content for this page goes here.</p>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>
