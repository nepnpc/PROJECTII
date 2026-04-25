<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$page_title   = 'Change Password';
$current_page = 'change-password';
$message = $error = '';

$must_change = false;
$stmt = $pdo->prepare("SELECT must_change_password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch();
$must_change = (bool)($row['must_change_password'] ?? false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = trim($_POST['current_password'] ?? '');
    $new_pass = trim($_POST['new_password']      ?? '');
    $confirm  = trim($_POST['confirm_password']  ?? '');

    if (!$current || !$new_pass || !$confirm) {
        $error = 'All fields are required.';
    } elseif (strlen($new_pass) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new_pass !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        // Verify current password
        $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND password = MD5(?)");
        $check->execute([$_SESSION['user_id'], $current]);

        if (!$check->fetch()) {
            $error = 'Current password is incorrect.';
        } else {
            // Update password + clear must_change flag
            $pdo->prepare(
                "UPDATE users SET password = MD5(?), must_change_password = 0 WHERE id = ?"
            )->execute([$new_pass, $_SESSION['user_id']]);

            $message = 'Password changed successfully.';
            $must_change = false;

            // Redirect to dashboard after 2s
            header('refresh: 2; url=' . BASE_URL . ($_SESSION['user_role'] === 'admin' ? '/admin/index.php' : '/staff/index.php'));
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($must_change && !$message): ?>
<div class="alert alert-warning">
    <strong>&#9888; First Login:</strong> You must change your password before continuing.
</div>
<?php endif; ?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?> Redirecting...</div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="max-width:480px;">
    <div class="card-header"><h3>&#128274; Change Password</h3></div>
    <div class="card-body">
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Current Password *</label>
                <input type="password" name="current_password" required
                       placeholder="Enter current password">
            </div>
            <div class="form-group">
                <label>New Password * <small>(min 6 characters)</small></label>
                <input type="password" name="new_password" required
                       placeholder="Enter new password" minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm New Password *</label>
                <input type="password" name="confirm_password" required
                       placeholder="Repeat new password">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Change Password</button>
                <?php if (!$must_change): ?>
                <a href="<?= BASE_URL . ($_SESSION['user_role'] === 'admin' ? '/admin/index.php' : '/staff/index.php') ?>"
                   class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
