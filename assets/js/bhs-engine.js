/**
 * BHS Performance System - GitHub Pages Cloud & Browser Database Engine
 * 100% Compatible with PHP BhsEngine (includes/helpers.php & actions/upload_action.php)
 */

const GOOGLE_SHEET_DEFAULT_WEBHOOK = 'https://script.google.com/macros/s/AKfycbyxFU9DOGJeeS70p0B21mxZ3DlYhQXw0d_jqDZ2McsasvLqKAx38ifYOhVQDD2xHqr1/exec';

class BhsEngine {
    static extractGsm(paperCode) {
        if (!paperCode) return 0;
        const m = String(paperCode).match(/(\d{3})/);
        return m ? parseInt(m[1], 10) : 0;
    }

    static calculateShiftDurationMinutes(start, end) {
        if (!start || !end) return 720;
        const s = String(start).replace('.', ':').trim();
        const e = String(end).replace('.', ':').trim();
        const pS = s.split(':').map(Number);
        const pE = e.split(':').map(Number);
        if (pS.length < 2 || pE.length < 2 || isNaN(pS[0]) || isNaN(pE[0])) return 720;
        let startMin = pS[0] * 60 + pS[1];
        let endMin = pE[0] * 60 + pE[1];
        if (endMin <= startMin) {
            endMin += 24 * 60;
        }
        return Math.max(1, endMin - startMin);
    }

    static getGsmBuckets() {
        return [100, 105, 125, 140, 150, 170, 175, 185, 200, 205, 230, 250, 270];
    }

    static getDefaultLossDepartments() {
        return {
            'ผลิต':       { target_pct: 0.00, occurrences: 0, minutes: 0, score: 3 },
            'MC':         { target_pct: 3.00, occurrences: 0, minutes: 0, score: 3 },
            'ไฟฟ้า':      { target_pct: 1.00, occurrences: 0, minutes: 0, score: 3 },
            'คลังม้วน':    { target_pct: 0.00, occurrences: 0, minutes: 0, score: 3 },
            'ม้วน':       { target_pct: 0.00, occurrences: 0, minutes: 0, score: 3 },
            'Utiliy':     { target_pct: 0.00, occurrences: 0, minutes: 0, score: 3 },
            'Packking':   { target_pct: 1.00, occurrences: 0, minutes: 0, score: 3 },
            'กาว':        { target_pct: 0.00, occurrences: 0, minutes: 0, score: 3 },
            'วางแผน':     { target_pct: 1.00, occurrences: 0, minutes: 0, score: 3 },
            'จัดส่ง':      { target_pct: 0.00, occurrences: 0, minutes: 0, score: 3 },
            'เปลี่ยนลอน':   { target_pct: 2.00, occurrences: 0, minutes: 0, score: 3 },
            'PM':         { target_pct: 2.00, occurrences: 0, minutes: 0, score: 3 }
        };
    }

    static getDefaultLossDepartmentsArray() {
        return [
            { name: 'ผลิต', target_pct: 0.00, is_active: 1 },
            { name: 'MC', target_pct: 3.00, is_active: 1 },
            { name: 'ไฟฟ้า', target_pct: 1.00, is_active: 1 },
            { name: 'คลังม้วน', target_pct: 0.00, is_active: 1 },
            { name: 'ม้วน', target_pct: 0.00, is_active: 1 },
            { name: 'Utiliy', target_pct: 0.00, is_active: 1 },
            { name: 'Packking', target_pct: 1.00, is_active: 1 },
            { name: 'กาว', target_pct: 0.00, is_active: 1 },
            { name: 'วางแผน', target_pct: 1.00, is_active: 1 },
            { name: 'จัดส่ง', target_pct: 0.00, is_active: 1 },
            { name: 'เปลี่ยนลอน', target_pct: 2.00, is_active: 1 },
            { name: 'PM', target_pct: 2.00, is_active: 1 }
        ];
    }

    static classifyGsm(gsm) {
        gsm = Number(gsm) || 0;
        if (gsm <= 0) return '';
        if (gsm < 151) return 'light';
        if (gsm < 186) return 'Medium';
        return 'Heavy';
    }

    static getMeterBucket(meters) {
        meters = Number(meters) || 0;
        if (meters < 200) return 1;
        if (meters < 300) return 2;
        if (meters < 400) return 3;
        if (meters < 500) return 4;
        if (meters < 700) return 5;
        if (meters < 1000) return 6;
        if (meters < 2500) return 7;
        if (meters < 5000) return 8;
        if (meters < 10000) return 9;
        if (meters < 15000) return 10;
        return 11;
    }

    static getCutlengBucket(lengthMm) {
        lengthMm = Number(lengthMm) || 0;
        if (lengthMm < 600) return 1;
        if (lengthMm < 700) return 2;
        if (lengthMm < 800) return 3;
        if (lengthMm < 900) return 4;
        if (lengthMm < 1000) return 5;
        if (lengthMm < 1300) return 6;
        if (lengthMm < 1500) return 7;
        if (lengthMm < 2000) return 8;
        if (lengthMm < 2500) return 9;
        if (lengthMm < 3000) return 10;
        return 11;
    }

    static calculateTrimSide(paperWidth, knifeT, knifeS) {
        return Math.round(((Number(paperWidth) - (Number(knifeT) * Number(knifeS))) / 2.0) * 10) / 10;
    }

    static getTrimBucket(trimSideMm) {
        trimSideMm = Number(trimSideMm) || 0;
        if (trimSideMm < 13) return 1;
        if (trimSideMm < 15) return 2;
        if (trimSideMm < 20) return 3;
        if (trimSideMm < 25) return 4;
        if (trimSideMm < 30) return 5;
        if (trimSideMm < 40) return 6;
        if (trimSideMm < 50) return 7;
        if (trimSideMm < 60) return 8;
        if (trimSideMm < 70) return 9;
        if (trimSideMm < 85) return 10;
        return 11;
    }

    static getSpeedBucket(speed) {
        speed = Number(speed) || 0;
        if (speed <= 50) return 1;
        if (speed <= 60) return 2;
        if (speed <= 70) return 3;
        if (speed <= 80) return 4;
        if (speed <= 90) return 5;
        if (speed <= 100) return 6;
        if (speed <= 110) return 7;
        if (speed <= 120) return 8;
        if (speed <= 130) return 9;
        if (speed <= 140) return 10;
        return 11;
    }

    static formatThaiDate(dateStr) {
        if (!dateStr) return '-';
        const parts = String(dateStr).substring(0, 10).split('-');
        if (parts.length < 3) return dateStr;
        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        const d = parseInt(parts[2], 10);
        const thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        const thaiYear = y < 2400 ? y + 543 : y;
        return `${d} ${thaiMonths[m] || m} ${thaiYear}`;
    }

    static computeReportSummary(records, options = {}) {
        const filterShift = options.shift || '';
        const filterFlute = options.flute || '';
        const filterWo    = options.wo_prefix || '';

        const filtered = records.filter(r => {
            if (filterShift && String(r.shift) !== String(filterShift)) return false;
            if (filterFlute && String(r.flute) !== String(filterFlute)) return false;
            if (filterWo && String(r.wo_prefix) !== String(filterWo)) return false;
            return true;
        });

        const totalOrders = filtered.length;
        let totalMeters = 0.0;
        let totalWeight = 0.0;
        let totalProductionSec = 0;
        let totalStops = 0;
        let totalStopTimeSec = 0;
        let weightedSpeedSum = 0.0;

        const gsmBuckets = this.getGsmBuckets();
        const gsmMeters = {};
        const gsmWeights = {};
        gsmBuckets.forEach(g => {
            gsmMeters[g] = { M1: 0, M2: 0, M3: 0, M4: 0, M5: 0, total: 0 };
            gsmWeights[g] = { M1: 0, M2: 0, M3: 0, M4: 0, M5: 0, total: 0 };
        });

        const fluteSummary = {
            'B':  { orders: 0, meters: 0, seconds: 0, weight: 0 },
            'C':  { orders: 0, meters: 0, seconds: 0, weight: 0 },
            'BC': { orders: 0, meters: 0, seconds: 0, weight: 0 },
            'A':  { orders: 0, meters: 0, seconds: 0, weight: 0 },
            'AB': { orders: 0, meters: 0, seconds: 0, weight: 0 },
            'E':  { orders: 0, meters: 0, seconds: 0, weight: 0 },
        };

        const fluteAdd = {
            'B':  { add_orders: 0, add_small: 0, add_weight: 0 },
            'C':  { add_orders: 0, add_small: 0, add_weight: 0 },
            'BC': { add_orders: 0, add_small: 0, add_weight: 0 },
            'A':  { add_orders: 0, add_small: 0, add_weight: 0 },
            'AB': { add_orders: 0, add_small: 0, add_weight: 0 },
            'E':  { add_orders: 0, add_small: 0, add_weight: 0 },
        };

        const meterBucketMap = {
            1: '200 m',   2: '300 m',   3: '400 m',  4: '500 m',
            5: '700 m',   6: '1000 m',  7: '2500 m', 8: '3000 m',
            9: '5000 m', 10: '10000 m', 11: '20000 m'
        };
        const meterDist = {};
        Object.values(meterBucketMap).forEach(lbl => {
            meterDist[lbl] = { orders: 0, meters: 0 };
        });

        const trimBucketMap = {
            1: '13',  2: '15',  3: '20',  4: '25',  5: '30',
            6: '40',  7: '50',  8: '60',  9: '70', 10: '75', 11: '85'
        };
        const trimKeysOrder = ['13', '15', '20', '25', '30', '40', '50', '60', '70', '75', '85', 'อื่นๆ'];
        const trimDist = {};
        trimKeysOrder.forEach(mm => {
            trimDist[mm] = { orders: 0, meters: 0 };
        });

        const woList = ['PDR', 'PDQ', 'PDF', 'PDW', 'PDL', 'PDC', 'PDZ', 'PDS', 'PDE'];
        const woSummary = {};
        woList.forEach(w => { woSummary[w] = 0; });

        filtered.forEach(r => {
            const meters  = Number(r.total_length_m) || 0;
            const weight  = Number(r.total_weight_kg) || 0;
            const sec     = parseInt(r.production_seconds, 10) || 0;
            const speed   = Number(r.avg_speed) || 0;
            const stops   = parseInt(r.stop_count, 10) || 0;
            const stopSec = parseInt(r.stop_time_sec, 10) || 0;
            const widthM  = (Number(r.paper_width_mm) || 0) / 1000.0;

            totalMeters += meters;
            totalWeight += weight;
            totalProductionSec += sec;
            totalStops += stops;
            totalStopTimeSec += stopSec;
            weightedSpeedSum += (speed * meters);

            let fKey = String(r.flute || '').trim().toUpperCase();
            if (fKey === 'CB') fKey = 'BC';
            if (fluteSummary[fKey]) {
                fluteSummary[fKey].orders++;
                fluteSummary[fKey].meters  += meters;
                fluteSummary[fKey].seconds += sec;
                fluteSummary[fKey].weight  += weight;
            }
            if (fluteAdd[fKey]) {
                fluteAdd[fKey].add_orders += parseInt(r.add_order, 10) || 0;
                fluteAdd[fKey].add_small  += parseInt(r.add_small_sheets, 10) || 0;
                fluteAdd[fKey].add_weight += Number(r.add_weight) || 0;
            }

            const stands = [
                { pos: 'M1', w: Number(r.m1_weight) || 0, gsm: parseInt(r.m1_gsm, 10) || 0 },
                { pos: 'M2', w: Number(r.m2_weight) || 0, gsm: parseInt(r.m2_gsm, 10) || 0 },
                { pos: 'M3', w: Number(r.m3_weight) || 0, gsm: parseInt(r.m3_gsm, 10) || 0 },
                { pos: 'M4', w: Number(r.m4_weight) || 0, gsm: parseInt(r.m4_gsm, 10) || 0 },
                { pos: 'M5', w: Number(r.m5_weight) || 0, gsm: parseInt(r.m5_gsm, 10) || 0 },
            ];
            stands.forEach(st => {
                if (st.gsm > 0 && gsmWeights[st.gsm]) {
                    gsmWeights[st.gsm][st.pos] += st.w;
                    gsmWeights[st.gsm].total += st.w;
                    if (widthM > 0) {
                        const pMeters = (st.w * 1000.0) / (widthM * st.gsm);
                        gsmMeters[st.gsm][st.pos] += pMeters;
                        gsmMeters[st.gsm].total += pMeters;
                    }
                }
            });

            const bsMeter = parseInt(r.meter_bucket, 10) || this.getMeterBucket(meters);
            const meterLabel = meterBucketMap[bsMeter];
            if (meterLabel && meterDist[meterLabel]) {
                meterDist[meterLabel].orders++;
                meterDist[meterLabel].meters += meters;
            }

            const bvTrim = parseInt(r.trim_bucket, 10) || this.getTrimBucket(r.trim_side_mm);
            const trimMm = trimBucketMap[bvTrim];
            if (trimMm && trimDist[trimMm]) {
                trimDist[trimMm].orders++;
                trimDist[trimMm].meters += meters;
            } else {
                trimDist['อื่นๆ'].orders++;
                trimDist['อื่นๆ'].meters += meters;
            }

            const prefix = String(r.wo_prefix || String(r.wo_no || '').substring(0, 3)).trim().toUpperCase();
            if (prefix) {
                if (woSummary[prefix] === undefined) woSummary[prefix] = 0;
                woSummary[prefix] += meters;
            }
        });

        const avgMetersPerOrder = totalOrders > 0 ? (totalMeters / totalOrders) : 0;
        const avgSpeedGross = totalMeters > 0 ? (weightedSpeedSum / totalMeters) : 0;
        const runningMinutes = totalProductionSec / 60.0;
        const lossMinutes = totalStopTimeSec / 60.0;
        const avgSpeedNet = runningMinutes > 0 ? (totalMeters / runningMinutes) : 0;

        return {
            total_orders:        totalOrders,
            total_meters:        Math.round(totalMeters * 100) / 100,
            total_weight:        Math.round(totalWeight * 100) / 100,
            avg_meters_per_order:Math.round(avgMetersPerOrder * 10) / 10,
            avg_speed_gross:     Math.round(avgSpeedGross * 10) / 10,
            avg_speed_net:       Math.round(avgSpeedNet * 10) / 10,
            running_minutes:     Math.round(runningMinutes * 10) / 10,
            loss_minutes:        Math.round(lossMinutes * 10) / 10,
            total_stops:         totalStops,
            flute_summary:       fluteSummary,
            flute_add:           fluteAdd,
            meter_dist:          meterDist,
            trim_dist:           trimDist,
            trim_keys_order:     trimKeysOrder,
            wo_summary:          woSummary,
            filtered_count:      filtered.length
        };
    }

    /**
     * Parse HTML Table string (from .html/.xls/.txt or Paste)
     */
    static parseHtmlTable(rawHtml) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(rawHtml, 'text/html');
        let rows = Array.from(doc.querySelectorAll('table tbody tr'));
        if (rows.length === 0) {
            rows = Array.from(doc.querySelectorAll('table tr')).filter(tr => tr.querySelectorAll('td').length >= 20);
        }
        const parsedRows = [];
        const detectedShifts = {};
        let earliestDate = null;

        rows.forEach(tr => {
            const cols = tr.querySelectorAll('td');
            if (cols.length < 20) return;
            const txt = idx => (cols[idx] ? cols[idx].textContent.trim() : '');
            const num = idx => parseFloat(txt(idx).replace(/,/g, '')) || 0;
            const intVal = idx => parseInt(txt(idx).replace(/,/g, ''), 10) || 0;

            const seq = intVal(0);
            const shift = (txt(1) || 'B').toUpperCase();
            const planOrder = txt(2);
            const orderNo = txt(3);
            const woNo = txt(4);
            let flute = txt(5).toUpperCase();
            if (flute === 'CB') flute = 'BC';

            if (!seq && !woNo) return;

            const wM1 = num(6), wM2 = num(7), wM3 = num(8), wM4 = num(9), wM5 = num(10);
            const pM1 = txt(11), pM2 = txt(12), pM3 = txt(13), pM4 = txt(14), pM5 = txt(15);
            const paperWidth = num(16), sheetLen = num(17), sheetWt = num(18);
            const bigGood = intVal(19), bigWaste = intVal(20), knifeT = intVal(21), knifeS = num(22);
            const f1 = intVal(23), f2 = intVal(24), f3 = intVal(25), f4 = intVal(26), f5 = intVal(27), f6 = intVal(28);
            const smallTotal = intVal(29), smallGood = intVal(30);
            const startTimeStr = txt(31), endTimeStr = txt(32);
            const prodSec = intVal(33), stops = intVal(34), stopTimeSec = intVal(35), stackCount = intVal(36);
            const avgSpeed = num(37), totWeight = num(38), totArea = num(39), totMeters = num(40), totVolume = num(41);

            const startTime = this.normalizeDateTimeStr(startTimeStr);
            const endTime = this.normalizeDateTimeStr(endTimeStr);
            if (startTime) {
                const dStr = startTime.substring(0, 10);
                if (!earliestDate || dStr < earliestDate) earliestDate = dStr;
            }
            if (shift) detectedShifts[shift] = (detectedShifts[shift] || 0) + 1;

            parsedRows.push(this.buildProductionRow({
                seq, shift, planOrder, orderNo, woNo, flute,
                wM1, wM2, wM3, wM4, wM5,
                pM1, pM2, pM3, pM4, pM5,
                paperWidth, sheetLen, sheetWt, bigGood, bigWaste, knifeT, knifeS,
                f1, f2, f3, f4, f5, f6,
                smallTotal, smallGood, startTime, endTime,
                prodSec, stops, stopTimeSec, stackCount,
                avgSpeed, totWeight, totArea, totMeters, totVolume
            }));
        });

        return {
            parsedRows,
            detectedShifts,
            earliestDate,
            reliveData: { order_start: null, order_finit: null, shift_duration: 720, running_minutes: null, total_loss_min: null, losses: {} }
        };
    }

    /**
     * Parse Excel Workbook (.xlsx / .xls) via SheetJS
     */
    static parseExcelBuffer(arrayBuffer) {
        if (typeof XLSX === 'undefined') {
            throw new Error('ไลบรารีอ่านไฟล์ Excel (SheetJS) ยังโหลดไม่เสร็จ กรุณารอสักครู่แล้วลองใหม่');
        }
        const wb = XLSX.read(arrayBuffer, { type: 'array', cellDates: false });
        let machineSheetName = null;
        let reliveSheetName = null;

        wb.SheetNames.forEach(sName => {
            if (sName.indexOf('ข้อมูลเครื่อง') !== -1 || sName.toLowerCase().indexOf('machine') !== -1) {
                machineSheetName = sName;
            } else if (sName.toLowerCase().indexOf('relive') !== -1) {
                reliveSheetName = sName;
            }
        });

        if (!machineSheetName) {
            let bestCount = 0;
            wb.SheetNames.forEach(sName => {
                const ws = wb.Sheets[sName];
                const range = ws['!ref'] ? XLSX.utils.decode_range(ws['!ref']) : null;
                const rowCount = range ? (range.e.r - range.s.r + 1) : 0;
                if (rowCount > bestCount) {
                    bestCount = rowCount;
                    machineSheetName = sName;
                }
            });
        }

        if (!machineSheetName) {
            throw new Error('ไม่พบหน้าตาราง "ข้อมูลเครื่อง" ในไฟล์ Excel ที่นำเข้า');
        }

        const reliveData = {
            order_start: null,
            order_finit: null,
            shift_duration: 720,
            running_minutes: null,
            total_loss_min: null,
            losses: {}
        };

        if (reliveSheetName && wb.Sheets[reliveSheetName]) {
            const rWs = wb.Sheets[reliveSheetName];
            const getCellVal = ref => (rWs[ref] && rWs[ref].v !== undefined ? rWs[ref].v : null);
            reliveData.shift_duration  = parseInt(getCellVal('F15') || getCellVal('E15') || 720, 10) || 720;
            reliveData.running_minutes = parseFloat(getCellVal('F16') || 0) || 0;
            reliveData.total_loss_min  = parseFloat(getCellVal('H30') || getCellVal('F17') || 0) || 0;
            reliveData.losses = {
                loss_person:   { occ: parseInt(getCellVal('F18') || 0, 10) || 0, min: parseInt(getCellVal('H18') || 0, 10) || 0 },
                loss_mc:       { occ: parseInt(getCellVal('F19') || 0, 10) || 0, min: parseInt(getCellVal('H19') || 0, 10) || 0 },
                loss_material: { occ: parseInt(getCellVal('F20') || 0, 10) || 0, min: parseInt(getCellVal('H20') || 0, 10) || 0 },
                loss_other:    { occ: parseInt(getCellVal('F21') || 0, 10) || 0, min: parseInt(getCellVal('H21') || 0, 10) || 0 },
            };
        }

        const ws = wb.Sheets[machineSheetName];
        const rowsAoA = XLSX.utils.sheet_to_json(ws, { header: 1, raw: true, defval: '' });
        const parsedRows = [];
        const detectedShifts = {};
        let earliestDate = null;

        for (let rIdx = 1; rIdx < rowsAoA.length; rIdx++) {
            const row = rowsAoA[rIdx];
            if (!row || (!row[0] && !row[4])) continue;

            const txt = c => (row[c] !== undefined && row[c] !== null ? String(row[c]).trim() : '');
            const num = c => parseFloat(String(row[c] ?? '0').replace(/,/g, '')) || 0;
            const intVal = c => parseInt(String(row[c] ?? '0').replace(/,/g, ''), 10) || 0;

            const seq = intVal(0);
            const shift = (txt(1) || 'B').toUpperCase();
            const planOrder = txt(2);
            const orderNo = txt(3);
            const woNo = txt(4);
            let flute = txt(5).toUpperCase();
            if (flute === 'CB') flute = 'BC';

            if (!seq && !woNo) continue;

            const wM1 = num(6), wM2 = num(7), wM3 = num(8), wM4 = num(9), wM5 = num(10);
            const pM1 = txt(11), pM2 = txt(12), pM3 = txt(13), pM4 = txt(14), pM5 = txt(15);
            const paperWidth = num(16), sheetLen = num(17), sheetWt = num(18);
            const bigGood = intVal(19), bigWaste = intVal(20), knifeT = intVal(21), knifeS = num(22);
            const f1 = intVal(23), f2 = intVal(24), f3 = intVal(25), f4 = intVal(26), f5 = intVal(27), f6 = intVal(28);
            const smallTotal = intVal(29), smallGood = intVal(30);

            const startTime = this.normalizeExcelDate(row[31]);
            const endTime   = this.normalizeExcelDate(row[32]);
            if (startTime) {
                const dStr = startTime.substring(0, 10);
                if (!earliestDate || dStr < earliestDate) earliestDate = dStr;
            }

            const prodSec = intVal(33), stops = intVal(34), stopTimeSec = intVal(35), stackCount = intVal(36);
            const avgSpeed = num(37), totWeight = num(38), totArea = num(39), totMeters = num(40), totVolume = num(41);

            if (shift) detectedShifts[shift] = (detectedShifts[shift] || 0) + 1;

            parsedRows.push(this.buildProductionRow({
                seq, shift, planOrder, orderNo, woNo, flute,
                wM1, wM2, wM3, wM4, wM5,
                pM1, pM2, pM3, pM4, pM5,
                paperWidth, sheetLen, sheetWt, bigGood, bigWaste, knifeT, knifeS,
                f1, f2, f3, f4, f5, f6,
                smallTotal, smallGood, startTime, endTime,
                prodSec, stops, stopTimeSec, stackCount,
                avgSpeed, totWeight, totArea, totMeters, totVolume
            }));
        }

        return { parsedRows, detectedShifts, earliestDate, reliveData };
    }

    static normalizeExcelDate(val) {
        if (val === undefined || val === null || val === '') return null;
        if (typeof val === 'number' && val > 1000) {
            const ts = Math.round((val - 25569) * 86400) * 1000;
            const d = new Date(ts);
            return d.toISOString().replace('T', ' ').substring(0, 19);
        }
        return this.normalizeDateTimeStr(String(val));
    }

    static normalizeDateTimeStr(str) {
        if (!str) return null;
        str = String(str).trim();
        if (!str) return null;
        const d = new Date(str.replace(' ', 'T'));
        if (!isNaN(d.getTime())) {
            const pad = n => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        }
        return str.length >= 19 ? str.substring(0, 19) : str;
    }

    static buildProductionRow(d) {
        const trimMm = Math.max(0, d.paperWidth - d.knifeS);
        const woPrefix = String(d.woNo || '').substring(0, 3).toUpperCase();
        const addOrder = d.bigWaste >= 1 ? 1 : 0;
        const addSmallSheets = d.bigWaste * d.knifeT;
        const addWeight = addSmallSheets * d.sheetWt;
        const prodMin = d.prodSec > 0 ? Math.round((d.prodSec / 60.0) * 100) / 100 : 0.00;

        const g1 = this.extractGsm(d.pM1);
        const g2 = this.extractGsm(d.pM2);
        const g3 = this.extractGsm(d.pM3);
        const g4 = this.extractGsm(d.pM4);
        const g5 = this.extractGsm(d.pM5);

        const m1Class = this.classifyGsm(g1);
        const m2Class = this.classifyGsm(g2);
        const m3Class = this.classifyGsm(g3);
        const m4Class = this.classifyGsm(g4);
        const m5Class = this.classifyGsm(g5);

        const meterBucket = this.getMeterBucket(d.totMeters);
        const cutlengBucket = this.getCutlengBucket(d.sheetLen);
        const trimSideMm = this.calculateTrimSide(d.paperWidth, d.knifeT, d.knifeS);
        const trimBucket = this.getTrimBucket(trimSideMm);
        const speedBucket = this.getSpeedBucket(d.avgSpeed);

        return {
            seq_no:             d.seq,
            shift:              d.shift,
            plan_order:         d.planOrder,
            order_no:           d.orderNo,
            wo_no:              d.woNo,
            wo_prefix:          woPrefix,
            flute:              d.flute,
            m1_weight:          d.wM1,
            m2_weight:          d.wM2,
            m3_weight:          d.wM3,
            m4_weight:          d.wM4,
            m5_weight:          d.wM5,
            m1_paper:           d.pM1,
            m2_paper:           d.pM2,
            m3_paper:           d.pM3,
            m4_paper:           d.pM4,
            m5_paper:           d.pM5,
            m1_gsm:             g1,
            m2_gsm:             g2,
            m3_gsm:             g3,
            m4_gsm:             g4,
            m5_gsm:             g5,
            m1_class:           m1Class,
            m2_class:           m2Class,
            m3_class:           m3Class,
            m4_class:           m4Class,
            m5_class:           m5Class,
            paper_width_mm:     d.paperWidth,
            sheet_length_mm:    d.sheetLen,
            sheet_weight_kg:    d.sheetWt,
            good_big_sheets:    d.bigGood,
            waste_big_sheets:   d.bigWaste,
            knife_t:            d.knifeT,
            knife_s:            d.knifeS,
            trim_mm:            trimMm,
            f1:                 d.f1,
            f2:                 d.f2,
            f3:                 d.f3,
            f4:                 d.f4,
            f5:                 d.f5,
            f6:                 d.f6,
            total_small_sheets: d.smallTotal,
            good_small_sheets:  d.smallGood,
            start_time:         d.startTime,
            end_time:           d.endTime,
            production_seconds: d.prodSec,
            stop_count:         d.stops,
            stop_time_sec:      d.stopTimeSec,
            stack_count:        d.stackCount,
            avg_speed:          d.avgSpeed,
            total_weight_kg:    d.totWeight,
            total_area_sqm:     d.totArea,
            total_length_m:     d.totMeters,
            total_volume_sqm:   d.totVolume,
            add_order:          addOrder,
            add_small_sheets:   addSmallSheets,
            add_weight:         addWeight,
            production_minutes: prodMin,
            meter_bucket:       meterBucket,
            cutleng_bucket:     cutlengBucket,
            trim_side_mm:       trimSideMm,
            trim_bucket:        trimBucket,
            speed_bucket:       speedBucket
        };
    }
}

/**
 * Browser IndexedDB + Cloud Sync Database Layer
 */
class BhsDB {
    static DB_NAME = 'bhs_datamachine_v1';
    static STORE_NAME = 'state';
    static state = null;

    static async init(basePath = '.') {
        if (this.state) return this.state;

        // 1. Try reading from IndexedDB
        let saved = await this.readFromIdb('db_data');
        if (!saved) {
            try {
                const resp = await fetch(`${basePath}/assets/data/initial_db.json?v=1`);
                if (resp.ok) {
                    saved = await resp.json();
                    await this.writeToIdb('db_data', saved);
                }
            } catch (e) {
                console.warn('Could not load initial_db.json:', e);
            }
        }

        if (!saved) {
            saved = {
                users: [
                    { id: 1, username: 'admin', password_plain: '123456', full_name: 'ผู้ดูแลระบบ (Admin BHS)', role: 'admin', status: 'active', created_at: '2026-03-19 08:40:28' },
                    { id: 2, username: 'user', password_plain: '123456', full_name: 'พนักงานประจำกะ (General User)', role: 'user', status: 'active', created_at: '2026-03-19 08:40:28' }
                ],
                bhs_target_templates: [],
                bhs_shift_reports: [],
                bhs_production_records: []
            };
        }

        this.state = saved;
        return this.state;
    }

    static async save() {
        if (!this.state) return;
        await this.writeToIdb('db_data', this.state);
    }

    static openIdb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(this.DB_NAME, 1);
            req.onupgradeneeded = e => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(this.STORE_NAME)) {
                    db.createObjectStore(this.STORE_NAME);
                }
            };
            req.onsuccess = e => resolve(e.target.result);
            req.onerror = e => reject(e.target.error);
        });
    }

    static async readFromIdb(key) {
        try {
            const db = await this.openIdb();
            return await new Promise((resolve, reject) => {
                const tx = db.transaction(this.STORE_NAME, 'readonly');
                const store = tx.objectStore(this.STORE_NAME);
                const req = store.get(key);
                req.onsuccess = () => resolve(req.result || null);
                req.onerror = () => resolve(null);
            });
        } catch (e) {
            return null;
        }
    }

    static async writeToIdb(key, val) {
        try {
            const db = await this.openIdb();
            return await new Promise((resolve, reject) => {
                const tx = db.transaction(this.STORE_NAME, 'readwrite');
                const store = tx.objectStore(this.STORE_NAME);
                const req = store.put(val, key);
                req.onsuccess = () => resolve(true);
                req.onerror = () => resolve(false);
            });
        } catch (e) {
            return false;
        }
    }

    static async syncReportToGoogleSheet(reportId) {
        const report = this.state.bhs_shift_reports.find(r => Number(r.id) === Number(reportId));
        if (!report) return { success: false, message: 'ไม่พบรายงานที่ต้องการส่ง' };

        const records = this.state.bhs_production_records.filter(r => Number(r.report_id) === Number(reportId));
        const summary = BhsEngine.computeReportSummary(records);

        let lossData = {};
        try {
            lossData = typeof report.loss_details === 'string' ? JSON.parse(report.loss_details) : (report.loss_details || BhsEngine.getDefaultLossDepartments());
        } catch (e) {
            lossData = BhsEngine.getDefaultLossDepartments();
        }

        let totalLossMinutes = 0;
        let totalScore = 0;
        Object.values(lossData).forEach(info => {
            totalLossMinutes += parseInt(info.minutes || 0, 10);
            totalScore += parseInt(info.score || 3, 10);
        });

        const shiftStartTime = report.shift_start_time || '20:00';
        const shiftEndTime = report.shift_end_time || '07:40';
        let actualShiftDuration = parseInt(report.actual_time_total || 0, 10);
        if (actualShiftDuration <= 0) {
            actualShiftDuration = BhsEngine.calculateShiftDurationMinutes(shiftStartTime, shiftEndTime);
        }
        const runningTimeMin = Math.max(0, actualShiftDuration - totalLossMinutes);
        const lossTotalPct = actualShiftDuration > 0 ? Math.round((totalLossMinutes / actualShiftDuration) * 10000) / 100 : 0;

        const actualMeters = Number(report.actual_meters) > 0 ? Number(report.actual_meters) : summary.total_meters;
        const payload = {
            report_id: report.id,
            title: report.title,
            machine_name: report.machine_name || 'BHS',
            shift: report.shift,
            report_date: report.report_date,
            shift_start_time: shiftStartTime,
            shift_end_time: shiftEndTime,
            actual_shift_duration: actualShiftDuration,
            running_time_min: runningTimeMin,
            total_orders: summary.total_orders,
            total_meters: actualMeters,
            data_meters: summary.total_meters,
            total_weight_kg: summary.total_weight,
            total_weight_ton: Math.round((summary.total_weight / 1000) * 100) / 100,
            avg_speed: summary.avg_speed_gross,
            total_loss_minutes: totalLossMinutes,
            loss_total_pct: lossTotalPct,
            total_kpi_score: totalScore,
            summary_notes: report.summary_notes || '',
            remarks: report.remarks || '',
            raw_filename: report.raw_filename || ''
        };

        try {
            await fetch(GOOGLE_SHEET_DEFAULT_WEBHOOK, {
                method: 'POST',
                mode: 'no-cors',
                headers: { 'Content-Type': 'text/plain;charset=utf-8' },
                body: JSON.stringify(payload)
            });
            return { success: true, message: 'ส่งข้อมูลไปยัง Google Sheets เรียบร้อยแล้ว!' };
        } catch (err) {
            return { success: false, message: err.message };
        }
    }
}

window.BhsEngine = BhsEngine;
window.BhsDB = BhsDB;
