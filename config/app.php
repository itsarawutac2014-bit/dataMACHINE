<?php
/**
 * Application Core Configuration & Global Utilities
 */

// เริ่มต้น Session หากยังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

// ตั้งค่า Timezone ประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ข้อมูลพื้นฐานของระบบ
define('APP_NAME', 'BHS Performance Shift System');
define('APP_TITLE', 'ระบบรายงานประสิทธิภาพการเดินงาน BHS ประจำกะ');
define('APP_VERSION', '1.0.0');

// Base URL อัตโนมัติสำหรับ XAMPP
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = rtrim($protocol . $host . $scriptDir, '/');
if (basename($baseUrl) === 'actions' || basename($baseUrl) === 'views') {
    $baseUrl = dirname($baseUrl);
}
define('BASE_URL', $baseUrl);

/**
 * ป้องกัน XSS Injection ด้วยการแปลงอักขระพิเศษ
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize String Input
 */
function sanitize_text(?string $str): string {
    return trim(filter_var($str ?? '', FILTER_DEFAULT));
}

/**
 * สร้าง CSRF Token ประจำ Session
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * ตรวจสอบความถูกต้องของ CSRF Token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ส่ง JSON Response พร้อม HTTP Status Code
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * เปลี่ยนเส้นทาง (Redirect)
 */
function redirect(string $path): void {
    header("Location: " . BASE_URL . "/" . ltrim($path, '/'));
    exit;
}

/**
 * Format วันที่ภาษาไทย เช่น 28/09/2569
 */
function format_thai_date(?string $dateStr): string {
    if (!$dateStr) return '-';
    $time = strtotime($dateStr);
    if (!$time) return $dateStr;
    $d = date('j', $time);
    $m = date('n', $time);
    $y = (int)date('Y', $time) + 543; // แปลง ค.ศ. เป็น พ.ศ.
    return "$d/$m/$y";
}

/**
 * Helper จัดการ Flash Message แจ้งเตือนผู้ใช้
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
