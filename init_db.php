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

    // ตรวจสอบว่ามีตาราง bhs_shift_reports หรือยัง
    $stmt = $pdo->query("SHOW TABLES LIKE 'bhs_shift_reports'");
    $tableExists = $stmt->fetch();

    if (!$tableExists) {
        $sqlFile = __DIR__ . '/datamanbhs.sql';
        if (file_exists($sqlFile)) {
            echo "[DB Init] Database is empty. Importing datamanbhs.sql...\n";
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
            echo "[DB Init] Database tables and sample data imported successfully!\n";
        } else {
            echo "[DB Init] Warning: datamanbhs.sql not found.\n";
        }
    } else {
        echo "[DB Init] Database tables already exist. Skipping import.\n";
    }
} catch (Exception $e) {
    echo "[DB Init Warning] " . $e->getMessage() . "\n";
}
