<?php
/**
 * Dashboard View - Shift Reports Overview
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$pdo = Database::getConnection();

// ดึงสรุปสถิติภาพรวม
$statStmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT r.id) as total_reports,
        COALESCE(COUNT(p.id), 0) as total_orders,
        COALESCE(SUM(p.total_length_m), 0) as total_meters,
        COALESCE(SUM(p.total_weight_kg), 0) as total_weight
    FROM bhs_shift_reports r
    LEFT JOIN bhs_production_records p ON r.id = p.report_id
");
$stats = $statStmt->fetch();

// ดึงรายการรายงานทั้งหมด
$reportsStmt = $pdo->query("
    SELECT 
        r.*,
        u.full_name as author_name,
        COUNT(p.id) as record_count,
        COALESCE(SUM(p.total_length_m), 0) as sum_meters,
        COALESCE(SUM(p.total_weight_kg), 0) as sum_weight
    FROM bhs_shift_reports r
    LEFT JOIN users u ON r.created_by = u.id
    LEFT JOIN bhs_production_records p ON r.id = p.report_id
    GROUP BY r.id
    ORDER BY r.report_date DESC, r.id DESC
");
$reports = $reportsStmt->fetchAll();

$pageTitle = 'แดชบอร์ดรายงานประจำกะ';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header & Quick Actions -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold mb-1 text-dark">
            <i class="fa-solid fa-gauge-high text-orange me-2"></i> แดชบอร์ดรายงานประสิทธิภาพการเดินงาน BHS
        </h3>
        <p class="text-muted fs-7 mb-0">ระบบติดตามและสรุปผลประสิทธิภาพเครื่องจักรรีดลอนลูกฟูก BHS ประจำแต่ละกะ</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <a href="<?= BASE_URL ?>/views/upload.php" class="btn btn-orange rounded-pill px-4 shadow-sm fw-bold">
            <i class="fa-solid fa-cloud-arrow-up me-2"></i> นำเข้าไฟล์เครื่องจักรใหม่ (Import)
        </a>
    </div>
</div>

<!-- 4 Key Metrics Overview Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-theme border-0 shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="rounded-3 bg-theme-orange-light p-3 text-orange me-3">
                    <i class="fa-solid fa-file-waveform fa-2x"></i>
                </div>
                <div>
                    <span class="text-muted fs-8 fw-semibold">จำนวนรอบรายงาน</span>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format($stats['total_reports'] ?? 0) ?></h4>
                    <span class="fs-9 text-secondary">รอบการเดินงาน</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-theme border-0 shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="rounded-3 bg-primary bg-opacity-10 p-3 text-primary me-3">
                    <i class="fa-solid fa-boxes-stacked fa-2x"></i>
                </div>
                <div>
                    <span class="text-muted fs-8 fw-semibold">ออเดอร์สะสมทั้งหมด</span>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format($stats['total_orders'] ?? 0) ?></h4>
                    <span class="fs-9 text-secondary">ออเดอร์</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-theme border-0 shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="rounded-3 bg-success bg-opacity-10 p-3 text-success me-3">
                    <i class="fa-solid fa-ruler-combined fa-2x"></i>
                </div>
                <div>
                    <span class="text-muted fs-8 fw-semibold">ความยาวผลิตสะสม</span>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format($stats['total_meters'] ?? 0) ?></h4>
                    <span class="fs-9 text-secondary">เมตร</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-theme border-0 shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="rounded-3 bg-warning bg-opacity-10 p-3 text-warning me-3">
                    <i class="fa-solid fa-weight-hanging fa-2x"></i>
                </div>
                <div>
                    <span class="text-muted fs-8 fw-semibold">น้ำหนักกระดาษสะสม</span>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format($stats['total_weight'] ?? 0) ?></h4>
                    <span class="fs-9 text-secondary">กิโลกรัม</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shift Reports Table Card -->
<div class="card card-theme shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold fs-6 text-dark">
            <i class="fa-solid fa-table-list text-orange me-2"></i> รายการรายงานการเดินงาน BHS ทั้งหมด
        </span>
        <span class="badge bg-theme-orange text-white fs-8"><?= count($reports) ?> รายงาน</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($reports)): ?>
        <div class="text-center py-5">
            <div class="mb-3 text-muted">
                <i class="fa-solid fa-file-circle-question fa-4x text-orange opacity-50"></i>
            </div>
            <h5 class="fw-bold text-dark">ยังไม่มีข้อมูลรายงานในระบบ</h5>
            <p class="text-muted fs-7 mb-3">กรุณานำเข้าไฟล์ข้อมูลจากเครื่องจักร BHS เพื่อเริ่มต้นสร้างรายงาน</p>
            <a href="<?= BASE_URL ?>/views/upload.php" class="btn btn-orange rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-cloud-arrow-up me-1"></i> นำเข้าไฟล์แรกเลย
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead class="table-light fs-8 text-secondary">
                    <tr>
                        <th class="ps-4">ชื่อรายงาน</th>
                        <th class="text-center">เครื่องจักร</th>
                        <th class="text-center">กะ (Shift)</th>
                        <th class="text-center">วันที่ผลิต</th>
                        <th class="text-center">เวลาตัดกะ</th>
                        <th class="text-center">จำนวนออเดอร์</th>
                        <th class="text-end">ความยาวรวม (เมตร)</th>
                        <th class="text-end">น้ำหนักรวม (กก.)</th>
                        <th>ผู้นำเข้า</th>
                        <th class="text-center pe-4" style="width: 180px;">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $rep): 
                        $mName = $rep['machine_name'] ?? 'BHS';
                        $mBadgeClass = ($mName === 'YUELI') ? 'bg-success' : (($mName === 'ISOWA') ? 'bg-dark' : 'bg-primary');
                    ?>
                    <tr>
                        <td class="ps-4">
                            <a href="<?= BASE_URL ?>/views/report_view.php?id=<?= $rep['id'] ?>" class="fw-bold text-dark text-decoration-none hover-orange">
                                <i class="fa-solid fa-file-lines text-orange me-2"></i><?= e($rep['title']) ?>
                            </a>
                            <?php if ($rep['raw_filename']): ?>
                            <small class="d-block text-muted fs-9 ps-4">ไฟล์: <?= e($rep['raw_filename']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $mBadgeClass ?> px-3 py-1 rounded-pill fw-bold">
                                <?= e($mName) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold">
                                กะ <?= e($rep['shift']) ?>
                            </span>
                        </td>
                        <td class="text-center fw-semibold text-secondary">
                            <?= format_thai_date($rep['report_date']) ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border"><?= e($rep['shift_end_time']) ?></span>
                        </td>
                        <td class="text-center fw-bold">
                            <?= number_format($rep['record_count']) ?>
                        </td>
                        <td class="text-end fw-bold text-primary">
                            <?= number_format($rep['sum_meters']) ?>
                        </td>
                        <td class="text-end fw-bold text-success">
                            <?= number_format($rep['sum_weight']) ?>
                        </td>
                        <td class="fs-8 text-muted">
                            <i class="fa-regular fa-user me-1"></i> <?= e($rep['author_name'] ?? 'System') ?>
                        </td>
                        <td class="text-center pe-4">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_URL ?>/views/report_view.php?id=<?= $rep['id'] ?>" class="btn btn-outline-orange" title="ดูรายงานฉบับเต็ม">
                                    <i class="fa-solid fa-eye me-1"></i> ดูรายงาน
                                </a>
                                <?php if (is_admin()): ?>
                                <button type="button" class="btn btn-outline-danger" title="ลบรายงาน" onclick="confirmDeleteReport(<?= $rep['id'] ?>, '<?= e($rep['title']) ?>')">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden Delete Report Form -->
<form id="deleteReportForm" action="<?= BASE_URL ?>/actions/report_action.php" method="POST" class="d-none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="report_id" id="deleteReportId" value="">
</form>

<script>
function confirmDeleteReport(id, title) {
    if (confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบรายงาน "${title}"?\nข้อมูลการผลิตทั้งหมดในรายงานนี้จะถูกลบออกถาวร`)) {
        document.getElementById('deleteReportId').value = id;
        document.getElementById('deleteReportForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
