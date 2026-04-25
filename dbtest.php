<?php
// Quick DB test + root reset
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;charset=utf8", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p style='color:green'>Connected! Now resetting root auth plugin...</p>";

    // Reset root to native password
    try {
        $pdo->exec("UPDATE mysql.user SET plugin='mysql_native_password', authentication_string='' WHERE User='root'");
        $pdo->exec("FLUSH PRIVILEGES");
        echo "<p style='color:green'>Root auth plugin updated to mysql_native_password with empty password.</p>";
        echo "<p><strong>Restart MySQL now (via XAMPP Control Panel), then run setup:</strong></p>";
        echo "<p><a href='/optical-ledger/setup.php' style='padding:10px 20px;background:#2980b9;color:white;text-decoration:none;border-radius:5px;'>Run Setup</a></p>";
    } catch (Exception $e2) {
        echo "<p style='color:orange'>Could not update plugin: " . $e2->getMessage() . "</p>";
        echo "<p>Try visiting setup.php directly — skip-grant-tables may allow it.</p>";
        echo "<p><a href='/optical-ledger/setup.php'>Run Setup &rarr;</a></p>";
    }

} catch (PDOException $e) {
    echo "<div style='padding:20px;background:#fde;border:1px solid red;border-radius:5px;'>";
    echo "<strong>DB Error:</strong> " . $e->getMessage() . "<br><br>";
    echo "<strong>Fix:</strong> Make sure XAMPP MySQL is running (start it from XAMPP Control Panel).";
    echo "</div>";
}
