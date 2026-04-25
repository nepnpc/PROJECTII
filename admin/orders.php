<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_check.php';

$page_title   = 'Order Management';
$current_page = 'orders';
$message = $error = '';

// ── UPDATE STATUS ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status   = $_POST['status'] ?? '';
    $valid    = ['DRAFT','ACTIVE','READY','CLOSED'];
    if ($order_id && in_array($status, $valid)) {
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $order_id]);
        $message = 'Order status updated to ' . $status . '.';
    }
}

// ── VIEW SINGLE ORDER ─────────────────────────────────────────
$view_order = null;
$order_items = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $s = $pdo->prepare("
        SELECT o.*, outlets.name AS outlet_name
        FROM orders o JOIN outlets ON o.outlet_id = outlets.id
        WHERE o.id = ?
    ");
    $s->execute([(int)$_GET['view']]);
    $view_order = $s->fetch();

    if ($view_order) {
        $si = $pdo->prepare("
            SELECT oi.*, l.brand, l.lens_index
            FROM order_items oi
            JOIN lenses l ON oi.lens_id = l.id
            WHERE oi.order_id = ?
        ");
        $si->execute([$view_order['id']]);
        $order_items = $si->fetchAll();
    }
}

// ── FILTER ────────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$where         = '';
$params        = [];
if (in_array($filter_status, ['DRAFT','ACTIVE','READY','CLOSED'])) {
    $where  = "WHERE o.status = ?";
    $params = [$filter_status];
}

$orders = $pdo->prepare("
    SELECT o.*, outlets.name AS outlet_name
    FROM orders o
    JOIN outlets ON o.outlet_id = outlets.id
    $where
    ORDER BY o.created_at DESC
");
$orders->execute($params);
$orders = $orders->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

<!-- ── VIEW SINGLE ORDER ─────────────────────────────────────── -->
<?php if ($view_order): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h3>Order: <?= htmlspecialchars($view_order['bill_number']) ?></h3>
        <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-sm btn-secondary">Back to List</a>
    </div>
    <div class="card-body">
        <div class="form-row" style="margin-bottom:16px;">
            <div><strong>Outlet:</strong> <?= htmlspecialchars($view_order['outlet_name']) ?></div>
            <div><strong>Status:</strong> <span class="badge badge-<?= strtolower($view_order['status']) ?>"><?= $view_order['status'] ?></span></div>
            <div><strong>Total:</strong> Rs. <?= number_format($view_order['total_amount'], 2) ?></div>
            <div><strong>Date:</strong> <?= date('Y-m-d H:i', strtotime($view_order['created_at'])) ?></div>
        </div>

        <?php if ($view_order['notes']): ?>
        <div class="alert alert-info" style="margin-bottom:16px;">
            <strong>Notes:</strong> <?= htmlspecialchars($view_order['notes']) ?>
        </div>
        <?php endif; ?>

        <!-- Update status form -->
        <form method="POST" style="margin-bottom:16px;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="<?= $view_order['id'] ?>">
            <div style="display:flex;gap:10px;align-items:center;">
                <label><strong>Change Status:</strong></label>
                <select name="status">
                    <option value="DRAFT"  <?= $view_order['status'] === 'DRAFT'  ? 'selected' : '' ?>>DRAFT</option>
                    <option value="ACTIVE" <?= $view_order['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                    <option value="READY"  <?= $view_order['status'] === 'READY'  ? 'selected' : '' ?>>READY</option>
                    <option value="CLOSED" <?= $view_order['status'] === 'CLOSED' ? 'selected' : '' ?>>CLOSED</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Update</button>
            </div>
        </form>

        <h4 style="margin-bottom:10px;">Order Items</h4>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Lens</th>
                        <th>RE SPH</th><th>RE CYL</th>
                        <th>LE SPH</th><th>LE CYL</th>
                        <th>Qty</th><th>Price</th><th>Fitting</th><th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($order_items as $item): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($item['brand']) ?> <?= htmlspecialchars($item['lens_index']) ?></td>
                        <td><?= number_format($item['re_sph'], 2) ?></td>
                        <td><?= number_format($item['re_cyl'], 2) ?></td>
                        <td><?= number_format($item['le_sph'], 2) ?></td>
                        <td><?= number_format($item['le_cyl'], 2) ?></td>
                        <td><?= $item['qty'] ?></td>
                        <td>Rs. <?= number_format($item['price_at_sale'], 2) ?></td>
                        <td>Rs. <?= number_format($item['fitting_at_sale'], 2) ?></td>
                        <td>Rs. <?= number_format(($item['price_at_sale'] + $item['fitting_at_sale']) * $item['qty'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr>
                        <td colspan="9" style="text-align:right;font-weight:bold;">Grand Total:</td>
                        <td><strong>Rs. <?= number_format($view_order['total_amount'], 2) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── ORDERS LIST ───────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h3>All Orders (<?= count($orders) ?>)</h3>
        <form method="GET" style="display:flex;gap:8px;">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['DRAFT','ACTIVE','READY','CLOSED'] as $s): ?>
                <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline">Filter</button>
            <?php if ($filter_status): ?>
            <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-sm btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Bill #</th><th>Outlet</th><th>Status</th><th>Total</th><th>Notes</th><th>Date</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="empty-state">No orders found.</td></tr>
            <?php else: $i = 1; foreach ($orders as $o): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($o['bill_number']) ?></strong></td>
                    <td><?= htmlspecialchars($o['outlet_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($o['status']) ?>"><?= $o['status'] ?></span></td>
                    <td>Rs. <?= number_format($o['total_amount'], 2) ?></td>
                    <td><?= htmlspecialchars($o['notes'] ?? '—') ?></td>
                    <td><?= date('Y-m-d', strtotime($o['created_at'])) ?></td>
                    <td>
                        <a href="?view=<?= $o['id'] ?>" class="btn btn-sm btn-primary">View</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
