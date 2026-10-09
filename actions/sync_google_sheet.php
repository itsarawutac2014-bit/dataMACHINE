<?php
/**
 * AJAX Endpoint สำหรับสั่งซิงค์รายงานเข้า Google Sheets ด้วยตนเอง
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/google_sheet.php';

header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบ Login
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ']);
    exit;
}

$reportId = (int)($_POST['report_id'] ?? $_GET['report_id'] ?? 0);
if ($reportId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสรายงาน (Report ID)']);
    exit;
}

$result = sync_report_to_google_sheet($reportId);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
