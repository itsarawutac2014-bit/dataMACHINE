<?php
/**
 * Global Header Component
 * ธีมสี ขาว-ส้ม (Clean Orange & White) แสดงผลเต็มหน้ากว้าง Responsive
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';

$user = current_user();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?><?= APP_TITLE ?></title>
    
    <!-- Google Fonts: Sarabun & Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Top Navigation Bar (White & Orange Theme) -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-theme-orange sticky-top shadow-sm py-2">
        <div class="container-fluid px-3 px-xl-4">
            <a class="navbar-brand d-flex align-items-center fw-bold" href="<?= BASE_URL ?>/views/dashboard.php">
                <span class="brand-icon bg-white text-orange rounded-3 p-2 me-2 shadow-sm d-inline-flex align-items-center justify-content-center">
                    <i class="fa-solid fa-industry fa-lg"></i>
                </span>
                <div>
                    <span class="fs-5 text-white brand-text">BHS Performance</span>
                    <small class="d-block text-white-50 fs-8 fw-normal">ระบบรายงานประสิทธิภาพการเดินงาน BHS</small>
                </div>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <?php if (is_logged_in()): ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                    <li class="nav-item">
                        <a class="nav-link text-white <?= ($activeNav ?? '') === 'dashboard' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>/views/dashboard.php">
                            <i class="fa-solid fa-chart-line me-1"></i> รายการรายงานประจำกะ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= ($activeNav ?? '') === 'upload' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>/views/upload.php">
                            <i class="fa-solid fa-cloud-arrow-up me-1"></i> นำเข้าไฟล์เครื่องจักร (Import)
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= ($activeNav ?? '') === 'targets' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>/views/admin_targets.php">
                            <i class="fa-solid fa-sliders me-1"></i> จัดการเป้าหมาย & หัวข้อ (Targets)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= ($activeNav ?? '') === 'users' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>/views/users.php">
                            <i class="fa-solid fa-users-gear me-1"></i> จัดการผู้ใช้ (Users)
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- User Profile & Logout -->
                <div class="d-flex align-items-center gap-2">
                    <div class="user-badge px-3 py-1 bg-white rounded-pill text-dark d-flex align-items-center shadow-sm">
                        <i class="fa-solid fa-circle-user text-orange me-2 fs-5"></i>
                        <div class="lh-1 me-2 text-start">
                            <div class="fw-bold fs-7"><?= e($user['full_name']) ?></div>
                            <span class="badge <?= is_admin() ? 'bg-danger' : 'bg-secondary' ?> fs-9 py-0">
                                <?= is_admin() ? 'ผู้ดูแลระบบ (Admin)' : 'ผู้ใช้งาน (User)' ?>
                            </span>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/actions/auth_action.php?action=logout" class="btn btn-outline-light btn-sm rounded-pill px-3 shadow-sm" onclick="return confirm('ยืนยันออกจากระบบหรือไม่?');">
                        <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> ออกจากระบบ
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Flash Message Notification -->
    <?php if ($flash): ?>
    <div class="container-fluid px-3 px-xl-4 mt-3">
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-success' : ($flash['type'] === 'danger' ? 'fa-circle-xmark text-danger' : 'fa-circle-info text-info') ?> fa-lg me-2"></i>
            <div><?= e($flash['message']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Container -->
    <main class="flex-grow-1 py-3">
        <div class="container-fluid px-3 px-xl-4">
