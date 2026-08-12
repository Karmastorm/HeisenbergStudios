<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (attempt_login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
} elseif (isset($_GET['denied'])) {
    $error = 'You must log in with an account that has sufficient access to view that page.';
}

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - My Toolbox Site</title>
    <link rel="stylesheet" href="assets/css/themes.css?v=5">
    <link rel="stylesheet" href="assets/css/fonts.css?v=5">
    <link rel="stylesheet" href="assets/css/main.css?v=5">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="login-page-wrapper">
        <div class="login-card">
            <h2>Log In</h2>
            <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="login.php">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Log In</button>
            </form>
        </div>
    </div>

    <?php
    // Build $menu for footer (guest view)
    include __DIR__ . '/includes/nav_data_only.php';
    include __DIR__ . '/includes/footer.php';
    ?>
    <script src="assets/js/theme-switcher.js"></script>
</body>
</html>
