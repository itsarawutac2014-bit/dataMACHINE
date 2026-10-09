<?php
/**
 * User Management Action (Admin Only)
 * จัดการ เพิ่ม ลบ แก้ไข ผู้ใช้งานในระบบ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';

require_admin(); // เฉพาะผู้ดูแลระบบเท่านั้น

$action = sanitize_text($_POST['action'] ?? ($_GET['action'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'CSRF Token ไม่ถูกต้อง');
        redirect('views/users.php');
    }
}

$pdo = Database::getConnection();

// 1. เพิ่มผู้ใช้ใหม่
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_text($_POST['username'] ?? '');
    $fullName = sanitize_text($_POST['full_name'] ?? '');
    $role     = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($fullName) || empty($password)) {
        set_flash('warning', 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง');
        redirect('views/users.php');
    }

    try {
        // ตรวจสอบชื่อผู้ใช้ซ้ำ
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $chk->execute([':u' => $username]);
        if ($chk->fetch()) {
            set_flash('warning', "ชื่อผู้ใช้งาน '{$username}' มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น");
            redirect('views/users.php');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, role, can_edit, status)
            VALUES (:username, :password, :full_name, :role, 1, 'active')
        ");
        $stmt->execute([
            ':username'  => $username,
            ':password'  => $hash,
            ':full_name' => $fullName,
            ':role'      => $role
        ]);

        set_flash('success', "เพิ่มผู้ใช้งาน '{$fullName}' เรียบร้อยแล้ว");
    } catch (Exception $e) {
        error_log("[User Create Error] " . $e->getMessage());
        set_flash('danger', 'เกิดข้อผิดพลาดในการเพิ่มผู้ใช้: ' . $e->getMessage());
    }
    redirect('views/users.php');
}

// 2. แก้ไขผู้ใช้
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['user_id'] ?? 0);
    $fullName = sanitize_text($_POST['full_name'] ?? '');
    $role     = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
    $status   = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $newPass  = $_POST['new_password'] ?? '';

    if ($id <= 0 || empty($fullName)) {
        set_flash('warning', 'ข้อมูลไม่ถูกต้อง');
        redirect('views/users.php');
    }

    try {
        if (!empty($newPass)) {
            // อัปเดตรวมรหัสผ่านใหม่
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = :full_name, role = :role, status = :status, password = :password
                WHERE id = :id
            ");
            $stmt->execute([
                ':full_name' => $fullName,
                ':role'      => $role,
                ':status'    => $status,
                ':password'  => $hash,
                ':id'        => $id
            ]);
        } else {
            // ไม่อัปเดตรหัสผ่าน
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = :full_name, role = :role, status = :status
                WHERE id = :id
            ");
            $stmt->execute([
                ':full_name' => $fullName,
                ':role'      => $role,
                ':status'    => $status,
                ':id'        => $id
            ]);
        }

        set_flash('success', "อัปเดตข้อมูลผู้ใช้งานเรียบร้อยแล้ว");
    } catch (Exception $e) {
        error_log("[User Update Error] " . $e->getMessage());
        set_flash('danger', 'เกิดข้อผิดพลาดในการอัปเดต: ' . $e->getMessage());
    }
    redirect('views/users.php');
}

// 3. ลบผู้ใช้
if ($action === 'delete') {
    $id = (int)($_POST['user_id'] ?? ($_GET['user_id'] ?? 0));
    $currentId = current_user()['id'] ?? 0;

    if ($id <= 0) {
        set_flash('warning', 'ไม่พบรหัสผู้ใช้ที่ต้องการลบ');
        redirect('views/users.php');
    }

    if ($id === $currentId) {
        set_flash('danger', 'ไม่สามารถลบบัญชีของตนเองที่กำลังใช้งานอยู่ได้');
        redirect('views/users.php');
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        set_flash('success', "ลบผู้ใช้งานเรียบร้อยแล้ว");
    } catch (Exception $e) {
        error_log("[User Delete Error] " . $e->getMessage());
        set_flash('danger', 'เกิดข้อผิดพลาดในการลบผู้ใช้: ' . $e->getMessage());
    }
    redirect('views/users.php');
}

redirect('views/users.php');
