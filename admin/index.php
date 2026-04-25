<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_check.php';

$page_title   = 'Dashboard';
$current_page = 'dashboard';

// ── STATS ────────────────────────────────────────────────────
$total_lenses   = $pdo->query("SELECT COUNT(*) FROM lenses WHERE is_active = 1")->fetchColumn();
$total_outlets  = $pdo->query("SELECT COUNT(*) FROM outlets WHERE is_active = 1")->fetchColumn();
$total_orders   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$active_orders  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('DRAFT','ACTIVE','READY')")->fetchColumn();
$total_staff    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff' AND is_active = 1")->fetchColumn();
$total_stock    = $pdo->query("SELECT COALESCE(SUM(change_qty),0) FROM inventory_ledger")->fetchColumn();

// ── RECENT ORDERS ────────────────────────────────────────────
$recent_orders = $pdo->query("
    SELECT o.*, u.name AS outlet_name
    FROM orders o
    JOIN outlets u ON o.outlet_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 8
")->fetchAll();

// ── LOW STOCK (lenses with net stock < 5) ────────────────────
$low_stock = $pdo->query("
    SELECT l.brand, l.lens_index,
           COALESCE(SUM(il.change_qty), 0) AS net_stock
    FROM lenses l
    LEFT JOIN inventory_ledger il ON l.id = il.lens_id
    WHERE l.is_active = 1
    GROUP BY l.id, l.brand, l.lens_index
    HAVING net_stock < 5
    ORDER BY net_stock ASC
    LIMIT 5
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── STATS CARDS ──────────────────────────────────────────── -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $total_lenses ?></div>
        <div class="stat-label">Active Lenses</div>
    </div>
    <div class="stat-card green">
        <div class="stat-number"><?= $total_outlets ?></div>
        <div class="stat-label">Active Outlets</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-number"><?= $active_orders ?></div>
        <div class="stat-label">Open Orders</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_orders ?></div>
        <div class="stat-label">Total Orders</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-number"><?= $total_staff ?></div>
        <div class="stat-label">Staff Members</div>
    </div>
    <div class="stat-card <?= $low_stock ? 'red' : 'green' ?>">
        <div class="stat-number"><?= count($low_stock) ?></div>
        <div class="stat-label">Low Stock Items</div>
    </div>
</div>

<!-- ── LOW STOCK ALERT ──────────────────────────────────────── -->
<?php if (!empty($low_stock)): ?>
<div class="alert alert-warning" style="margin-bottom:20px;">
    <strong>&#9888; Low Stock Alert:</strong>
    <?php foreach ($low_stock as $ls): ?>
        <span><?= htmlspecialchars($ls['brand']) ?> <?= htmlspecialchars($ls['lens_index']) ?> (<?= $ls['net_stock'] ?> units)</span>;
    <?php endforeach; ?>
    &mdash; <a href="<?= BASE_URL ?>/admin/inventory.php">Manage Inventory &rarr;</a>
</div>
<?php endif; ?>

<!-- ── RECENT ORDERS TABLE ──────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h3>Recent Orders</h3>
        <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Outlet</th>
                    <th>Status</th>
                    <th>Total Amount</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recent_orders)): ?>
                <tr><td colspan="6" class="empty-state">No orders yet. Create one from the Staff panel.</td></tr>
            <?php else: foreach ($recent_orders as $o): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($o['bill_number']) ?></strong></td>
                    <td><?= htmlspecialchars($o['outlet_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= $o['status'] ?></span></td>
                    <td>Rs. <?= number_format($o['total_amount'], 2) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($o['created_at'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/orders.php?view=<?= $o['id'] ?>"
                           class="btn btn-sm btn-primary">View</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
