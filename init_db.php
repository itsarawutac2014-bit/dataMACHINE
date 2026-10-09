<?php
/**
 * Auto Database Initializer for Cloud Deployments (Railway, Docker, etc.)
 * ตรวจสอบและนำเข้า datamanbhs.sql อัตโนมัติหากฐานข้อมูลยังว่างเปล่า
 */
require_once __DIR__ . '/config/database.php';

try {
    echo "[DB Init] Connecting to database...\n";
    $pdo = Database::getConnection();

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
    echo "[DB Init Error] " . $e->getMessage() . "\n";
}
