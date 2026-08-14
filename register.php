<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

$error = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: real users never see or fill this field. If it's filled,
    // pretend registration succeeded without creating anything or
    // tipping off the bot.
    if (trim($_POST['website'] ?? '') !== '') {
        header('Location: login.php');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Username must be between 3 and 50 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute(['username' => $username, 'email' => $email]);
        $existing = $stmt->fetch();

        if ($existing && $existing['username'] === $username) {
            $error = 'Username already taken.';
        } elseif ($existing) {
            $error = 'Email already registered.';
        } elseif (!create_user($username, $password, $email, ACCESS_LEVELS['read_only'], isActive: false)) {
            $error = 'Something went wrong creating your account. Please try again.';
        } else {
            $submitted = true;
        }
    }
}

if (!$submitted && is_logged_in()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - My Toolbox Site</title>
    <link rel="stylesheet" href="assets/css/themes.css?v=7">
    <link rel="stylesheet" href="assets/css/fonts.css?v=7">
    <link rel="stylesheet" href="assets/css/main.css?v=7">
</head>
<body data-theme="light">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="login-page-wrapper">
        <div class="login-card">
            <h2>Register</h2>
            <?php if ($submitted): ?>
                <div class="success-msg">Registration submitted &mdash; an admin will review your account before you can log in.</div>
                <p><a href="login.php">Return to Log In</a></p>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="post" action="register.php">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>

                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>

                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>

                    <div class="honeypot-field" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <button type="submit">Register</button>
                </form>
            <?php endif; ?>
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
