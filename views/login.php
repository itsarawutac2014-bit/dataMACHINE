<?php
/**
 * Login Page - Clean White & Orange Theme
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';

if (is_logged_in()) {
    redirect('views/dashboard.php');
}

$pageTitle = 'เข้าสู่ระบบ';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= APP_TITLE ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">

<div class="card card-theme shadow-lg border-0" style="max-width: 440px; width: 100%; border-top: 5px solid var(--theme-orange) !important;">
    <div class="card-body p-4 p-md-5">
        <!-- Logo Header -->
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-theme-orange text-white rounded-circle shadow-sm mb-3" style="width: 70px; height: 70px;">
                <i class="fa-solid fa-industry fa-2x"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">BHS Performance</h4>
            <p class="text-muted fs-7 mb-0">ระบบรายงานประสิทธิภาพการเดินงาน BHS ประจำกะ</p>
        </div>

        <!-- Alert Notification -->
        <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show border-0 shadow-sm fs-7 py-2" role="alert">
            <i class="fa-solid fa-circle-exclamation me-1"></i> <?= e($flash['message']) ?>
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="<?= BASE_URL ?>/actions/auth_action.php?action=login" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold fs-7 text-secondary">
                    <i class="fa-solid fa-user me-1 text-orange"></i> ชื่อผู้ใช้งาน (Username)
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" placeholder="เช่น admin" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold fs-7 text-secondary">
                    <i class="fa-solid fa-lock me-1 text-orange"></i> รหัสผ่าน (Password)
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-key"></i></span>
                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="เช่น 123456" required>
                </div>
            </div>

            <button type="submit" class="btn btn-orange w-100 py-2 rounded-3 shadow-sm mb-3">
                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> เข้าสู่ระบบ (Login)
            </button>
        </form>

        <!-- Test Account Hint Box -->
        <div class="card bg-theme-orange-light border-orange rounded-3 mt-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-1">
                    <i class="fa-solid fa-circle-info text-orange me-2"></i>
                    <span class="fw-bold text-dark fs-7">บัญชีทดสอบในระบบ:</span>
                </div>
                <ul class="list-unstyled mb-0 fs-8 text-secondary ps-3">
                    <li><i class="fa-solid fa-angle-right text-orange me-1"></i> <strong>Admin:</strong> <code>admin</code> / รหัสผ่าน: <code>123456</code></li>
                    <li><i class="fa-solid fa-angle-right text-orange me-1"></i> <strong>User:</strong> <code>user</code> / รหัสผ่าน: <code>123456</code></li>
                </ul>
            </div>
        </div>

    </div>
    <div class="card-footer bg-light text-center py-2 border-0">
        <small class="text-muted fs-9">Vanilla PHP 8.x + MySQL PDO Prepared Statements</small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
