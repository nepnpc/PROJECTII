<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Setup — Optical Ledger</title>
<style>
body { font-family: 'Segoe UI', sans-serif; background: #1e2a3a; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
.box { background: #fff; padding: 40px; border-radius: 10px; width: 650px; box-shadow: 0 10px 40px rgba(0,0,0,0.4); }
h1 { font-size: 1.4rem; margin-bottom: 6px; }
p  { color: #666; margin-bottom: 24px; font-size: 0.9rem; }
.step { padding: 10px 14px; border-radius: 5px; margin-bottom: 8px; font-size: 0.875rem; }
.ok   { background: #d5f5e3; color: #1e8449; border: 1px solid #a9dfbf; }
.fail { background: #fde8e8; color: #922b21; border: 1px solid #f5b7b1; }
.info { background: #d6eaf8; color: #1a5276; border: 1px solid #a9cce3; }
.btn  { display: inline-block; padding: 12px 28px; background: #2980b9; color: #fff; border-radius: 5px; text-decoration: none; font-weight: 600; margin-top: 16px; font-size: 0.95rem; }
code  { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>
</head>
<body>
<div class="box">
<h1>&#128065; Optical Ledger — Database Setup</h1>
<p>This script creates the database, tables, and inserts demo data.</p>

<?php
$host   = '127.0.0.1';
$dbuser = 'root';
$dbpass = '';
$dbname = 'optical_ledger';

$steps  = [];
$failed = false;

// ── STEP 1: Connect WITHOUT selecting DB ────────────────────
try {
    $pdo = new PDO(
        "mysql:host=$host;port=3307;charset=utf8",
        $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $steps[] = ['ok', 'MySQL connection successful.'];
} catch (PDOException $e) {
    $steps[] = ['fail', 'Cannot connect to MySQL: ' . $e->getMessage()];
    $failed  = true;
}

// ── STEP 2: Create Database ──────────────────────────────────
if (!$failed) {
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8 COLLATE utf8_general_ci");
        $pdo->exec("USE `$dbname`");
        $steps[] = ['ok', "Database <code>$dbname</code> created / selected."];
    } catch (PDOException $e) {
        $steps[] = ['fail', 'Create database failed: ' . $e->getMessage()];
        $failed  = true;
    }
}

// ── STEP 3: Create Tables ─────────────────────────────────────
if (!$failed) {
    $tables = [
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            email      VARCHAR(150) NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            role       ENUM('admin','staff') DEFAULT 'staff',
            is_active  TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "outlets" => "CREATE TABLE IF NOT EXISTS outlets (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(150) NOT NULL,
            bill_prefix VARCHAR(10)  NOT NULL,
            phone       VARCHAR(20),
            address     TEXT,
            is_active   TINYINT(1) DEFAULT 1,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "lenses" => "CREATE TABLE IF NOT EXISTS lenses (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            brand           VARCHAR(100) NOT NULL,
            lens_index      VARCHAR(20)  NOT NULL,
            material        VARCHAR(100),
            base_cost       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            wholesale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            mrp             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            fitting_fee     DECIMAL(10,2) DEFAULT 0.00,
            is_active       TINYINT(1) DEFAULT 1,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "inventory_ledger" => "CREATE TABLE IF NOT EXISTS inventory_ledger (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            lens_id    INT NOT NULL,
            sph        DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            cyl        DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            axis       INT DEFAULT 0,
            change_qty INT NOT NULL,
            reason     ENUM('PURCHASE','SALE','DAMAGE','RETURN','ADJUSTMENT','WASTAGE') NOT NULL,
            notes      TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lens_id) REFERENCES lenses(id)
        )",
        "orders" => "CREATE TABLE IF NOT EXISTS orders (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            outlet_id    INT NOT NULL,
            bill_number  VARCHAR(50) NOT NULL,
            status       ENUM('DRAFT','ACTIVE','READY','CLOSED') DEFAULT 'DRAFT',
            total_amount DECIMAL(10,2) DEFAULT 0.00,
            notes        TEXT,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (outlet_id) REFERENCES outlets(id)
        )",
        "order_items" => "CREATE TABLE IF NOT EXISTS order_items (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            order_id        INT NOT NULL,
            lens_id         INT NOT NULL,
            re_sph          DECIMAL(5,2) DEFAULT 0.00,
            re_cyl          DECIMAL(5,2) DEFAULT 0.00,
            re_axis         INT DEFAULT 0,
            le_sph          DECIMAL(5,2) DEFAULT 0.00,
            le_cyl          DECIMAL(5,2) DEFAULT 0.00,
            le_axis         INT DEFAULT 0,
            price_at_sale   DECIMAL(10,2) NOT NULL,
            fitting_at_sale DECIMAL(10,2) DEFAULT 0.00,
            qty             INT DEFAULT 1,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id),
            FOREIGN KEY (lens_id)  REFERENCES lenses(id)
        )",
    ];

    foreach ($tables as $name => $sql) {
        try {
            $pdo->exec($sql);
            $steps[] = ['ok', "Table <code>$name</code> created."];
        } catch (PDOException $e) {
            $steps[] = ['fail', "Table <code>$name</code> failed: " . $e->getMessage()];
            $failed  = true;
        }
    }
}

// ── STEP 3b: Migrations (add columns to existing installs) ───────
if (!$failed) {
    $migrations = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) DEFAULT 1",
        "UPDATE users SET must_change_password = 0 WHERE role = 'admin'",
        "UPDATE users SET must_change_password = 1 WHERE role = 'staff' AND must_change_password IS NULL",
    ];
    foreach ($migrations as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Non-fatal: column may already exist
        }
    }
    $steps[] = ['ok', 'Column <code>must_change_password</code> ensured on users table.'];
}

// ── STEP 4: Seed Data ─────────────────────────────────────────
if (!$failed) {
    $existing = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    if ($existing > 0) {
        $steps[] = ['info', "Seed data already exists ($existing users found). Skipping."];
    } else {
        try {
            $pdo->exec("INSERT INTO users (name,email,password,role) VALUES
                ('Admin User',  'admin@optical.com', MD5('admin123'), 'admin'),
                ('Staff User',  'staff@optical.com', MD5('staff123'), 'staff'),
                ('Ram Sharma',  'ram@optical.com',   MD5('staff123'), 'staff')
            ");
            $pdo->exec("INSERT INTO outlets (name,bill_prefix,phone,address) VALUES
                ('Vision Care Outlet', 'VC', '9841000001', 'Kathmandu, Nepal'),
                ('Bright Eyes Shop',   'BE', '9841000002', 'Lalitpur, Nepal'),
                ('Clear View Optics',  'CV', '9841000003', 'Bhaktapur, Nepal')
            ");
            $pdo->exec("INSERT INTO lenses (brand,lens_index,material,base_cost,wholesale_price,mrp,fitting_fee) VALUES
                ('Essilor','1.50','CR-39',        350.00, 550.00,  900.00, 100.00),
                ('Essilor','1.56','CR-39',        420.00, 650.00, 1100.00, 100.00),
                ('Essilor','1.60','Polycarbonate',550.00, 850.00, 1400.00, 120.00),
                ('Essilor','1.67','Polycarbonate',750.00,1150.00, 1900.00, 150.00),
                ('Zeiss',  '1.50','CR-39',        500.00, 800.00, 1300.00, 100.00),
                ('Zeiss',  '1.60','Polycarbonate',750.00,1150.00, 1800.00, 120.00),
                ('Nikon',  '1.50','CR-39',        450.00, 700.00, 1150.00, 100.00),
                ('Nikon',  '1.56','CR-39',        520.00, 800.00, 1300.00, 100.00),
                ('Hoya',   '1.50','CR-39',        480.00, 730.00, 1200.00, 100.00),
                ('Hoya',   '1.67','Polycarbonate',800.00,1200.00, 2000.00, 150.00)
            ");
            $pdo->exec("INSERT INTO inventory_ledger (lens_id,sph,cyl,axis,change_qty,reason,notes) VALUES
                (1,-1.00,-0.50,90, 20,'PURCHASE','Initial stock'),
                (1,-2.00,-0.75,180,15,'PURCHASE','Initial stock'),
                (2,-1.50, 0.00,0,  25,'PURCHASE','Initial stock'),
                (3,-3.00,-1.00,90, 10,'PURCHASE','Initial stock'),
                (4,-4.00,-1.25,180, 8,'PURCHASE','Initial stock'),
                (5, 0.00, 0.00,0,  30,'PURCHASE','Initial stock'),
                (6,-2.50,-0.50,90, 12,'PURCHASE','Initial stock')
            ");
            $steps[] = ['ok', 'Demo data seeded: 3 users, 3 outlets, 10 lenses, inventory entries.'];
        } catch (PDOException $e) {
            $steps[] = ['fail', 'Seed data failed: ' . $e->getMessage()];
        }
    }
}

// ── Output ────────────────────────────────────────────────────
foreach ($steps as [$type, $msg]) {
    $icon = $type === 'ok' ? '&#10003;' : ($type === 'fail' ? '&#10007;' : 'ℹ');
    echo "<div class=\"step $type\">$icon &nbsp;$msg</div>";
}

if (!$failed):
?>
    <div class="step ok" style="margin-top:16px;font-weight:bold;">
        &#10003;&nbsp; Setup complete! Database is ready.
    </div>
    <a href="/optical-ledger/login.php" class="btn">&#128065; Open Optical Ledger</a>
    <p style="margin-top:16px;font-size:0.8rem;color:#aaa;">
        Delete or rename <code>setup.php</code> after first use for security.
    </p>
<?php else: ?>
    <div class="step fail" style="margin-top:16px;font-weight:bold;">
        &#10007;&nbsp; Setup failed. Check that XAMPP MySQL is running.
    </div>
    <p style="margin-top:12px;font-size:0.85rem;color:#666;">
        Open XAMPP Control Panel &rarr; Click <strong>Start</strong> next to MySQL &rarr; Refresh this page.
    </p>
<?php endif; ?>

</div>
</body>
</html>
