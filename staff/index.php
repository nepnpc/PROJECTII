<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title   = 'Staff Dashboard';
$current_page = 'dashboard';

$uid = (int)$_SESSION['user_id'];

$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$today_orders = $pdo->query(
    "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();
$total_lenses = $pdo->query("SELECT COUNT(*) FROM lenses WHERE is_active = 1")->fetchColumn();
$open_orders  = $pdo->query(
    "SELECT COUNT(*) FROM orders WHERE status IN ('DRAFT','ACTIVE','READY')"
)->fetchColumn();

// My recent orders
$my_orders = $pdo->query("
    SELECT o.*, outlets.name AS outlet_name
    FROM orders o
    JOIN outlets ON o.outlet_id = outlets.id
    ORDER BY o.created_at DESC LIMIT 5
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $today_orders ?></div>
        <div class="stat-label">Today's Orders</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-number"><?= $open_orders ?></div>
        <div class="stat-label">Open Orders</div>
    </div>
    <div class="stat-card green">
        <div class="stat-number"><?= $total_lenses ?></div>
        <div class="stat-label">Available Lenses</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_orders ?></div>
        <div class="stat-label">Total Orders</div>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h3>Quick Actions</h3>
    </div>
    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>/staff/orders.php?new=1" class="btn btn-primary">
            &#43; Create New Order
        </a>
        <a href="<?= BASE_URL ?>/staff/inventory.php" class="btn btn-outline">
            &#128269; Search Inventory
        </a>
        <a href="<?= BASE_URL ?>/staff/orders.php" class="btn btn-outline">
            &#128203; View All Orders
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Recent Orders</h3>
        <a href="<?= BASE_URL ?>/staff/orders.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Bill #</th><th>Outlet</th><th>Status</th><th>Amount</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php if (empty($my_orders)): ?>
                <tr><td colspan="5" class="empty-state">No orders yet. <a href="<?= BASE_URL ?>/staff/orders.php?new=1">Create one now &rarr;</a></td></tr>
            <?php else: foreach ($my_orders as $o): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($o['bill_number']) ?></strong></td>
                    <td><?= htmlspecialchars($o['outlet_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= $o['status'] ?></span></td>
                    <td>Rs. <?= number_format($o['total_amount'], 2) ?></td>
                    <td><?= date('Y-m-d', strtotime($o['created_at'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
