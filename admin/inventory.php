<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_check.php';

$page_title   = 'Inventory Management';
$current_page = 'inventory';
$message = $error = '';

// ── ADD STOCK ENTRY ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lens_id    = (int)($_POST['lens_id'] ?? 0);
    $sph        = (float)($_POST['sph'] ?? 0);
    $cyl        = (float)($_POST['cyl'] ?? 0);
    $axis       = (int)($_POST['axis'] ?? 0);
    $change_qty = (int)($_POST['change_qty'] ?? 0);
    $reason     = $_POST['reason'] ?? '';
    $notes      = trim($_POST['notes'] ?? '');

    $valid_reasons = ['PURCHASE','SALE','DAMAGE','RETURN','ADJUSTMENT','WASTAGE'];

    if ($lens_id && $change_qty !== 0 && in_array($reason, $valid_reasons)) {
        $pdo->prepare(
            "INSERT INTO inventory_ledger (lens_id, sph, cyl, axis, change_qty, reason, notes)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([$lens_id, $sph, $cyl, $axis, $change_qty, $reason, $notes]);
        $message = 'Stock entry added.';
        // Keep lens filter after add
        if (isset($_POST['back_lens_id'])) {
            header('Location: ?lens_id=' . (int)$_POST['back_lens_id']);
            exit;
        }
    } else {
        $error = 'Lens, quantity (non-zero) and reason are required.';
    }
}

// ── SELECTED LENS (drill-down view) ──────────────────────────
$selected_lens_id = isset($_GET['lens_id']) ? (int)$_GET['lens_id'] : 0;
$selected_lens    = null;
if ($selected_lens_id) {
    $s = $pdo->prepare("SELECT * FROM lenses WHERE id = ? AND is_active = 1");
    $s->execute([$selected_lens_id]);
    $selected_lens = $s->fetch();
}

// ── FETCH ALL LENSES (for left panel & dropdown) ─────────────
$lenses = $pdo->query(
    "SELECT l.id, l.brand, l.lens_index, l.material, l.wholesale_price,
            COALESCE(SUM(il.change_qty), 0) AS total_stock
     FROM lenses l
     LEFT JOIN inventory_ledger il ON l.id = il.lens_id
     WHERE l.is_active = 1
     GROUP BY l.id, l.brand, l.lens_index, l.material, l.wholesale_price
     ORDER BY l.brand, l.lens_index"
)->fetchAll();

// ── POWERS WITH STOCK (for left panel summary) ────────────────
$all_powers_raw = $pdo->query("
    SELECT lens_id, sph, cyl, SUM(change_qty) AS net_stock
    FROM inventory_ledger
    GROUP BY lens_id, sph, cyl
    HAVING SUM(change_qty) > 0
    ORDER BY lens_id, sph, cyl
")->fetchAll();
$powers_by_lens = [];
foreach ($all_powers_raw as $row) {
    $powers_by_lens[$row['lens_id']][] = number_format($row['sph'], 2) . '/' . number_format($row['cyl'], 2);
}

// ── STOCK PER POWER for selected lens ────────────────────────
$power_stock = [];
if ($selected_lens_id) {
    $power_stock = $pdo->prepare("
        SELECT sph, cyl, axis, SUM(change_qty) AS net_stock
        FROM inventory_ledger
        WHERE lens_id = ?
        GROUP BY sph, cyl, axis
        ORDER BY sph, cyl
    ");
    $power_stock->execute([$selected_lens_id]);
    $power_stock = $power_stock->fetchAll();
}

// ── LEDGER HISTORY ────────────────────────────────────────────
$where  = $selected_lens_id ? "WHERE il.lens_id = $selected_lens_id" : '';
$ledger = $pdo->query("
    SELECT il.*, l.brand, l.lens_index
    FROM inventory_ledger il
    JOIN lenses l ON il.lens_id = l.id
    $where
    ORDER BY il.created_at DESC
    LIMIT 60
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start;">

<!-- ── LEFT: LENS LIST ─────────────────────────────────────── -->
<div class="card">
    <div class="card-header"><h3>Lens Categories</h3></div>
    <div style="overflow-y:auto;max-height:600px;">
        <table style="width:100%;">
            <thead>
                <tr><th>Lens</th><th>Stock</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lenses as $l): ?>
                <?php
                    $stock = (int)$l['total_stock'];
                    $cls   = $stock <= 0 ? 'stock-zero' : ($stock < 5 ? 'stock-low' : 'stock-ok');
                    $active = $selected_lens_id === (int)$l['id'];
                ?>
                <tr style="<?= $active ? 'background:#eaf4fb;' : '' ?>">
                    <td>
                        <a href="?lens_id=<?= $l['id'] ?>" style="text-decoration:none;color:inherit;">
                            <strong><?= htmlspecialchars($l['brand']) ?></strong><br>
                            <small style="color:#888;"><?= htmlspecialchars($l['lens_index']) ?>
                            <?= htmlspecialchars($l['material'] ?? '') ?></small>
                        </a>
                    </td>
                    <td><span class="<?= $cls ?>"><?= $stock ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── RIGHT: DETAIL PANEL ─────────────────────────────────── -->
<div>

<?php if ($selected_lens): ?>
<!-- ── ADD STOCK FOR THIS LENS ────────────────────────────── -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h3>Add Stock — <?= htmlspecialchars($selected_lens['brand']) ?> <?= htmlspecialchars($selected_lens['lens_index']) ?></h3>
        <a href="<?= BASE_URL ?>/admin/inventory.php" class="btn btn-sm btn-secondary">&#8592; All Lenses</a>
    </div>
    <div class="card-body">
        <div class="algo-box">
            <strong>Ledger Algorithm (Append-Only):</strong>
            Never overwrite stock. Each change = new ledger row.
            Net stock = SUM(change_qty). PURCHASE = +qty, SALE/DAMAGE = &minus;qty.
        </div>
        <form method="POST">
            <input type="hidden" name="lens_id" value="<?= $selected_lens['id'] ?>">
            <input type="hidden" name="back_lens_id" value="<?= $selected_lens['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>SPH (Sphere)</label>
                    <input type="number" name="sph" step="0.25" value="0.00" placeholder="-2.00">
                </div>
                <div class="form-group">
                    <label>CYL (Cylinder)</label>
                    <input type="number" name="cyl" step="0.25" value="0.00" placeholder="-0.50">
                </div>
                <div class="form-group">
                    <label>Axis</label>
                    <input type="number" name="axis" min="0" max="180" value="0">
                </div>
                <div class="form-group">
                    <label>Qty Change * <small>(negative = remove)</small></label>
                    <input type="number" name="change_qty" required placeholder="+10 or -3">
                </div>
                <div class="form-group">
                    <label>Reason *</label>
                    <select name="reason" required>
                        <option value="">— Select —</option>
                        <option value="PURCHASE">PURCHASE (+)</option>
                        <option value="RETURN">RETURN (+)</option>
                        <option value="SALE">SALE (−)</option>
                        <option value="DAMAGE">DAMAGE (−)</option>
                        <option value="WASTAGE">WASTAGE (−)</option>
                        <option value="ADJUSTMENT">ADJUSTMENT</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" placeholder="Optional note">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Stock Entry</button>
        </form>
    </div>
</div>

<!-- ── STOCK BY POWER ────────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <h3>Current Stock by Power</h3>
        <span style="font-size:0.8rem;color:#888;">
            <?= htmlspecialchars($selected_lens['brand']) ?> <?= htmlspecialchars($selected_lens['lens_index']) ?> &bull;
            Wholesale: Rs. <?= number_format($selected_lens['wholesale_price'], 2) ?>
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>SPH</th><th>CYL</th><th>Axis</th><th>Net Stock</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if (empty($power_stock)): ?>
                <tr><td colspan="5" class="empty-state">No stock entries yet. Add stock above.</td></tr>
            <?php else: foreach ($power_stock as $p):
                $qty = (int)$p['net_stock'];
                $cls = $qty <= 0 ? 'stock-zero' : ($qty < 5 ? 'stock-low' : 'stock-ok');
            ?>
                <tr>
                    <td><?= number_format($p['sph'], 2) ?></td>
                    <td><?= number_format($p['cyl'], 2) ?></td>
                    <td><?= $p['axis'] ?>°</td>
                    <td><strong class="<?= $cls ?>"><?= $qty ?> units</strong></td>
                    <td>
                        <?php if ($qty <= 0): ?>
                            <span class="badge badge-closed">Out of Stock</span>
                        <?php elseif ($qty < 5): ?>
                            <span class="badge badge-damage">Low Stock</span>
                        <?php else: ?>
                            <span class="badge badge-active">In Stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- ── NO LENS SELECTED ──────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-body">
        <div class="algo-box">
            <strong>How to manage stock:</strong>
            Click any lens on the left &rarr; View stock by power &rarr; Add / adjust stock entries.
        </div>
        <p style="color:#888;text-align:center;padding:30px 0;">
            &#8592; Select a lens from the left panel to view and manage its stock.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- ── LEDGER HISTORY ─────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h3>
            <?= $selected_lens
                ? 'Ledger: ' . htmlspecialchars($selected_lens['brand']) . ' ' . htmlspecialchars($selected_lens['lens_index'])
                : 'Recent Ledger Entries (All Lenses)'
            ?>
        </h3>
        <span style="font-size:0.8rem;color:#888;">Last 60 entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Lens</th><th>SPH</th><th>CYL</th><th>Axis</th><th>Qty</th><th>Reason</th><th>Notes</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php if (empty($ledger)): ?>
                <tr><td colspan="9" class="empty-state">No entries yet.</td></tr>
            <?php else: $i = 1; foreach ($ledger as $e): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>
                        <a href="?lens_id=<?= $e['lens_id'] ?>" style="text-decoration:none;">
                            <?= htmlspecialchars($e['brand']) ?> <?= htmlspecialchars($e['lens_index']) ?>
                        </a>
                    </td>
                    <td><?= number_format($e['sph'], 2) ?></td>
                    <td><?= number_format($e['cyl'], 2) ?></td>
                    <td><?= $e['axis'] ?>°</td>
                    <td>
                        <strong style="color:<?= $e['change_qty'] > 0 ? '#27ae60' : '#e74c3c' ?>">
                            <?= $e['change_qty'] > 0 ? '+' : '' ?><?= $e['change_qty'] ?>
                        </strong>
                    </td>
                    <td><span class="badge badge-<?= strtolower($e['reason']) ?>"><?= $e['reason'] ?></span></td>
                    <td><?= htmlspecialchars($e['notes'] ?? '—') ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($e['created_at'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div><!-- /.right -->
</div><!-- /.grid -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
