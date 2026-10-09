<?php
/**
 * Upload & Machine Data Parser Action
 * รองรับทั้งการอัปโหลดไฟล์ Excel (.xlsx, .xls), HTML/TXT และการ Copy & Paste โค้ดตารางมาวาง
 * ถอดข้อมูลครบทั้ง 2 หน้า: "ข้อมูลเครื่อง" (76 คอลัมน์ A ถึง BX) และ "Relive" (ดึงเวลาสูญเสียและเวลาเดินงานอัตโนมัติ)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

// ตรวจสอบ Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }
    redirect('views/upload.php');
}

// ตรวจสอบ CSRF Token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $msg = 'ความปลอดภัยล้มเหลว: CSRF Token ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['success' => false, 'message' => $msg], 403);
    }
    set_flash('danger', $msg);
    redirect('views/upload.php');
}

$rawHtml = '';
$uploadedFilename = 'testing_pasted.html';
$isXlsx = false;
$xlsxTmpPath = null;

// ตรวจสอบว่าส่งมาแบบ File Upload หรือ Textarea Paste
if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['report_file']['tmp_name'];
    $uploadedFilename = sanitize_text($_FILES['report_file']['name']);
    $ext = strtolower(pathinfo($uploadedFilename, PATHINFO_EXTENSION));

    $magic = @file_get_contents($tmpName, false, null, 0, 4);
    $fileHeader = @file_get_contents($tmpName, false, null, 0, 4096);

    // ตรวจสอบว่าเป็นไฟล์ ZIP / XLSX จริงหรือไม่
    if ($magic === "PK\x03\x04" || $ext === 'xlsx') {
        $zip = new ZipArchive();
        $openRes = $zip->open($tmpName, ZipArchive::RDONLY);
        if ($openRes !== true) {
            $openRes = $zip->open($tmpName);
        }
        if ($openRes !== true) {
            $tempZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bhs_' . uniqid() . '.zip';
            if (@copy($tmpName, $tempZip)) {
                $openRes = $zip->open($tempZip, ZipArchive::RDONLY);
                if ($openRes !== true) {
                    $openRes = $zip->open($tempZip);
                }
            }
        }

        if ($openRes === true) {
            $isXlsx = true;
            $zipArchive = $zip;
        } else {
            // ไม่สามารถเปิดเป็น Zip ได้ เช็คว่าข้างในเป็น HTML table หรือไม่ (เช่น ไฟล์ที่ Export จากระบบแล้วตั้งชื่อเป็น .xls/.xlsx)
            if ($fileHeader && (stripos($fileHeader, '<table') !== false || stripos($fileHeader, '<tr') !== false || stripos($fileHeader, '<html') !== false)) {
                $isXlsx = false;
                $rawHtml = file_get_contents($tmpName);
            } else {
                throw new Exception("ไม่สามารถเปิดอ่านโครงสร้างไฟล์ Excel (.xlsx) ได้ (รหัส: {$openRes}, ขนาด: " . filesize($tmpName) . " ไบต์, ชื่อไฟล์: {$uploadedFilename})");
            }
        }
    } else {
        // ไม่ใช่ PK\x03\x04 เช่น .html, .htm, .txt หรือ .xls ที่เป็นโค้ด HTML ตาราง
        $isXlsx = false;
        $rawHtml = file_get_contents($tmpName);
    }
} elseif (!empty($_POST['pasted_html'])) {
    $rawHtml = trim($_POST['pasted_html']);
    $uploadedFilename = 'pasted_html_' . date('Ymd_His') . '.html';
} elseif (!empty($_POST['use_sample'])) {
    // โหลดข้อมูลตัวอย่างจากไฟล์ sample_testing23.html ในเครื่อง
    $samplePath = __DIR__ . '/../sample_testing23.html';
    if (file_exists($samplePath)) {
        $rawHtml = file_get_contents($samplePath);
        $uploadedFilename = 'testing (23).html';
    }
}

if (!$isXlsx && empty($rawHtml)) {
    $msg = 'ไม่พบข้อมูลไฟล์: กรุณาเลือกไฟล์ Excel (.xlsx) หรือไฟล์ตาราง HTML หรือวางโค้ดตารางเครื่องจักร';
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['success' => false, 'message' => $msg], 400);
    }
    set_flash('warning', $msg);
    redirect('views/upload.php');
}

try {
    $parsedRows = [];
    $detectedShifts = [];
    $earliestDate = null;
    $reliveData = [
        'order_start'     => null,
        'order_finit'     => null,
        'shift_duration'  => 720,
        'running_minutes' => null,
        'total_loss_min'  => null,
        'losses'          => []
    ];

    if ($isXlsx) {
        // ==============================================================
        // โหมด 1: อ่านไฟล์ Excel (.xlsx) ด้วย ZipArchive
        // ==============================================================
        $zip = isset($zipArchive) ? $zipArchive : new ZipArchive();
        if (!isset($zipArchive)) {
            $openRes = $zip->open($xlsxTmpPath, ZipArchive::RDONLY);
            if ($openRes !== true) {
                $openRes = $zip->open($xlsxTmpPath);
            }
            if ($openRes !== true) {
                throw new Exception('ไม่สามารถเปิดอ่านโครงสร้างไฟล์ Excel (.xlsx) ได้');
            }
        }

        // 1.1 อ่าน Shared Strings
        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            preg_match_all('/<si>[\s\S]*?<\/si>/u', $ssXml, $siMatches);
            foreach ($siMatches[0] as $si) {
                preg_match_all('/<t[^>]*>([^<]*)<\/t>/u', $si, $tm);
                $shared[] = implode('', $tm[1]);
            }
        }

        // 1.2 อ่าน Workbook เพื่อจับคู่ชื่อชีตกับไฟล์ xml
        $wbXml = $zip->getFromName('xl/workbook.xml');
        $wbRels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $relMap = [];
        if ($wbRels) {
            preg_match_all('/<Relationship[^>]+Id="([^"]+)"[^>]+Target="([^"]+)"/i', $wbRels, $relMatches, PREG_SET_ORDER);
            foreach ($relMatches as $rm) {
                $relMap[$rm[1]] = $rm[2];
            }
        }

        $sheetMatches = [];
        if ($wbXml) {
            preg_match_all('/<sheet[^>]+name="([^"]+)"[^>]+r:id="([^"]+)"/i', $wbXml, $sheetMatches, PREG_SET_ORDER);
        }

        $machineSheetTarget = null;
        $reliveSheetTarget = null;

        foreach ($sheetMatches as $sm) {
            $sName = $sm[1];
            $rId = $sm[2];
            $target = $relMap[$rId] ?? '';
            $fullPath = 'xl/' . (strpos($target, 'worksheets/') === 0 ? $target : 'worksheets/' . basename($target));

            if (mb_strpos($sName, 'ข้อมูลเครื่อง') !== false || mb_strpos($sName, 'Machine') !== false) {
                $machineSheetTarget = $fullPath;
            } elseif (mb_strpos($sName, 'Relive') !== false) {
                // เก็บ Relive sheet ล่าสุด (เช่น Relive A 5)
                $reliveSheetTarget = $fullPath;
            }
        }

        // Fallback หาชีตข้อมูลเครื่องจักร: ชีตที่มีจำนวน row มากที่สุด
        if (!$machineSheetTarget) {
            $bestCount = 0;
            for ($i = 1; $i <= 20; $i++) {
                $path = "xl/worksheets/sheet{$i}.xml";
                $c = $zip->getFromName($path);
                if ($c) {
                    $cnt = substr_count($c, '<row ');
                    if ($cnt > $bestCount) {
                        $bestCount = $cnt;
                        $machineSheetTarget = $path;
                    }
                }
            }
        }

        if (!$machineSheetTarget) {
            $zip->close();
            throw new Exception('ไม่พบหน้าตาราง "ข้อมูลเครื่อง" ในไฟล์ Excel ที่นำเข้า');
        }

        // 1.3 ดึงข้อมูลจากหน้า Relive (ถ้ามี)
        if ($reliveSheetTarget) {
            $reliveXml = $zip->getFromName($reliveSheetTarget);
            if ($reliveXml) {
                $getReliveVal = function($cellRef) use ($reliveXml, $shared) {
                    if (preg_match('/<c r="' . $cellRef . '"([^>]*)>([\s\S]*?)<\/c>/u', $reliveXml, $m)) {
                        $attr = $m[1];
                        $body = $m[2];
                        if (preg_match('/<v>([\s\S]*?)<\/v>/u', $body, $vm)) {
                            $v = trim($vm[1]);
                            if (strpos($attr, 't="s"') !== false && isset($shared[(int)$v])) {
                                return $shared[(int)$v];
                            }
                            return $v;
                        }
                    }
                    return null;
                };

                $reliveData['shift_duration']  = (int)($getReliveVal('F15') ?: $getReliveVal('E15') ?: 720);
                $reliveData['running_minutes'] = (float)($getReliveVal('F16') ?: 0);
                $reliveData['total_loss_min']  = (float)($getReliveVal('H30') ?: $getReliveVal('F17') ?: 0);

                // Loss Categories (Row 18: ผลิต, Row 19: MC, Row 20: วัตถุดิบ, Row 21: อื่นๆ)
                $reliveData['losses'] = [
                    'loss_person'   => ['occ' => (int)($getReliveVal('F18') ?? 0), 'min' => (int)($getReliveVal('H18') ?? 0)],
                    'loss_mc'       => ['occ' => (int)($getReliveVal('F19') ?? 0), 'min' => (int)($getReliveVal('H19') ?? 0)],
                    'loss_material' => ['occ' => (int)($getReliveVal('F20') ?? 0), 'min' => (int)($getReliveVal('H20') ?? 0)],
                    'loss_other'    => ['occ' => (int)($getReliveVal('F21') ?? 0), 'min' => (int)($getReliveVal('H21') ?? 0)],
                ];
            }
        }

        // 1.4 ดึงข้อมูลเครื่องจักรรายออเดอร์ (แถว 2 เป็นต้นไป) จากชีตข้อมูลเครื่อง
        $wsXml = $zip->getFromName($machineSheetTarget);
        $zip->close();

        preg_match_all('/<row r="([0-9]+)"[^>]*>([\s\S]*?)<\/row>/u', $wsXml, $rowMatches, PREG_SET_ORDER);
        if (empty($rowMatches)) {
            throw new Exception('ไม่พบแถวข้อมูลในชีตข้อมูลเครื่อง');
        }

        foreach ($rowMatches as $rm) {
            $rNum = (int)$rm[1];
            if ($rNum === 1) continue; // ข้าม Row 1 (Header)

            $rowBody = $rm[2];
            preg_match_all('/<c r="([A-Z]+)[0-9]+"([^>]*)>([\s\S]*?)<\/c>|<c r="([A-Z]+)[0-9]+"([^>]*)\/>/u', $rowBody, $cMatches, PREG_SET_ORDER);

            $rowCells = [];
            foreach ($cMatches as $cm) {
                $colLetter = !empty($cm[1]) ? $cm[1] : $cm[4];
                $attr = !empty($cm[2]) ? $cm[2] : $cm[5];
                $cBody = !empty($cm[3]) ? $cm[3] : '';

                $val = '';
                if (preg_match('/<v>([\s\S]*?)<\/v>/u', $cBody, $vm)) {
                    $val = trim($vm[1]);
                    if (strpos($attr, 't="s"') !== false && isset($shared[(int)$val])) {
                        $val = $shared[(int)$val];
                    }
                }
                $rowCells[$colLetter] = $val;
            }

            // ข้ามแถวว่าง
            if (empty($rowCells['A']) && empty($rowCells['E'])) {
                continue;
            }

            $seq        = (int)($rowCells['A'] ?? 0);
            $shift      = strtoupper(trim($rowCells['B'] ?? 'B'));
            $planOrder  = trim($rowCells['C'] ?? '');
            $orderNo    = trim($rowCells['D'] ?? '');
            $woNo       = trim($rowCells['E'] ?? '');
            $flute      = strtoupper(trim($rowCells['F'] ?? ''));
            if ($flute === 'CB') $flute = 'BC';

            $wM1 = (float)str_replace(',', '', $rowCells['G'] ?? '0');
            $wM2 = (float)str_replace(',', '', $rowCells['H'] ?? '0');
            $wM3 = (float)str_replace(',', '', $rowCells['I'] ?? '0');
            $wM4 = (float)str_replace(',', '', $rowCells['J'] ?? '0');
            $wM5 = (float)str_replace(',', '', $rowCells['K'] ?? '0');

            $pM1 = trim($rowCells['L'] ?? '');
            $pM2 = trim($rowCells['M'] ?? '');
            $pM3 = trim($rowCells['N'] ?? '');
            $pM4 = trim($rowCells['O'] ?? '');
            $pM5 = trim($rowCells['P'] ?? '');

            $paperWidth = (float)str_replace(',', '', $rowCells['Q'] ?? '0');
            $sheetLen   = (float)str_replace(',', '', $rowCells['R'] ?? '0');
            $sheetWt    = (float)str_replace(',', '', $rowCells['S'] ?? '0');
            $bigGood    = (int)str_replace(',', '', $rowCells['T'] ?? '0');
            $bigWaste   = (int)str_replace(',', '', $rowCells['U'] ?? '0');
            $knifeT     = (int)str_replace(',', '', $rowCells['V'] ?? '0');
            $knifeS     = (float)str_replace(',', '', $rowCells['W'] ?? '0');

            $f1 = (int)str_replace(',', '', $rowCells['X'] ?? '0');
            $f2 = (int)str_replace(',', '', $rowCells['Y'] ?? '0');
            $f3 = (int)str_replace(',', '', $rowCells['Z'] ?? '0');
            $f4 = (int)str_replace(',', '', $rowCells['AA'] ?? '0');
            $f5 = (int)str_replace(',', '', $rowCells['AB'] ?? '0');
            $f6 = (int)str_replace(',', '', $rowCells['AC'] ?? '0');

            $smallTotal = (int)str_replace(',', '', $rowCells['AD'] ?? '0');
            $smallGood  = (int)str_replace(',', '', $rowCells['AE'] ?? '0');

            // เวลาเริ่ม - สิ้นสุด (AF, AG) รองรับทั้ง Excel Serial Number และ String
            $rawStart = trim($rowCells['AF'] ?? '');
            $rawEnd   = trim($rowCells['AG'] ?? '');
            $startTime = null;
            $endTime   = null;

            if (is_numeric($rawStart) && (float)$rawStart > 1000) {
                $ts = round(((float)$rawStart - 25569) * 86400);
                $startTime = date('Y-m-d H:i:s', $ts);
                $dStr = date('Y-m-d', $ts);
                if (!$earliestDate || $dStr < $earliestDate) $earliestDate = $dStr;
            } elseif (!empty($rawStart)) {
                $ts = strtotime($rawStart);
                if ($ts) {
                    $startTime = date('Y-m-d H:i:s', $ts);
                    $dStr = date('Y-m-d', $ts);
                    if (!$earliestDate || $dStr < $earliestDate) $earliestDate = $dStr;
                }
            }

            if (is_numeric($rawEnd) && (float)$rawEnd > 1000) {
                $endTime = date('Y-m-d H:i:s', round(((float)$rawEnd - 25569) * 86400));
            } elseif (!empty($rawEnd)) {
                $ts = strtotime($rawEnd);
                if ($ts) $endTime = date('Y-m-d H:i:s', $ts);
            }

            $prodSec      = (int)str_replace(',', '', $rowCells['AH'] ?? '0');
            $stops        = (int)str_replace(',', '', $rowCells['AI'] ?? '0');
            $stopTimeSec  = (int)str_replace(',', '', $rowCells['AJ'] ?? '0');
            $stackCount   = (int)str_replace(',', '', $rowCells['AK'] ?? '0');
            $avgSpeed     = (float)str_replace(',', '', $rowCells['AL'] ?? '0');
            $totWeight    = (float)str_replace(',', '', $rowCells['AM'] ?? '0');
            $totArea      = (float)str_replace(',', '', $rowCells['AN'] ?? '0');
            $totMeters    = (float)str_replace(',', '', $rowCells['AO'] ?? '0');
            $totVolume    = (float)str_replace(',', '', $rowCells['AP'] ?? '0');

            $trimMm = max(0, $paperWidth - $knifeS);

            // ==============================================================
            // คำนวณสูตรอัตโนมัติตาม Excel (AQ ถึง BX)
            // ==============================================================
            $addOrder        = $bigWaste >= 1 ? 1 : 0;                              // AQ
            $addSmallSheets  = $bigWaste * $knifeT;                                 // AR
            $addWeight       = $addSmallSheets * $sheetWt;                          // AS
            $prodMin         = $prodSec > 0 ? round($prodSec / 60.0, 2) : 0.00;    // AT

            $g1 = BhsEngine::extractGsm($pM1);
            $g2 = BhsEngine::extractGsm($pM2);
            $g3 = BhsEngine::extractGsm($pM3);
            $g4 = BhsEngine::extractGsm($pM4);
            $g5 = BhsEngine::extractGsm($pM5);

            $m1Class = BhsEngine::classifyGsm($g1);                                 // BE
            $m2Class = BhsEngine::classifyGsm($g2);                                 // BF
            $m3Class = BhsEngine::classifyGsm($g3);                                 // BG
            $m4Class = BhsEngine::classifyGsm($g4);                                 // BH
            $m5Class = BhsEngine::classifyGsm($g5);                                 // BI

            $meterBucket     = BhsEngine::getMeterBucket($totMeters);               // BS
            $cutlengBucket   = BhsEngine::getCutlengBucket($sheetLen);              // BT
            $trimSideMm      = BhsEngine::calculateTrimSide($paperWidth, $knifeT, $knifeS); // BU
            $trimBucket      = BhsEngine::getTrimBucket($trimSideMm);               // BV
            $speedBucket     = BhsEngine::getSpeedBucket($avgSpeed);                // BW
            $woPrefix        = strtoupper(substr($woNo, 0, 3));                     // BX

            if (!empty($shift)) {
                $detectedShifts[$shift] = ($detectedShifts[$shift] ?? 0) + 1;
            }

            $parsedRows[] = [
                'seq_no'             => $seq,
                'shift'              => $shift,
                'plan_order'         => $planOrder,
                'order_no'           => $orderNo,
                'wo_no'              => $woNo,
                'wo_prefix'          => $woPrefix,
                'flute'              => $flute,
                'm1_weight'          => $wM1,
                'm2_weight'          => $wM2,
                'm3_weight'          => $wM3,
                'm4_weight'          => $wM4,
                'm5_weight'          => $wM5,
                'm1_paper'           => $pM1,
                'm2_paper'           => $pM2,
                'm3_paper'           => $pM3,
                'm4_paper'           => $pM4,
                'm5_paper'           => $pM5,
                'm1_gsm'             => $g1,
                'm2_gsm'             => $g2,
                'm3_gsm'             => $g3,
                'm4_gsm'             => $g4,
                'm5_gsm'             => $g5,
                'm1_class'           => $m1Class,
                'm2_class'           => $m2Class,
                'm3_class'           => $m3Class,
                'm4_class'           => $m4Class,
                'm5_class'           => $m5Class,
                'paper_width_mm'     => $paperWidth,
                'sheet_length_mm'    => $sheetLen,
                'sheet_weight_kg'    => $sheetWt,
                'good_big_sheets'    => $bigGood,
                'waste_big_sheets'   => $bigWaste,
                'knife_t'            => $knifeT,
                'knife_s'            => $knifeS,
                'trim_mm'            => $trimMm,
                'f1'                 => $f1,
                'f2'                 => $f2,
                'f3'                 => $f3,
                'f4'                 => $f4,
                'f5'                 => $f5,
                'f6'                 => $f6,
                'total_small_sheets' => $smallTotal,
                'good_small_sheets'  => $smallGood,
                'start_time'         => $startTime,
                'end_time'           => $endTime,
                'production_seconds' => $prodSec,
                'stop_count'         => $stops,
                'stop_time_sec'      => $stopTimeSec,
                'stack_count'        => $stackCount,
                'avg_speed'          => $avgSpeed,
                'total_weight_kg'    => $totWeight,
                'total_area_sqm'     => $totArea,
                'total_length_m'     => $totMeters,
                'total_volume_sqm'   => $totVolume,
                'add_order'          => $addOrder,
                'add_small_sheets'   => $addSmallSheets,
                'add_weight'         => $addWeight,
                'production_minutes' => $prodMin,
                'meter_bucket'       => $meterBucket,
                'cutleng_bucket'     => $cutlengBucket,
                'trim_side_mm'       => $trimSideMm,
                'trim_bucket'        => $trimBucket,
                'speed_bucket'       => $speedBucket,
            ];
        }

    } else {
        // ==============================================================
        // โหมด 2: อ่านไฟล์ตาราง HTML / TXT หรือ Code Paste
        // ==============================================================
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($rawHtml, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $rows = $xpath->query('//table/tbody/tr');
        if ($rows->length === 0) {
            $rows = $xpath->query('//table//tr[td]');
        }

        if ($rows->length === 0) {
            throw new Exception('ไม่พบแถวข้อมูล <tr><td> ในเอกสารที่นำเข้า กรุณาตรวจสอบรูปแบบไฟล์ตาราง');
        }

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
                    if (!$earliestDate || $dStr < $earliestDate) $earliestDate = $dStr;
                }
            }

            if (!empty($shift)) {
                $detectedShifts[$shift] = ($detectedShifts[$shift] ?? 0) + 1;
            }

            $woPrefix        = strtoupper(substr($woNo, 0, 3));
            $addOrder        = $bigWaste >= 1 ? 1 : 0;
            $addSmallSheets  = $bigWaste * $knifeT;
            $addWeight       = $addSmallSheets * $sheetWt;
            $prodMin         = $prodSec > 0 ? round($prodSec / 60.0, 2) : 0.00;

            $g1 = BhsEngine::extractGsm($pM1);
            $g2 = BhsEngine::extractGsm($pM2);
            $g3 = BhsEngine::extractGsm($pM3);
            $g4 = BhsEngine::extractGsm($pM4);
            $g5 = BhsEngine::extractGsm($pM5);

            $m1Class = BhsEngine::classifyGsm($g1);
            $m2Class = BhsEngine::classifyGsm($g2);
            $m3Class = BhsEngine::classifyGsm($g3);
            $m4Class = BhsEngine::classifyGsm($g4);
            $m5Class = BhsEngine::classifyGsm($g5);

            $meterBucket     = BhsEngine::getMeterBucket($totMeters);
            $cutlengBucket   = BhsEngine::getCutlengBucket($sheetLen);
            $trimSideMm      = BhsEngine::calculateTrimSide($paperWidth, $knifeT, $knifeS);
            $trimBucket      = BhsEngine::getTrimBucket($trimSideMm);
            $speedBucket     = BhsEngine::getSpeedBucket($avgSpeed);

            $parsedRows[] = [
                'seq_no'             => $seq,
                'shift'              => $shift,
                'plan_order'         => $planOrder,
                'order_no'           => $orderNo,
                'wo_no'              => $woNo,
                'wo_prefix'          => $woPrefix,
                'flute'              => $flute,
                'm1_weight'          => $wM1,
                'm2_weight'          => $wM2,
                'm3_weight'          => $wM3,
                'm4_weight'          => $wM4,
                'm5_weight'          => $wM5,
                'm1_paper'           => $pM1,
                'm2_paper'           => $pM2,
                'm3_paper'           => $pM3,
                'm4_paper'           => $pM4,
                'm5_paper'           => $pM5,
                'm1_gsm'             => $g1,
                'm2_gsm'             => $g2,
                'm3_gsm'             => $g3,
                'm4_gsm'             => $g4,
                'm5_gsm'             => $g5,
                'm1_class'           => $m1Class,
                'm2_class'           => $m2Class,
                'm3_class'           => $m3Class,
                'm4_class'           => $m4Class,
                'm5_class'           => $m5Class,
                'paper_width_mm'     => $paperWidth,
                'sheet_length_mm'    => $sheetLen,
                'sheet_weight_kg'    => $sheetWt,
                'good_big_sheets'    => $bigGood,
                'waste_big_sheets'   => $bigWaste,
                'knife_t'            => $knifeT,
                'knife_s'            => $knifeS,
                'trim_mm'            => $trimMm,
                'f1'                 => $f1,
                'f2'                 => $f2,
                'f3'                 => $f3,
                'f4'                 => $f4,
                'f5'                 => $f5,
                'f6'                 => $f6,
                'total_small_sheets' => $smallTotal,
                'good_small_sheets'  => $smallGood,
                'start_time'         => !empty($startTimeStr) ? date('Y-m-d H:i:s', strtotime($startTimeStr)) : null,
                'end_time'           => !empty($endTimeStr) ? date('Y-m-d H:i:s', strtotime($endTimeStr)) : null,
                'production_seconds' => $prodSec,
                'stop_count'         => $stops,
                'stop_time_sec'      => $stopTimeSec,
                'stack_count'        => $stackCount,
                'avg_speed'          => $avgSpeed,
                'total_weight_kg'    => $totWeight,
                'total_area_sqm'     => $totArea,
                'total_length_m'     => $totMeters,
                'total_volume_sqm'   => $totVolume,
                'add_order'          => $addOrder,
                'add_small_sheets'   => $addSmallSheets,
                'add_weight'         => $addWeight,
                'production_minutes' => $prodMin,
                'meter_bucket'       => $meterBucket,
                'cutleng_bucket'     => $cutlengBucket,
                'trim_side_mm'       => $trimSideMm,
                'trim_bucket'        => $trimBucket,
                'speed_bucket'       => $speedBucket,
            ];
        }
    }

    if (empty($parsedRows)) {
        throw new Exception('ไม่พบรายการข้อมูลการผลิตที่สมบูรณ์ในไฟล์');
    }

    // ตรวจสอบค่าที่ผู้ใช้เลือก (Machine, Shift, Date)
    $validMachines = ['BHS', 'YUELI', 'ISOWA'];
    $userMachine = strtoupper(sanitize_text($_POST['machine_name'] ?? 'BHS'));
    $machineName = in_array($userMachine, $validMachines) ? $userMachine : 'BHS';

    // ตรวจสอบการเลือกกะ (Shift A หรือ B)
    $userShift = strtoupper(sanitize_text($_POST['shift'] ?? ''));
    if (in_array($userShift, ['A', 'B'])) {
        $primaryShift = $userShift;
    } else {
        arsort($detectedShifts);
        $primaryShift = key($detectedShifts) ?: 'B';
    }

    // ตรวจสอบวันที่เดินงาน
    $userDate = sanitize_text($_POST['report_date'] ?? '');
    if (!empty($userDate) && strtotime($userDate)) {
        $reportDate = date('Y-m-d', strtotime($userDate));
    } else {
        $reportDate = $earliestDate ?: date('Y-m-d');
    }

    // เวลาเริ่มเดิน และ เวลาจบ
    $defaultStart = ($primaryShift === 'A') ? '08:00' : '20:00';
    $defaultEnd   = ($primaryShift === 'A') ? '20:00' : '07:40';
    $shiftStartTime = sanitize_text($_POST['shift_start_time'] ?? $defaultStart);
    $shiftEndTime   = sanitize_text($_POST['shift_end_time'] ?? $defaultEnd);

    // คำนวณเวลาเดินทั้งหมดเสมอจาก (เวลาจบ - เวลาเริ่ม) หรือใช้จาก Relive ถ้าตั้งต้นเป็น 720
    $actualTimeTotal = BhsEngine::calculateShiftDurationMinutes($shiftStartTime, $shiftEndTime);
    if (!empty($reliveData['shift_duration']) && $actualTimeTotal === 720) {
        $actualTimeTotal = $reliveData['shift_duration'];
    }

    $title = "รายงานประสิทธิภาพการเดินงาน " . $machineName . " กะ " . $primaryShift;

    // เชื่อมต่อฐานข้อมูล
    $pdo = Database::getConnection();

    // ดึงค่าเป้าหมายและโครงสร้างแผนกจาก Master Template
    $templateRow = null;
    try {
        $stmtTpl = $pdo->prepare("SELECT * FROM bhs_target_templates WHERE machine_name = :machine OR template_name = 'default' ORDER BY id DESC LIMIT 1");
        $stmtTpl->execute([':machine' => $machineName]);
        $templateRow = $stmtTpl->fetch();
    } catch (Exception $e) {}

    $targetOrders   = $templateRow ? (int)$templateRow['target_orders'] : 120;
    $targetMeters   = $templateRow ? (float)$templateRow['target_meters'] : 68056.00;
    $targetSpeed    = $templateRow ? (float)$templateRow['target_speed'] : 100.00;
    $targetAvgMeters= $templateRow ? (float)$templateRow['target_avg_meters'] : 500.00;
    $targetTimeTotal= $templateRow ? (int)$templateRow['target_time_total'] : 720;
    $targetTimeRun  = $templateRow ? (int)$templateRow['target_time_run'] : 675;
    $targetLossPct  = $templateRow ? (float)$templateRow['target_loss_pct'] : 10.00;

    // Loss Details เริ่มต้นตาม Master Template
    $defaultLoss = [];
    if (!empty($templateRow['loss_departments'])) {
        $parsedDepts = json_decode($templateRow['loss_departments'], true);
        if (is_array($parsedDepts)) {
            foreach ($parsedDepts as $d) {
                if (is_array($d) && !empty($d['name'])) {
                    if (isset($d['is_active']) && !$d['is_active']) continue;
                    $defaultLoss[$d['name']] = [
                        'target_pct'  => (float)($d['target_pct'] ?? 0.0),
                        'occurrences' => 0,
                        'minutes'     => 0,
                        'score'       => 3
                    ];
                }
            }
        }
    }
    if (empty($defaultLoss)) {
        $defaultLoss = BhsEngine::getDefaultLossDepartments();
    }

    // ===== รับข้อมูล Optional จาก upload form หรือใช้ค่า Auto จากหน้า Relive =====
    $uploadOrderStart   = sanitize_text($_POST['order_start']     ?? '');
    $uploadOrderFinit   = sanitize_text($_POST['order_finit']     ?? '');
    $uploadShortage     = max(0, (int)($_POST['shortage_orders'] ?? 0));

    // Fallback Auto-fill จาก Relive sheet
    if (empty($uploadOrderStart) && !empty($reliveData['order_start'])) {
        $uploadOrderStart = (string)$reliveData['order_start'];
    }
    if (empty($uploadOrderFinit) && !empty($reliveData['order_finit'])) {
        $uploadOrderFinit = (string)$reliveData['order_finit'];
    }

    // แมปเวลาสูญเสีย 4 ประเภท (ผลิต, เครื่องจักร, วัตถุดิบ, อื่นๆ)
    $lossMapping = [
        'loss_person'   => ['คน', 'ผลิต', 'person', 'people'],
        'loss_mc'       => ['เครื่องจักร', 'MC', 'machine', 'เครื่อง'],
        'loss_material' => ['วัตถุดิบ', 'ม้วน', 'material', 'คลัง'],
        'loss_other'    => ['อื่นๆ', 'other', 'อื่น'],
    ];

    foreach ($lossMapping as $key => $keywords) {
        // เช็คว่าผู้ใช้กรอกในฟอร์มหรือไม่
        $occ = isset($_POST[$key . '_occ']) && $_POST[$key . '_occ'] !== '' ? (int)$_POST[$key . '_occ'] : null;
        $min = isset($_POST[$key . '_min']) && $_POST[$key . '_min'] !== '' ? (int)$_POST[$key . '_min'] : null;

        // หากผู้ใช้ไม่ได้กรอก และใน Relive มีค่า ให้ดึงจาก Relive อัตโนมัติ
        if (($occ === null || $occ === 0) && ($min === null || $min === 0)) {
            if (isset($reliveData['losses'][$key])) {
                $occ = $reliveData['losses'][$key]['occ'];
                $min = $reliveData['losses'][$key]['min'];
            }
        }

        $occ = max(0, (int)$occ);
        $min = max(0, (int)$min);
        if ($occ <= 0 && $min <= 0) continue;

        // หาชื่อแผนกที่ตรงใน $defaultLoss
        $matched = null;
        foreach (array_keys($defaultLoss) as $deptName) {
            foreach ($keywords as $kw) {
                if (mb_stripos($deptName, $kw) !== false) {
                    $matched = $deptName;
                    break 2;
                }
            }
        }
        if ($matched) {
            $defaultLoss[$matched]['occurrences'] += $occ;
            $defaultLoss[$matched]['minutes']     += $min;
            if ($min > 0) $defaultLoss[$matched]['score'] = 1;
        }
    }

    // Station Waste KG จาก upload form
    $stationsUpload = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];
    $stationWasteUpload = [];
    $stationWastePost   = $_POST['station_waste_upload'] ?? [];
    foreach ($stationsUpload as $st) {
        $kgVal = max(0.0, (float)($stationWastePost[$st] ?? 0));
        $stationWasteUpload[$st] = ['kg' => $kgVal];
    }
    $jsonStationWasteUpload = json_encode($stationWasteUpload, JSON_UNESCAPED_UNICODE);

    // บันทึกลงฐานข้อมูลแบบ Transaction
    $pdo->beginTransaction();

    $stmtReport = $pdo->prepare("
        INSERT INTO bhs_shift_reports (
            title, machine_name, shift, report_date, shift_start_time, shift_end_time, actual_time_total,
            order_start, order_finit, shortage_orders, station_waste,
            target_orders, target_meters, target_speed, target_avg_meters, target_time_total, target_time_run,
            target_loss_pct, loss_details, summary_notes, remarks, raw_filename, created_by
        ) VALUES (
            :title, :machine_name, :shift, :report_date, :shift_start_time, :shift_end_time, :actual_time_total,
            :order_start, :order_finit, :shortage_orders, :station_waste,
            :target_orders, :target_meters, :target_speed, :target_avg_meters, :target_time_total, :target_time_run,
            :target_loss_pct, :loss_details, 'ผลการเดินงานโดยรวมปกติ เดินตามแผนงาน', 'เครื่องจักรทำงานเรียบร้อย', :raw_filename, :created_by
        )
    ");

    $userId = current_user()['id'] ?? 1;
    $stmtReport->execute([
        ':title'            => $title,
        ':machine_name'     => $machineName,
        ':shift'            => $primaryShift,
        ':report_date'      => $reportDate,
        ':shift_start_time' => $shiftStartTime,
        ':shift_end_time'   => $shiftEndTime,
        ':actual_time_total'=> $actualTimeTotal,
        ':order_start'      => !empty($uploadOrderStart) ? (int)$uploadOrderStart : 0,
        ':order_finit'      => !empty($uploadOrderFinit) ? (int)$uploadOrderFinit : 0,
        ':shortage_orders'  => $uploadShortage,
        ':station_waste'    => $jsonStationWasteUpload,
        ':target_orders'    => $targetOrders,
        ':target_meters'    => $targetMeters,
        ':target_speed'     => $targetSpeed,
        ':target_avg_meters'=> $targetAvgMeters,
        ':target_time_total'=> $targetTimeTotal,
        ':target_time_run'  => $targetTimeRun,
        ':target_loss_pct'  => $targetLossPct,
        ':loss_details'     => json_encode($defaultLoss, JSON_UNESCAPED_UNICODE),
        ':raw_filename'     => $uploadedFilename,
        ':created_by'       => $userId
    ]);

    $reportId = (int)$pdo->lastInsertId();

    // Insert รายการ production records ทั้งหมด (ครอบคลุมครบ 76 คอลัมน์ A ถึง BX)
    $sqlRecord = "
        INSERT INTO bhs_production_records (
            report_id, seq_no, shift, plan_order, order_no, wo_no, wo_prefix, flute,
            m1_weight, m2_weight, m3_weight, m4_weight, m5_weight,
            m1_paper, m2_paper, m3_paper, m4_paper, m5_paper,
            m1_gsm, m2_gsm, m3_gsm, m4_gsm, m5_gsm,
            m1_class, m2_class, m3_class, m4_class, m5_class,
            paper_width_mm, sheet_length_mm, sheet_weight_kg,
            good_big_sheets, waste_big_sheets, knife_t, knife_s, trim_mm,
            f1, f2, f3, f4, f5, f6,
            total_small_sheets, good_small_sheets, start_time, end_time,
            production_seconds, stop_count, stop_time_sec, stack_count,
            avg_speed, total_weight_kg, total_area_sqm, total_length_m, total_volume_sqm,
            add_order, add_small_sheets, add_weight, production_minutes,
            meter_bucket, cutleng_bucket, trim_side_mm, trim_bucket, speed_bucket
        ) VALUES (
            :report_id, :seq_no, :shift, :plan_order, :order_no, :wo_no, :wo_prefix, :flute,
            :m1_weight, :m2_weight, :m3_weight, :m4_weight, :m5_weight,
            :m1_paper, :m2_paper, :m3_paper, :m4_paper, :m5_paper,
            :m1_gsm, :m2_gsm, :m3_gsm, :m4_gsm, :m5_gsm,
            :m1_class, :m2_class, :m3_class, :m4_class, :m5_class,
            :paper_width_mm, :sheet_length_mm, :sheet_weight_kg,
            :good_big_sheets, :waste_big_sheets, :knife_t, :knife_s, :trim_mm,
            :f1, :f2, :f3, :f4, :f5, :f6,
            :total_small_sheets, :good_small_sheets, :start_time, :end_time,
            :production_seconds, :stop_count, :stop_time_sec, :stack_count,
            :avg_speed, :total_weight_kg, :total_area_sqm, :total_length_m, :total_volume_sqm,
            :add_order, :add_small_sheets, :add_weight, :production_minutes,
            :meter_bucket, :cutleng_bucket, :trim_side_mm, :trim_bucket, :speed_bucket
        )
    ";

    $stmtRec = $pdo->prepare($sqlRecord);

    foreach ($parsedRows as $row) {
        $row['report_id'] = $reportId;
        $stmtRec->execute($row);
    }

    $pdo->commit();

    // Auto Sync ไปยัง Google Sheets หากเปิดใช้งาน
    require_once __DIR__ . '/../includes/google_sheet.php';
    if (is_google_sheet_configured() && defined('GOOGLE_SHEET_AUTO_SYNC') && GOOGLE_SHEET_AUTO_SYNC) {
        sync_report_to_google_sheet($reportId);
    }

    $successMsg = "นำเข้าข้อมูลเครื่องจักรเรียบร้อยแล้ว จำนวน " . count($parsedRows) . " รายการ (กะ $primaryShift)";

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response([
            'success'   => true,
            'message'   => $successMsg,
            'report_id' => $reportId,
            'redirect'  => BASE_URL . "/views/report_view.php?id=" . $reportId
        ]);
    }

    set_flash('success', $successMsg);
    redirect("views/report_view.php?id=" . $reportId);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errMsg = "เกิดข้อผิดพลาดในการประมวลผล: " . $e->getMessage();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['success' => false, 'message' => $errMsg], 500);
    }
    set_flash('danger', $errMsg);
    redirect('views/upload.php');
}
