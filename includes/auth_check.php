<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/optical-ledger');
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Staff must change password on first login
// Skip this check when already on change-password.php
$_current_script = basename($_SERVER['PHP_SELF'] ?? '');
if ($_current_script !== 'change-password.php' && isset($_SESSION['must_change_password']) && $_SESSION['must_change_password']) {
    header('Location: ' . BASE_URL . '/change-password.php');
    exit;
}
