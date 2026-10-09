<?php
/**
 * Authentication Action Handler (Login & Logout)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';

$action = sanitize_text($_GET['action'] ?? ($_POST['action'] ?? ''));

// จัดการ Logout
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    session_start();
    set_flash('info', 'ออกจากระบบเรียบร้อยแล้ว');
    redirect('views/login.php');
}

// จัดการ Login
if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('views/login.php');
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'ความปลอดภัยล้มเหลว: CSRF Token ไม่ถูกต้อง กรุณาลองใหม่');
        redirect('views/login.php');
    }

    $username = sanitize_text($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        set_flash('warning', 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน');
        redirect('views/login.php');
    }

    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, role, can_edit, status FROM users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && ($user['status'] ?? 'active') === 'active') {
            if (password_verify($password, $user['password'])) {
                // Regenerate Session ID เพื่อป้องกัน Session Fixation
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['can_edit']  = $user['can_edit'];

                set_flash('success', 'ยินดีต้อนรับเข้าสู่ระบบ, ' . $user['full_name']);
                redirect('views/dashboard.php');
            }
        }

        set_flash('danger', 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
        redirect('views/login.php');

    } catch (Exception $e) {
        error_log("[Login Error] " . $e->getMessage());
        set_flash('danger', 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ กรุณาลองใหม่อีกครั้ง');
        redirect('views/login.php');
    }
}

redirect('views/login.php');
