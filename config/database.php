<?php
/**
 * Database Connection Configuration using PDO
 * ระบบเชื่อมต่อฐานข้อมูล datamanbhs แบบ Singleton และ Prepared Statements
 */

class Database {
    private static ?PDO $instance = null;

    private static string $host = 'localhost';
    private static string $db_name = 'datamanbhs';
    private static string $username = 'root';
    private static string $password = '';
    private static string $charset = 'utf8mb4';

    /**
     * ดึง Object การเชื่อมต่อ PDO (Singleton Pattern)
     * @return PDO
     * @throws Exception
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$db_name . ";charset=" . self::$charset;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // บังคับใช้ Native Prepared Statements 100%
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::$charset . " COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, self::$username, self::$password, $options);
            } catch (PDOException $e) {
                // บันทึก Error Log ทางฝั่ง Server เพื่อความปลอดภัย (ไม่เปิดเผย Password หรือ DSN ต่อสาธารณะ)
                error_log("[Database Connection Error] " . $e->getMessage());
                throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: กรุณาตรวจสอบ MySQL Service บน XAMPP หรือการตั้งค่า Config");
            }
        }

        return self::$instance;
    }

    /**
     * ป้องกันการ clone หรือ new instance จากภายนอก
     */
    private function __construct() {}
    private function __clone() {}
}
