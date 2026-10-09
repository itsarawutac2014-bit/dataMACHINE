<?php
/**
 * Admin Target & Table Topics Management View
 * หน้าสำหรับผู้ดูแลระบบ (Admin) ในการแก้ไข Target ในตาราง, เปลี่ยนแปลงหัวข้อ, เพิ่มและลบหัวข้อได้หลากหลาย
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin(); // จำกัดการเข้าถึงเฉพาะ Admin เท่านั้น

$pdo = Database::getConnection();

// ตรวจสอบพารามิเตอร์เลือกดูรายงานเฉพาะ หรือ แม่แบบ
$selectedReportId = (int)($_GET['report_id'] ?? 0);
$currentReport = null;

if ($selectedReportId > 0) {
    $stmtRep = $pdo->prepare("SELECT * FROM bhs_shift_reports WHERE id = :id LIMIT 1");
    $stmtRep->execute([':id' => $selectedReportId]);
    $currentReport = $stmtRep->fetch();
}

// ดึงรายการรายงานทั้งหมดสำหรับ Dropdown เลือกปรับแต่งเฉพาะกะ
$allReports = $pdo->query("SELECT id, title, machine_name, shift, report_date FROM bhs_shift_reports ORDER BY id DESC LIMIT 50")->fetchAll();

// ดึงแม่แบบเป้าหมายหลักจากฐานข้อมูล
$stmtTpl = $pdo->query("SELECT * FROM bhs_target_templates WHERE template_name = 'default' LIMIT 1");
$template = $stmtTpl->fetch();

if (!$template) {
    // หากยังไม่มี ให้สร้างค่าเริ่มต้น
    $defaultDepts = [
        ['name' => 'ผลิต', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'MC', 'target_pct' => 3.00, 'is_active' => 1],
        ['name' => 'ไฟฟ้า', 'target_pct' => 1.00, 'is_active' => 1],
        ['name' => 'คลังม้วน', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'ม้วน', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'Utiliy', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'Packking', 'target_pct' => 1.00, 'is_active' => 1],
        ['name' => 'กาว', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'วางแผน', 'target_pct' => 1.00, 'is_active' => 1],
        ['name' => 'จัดส่ง', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'เปลี่ยนลอน', 'target_pct' => 2.00, 'is_active' => 1],
        ['name' => 'PM', 'target_pct' => 2.00, 'is_active' => 1],
    ];
    $pdo->prepare("
        INSERT INTO bhs_target_templates (
            template_name, machine_name, target_orders, target_meters, target_speed,
            target_avg_meters, target_time_total, target_time_run, target_loss_pct,
            target_shortage_pct, loss_departments
        ) VALUES (
            'default', 'BHS', 120, 68056.00, 100.00,
            500.00, 720, 675, 10.00,
            15.00, :loss_departments
        )
    ")->execute([':loss_departments' => json_encode($defaultDepts, JSON_UNESCAPED_UNICODE)]);

    $template = $pdo->query("SELECT * FROM bhs_target_templates WHERE template_name = 'default' LIMIT 1")->fetch();
}

// กำหนดค่าตัวเลขเป้าหมายตามโหมดที่เลือก (รายงานเฉพาะกะ หรือ แม่แบบระบบ)
if ($currentReport) {
    $modeTitle = "แก้ไขเป้าหมายสำหรับรายงาน: " . e($currentReport['title']) . " (" . format_thai_date($currentReport['report_date']) . ")";
    $targetOrders    = (int)$currentReport['target_orders'];
    $targetMeters    = (float)$currentReport['target_meters'];
    $targetSpeed     = (float)$currentReport['target_speed'];
    $targetAvgMeters = (float)$currentReport['target_avg_meters'];
    $targetTimeTotal = (int)$currentReport['target_time_total'];
    $targetTimeRun   = (int)$currentReport['target_time_run'];
    $targetLossPct   = (float)$currentReport['target_loss_pct'];
    $targetShortage  = 15.00;

    $reportLossData = !empty($currentReport['loss_details']) ? json_decode($currentReport['loss_details'], true) : [];
    $departmentsList = [];
    if (is_array($reportLossData)) {
        foreach ($reportLossData as $dName => $dInfo) {
            $departmentsList[] = [
                'name'       => $dName,
                'target_pct' => (float)($dInfo['target_pct'] ?? 0.0),
                'is_active'  => 1
            ];
        }
    }
} else {
    $modeTitle = "จัดการแม่แบบเป้าหมายมาตรฐาน (Master Template Defaults)";
    $targetOrders    = (int)$template['target_orders'];
    $targetMeters    = (float)$template['target_meters'];
    $targetSpeed     = (float)$template['target_speed'];
    $targetAvgMeters = (float)$template['target_avg_meters'];
    $targetTimeTotal = (int)$template['target_time_total'];
    $targetTimeRun   = (int)$template['target_time_run'];
    $targetLossPct   = (float)$template['target_loss_pct'];
    $targetShortage  = (float)$template['target_shortage_pct'];

    $parsedDepts = !empty($template['loss_departments']) ? json_decode($template['loss_departments'], true) : [];
    $departmentsList = is_array($parsedDepts) ? $parsedDepts : [];
}

// โหลด Station Waste Targets (Target KG ต่อ Station)
$defaultStationsAdmin = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];
$stationWasteTargets = [];
try {
    $parsedSwt = !empty($template['station_waste_targets']) ? json_decode($template['station_waste_targets'], true) : [];
    $stationWasteTargets = is_array($parsedSwt) ? $parsedSwt : [];
} catch (Exception $e) { $stationWasteTargets = []; }

if (empty($departmentsList)) {
    $departmentsList = [
        ['name' => 'ผลิต', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'MC', 'target_pct' => 3.00, 'is_active' => 1],
        ['name' => 'ไฟฟ้า', 'target_pct' => 1.00, 'is_active' => 1],
        ['name' => 'คลังม้วน', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'ม้วน', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'Utiliy', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'Packking', 'target_pct' => 1.00, 'is_active' => 1],
        ['name' => 'กาว', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'วางแผน', 'target_pct' => 1.00, 'is_active' => 1],
        ['name' => 'จัดส่ง', 'target_pct' => 0.00, 'is_active' => 1],
        ['name' => 'เปลี่ยนลอน', 'target_pct' => 2.00, 'is_active' => 1],
        ['name' => 'PM', 'target_pct' => 2.00, 'is_active' => 1],
    ];
}

$pageTitle = "จัดการเป้าหมาย & หัวข้อตาราง (Admin Targets)";
$activeNav = 'targets';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-3 px-xl-4 py-3">

    <!-- Header & Mode Navigator -->
    <div class="row align-items-center mb-3">
        <div class="col-lg-6">
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-sliders text-orange me-2"></i> จัดการเป้าหมาย & หัวข้อตารางรายงาน
            </h4>
            <div class="text-muted fs-7">
                <span>สำหรับผู้ดูแลระบบ: ปรับแต่ง Target ตัวเลขในตาราง, เพิ่ม/ลบ และเปลี่ยนชื่อหัวข้อแผนกได้อย่างอิสระ</span>
            </div>
        </div>
        <div class="col-lg-6 text-lg-end mt-2 mt-lg-0 d-flex flex-wrap gap-2 justify-content-lg-end">
            <?php if ($selectedReportId > 0): ?>
            <a href="<?= BASE_URL ?>/views/report_view.php?id=<?= $selectedReportId ?>" class="btn btn-outline-dark rounded-pill px-3 shadow-sm btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> กลับไปหน้ารายงาน
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-danger rounded-pill px-3 shadow-sm btn-sm" data-bs-toggle="modal" data-bs-target="#modalResetDefaults">
                <i class="fa-solid fa-rotate-left me-1"></i> คืนค่ามาตรฐานโรงงาน
            </button>
            <button type="button" class="btn btn-orange rounded-pill px-4 shadow-sm fw-bold btn-sm btn-submit-all">
                <i class="fa-solid fa-floppy-disk me-1"></i> บันทึกการเปลี่ยนแปลง
            </button>
        </div>
    </div>

    <!-- Mode Selector Card -->
    <div class="card card-theme shadow-sm border-0 mb-4">
        <div class="card-body p-3 bg-white">
            <div class="row align-items-center g-2">
                <div class="col-md-5">
                    <label class="form-label fs-8 fw-bold text-secondary mb-1">
                        <i class="fa-solid fa-crosshairs text-orange me-1"></i> เลือกขอบเขตการแก้ไข (Scope)
                    </label>
                    <div class="btn-group w-100" role="group">
                        <a href="<?= BASE_URL ?>/views/admin_targets.php" class="btn btn-sm <?= ($selectedReportId <= 0) ? 'btn-orange fw-bold' : 'btn-outline-secondary' ?>">
                            <i class="fa-solid fa-star me-1"></i> แม่แบบมาตรฐานระบบ (Master Template)
                        </a>
                        <button type="button" class="btn btn-sm <?= ($selectedReportId > 0) ? 'btn-orange fw-bold' : 'btn-outline-secondary' ?>" data-bs-toggle="collapse" data-bs-target="#collapseReportPicker">
                            <i class="fa-solid fa-file-waveform me-1"></i> เลือกรายงานเฉพาะกะ <?= $selectedReportId > 0 ? " (ID: $selectedReportId)" : "" ?>
                        </button>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="collapse <?= ($selectedReportId > 0) ? 'show' : '' ?>" id="collapseReportPicker">
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">
                            <i class="fa-solid fa-list-check text-orange me-1"></i> เลือกรอบรายงานที่ต้องการปรับแต่ง
                        </label>
                        <form method="GET" action="<?= BASE_URL ?>/views/admin_targets.php" class="d-flex gap-2">
                            <select name="report_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="0">-- เลือกรายงานที่ต้องการแก้ไข Target --</option>
                                <?php foreach ($allReports as $rep): ?>
                                <option value="<?= $rep['id'] ?>" <?= ($selectedReportId == $rep['id']) ? 'selected' : '' ?>>
                                    [ID: <?= $rep['id'] ?>] <?= e($rep['machine_name']) ?> กะ <?= e($rep['shift']) ?> - วันที่ <?= format_thai_date($rep['report_date']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="<?= BASE_URL ?>/views/admin_targets.php" class="btn btn-outline-secondary btn-sm" title="กลับไปแม่แบบหลัก">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="mt-2 pt-2 border-top fs-8 d-flex align-items-center justify-content-between">
                <div>
                    <span class="badge bg-primary me-2">กำลังทำงานใน:</span>
                    <strong class="text-dark"><?= $modeTitle ?></strong>
                </div>
                <?php if ($selectedReportId <= 0): ?>
                <span class="text-success"><i class="fa-solid fa-circle-info me-1"></i> ค่าที่บันทึกตรงนี้จะถูกใช้เป็นค่าเริ่มต้นสำหรับทุกล็อต/กะใหม่ที่นำเข้า</span>
                <?php else: ?>
                <span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i> การแก้ไขนี้จะส่งผลโดยตรงต่อรายงาน ID: <?= $selectedReportId ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MAIN FORM -->
    <form id="formAdminTargets" action="<?= BASE_URL ?>/actions/report_action.php" method="POST">
        <input type="hidden" name="action" value="save_target_template">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="template_id" value="<?= (int)($template['id'] ?? 1) ?>">
        <input type="hidden" name="sync_report_id" value="<?= $selectedReportId ?>">

        <div class="row g-3">

            <!-- ======================================================== -->
            <!-- PART 1: MASTER KPI TARGETS IN REPORT TABLE               -->
            <!-- ======================================================== -->
            <div class="col-lg-5">
                <div class="card card-theme shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-bullseye text-orange me-2"></i> 1. ค่าเป้าหมายหลักในตาราง (KPI Targets)
                        </h6>
                        <span class="badge bg-light text-secondary fs-8 border">8 ตัวแปรหลัก</span>
                    </div>
                    <div class="card-body p-3">
                        <p class="text-muted fs-8 mb-3">
                            แก้ไขตัวเลขเป้าหมาย (Target) ที่ปรากฏในตารางรายงาน BHS ประจำกะ:
                        </p>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm fs-8 align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th class="text-start" style="width: 45%;">หัวข้อเป้าหมาย</th>
                                        <th style="width: 35%;">ค่าเป้าหมาย (Target)</th>
                                        <th style="width: 20%;">หน่วย</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="fw-semibold text-start">
                                            จำนวนออเดอร์ที่เดินทั้งหมด+ซ่อม
                                            <div class="fs-9 text-muted">เป้าหมายจำนวนออเดอร์ต่อกะ</div>
                                        </td>
                                        <td>
                                            <input type="number" min="1" name="target_orders" class="form-control form-control-sm text-center fw-bold text-primary" value="<?= $targetOrders ?>" required>
                                        </td>
                                        <td class="text-center">Order</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-start">
                                            ผลิตได้ (ความยาวทั้งหมด)
                                            <div class="fs-9 text-muted">เป้าความยาวการผลิตรวม</div>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="1" name="target_meters" class="form-control form-control-sm text-center fw-bold text-primary" value="<?= $targetMeters ?>" required>
                                        </td>
                                        <td class="text-center">เมตร</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-start">
                                            Speed เฉลี่ยเป้าหมาย
                                            <div class="fs-9 text-muted">ความเร็วมาตรฐานของเครื่องจักร</div>
                                        </td>
                                        <td>
                                            <input type="number" step="0.1" min="1" name="target_speed" class="form-control form-control-sm text-center fw-bold text-primary" value="<?= $targetSpeed ?>" required>
                                        </td>
                                        <td class="text-center">M/Min</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-start">
                                            เมตรเฉลี่ย/order
                                            <div class="fs-9 text-muted">ความยาวเฉลี่ยต่อคำสั่งผลิต</div>
                                        </td>
                                        <td>
                                            <input type="number" step="0.1" min="1" name="target_avg_meters" class="form-control form-control-sm text-center fw-bold text-primary" value="<?= $targetAvgMeters ?>" required>
                                        </td>
                                        <td class="text-center">เมตร</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-start">
                                            เวลาเดินทั้งหมด
                                            <div class="fs-9 text-muted">เวลาต่อ 1 กะ (12 ชม. = 720 น.)</div>
                                        </td>
                                        <td>
                                            <input type="number" min="1" name="target_time_total" class="form-control form-control-sm text-center fw-bold text-primary" value="<?= $targetTimeTotal ?>" required>
                                        </td>
                                        <td class="text-center">นาที</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-start">
                                            เวลาเดินงานจริง
                                            <div class="fs-9 text-muted">เวลาเดินงานสุทธิเป้าหมาย (675 น.)</div>
                                        </td>
                                        <td>
                                            <input type="number" min="1" name="target_time_run" class="form-control form-control-sm text-center fw-bold text-primary" value="<?= $targetTimeRun ?>" required>
                                        </td>
                                        <td class="text-center">นาที</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-semibold text-start">
                                            ขาดจำนวนสูงสุด
                                            <div class="fs-9 text-muted">เกณฑ์ออเดอร์ขาดจำนวนที่ยอมรับได้</div>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="100" name="target_shortage_pct" class="form-control form-control-sm text-center fw-bold text-primary" value="<?= $targetShortage ?>" required>
                                        </td>
                                        <td class="text-center">%</td>
                                    </tr>
                                    <tr class="table-warning">
                                        <td class="fw-bold text-start text-danger">
                                            เวลาสูญเสียรวม (Max Loss)
                                            <div class="fs-9 text-muted">เกณฑ์เวลาสูญเสียรวมทั้งหมด</div>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="100" name="target_loss_pct" id="masterTargetLossPct" class="form-control form-control-sm text-center fw-bold text-danger" value="<?= $targetLossPct ?>" required>
                                        </td>
                                        <td class="text-center fw-bold text-danger">%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- PART 2: DYNAMIC TOPICS & DEPARTMENT LOSS TIMES TABLE     -->
            <!-- ======================================================== -->
            <div class="col-lg-7">
                <div class="card card-theme shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-list-ol text-orange me-2"></i> 2. หัวข้อ & เวลาสูญเสียรายแผนก (Loss Topics & Targets)
                            </h6>
                            <small class="text-muted fs-9">สามารถเปลี่ยนชื่อหัวข้อ เพิ่มหัวข้อใหม่ หรือลบหัวข้อที่ไม่ใช้ออกได้</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-bold" id="btnAddDepartmentRow">
                            <i class="fa-solid fa-plus me-1"></i> เพิ่มหัวข้อใหม่
                        </button>
                    </div>

                    <div class="card-body p-3">
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-hover table-sm fs-8 align-middle text-center mb-0" id="tableDepartmentEditor">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 8%;">#</th>
                                        <th class="text-start" style="width: 48%;">ชื่อหัวข้อ / แผนก (Department Name)</th>
                                        <th style="width: 24%;">Target (%)</th>
                                        <th style="width: 10%;">สถานะ</th>
                                        <th style="width: 10%;">ลบ</th>
                                    </tr>
                                </thead>
                                <tbody id="deptTableBody">
                                    <?php 
                                    $seq = 1;
                                    $runningSumTarget = 0.0;
                                    foreach ($departmentsList as $dItem): 
                                        $dName = $dItem['name'] ?? '';
                                        $dTgt  = (float)($dItem['target_pct'] ?? 0.0);
                                        $dAct  = (int)($dItem['is_active'] ?? 1);
                                        if ($dAct) $runningSumTarget += $dTgt;
                                    ?>
                                    <tr class="dept-row">
                                        <td class="text-muted fw-bold row-index"><?= $seq++ ?></td>
                                        <td class="text-start">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light text-secondary border-end-0">
                                                    <i class="fa-solid fa-tag"></i>
                                                </span>
                                                <input type="text" name="dept_name[]" class="form-control form-control-sm fw-bold border-start-0" value="<?= e($dName) ?>" placeholder="ระบุชื่อแผนก/หัวข้อ..." required>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.01" min="0" max="100" name="dept_target[]" class="form-control form-control-sm text-center fw-semibold inp-dept-target" value="<?= number_format($dTgt, 2, '.', '') ?>" required>
                                                <span class="input-group-text bg-light text-secondary">%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch d-flex justify-content-center p-0 m-0">
                                                <input class="form-check-input ms-0 chk-dept-active" type="checkbox" name="dept_active[]" value="1" <?= $dAct ? 'checked' : '' ?> title="เปิด/ปิด การใช้งานหัวข้อนี้">
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle btn-remove-row p-0 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" title="ลบหัวข้อนี้">
                                                <i class="fa-solid fa-trash fs-9"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light fw-bold fs-7">
                                    <tr>
                                        <td colspan="2" class="text-end text-dark">
                                            ผลรวมเป้าหมายแผนกทั้งหมด (Total Target %):
                                        </td>
                                        <td>
                                            <span id="lblSumDeptTarget" class="fs-6 fw-bold <?= abs($runningSumTarget - 10.0) < 0.01 ? 'text-success' : 'text-danger' ?>">
                                                <?= number_format($runningSumTarget, 2) ?>%
                                            </span>
                                        </td>
                                        <td colspan="2" class="text-center" id="lblTargetBalanceStatus">
                                            <?php if (abs($runningSumTarget - 10.0) < 0.01): ?>
                                                <span class="badge bg-success fs-9"><i class="fa-solid fa-check me-1"></i> สมดุล 10%</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark fs-9">ต่าง <?= number_format($runningSumTarget - 10.0, 2) ?>%</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Sync Options Box -->
                        <div class="bg-light p-3 rounded border">
                            <h6 class="fw-bold fs-8 text-secondary mb-2">
                                <i class="fa-solid fa-arrows-rotate text-orange me-1"></i> ตัวเลือกการนำไปใช้ (Sync Options)
                            </h6>
                            <?php if ($selectedReportId > 0): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sync_this_report" id="syncThisReport" checked disabled>
                                <label class="form-check-label fs-8 fw-bold text-dark" for="syncThisReport">
                                    อัปเดตไปยังรายงาน ID: <?= $selectedReportId ?> (<?= e($currentReport['title']) ?>) ทันที
                                </label>
                            </div>
                            <?php else: ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="sync_all_reports" id="syncAllReports" value="1">
                                <label class="form-check-label fs-8 text-danger fw-semibold" for="syncAllReports">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> ซิงค์การเปลี่ยนแปลงนี้ไปยังประวัติรายงาน BHS ทั้งหมดที่มีอยู่ในระบบด้วย (Sync to all existing reports)
                                </label>
                            </div>
                            <small class="text-muted fs-9 d-block">
                                * หากไม่ติ๊กเลือก จะเป็นการบันทึกเฉพาะแม่แบบมาตรฐาน (Master Template) ซึ่งจะมีผลต่อรายงานใหม่ๆ ที่ถูกอัปโหลดในอนาคต
                            </small>
                            <?php endif; ?>
                        </div>

                        <!-- Bottom Save Button -->
                        <div class="mt-3 text-end d-flex justify-content-between align-items-center">
                            <span id="saveStatusIndicator" class="fs-8 fw-bold"></span>
                            <button type="submit" class="btn btn-orange px-4 py-2 rounded-pill fw-bold shadow-sm">
                                <i class="fa-solid fa-save me-1"></i> บันทึกการตั้งค่าทั้งหมด (Save Settings)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- PART 3: STATION WASTE TARGETS (% ต่อ Station)            -->
            <!-- ======================================================== -->
            <div class="col-12">
                <div class="card card-theme shadow-sm border-0">
                    <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1f4e79 0%, #2e75b6 100%);">
                        <div>
                            <h6 class="fw-bold mb-0 text-white">
                                <i class="fa-solid fa-trash-can text-warning me-2"></i> 3. เป้าหมายของเสียแยก Station (Target % per Station)
                            </h6>
                            <small class="text-white-50 fs-9">ตั้งค่า Target (%) สำหรับแต่ละ Station เพื่อเปรียบเทียบในหน้ารายงาน</small>
                        </div>
                        <span class="badge bg-warning text-dark fs-8"><?= count($defaultStationsAdmin) ?> Stations</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 align-items-end">
                            <?php foreach ($defaultStationsAdmin as $st): 
                                $tPct = (float)($stationWasteTargets[$st]['target_pct'] ?? $stationWasteTargets[$st]['target_kg'] ?? 0);
                            ?>
                            <div class="col-md-2 col-sm-4 col-6">
                                <label class="form-label fs-8 fw-bold mb-1" style="color: #1f4e79;">
                                    <i class="fa-solid fa-layer-group me-1 text-warning"></i>
                                    <?= e($st) ?>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0" max="100"
                                           name="station_target_pct[<?= e($st) ?>]"
                                           class="form-control form-control-sm text-center fw-bold inp-station-target"
                                           value="<?= $tPct > 0 ? number_format($tPct, 2, '.', '') : '' ?>"
                                           placeholder="0.00"
                                           style="font-size: 0.95rem; height: 38px;">
                                    <span class="input-group-text bg-light text-secondary fs-9">%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="col-md-2 col-sm-4 col-6 d-flex align-items-end">
                                <div class="bg-light rounded p-2 text-center w-100 border" style="height: 38px; line-height: 20px;">
                                    <span class="fs-9 text-muted">รวม: </span>
                                    <span class="fw-bold text-primary" id="lblStationTargetSum">
                                        <?= number_format(array_sum(array_column(array_map(fn($st) => ['v' => (float)($stationWasteTargets[$st]['target_pct'] ?? $stationWasteTargets[$st]['target_kg'] ?? 0)], $defaultStationsAdmin), 'v')), 2) ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-muted fs-9">
                            <i class="fa-solid fa-circle-info text-primary me-1"></i>
                            ค่าที่กำหนดตรงนี้จะแสดงในคอลัมน์ <strong>Target (%)</strong> ของตาราง "ของเสียแยก Station" ในหน้ารายงาน
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>

</div>

<!-- Modal: คืนค่ามาตรฐานโรงงาน (Reset Defaults Confirmation) -->
<div class="modal fade" id="modalResetDefaults" tabindex="-1" aria-labelledby="modalResetDefaultsLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= BASE_URL ?>/actions/report_action.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="reset_target_defaults">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="template_id" value="<?= (int)($template['id'] ?? 1) ?>">

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="modalResetDefaultsLabel">
                    <i class="fa-solid fa-rotate-left me-2"></i> ยืนยันคืนค่ามาตรฐานโรงงาน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2 text-dark">
                    คุณแน่ใจหรือไม่ว่าต้องการคืนค่าเป้าหมายและหัวข้อเป็น <strong>12 แผนกมาตรฐานโรงงาน</strong>?
                </p>
                <div class="bg-light p-3 rounded border fs-8 text-secondary">
                    <div>• Target Orders = 120 | Target Meters = 68,056 | Speed = 100</div>
                    <div>• 12 แผนก: ผลิต (0%), MC (3%), ไฟฟ้า (1%), คลังม้วน (0%), ม้วน (0%), Utiliy (0%), Packking (1%), กาว (0%), วางแผน (1%), จัดส่ง (0%), เปลี่ยนลอน (2%), PM (2%)</div>
                    <div class="fw-bold text-danger mt-1">• ผลรวมเวลาสูญเสียมาตรฐาน = 10.00%</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold">
                    <i class="fa-solid fa-check me-1"></i> ยืนยันคืนค่า
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Dynamic Row Addition & Interactive Target Calculator Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('deptTableBody');
    const btnAdd = document.getElementById('btnAddDepartmentRow');
    const lblSum = document.getElementById('lblSumDeptTarget');
    const lblStatus = document.getElementById('lblTargetBalanceStatus');
    const inpMasterLoss = document.getElementById('masterTargetLossPct');

    // 1. คำนวณผลรวม Target % ของแผนกแบบ Real-Time
    function calculateDeptTargetSum() {
        let total = 0.0;
        const rows = tableBody.querySelectorAll('.dept-row');
        
        rows.forEach((row, idx) => {
            // อัปเดตเลขลำดับ #
            const seqEl = row.querySelector('.row-index');
            if (seqEl) seqEl.textContent = (idx + 1);

            const tgtInp = row.querySelector('.inp-dept-target');
            const chkActive = row.querySelector('.chk-dept-active');

            if (chkActive && chkActive.checked && tgtInp) {
                const val = parseFloat(tgtInp.value) || 0.0;
                total += val;
            }
        });

        if (lblSum) {
            lblSum.textContent = total.toFixed(2) + '%';
            if (Math.abs(total - 10.0) < 0.01) {
                lblSum.className = 'fs-6 fw-bold text-success';
                if (lblStatus) {
                    lblStatus.innerHTML = '<span class="badge bg-success fs-9"><i class="fa-solid fa-check me-1"></i> สมดุล 10%</span>';
                }
            } else {
                lblSum.className = 'fs-6 fw-bold text-danger';
                const diff = (total - 10.0).toFixed(2);
                if (lblStatus) {
                    lblStatus.innerHTML = `<span class="badge bg-warning text-dark fs-9">ต่าง ${diff > 0 ? '+' : ''}${diff}%</span>`;
                }
            }
        }

        // ซิงค์ไปยังช่องเป้าหมายเวลาสูญเสียรวม หากต้องการ
        if (inpMasterLoss && total > 0 && (!inpMasterLoss.value || parseFloat(inpMasterLoss.value) === 0)) {
            inpMasterLoss.value = total.toFixed(2);
        }
    }

    // 2. ตรวจจับการเปลี่ยนแปลงตัวเลขหรือสถานะในตาราง
    tableBody.addEventListener('input', function (e) {
        if (e.target.matches('.inp-dept-target, .chk-dept-active')) {
            calculateDeptTargetSum();
        }
    });

    tableBody.addEventListener('change', function (e) {
        if (e.target.matches('.inp-dept-target, .chk-dept-active')) {
            calculateDeptTargetSum();
        }
    });

    // 3. ปุ่มลบแถว (Delete Row)
    tableBody.addEventListener('click', function (e) {
        const delBtn = e.target.closest('.btn-remove-row');
        if (delBtn) {
            const rows = tableBody.querySelectorAll('.dept-row');
            if (rows.length <= 1) {
                alert('ต้องมีหัวข้อแผนกอย่างน้อย 1 รายการ');
                return;
            }
            const row = delBtn.closest('.dept-row');
            if (row) {
                const dName = row.querySelector('input[name="dept_name[]"]')?.value || 'หัวข้อนี้';
                if (confirm(`คุณต้องการลบหัวข้อ "${dName}" ออกจากตารางหรือไม่?`)) {
                    row.remove();
                    calculateDeptTargetSum();
                }
            }
        }
    });

    // 4. ปุ่มเพิ่มแถวใหม่ (Add Department Row)
    if (btnAdd) {
        btnAdd.addEventListener('click', function () {
            const rowCount = tableBody.querySelectorAll('.dept-row').length + 1;
            const newTr = document.createElement('tr');
            newTr.className = 'dept-row bg-white';
            newTr.innerHTML = `
                <td class="text-muted fw-bold row-index">${rowCount}</td>
                <td class="text-start">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-secondary border-end-0">
                            <i class="fa-solid fa-tag"></i>
                        </span>
                        <input type="text" name="dept_name[]" class="form-control form-control-sm fw-bold border-start-0" value="แผนกใหม่ ${rowCount}" placeholder="ระบุชื่อแผนก/หัวข้อ..." required>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" min="0" max="100" name="dept_target[]" class="form-control form-control-sm text-center fw-semibold inp-dept-target" value="0.00" required>
                        <span class="input-group-text bg-light text-secondary">%</span>
                    </div>
                </td>
                <td>
                    <div class="form-check form-switch d-flex justify-content-center p-0 m-0">
                        <input class="form-check-input ms-0 chk-dept-active" type="checkbox" name="dept_active[]" value="1" checked title="เปิด/ปิด การใช้งานหัวข้อนี้">
                    </div>
                </td>
                <td>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-circle btn-remove-row p-0 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" title="ลบหัวข้อนี้">
                        <i class="fa-solid fa-trash fs-9"></i>
                    </button>
                </td>
            `;

            tableBody.appendChild(newTr);
            calculateDeptTargetSum();

            // Focus ที่ช่องชื่อแผนกใหม่ทันที
            const newInp = newTr.querySelector('input[name="dept_name[]"]');
            if (newInp) {
                newInp.focus();
                newInp.select();
            }
        });
    }

    // 5. ปุ่มบันทึกด้านบน
    const btnSubmitTop = document.querySelector('.btn-submit-all');
    if (btnSubmitTop) {
        btnSubmitTop.addEventListener('click', function () {
            document.getElementById('formAdminTargets')?.submit();
        });
    }

    // 6. Station Waste Target — คำนวณผลรวม Real-Time (%)
    const lblStationSum = document.getElementById('lblStationTargetSum');
    function calcStationTargetSum() {
        let total = 0.0;
        document.querySelectorAll('.inp-station-target').forEach(inp => {
            total += parseFloat(inp.value) || 0.0;
        });
        if (lblStationSum) lblStationSum.textContent = total.toFixed(2) + '%';
    }
    document.querySelectorAll('.inp-station-target').forEach(inp => {
        inp.addEventListener('input', calcStationTargetSum);
    });
    calcStationTargetSum();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
