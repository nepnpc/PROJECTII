<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title   = 'Orders';
$current_page = 'orders';
$message = $error = '';

// ── FETCH OUTLETS & LENSES ────────────────────────────────────
$outlets = $pdo->query("SELECT * FROM outlets WHERE is_active = 1 ORDER BY name")->fetchAll();
$lenses  = $pdo->query(
    "SELECT id, brand, lens_index, wholesale_price, fitting_fee
     FROM lenses WHERE is_active = 1 ORDER BY brand, lens_index"
)->fetchAll();

// ── CREATE ORDER ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_order') {
    $outlet_id   = (int)($_POST['outlet_id'] ?? 0);
    $bill_number = trim($_POST['bill_number'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $lens_ids    = $_POST['lens_id']         ?? [];
    $re_sphs     = $_POST['re_sph']          ?? [];
    $re_cyls     = $_POST['re_cyl']          ?? [];
    $le_sphs     = $_POST['le_sph']          ?? [];
    $le_cyls     = $_POST['le_cyl']          ?? [];
    $qtys        = $_POST['qty']             ?? [];
    $prices      = $_POST['price_at_sale']   ?? [];
    $fittings    = $_POST['fitting_at_sale'] ?? [];

    if (!$outlet_id || !$bill_number) {
        $error = 'Outlet and Bill Number are required.';
    } elseif (empty($lens_ids)) {
        $error = 'Add at least one lens item.';
    } else {
        // Check duplicate bill number for same outlet
        $dup = $pdo->prepare("SELECT id FROM orders WHERE outlet_id = ? AND bill_number = ?");
        $dup->execute([$outlet_id, $bill_number]);
        if ($dup->fetch()) {
            $error = 'Bill number already exists for this outlet.';
        } else {
            // Build lens name lookup
            $lens_map = [];
            foreach ($lenses as $l) { $lens_map[(int)$l['id']] = $l; }

            // Stock validation — check RE power stock before inserting
            $stock_error = '';
            $sc_stmt = $pdo->prepare("
                SELECT COALESCE(SUM(change_qty), 0)
                FROM inventory_ledger
                WHERE lens_id = ? AND sph = ? AND cyl = ?
            ");
            for ($i = 0; $i < count($lens_ids); $i++) {
                $lid    = (int)($lens_ids[$i] ?? 0);
                if (!$lid) continue;
                $qty    = max(1, (int)($qtys[$i] ?? 1));
                $re_sph = (float)($re_sphs[$i] ?? 0);
                $re_cyl = (float)($re_cyls[$i] ?? 0);

                $sc_stmt->execute([$lid, $re_sph, $re_cyl]);
                $avail = (int)$sc_stmt->fetchColumn();

                if ($avail < $qty) {
                    $ln = $lens_map[$lid] ?? null;
                    $lname = $ln ? $ln['brand'] . ' ' . $ln['lens_index'] : "Lens #$lid";
                    $stock_error = "$lname — SPH " . number_format($re_sph, 2)
                                 . " / CYL " . number_format($re_cyl, 2)
                                 . ": only $avail in stock, $qty requested.";
                    break;
                }
            }

            if ($stock_error) {
                $error = 'Insufficient stock — ' . $stock_error;
            } else {
            // Calculate total
            $total = 0;
            for ($i = 0; $i < count($lens_ids); $i++) {
                $qty     = max(1, (int)($qtys[$i] ?? 1));
                $price   = (float)($prices[$i]   ?? 0);
                $fitting = (float)($fittings[$i] ?? 0);
                $total  += $qty * ($price + $fitting);
            }

            // Insert order
            $stmt = $pdo->prepare(
                "INSERT INTO orders (outlet_id, bill_number, status, total_amount, notes)
                 VALUES (?,?,'ACTIVE',?,?)"
            );
            $stmt->execute([$outlet_id, $bill_number, $total, $notes]);
            $order_id = (int)$pdo->lastInsertId();

            // Insert items
            $item_stmt = $pdo->prepare("
                INSERT INTO order_items
                    (order_id, lens_id, re_sph, re_cyl, le_sph, le_cyl, price_at_sale, fitting_at_sale, qty)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");

            for ($i = 0; $i < count($lens_ids); $i++) {
                $lid = (int)$lens_ids[$i];
                if (!$lid) continue;
                $qty     = max(1, (int)($qtys[$i]  ?? 1));
                $price   = (float)($prices[$i]      ?? 0);
                $fitting = (float)($fittings[$i]    ?? 0);
                $re_sph  = (float)($re_sphs[$i]     ?? 0);
                $re_cyl  = (float)($re_cyls[$i]     ?? 0);
                $le_sph  = (float)($le_sphs[$i]     ?? 0);
                $le_cyl  = (float)($le_cyls[$i]     ?? 0);

                $item_stmt->execute([
                    $order_id, $lid,
                    $re_sph, $re_cyl, $le_sph, $le_cyl,
                    $price, $fitting, $qty
                ]);

                // Auto-add SALE entry to inventory ledger
                $pdo->prepare(
                    "INSERT INTO inventory_ledger (lens_id, sph, cyl, change_qty, reason, notes)
                     VALUES (?,?,?,?,'SALE',?)"
                )->execute([$lid, $re_sph, $re_cyl, -$qty, 'Order ' . $bill_number]);
            }

            $message = 'Order ' . $bill_number . ' created successfully! Total: Rs. ' . number_format($total, 2);
            } // end stock-ok else
        }
    }
}

// ── FETCH ALL ORDERS (for list + JS bubble sort) ──────────────
$all_orders = $pdo->query("
    SELECT o.*, outlets.name AS outlet_name,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o
    JOIN outlets ON o.outlet_id = outlets.id
    ORDER BY o.created_at DESC
")->fetchAll();

$orders_json = json_encode(array_values($all_orders));
$lenses_json = json_encode(array_values($lenses));

// Per-power stock for client-side hints
$power_stock_raw = $pdo->query("
    SELECT lens_id, sph, cyl, SUM(change_qty) AS net_qty
    FROM inventory_ledger
    GROUP BY lens_id, sph, cyl
    HAVING SUM(change_qty) > 0
")->fetchAll();
$power_stock_json = json_encode(array_values($power_stock_raw));

$show_form = isset($_GET['new']);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── TOGGLE NEW ORDER FORM ──────────────────────────────────── -->
<div style="margin-bottom:18px;">
    <a href="<?= BASE_URL ?>/staff/orders.php<?= $show_form ? '' : '?new=1' ?>"
       class="btn btn-primary">
        <?= $show_form ? '&#10005; Cancel' : '&#43; New Order' ?>
    </a>
</div>

<!-- ── CREATE ORDER FORM ──────────────────────────────────────── -->
<?php if ($show_form): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>&#43; Create New Order</h3></div>
    <div class="card-body">

        <?php if (empty($outlets)): ?>
        <div class="alert alert-warning">No active outlets found. Ask admin to add outlets first.</div>
        <?php else: ?>

        <form method="POST" id="order-form">
            <input type="hidden" name="action" value="create_order">
            <div class="form-row">
                <div class="form-group">
                    <label>Outlet *</label>
                    <select name="outlet_id" required>
                        <option value="">— Select Outlet —</option>
                        <?php foreach ($outlets as $out): ?>
                        <option value="<?= $out['id'] ?>"><?= htmlspecialchars($out['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bill Number *</label>
                    <input type="text" name="bill_number" required placeholder="e.g. VC-001">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" placeholder="Optional">
                </div>
            </div>

            <!-- Order Items -->
            <h4 style="margin:16px 0 10px;">Lens Items</h4>
            <div class="table-wrap">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Lens</th>
                            <th>RE SPH</th><th>RE CYL</th>
                            <th>LE SPH</th><th>LE CYL</th>
                            <th>Qty</th>
                            <th>Price (Rs.)</th>
                            <th>Fitting (Rs.)</th>
                            <th>Remove</th>
                        </tr>
                    </thead>
                    <tbody id="order-items-body"></tbody>
                </table>
            </div>

            <div style="margin-top:10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="addOrderItem()">
                    &#43; Add Lens Row
                </button>
            </div>

            <div class="total-bar" style="margin-top:14px;">
                Order Total: <span id="order-total">Rs. 0.00</span>
            </div>

            <div style="margin-top:14px;">
                <button type="submit" class="btn btn-success btn-lg">&#10003; Submit Order</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── ORDERS LIST WITH BUBBLE SORT ────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h3>All Orders</h3>
    </div>
    <div class="card-body" style="padding-bottom:0;">

        <div class="action-bar" style="margin-bottom:14px;">
            <label style="font-size:0.85rem;font-weight:600;">Sort By:</label>
            <select id="sort-field" onchange="doSort()">
                <option value="created_at">Date</option>
                <option value="total_amount">Amount</option>
                <option value="status">Status</option>
                <option value="outlet_name">Outlet</option>
            </select>
            <select id="sort-order" onchange="doSort()">
                <option value="desc">Descending</option>
                <option value="asc">Ascending</option>
            </select>
            <input type="search" id="order-search" placeholder="Search bill# or outlet..."
                   oninput="doSort()" style="max-width:220px;">
            <span id="order-count" style="color:#888;font-size:0.85rem;"></span>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Bill #</th><th>Outlet</th><th>Items</th><th>Status</th><th>Total</th><th>Date</th></tr>
            </thead>
            <tbody id="orders-table-body"></tbody>
        </table>
    </div>
</div>

<!-- ── INJECT DATA & RUN ALGORITHMS ──────────────────────────── -->
<script>
var allOrders  = <?= $orders_json ?>;
var lensData   = <?= $lenses_json ?>;   // used by addOrderItem() in main.js
var powerStock = <?= $power_stock_json ?>; // used by checkStock() in main.js

function doSort() {
    var field  = document.getElementById('sort-field').value;
    var order  = document.getElementById('sort-order').value;
    var query  = document.getElementById('order-search').value;

    // Step 1: Linear Search (filter)
    var searched = linearSearch(allOrders, query, ['bill_number', 'outlet_name']);

    // Step 2: Bubble Sort (sort)
    var sorted = bubbleSort(searched, field, order);

    // Step 3: Render
    renderTable('orders-table-body', sorted, function(o, idx) {
        var badge = '<span class="badge badge-' + o.status.toLowerCase() + '">' + o.status + '</span>';
        return '<tr>'
            + '<td>' + (idx + 1) + '</td>'
            + '<td><strong>' + escHtml(o.bill_number) + '</strong></td>'
            + '<td>' + escHtml(o.outlet_name) + '</td>'
            + '<td>' + o.item_count + ' item(s)</td>'
            + '<td>' + badge + '</td>'
            + '<td>Rs. ' + parseFloat(o.total_amount).toFixed(2) + '</td>'
            + '<td>' + o.created_at.substring(0,10) + '</td>'
            + '</tr>';
    });

    document.getElementById('order-count').textContent =
        sorted.length + ' of ' + allOrders.length + ' orders';
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;');
}

// Wait for algorithms.js to load (it's in footer, after this inline script)
document.addEventListener('DOMContentLoaded', function() { doSort(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
