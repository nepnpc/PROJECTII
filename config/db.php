<?php
define('BASE_URL', '/optical-ledger');
define('SITE_NAME', 'Optical Ledger');

$host   = '127.0.0.1';
$dbname = 'optical_ledger';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=3307;dbname=$dbname;charset=utf8",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('
    <div style="font-family:sans-serif;padding:30px;max-width:600px;margin:50px auto;background:#fff;border:2px solid #e74c3c;border-radius:8px;">
        <h2 style="color:#e74c3c;">Database Connection Failed</h2>
        <p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
        <hr>
        <p><strong>Fix steps:</strong></p>
        <ol>
            <li>Open XAMPP Control Panel</li>
            <li>Start <strong>Apache</strong> and <strong>MySQL</strong></li>
            <li>Go to <a href="http://localhost/phpmyadmin">http://localhost/phpmyadmin</a></li>
            <li>Import <code>optical-ledger/database.sql</code></li>
            <li>Refresh this page</li>
        </ol>
    </div>');
}
