<?php
/**
 * Auto Database Initializer for Cloud Deployments (Railway, Docker, etc.)
 */
require_once __DIR__ . '/config/database.php';

try {
    echo "[DB Init] Connecting to database...\n";
    $host     = getenv('DB_HOST') ?: 'localhost';
    $port     = getenv('DB_PORT') ?: '3306';
    $dbName   = getenv('DB_NAME') ?: 'datamanbhs';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        PDO::MYSQL_ATTR_INIT_COMMAND     => "SET NAMES utf8mb4"
    ]);

    // ตรวจสอบว่ามีตาราง users หรือยัง
    $stmtUsers = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersExist = $stmtUsers->fetch();

    if (!$usersExist) {
        echo "[DB Init] Table 'users' not found. Running datamanbhs.sql...\n";
        $sqlFile = __DIR__ . '/datamanbhs.sql';
        if (file_exists($sqlFile)) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
            echo "[DB Init] Database imported successfully!\n";
        }
    } else {
        echo "[DB Init] Table 'users' already exists.\n";
    }

    // เพื่อความมั่นใจ 100% ตรวจสอบว่ามีผู้ใช้ admin อยู่ในตารางหรือไม่
    $stmtAdmin = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $adminCount = (int)$stmtAdmin->fetchColumn();
    if ($adminCount === 0) {
        echo "[DB Init] Creating default admin user...\n";
        $hash = password_hash('123456', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES ('admin', :p, 'ผู้ดูแลระบบ (Admin BHS)', 'admin', 'active')")
            ->execute([':p' => $hash]);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES ('user', :p, 'General User', 'user', 'active')")
            ->execute([':p' => $hash]);
        echo "[DB Init] Default users created!\n";
    }

} catch (Exception $e) {
    echo "[DB Init Warning] " . $e->getMessage() . "\n";
}
