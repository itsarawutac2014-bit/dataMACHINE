<?php
/**
 * BHS Calculation & Parsing Engine
 * คำนวณสรุปผลตามมาตรฐานเครื่องจักรรีดลอนลูกฟูก BHS
 */

class BhsEngine {

    /**
     * ดึงค่าตัวเลข GSM จากชื่อเกรดกระดาษ เช่น KL125 -> 125, CM105 -> 105, KA230 -> 230
     */
    public static function extractGsm(?string $paperCode): int {
        if (!$paperCode) return 0;
        if (preg_match('/(\d{3})/', $paperCode, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }

    /**
     * คำนวณเวลาเดินทั้งหมดเป็นนาที ระหว่างเวลาเริ่มเดินและเวลาจบ (รองรับข้ามคืน เช่น 20:00 ถึง 08:00 หรือ 20:00 ถึง 07:40)
     */
    public static function calculateShiftDurationMinutes(?string $start, ?string $end): int {
        if (empty($start) || empty($end)) return 720;
        $s = str_replace('.', ':', trim($start));
        $e = str_replace('.', ':', trim($end));
        $tStart = strtotime("2026-01-01 $s");
        $tEnd   = strtotime("2026-01-01 $e");
        if ($tStart === false || $tEnd === false) return 720;
        if ($tEnd <= $tStart) {
            // เดินงานข้ามคืน (Overnight)
            $tEnd = strtotime("2026-01-02 $e");
        }
        $diffMin = (int)(($tEnd - $tStart) / 60);
        return max(1, $diffMin);
    }

    /**
     * ดึง Standard GSM Buckets
     */
    public static function getGsmBuckets(): array {
        return [100, 105, 125, 140, 150, 170, 175, 185, 200, 205, 230, 250, 270];
    }

    /**
     * ดึง Standard Flutes
     */
    public static function getStandardFlutes(): array {
        return ['ลอน B' => 'B', 'ลอน C' => 'C', 'ลอน BC' => 'BC', 'ลอน A' => 'A', 'ลอน AB' => 'AB', 'ลอน E' => 'E'];
    }

    /**
     * ดึง Standard Meter Ranges
     */
    public static function getMeterRanges(): array {
        return [
            ['label' => '200 m',   'min' => 0,    'max' => 200],
            ['label' => '300 m',   'min' => 201,  'max' => 300],
            ['label' => '400 m',   'min' => 301,  'max' => 400],
            ['label' => '500 m',   'min' => 401,  'max' => 500],
            ['label' => '700 m',   'min' => 501,  'max' => 700],
            ['label' => '1000 m',  'min' => 701,  'max' => 1000],
            ['label' => '2500 m',  'min' => 1001, 'max' => 2500],
            ['label' => '3000 m',  'min' => 2501, 'max' => 3000],
            ['label' => '5000 m',  'min' => 3001, 'max' => 5000],
            ['label' => '10000 m', 'min' => 5001, 'max' => 10000],
            ['label' => '20000 m', 'min' => 10001,'max' => 20000],
        ];
    }

    /**
     * ดึงรายการ Trim ยอดนิยม
     */
    public static function getTrimBuckets(): array {
        return [13, 15, 20, 25, 30, 40, 50, 60, 70, 75, 85];
    }

    /**
     * รายการแผนกและค่าตั้งต้นของเวลาสูญเสีย (Default Loss Time Department Structure)
     */
    public static function getDefaultLossDepartments(): array {
        return [
            'ผลิต'       => ['target_pct' => 0.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'MC'         => ['target_pct' => 3.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'ไฟฟ้า'      => ['target_pct' => 1.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'คลังม้วน'    => ['target_pct' => 0.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'ม้วน'       => ['target_pct' => 0.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'Utiliy'     => ['target_pct' => 0.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'Packking'   => ['target_pct' => 1.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'กาว'        => ['target_pct' => 0.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'วางแผน'     => ['target_pct' => 1.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'จัดส่ง'      => ['target_pct' => 0.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'เปลี่ยนลอน'   => ['target_pct' => 2.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3],
            'PM'         => ['target_pct' => 2.00, 'occurrences' => 0, 'minutes' => 0, 'score' => 3]
        ];
    }

    /**
     * คำนวณสรุปผลทั้งหมดจากแถวข้อมูลการผลิต (Production Records Array)
     */
    public static function computeReportSummary(array $records, array $options = []): array {
        $filterShift = $options['shift'] ?? null;
        $filterFlute = $options['flute'] ?? null;
        $filterWo    = $options['wo_prefix'] ?? null;

        $filtered = [];
        foreach ($records as $r) {
            if ($filterShift && $r['shift'] !== $filterShift) continue;
            if ($filterFlute && $r['flute'] !== $filterFlute) continue;
            if ($filterWo && $r['wo_prefix'] !== $filterWo) continue;
            $filtered[] = $r;
        }

        $totalOrders = count($filtered);
        $totalMeters = 0.0;
        $totalWeight = 0.0;
        $totalProductionSec = 0;
        $totalStops = 0;
        $totalStopTimeSec = 0;
        $weightedSpeedSum = 0.0;

        // โครงสร้าง GSM Meters และ GSM Weights
        $gsmBuckets = self::getGsmBuckets();
        $gsmMeters = [];
        $gsmWeights = [];
        foreach ($gsmBuckets as $g) {
            $gsmMeters[$g] = ['M1' => 0.0, 'M2' => 0.0, 'M3' => 0.0, 'M4' => 0.0, 'M5' => 0.0, 'total' => 0.0];
            $gsmWeights[$g] = ['M1' => 0.0, 'M2' => 0.0, 'M3' => 0.0, 'M4' => 0.0, 'M5' => 0.0, 'total' => 0.0];
        }

        // โครงสร้างแยกตามลอน
        $fluteSummary = [
            'B'  => ['orders' => 0, 'meters' => 0.0, 'seconds' => 0, 'weight' => 0.0],
            'C'  => ['orders' => 0, 'meters' => 0.0, 'seconds' => 0, 'weight' => 0.0],
            'BC' => ['orders' => 0, 'meters' => 0.0, 'seconds' => 0, 'weight' => 0.0],
            'A'  => ['orders' => 0, 'meters' => 0.0, 'seconds' => 0, 'weight' => 0.0],
            'AB' => ['orders' => 0, 'meters' => 0.0, 'seconds' => 0, 'weight' => 0.0],
            'E'  => ['orders' => 0, 'meters' => 0.0, 'seconds' => 0, 'weight' => 0.0],
        ];

        // ช่วงระยะเมตร — ใช้ meter_bucket (BS) จาก DB โดยตรง
        $meterBucketMap = [
            1 => '200 m',   2 => '300 m',   3 => '400 m',  4 => '500 m',
            5 => '700 m',   6 => '1000 m',  7 => '2500 m', 8 => '3000 m',
            9 => '5000 m', 10 => '10000 m', 11 => '20000 m'
        ];
        $meterDist = [];
        foreach ($meterBucketMap as $label) {
            $meterDist[$label] = ['orders' => 0, 'meters' => 0.0];
        }

        // Trim Distribution — ใช้ trim_bucket (BV) จาก DB โดยตรง
        $trimBucketMap = [
            1 => 13,  2 => 15,  3 => 20,  4 => 25,  5 => 30,
            6 => 40,  7 => 50,  8 => 60,  9 => 70, 10 => 75, 11 => 85
        ];
        $trimDist = [];
        foreach ($trimBucketMap as $mm) {
            $trimDist[$mm] = ['orders' => 0, 'meters' => 0.0];
        }
        $trimDist['อื่นๆ'] = ['orders' => 0, 'meters' => 0.0];

        // Wo Types
        $woList = ['PDR', 'PDQ', 'PDF', 'PDW', 'PDL', 'PDC', 'PDZ', 'PDS', 'PDE'];
        $woSummary = [];
        foreach ($woList as $w) {
            $woSummary[$w] = 0.0;
        }

        // Flute add tracking (add_order, add_small_sheets, add_weight แยกตามลอน)
        $fluteAdd = [
            'B'  => ['add_orders' => 0, 'add_small' => 0, 'add_weight' => 0.0],
            'C'  => ['add_orders' => 0, 'add_small' => 0, 'add_weight' => 0.0],
            'BC' => ['add_orders' => 0, 'add_small' => 0, 'add_weight' => 0.0],
            'A'  => ['add_orders' => 0, 'add_small' => 0, 'add_weight' => 0.0],
            'AB' => ['add_orders' => 0, 'add_small' => 0, 'add_weight' => 0.0],
            'E'  => ['add_orders' => 0, 'add_small' => 0, 'add_weight' => 0.0],
        ];

        foreach ($filtered as $r) {
            $meters = (float)$r['total_length_m'];
            $weight = (float)$r['total_weight_kg'];
            $sec    = (int)$r['production_seconds'];
            $speed  = (float)$r['avg_speed'];
            $stops  = (int)$r['stop_count'];
            $stopSec= (int)$r['stop_time_sec'];
            $widthM = (float)$r['paper_width_mm'] / 1000.0;

            $totalMeters += $meters;
            $totalWeight += $weight;
            $totalProductionSec += $sec;
            $totalStops += $stops;
            $totalStopTimeSec += $stopSec;
            $weightedSpeedSum += ($speed * $meters);

            // Flute mapping
            $fKey = strtoupper(trim($r['flute']));
            if ($fKey === 'CB') $fKey = 'BC';
            if (isset($fluteSummary[$fKey])) {
                $fluteSummary[$fKey]['orders']++;
                $fluteSummary[$fKey]['meters']  += $meters;
                $fluteSummary[$fKey]['seconds'] += $sec;
                $fluteSummary[$fKey]['weight']  += $weight;
            }

            // Flute add tracking
            if (isset($fluteAdd[$fKey])) {
                $fluteAdd[$fKey]['add_orders'] += (int)($r['add_order'] ?? 0);
                $fluteAdd[$fKey]['add_small']  += (int)($r['add_small_sheets'] ?? 0);
                $fluteAdd[$fKey]['add_weight'] += (float)($r['add_weight'] ?? 0);
            }

            // GSM calculations (M1 - M5)
            $stands = [
                ['pos' => 'M1', 'w' => (float)$r['m1_weight'], 'gsm' => (int)$r['m1_gsm']],
                ['pos' => 'M2', 'w' => (float)$r['m2_weight'], 'gsm' => (int)$r['m2_gsm']],
                ['pos' => 'M3', 'w' => (float)$r['m3_weight'], 'gsm' => (int)$r['m3_gsm']],
                ['pos' => 'M4', 'w' => (float)$r['m4_weight'], 'gsm' => (int)$r['m4_gsm']],
                ['pos' => 'M5', 'w' => (float)$r['m5_weight'], 'gsm' => (int)$r['m5_gsm']],
            ];

            foreach ($stands as $st) {
                $g = $st['gsm'];
                $w = $st['w'];
                $p = $st['pos'];
                if ($g > 0 && isset($gsmWeights[$g])) {
                    $gsmWeights[$g][$p] += $w;
                    $gsmWeights[$g]['total'] += $w;

                    // คำนวณเมตรกระดาษ: length = (weight_kg * 1000) / (width_m * gsm)
                    if ($widthM > 0) {
                        $pMeters = ($w * 1000.0) / ($widthM * $g);
                        $gsmMeters[$g][$p] += $pMeters;
                        $gsmMeters[$g]['total'] += $pMeters;
                    }
                }
            }

            // Meter Distribution — ใช้ meter_bucket (BS) จาก DB โดยตรง
            $bsMeter = (int)($r['meter_bucket'] ?? 0);
            $meterLabel = $meterBucketMap[$bsMeter] ?? null;
            if ($meterLabel !== null) {
                $meterDist[$meterLabel]['orders']++;
                $meterDist[$meterLabel]['meters'] += $meters;
            }

            // Trim Distribution — ใช้ trim_bucket (BV) จาก DB โดยตรง
            $bvTrim = (int)($r['trim_bucket'] ?? 0);
            $trimMm = $trimBucketMap[$bvTrim] ?? null;
            if ($trimMm !== null) {
                $trimDist[$trimMm]['orders']++;
                $trimDist[$trimMm]['meters'] += $meters;
            } else {
                $trimDist['อื่นๆ']['orders']++;
                $trimDist['อื่นๆ']['meters'] += $meters;
            }

            // Wo Type — ใช้ wo_prefix (BX) จาก DB โดยตรง
            $prefix = strtoupper(trim($r['wo_prefix'] ?? '') ?: substr($r['wo_no'] ?? '', 0, 3));
            if (!isset($woSummary[$prefix])) {
                $woSummary[$prefix] = 0.0;
            }
            $woSummary[$prefix] += $meters;
        }

        // ผลรวม GSM Meters รวมทุกช่อง
        $grandTotalGsmMeters = 0.0;
        foreach ($gsmMeters as $g => $vals) {
            $grandTotalGsmMeters += $vals['total'];
        }

        // ผลรวม GSM Weights รวมทุกช่อง
        $grandTotalGsmWeights = 0.0;
        foreach ($gsmWeights as $g => $vals) {
            $grandTotalGsmWeights += $vals['total'];
        }

        // จำแนก แกรมบาง / แกรมกลาง / แกรมหนา
        $gramClass = [
            'แกรมบาง'  => ['meters' => 0.0, 'weight' => 0.0], // <= 125 gsm
            'แกรมกลาง' => ['meters' => 0.0, 'weight' => 0.0], // 140 - 185 gsm
            'แกรมหนา'  => ['meters' => 0.0, 'weight' => 0.0], // >= 200 gsm
        ];

        foreach ($gsmBuckets as $g) {
            $mVal = $gsmMeters[$g]['total'];
            $wVal = $gsmWeights[$g]['total'];
            if ($g <= 125) {
                $gramClass['แกรมบาง']['meters'] += $mVal;
                $gramClass['แกรมบาง']['weight'] += $wVal;
            } elseif ($g <= 185) {
                $gramClass['แกรมกลาง']['meters'] += $mVal;
                $gramClass['แกรมกลาง']['weight'] += $wVal;
            } else {
                $gramClass['แกรมหนา']['meters'] += $mVal;
                $gramClass['แกรมหนา']['weight'] += $wVal;
            }
        }

        $avgMetersPerOrder = ($totalOrders > 0) ? ($totalMeters / $totalOrders) : 0;
        $avgSpeedGross = ($totalMeters > 0) ? ($weightedSpeedSum / $totalMeters) : 0;
        $runningMinutes = $totalProductionSec / 60.0;
        $lossMinutes = $totalStopTimeSec / 60.0;
        $avgSpeedNet = ($runningMinutes > 0) ? ($totalMeters / $runningMinutes) : 0;

        return [
            'total_orders'          => $totalOrders,
            'total_meters'          => round($totalMeters, 2),
            'total_weight'          => round($totalWeight, 2),
            'avg_meters_per_order'  => round($avgMetersPerOrder, 1),
            'avg_speed_gross'       => round($avgSpeedGross, 1),
            'avg_speed_net'         => round($avgSpeedNet, 1),
            'running_minutes'       => round($runningMinutes, 1),
            'loss_minutes'          => round($lossMinutes, 1),
            'total_stops'           => $totalStops,
            'gsm_meters'            => $gsmMeters,
            'grand_total_gsm_meters'=> round($grandTotalGsmMeters, 1),
            'gsm_weights'           => $gsmWeights,
            'grand_total_gsm_weights'=> round($grandTotalGsmWeights, 1),
            'flute_summary'         => $fluteSummary,
            'flute_add'             => $fluteAdd,
            'meter_dist'            => $meterDist,
            'trim_dist'             => $trimDist,
            'wo_summary'            => $woSummary,
            'gram_class'            => $gramClass,
            'filtered_count'        => count($filtered)
        ];
    }

    /**
     * GSM Classification: <=150 light, <=185 Medium, >185 Heavy
     */
    public static function classifyGsm(int $gsm): string {
        if ($gsm <= 0) return '';
        if ($gsm < 151) return 'light';
        if ($gsm < 186) return 'Medium';
        return 'Heavy';
    }

    /**
     * Meter Bucket (1 - 11) ตามสูตร Excel ข้อมูลเครื่อง ช่อง BS
     */
    public static function getMeterBucket(float $meters): int {
        if ($meters < 200) return 1;
        if ($meters < 300) return 2;
        if ($meters < 400) return 3;
        if ($meters < 500) return 4;
        if ($meters < 700) return 5;
        if ($meters < 1000) return 6;
        if ($meters < 2500) return 7;
        if ($meters < 5000) return 8;
        if ($meters < 10000) return 9;
        if ($meters < 15000) return 10;
        return 11;
    }

    /**
     * Cutleng Bucket (1 - 11) ตามสูตร Excel ข้อมูลเครื่อง ช่อง BT
     */
    public static function getCutlengBucket(float $lengthMm): int {
        if ($lengthMm < 600) return 1;
        if ($lengthMm < 700) return 2;
        if ($lengthMm < 800) return 3;
        if ($lengthMm < 900) return 4;
        if ($lengthMm < 1000) return 5;
        if ($lengthMm < 1300) return 6;
        if ($lengthMm < 1500) return 7;
        if ($lengthMm < 2000) return 8;
        if ($lengthMm < 2500) return 9;
        if ($lengthMm < 3000) return 10;
        return 11;
    }

    /**
     * Trim/ข้าง mm = (PaperWidth - (KnifeT * KnifeS)) / 2 ตามสูตร Excel ช่อง BU
     */
    public static function calculateTrimSide(float $paperWidth, int $knifeT, float $knifeS): float {
        return round(($paperWidth - ($knifeT * $knifeS)) / 2.0, 1);
    }

    /**
     * Trim Bucket (1 - 11) ตามสูตร Excel ข้อมูลเครื่อง ช่อง BV
     */
    public static function getTrimBucket(float $trimSideMm): int {
        if ($trimSideMm < 13) return 1;
        if ($trimSideMm < 15) return 2;
        if ($trimSideMm < 20) return 3;
        if ($trimSideMm < 25) return 4;
        if ($trimSideMm < 30) return 5;
        if ($trimSideMm < 40) return 6;
        if ($trimSideMm < 50) return 7;
        if ($trimSideMm < 60) return 8;
        if ($trimSideMm < 70) return 9;
        if ($trimSideMm < 85) return 10;
        return 11;
    }

    /**
     * Speed Bucket (1 - 11) ตามสูตร Excel ข้อมูลเครื่อง ช่อง BW
     */
    public static function getSpeedBucket(float $speed): int {
        if ($speed < 60) return 1;
        if ($speed < 70) return 2;
        if ($speed < 80) return 3;
        if ($speed < 90) return 4;
        if ($speed < 100) return 5;
        if ($speed < 110) return 6;
        if ($speed < 120) return 7;
        if ($speed < 130) return 8;
        if ($speed < 140) return 9;
        if ($speed < 150) return 10;
        return 11;
    }
}

// Global helper wrappers for backward compatibility and view usage
if (!function_exists('bhsGsmClass')) {
    function bhsGsmClass($gsm) { return BhsEngine::classifyGsm((int)$gsm); }
}
if (!function_exists('bhsMeterBucket')) {
    function bhsMeterBucket($m) { return BhsEngine::getMeterBucket((float)$m); }
}
if (!function_exists('bhsCutlengBucket')) {
    function bhsCutlengBucket($l) { return BhsEngine::getCutlengBucket((float)$l); }
}
if (!function_exists('bhsTrimSide')) {
    function bhsTrimSide($w, $t, $s) { return BhsEngine::calculateTrimSide((float)$w, (int)$t, (float)$s); }
}
if (!function_exists('bhsTrimBucket')) {
    function bhsTrimBucket($t) { return BhsEngine::getTrimBucket((float)$t); }
}
if (!function_exists('bhsSpeedBucket')) {
    function bhsSpeedBucket($s) { return BhsEngine::getSpeedBucket((float)$s); }
}

