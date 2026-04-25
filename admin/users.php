<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_check.php';

$page_title   = 'User Management';
$current_page = 'users';
$message = $error = '';

// ── TOGGLE ACTIVE ─────────────────────────────────────────────
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    if ($uid !== (int)$_SESSION['user_id']) { // can't deactivate self
        $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?")->execute([$uid]);
        $message = 'User status updated.';
    } else {
        $error = 'You cannot deactivate your own account.';
    }
}

// ── ADD USER ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';

    if ($name && $email && $password) {
        // Check duplicate email
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Email already exists.';
        } else {
            $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?, MD5(?),?)")
                ->execute([$name, $email, $password, $role]);
            $message = 'User created. Login: ' . $email . ' / ' . $password;
        }
    } else {
        $error = 'Name, Email and Password are required.';
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── ADD USER FORM ─────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>&#43; Add New User</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required placeholder="Ram Sharma">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required placeholder="ram@optical.com">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="text" name="password" required placeholder="Temporary password">
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </div>
</div>

<!-- ── USERS TABLE ───────────────────────────────────────────── -->
<div class="card">
    <div class="card-header"><h3>All Users (<?= count($users) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php $i = 1; foreach ($users as $u): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-closed">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <a href="?toggle=<?= $u['id'] ?>"
                           class="btn btn-sm <?= $u['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                           data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?">
                           <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </a>
                        <?php else: ?>
                        <span style="color:#aaa;font-size:0.8rem;">(you)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
