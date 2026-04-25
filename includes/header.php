<?php
// Expected vars: $page_title (string), $current_page (string)
$_role      = $_SESSION['user_role'] ?? 'staff';
$_user_name = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title ?? 'Optical Ledger') ?> &mdash; Optical Ledger</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="wrapper">

<!-- ── SIDEBAR ──────────────────────────────────────────── -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <h2>&#128065; Optical Ledger</h2>
        <span><?= $_role === 'admin' ? 'Admin Panel' : 'Staff Panel' ?></span>
    </div>

    <nav class="sidebar-nav">
        <ul>
        <?php if ($_role === 'admin'): ?>
            <li class="nav-section">Main</li>
            <li>
                <a href="<?= BASE_URL ?>/admin/index.php"
                   class="<?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>">
                    &#9632; Dashboard
                </a>
            </li>
            <li class="nav-section">Catalog</li>
            <li>
                <a href="<?= BASE_URL ?>/admin/lenses.php"
                   class="<?= ($current_page ?? '') === 'lenses' ? 'active' : '' ?>">
                    &#9632; Lenses
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/admin/outlets.php"
                   class="<?= ($current_page ?? '') === 'outlets' ? 'active' : '' ?>">
                    &#9632; Outlets
                </a>
            </li>
            <li class="nav-section">Operations</li>
            <li>
                <a href="<?= BASE_URL ?>/admin/inventory.php"
                   class="<?= ($current_page ?? '') === 'inventory' ? 'active' : '' ?>">
                    &#9632; Inventory
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/admin/orders.php"
                   class="<?= ($current_page ?? '') === 'orders' ? 'active' : '' ?>">
                    &#9632; Orders
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/admin/damages.php"
                   class="<?= ($current_page ?? '') === 'damages' ? 'active' : '' ?>">
                    &#9632; Damages
                </a>
            </li>
            <li class="nav-section">System</li>
            <li>
                <a href="<?= BASE_URL ?>/admin/users.php"
                   class="<?= ($current_page ?? '') === 'users' ? 'active' : '' ?>">
                    &#9632; Users
                </a>
            </li>
        <?php else: ?>
            <li class="nav-section">Menu</li>
            <li>
                <a href="<?= BASE_URL ?>/staff/index.php"
                   class="<?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>">
                    &#9632; Dashboard
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/staff/inventory.php"
                   class="<?= ($current_page ?? '') === 'inventory' ? 'active' : '' ?>">
                    &#9632; View Inventory
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/staff/orders.php"
                   class="<?= ($current_page ?? '') === 'orders' ? 'active' : '' ?>">
                    &#9632; Orders
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/staff/damages.php"
                   class="<?= ($current_page ?? '') === 'damages' ? 'active' : '' ?>">
                    &#9632; Damages
                </a>
            </li>
        <?php endif; ?>
            <li style="margin-top: 20px;">
                <a href="<?= BASE_URL ?>/change-password.php"
                   class="<?= ($current_page ?? '') === 'change-password' ? 'active' : '' ?>"
                   style="color:#f0c060;">
                    &#9632; Change Password
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/logout.php" style="color:#e87171;">
                    &#9632; Logout
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        Logged in as<br>
        <strong><?= htmlspecialchars($_user_name) ?></strong>
        <span class="badge badge-<?= $_role ?>" style="margin-top:4px;"><?= ucfirst($_role) ?></span>
    </div>
</aside>
<!-- ── /SIDEBAR ─────────────────────────────────────────── -->

<main class="main-content">
    <div class="topbar">
        <h1><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
        <span class="user-pill"><?= htmlspecialchars($_user_name) ?> &bull; <?= ucfirst($_role) ?></span>
    </div>
