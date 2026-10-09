<?php
/**
 * Authentication Middleware & Session Control
 */

require_once __DIR__ . '/app.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'] ?? 'ผู้ใช้งาน',
        'role'      => $_SESSION['role'] ?? 'user',
        'can_edit'  => $_SESSION['can_edit'] ?? 1
    ];
}

function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน');
        redirect('views/login.php');
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'สิทธิ์การใช้งานไม่เพียงพอ: หน้าสำหรับผู้ดูแลระบบ (Admin) เท่านั้น');
        redirect('views/dashboard.php');
    }
}
