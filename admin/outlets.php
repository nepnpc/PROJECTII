<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_check.php';

$page_title   = 'Outlet Management';
$current_page = 'outlets';
$message = $error = '';

// ── DELETE ────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("UPDATE outlets SET is_active = 0 WHERE id = ?")->execute([(int)$_GET['delete']]);
    $message = 'Outlet deactivated.';
}

// ── ADD ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name        = trim($_POST['name'] ?? '');
    $bill_prefix = strtoupper(trim($_POST['bill_prefix'] ?? ''));
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');

    if ($name && $bill_prefix) {
        $pdo->prepare("INSERT INTO outlets (name, bill_prefix, phone, address) VALUES (?,?,?,?)")
            ->execute([$name, $bill_prefix, $phone, $address]);
        $message = 'Outlet added successfully.';
    } else {
        $error = 'Name and Bill Prefix are required.';
    }
}

// ── EDIT ──────────────────────────────────────────────────────
$edit_outlet = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM outlets WHERE id = ?");
    $s->execute([(int)$_GET['edit']]);
    $edit_outlet = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id          = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $bill_prefix = strtoupper(trim($_POST['bill_prefix'] ?? ''));
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');

    if ($name && $bill_prefix && $id) {
        $pdo->prepare("UPDATE outlets SET name=?, bill_prefix=?, phone=?, address=? WHERE id=?")
            ->execute([$name, $bill_prefix, $phone, $address, $id]);
        $message     = 'Outlet updated.';
        $edit_outlet = null;
    }
}

$outlets = $pdo->query("SELECT * FROM outlets ORDER BY is_active DESC, name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h3><?= $edit_outlet ? '&#9998; Edit Outlet' : '&#43; Add Outlet' ?></h3>
        <?php if ($edit_outlet): ?>
        <a href="<?= BASE_URL ?>/admin/outlets.php" class="btn btn-sm btn-secondary">Cancel</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $edit_outlet ? 'edit' : 'add' ?>">
            <?php if ($edit_outlet): ?>
            <input type="hidden" name="id" value="<?= $edit_outlet['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Outlet Name *</label>
                    <input type="text" name="name" placeholder="Vision Care Shop" required
                           value="<?= htmlspecialchars($edit_outlet['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Bill Prefix *</label>
                    <input type="text" name="bill_prefix" placeholder="VC" maxlength="10" required
                           value="<?= htmlspecialchars($edit_outlet['bill_prefix'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="98xxxxxxxx"
                           value="<?= htmlspecialchars($edit_outlet['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" placeholder="Kathmandu, Nepal"
                       value="<?= htmlspecialchars($edit_outlet['address'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <?= $edit_outlet ? 'Update Outlet' : 'Add Outlet' ?>
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>All Outlets (<?= count($outlets) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Bill Prefix</th><th>Phone</th><th>Address</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($outlets)): ?>
                <tr><td colspan="7" class="empty-state">No outlets yet.</td></tr>
            <?php else: $i = 1; foreach ($outlets as $o): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($o['name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($o['bill_prefix']) ?></code></td>
                    <td><?= htmlspecialchars($o['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($o['address'] ?? '—') ?></td>
                    <td>
                        <?php if ($o['is_active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-draft">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?edit=<?= $o['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <?php if ($o['is_active']): ?>
                        <a href="?delete=<?= $o['id'] ?>" class="btn btn-sm btn-danger"
                           data-confirm="Deactivate this outlet?">Deactivate</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
