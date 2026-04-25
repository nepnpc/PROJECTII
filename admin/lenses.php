<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_check.php';

$page_title   = 'Lens Management';
$current_page = 'lenses';
$message = $error = '';

// ── DELETE (soft) ────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("UPDATE lenses SET is_active = 0 WHERE id = ?")->execute([(int)$_GET['delete']]);
    $message = 'Lens deactivated successfully.';
}

// ── ADD ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $brand           = trim($_POST['brand'] ?? '');
    $lens_index      = trim($_POST['lens_index'] ?? '');
    $material        = trim($_POST['material'] ?? '');
    $base_cost       = (float)($_POST['base_cost'] ?? 0);
    $wholesale_price = (float)($_POST['wholesale_price'] ?? 0);
    $mrp             = (float)($_POST['mrp'] ?? 0);
    $fitting_fee     = (float)($_POST['fitting_fee'] ?? 0);

    if ($brand && $lens_index && $base_cost > 0 && $wholesale_price > 0 && $mrp > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO lenses (brand, lens_index, material, base_cost, wholesale_price, mrp, fitting_fee)
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->execute([$brand, $lens_index, $material, $base_cost, $wholesale_price, $mrp, $fitting_fee]);
        $message = 'Lens added successfully.';
    } else {
        $error = 'Brand, Index and all prices (> 0) are required.';
    }
}

// ── EDIT ─────────────────────────────────────────────────────
$edit_lens = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM lenses WHERE id = ? AND is_active = 1");
    $s->execute([(int)$_GET['edit']]);
    $edit_lens = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id              = (int)($_POST['id'] ?? 0);
    $brand           = trim($_POST['brand'] ?? '');
    $lens_index      = trim($_POST['lens_index'] ?? '');
    $material        = trim($_POST['material'] ?? '');
    $base_cost       = (float)($_POST['base_cost'] ?? 0);
    $wholesale_price = (float)($_POST['wholesale_price'] ?? 0);
    $mrp             = (float)($_POST['mrp'] ?? 0);
    $fitting_fee     = (float)($_POST['fitting_fee'] ?? 0);

    if ($brand && $lens_index && $id) {
        $pdo->prepare(
            "UPDATE lenses SET brand=?, lens_index=?, material=?, base_cost=?,
             wholesale_price=?, mrp=?, fitting_fee=? WHERE id=?"
        )->execute([$brand, $lens_index, $material, $base_cost, $wholesale_price, $mrp, $fitting_fee, $id]);
        $message   = 'Lens updated successfully.';
        $edit_lens = null;
    } else {
        $error = 'All required fields must be filled.';
    }
}

// ── FETCH ALL ────────────────────────────────────────────────
$lenses = $pdo->query(
    "SELECT * FROM lenses WHERE is_active = 1 ORDER BY brand, lens_index"
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── ADD / EDIT FORM ──────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h3><?= $edit_lens ? '&#9998; Edit Lens' : '&#43; Add New Lens' ?></h3>
        <?php if ($edit_lens): ?>
        <a href="<?= BASE_URL ?>/admin/lenses.php" class="btn btn-sm btn-secondary">Cancel Edit</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $edit_lens ? 'edit' : 'add' ?>">
            <?php if ($edit_lens): ?>
            <input type="hidden" name="id" value="<?= $edit_lens['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Brand *</label>
                    <input type="text" name="brand" placeholder="e.g. Essilor, Zeiss, Nikon" required
                           value="<?= htmlspecialchars($edit_lens['brand'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Lens Index *</label>
                    <input type="text" name="lens_index" placeholder="e.g. 1.50, 1.56, 1.67" required
                           value="<?= htmlspecialchars($edit_lens['lens_index'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Material</label>
                    <input type="text" name="material" placeholder="e.g. CR-39, Polycarbonate"
                           value="<?= htmlspecialchars($edit_lens['material'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Base Cost (Rs.) *</label>
                    <input type="number" name="base_cost" step="0.01" min="0" required
                           value="<?= $edit_lens['base_cost'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Wholesale Price (Rs.) *</label>
                    <input type="number" name="wholesale_price" step="0.01" min="0" required
                           value="<?= $edit_lens['wholesale_price'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>MRP (Rs.) *</label>
                    <input type="number" name="mrp" step="0.01" min="0" required
                           value="<?= $edit_lens['mrp'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Default Fitting Fee (Rs.)</label>
                    <input type="number" name="fitting_fee" step="0.01" min="0"
                           value="<?= $edit_lens['fitting_fee'] ?? '0' ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $edit_lens ? 'Update Lens' : 'Add Lens' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── LENSES TABLE ─────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h3>All Lenses (<?= count($lenses) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Brand</th>
                    <th>Index</th>
                    <th>Material</th>
                    <th>Base Cost</th>
                    <th>Wholesale</th>
                    <th>MRP</th>
                    <th>Fitting Fee</th>
                    <th>Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($lenses)): ?>
                <tr><td colspan="10" class="empty-state">No lenses added yet. Use the form above.</td></tr>
            <?php else: $i = 1; foreach ($lenses as $l): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($l['brand']) ?></strong></td>
                    <td><?= htmlspecialchars($l['lens_index']) ?></td>
                    <td><?= htmlspecialchars($l['material']) ?></td>
                    <td>Rs. <?= number_format($l['base_cost'], 2) ?></td>
                    <td>Rs. <?= number_format($l['wholesale_price'], 2) ?></td>
                    <td>Rs. <?= number_format($l['mrp'], 2) ?></td>
                    <td>Rs. <?= number_format($l['fitting_fee'], 2) ?></td>
                    <td><?= date('Y-m-d', strtotime($l['created_at'])) ?></td>
                    <td>
                        <a href="?edit=<?= $l['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="?delete=<?= $l['id'] ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="Deactivate this lens? It will be hidden from orders.">Delete</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
