<?php
/**
 * Master BHS Shift Performance Report View
 * แสดงผลครบถ้วนตามแบบภาพรายงาน BHS พร้อมระบบบันทึกภาพ HD และฟิลเตอร์
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$reportId = (int)($_GET['id'] ?? 0);
if ($reportId <= 0) {
    set_flash('warning', 'กรุณาระบุรหัสรายงานที่ต้องการดู');
    redirect('views/dashboard.php');
}

$pdo = Database::getConnection();

// 1. ดึงข้อมูลหัวรายงาน
$stmtReport = $pdo->prepare("SELECT * FROM bhs_shift_reports WHERE id = :id LIMIT 1");
$stmtReport->execute([':id' => $reportId]);
$report = $stmtReport->fetch();

if (!$report) {
    set_flash('danger', 'ไม่พบข้อมูลรายงานที่ต้องการ');
    redirect('views/dashboard.php');
}

// 2. ดึงรายการแถวข้อมูลการผลิตทั้งหมด
$stmtRecs = $pdo->prepare("SELECT * FROM bhs_production_records WHERE report_id = :id ORDER BY seq_no ASC");
$stmtRecs->execute([':id' => $reportId]);
$rawRecords = $stmtRecs->fetchAll();

// 3. รับค่าตัวกรองจาก URL (Query String)
$filterShift = sanitize_text($_GET['shift'] ?? '');
$filterFlute = sanitize_text($_GET['flute'] ?? '');
$filterWo    = sanitize_text($_GET['wo_prefix'] ?? '');
$filterWidth = (float)($_GET['width'] ?? 0);

$filteredRecords = [];
foreach ($rawRecords as $r) {
    if ($filterShift !== '' && $r['shift'] !== $filterShift) continue;
    if ($filterFlute !== '' && $r['flute'] !== $filterFlute) continue;
    if ($filterWo !== '' && $r['wo_prefix'] !== $filterWo) continue;
    if ($filterWidth > 0 && (float)$r['paper_width_mm'] != $filterWidth) continue;
    $filteredRecords[] = $r;
}

// 4. คำนวณสรุปผลตาม Engine
$summary = BhsEngine::computeReportSummary($filteredRecords);

// รายการเวลาสูญเสียรายแผนกจาก Database หรือค่าเริ่มต้น
$lossData = !empty($report['loss_details']) ? json_decode($report['loss_details'], true) : BhsEngine::getDefaultLossDepartments();
if (!$lossData) $lossData = BhsEngine::getDefaultLossDepartments();

// รวมเวลาสูญเสียและคะแนน
$totalLossOccurrences = 0;
$totalLossMinutes = 0;
$totalScore = 0;
$totalTargetLossPct = 0.0;
foreach ($lossData as $dept => $info) {
    $totalLossOccurrences += (int)($info['occurrences'] ?? 0);
    $totalLossMinutes     += (int)($info['minutes'] ?? 0);
    $totalScore           += (int)($info['score'] ?? 3);
    $totalTargetLossPct   += (float)($info['target_pct'] ?? 0.0);
}
if ($totalTargetLossPct <= 0) $totalTargetLossPct = 10.0;

// น้ำหนักผลิตรวม
$totFluteWeight = (float)($summary['total_weight'] ?? 0);

// ข้อมูลของเสียแยก Station (5 Stations: SF-C, SF-B, DF, Control, Stacker)
$stationWasteData = !empty($report['station_waste']) ? json_decode($report['station_waste'], true) : [];
$defaultStations  = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];
$stationWasteKg   = [];
$totalStationWasteKg = 0.0;
foreach ($defaultStations as $st) {
    $val = 0.0;
    if (isset($stationWasteData[$st])) {
        $val = is_array($stationWasteData[$st]) ? (float)($stationWasteData[$st]['kg'] ?? 0) : (float)$stationWasteData[$st];
    }
    $stationWasteKg[$st] = $val;
    $totalStationWasteKg += $val;
}

// โหลด Station Waste Targets จาก bhs_target_templates (default template)
$stationWasteTargets = [];
try {
    $stmtSwt = $pdo->query("SELECT station_waste_targets FROM bhs_target_templates WHERE template_name='default' LIMIT 1");
    $rowSwt = $stmtSwt ? $stmtSwt->fetch() : null;
    if ($rowSwt && !empty($rowSwt['station_waste_targets'])) {
        $stationWasteTargets = json_decode($rowSwt['station_waste_targets'], true) ?: [];
    }
} catch (Exception $e) { /* ยังไม่มี column ก็ไม่เป็นไร */ }

// ข้อมูล KPI บนซ้าย
$totalOrdersActual = $summary['total_orders'];
$totalMetersActual = $summary['total_meters'];
$avgMetersActual   = $summary['avg_meters_per_order'];
$avgSpeedGross     = $summary['avg_speed_gross'];
$avgSpeedNet       = $summary['avg_speed_net'];

// KPI Inputs จากตาราง
$orderStart     = (int)($report['order_start'] ?? 10);
$orderFinit     = (int)($report['order_finit'] ?? 1120);
$orderPlanTotal = (int)($report['order_plan_total'] ?? 120);
$insertedMeters = (float)($report['inserted_meters'] ?? 0.0);
$pendingMeters  = (float)($report['pending_meters'] ?? 0.0);
$shortageOrders = (int)($report['shortage_orders'] ?? 24);

// KPI Targets
$targetOrders    = (int)($report['target_orders'] ?: 120);
$targetMeters    = (float)($report['target_meters'] ?: 68056);
$targetSpeed     = (float)($report['target_speed'] ?: 100);
$targetAvgMeters = (float)($report['target_avg_meters'] ?: 500);

// เวลาเริ่มเดิน และ เวลาจบ
$shiftStartTime = $report['shift_start_time'] ?? '20:00';
$shiftEndTime   = $report['shift_end_time'] ?? '07:40';

// คำนวณเวลาเดินทั้งหมดเป็นนาทีเสมอ จาก (เวลาจบ - เวลาเริ่ม)
$actualShiftDuration = (int)($report['actual_time_total'] ?? 0);
if ($actualShiftDuration <= 0) {
    $actualShiftDuration = BhsEngine::calculateShiftDurationMinutes($shiftStartTime, $shiftEndTime);
}

$targetShiftTotalTime = (int)($report['target_time_total'] ?: 720);
$targetTimeRun        = (int)($report['target_time_run'] ?: 675);

// เวลาเดินงานจริง = เวลาเดินทั้งหมด - เวลาสูญเสียรวม
$runningTimeMin = max(0, $actualShiftDuration - $totalLossMinutes);
$runningPct     = ($targetTimeRun > 0) ? round(($runningTimeMin / $targetTimeRun) * 100) : 0;
$shiftTotalPct  = ($targetShiftTotalTime > 0) ? round(($actualShiftDuration / $targetShiftTotalTime) * 100) : 100;
$lossTotalPct   = ($actualShiftDuration > 0) ? round(($totalLossMinutes / $actualShiftDuration) * 100, 2) : 0;

// คำนวณ Order Plan % และ คะแนน (สูตร: %Actual = ช่องรวม / ช่อง Target (120), >= 100% ได้ 3)
$orderPlanPct   = ($targetOrders > 0) ? round(($orderPlanTotal / $targetOrders) * 100) : 0;
$orderPlanScore = ($orderPlanPct >= 100) ? 3 : 1;

// คำนวณ ขาดจำนวน % และ คะแนน (สูตร: %ขาดจำนวน = ขาดจำนวน (order) / ออเดอร์ที่เดินได้ทั้งหมดในกะ * 100%)
$shortagePct   = ($totalOrdersActual > 0) ? round(($shortageOrders / $totalOrdersActual) * 100) : 0;
$shortageScore = ($shortagePct <= 15) ? 3 : 1;

// คำนวณ Speed จริงตามสูตร
if ($runningTimeMin > 0 && $totalMetersActual > 0) {
    $avgSpeedNet = round($totalMetersActual / $runningTimeMin);
}
if ($actualShiftDuration > 0 && $totalMetersActual > 0) {
    $avgSpeedGross = round($totalMetersActual / $actualShiftDuration);
}



$pageTitle = e($report['title']);
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Top Action Toolbar (No-Capture) -->
<div class="row align-items-center mb-3 no-capture">
    <div class="col-lg-6">
        <h4 class="fw-bold mb-1 text-dark">
            <i class="fa-solid fa-file-waveform text-orange me-2"></i> <?= e($report['title']) ?>
        </h4>
        <div class="text-muted fs-7">
            <span><i class="fa-regular fa-calendar me-1"></i> วันที่: <strong><?= format_thai_date($report['report_date']) ?></strong></span>
            <span class="ms-3"><i class="fa-regular fa-clock me-1"></i> ตัดกะ: <strong><?= e($report['shift_end_time']) ?></strong></span>
            <span class="ms-3"><i class="fa-solid fa-list-ol me-1"></i> ข้อมูล: <strong><?= count($filteredRecords) ?> / <?= count($rawRecords) ?> รายการ</strong></span>
        </div>
    </div>
    <div class="col-lg-6 text-lg-end mt-2 mt-lg-0 d-flex flex-wrap gap-2 justify-content-lg-end">
        <!-- ปุ่มบันทึกข้อมูลตาราง (Save KPI Data) -->
        <button type="button" class="btn btn-success rounded-pill px-3 shadow-sm fw-bold btn-save-action" id="btnSaveInlineKpiTop">
            <i class="fa-solid fa-floppy-disk me-1"></i> 💾 บันทึกข้อมูลตาราง (Save Data)
        </button>

        <!-- ปุ่มส่งข้อมูลเข้า Google Sheets -->
        <button type="button" class="btn btn-outline-success rounded-pill px-3 shadow-sm fw-bold" id="btnSyncGoogleSheet" data-report-id="<?= $reportId ?>" title="ส่งข้อมูลรายงานนี้ไปยัง Google Sheets">
            <i class="fa-solid fa-table me-1"></i> 📊 ส่งไป Google Sheets
        </button>

        <!-- ปุ่มบันทึกภาพ HD (Export High-Definition Image) -->
        <button type="button" class="btn btn-orange rounded-pill px-3 shadow-sm fw-bold" id="btnSaveHdImage">
            <i class="fa-solid fa-camera me-1"></i> 📸 บันทึกภาพแบบ HD (Save HD Image)
        </button>

        <!-- ปุ่มแก้ไข KPI / เวลาสูญเสีย -->
        <button type="button" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEditLoss">
            <i class="fa-solid fa-pen-to-square me-1"></i> แก้ไขฟอร์มละเอียด
        </button>

        <?php if (is_admin()): ?>
        <!-- ปุ่มจัดการ Target & หัวข้อสำหรับผู้ดูแลระบบ -->
        <a href="<?= BASE_URL ?>/views/admin_targets.php?report_id=<?= $reportId ?>" class="btn btn-outline-primary rounded-pill px-3 shadow-sm fw-bold">
            <i class="fa-solid fa-sliders me-1"></i> จัดการ Target & หัวข้อ
        </a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/views/dashboard.php" class="btn btn-outline-dark rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> กลับ
        </a>
    </div>
</div>

<!-- Filter Bar (No-Capture) -->
<div class="card card-theme shadow-sm border-0 mb-3 no-capture">
    <div class="card-body p-3">
        <form method="GET" action="<?= BASE_URL ?>/views/report_view.php" class="row g-2 align-items-end">
            <input type="hidden" name="id" value="<?= $reportId ?>">
            
            <div class="col-sm-6 col-md-3">
                <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                    <i class="fa-solid fa-filter text-orange me-1"></i> กรองกะ (Shift)
                </label>
                <select name="shift" class="form-select form-select-sm">
                    <option value="">ทั้งหมด (All Shifts)</option>
                    <option value="A" <?= $filterShift === 'A' ? 'selected' : '' ?>>กะ A</option>
                    <option value="B" <?= $filterShift === 'B' ? 'selected' : '' ?>>กะ B</option>
                    <option value="C" <?= $filterShift === 'C' ? 'selected' : '' ?>>กะ C</option>
                </select>
            </div>

            <div class="col-sm-6 col-md-3">
                <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                    <i class="fa-solid fa-cubes text-orange me-1"></i> กรองลอน (Flute)
                </label>
                <select name="flute" class="form-select form-select-sm">
                    <option value="">ทุกลอน (All Flutes)</option>
                    <option value="B" <?= $filterFlute === 'B' ? 'selected' : '' ?>>ลอน B</option>
                    <option value="C" <?= $filterFlute === 'C' ? 'selected' : '' ?>>ลอน C</option>
                    <option value="BC" <?= $filterFlute === 'BC' ? 'selected' : '' ?>>ลอน BC</option>
                    <option value="A" <?= $filterFlute === 'A' ? 'selected' : '' ?>>ลอน A</option>
                    <option value="AB" <?= $filterFlute === 'AB' ? 'selected' : '' ?>>ลอน AB</option>
                    <option value="E" <?= $filterFlute === 'E' ? 'selected' : '' ?>>ลอน E</option>
                </select>
            </div>

            <div class="col-sm-6 col-md-3">
                <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                    <i class="fa-solid fa-hashtag text-orange me-1"></i> ประเภทงาน (Wo Prefix)
                </label>
                <select name="wo_prefix" class="form-select form-select-sm">
                    <option value="">ทุกประเภท (All Wo)</option>
                    <option value="PDR" <?= $filterWo === 'PDR' ? 'selected' : '' ?>>PDR</option>
                    <option value="PDW" <?= $filterWo === 'PDW' ? 'selected' : '' ?>>PDW</option>
                    <option value="PDF" <?= $filterWo === 'PDF' ? 'selected' : '' ?>>PDF</option>
                    <option value="PDS" <?= $filterWo === 'PDS' ? 'selected' : '' ?>>PDS</option>
                    <option value="PDE" <?= $filterWo === 'PDE' ? 'selected' : '' ?>>PDE</option>
                </select>
            </div>

            <div class="col-sm-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-orange btn-sm w-100 fw-bold">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> กรองข้อมูล
                </button>
                <a href="<?= BASE_URL ?>/views/report_view.php?id=<?= $reportId ?>" class="btn btn-outline-secondary btn-sm" title="ล้างตัวกรอง">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================================= -->
<!-- REPORT MASTER CAPTURE AREA (จะถูกถ่ายภาพ HD เมื่อกดปุ่ม) -->
<!-- ========================================================================================= -->
<div id="hdReportCaptureArea" class="bg-white border rounded shadow-sm p-3">

    <!-- Report Header Banner (Exactly matching top header in image) -->
    <div class="row align-items-center mb-2 px-1">
        <div class="col-md-5 d-flex align-items-center mb-2 mb-md-0">
            <span class="badge <?= ($report['machine_name'] ?? 'BHS') === 'YUELI' ? 'bg-success' : (($report['machine_name'] ?? 'BHS') === 'ISOWA' ? 'bg-dark' : 'bg-primary') ?> fs-7 me-2 px-2 py-1">
                <i class="fa-solid fa-industry me-1"></i><?= e($report['machine_name'] ?? 'BHS') ?>
            </span>
            <h4 class="fw-bold mb-0 text-dark" id="reportTitleHeader" style="letter-spacing: -0.5px;">
                <?= e($report['title']) ?>
            </h4>
        </div>
        <div class="col-md-4 text-center mb-2 mb-md-0">
            <h5 class="fw-bold mb-0 text-dark">
                <?= format_thai_date($report['report_date']) ?>
            </h5>
            <div class="fs-8 text-secondary">
                เวลาเดิน: <strong><?= e($shiftStartTime) ?> - <?= e($shiftEndTime) ?></strong> 
                (<span id="lblDurationBadge" class="text-danger fw-bold"><?= $actualShiftDuration ?> นาที</span>)
            </div>
        </div>
        <div class="col-md-3 text-end">
            <div class="d-inline-flex align-items-center border border-dark px-2 py-1 bg-white">
                <span class="fs-8 fw-bold me-2">เวลาจบ/ตัดกะ</span>
                <span class="fs-6 fw-bold text-dark"><?= e($report['shift_end_time']) ?></span>
            </div>
        </div>
    </div>

    <!-- MAIN GRID 2 COLUMNS -->
    <div class="row g-2">

        <!-- ========================================== -->
        <!-- LEFT COLUMN: KPI & Loss Table & Flute Table -->
        <!-- ========================================== -->
        <div class="col-lg-6">

            <!-- Section 1: KPI Department & Loss Times Table (Red/Orange/Pink Theme from Image) -->
            <form id="formInlineKpi" method="POST" action="<?= BASE_URL ?>/actions/report_action.php">
                <input type="hidden" name="action" value="update_kpi">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="report_id" value="<?= $reportId ?>">
                <input type="hidden" name="is_ajax" value="1">
                <input type="hidden" name="shift_start_time" id="hiddenShiftStartTime" value="<?= e($shiftStartTime) ?>">
                <input type="hidden" name="shift_end_time" id="hiddenShiftEndTime" value="<?= e($shiftEndTime) ?>">

                <!-- Hidden Reference Values for JS Math Engine -->
                <input type="hidden" id="valTotalOrdersActual" value="<?= $totalOrdersActual ?>">
                <input type="hidden" id="valTotalMetersActual" value="<?= $totalMetersActual ?>">
                <input type="hidden" id="valTargetOrders" value="<?= $targetOrders ?>">
                <input type="hidden" id="valTargetMeters" value="<?= $targetMeters ?>">
                <input type="hidden" id="valTargetSpeed" value="<?= $targetSpeed ?>">
                <input type="hidden" id="valTargetAvgMeters" value="<?= $targetAvgMeters ?>">
                <input type="hidden" id="valTargetShiftTotalTime" value="<?= $targetShiftTotalTime ?>">
                <input type="hidden" id="valTargetTimeRun" value="<?= $targetTimeRun ?>">
                <input type="hidden" id="valTotalProductionWeight" value="<?= $totFluteWeight ?>">

                <!-- Table Header Actions (No-Capture) -->
                <div class="d-flex justify-content-between align-items-center mb-1 px-1 no-capture">
                    <span class="fs-8 text-secondary">
                        <i class="fa-solid fa-pen-to-square text-orange me-1"></i> กรอกเวลาสูญเสียและข้อมูลได้โดยตรงบนตาราง
                    </span>
                    <div class="d-flex align-items-center">
                        <span id="saveStatusMsg" class="fs-8 fw-bold me-2"></span>
                        <button type="button" class="btn btn-sm btn-orange fw-bold rounded-pill px-3 shadow-sm btn-save-action" id="btnSaveInlineKpiTable">
                            <i class="fa-solid fa-floppy-disk me-1"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </div>

                <div class="table-responsive mb-2">
                    <table class="table-bhs text-center" id="tableKpiMaster">
                        <thead>
                            <tr>
                                <th class="th-kpi-main text-start" style="width: 32%;">แผนก</th>
                                <th class="th-kpi-green" style="width: 14%;">Target</th>
                                <th class="th-kpi-green" style="width: 28%;" colspan="3">Actual</th>
                                <th class="th-kpi-green" style="width: 10%;">หน่วย</th>
                                <th class="th-kpi-green" style="width: 8%;">% Actual</th>
                                <th class="th-kpi-green" style="width: 8%;">คะแนน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Order Start / Finish -->
                            <tr>
                                <td class="td-kpi-pink text-start fw-semibold">Order Start/Order Finit จากแผน</td>
                                <td class="td-kpi-pink fw-bold text-primary"><?= $targetOrders ?></td>
                                <td class="td-kpi-pink fs-8">
                                    Start<br>
                                    <input type="number" name="order_start" id="inpOrderStart" class="table-input-cell fw-bold text-dark" value="<?= $orderStart ?>" style="width: 48px;">
                                </td>
                                <td class="td-kpi-pink fs-8">
                                    Finit<br>
                                    <input type="number" name="order_finit" id="inpOrderFinit" class="table-input-cell fw-bold text-dark" value="<?= $orderFinit ?>" style="width: 54px;">
                                </td>
                                <td class="td-kpi-pink fs-8">
                                    รวม<br>
                                    <input type="number" name="order_plan_total" id="inpOrderPlanTotal" class="table-input-cell fw-bold text-danger" value="<?= $orderPlanTotal ?>" style="width: 48px;">
                                </td>
                                <td class="td-kpi-pink">Order</td>
                                <td class="td-kpi-pink fw-bold" id="lblOrderPlanPct"><?= $orderPlanPct ?>%</td>
                                <td class="td-kpi-pink fw-bold <?= $orderPlanScore >= 3 ? 'text-success' : 'text-danger' ?>" id="lblOrderPlanScore"><?= $orderPlanScore ?></td>
                            </tr>

                            <tr>
                                <td class="td-kpi-pink text-start">จำนวนออเดอร์ที่เดินทั้งหมด+ซ่อม</td>
                                <td class="td-kpi-pink text-primary fw-bold"><?= $targetOrders ?></td>
                                <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblTotalOrdersActual"><?= $totalOrdersActual ?></td>
                                <td class="td-kpi-pink">Order</td>
                                <td class="td-kpi-pink fw-bold" id="lblTotalOrdersPct"><?= $targetOrders > 0 ? round(($totalOrdersActual / $targetOrders) * 100) : 0 ?>%</td>
                                <td class="td-kpi-pink fw-bold <?= ($totalOrdersActual >= $targetOrders) ? 'text-success' : 'text-danger' ?>" id="lblTotalOrdersScore"><?= ($totalOrdersActual >= $targetOrders) ? 3 : 1 ?></td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start">ผลิตได้</td>
                                <td class="td-kpi-pink text-primary fw-bold"><?= number_format($targetMeters) ?></td>
                                <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblTotalMetersActual"><?= number_format($totalMetersActual) ?></td>
                                <td class="td-kpi-pink">เมตร</td>
                                <td class="td-kpi-pink fw-bold" id="lblTotalMetersPct"><?= $targetMeters > 0 ? round(($totalMetersActual / $targetMeters) * 100) : 0 ?>%</td>
                                <td class="td-kpi-pink fw-bold <?= ($totalMetersActual >= $targetMeters) ? 'text-success' : 'text-danger' ?>" id="lblTotalMetersScore"><?= ($totalMetersActual >= $targetMeters) ? 3 : 1 ?></td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start">งานแทรก</td>
                                <td class="td-kpi-pink">-</td>
                                <td class="td-kpi-pink" colspan="3">
                                    <input type="number" step="any" min="0" name="inserted_meters" id="inpInsertedMeters" class="table-input-cell" value="<?= $insertedMeters ?>" style="width: 70px;">
                                </td>
                                <td class="td-kpi-pink">เมตร</td>
                                <td class="td-kpi-pink">0%</td>
                                <td class="td-kpi-pink fw-bold text-success">3</td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start">งานค้าง/ไม่ค้าง</td>
                                <td class="td-kpi-pink">0</td>
                                <td class="td-kpi-pink" colspan="3">
                                    <input type="number" step="any" min="0" name="pending_meters" id="inpPendingMeters" class="table-input-cell" value="<?= $pendingMeters ?>" style="width: 70px;">
                                </td>
                                <td class="td-kpi-pink">เมตร</td>
                                <td class="td-kpi-pink">0%</td>
                                <td class="td-kpi-pink fw-bold text-success">3</td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start">เมตรเฉลี่ย/order</td>
                                <td class="td-kpi-pink text-primary fw-bold"><?= number_format($targetAvgMeters) ?></td>
                                <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblAvgMetersActual"><?= number_format($avgMetersActual) ?></td>
                                <td class="td-kpi-pink">เมตร</td>
                                <td class="td-kpi-pink fw-bold" id="lblAvgMetersPct"><?= $targetAvgMeters > 0 ? round(($avgMetersActual / $targetAvgMeters) * 100) : 0 ?>%</td>
                                <td class="td-kpi-pink fw-bold text-danger" id="lblAvgMetersScore">1</td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start">Speed เฉลี่ยหักสูญเสีย</td>
                                <td class="td-kpi-pink text-primary fw-bold"><?= number_format($targetSpeed) ?></td>
                                <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblAvgSpeedNet"><?= number_format($avgSpeedNet) ?></td>
                                <td class="td-kpi-pink">M/Min</td>
                                <td class="td-kpi-pink fw-bold" id="lblAvgSpeedNetPct"><?= $targetSpeed > 0 ? round(($avgSpeedNet / $targetSpeed) * 100) : 0 ?>%</td>
                                <td class="td-kpi-pink fw-bold <?= ($avgSpeedNet >= $targetSpeed) ? 'text-success' : 'text-danger' ?>" id="lblAvgSpeedNetScore"><?= ($avgSpeedNet >= $targetSpeed) ? 3 : 1 ?></td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start">Speed เฉลี่ยไม่หักเวลาสูญเสีย</td>
                                <td class="td-kpi-pink text-primary fw-bold"><?= number_format($targetSpeed) ?></td>
                                <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblAvgSpeedGross"><?= number_format($avgSpeedGross) ?></td>
                                <td class="td-kpi-pink">M/Min</td>
                                <td class="td-kpi-pink fw-bold" id="lblAvgSpeedGrossPct"><?= $targetSpeed > 0 ? round(($avgSpeedGross / $targetSpeed) * 100) : 0 ?>%</td>
                                <td class="td-kpi-pink fw-bold <?= ($avgSpeedGross >= $targetSpeed) ? 'text-success' : 'text-danger' ?>" id="lblAvgSpeedGrossScore"><?= ($avgSpeedGross >= $targetSpeed) ? 3 : 1 ?></td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start">ขาดจำนวน</td>
                                <td class="td-kpi-pink text-primary fw-bold">15%</td>
                                <td class="td-kpi-pink fw-bold text-danger" colspan="3">
                                    <input type="number" min="0" name="shortage_orders" id="inpShortageOrders" class="table-input-cell fw-bold text-danger" value="<?= $shortageOrders ?>" style="width: 60px;">
                                </td>
                                <td class="td-kpi-pink">Order</td>
                                <td class="td-kpi-pink fw-bold" id="lblShortagePct"><?= $shortagePct ?>%</td>
                                <td class="td-kpi-pink fw-bold <?= $shortageScore >= 3 ? 'text-success' : 'text-danger' ?>" id="lblShortageScore"><?= $shortageScore ?></td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start">เวลาเดินทั้งหมด</td>
                                <td class="td-kpi-pink text-primary fw-bold"><?= $targetShiftTotalTime ?></td>
                                <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblActualShiftDuration"><?= $actualShiftDuration ?></td>
                                <td class="td-kpi-pink">นาที</td>
                                <td class="td-kpi-pink fw-bold" id="lblShiftTotalPct"><?= $shiftTotalPct ?>%</td>
                                <td class="td-kpi-pink fw-bold text-success" id="lblShiftTotalScore"><?= $actualShiftDuration >= 700 ? 3 : 1 ?></td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start">เวลาเดินงานจริง</td>
                                <td class="td-kpi-pink text-primary fw-bold"><?= $targetTimeRun ?></td>
                                <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblRunningTimeMin"><?= $runningTimeMin ?></td>
                                <td class="td-kpi-pink">นาที</td>
                                <td class="td-kpi-pink fw-bold" id="lblRunningPct"><?= $runningPct ?>%</td>
                                <td class="td-kpi-pink fw-bold <?= ($runningTimeMin >= $targetTimeRun) ? 'text-success' : 'text-danger' ?>" id="lblRunningScore"><?= ($runningTimeMin >= $targetTimeRun) ? 3 : 1 ?></td>
                            </tr>
                            <tr>
                                <td class="td-kpi-pink text-start fw-bold">เวลาสูญเสียรวม</td>
                                <td class="td-kpi-pink text-primary fw-bold" id="lblTargetLossPctTop"><?= number_format($totalTargetLossPct, 2) ?>%</td>
                                <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblTotalLossMinutesTop"><?= $totalLossMinutes ?></td>
                                <td class="td-kpi-pink">นาที</td>
                                <td class="td-kpi-pink fw-bold text-danger" id="lblLossTotalPctTop"><?= number_format($lossTotalPct, 2) ?>%</td>
                                <td class="td-kpi-pink fw-bold <?= ($lossTotalPct <= $totalTargetLossPct) ? 'text-success' : 'text-danger' ?>" id="lblLossTotalScoreTop"><?= ($lossTotalPct <= $totalTargetLossPct) ? 3 : 1 ?></td>
                            </tr>

                            <!-- Loss Times Breakdown (ผลิต, MC, ไฟฟ้า, ... PM) - ลงเองได้ทั้งหมดตามต้องการ -->
                            <?php foreach ($lossData as $deptName => $loss): 
                                $deptMin = (int)($loss['minutes'] ?? 0);
                                $deptOcc = (int)($loss['occurrences'] ?? 0);
                                $deptTgt = (float)($loss['target_pct'] ?? 0.0);
                                $deptSco = (int)($loss['score'] ?? 3);
                                $deptActualPct = ($actualShiftDuration > 0) ? round(($deptMin / $actualShiftDuration) * 100, 2) : 0;
                            ?>
                            <tr class="row-loss-dept" data-dept="<?= e($deptName) ?>">
                                <td class="td-kpi-pink text-start fw-semibold"><?= e($deptName) ?></td>
                                <td class="td-kpi-pink text-primary fs-8">
                                    <input type="number" step="0.01" min="0" class="table-input-cell inp-dept-tgt text-primary" name="loss_tgt[<?= e($deptName) ?>]" value="<?= number_format($deptTgt, 2) ?>" style="width: 48px;">%
                                </td>
                                <td class="td-kpi-pink fs-8">
                                    <input type="number" min="0" class="table-input-cell inp-dept-occ" name="loss_occ[<?= e($deptName) ?>]" value="<?= $deptOcc > 0 ? $deptOcc : '' ?>" placeholder="-" style="width: 42px;">
                                </td>
                                <td class="td-kpi-pink fs-8">ครั้ง</td>
                                <td class="td-kpi-pink fs-8">
                                    <input type="number" min="0" class="table-input-cell inp-dept-min <?= $deptMin > 0 ? 'text-danger fw-bold' : '' ?>" name="loss_min[<?= e($deptName) ?>]" value="<?= $deptMin > 0 ? $deptMin : '' ?>" placeholder="-" style="width: 42px;">
                                </td>
                                <td class="td-kpi-pink fs-8">นาที</td>
                                <td class="td-kpi-pink fs-8 lbl-dept-pct <?= $deptMin > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    <?= $deptMin > 0 ? number_format($deptActualPct, 2) . '%' : '0.00%' ?>
                                </td>
                                <td class="td-kpi-pink">
                                    <select name="loss_sco[<?= e($deptName) ?>]" class="table-input-cell sel-dept-sco fw-bold <?= $deptSco >= 3 ? 'text-success' : 'text-danger' ?>" style="width: 42px; cursor: pointer;">
                                        <option value="3" <?= $deptSco == 3 ? 'selected' : '' ?>>3</option>
                                        <option value="2" <?= $deptSco == 2 ? 'selected' : '' ?>>2</option>
                                        <option value="1" <?= $deptSco == 1 ? 'selected' : '' ?>>1</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <!-- Total Loss Summary Row (ยกเว้นช่องรวมเวลา - คำนวณอัตโนมัติ ห้ามลงเอง) -->
                            <tr class="fw-bold">
                                <td class="td-kpi-pink text-start">Total</td>
                                <td class="td-kpi-pink text-primary" id="lblTotalTgtPct"><?= number_format($totalTargetLossPct, 2) ?>%</td>
                                <td class="td-kpi-pink" id="lblTotalLossOccurrences"><?= $totalLossOccurrences ?></td>
                                <td class="td-kpi-pink">ครั้ง</td>
                                <td class="td-kpi-pink text-danger fs-6" id="lblTotalLossMinutes"><?= $totalLossMinutes ?></td>
                                <td class="td-kpi-pink">นาที</td>
                                <td class="td-kpi-pink text-danger fs-8" id="lblTotalLossPct"><?= number_format($lossTotalPct, 2) ?>%</td>
                                <td class="td-kpi-pink text-dark fs-6" id="lblTotalScore"><?= $totalScore ?></td>
                            </tr>

                            <!-- Summary Notes & Remarks -->
                            <tr>
                                <td class="td-kpi-red-head text-start bg-white text-dark" colspan="8">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark">*สรุปผลการเดินงาน*</span>
                                        <span class="fs-9 fw-normal text-muted no-capture-inline"><i class="fa-solid fa-pen me-1"></i>พิมพ์แก้ไขได้</span>
                                    </div>
                                    <textarea name="summary_notes" id="inpSummaryNotes" rows="2" class="form-control form-control-sm bg-white text-dark fs-8 border" placeholder="ระบุสรุปผลการเดินงาน..."><?= e($report['summary_notes']) ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <td class="td-kpi-red-head text-start bg-white text-dark" colspan="8">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark">*หมายเหตุ*</span>
                                        <span class="fs-9 fw-normal text-muted no-capture-inline"><i class="fa-solid fa-pen me-1"></i>พิมพ์แก้ไขได้</span>
                                    </div>
                                    <textarea name="remarks" id="inpRemarks" rows="2" class="form-control form-control-sm bg-white text-dark fs-8 border" placeholder="ระบุหมายเหตุ/ข้อเสนอแนะ..."><?= e($report['remarks']) ?></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Section 4: รายการที่ต้องการ (แยกตามลอน) (Light Green/Yellow from Image) -->
            <div class="table-responsive mb-2">
                <table class="table-bhs text-center">
                    <thead>
                        <tr>
                            <th class="th-flute-head text-start" style="width: 32%;">รายการที่ต้องการ</th>
                            <th class="th-flute-head">ลอน B</th>
                            <th class="th-flute-head">ลอน C</th>
                            <th class="th-flute-head">ลอน BC</th>
                            <th class="th-flute-head">ลอน A</th>
                            <th class="th-flute-head">ลอน AB</th>
                            <th class="th-flute-head">ลอน E</th>
                            <th class="th-range-yellow">รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $fs = $summary['flute_summary'];
                        $fa = $summary['flute_add'] ?? [];
                        $totFluteOrders = $summary['total_orders'];
                        $totFluteMeters = $summary['total_meters'];
                        $totFluteWeight = $summary['total_weight'];
                        $totFluteSec    = 0;
                        foreach ($fs as $k => $v) $totFluteSec += $v['seconds'];
                        $totFluteMin    = round($totFluteSec / 60);
                        $totFluteHours  = round($totFluteMin / 60, 1);
                        // รวม add totals
                        $totAddOrders = 0; $totAddSmall = 0; $totAddWeight = 0.0;
                        foreach ($fa as $fk => $fv) {
                            $totAddOrders += $fv['add_orders'];
                            $totAddSmall  += $fv['add_small'];
                            $totAddWeight += $fv['add_weight'];
                        }
                        ?>
                        <tr>
                            <td class="td-flute-yellow text-start">จำนวนออเดอร์แยกลอน</td>
                            <td class="td-flute-yellow"><?= $fs['B']['orders'] ?></td>
                            <td class="td-flute-yellow"><?= $fs['C']['orders'] ?></td>
                            <td class="td-flute-yellow"><?= $fs['BC']['orders'] ?></td>
                            <td class="td-flute-yellow"><?= $fs['A']['orders'] ?></td>
                            <td class="td-flute-yellow"><?= $fs['AB']['orders'] ?></td>
                            <td class="td-flute-yellow"><?= $fs['E']['orders'] ?></td>
                            <td class="td-flute-green"><?= $totFluteOrders ?></td>
                        </tr>
                        <tr>
                            <td class="td-flute-yellow text-start">จำนวนเมตรแยกลอน</td>
                            <td class="td-flute-yellow"><?= number_format($fs['B']['meters']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['C']['meters']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['BC']['meters']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['A']['meters']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['AB']['meters']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['E']['meters']) ?></td>
                            <td class="td-flute-green"><?= number_format($totFluteMeters) ?></td>
                        </tr>
                        <tr>
                            <td class="td-flute-yellow text-start">เวลาผลิตเป็นนาที</td>
                            <td class="td-flute-yellow"><?= round($fs['B']['seconds'] / 60) ?></td>
                            <td class="td-flute-yellow"><?= round($fs['C']['seconds'] / 60) ?></td>
                            <td class="td-flute-yellow"><?= round($fs['BC']['seconds'] / 60) ?></td>
                            <td class="td-flute-yellow"><?= round($fs['A']['seconds'] / 60) ?></td>
                            <td class="td-flute-yellow"><?= round($fs['AB']['seconds'] / 60) ?></td>
                            <td class="td-flute-yellow"><?= round($fs['E']['seconds'] / 60) ?></td>
                            <td class="td-flute-green"><?= $totFluteMin ?></td>
                        </tr>
                        <tr>
                            <td class="td-flute-yellow text-start">เวลาผลิตเป็นชั่วโมง</td>
                            <td class="td-flute-yellow"><?= round(($fs['B']['seconds'] / 3600), 1) ?></td>
                            <td class="td-flute-yellow"><?= round(($fs['C']['seconds'] / 3600), 1) ?></td>
                            <td class="td-flute-yellow"><?= round(($fs['BC']['seconds'] / 3600), 1) ?></td>
                            <td class="td-flute-yellow"><?= round(($fs['A']['seconds'] / 3600), 1) ?></td>
                            <td class="td-flute-yellow"><?= round(($fs['AB']['seconds'] / 3600), 1) ?></td>
                            <td class="td-flute-yellow"><?= round(($fs['E']['seconds'] / 3600), 1) ?></td>
                            <td class="td-flute-green"><?= $totFluteHours ?></td>
                        </tr>
                        <tr>
                            <td class="td-flute-yellow text-start">น้ำหนักทั้งหมดแยกลอน</td>
                            <td class="td-flute-yellow"><?= number_format($fs['B']['weight']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['C']['weight']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['BC']['weight']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['A']['weight']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['AB']['weight']) ?></td>
                            <td class="td-flute-yellow"><?= number_format($fs['E']['weight']) ?></td>
                            <td class="td-flute-green"><?= number_format($totFluteWeight) ?></td>
                        </tr>
                        <tr>
                            <td class="td-flute-pink text-start">จำนวนออเดอร์ที่บวกเพิ่ม</td>
                            <td class="td-flute-pink"><?= $fa['B']['add_orders'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['C']['add_orders'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['BC']['add_orders'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['A']['add_orders'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['AB']['add_orders'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['E']['add_orders'] ?? 0 ?></td>
                            <td class="td-flute-green"><?= $totAddOrders ?></td>
                        </tr>
                        <tr>
                            <td class="td-flute-pink text-start">จำนวนแผ่นเล็กที่บวกเพิ่ม</td>
                            <td class="td-flute-pink"><?= $fa['B']['add_small'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['C']['add_small'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['BC']['add_small'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['A']['add_small'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['AB']['add_small'] ?? 0 ?></td>
                            <td class="td-flute-pink"><?= $fa['E']['add_small'] ?? 0 ?></td>
                            <td class="td-flute-green"><?= $totAddSmall ?></td>
                        </tr>
                        <tr>
                            <td class="td-flute-pink text-start">น้ำหนักที่บวกเพิ่ม</td>
                            <td class="td-flute-pink"><?= number_format($fa['B']['add_weight'] ?? 0) ?></td>
                            <td class="td-flute-pink"><?= number_format($fa['C']['add_weight'] ?? 0) ?></td>
                            <td class="td-flute-pink"><?= number_format($fa['BC']['add_weight'] ?? 0) ?></td>
                            <td class="td-flute-pink"><?= number_format($fa['A']['add_weight'] ?? 0) ?></td>
                            <td class="td-flute-pink"><?= number_format($fa['AB']['add_weight'] ?? 0) ?></td>
                            <td class="td-flute-pink"><?= number_format($fa['E']['add_weight'] ?? 0) ?></td>
                            <td class="td-flute-green"><?= number_format($totAddWeight) ?></td>
                        </tr>

                        <!-- Percentages -->
                        <tr class="fw-bold">
                            <td class="td-flute-head text-start">% รายการที่ต้องการ</td>
                            <td class="td-flute-head">ลอน B</td>
                            <td class="td-flute-head">ลอน C</td>
                            <td class="td-flute-head">ลอน BC</td>
                            <td class="td-flute-head">ลอน A</td>
                            <td class="td-flute-head">ลอน AB</td>
                            <td class="td-flute-head">ลอน E</td>
                            <td class="td-flute-green"></td>
                        </tr>
                        <tr>
                            <td class="td-flute-pink text-start">% จำนวนเมตรแยกลอน</td>
                            <?php foreach (['B','C','BC','A','AB','E'] as $fk): ?>
                            <td class="td-flute-pink"><?= $totFluteMeters > 0 ? round(($fs[$fk]['meters'] / $totFluteMeters) * 100) : 0 ?>%</td>
                            <?php endforeach; ?>
                            <td class="td-flute-green">100%</td>
                        </tr>
                        <tr>
                            <td class="td-flute-pink text-start">% จำนวนออเดอร์แยกลอน</td>
                            <?php foreach (['B','C','BC','A','AB','E'] as $fk): ?>
                            <td class="td-flute-pink"><?= $totFluteOrders > 0 ? round(($fs[$fk]['orders'] / $totFluteOrders) * 100) : 0 ?>%</td>
                            <?php endforeach; ?>
                            <td class="td-flute-green">100%</td>
                        </tr>
                        <tr>
                            <td class="td-flute-pink text-start">% น้ำหนักทั้งหมดแยกลอน</td>
                            <?php foreach (['B','C','BC','A','AB','E'] as $fk): ?>
                            <td class="td-flute-pink"><?= $totFluteWeight > 0 ? round(($fs[$fk]['weight'] / $totFluteWeight) * 100) : 0 ?>%</td>
                            <?php endforeach; ?>
                            <td class="td-flute-green">100%</td>
                        </tr>
                        <tr>
                            <td class="td-flute-pink text-start">% จำนวนออเดอร์ที่บวกเพิ่ม</td>
                            <?php foreach (['B','C','BC','A','AB','E'] as $fk):
                                $ord = $fs[$fk]['orders'];
                                $addO = $fa[$fk]['add_orders'] ?? 0;
                                $pct = ($ord > 0) ? round(($addO / $ord) * 100) : null;
                            ?>
                            <td class="td-flute-pink"><?= $pct !== null ? $pct.'%' : '-' ?></td>
                            <?php endforeach; ?>
                            <td class="td-flute-green"><?= $totFluteOrders > 0 ? round(($totAddOrders / $totFluteOrders) * 100) : 0 ?>%</td>
                        </tr>
                        <tr>
                            <td class="td-flute-yellow text-start">% จำนวนแผ่นเล็กที่บวกเพิ่ม</td>
                            <?php foreach (['B','C','BC','A','AB','E'] as $fk):
                                $addO = $fa[$fk]['add_orders'] ?? 0;
                                $addS = $fa[$fk]['add_small'] ?? 0;
                                $pct = ($addO > 0) ? round(($addS / $addO) * 100) : null;
                            ?>
                            <td class="td-flute-yellow<?= $pct === null ? ' text-muted' : '' ?>"><?= $pct !== null ? $pct.'%' : '-' ?></td>
                            <?php endforeach; ?>
                            <td class="td-flute-green<?= $totAddOrders == 0 ? ' text-muted' : '' ?>"><?= $totAddOrders > 0 ? round(($totAddSmall / $totAddOrders) * 100).'%' : '-' ?></td>
                        </tr>
                        <tr>
                            <td class="td-flute-yellow text-start">% น้ำหนักที่บวกเพิ่ม</td>
                            <?php foreach (['B','C','BC','A','AB','E'] as $fk):
                                $addO = $fa[$fk]['add_orders'] ?? 0;
                                $addW = $fa[$fk]['add_weight'] ?? 0;
                                $pct = ($addO > 0) ? round(($addW / $addO) * 100) : null;
                            ?>
                            <td class="td-flute-yellow<?= $pct === null ? ' text-muted' : '' ?>"><?= $pct !== null ? $pct.'%' : '-' ?></td>
                            <?php endforeach; ?>
                            <td class="td-flute-green<?= $totAddOrders == 0 ? ' text-muted' : '' ?>"><?= $totAddOrders > 0 ? round(($totAddWeight / $totAddOrders) * 100).'%' : '-' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- ========================================== -->
        <!-- RIGHT COLUMN: GSM Tables, Ranges & Trim     -->
        <!-- ========================================== -->
        <div class="col-lg-6">

            <!-- Section 2: ตารางของเสียแยก Station (5 Stations: SF-C, SF-B, DF, Control, Stacker) -->
            <div class="card card-theme shadow-sm border-0 mb-2">
                <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1f4e79 0%, #2e75b6 100%);">
                    <span class="fw-bold text-white fs-7">
                        <i class="fa-solid fa-trash-can me-1 text-warning"></i> ของเสียแยก Station
                    </span>
                    <span class="badge bg-white text-dark fs-8 fw-semibold">
                        น้ำหนักผลิตรวม: <?= number_format($totFluteWeight) ?> KG
                    </span>
                </div>
                <div class="table-responsive p-0">
                    <table class="table-bhs text-center" id="tableStationWaste">
                        <thead>
                            <tr>
                                <th style="width: 20%; background: #1f4e79; color: #fff; font-size: 0.82rem; padding: 7px;">Station</th>
                                <th style="width: 20%; background: #1a6b3c; color: #fff; font-size: 0.82rem; padding: 7px;">Target (%)</th>
                                <th style="width: 20%; background: #1a6b3c; color: #fff; font-size: 0.82rem; padding: 7px;">Target น้ำหนัก (KG)</th>
                                <th style="width: 20%; background: #1f4e79; color: #fff; font-size: 0.82rem; padding: 7px;">ของเสีย (KG)</th>
                                <th style="width: 20%; background: #2e75b6; color: #fff; font-size: 0.82rem; padding: 7px;">Actual %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($defaultStations as $st):
                                $kg = $stationWasteKg[$st] ?? 0;
                                $prodPct  = ($totFluteWeight > 0) ? round(($kg / $totFluteWeight) * 100, 2) : 0;
                                $targetPct = (float)($stationWasteTargets[$st]['target_pct'] ?? $stationWasteTargets[$st]['target_kg'] ?? 0);
                                $targetWeight = ($totFluteWeight > 0 && $targetPct > 0) ? round(($targetPct / 100) * $totFluteWeight, 1) : 0;

                                // สีแสดงผล Actual % เทียบกับ Target %
                                if ($targetPct > 0) {
                                    if ($prodPct <= $targetPct) {
                                        $actualBg = '#d4edda'; // สีเขียวอ่อน
                                        $actualColor = '#155724';
                                    } else {
                                        $actualBg = '#f8d7da'; // สีแดงอ่อน
                                        $actualColor = '#721c24';
                                    }
                                } else {
                                    $actualBg = '#f8f9fa';
                                    $actualColor = '#495057';
                                }
                            ?>
                            <tr class="row-station-waste" data-station="<?= e($st) ?>" data-target="<?= $targetPct ?>">
                                <td class="td-station-name fw-bold" style="background: #fdf2e9; color: #b94a00; font-size: 0.85rem; vertical-align: middle;">
                                    <?= e($st) ?>
                                </td>
                                <td class="td-station-target text-center fw-bold" style="background: #eafaf1; color: #1a6b3c; font-size: 0.85rem; vertical-align: middle;" data-station="<?= e($st) ?>">
                                    <?= $targetPct > 0 ? number_format($targetPct, 2) . '%' : '-' ?>
                                </td>
                                <td class="td-station-target-weight text-center fw-bold" style="background: #eafaf1; color: #1a6b3c; font-size: 0.85rem; vertical-align: middle;" data-station="<?= e($st) ?>">
                                    <?= $targetWeight > 0 ? number_format($targetWeight, 1) : '-' ?>
                                </td>
                                <td class="td-station-input p-0" style="background: #ffffff; vertical-align: middle;">
                                    <input type="number" step="any" min="0" 
                                           name="station_waste[<?= e($st) ?>]" 
                                           form="formInlineKpi"
                                           class="form-control form-control-sm table-input-cell text-center fw-bold inp-station-waste-kg" 
                                           data-station="<?= e($st) ?>" 
                                           value="<?= $kg > 0 ? $kg : '' ?>" 
                                           placeholder="0"
                                           style="font-size: 0.9rem; height: 36px;">
                                </td>
                                <td class="td-station-prod fw-bold lbl-station-prod" data-station="<?= e($st) ?>" data-target="<?= $targetPct ?>" style="background: <?= $actualBg ?>; color: <?= $actualColor ?>; vertical-align: middle; font-size: 0.85rem;">
                                    <?= $prodPct ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <!-- Total Row -->
                            <?php
                            $totalTargetPctSum = 0;
                            $totalTargetWeightSum = 0;
                            foreach ($defaultStations as $st) {
                                $tPct = (float)($stationWasteTargets[$st]['target_pct'] ?? $stationWasteTargets[$st]['target_kg'] ?? 0);
                                $totalTargetPctSum += $tPct;
                                if ($totFluteWeight > 0 && $tPct > 0) {
                                    $totalTargetWeightSum += round(($tPct / 100) * $totFluteWeight, 1);
                                }
                            }
                            $totalActualProdPct = ($totFluteWeight > 0 && $totalStationWasteKg > 0) ? round(($totalStationWasteKg / $totFluteWeight) * 100, 2) : 0;
                            if ($totalTargetPctSum > 0) {
                                if ($totalActualProdPct <= $totalTargetPctSum) {
                                    $totalActualBg = '#d4edda';
                                    $totalActualColor = '#155724';
                                } else {
                                    $totalActualBg = '#f8d7da';
                                    $totalActualColor = '#721c24';
                                }
                            } else {
                                $totalActualBg = '#d9e1f2';
                                $totalActualColor = '#dc3545';
                            }
                            ?>
                            <tr class="tr-station-waste-total fw-bold" style="background: #d9e1f2; border-top: 2px solid #1f4e79;">
                                <td class="text-center text-dark py-2" style="font-size: 0.85rem;">รวมทั้งหมด</td>
                                <td class="text-center py-2" style="color: #1a6b3c; font-size: 0.85rem;" id="lblStationTargetTotal">
                                    <?= $totalTargetPctSum > 0 ? number_format($totalTargetPctSum, 2) . '%' : '-' ?>
                                </td>
                                <td class="text-center py-2" style="color: #1a6b3c; font-size: 0.85rem;" id="lblStationTargetWeightTotal">
                                    <?= $totalTargetWeightSum > 0 ? number_format($totalTargetWeightSum, 1) : '-' ?>
                                </td>
                                <td class="text-danger fs-6 py-2" id="lblStationWasteTotalKg">
                                    <?= $totalStationWasteKg > 0 ? number_format($totalStationWasteKg, 1) : '0' ?>
                                </td>
                                <td class="fs-7 py-2" id="lblStationWasteTotalProdPct" data-target="<?= $totalTargetPctSum ?>" style="background: <?= $totalActualBg ?>; color: <?= $totalActualColor ?>;">
                                    <?= ($totFluteWeight > 0 && $totalStationWasteKg > 0) ? $totalActualProdPct . '%' : '0%' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 5: Meter Range, Trim and Wo Prefix Tables -->
            <div class="row g-1">
                <!-- 5.1 ตารางช่วงระยะเมตร -->
                <div class="col-4">
                    <table class="table-bhs text-center">
                        <thead>
                            <tr>
                                <th class="th-range-yellow">ช่วงระยะ<br>เมตร</th>
                                <th class="th-range-yellow">จำนวน<br>ออเดอร์</th>
                                <th class="th-range-yellow">จำนวน<br>เมตร</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totRangeOrders = 0; $totRangeMeters = 0;
                            foreach ($summary['meter_dist'] as $rangeLbl => $rData): 
                                $totRangeOrders += $rData['orders'];
                                $totRangeMeters += $rData['meters'];
                            ?>
                            <tr>
                                <td class="td-range-white fs-8 fw-semibold"><?= $rangeLbl ?></td>
                                <td class="td-range-white fs-8"><?= $rData['orders'] ?></td>
                                <td class="td-range-white fs-8"><?= number_format($rData['meters']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="tr-gsm-total fs-8">
                                <td>รวม</td>
                                <td><?= $totRangeOrders ?></td>
                                <td><?= number_format($totRangeMeters) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 5.2 ตาราง Trim -->
                <div class="col-4">
                    <table class="table-bhs text-center">
                        <thead>
                            <tr>
                                <th class="th-range-yellow">Trim<br>(mm)</th>
                                <th class="th-range-yellow">จำนวน<br>ออเดอร์</th>
                                <th class="th-range-yellow">จำนวน<br>เมตร</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totTrimOrders = 0; $totTrimMeters = 0;
                            foreach ($summary['trim_dist'] as $trimVal => $tData): 
                                if ($trimVal === 'อื่นๆ' && $tData['orders'] === 0) continue;
                                $totTrimOrders += $tData['orders'];
                                $totTrimMeters += $tData['meters'];
                            ?>
                            <tr>
                                <td class="td-range-white fs-8 fw-semibold"><?= $trimVal ?></td>
                                <td class="td-range-white fs-8"><?= $tData['orders'] ?></td>
                                <td class="td-range-white fs-8"><?= number_format($tData['meters']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="tr-gsm-total fs-8">
                                <td>รวม</td>
                                <td><?= $totTrimOrders ?></td>
                                <td><?= number_format($totTrimMeters) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 5.3 ตารางประเภท Wo Prefix -->
                <div class="col-4">
                    <table class="table-bhs text-center">
                        <thead>
                            <tr>
                                <th class="th-range-yellow">รายการ</th>
                                <th class="th-range-yellow">จำนวน<br>เมตร</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totWoMeters = 0;
                            foreach ($summary['wo_summary'] as $woPrefix => $wMeters): 
                                $totWoMeters += $wMeters;
                            ?>
                            <tr>
                                <td class="td-range-white fs-8 fw-semibold text-primary"><?= $woPrefix ?></td>
                                <td class="td-range-white fs-8"><?= number_format($wMeters) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="tr-gsm-total fs-8">
                                <td>รวม</td>
                                <td><?= number_format($totWoMeters) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 6: 4 Charts 2×2 ใน Right Column -->
            <div class="row g-2 mt-2">
                <!-- กราฟเวลาสูญเสีย -->
                <div class="col-6">
                    <div class="border rounded p-2 bg-white shadow-sm" style="height: 260px;">
                        <canvas id="chartLossTime"></canvas>
                    </div>
                </div>
                <!-- กราฟ % น้ำหนักแยกลอน -->
                <div class="col-6">
                    <div class="border rounded p-2 bg-white shadow-sm" style="height: 260px;">
                        <canvas id="chartFluteWeight"></canvas>
                    </div>
                </div>
                <!-- กราฟของเสียแยก Station (KG) -->
                <div class="col-6">
                    <div class="border rounded p-2 bg-white shadow-sm" style="height: 260px;">
                        <canvas id="chartStationWasteBar"></canvas>
                    </div>
                </div>
                <!-- กราฟ % สัดส่วนของเสียแยก Station -->
                <div class="col-6">
                    <div class="border rounded p-2 bg-white shadow-sm" style="height: 260px;">
                        <canvas id="chartStationWasteDonut"></canvas>
                    </div>
                </div>
            </div>

        </div>

    </div> <!-- End Main Grid 2 Columns -->

</div>
<!-- ========================================================================================= -->
<!-- END REPORT MASTER CAPTURE AREA -->
<!-- ========================================================================================= -->

<!-- RAW DATA INSPECTION TABLE (Collapsible) -->
<div class="card card-theme shadow-sm border-0 mt-4 no-capture">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-bold fs-6 text-dark">
                <i class="fa-solid fa-table-cells text-orange me-2"></i> ข้อมูลเครื่องจักรรายออเดอร์ (Raw Machine Records)
            </span>
            <span class="badge bg-secondary ms-2"><?= count($filteredRecords) ?> แถว</span>
        </div>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#rawTableCollapse">
            <i class="fa-solid fa-chevron-down"></i> ย่อ/ขยาย
        </button>
    </div>
<?php
// Helper functions สำหรับแปลงข้อมูลแถวตารางข้อมูลเครื่องจักร (A ถึง BX = 76 คอลัมน์)
if (!function_exists('bhsGsmClass')) {
    function bhsGsmClass($gsm) {
        if ($gsm <= 0) return '';
        if ($gsm <= 150) return 'light';
        if ($gsm <= 185) return 'Medium';
        return 'Heavy';
    }
}
if (!function_exists('bhsMeterBucket')) {
    function bhsMeterBucket($m) {
        if ($m <= 200) return 1;
        if ($m <= 300) return 2;
        if ($m <= 400) return 3;
        if ($m <= 500) return 4;
        if ($m <= 700) return 5;
        if ($m <= 1000) return 6;
        if ($m <= 2500) return 7;
        if ($m <= 3000) return 8;
        if ($m <= 5000) return 9;
        if ($m <= 10000) return 10;
        return 11;
    }
}
if (!function_exists('bhsCutlengBucket')) {
    function bhsCutlengBucket($l) {
        if ($l <= 600) return 1;
        if ($l <= 700) return 2;
        if ($l <= 800) return 3;
        if ($l <= 900) return 4;
        if ($l <= 1000) return 5;
        if ($l <= 1300) return 6;
        if ($l <= 1500) return 7;
        if ($l <= 2000) return 8;
        if ($l <= 2500) return 9;
        if ($l <= 3000) return 10;
        return 11;
    }
}
if (!function_exists('bhsSpeedBucket')) {
    function bhsSpeedBucket($s) {
        $buckets = [50=>1, 60=>2, 70=>3, 80=>4, 90=>5, 100=>6, 110=>7, 120=>8, 130=>9, 140=>10, 150=>11];
        foreach ($buckets as $limit => $idx) {
            if ($s <= $limit) return $idx;
        }
        return 11;
    }
}
if (!function_exists('bhsTrimBucket')) {
    function bhsTrimBucket($t) {
        $trimBuckets = [13=>1, 15=>2, 20=>3, 25=>4, 30=>5, 40=>6, 50=>7, 60=>8, 70=>9, 75=>10, 85=>11];
        foreach ($trimBuckets as $limit => $idx) {
            if ($t <= $limit) return $idx;
        }
        return 11;
    }
}
?>
    <div class="collapse show" id="rawTableCollapse">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 560px; overflow-y: auto;">
                <table class="table table-sm table-bordered table-striped table-hover mb-0 text-nowrap fs-9 text-end align-middle">
                    <thead class="table-dark sticky-top text-center" style="font-size: 0.76rem; z-index: 5;">
                        <tr>
                            <!-- 1-6 (A - F) -->
                            <th title="A: ลำดับที่">A<br>ลำดับที่</th>
                            <th title="B: กะ">B<br>กะ</th>
                            <th title="C: ออร์เดอร์แผน">C<br>ออร์เดอร์แผน</th>
                            <th title="D: ออร์เดอร์">D<br>ออร์เดอร์</th>
                            <th title="E: Wo. No.">E<br>Wo. No.</th>
                            <th title="F: ลอน">F<br>ลอน</th>
                            <!-- 7-11 (G - K) Weights -->
                            <th title="G: M1Weight">G<br>M1Weight</th>
                            <th title="H: M2Weight">H<br>M2Weight</th>
                            <th title="I: M3Weight">I<br>M3Weight</th>
                            <th title="J: M4Weight">J<br>M4Weight</th>
                            <th title="K: M5Weight">K<br>M5Weight</th>
                            <!-- 12-16 (L - P) Papers -->
                            <th title="L: M1">L<br>M1</th>
                            <th title="M: M2">M<br>M2</th>
                            <th title="N: M3">N<br>M3</th>
                            <th title="O: M4">O<br>M4</th>
                            <th title="P: M5">P<br>M5</th>
                            <!-- 17-21 (Q - U) Sheet dimensions -->
                            <th title="Q: หน้ากว้าง(มม)">Q<br>หน้ากว้าง</th>
                            <th title="R: ความยาว/แผ่น(มม)">R<br>ยาว/แผ่น</th>
                            <th title="S: นน/แผ่น(กก)">S<br>นน/แผ่น</th>
                            <th title="T: แผ่นใหญ่ดี">T<br>แผ่นใหญ่ดี</th>
                            <th title="U: แผ่นใหญ่เสีย">U<br>แผ่นใหญ่เสีย</th>
                            <!-- 22-29 (V - AC) Knives & Scores -->
                            <th title="V: T">V<br>T</th>
                            <th title="W: S">W<br>S</th>
                            <th title="X: F1">X<br>F1</th>
                            <th title="Y: F2">Y<br>F2</th>
                            <th title="Z: F3">Z<br>F3</th>
                            <th title="AA: F4">AA<br>F4</th>
                            <th title="AB: F5">AB<br>F5</th>
                            <th title="AC: F6">AC<br>F6</th>
                            <!-- 30-31 (AD - AE) Small sheets -->
                            <th title="AD: แผ่นเล็กทั้งหมด">AD<br>แผ่นเล็กรวม</th>
                            <th title="AE: แผ่นเล็กดี">AE<br>แผ่นเล็กดี</th>
                            <!-- 32-38 (AF - AL) Time & Speed -->
                            <th title="AF: เวลาเริ่ม">AF<br>เวลาเริ่ม</th>
                            <th title="AG: เวลาสิ้นสุด">AG<br>เวลาสิ้นสุด</th>
                            <th title="AH: เวลาผลิต(วินาที)">AH<br>เวลาผลิต(วิ)</th>
                            <th title="AI: จำนวนครั้งที่หยุด">AI<br>หยุด(ครั้ง)</th>
                            <th title="AJ: เวลาที่หยุด">AJ<br>หยุด(วิ)</th>
                            <th title="AK: Stackcount">AK<br>Stackcount</th>
                            <th title="AL: AVG_Speed(M/min)">AL<br>Speed</th>
                            <!-- 39-42 (AM - AP) Totals -->
                            <th title="AM: นน.ทั้งหมด(กก)">AM<br>นน.รวม(กก)</th>
                            <th title="AN: พื้นที่.ทั้งหมด(ตรม)">AN<br>พื้นที่(ตรม)</th>
                            <th title="AO: ความยาวทั้งหมด(เมตร)">AO<br>ยาว(เมตร)</th>
                            <th title="AP: ปริมาตรทั้งหมด(ตรม)">AP<br>ปริมาตร(ตรม)</th>
                            <!-- 43-46 (AQ - AT) Additions & Prod Minutes -->
                            <th title="AQ: จำนวนออเดอร์ที่บวก">AQ<br>ออเดอร์บวก</th>
                            <th title="AR: แผ่นเล็กที่บวก">AR<br>แผ่นเล็กบวก</th>
                            <th title="AS: น้ำหนักที่บวก">AS<br>นน.บวก</th>
                            <th title="AT: เวลาที่ผลิต นาที">AT<br>เวลาผลิต(นาที)</th>
                            <!-- 47-51 (AU - AY) M1 - M5 GSM -->
                            <th title="AU: M1">AU<br>M1</th>
                            <th title="AV: M2">AV<br>M2</th>
                            <th title="AW: M3">AW<br>M3</th>
                            <th title="AX: M4">AX<br>M4</th>
                            <th title="AY: M5">AY<br>M5</th>
                            <!-- 52-56 (AZ - BD) MM1 - MM5 -->
                            <th title="AZ: MM1">AZ<br>MM1</th>
                            <th title="BA: MM2">BA<br>MM2</th>
                            <th title="BB: MM3">BB<br>MM3</th>
                            <th title="BC: MM4">BC<br>MM4</th>
                            <th title="BD: MM5">BD<br>MM5</th>
                            <!-- 57-61 (BE - BI) MMM1 - MMM5 Class -->
                            <th title="BE: MMM1">BE<br>MMM1</th>
                            <th title="BF: MMM2">BF<br>MMM2</th>
                            <th title="BG: MMM3">BG<br>MMM3</th>
                            <th title="BH: MMM4">BH<br>MMM4</th>
                            <th title="BI: MMM5">BI<br>MMM5</th>
                            <!-- 62-70 (BJ - BR) Station Flute Meters -->
                            <th title="BJ: light C">BJ<br>light C</th>
                            <th title="BK: Medium C">BK<br>Medium C</th>
                            <th title="BL: Heavy C">BL<br>Heavy C</th>
                            <th title="BM: light B">BM<br>light B</th>
                            <th title="BN: Medium B">BN<br>Medium B</th>
                            <th title="BO: Heavy B">BO<br>Heavy B</th>
                            <th title="BP: light DF">BP<br>light DF</th>
                            <th title="BQ: Medium DF">BQ<br>Medium DF</th>
                            <th title="BR: Heavy DF">BR<br>Heavy DF</th>
                            <!-- 71-76 (BS - BX) Buckets & PDR -->
                            <th title="BS: เมตร">BS<br>เมตร</th>
                            <th title="BT: Cutleng">BT<br>Cutleng</th>
                            <th title="BU: Trim/ข้าง">BU<br>Trim/ข้าง</th>
                            <th title="BV: Trim2">BV<br>Trim2</th>
                            <th title="BW: Speed">BW<br>Speed</th>
                            <th title="BX: PDR">BX<br>PDR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($filteredRecords as $rec): 
                            $prodSec = (int)$rec['production_seconds'];
                            $prodMin = $prodSec > 0 ? round($prodSec / 60, 2) : 0;

                            // GSM Numbers (AU - AY / AZ - BD)
                            $g1 = (int)($rec['m1_gsm'] ?: BhsEngine::extractGsm($rec['m1_paper']));
                            $g2 = (int)($rec['m2_gsm'] ?: BhsEngine::extractGsm($rec['m2_paper']));
                            $g3 = (int)($rec['m3_gsm'] ?: BhsEngine::extractGsm($rec['m3_paper']));
                            $g4 = (int)($rec['m4_gsm'] ?: BhsEngine::extractGsm($rec['m4_paper']));
                            $g5 = (int)($rec['m5_gsm'] ?: BhsEngine::extractGsm($rec['m5_paper']));

                            // GSM Classes (BE - BI: MMM1 - MMM5)
                            $mmm1 = !empty($rec['m1_class']) ? $rec['m1_class'] : bhsGsmClass($g1);
                            $mmm2 = !empty($rec['m2_class']) ? $rec['m2_class'] : bhsGsmClass($g2);
                            $mmm3 = !empty($rec['m3_class']) ? $rec['m3_class'] : bhsGsmClass($g3);
                            $mmm4 = !empty($rec['m4_class']) ? $rec['m4_class'] : bhsGsmClass($g4);
                            $mmm5 = !empty($rec['m5_class']) ? $rec['m5_class'] : bhsGsmClass($g5);

                            $mLen = (float)$rec['total_length_m'];

                            // BJ - BL: light C, Medium C, Heavy C (ตรงตามสูตร Excel: เช็ค BE = MMM1)
                            $lightC  = ($mmm1 === 'light')  ? $mLen : '';
                            $medC    = ($mmm1 === 'Medium') ? $mLen : '';
                            $heavyC  = ($mmm1 === 'Heavy')  ? $mLen : '';

                            // BM - BO: light B, Medium B, Heavy B (ตรงตามสูตร Excel: เช็ค BG = MMM3)
                            $lightB  = ($mmm3 === 'light')  ? $mLen : '';
                            $medB    = ($mmm3 === 'Medium') ? $mLen : '';
                            $heavyB  = ($mmm3 === 'Heavy')  ? $mLen : '';

                            // BP - BR: light DF, Medium DF, Heavy DF (ตรงตามสูตร Excel: เช็ค BI = MMM5)
                            $lightDF = ($mmm5 === 'light')  ? $mLen : '';
                            $medDF   = ($mmm5 === 'Medium') ? $mLen : '';
                            $heavyDF = ($mmm5 === 'Heavy')  ? $mLen : '';

                            // Buckets (BS - BX)
                            $bMeter   = !empty($rec['meter_bucket']) ? $rec['meter_bucket'] : bhsMeterBucket($mLen);
                            $bCutleng = !empty($rec['cutleng_bucket']) ? $rec['cutleng_bucket'] : bhsCutlengBucket((float)$rec['sheet_length_mm']);
                            $trimSide = isset($rec['trim_side_mm']) && $rec['trim_side_mm'] > 0 
                                        ? (float)$rec['trim_side_mm'] 
                                        : bhsTrimSide((float)$rec['paper_width_mm'], (int)$rec['knife_t'], (float)$rec['knife_s']);
                            $bTrim    = !empty($rec['trim_bucket']) ? $rec['trim_bucket'] : bhsTrimBucket($trimSide);
                            $bSpeed   = !empty($rec['speed_bucket']) ? $rec['speed_bucket'] : bhsSpeedBucket((float)$rec['avg_speed']);
                            $pdrCode  = $rec['wo_prefix'] ?: strtoupper(substr($rec['wo_no'], 0, 3));

                            // Additions (AQ - AT) ตรงตามสูตร Excel
                            $addOrder = isset($rec['add_order']) && $rec['add_order'] !== null 
                                        ? (int)$rec['add_order'] 
                                        : ($rec['waste_big_sheets'] >= 1 ? 1 : 0);
                            $addSmall = isset($rec['add_small_sheets']) && $rec['add_small_sheets'] !== null 
                                        ? (int)$rec['add_small_sheets'] 
                                        : ($rec['waste_big_sheets'] * (int)$rec['knife_t']);
                            $addWt    = isset($rec['add_weight']) && $rec['add_weight'] !== null 
                                        ? (float)$rec['add_weight'] 
                                        : ($addSmall * (float)$rec['sheet_weight_kg']);
                            $prodMin  = isset($rec['production_minutes']) && (float)$rec['production_minutes'] > 0 
                                        ? (float)$rec['production_minutes'] 
                                        : ($prodSec > 0 ? round($prodSec / 60.0, 2) : 0.00);
                        ?>
                        <tr>
                            <!-- 1-6 (A - F) -->
                            <td class="text-center fw-bold"><?= $rec['seq_no'] ?></td>
                            <td class="text-center"><span class="badge bg-info text-dark"><?= $rec['shift'] ?></span></td>
                            <td class="text-center"><?= e($rec['plan_order']) ?></td>
                            <td class="text-center"><code><?= e($rec['order_no']) ?></code></td>
                            <td class="text-start"><strong><?= e($rec['wo_no']) ?></strong></td>
                            <td class="text-center"><span class="badge bg-warning text-dark"><?= e($rec['flute']) ?></span></td>

                            <!-- 7-11 (G - K) Weights -->
                            <td><?= number_format($rec['m1_weight'], 3) ?></td>
                            <td><?= number_format($rec['m2_weight'], 3) ?></td>
                            <td><?= number_format($rec['m3_weight'], 3) ?></td>
                            <td><?= number_format($rec['m4_weight'], 3) ?></td>
                            <td><?= number_format($rec['m5_weight'], 3) ?></td>

                            <!-- 12-16 (L - P) Papers -->
                            <td class="text-center"><?= e($rec['m1_paper']) ?></td>
                            <td class="text-center"><?= e($rec['m2_paper']) ?></td>
                            <td class="text-center"><?= e($rec['m3_paper']) ?></td>
                            <td class="text-center"><?= e($rec['m4_paper']) ?></td>
                            <td class="text-center"><?= e($rec['m5_paper']) ?></td>

                            <!-- 17-21 (Q - U) Sheet dimensions -->
                            <td><?= number_format($rec['paper_width_mm']) ?></td>
                            <td><?= number_format($rec['sheet_length_mm']) ?></td>
                            <td><?= number_format($rec['sheet_weight_kg'], 5) ?></td>
                            <td><?= number_format($rec['good_big_sheets']) ?></td>
                            <td class="<?= $rec['waste_big_sheets'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format($rec['waste_big_sheets']) ?></td>

                            <!-- 22-29 (V - AC) Knives & Scores -->
                            <td class="text-center"><?= $rec['knife_t'] ?></td>
                            <td><?= number_format($rec['knife_s']) ?></td>
                            <td><?= number_format($rec['f1']) ?></td>
                            <td><?= number_format($rec['f2']) ?></td>
                            <td><?= number_format($rec['f3']) ?></td>
                            <td><?= number_format($rec['f4']) ?></td>
                            <td><?= number_format($rec['f5']) ?></td>
                            <td><?= number_format($rec['f6']) ?></td>

                            <!-- 30-31 (AD - AE) Small sheets -->
                            <td><?= number_format($rec['total_small_sheets']) ?></td>
                            <td><?= number_format($rec['good_small_sheets']) ?></td>

                            <!-- 32-38 (AF - AL) Time & Speed -->
                            <td class="text-center"><?= $rec['start_time'] ? substr($rec['start_time'], 11, 8) : '-' ?></td>
                            <td class="text-center"><?= $rec['end_time'] ? substr($rec['end_time'], 11, 8) : '-' ?></td>
                            <td><?= number_format($rec['production_seconds']) ?></td>
                            <td><?= $rec['stop_count'] ?></td>
                            <td class="<?= $rec['stop_time_sec'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format($rec['stop_time_sec']) ?></td>
                            <td><?= $rec['stack_count'] ?></td>
                            <td class="text-primary fw-bold"><?= number_format($rec['avg_speed'], 1) ?></td>

                            <!-- 39-42 (AM - AP) Totals -->
                            <td><?= number_format($rec['total_weight_kg'], 2) ?></td>
                            <td><?= number_format($rec['total_area_sqm'], 1) ?></td>
                            <td class="fw-bold text-success"><?= number_format($rec['total_length_m'], 1) ?></td>
                            <td><?= number_format($rec['total_volume_sqm'], 3) ?></td>

                            <!-- 43-46 (AQ - AT) Additions & Prod Minutes -->
                            <td class="text-center fw-bold <?= $addOrder > 0 ? 'text-danger' : '' ?>"><?= $addOrder ?></td>
                            <td class="text-center fw-bold <?= $addSmall > 0 ? 'text-danger' : '' ?>"><?= number_format($addSmall) ?></td>
                            <td class="text-end <?= $addWt > 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format($addWt, 3) ?></td>
                            <td class="fw-bold"><?= number_format($prodMin, 2) ?></td>

                            <!-- 47-51 (AU - AY) M1 - M5 GSM -->
                            <td class="text-center"><?= $g1 ?: '' ?></td>
                            <td class="text-center"><?= $g2 ?: '' ?></td>
                            <td class="text-center"><?= $g3 ?: '' ?></td>
                            <td class="text-center"><?= $g4 ?: '' ?></td>
                            <td class="text-center"><?= $g5 ?: '' ?></td>

                            <!-- 52-56 (AZ - BD) MM1 - MM5 -->
                            <td class="text-center"><?= $g1 ?: '' ?></td>
                            <td class="text-center"><?= $g2 ?: '' ?></td>
                            <td class="text-center"><?= $g3 ?: '' ?></td>
                            <td class="text-center"><?= $g4 ?: '' ?></td>
                            <td class="text-center"><?= $g5 ?: '' ?></td>

                            <!-- 57-61 (BE - BI) MMM1 - MMM5 Class -->
                            <td class="text-center"><span class="badge bg-light text-dark border"><?= $mmm1 ?></span></td>
                            <td class="text-center"><span class="badge bg-light text-dark border"><?= $mmm2 ?></span></td>
                            <td class="text-center"><span class="badge bg-light text-dark border"><?= $mmm3 ?></span></td>
                            <td class="text-center"><span class="badge bg-light text-dark border"><?= $mmm4 ?></span></td>
                            <td class="text-center"><span class="badge bg-light text-dark border"><?= $mmm5 ?></span></td>

                            <!-- 62-70 (BJ - BR) Station Flute Meters -->
                            <td><?= $lightC ? number_format($lightC, 1) : '' ?></td>
                            <td><?= $medC ? number_format($medC, 1) : '' ?></td>
                            <td><?= $heavyC ? number_format($heavyC, 1) : '' ?></td>
                            <td><?= $lightB ? number_format($lightB, 1) : '' ?></td>
                            <td><?= $medB ? number_format($medB, 1) : '' ?></td>
                            <td><?= $heavyB ? number_format($heavyB, 1) : '' ?></td>
                            <td><?= $lightDF ? number_format($lightDF, 1) : '' ?></td>
                            <td><?= $medDF ? number_format($medDF, 1) : '' ?></td>
                            <td><?= $heavyDF ? number_format($heavyDF, 1) : '' ?></td>

                            <!-- 71-76 (BS - BX) Buckets & PDR -->
                            <td class="text-center fw-bold"><?= $bMeter ?></td>
                            <td class="text-center fw-bold"><?= $bCutleng ?></td>
                            <td><?= number_format($trimSide, 1) ?></td>
                            <td class="text-center fw-bold"><?= $bTrim ?></td>
                            <td class="text-center fw-bold"><?= $bSpeed ?></td>
                            <td class="text-center"><span class="badge bg-primary"><?= e($pdrCode) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================================= -->
<!-- MODAL: แก้ไขเวลาสูญเสีย / หมายเหตุ / สรุปผล -->
<!-- ========================================================================================= -->
<div class="modal fade" id="modalEditLoss" tabindex="-1" aria-labelledby="modalEditLossLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form action="<?= BASE_URL ?>/actions/report_action.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="update_kpi">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="report_id" value="<?= $reportId ?>">

            <div class="modal-header bg-theme-orange text-white">
                <h5 class="modal-title fw-bold" id="modalEditLossLabel">
                    <i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขเวลาสูญเสียรายแผนก & หมายเหตุ
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold fs-7 text-secondary">
                            <i class="fa-solid fa-play text-orange me-1"></i> เวลาเริ่มเดิน
                        </label>
                        <input type="text" name="shift_start_time" class="form-control" value="<?= e($shiftStartTime) ?>" placeholder="เช่น 20:00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold fs-7 text-secondary">
                            <i class="fa-solid fa-stop text-orange me-1"></i> เวลาจบ/ตัดกะ
                        </label>
                        <input type="text" name="shift_end_time" class="form-control" value="<?= e($shiftEndTime) ?>" placeholder="เช่น 07:40">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fs-8 fw-semibold text-secondary">Order Start (แผน)</label>
                        <input type="number" name="order_start" class="form-control form-control-sm" value="<?= $orderStart ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fs-8 fw-semibold text-secondary">Order Finit (แผน)</label>
                        <input type="number" name="order_finit" class="form-control form-control-sm" value="<?= $orderFinit ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fs-8 fw-semibold text-secondary">Order รวม (แผน)</label>
                        <input type="number" name="order_plan_total" class="form-control form-control-sm" value="<?= $orderPlanTotal ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fs-8 fw-semibold text-secondary">งานแทรก (เมตร)</label>
                        <input type="number" step="any" name="inserted_meters" class="form-control form-control-sm" value="<?= $insertedMeters ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fs-8 fw-semibold text-secondary">งานค้าง/ไม่ค้าง (เมตร)</label>
                        <input type="number" step="any" name="pending_meters" class="form-control form-control-sm" value="<?= $pendingMeters ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fs-8 fw-semibold text-secondary">ขาดจำนวน (Order)</label>
                        <input type="number" name="shortage_orders" class="form-control form-control-sm" value="<?= $shortageOrders ?>">
                    </div>
                </div>

                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-clock-rotate-left text-orange me-1"></i> เวลาสูญเสียแยกรายแผนก (Loss Times)
                </h6>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm text-center fs-8 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>แผนก</th>
                                <th>Target (%)</th>
                                <th>จำนวนครั้ง</th>
                                <th>เวลา (นาที)</th>
                                <th>คะแนน (1-3)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lossData as $dept => $vals): ?>
                            <tr>
                                <td class="text-start fw-bold"><?= e($dept) ?></td>
                                <td>
                                    <input type="number" step="0.01" name="loss_tgt[<?= e($dept) ?>]" class="form-control form-control-sm text-center" value="<?= (float)($vals['target_pct'] ?? 0) ?>">
                                </td>
                                <td>
                                    <input type="number" name="loss_occ[<?= e($dept) ?>]" class="form-control form-control-sm text-center" value="<?= (int)($vals['occurrences'] ?? 0) ?>">
                                </td>
                                <td>
                                    <input type="number" name="loss_min[<?= e($dept) ?>]" class="form-control form-control-sm text-center" value="<?= (int)($vals['minutes'] ?? 0) ?>">
                                </td>
                                <td>
                                    <select name="loss_sco[<?= e($dept) ?>]" class="form-select form-select-sm text-center">
                                        <option value="3" <?= ($vals['score'] ?? 3) == 3 ? 'selected' : '' ?>>3</option>
                                        <option value="2" <?= ($vals['score'] ?? 3) == 2 ? 'selected' : '' ?>>2</option>
                                        <option value="1" <?= ($vals['score'] ?? 3) == 1 ? 'selected' : '' ?>>1</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold fs-7 text-secondary">*สรุปผลการเดินงาน*</label>
                    <textarea name="summary_notes" rows="2" class="form-control fs-8"><?= e($report['summary_notes']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold fs-7 text-secondary">*หมายเหตุ*</label>
                    <textarea name="remarks" rows="2" class="form-control fs-8"><?= e($report['remarks']) ?></textarea>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-orange btn-sm px-4 fw-bold">
                    <i class="fa-solid fa-save me-1"></i> บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- เตรียมข้อมูล JSON ส่งให้ Chart.js -->
<?php
// เตรียม Loss Labels & Minutes
$lossLabels = [];
$lossMinutes = [];
foreach ($lossData as $dName => $lInfo) {
    $lossLabels[] = $dName;
    $lossMinutes[] = (int)($lInfo['minutes'] ?? 0);
}

// เตรียม Flute Labels & Percentages
$fluteLabels = ['ลอน B', 'ลอน C', 'ลอน BC', 'ลอน A', 'ลอน AB', 'ลอน E'];
$flutePcts = [];
foreach (['B','C','BC','A','AB','E'] as $_fk) {
    $flutePcts[] = $totFluteWeight > 0 ? round(($fs[$_fk]['weight'] / $totFluteWeight) * 100, 1) : 0;
}

// เตรียมข้อมูลของเสียแยก Station ส่งให้กราฟ
$stationWasteKgList = [];
foreach ($defaultStations as $st) {
    $stationWasteKgList[] = (float)($stationWasteKg[$st] ?? 0);
}

$chartJson = json_encode([
    'lossLabels'         => $lossLabels,
    'lossMinutes'        => $lossMinutes,
    'fluteLabels'        => $fluteLabels,
    'flutePcts'          => $flutePcts,
    'stationLabels'      => $defaultStations,
    'stationWasteKg'     => $stationWasteKgList,
    'totalProdWeight'    => (float)$totFluteWeight
], JSON_UNESCAPED_UNICODE);
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartPayload = <?= $chartJson ?>;
    window.initBhsCharts(chartPayload);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
