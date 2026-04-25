<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Already logged in → redirect
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . ($_SESSION['user_role'] === 'admin' ? '/admin/index.php' : '/staff/index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, role, must_change_password
             FROM users WHERE email = ? AND password = MD5(?) AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$email, $password]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id']              = $user['id'];
            $_SESSION['user_name']            = $user['name'];
            $_SESSION['user_role']            = $user['role'];
            $_SESSION['user_email']           = $user['email'];
            $_SESSION['must_change_password'] = (bool)($user['must_change_password'] ?? false);

            // Staff on first login → force password change
            if ($_SESSION['must_change_password'] && $user['role'] === 'staff') {
                header('Location: ' . BASE_URL . '/change-password.php');
                exit;
            }

            $redirect = ($user['role'] === 'admin')
                ? BASE_URL . '/admin/index.php'
                : BASE_URL . '/staff/index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid email or password. Check your credentials.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login &mdash; Optical Ledger</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <h1>&#128065; Optical Ledger</h1>
        <p class="subtitle">Lens Inventory &amp; Wholesale Management System</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    required
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter password"
                    required
                >
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

    </div>
</div>
</body>
</html>
