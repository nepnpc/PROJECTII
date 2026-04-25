-- ============================================================
-- Optical Ledger — Database Schema
-- Import via phpMyAdmin or: mysql -u root optical_ledger < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS optical_ledger CHARACTER SET utf8 COLLATE utf8_general_ci;
USE optical_ledger;

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','staff') DEFAULT 'staff',
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── OUTLETS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS outlets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    bill_prefix VARCHAR(10)  NOT NULL,
    phone       VARCHAR(20),
    address     TEXT,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── LENSES ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lenses (
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
);

-- ── INVENTORY LEDGER (append-only stock ledger) ───────────────
CREATE TABLE IF NOT EXISTS inventory_ledger (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    lens_id     INT NOT NULL,
    sph         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    cyl         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    axis        INT DEFAULT 0,
    change_qty  INT NOT NULL,
    reason      ENUM('PURCHASE','SALE','DAMAGE','RETURN','ADJUSTMENT','WASTAGE') NOT NULL,
    notes       TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lens_id) REFERENCES lenses(id)
);

-- ── ORDERS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    outlet_id    INT NOT NULL,
    bill_number  VARCHAR(50) NOT NULL,
    status       ENUM('DRAFT','ACTIVE','READY','CLOSED') DEFAULT 'DRAFT',
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    notes        TEXT,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (outlet_id) REFERENCES outlets(id)
);

-- ── ORDER ITEMS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    order_id       INT NOT NULL,
    lens_id        INT NOT NULL,
    re_sph         DECIMAL(5,2) DEFAULT 0.00,
    re_cyl         DECIMAL(5,2) DEFAULT 0.00,
    re_axis        INT DEFAULT 0,
    le_sph         DECIMAL(5,2) DEFAULT 0.00,
    le_cyl         DECIMAL(5,2) DEFAULT 0.00,
    le_axis        INT DEFAULT 0,
    price_at_sale  DECIMAL(10,2) NOT NULL,
    fitting_at_sale DECIMAL(10,2) DEFAULT 0.00,
    qty            INT DEFAULT 1,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (lens_id)  REFERENCES lenses(id)
);

-- ── SEED DATA ────────────────────────────────────────────────

-- Default users (passwords stored as MD5)
-- admin123 → MD5 → 0192023a7bbd73250516f069df18b500
-- staff123 → MD5 → 78de9cb7c0a1571dde01b65deb4b05d4
INSERT INTO users (name, email, password, role) VALUES
('Admin User',  'admin@optical.com', MD5('admin123'), 'admin'),
('Staff User',  'staff@optical.com', MD5('staff123'), 'staff'),
('Ram Sharma',  'ram@optical.com',   MD5('staff123'), 'staff');

-- Sample outlets
INSERT INTO outlets (name, bill_prefix, phone, address) VALUES
('Vision Care Outlet',  'VC',  '9841000001', 'Kathmandu, Nepal'),
('Bright Eyes Shop',    'BE',  '9841000002', 'Lalitpur, Nepal'),
('Clear View Optics',   'CV',  '9841000003', 'Bhaktapur, Nepal');

-- Sample lenses
INSERT INTO lenses (brand, lens_index, material, base_cost, wholesale_price, mrp, fitting_fee) VALUES
('Essilor',     '1.50', 'CR-39',         350.00,  550.00,  900.00,  100.00),
('Essilor',     '1.56', 'CR-39',         420.00,  650.00,  1100.00, 100.00),
('Essilor',     '1.60', 'Polycarbonate', 550.00,  850.00,  1400.00, 120.00),
('Essilor',     '1.67', 'Polycarbonate', 750.00,  1150.00, 1900.00, 150.00),
('Zeiss',       '1.50', 'CR-39',         500.00,  800.00,  1300.00, 100.00),
('Zeiss',       '1.60', 'Polycarbonate', 750.00,  1150.00, 1800.00, 120.00),
('Nikon',       '1.50', 'CR-39',         450.00,  700.00,  1150.00, 100.00),
('Nikon',       '1.56', 'CR-39',         520.00,  800.00,  1300.00, 100.00),
('Hoya',        '1.50', 'CR-39',         480.00,  730.00,  1200.00, 100.00),
('Hoya',        '1.67', 'Polycarbonate', 800.00,  1200.00, 2000.00, 150.00);

-- Sample inventory
INSERT INTO inventory_ledger (lens_id, sph, cyl, axis, change_qty, reason, notes) VALUES
(1, -1.00, -0.50, 90,  20, 'PURCHASE', 'Initial stock'),
(1, -2.00, -0.75, 180, 15, 'PURCHASE', 'Initial stock'),
(2, -1.50, 0.00,  0,   25, 'PURCHASE', 'Initial stock'),
(3, -3.00, -1.00, 90,  10, 'PURCHASE', 'Initial stock'),
(4, -4.00, -1.25, 180, 8,  'PURCHASE', 'Initial stock'),
(5,  0.00,  0.00, 0,   30, 'PURCHASE', 'Initial stock'),
(6, -2.50, -0.50, 90,  12, 'PURCHASE', 'Initial stock');
