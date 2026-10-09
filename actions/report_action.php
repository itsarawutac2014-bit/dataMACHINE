<?php
/**
 * Report Management Action
 * บันทึกการแก้ไขเป้าหมาย (KPI Targets), เวลาสูญเสียรายแผนก (Loss Times), หมายเหตุ และการลบรายงาน
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$action = sanitize_text($_POST['action'] ?? ($_GET['action'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if (!empty($_POST['is_ajax'])) {
            json_response(['success' => false, 'message' => 'Session หรือ Token หมดอายุ กรุณารีเฟรชหน้าจอใหม่อีกครั้ง']);
        }
        set_flash('danger', 'CSRF Token ไม่ถูกต้อง');
        redirect('views/dashboard.php');
    }
}

$pdo = Database::getConnection();

// 1. อัปเดตเวลาสูญเสียและหมายเหตุรายงาน
if ($action === 'update_kpi' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    if ($reportId <= 0) {
        set_flash('warning', 'ไม่พบรายงานที่ต้องการบันทึก');
        redirect('views/dashboard.php');
    }

    $summaryNotes   = sanitize_text($_POST['summary_notes'] ?? '');
    $remarks        = sanitize_text($_POST['remarks'] ?? '');
    $shiftStartTime = sanitize_text($_POST['shift_start_time'] ?? '20:00');
    $shiftEndTime   = sanitize_text($_POST['shift_end_time'] ?? '07:40');

    $orderStart     = (int)($_POST['order_start'] ?? 10);
    $orderFinit     = (int)($_POST['order_finit'] ?? 1120);
    $orderPlanTotal = (int)($_POST['order_plan_total'] ?? 120);
    $insertedMeters = (float)($_POST['inserted_meters'] ?? 0.0);
    $pendingMeters  = (float)($_POST['pending_meters'] ?? 0.0);
    $shortageOrders = (int)($_POST['shortage_orders'] ?? 24);

    require_once __DIR__ . '/../includes/helpers.php';
    $actualTimeTotal = BhsEngine::calculateShiftDurationMinutes($shiftStartTime, $shiftEndTime);

    // รวบรวม Loss Details รายแผนกแบบ Dynamic (รองรับการเพิ่ม/เปลี่ยนชื่อ/ลบแผนก)
    $lossDetails = [];
    if (!empty($_POST['loss_name']) && is_array($_POST['loss_name'])) {
        foreach ($_POST['loss_name'] as $idx => $dept) {
            $dept = sanitize_text($dept);
            if ($dept === '') continue;
            $occ = (int)($_POST['loss_occ'][$idx] ?? ($_POST['loss_occ'][$dept] ?? 0));
            $min = (int)($_POST['loss_min'][$idx] ?? ($_POST['loss_min'][$dept] ?? 0));
            $tgt = (float)($_POST['loss_tgt'][$idx] ?? ($_POST['loss_tgt'][$dept] ?? 0.0));
            $sco = (int)($_POST['loss_sco'][$idx] ?? ($_POST['loss_sco'][$dept] ?? 3));
            $lossDetails[$dept] = [
                'target_pct'  => $tgt,
                'occurrences' => $occ,
                'minutes'     => $min,
                'score'       => $sco
            ];
        }
    } else if (!empty($_POST['loss_min']) && is_array($_POST['loss_min'])) {
        foreach ($_POST['loss_min'] as $dept => $minVal) {
            $dept = sanitize_text($dept);
            if ($dept === '') continue;
            $occ = (int)($_POST['loss_occ'][$dept] ?? 0);
            $min = (int)$minVal;
            $tgt = (float)($_POST['loss_tgt'][$dept] ?? 0.0);
            $sco = (int)($_POST['loss_sco'][$dept] ?? 3);
            $lossDetails[$dept] = [
                'target_pct'  => $tgt,
                'occurrences' => $occ,
                'minutes'     => $min,
                'score'       => $sco
            ];
        }
    }

    // ตรวจสอบค่า Targets (หากส่งมาด้วย ให้บันทึกอัปเดต Target ของรายงานด้วย)
    $targetOrders    = isset($_POST['target_orders']) ? (int)$_POST['target_orders'] : null;
    $targetMeters    = isset($_POST['target_meters']) ? (float)$_POST['target_meters'] : null;
    $targetSpeed     = isset($_POST['target_speed']) ? (float)$_POST['target_speed'] : null;
    $targetAvgMeters = isset($_POST['target_avg_meters']) ? (float)$_POST['target_avg_meters'] : null;
    $targetTimeTotal = isset($_POST['target_time_total']) ? (int)$_POST['target_time_total'] : null;
    $targetTimeRun   = isset($_POST['target_time_run']) ? (int)$_POST['target_time_run'] : null;
    $targetLossPct   = isset($_POST['target_loss_pct']) ? (float)$_POST['target_loss_pct'] : null;

    // รับค่า station_waste
    $stationWasteData = [];
    $defaultStations = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];
    if (isset($_POST['station_waste']) && is_array($_POST['station_waste'])) {
        foreach ($defaultStations as $st) {
            $kg = isset($_POST['station_waste'][$st]) ? (float)$_POST['station_waste'][$st] : 0.0;
            $stationWasteData[$st] = ['kg' => $kg];
        }
    }

    try {
        $sql = "
            UPDATE bhs_shift_reports
            SET shift_start_time = :shift_start_time,
                shift_end_time = :shift_end_time,
                actual_time_total = :actual_time_total,
                order_start = :order_start,
                order_finit = :order_finit,
                order_plan_total = :order_plan_total,
                inserted_meters = :inserted_meters,
                pending_meters = :pending_meters,
                shortage_orders = :shortage_orders,
                summary_notes = :summary_notes,
                remarks = :remarks,
                loss_details = :loss_details,
                station_waste = :station_waste
        ";
        
        $params = [
            ':shift_start_time' => $shiftStartTime,
            ':shift_end_time'   => $shiftEndTime,
            ':actual_time_total'=> $actualTimeTotal,
            ':order_start'      => $orderStart,
            ':order_finit'      => $orderFinit,
            ':order_plan_total' => $orderPlanTotal,
            ':inserted_meters'  => $insertedMeters,
            ':pending_meters'   => $pendingMeters,
            ':shortage_orders'  => $shortageOrders,
            ':summary_notes'    => $summaryNotes,
            ':remarks'          => $remarks,
            ':loss_details'     => json_encode($lossDetails, JSON_UNESCAPED_UNICODE),
            ':station_waste'    => json_encode($stationWasteData, JSON_UNESCAPED_UNICODE),
            ':id'               => $reportId
        ];

        if ($targetOrders !== null) {
            $sql .= ", target_orders = :target_orders";
            $params[':target_orders'] = $targetOrders;
        }
        if ($targetMeters !== null) {
            $sql .= ", target_meters = :target_meters";
            $params[':target_meters'] = $targetMeters;
        }
        if ($targetSpeed !== null) {
            $sql .= ", target_speed = :target_speed";
            $params[':target_speed'] = $targetSpeed;
        }
        if ($targetAvgMeters !== null) {
            $sql .= ", target_avg_meters = :target_avg_meters";
            $params[':target_avg_meters'] = $targetAvgMeters;
        }
        if ($targetTimeTotal !== null) {
            $sql .= ", target_time_total = :target_time_total";
            $params[':target_time_total'] = $targetTimeTotal;
        }
        if ($targetTimeRun !== null) {
            $sql .= ", target_time_run = :target_time_run";
            $params[':target_time_run'] = $targetTimeRun;
        }
        if ($targetLossPct !== null) {
            $sql .= ", target_loss_pct = :target_loss_pct";
            $params[':target_loss_pct'] = $targetLossPct;
        }

        $sql .= " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Auto Sync ไปยัง Google Sheets หากเปิดใช้งาน
        require_once __DIR__ . '/../includes/google_sheet.php';
        if (is_google_sheet_configured() && defined('GOOGLE_SHEET_AUTO_SYNC') && GOOGLE_SHEET_AUTO_SYNC) {
            sync_report_to_google_sheet($reportId);
        }

        $msg = 'บันทึกการแก้ไขเวลาสูญเสียและข้อมูลรายงานเรียบร้อยแล้ว';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['is_ajax'])) {
            json_response(['success' => true, 'message' => $msg]);
        }
        set_flash('success', $msg);
        redirect("views/report_view.php?id=" . $reportId);
    } catch (Exception $e) {
        error_log("[Report Update Error] " . $e->getMessage());
        $errMsg = 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['is_ajax'])) {
            json_response(['success' => false, 'message' => $errMsg], 500);
        }
        set_flash('danger', $errMsg);
        redirect("views/report_view.php?id=" . $reportId);
    }
}

// 2. จัดการเป้าหมายและหัวข้อตารางแม่แบบ (Admin Only: Save Target Template & Dynamic Topics)
if ($action === 'save_target_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();

    $templateId     = (int)($_POST['template_id'] ?? 0);
    $templateName   = sanitize_text($_POST['template_name'] ?? 'default');
    $machineName    = strtoupper(sanitize_text($_POST['machine_name'] ?? 'BHS'));
    $targetOrders   = (int)($_POST['target_orders'] ?? 120);
    $targetMeters   = (float)($_POST['target_meters'] ?? 68056.00);
    $targetSpeed    = (float)($_POST['target_speed'] ?? 100.00);
    $targetAvgMeters= (float)($_POST['target_avg_meters'] ?? 500.00);
    $targetTimeTotal= (int)($_POST['target_time_total'] ?? 720);
    $targetTimeRun  = (int)($_POST['target_time_run'] ?? 675);
    $targetLossPct  = (float)($_POST['target_loss_pct'] ?? 10.00);
    $targetShortage = (float)($_POST['target_shortage_pct'] ?? 15.00);
    $syncReportId   = (int)($_POST['sync_report_id'] ?? 0);
    $syncAllReports = !empty($_POST['sync_all_reports']);

    // รวบรวมรายการแผนก/หัวข้อที่ส่งมา (รองรับเพิ่ม/เปลี่ยนชื่อ/ลบได้ไม่จำกัด)
    $deptNames   = $_POST['dept_name'] ?? [];
    $deptTargets = $_POST['dept_target'] ?? [];
    $deptActives = $_POST['dept_active'] ?? [];

    $lossDeptsList = [];
    $totalDeptTargetSum = 0.0;

    foreach ($deptNames as $i => $dName) {
        $dName = sanitize_text($dName);
        if ($dName === '') continue;

        $tVal = (float)($deptTargets[$i] ?? 0.0);
        $isActive = isset($deptActives[$i]) ? 1 : 0;

        $lossDeptsList[] = [
            'name'       => $dName,
            'target_pct' => $tVal,
            'is_active'  => $isActive
        ];

        if ($isActive) {
            $totalDeptTargetSum += $tVal;
        }
    }

    if ($targetLossPct <= 0 && $totalDeptTargetSum > 0) {
        $targetLossPct = $totalDeptTargetSum;
    }

    $jsonDepts = json_encode($lossDeptsList, JSON_UNESCAPED_UNICODE);

    // รวบรวม Station Waste Targets (% ต่อ Station)
    $stationTargetPctPost = $_POST['station_target_pct'] ?? $_POST['station_target_kg'] ?? [];
    $stationWasteTargetsData = [];
    $defaultStationsForAction = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];
    foreach ($defaultStationsForAction as $st) {
        $tPctVal = (float)($stationTargetPctPost[$st] ?? 0);
        $stationWasteTargetsData[$st] = [
            'target_pct' => $tPctVal,
            'target_kg'  => $tPctVal
        ];
    }
    $jsonStationWasteTargets = json_encode($stationWasteTargetsData, JSON_UNESCAPED_UNICODE);

    $currentUserId = current_user()['id'] ?? 1;

    try {
        // บันทึกแม่แบบลง bhs_target_templates
        if ($templateId > 0) {
            $stmt = $pdo->prepare("
                UPDATE bhs_target_templates
                SET template_name = :template_name,
                    machine_name = :machine_name,
                    target_orders = :target_orders,
                    target_meters = :target_meters,
                    target_speed = :target_speed,
                    target_avg_meters = :target_avg_meters,
                    target_time_total = :target_time_total,
                    target_time_run = :target_time_run,
                    target_loss_pct = :target_loss_pct,
                    target_shortage_pct = :target_shortage_pct,
                    loss_departments = :loss_departments,
                    station_waste_targets = :station_waste_targets,
                    updated_by = :updated_by
                WHERE id = :id
            ");
            $stmt->execute([
                ':template_name'           => $templateName,
                ':machine_name'            => $machineName,
                ':target_orders'           => $targetOrders,
                ':target_meters'           => $targetMeters,
                ':target_speed'            => $targetSpeed,
                ':target_avg_meters'       => $targetAvgMeters,
                ':target_time_total'       => $targetTimeTotal,
                ':target_time_run'         => $targetTimeRun,
                ':target_loss_pct'         => $targetLossPct,
                ':target_shortage_pct'     => $targetShortage,
                ':loss_departments'        => $jsonDepts,
                ':station_waste_targets'   => $jsonStationWasteTargets,
                ':updated_by'              => $currentUserId,
                ':id'                      => $templateId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO bhs_target_templates (
                    template_name, machine_name, target_orders, target_meters, target_speed,
                    target_avg_meters, target_time_total, target_time_run, target_loss_pct,
                    target_shortage_pct, loss_departments, updated_by
                ) VALUES (
                    :template_name, :machine_name, :target_orders, :target_meters, :target_speed,
                    :target_avg_meters, :target_time_total, :target_time_run, :target_loss_pct,
                    :target_shortage_pct, :loss_departments, :updated_by
                )
            ");
            $stmt->execute([
                ':template_name'      => $templateName,
                ':machine_name'       => $machineName,
                ':target_orders'      => $targetOrders,
                ':target_meters'      => $targetMeters,
                ':target_speed'       => $targetSpeed,
                ':target_avg_meters'  => $targetAvgMeters,
                ':target_time_total'  => $targetTimeTotal,
                ':target_time_run'    => $targetTimeRun,
                ':target_loss_pct'    => $targetLossPct,
                ':target_shortage_pct'=> $targetShortage,
                ':loss_departments'   => $jsonDepts,
                ':updated_by'         => $currentUserId
            ]);
            $templateId = (int)$pdo->lastInsertId();
        }

        // ซิงค์ไปยังรายงานเดี่ยว (หากเลือก) หรือ รายงานทั้งหมด
        if ($syncReportId > 0 || $syncAllReports) {
            $whereReport = $syncAllReports ? "1" : "id = :report_id";
            
            // ดึงรายงานเพื่อปรับ loss_details ตามหัวข้อใหม่
            $stmtReps = $pdo->prepare("SELECT id, loss_details FROM bhs_shift_reports WHERE $whereReport");
            if ($syncAllReports) {
                $stmtReps->execute();
            } else {
                $stmtReps->execute([':report_id' => $syncReportId]);
            }
            $targetReports = $stmtReps->fetchAll();

            $stmtUpdateRep = $pdo->prepare("
                UPDATE bhs_shift_reports
                SET target_orders = :target_orders,
                    target_meters = :target_meters,
                    target_speed = :target_speed,
                    target_avg_meters = :target_avg_meters,
                    target_time_total = :target_time_total,
                    target_time_run = :target_time_run,
                    target_loss_pct = :target_loss_pct,
                    loss_details = :loss_details
                WHERE id = :id
            ");

            foreach ($targetReports as $rep) {
                $existingLoss = json_decode($rep['loss_details'] ?? '[]', true) ?: [];
                $newReportLoss = [];

                foreach ($lossDeptsList as $d) {
                    if (!$d['is_active']) continue;
                    $dName = $d['name'];
                    $prevInfo = $existingLoss[$dName] ?? null;

                    $newReportLoss[$dName] = [
                        'target_pct'  => (float)$d['target_pct'],
                        'occurrences' => $prevInfo ? (int)($prevInfo['occurrences'] ?? 0) : 0,
                        'minutes'     => $prevInfo ? (int)($prevInfo['minutes'] ?? 0) : 0,
                        'score'       => $prevInfo ? (int)($prevInfo['score'] ?? 3) : 3
                    ];
                }

                $stmtUpdateRep->execute([
                    ':target_orders'    => $targetOrders,
                    ':target_meters'    => $targetMeters,
                    ':target_speed'     => $targetSpeed,
                    ':target_avg_meters'=> $targetAvgMeters,
                    ':target_time_total'=> $targetTimeTotal,
                    ':target_time_run'  => $targetTimeRun,
                    ':target_loss_pct'  => $targetLossPct,
                    ':loss_details'     => json_encode($newReportLoss, JSON_UNESCAPED_UNICODE),
                    ':id'               => $rep['id']
                ]);
            }
        }

        $succMsg = 'บันทึกการตั้งค่าเป้าหมายและหัวข้อตารางเรียบร้อยแล้ว' . ($syncReportId > 0 || $syncAllReports ? ' (พร้อมซิงค์ไปยังรายงานแล้ว)' : '');
        if (!empty($_POST['is_ajax'])) {
            json_response(['success' => true, 'message' => $succMsg]);
        }
        set_flash('success', $succMsg);
        redirect("views/admin_targets.php" . ($syncReportId > 0 ? "?report_id=$syncReportId" : ""));
    } catch (Exception $e) {
        error_log("[Target Template Save Error] " . $e->getMessage());
        $errMsg = 'เกิดข้อผิดพลาดในการบันทึกเป้าหมาย: ' . $e->getMessage();
        if (!empty($_POST['is_ajax'])) {
            json_response(['success' => false, 'message' => $errMsg], 500);
        }
        set_flash('danger', $errMsg);
        redirect("views/admin_targets.php");
    }
}

// 3. รีเซ็ตเป้าหมายเป็นค่าเริ่มต้นโรงงาน (Reset to Defaults)
if ($action === 'reset_target_defaults' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();

    $templateId = (int)($_POST['template_id'] ?? 0);
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

    try {
        $stmt = $pdo->prepare("
            UPDATE bhs_target_templates
            SET target_orders = 120,
                target_meters = 68056.00,
                target_speed = 100.00,
                target_avg_meters = 500.00,
                target_time_total = 720,
                target_time_run = 675,
                target_loss_pct = 10.00,
                target_shortage_pct = 15.00,
                loss_departments = :loss_departments
            WHERE id = :id OR template_name = 'default'
        ");
        $stmt->execute([
            ':loss_departments' => json_encode($defaultDepts, JSON_UNESCAPED_UNICODE),
            ':id'               => $templateId
        ]);

        set_flash('success', 'คืนค่าเป้าหมายและหัวข้อมาตรฐานโรงงานเรียบร้อยแล้ว');
    } catch (Exception $e) {
        set_flash('danger', 'เกิดข้อผิดพลาดในการคืนค่า: ' . $e->getMessage());
    }
    redirect("views/admin_targets.php");
}

// 4. ลบรายงาน
if ($action === 'delete') {
    require_admin();
    $reportId = (int)($_POST['report_id'] ?? ($_GET['report_id'] ?? 0));
    if ($reportId <= 0) {
        set_flash('warning', 'ไม่พบรายงาน');
        redirect('views/dashboard.php');
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM bhs_shift_reports WHERE id = :id");
        $stmt->execute([':id' => $reportId]);
        set_flash('success', 'ลบรายงานและประวัติการเดินงานเรียบร้อยแล้ว');
    } catch (Exception $e) {
        error_log("[Report Delete Error] " . $e->getMessage());
        set_flash('danger', 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage());
    }
    redirect('views/dashboard.php');
}

redirect('views/dashboard.php');
