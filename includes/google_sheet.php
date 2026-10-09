<?php
/**
 * Google Sheets Synchronization Helper
 * จัดการรวบรวมข้อมูลและส่ง HTTP POST ไปยัง Google Apps Script Web App
 */

require_once __DIR__ . '/../config/google_sheet.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * ตรวจสอบว่าได้กำหนด Webhook URL ของ Google Sheets หรือยัง
 */
function is_google_sheet_configured(): bool {
    return defined('GOOGLE_SHEET_WEBHOOK_URL') && !empty(trim(GOOGLE_SHEET_WEBHOOK_URL));
}

/**
 * ดึงข้อมูลสรุปของรายงานจาก ID แล้วส่งไปยัง Google Sheets
 * 
 * @param int $reportId
 * @return array ['success' => bool, 'message' => string, 'details' => mixed]
 */
function sync_report_to_google_sheet(int $reportId): array {
    if (!is_google_sheet_configured()) {
        return [
            'success' => false,
            'message' => 'ยังไม่ได้ตั้งค่า GOOGLE_SHEET_WEBHOOK_URL ในไฟล์ config/google_sheet.php'
        ];
    }

    try {
        $pdo = Database::getConnection();

        // 1. ดึงข้อมูลรายงานหลัก
        $stmt = $pdo->prepare("SELECT * FROM bhs_shift_reports WHERE id = :id");
        $stmt->execute([':id' => $reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            return [
                'success' => false,
                'message' => "ไม่พบข้อมูลรายงาน ID: $reportId"
            ];
        }

        // 2. คำนวณสรุปข้อมูลตาม Engine เดียวกับ report_view.php
        $calc = calculate_report_summary_engine($pdo, $report);

        // 3. เตรียมชุดข้อมูล (Payload) สำหรับส่งไป Google Sheets
        $payload = [
            'report_id'             => (int)$report['id'],
            'report_date'           => $report['report_date'],
            'shift'                 => $report['shift'],
            'machine_name'          => $report['machine_name'] ?? 'BHS',
            'shift_start_time'      => $report['shift_start_time'] ?? '',
            'shift_end_time'        => $report['shift_end_time'] ?? '',
            'actual_shift_duration' => (int)($calc['actual_shift_duration'] ?? 0),
            'total_meters'          => round((float)($calc['tot_flute_meters'] ?? 0), 2),
            'total_weight_ton'      => round((float)($calc['tot_flute_weight'] ?? 0) / 1000, 3), // แปลง กก. เป็น ตัน
            'total_orders'          => (int)($calc['total_orders_actual'] ?? 0),
            'avg_speed'             => round((float)($calc['avg_speed_actual'] ?? 0), 2),
            'running_time_min'      => (int)($calc['running_time_min'] ?? 0),
            'total_loss_minutes'    => (int)($calc['total_loss_minutes'] ?? 0),
            'loss_total_pct'        => round((float)($calc['loss_total_pct'] ?? 0), 2),
            'total_kpi_score'       => (int)($calc['total_score'] ?? 0),
            'summary_notes'         => $report['summary_notes'] ?? '',
            'remarks'               => $report['remarks'] ?? '',
            'raw_filename'          => $report['raw_filename'] ?? ''
        ];

        // 4. ส่งข้อมูลผ่าน cURL ไปยัง Google Apps Script Web App
        return send_payload_to_google_sheet($payload);

    } catch (Exception $e) {
        error_log("[Google Sheet Sync Error] " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการรวบรวมข้อมูล: ' . $e->getMessage()
        ];
    }
}

/**
 * ส่ง Payload JSON ไปยัง Google Apps Script Webhook
 */
function send_payload_to_google_sheet(array $payload): array {
    $url = trim(GOOGLE_SHEET_WEBHOOK_URL);
    $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonData,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true, // Google Apps Script มัก redirect 302 ไปหน้าผลลัพธ์
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [
            'success' => false,
            'message' => 'เชื่อมต่อ Google Apps Script ไม่สำเร็จ: ' . $curlErr
        ];
    }

    $resData = json_decode($response, true);
    if ($resData && isset($resData['success'])) {
        return [
            'success' => (bool)$resData['success'],
            'message' => $resData['message'] ?? 'ส่งข้อมูลเข้า Google Sheets สำเร็จ',
            'data'    => $resData
        ];
    }

    // กรณีได้รับผลตอบกลับที่ไม่ใช่ JSON มาตรฐาน
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'ส่งข้อมูลเข้า Google Sheets เรียบร้อยแล้ว (HTTP ' . $httpCode . ')',
            'raw'     => $response
        ];
    }

    return [
        'success' => false,
        'message' => 'Google Apps Script ส่งสถานะผิดพลาด (HTTP ' . $httpCode . ')',
        'raw'     => $response
    ];
}

/**
 * ฟังก์ชันคำนวณสรุปข้อมูลตัวเลขของรายงาน
 */
function calculate_report_summary_engine(PDO $pdo, array $report): array {
    $reportId = (int)$report['id'];

    // ดึงข้อมูลรายการย่อย
    $stmtItems = $pdo->prepare("SELECT * FROM bhs_report_items WHERE report_id = :report_id");
    $stmtItems->execute([':report_id' => $reportId]);
    $items = $stmtItems->fetchAll();

    $totFluteMeters = 0;
    $totFluteWeight = 0;
    $totalOrdersActual = count($items);
    $totalProdSec = 0;

    foreach ($items as $it) {
        $totFluteMeters += (float)($it['tot_meters'] ?? 0);
        $totFluteWeight += (float)($it['tot_weight'] ?? 0);
        $totalProdSec   += (int)($it['prod_sec'] ?? 0);
    }

    $avgSpeedActual = ($totalProdSec > 0) ? round(($totFluteMeters / ($totalProdSec / 60)), 1) : 0;

    // คำนวณเวลาเดินกะ (นาที)
    $startTime = $report['shift_start_time'] ?? '08:00';
    $endTime   = $report['shift_end_time'] ?? '20:00';
    $t1 = strtotime("1970-01-01 $startTime:00");
    $t2 = strtotime("1970-01-01 $endTime:00");
    $actualShiftDuration = (int)($report['actual_time_total'] ?? 0);
    if ($actualShiftDuration <= 0 && $t1 && $t2) {
        $diff = ($t2 - $t1) / 60;
        if ($diff < 0) $diff += 24 * 60; // ข้ามวัน
        $actualShiftDuration = (int)$diff;
    }

    // เวลาสูญเสีย
    $lossDetails = json_decode($report['loss_details'] ?? '[]', true) ?: [];
    $totalLossMinutes = 0;
    $totalScore = 0;
    foreach ($lossDetails as $dept => $vals) {
        $totalLossMinutes += (int)($vals['minutes'] ?? 0);
        $totalScore       += (int)($vals['score'] ?? 3);
    }

    $lossTotalPct = ($actualShiftDuration > 0) ? ($totalLossMinutes / $actualShiftDuration) * 100 : 0;
    $runningTimeMin = max(0, $actualShiftDuration - $totalLossMinutes);

    return [
        'actual_shift_duration' => $actualShiftDuration,
        'tot_flute_meters'      => $totFluteMeters,
        'tot_flute_weight'      => $totFluteWeight,
        'total_orders_actual'   => $totalOrdersActual,
        'avg_speed_actual'      => $avgSpeedActual,
        'running_time_min'      => $runningTimeMin,
        'total_loss_minutes'    => $totalLossMinutes,
        'loss_total_pct'        => $lossTotalPct,
        'total_score'           => $totalScore
    ];
}
