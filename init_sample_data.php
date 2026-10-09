<?php
/**
 * Initializer script to seed the first BHS shift report from sample_testing23.html
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = Database::getConnection();
$existingCount = (int)$pdo->query("SELECT COUNT(*) FROM bhs_shift_reports")->fetchColumn();

if ($existingCount > 0) {
    echo "Reports already exist: $existingCount reports found.\n";
    exit(0);
}

echo "Seeding initial report from sample_testing23.html...\n";
$rawHtml = file_get_contents(__DIR__ . '/sample_testing23.html');

$dom = new DOMDocument();
@$dom->loadHTML(mb_convert_encoding($rawHtml, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);
$rows = $xpath->query('//table/tbody/tr');

$parsedRows = [];
$earliestDate = null;
$detectedShifts = [];

foreach ($rows as $r) {
    $cols = $xpath->query('td', $r);
    if ($cols->length < 20) continue;

    $seq        = (int)trim($cols->item(0)->textContent ?? '0');
    $shift      = strtoupper(trim($cols->item(1)->textContent ?? 'B'));
    $planOrder  = trim($cols->item(2)->textContent ?? '');
    $orderNo    = trim($cols->item(3)->textContent ?? '');
    $woNo       = trim($cols->item(4)->textContent ?? '');
    $flute      = strtoupper(trim($cols->item(5)->textContent ?? ''));
    if ($flute === 'CB') $flute = 'BC';

    $wM1 = (float)str_replace(',', '', trim($cols->item(6)->textContent ?? '0'));
    $wM2 = (float)str_replace(',', '', trim($cols->item(7)->textContent ?? '0'));
    $wM3 = (float)str_replace(',', '', trim($cols->item(8)->textContent ?? '0'));
    $wM4 = (float)str_replace(',', '', trim($cols->item(9)->textContent ?? '0'));
    $wM5 = (float)str_replace(',', '', trim($cols->item(10)->textContent ?? '0'));

    $pM1 = trim($cols->item(11)->textContent ?? '');
    $pM2 = trim($cols->item(12)->textContent ?? '');
    $pM3 = trim($cols->item(13)->textContent ?? '');
    $pM4 = trim($cols->item(14)->textContent ?? '');
    $pM5 = trim($cols->item(15)->textContent ?? '');

    $paperWidth = (float)str_replace(',', '', trim($cols->item(16)->textContent ?? '0'));
    $sheetLen   = (float)str_replace(',', '', trim($cols->item(17)->textContent ?? '0'));
    $sheetWt    = (float)str_replace(',', '', trim($cols->item(18)->textContent ?? '0'));
    $bigGood    = (int)str_replace(',', '', trim($cols->item(19)->textContent ?? '0'));
    $bigWaste   = (int)str_replace(',', '', trim($cols->item(20)->textContent ?? '0'));
    $knifeT     = (int)str_replace(',', '', trim($cols->item(21)->textContent ?? '0'));
    $knifeS     = (float)str_replace(',', '', trim($cols->item(22)->textContent ?? '0'));

    $f1 = (int)str_replace(',', '', trim($cols->item(23)->textContent ?? '0'));
    $f2 = (int)str_replace(',', '', trim($cols->item(24)->textContent ?? '0'));
    $f3 = (int)str_replace(',', '', trim($cols->item(25)->textContent ?? '0'));
    $f4 = (int)str_replace(',', '', trim($cols->item(26)->textContent ?? '0'));
    $f5 = (int)str_replace(',', '', trim($cols->item(27)->textContent ?? '0'));
    $f6 = (int)str_replace(',', '', trim($cols->item(28)->textContent ?? '0'));

    $smallTotal = (int)str_replace(',', '', trim($cols->item(29)->textContent ?? '0'));
    $smallGood  = (int)str_replace(',', '', trim($cols->item(30)->textContent ?? '0'));

    $startTimeStr = trim($cols->item(31)->textContent ?? '');
    $endTimeStr   = trim($cols->item(32)->textContent ?? '');
    $prodSec      = (int)str_replace(',', '', trim($cols->item(33)->textContent ?? '0'));
    $stops        = (int)str_replace(',', '', trim($cols->item(34)->textContent ?? '0'));
    $stopTimeSec  = (int)str_replace(',', '', trim($cols->item(35)->textContent ?? '0'));
    $stackCount   = (int)str_replace(',', '', trim($cols->item(36)->textContent ?? '0'));
    $avgSpeed     = (float)str_replace(',', '', trim($cols->item(37)->textContent ?? '0'));
    $totWeight    = (float)str_replace(',', '', trim($cols->item(38)->textContent ?? '0'));
    $totArea      = (float)str_replace(',', '', trim($cols->item(39)->textContent ?? '0'));
    $totMeters    = (float)str_replace(',', '', trim($cols->item(40)->textContent ?? '0'));
    $totVolume    = (float)str_replace(',', '', trim($cols->item(41)->textContent ?? '0'));

    $trimMm = max(0, $paperWidth - $knifeS);

    if (!empty($startTimeStr)) {
        $ts = strtotime($startTimeStr);
        if ($ts) {
            $dStr = date('Y-m-d', $ts);
            if (!$earliestDate || $dStr < $earliestDate) {
                $earliestDate = $dStr;
            }
        }
    }

    if (!empty($shift)) {
        $detectedShifts[$shift] = ($detectedShifts[$shift] ?? 0) + 1;
    }

    $woPrefix = strtoupper(substr($woNo, 0, 3));

    $parsedRows[] = [
        'seq_no'            => $seq,
        'shift'             => $shift,
        'plan_order'        => $planOrder,
        'order_no'          => $orderNo,
        'wo_no'             => $woNo,
        'wo_prefix'         => $woPrefix,
        'flute'             => $flute,
        'm1_weight'         => $wM1,
        'm2_weight'         => $wM2,
        'm3_weight'         => $wM3,
        'm4_weight'         => $wM4,
        'm5_weight'         => $wM5,
        'm1_paper'          => $pM1,
        'm2_paper'          => $pM2,
        'm3_paper'          => $pM3,
        'm4_paper'          => $pM4,
        'm5_paper'          => $pM5,
        'm1_gsm'            => BhsEngine::extractGsm($pM1),
        'm2_gsm'            => BhsEngine::extractGsm($pM2),
        'm3_gsm'            => BhsEngine::extractGsm($pM3),
        'm4_gsm'            => BhsEngine::extractGsm($pM4),
        'm5_gsm'            => BhsEngine::extractGsm($pM5),
        'paper_width_mm'    => $paperWidth,
        'sheet_length_mm'   => $sheetLen,
        'sheet_weight_kg'   => $sheetWt,
        'good_big_sheets'   => $bigGood,
        'waste_big_sheets'  => $bigWaste,
        'knife_t'           => $knifeT,
        'knife_s'           => $knifeS,
        'trim_mm'           => $trimMm,
        'f1'                => $f1,
        'f2'                => $f2,
        'f3'                => $f3,
        'f4'                => $f4,
        'f5'                => $f5,
        'f6'                => $f6,
        'total_small_sheets'=> $smallTotal,
        'good_small_sheets' => $smallGood,
        'start_time'        => !empty($startTimeStr) ? date('Y-m-d H:i:s', strtotime($startTimeStr)) : null,
        'end_time'          => !empty($endTimeStr) ? date('Y-m-d H:i:s', strtotime($endTimeStr)) : null,
        'production_seconds'=> $prodSec,
        'stop_count'        => $stops,
        'stop_time_sec'     => $stopTimeSec,
        'stack_count'       => $stackCount,
        'avg_speed'         => $avgSpeed,
        'total_weight_kg'   => $totWeight,
        'total_area_sqm'    => $totArea,
        'total_length_m'    => $totMeters,
        'total_volume_sqm'  => $totVolume
    ];
}

$pdo->beginTransaction();

$stmtReport = $pdo->prepare("
    INSERT INTO bhs_shift_reports (
        title, shift, report_date, shift_end_time, target_orders, target_meters, 
        target_speed, target_avg_meters, target_time_total, target_time_run, 
        target_loss_pct, loss_details, summary_notes, remarks, raw_filename, created_by
    ) VALUES (
        'รายงานประสิทธิภาพการเดินงาน BHS กะ B', 'B', '2026-09-28', '8.00', 120, 68056.00,
        100.00, 500.00, 720, 675,
        6.25, :loss_details, 'เดินงานตามแผนกะ B ได้ผลผลิตและเกรดคุณภาพเป็นไปตามเป้าหมาย', 'ไม่มีปัญหาเครื่องจักรสะดุดรุนแรง การเปลี่ยนม้วนราบรื่น', 'testing (23).html', 1
    )
");

$defaultLoss = BhsEngine::getDefaultLossDepartments();
$stmtReport->execute([
    ':loss_details' => json_encode($defaultLoss, JSON_UNESCAPED_UNICODE)
]);
$reportId = (int)$pdo->lastInsertId();

$sqlRecord = "
    INSERT INTO bhs_production_records (
        report_id, seq_no, shift, plan_order, order_no, wo_no, wo_prefix, flute,
        m1_weight, m2_weight, m3_weight, m4_weight, m5_weight,
        m1_paper, m2_paper, m3_paper, m4_paper, m5_paper,
        m1_gsm, m2_gsm, m3_gsm, m4_gsm, m5_gsm,
        paper_width_mm, sheet_length_mm, sheet_weight_kg,
        good_big_sheets, waste_big_sheets, knife_t, knife_s, trim_mm,
        f1, f2, f3, f4, f5, f6,
        total_small_sheets, good_small_sheets, start_time, end_time,
        production_seconds, stop_count, stop_time_sec, stack_count,
        avg_speed, total_weight_kg, total_area_sqm, total_length_m, total_volume_sqm
    ) VALUES (
        :report_id, :seq_no, :shift, :plan_order, :order_no, :wo_no, :wo_prefix, :flute,
        :m1_weight, :m2_weight, :m3_weight, :m4_weight, :m5_weight,
        :m1_paper, :m2_paper, :m3_paper, :m4_paper, :m5_paper,
        :m1_gsm, :m2_gsm, :m3_gsm, :m4_gsm, :m5_gsm,
        :paper_width_mm, :sheet_length_mm, :sheet_weight_kg,
        :good_big_sheets, :waste_big_sheets, :knife_t, :knife_s, :trim_mm,
        :f1, :f2, :f3, :f4, :f5, :f6,
        :total_small_sheets, :good_small_sheets, :start_time, :end_time,
        :production_seconds, :stop_count, :stop_time_sec, :stack_count,
        :avg_speed, :total_weight_kg, :total_area_sqm, :total_length_m, :total_volume_sqm
    )
";

$stmtRec = $pdo->prepare($sqlRecord);
foreach ($parsedRows as $row) {
    $row['report_id'] = $reportId;
    $stmtRec->execute($row);
}

$pdo->commit();
echo "Successfully seeded report ID: $reportId with " . count($parsedRows) . " records!\n";
