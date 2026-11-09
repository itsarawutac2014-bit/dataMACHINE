/**
 * BHS Performance System - Report View, Admin Targets View & Users View
 * Extends window.BhsApp with 100% fidelity to views/report_view.php, views/admin_targets.php, views/users.php
 */

Object.assign(window.BhsApp, {

    // =========================================================================
    // 4. REPORT VIEW (Full Master BHS Shift Performance Report + 76 Cols + 4 Charts)
    // =========================================================================
    renderReportView(params) {
        const reportId = parseInt(params.get('id') || '0', 10);
        const report = (BhsDB.state.bhs_shift_reports || []).find(r => Number(r.id) === reportId);

        if (!report) {
            this.setFlash('danger', 'ไม่พบข้อมูลรายงานที่ต้องการ');
            this.navigate('#dashboard');
            return;
        }

        const rawRecords = (BhsDB.state.bhs_production_records || [])
            .filter(r => Number(r.report_id) === reportId)
            .sort((a, b) => (Number(a.seq_no) || 0) - (Number(b.seq_no) || 0));

        const filterShift = params.get('shift') || '';
        const filterFlute = params.get('flute') || '';
        const filterWo    = params.get('wo_prefix') || '';

        const filteredRecords = rawRecords.filter(r => {
            if (filterShift && String(r.shift) !== filterShift) return false;
            if (filterFlute && String(r.flute) !== filterFlute) return false;
            if (filterWo && String(r.wo_prefix) !== filterWo) return false;
            return true;
        });

        const summary = BhsEngine.computeReportSummary(filteredRecords);

        let lossData = null;
        try {
            lossData = typeof report.loss_details === 'string' ? JSON.parse(report.loss_details) : report.loss_details;
        } catch (e) {}
        if (!lossData || typeof lossData !== 'object') {
            lossData = BhsEngine.getDefaultLossDepartments();
        }

        let totalLossOccurrences = 0;
        let totalLossMinutes = 0;
        let totalScore = 0;
        let totalTargetLossPct = 0.0;
        Object.entries(lossData).forEach(([dept, info]) => {
            totalLossOccurrences += parseInt(info.occurrences || 0, 10);
            totalLossMinutes     += parseInt(info.minutes || 0, 10);
            totalScore           += parseInt(info.score ?? 3, 10);
            totalTargetLossPct   += parseFloat(info.target_pct || 0.0);
        });
        if (totalTargetLossPct <= 0) totalTargetLossPct = 10.0;

        const totFluteWeight = Number(summary.total_weight) || 0;

        let stationWasteData = {};
        try {
            stationWasteData = typeof report.station_waste === 'string' ? JSON.parse(report.station_waste) : (report.station_waste || {});
        } catch (e) {}

        const defaultStations = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];
        const stationWasteKg = {};
        let totalStationWasteKg = 0;
        defaultStations.forEach(st => {
            let val = 0;
            if (stationWasteData && stationWasteData[st] !== undefined) {
                val = typeof stationWasteData[st] === 'object' ? (parseFloat(stationWasteData[st].kg) || 0) : (parseFloat(stationWasteData[st]) || 0);
            }
            stationWasteKg[st] = val;
            totalStationWasteKg += val;
        });

        const tpl = (BhsDB.state.bhs_target_templates || [])[0] || {};
        let stationWasteTargets = {};
        try {
            stationWasteTargets = typeof tpl.station_waste_targets === 'string' ? JSON.parse(tpl.station_waste_targets) : (tpl.station_waste_targets || {});
        } catch (e) {}

        const totalOrdersActual = summary.total_orders;
        const dataMetersActual  = summary.total_meters;
        const totalMetersActual = (Number(report.actual_meters) > 0) ? Number(report.actual_meters) : dataMetersActual;
        const avgMetersActual   = totalOrdersActual > 0 ? Math.round((totalMetersActual / totalOrdersActual) * 10) / 10 : summary.avg_meters_per_order;
        let avgSpeedGross       = summary.avg_speed_gross;
        let avgSpeedNet         = summary.avg_speed_net;

        const orderStart     = parseInt(report.order_start ?? 10, 10);
        const orderFinit     = parseInt(report.order_finit ?? 1120, 10);
        const orderPlanTotal = parseInt(report.order_plan_total ?? 120, 10);
        const insertedMeters = parseFloat(report.inserted_meters ?? 0);
        const pendingMeters  = parseFloat(report.pending_meters ?? 0);
        const shortageOrders = parseInt(report.shortage_orders ?? 24, 10);

        const targetOrders    = parseInt(report.target_orders || 120, 10);
        const targetMeters    = parseFloat(report.target_meters || 68056);
        const targetSpeed     = parseFloat(report.target_speed || 100);
        const targetAvgMeters = parseFloat(report.target_avg_meters || 500);

        const shiftStartTime = report.shift_start_time || '20:00';
        const shiftEndTime   = report.shift_end_time || '07:40';
        let actualShiftDuration = parseInt(report.actual_time_total || 0, 10);
        if (actualShiftDuration <= 0) {
            actualShiftDuration = BhsEngine.calculateShiftDurationMinutes(shiftStartTime, shiftEndTime);
        }

        const targetShiftTotalTime = parseInt(report.target_time_total || 720, 10);
        const targetTimeRun        = parseInt(report.target_time_run || 675, 10);

        const runningTimeMin = Math.max(0, actualShiftDuration - totalLossMinutes);
        const runningPct     = targetTimeRun > 0 ? Math.round((runningTimeMin / targetTimeRun) * 100) : 0;
        const shiftTotalPct  = targetShiftTotalTime > 0 ? Math.round((actualShiftDuration / targetShiftTotalTime) * 100) : 100;
        const lossTotalPct   = actualShiftDuration > 0 ? Math.round((totalLossMinutes / actualShiftDuration) * 10000) / 100 : 0;

        const orderPlanPct   = targetOrders > 0 ? Math.round((orderPlanTotal / targetOrders) * 100) : 0;
        const orderPlanScore = orderPlanPct >= 100 ? 3 : 1;

        const shortagePct   = totalOrdersActual > 0 ? Math.round((shortageOrders / totalOrdersActual) * 100) : 0;
        const shortageScore = shortagePct <= 15 ? 3 : 1;

        if (runningTimeMin > 0 && totalMetersActual > 0) {
            avgSpeedNet = Math.round(totalMetersActual / runningTimeMin);
        }
        if (actualShiftDuration > 0 && totalMetersActual > 0) {
            avgSpeedGross = Math.round(totalMetersActual / actualShiftDuration);
        }

        const fs = summary.flute_summary;
        const fa = summary.flute_add;
        const totFluteOrders = summary.total_orders;
        const totFluteMeters = summary.total_meters;
        let totFluteSec = 0;
        Object.values(fs).forEach(v => { totFluteSec += v.seconds; });
        const totFluteMin = Math.round(totFluteSec / 60);
        const totFluteHours = Math.round((totFluteMin / 60) * 10) / 10;

        let totAddOrders = 0, totAddSmall = 0, totAddWeight = 0;
        Object.values(fa).forEach(fv => {
            totAddOrders += fv.add_orders;
            totAddSmall  += fv.add_small;
            totAddWeight += fv.add_weight;
        });

        let totalTargetPctSum = 0;
        let totalTargetWeightSum = 0;
        defaultStations.forEach(st => {
            const tPct = parseFloat(stationWasteTargets[st]?.target_pct ?? stationWasteTargets[st]?.target_kg ?? 0);
            totalTargetPctSum += tPct;
            if (totFluteWeight > 0 && tPct > 0) {
                totalTargetWeightSum += Math.round(((tPct / 100) * totFluteWeight) * 10) / 10;
            }
        });
        const totalActualProdPct = (totFluteWeight > 0 && totalStationWasteKg > 0)
            ? Math.round((totalStationWasteKg / totFluteWeight) * 10000) / 100
            : 0;
        let totalActualBg = '#d9e1f2', totalActualColor = '#dc3545';
        if (totalTargetPctSum > 0) {
            if (totalActualProdPct <= totalTargetPctSum) {
                totalActualBg = '#d4edda'; totalActualColor = '#155724';
            } else {
                totalActualBg = '#f8d7da'; totalActualColor = '#721c24';
            }
        }

        const mName = report.machine_name || 'BHS';
        const mBadge = mName === 'YUELI' ? 'bg-success' : (mName === 'ISOWA' ? 'bg-dark' : 'bg-primary');

        let totRangeOrders = 0, totRangeMeters = 0;
        const meterRowsHtml = Object.entries(summary.meter_dist).map(([rangeLbl, rData]) => {
            totRangeOrders += rData.orders;
            totRangeMeters += rData.meters;
            return `
            <tr>
                <td class="td-range-white fs-8 fw-semibold">${rangeLbl}</td>
                <td class="td-range-white fs-8">${rData.orders}</td>
                <td class="td-range-white fs-8">${this.numFmt(rData.meters)}</td>
            </tr>`;
        }).join('');

        let totTrimOrders = 0, totTrimMeters = 0;
        const trimRowsHtml = summary.trim_keys_order.map(trimVal => {
            const tData = summary.trim_dist[trimVal];
            if (!tData || (trimVal === 'อื่นๆ' && tData.orders === 0)) return '';
            totTrimOrders += tData.orders;
            totTrimMeters += tData.meters;
            return `
            <tr>
                <td class="td-range-white fs-8 fw-semibold">${trimVal}</td>
                <td class="td-range-white fs-8">${tData.orders}</td>
                <td class="td-range-white fs-8">${this.numFmt(tData.meters)}</td>
            </tr>`;
        }).join('');

        let totWoMeters = 0;
        const woRowsHtml = Object.entries(summary.wo_summary).map(([woPrefix, wMeters]) => {
            totWoMeters += wMeters;
            return `
            <tr>
                <td class="td-range-white fs-8 fw-semibold text-primary">${this.esc(woPrefix)}</td>
                <td class="td-range-white fs-8">${this.numFmt(wMeters)}</td>
            </tr>`;
        }).join('');

        const html = `
        <!-- Top Action Toolbar -->
        <div class="row align-items-center mb-3 no-capture">
            <div class="col-lg-6">
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-file-waveform text-orange me-2"></i> ${this.esc(report.title)}
                </h4>
                <div class="text-muted fs-7">
                    <span><i class="fa-regular fa-calendar me-1"></i> วันที่: <strong>${BhsEngine.formatThaiDate(report.report_date)}</strong></span>
                    <span class="ms-3"><i class="fa-regular fa-clock me-1"></i> ตัดกะ: <strong>${this.esc(report.shift_end_time)}</strong></span>
                    <span class="ms-3"><i class="fa-solid fa-list-ol me-1"></i> ข้อมูล: <strong>${filteredRecords.length} / ${rawRecords.length} รายการ</strong></span>
                </div>
            </div>
            <div class="col-lg-6 text-lg-end mt-2 mt-lg-0 d-flex flex-wrap gap-2 justify-content-lg-end">
                <button type="button" class="btn btn-success rounded-pill px-3 shadow-sm fw-bold btn-save-action" id="btnSaveInlineKpiTop">
                    <i class="fa-solid fa-floppy-disk me-1"></i> 💾 บันทึกข้อมูลตาราง (Save Data)
                </button>
                <button type="button" class="btn btn-outline-success rounded-pill px-3 shadow-sm fw-bold" id="btnSyncGoogleSheet" data-report-id="${reportId}">
                    <i class="fa-solid fa-table me-1"></i> 📊 ส่งไป Google Sheets
                </button>
                <button type="button" class="btn btn-orange rounded-pill px-3 shadow-sm fw-bold" id="btnSaveHdImage">
                    <i class="fa-solid fa-camera me-1"></i> 📸 บันทึกภาพแบบ HD (Save HD Image)
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEditLoss">
                    <i class="fa-solid fa-pen-to-square me-1"></i> แก้ไขฟอร์มละเอียด
                </button>
                ${this.isAdmin() ? `
                <a href="#admin_targets?report_id=${reportId}" class="btn btn-outline-primary rounded-pill px-3 shadow-sm fw-bold">
                    <i class="fa-solid fa-sliders me-1"></i> จัดการ Target & หัวข้อ
                </a>` : ''}
                <a href="#dashboard" class="btn btn-outline-dark rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> กลับ
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card card-theme shadow-sm border-0 mb-3 no-capture">
            <div class="card-body p-3">
                <form id="filterReportForm" class="row g-2 align-items-end">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                            <i class="fa-solid fa-filter text-orange me-1"></i> กรองกะ (Shift)
                        </label>
                        <select id="selFilterShift" class="form-select form-select-sm">
                            <option value="">ทั้งหมด (All Shifts)</option>
                            <option value="A" ${filterShift === 'A' ? 'selected' : ''}>กะ A</option>
                            <option value="B" ${filterShift === 'B' ? 'selected' : ''}>กะ B</option>
                            <option value="C" ${filterShift === 'C' ? 'selected' : ''}>กะ C</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                            <i class="fa-solid fa-cubes text-orange me-1"></i> กรองลอน (Flute)
                        </label>
                        <select id="selFilterFlute" class="form-select form-select-sm">
                            <option value="">ทุกลอน (All Flutes)</option>
                            ${['B','C','BC','A','AB','E'].map(f => `<option value="${f}" ${filterFlute === f ? 'selected' : ''}>ลอน ${f}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                            <i class="fa-solid fa-hashtag text-orange me-1"></i> ประเภทงาน (Wo Prefix)
                        </label>
                        <select id="selFilterWo" class="form-select form-select-sm">
                            <option value="">ทุกประเภท (All Wo)</option>
                            ${['PDR','PDW','PDF','PDS','PDE'].map(w => `<option value="${w}" ${filterWo === w ? 'selected' : ''}>${w}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-orange btn-sm w-100 fw-bold">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> กรองข้อมูล
                        </button>
                        <a href="#report_view?id=${reportId}" class="btn btn-outline-secondary btn-sm" title="ล้างตัวกรอง">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- REPORT MASTER CAPTURE AREA -->
        <div id="hdReportCaptureArea" class="bg-white border rounded shadow-sm p-3">
            <div class="row align-items-center mb-2 px-1">
                <div class="col-md-5 d-flex align-items-center mb-2 mb-md-0">
                    <span class="badge ${mBadge} fs-7 me-2 px-2 py-1">
                        <i class="fa-solid fa-industry me-1"></i>${this.esc(mName)}
                    </span>
                    <h4 class="fw-bold mb-0 text-dark" id="reportTitleHeader" style="letter-spacing: -0.5px;">
                        ${this.esc(report.title)}
                    </h4>
                </div>
                <div class="col-md-4 text-center mb-2 mb-md-0">
                    <h5 class="fw-bold mb-0 text-dark">${BhsEngine.formatThaiDate(report.report_date)}</h5>
                    <div class="fs-8 text-secondary">
                        เวลาเดิน: <strong>${this.esc(shiftStartTime)} - ${this.esc(shiftEndTime)}</strong>
                        (<span id="lblDurationBadge" class="text-danger fw-bold">${actualShiftDuration} นาที</span>)
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <div class="d-inline-flex align-items-center border border-dark px-2 py-1 bg-white">
                        <span class="fs-8 fw-bold me-2">เวลาจบ/ตัดกะ</span>
                        <span class="fs-6 fw-bold text-dark">${this.esc(report.shift_end_time)}</span>
                    </div>
                </div>
            </div>

            <div class="row g-2">
                <!-- LEFT COLUMN -->
                <div class="col-lg-6">
                    <form id="formInlineKpi">
                        <input type="hidden" name="report_id" value="${reportId}">
                        <input type="hidden" name="shift_start_time" id="hiddenShiftStartTime" value="${this.esc(shiftStartTime)}">
                        <input type="hidden" name="shift_end_time" id="hiddenShiftEndTime" value="${this.esc(shiftEndTime)}">

                        <input type="hidden" id="valTotalOrdersActual" value="${totalOrdersActual}">
                        <input type="hidden" id="valTotalMetersActual" value="${totalMetersActual}">
                        <input type="hidden" id="valTargetOrders" value="${targetOrders}">
                        <input type="hidden" id="valTargetMeters" value="${targetMeters}">
                        <input type="hidden" id="valTargetSpeed" value="${targetSpeed}">
                        <input type="hidden" id="valTargetAvgMeters" value="${targetAvgMeters}">
                        <input type="hidden" id="valTargetShiftTotalTime" value="${targetShiftTotalTime}">
                        <input type="hidden" id="valTargetTimeRun" value="${targetTimeRun}">
                        <input type="hidden" id="valTotalProductionWeight" value="${totFluteWeight}">

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
                                    <tr>
                                        <td class="td-kpi-pink text-start fw-semibold">Order Start/Order Finit จากแผน</td>
                                        <td class="td-kpi-pink fw-bold text-primary">${targetOrders}</td>
                                        <td class="td-kpi-pink fs-8">Start<br><input type="number" name="order_start" id="inpOrderStart" class="table-input-cell fw-bold text-dark" value="${orderStart}" style="width: 48px;"></td>
                                        <td class="td-kpi-pink fs-8">Finit<br><input type="number" name="order_finit" id="inpOrderFinit" class="table-input-cell fw-bold text-dark" value="${orderFinit}" style="width: 54px;"></td>
                                        <td class="td-kpi-pink fs-8">รวม<br><input type="number" name="order_plan_total" id="inpOrderPlanTotal" class="table-input-cell fw-bold text-danger" value="${orderPlanTotal}" style="width: 48px;"></td>
                                        <td class="td-kpi-pink">Order</td>
                                        <td class="td-kpi-pink fw-bold" id="lblOrderPlanPct">${orderPlanPct}%</td>
                                        <td class="td-kpi-pink fw-bold ${orderPlanScore >= 3 ? 'text-success' : 'text-danger'}" id="lblOrderPlanScore">${orderPlanScore}</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">จำนวนออเดอร์ที่เดินทั้งหมด+ซ่อม</td>
                                        <td class="td-kpi-pink text-primary fw-bold">${targetOrders}</td>
                                        <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblTotalOrdersActual">${totalOrdersActual}</td>
                                        <td class="td-kpi-pink">Order</td>
                                        <td class="td-kpi-pink fw-bold" id="lblTotalOrdersPct">${targetOrders > 0 ? Math.round((totalOrdersActual / targetOrders) * 100) : 0}%</td>
                                        <td class="td-kpi-pink fw-bold ${totalOrdersActual >= targetOrders ? 'text-success' : 'text-danger'}" id="lblTotalOrdersScore">${totalOrdersActual >= targetOrders ? 3 : 1}</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">ผลิตได้ (เมตรเดินงานจริง)</td>
                                        <td class="td-kpi-pink text-primary fw-bold">${this.numFmt(targetMeters)}</td>
                                        <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblTotalMetersActual">
                                            <input type="number" step="any" min="0" name="actual_meters" id="inpActualMeters" class="table-input-cell fw-bold text-danger fs-6" value="${totalMetersActual}" style="width: 110px;" title="เมตรเดินงานจริง (ข้อมูลจากไฟล์: ${this.numFmt(dataMetersActual)} เมตร)">
                                        </td>
                                        <td class="td-kpi-pink">เมตร</td>
                                        <td class="td-kpi-pink fw-bold" id="lblTotalMetersPct">${targetMeters > 0 ? Math.round((totalMetersActual / targetMeters) * 100) : 0}%</td>
                                        <td class="td-kpi-pink fw-bold ${totalMetersActual >= targetMeters ? 'text-success' : 'text-danger'}" id="lblTotalMetersScore">${totalMetersActual >= targetMeters ? 3 : 1}</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">งานแทรก</td>
                                        <td class="td-kpi-pink">-</td>
                                        <td class="td-kpi-pink" colspan="3"><input type="number" step="any" min="0" name="inserted_meters" id="inpInsertedMeters" class="table-input-cell" value="${insertedMeters}" style="width: 70px;"></td>
                                        <td class="td-kpi-pink">เมตร</td>
                                        <td class="td-kpi-pink">0%</td>
                                        <td class="td-kpi-pink fw-bold text-success">3</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">งานค้าง/ไม่ค้าง</td>
                                        <td class="td-kpi-pink">0</td>
                                        <td class="td-kpi-pink" colspan="3"><input type="number" step="any" min="0" name="pending_meters" id="inpPendingMeters" class="table-input-cell" value="${pendingMeters}" style="width: 70px;"></td>
                                        <td class="td-kpi-pink">เมตร</td>
                                        <td class="td-kpi-pink">0%</td>
                                        <td class="td-kpi-pink fw-bold text-success">3</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">เมตรเฉลี่ย/order</td>
                                        <td class="td-kpi-pink text-primary fw-bold">${this.numFmt(targetAvgMeters)}</td>
                                        <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblAvgMetersActual">${this.numFmt(avgMetersActual)}</td>
                                        <td class="td-kpi-pink">เมตร</td>
                                        <td class="td-kpi-pink fw-bold" id="lblAvgMetersPct">${targetAvgMeters > 0 ? Math.round((avgMetersActual / targetAvgMeters) * 100) : 0}%</td>
                                        <td class="td-kpi-pink fw-bold ${avgMetersActual >= targetAvgMeters ? 'text-success' : 'text-danger'}" id="lblAvgMetersScore">${avgMetersActual >= targetAvgMeters ? 3 : 1}</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">Speed เฉลี่ยหักสูญเสีย</td>
                                        <td class="td-kpi-pink text-primary fw-bold">${this.numFmt(targetSpeed)}</td>
                                        <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblAvgSpeedNet">${this.numFmt(avgSpeedNet)}</td>
                                        <td class="td-kpi-pink">M/Min</td>
                                        <td class="td-kpi-pink fw-bold" id="lblAvgSpeedNetPct">${targetSpeed > 0 ? Math.round((avgSpeedNet / targetSpeed) * 100) : 0}%</td>
                                        <td class="td-kpi-pink fw-bold ${avgSpeedNet >= targetSpeed ? 'text-success' : 'text-danger'}" id="lblAvgSpeedNetScore">${avgSpeedNet >= targetSpeed ? 3 : 1}</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">Speed เฉลี่ยไม่หักเวลาสูญเสีย</td>
                                        <td class="td-kpi-pink text-primary fw-bold">${this.numFmt(targetSpeed)}</td>
                                        <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblAvgSpeedGross">${this.numFmt(avgSpeedGross)}</td>
                                        <td class="td-kpi-pink">M/Min</td>
                                        <td class="td-kpi-pink fw-bold" id="lblAvgSpeedGrossPct">${targetSpeed > 0 ? Math.round((avgSpeedGross / targetSpeed) * 100) : 0}%</td>
                                        <td class="td-kpi-pink fw-bold ${avgSpeedGross >= targetSpeed ? 'text-success' : 'text-danger'}" id="lblAvgSpeedGrossScore">${avgSpeedGross >= targetSpeed ? 3 : 1}</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">ขาดจำนวน</td>
                                        <td class="td-kpi-pink text-primary fw-bold">15%</td>
                                        <td class="td-kpi-pink fw-bold text-danger" colspan="3"><input type="number" min="0" name="shortage_orders" id="inpShortageOrders" class="table-input-cell fw-bold text-danger" value="${shortageOrders}" style="width: 60px;"></td>
                                        <td class="td-kpi-pink">Order</td>
                                        <td class="td-kpi-pink fw-bold" id="lblShortagePct">${shortagePct}%</td>
                                        <td class="td-kpi-pink fw-bold ${shortageScore >= 3 ? 'text-success' : 'text-danger'}" id="lblShortageScore">${shortageScore}</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">เวลาเดินทั้งหมด</td>
                                        <td class="td-kpi-pink text-primary fw-bold">${targetShiftTotalTime}</td>
                                        <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblActualShiftDuration">${actualShiftDuration}</td>
                                        <td class="td-kpi-pink">นาที</td>
                                        <td class="td-kpi-pink fw-bold" id="lblShiftTotalPct">${shiftTotalPct}%</td>
                                        <td class="td-kpi-pink fw-bold text-success" id="lblShiftTotalScore">${actualShiftDuration >= 700 ? 3 : 1}</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start">เวลาเดินงานจริง</td>
                                        <td class="td-kpi-pink text-primary fw-bold">${targetTimeRun}</td>
                                        <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblRunningTimeMin">${runningTimeMin}</td>
                                        <td class="td-kpi-pink">นาที</td>
                                        <td class="td-kpi-pink fw-bold" id="lblRunningPct">${runningPct}%</td>
                                        <td class="td-kpi-pink fw-bold ${runningTimeMin >= targetTimeRun ? 'text-success' : 'text-danger'}" id="lblRunningScore">${runningTimeMin >= targetTimeRun ? 3 : 1}</td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-pink text-start fw-bold">เวลาสูญเสียรวม</td>
                                        <td class="td-kpi-pink text-primary fw-bold" id="lblTargetLossPctTop">${this.numFmt(totalTargetLossPct, 2)}%</td>
                                        <td class="td-kpi-pink fw-bold text-danger fs-6" colspan="3" id="lblTotalLossMinutesTop">${totalLossMinutes}</td>
                                        <td class="td-kpi-pink">นาที</td>
                                        <td class="td-kpi-pink fw-bold text-danger" id="lblLossTotalPctTop">${this.numFmt(lossTotalPct, 2)}%</td>
                                        <td class="td-kpi-pink fw-bold ${lossTotalPct <= totalTargetLossPct ? 'text-success' : 'text-danger'}" id="lblLossTotalScoreTop">${lossTotalPct <= totalTargetLossPct ? 3 : 1}</td>
                                    </tr>

                                    ${Object.entries(lossData).map(([deptName, loss]) => {
                                        const deptMin = parseInt(loss.minutes || 0, 10);
                                        const deptOcc = parseInt(loss.occurrences || 0, 10);
                                        const deptTgt = parseFloat(loss.target_pct || 0);
                                        const deptSco = parseInt(loss.score ?? 3, 10);
                                        const deptActualPct = actualShiftDuration > 0 ? ((deptMin / actualShiftDuration) * 100).toFixed(2) : '0.00';
                                        return `
                                        <tr class="row-loss-dept" data-dept="${this.esc(deptName)}">
                                            <td class="td-kpi-pink text-start fw-semibold">${this.esc(deptName)}</td>
                                            <td class="td-kpi-pink text-primary fs-8">
                                                <input type="number" step="0.01" min="0" class="table-input-cell inp-dept-tgt text-primary" data-dept="${this.esc(deptName)}" value="${deptTgt.toFixed(2)}" style="width: 48px;">%
                                            </td>
                                            <td class="td-kpi-pink fs-8">
                                                <input type="number" min="0" class="table-input-cell inp-dept-occ" data-dept="${this.esc(deptName)}" value="${deptOcc > 0 ? deptOcc : ''}" placeholder="-" style="width: 42px;">
                                            </td>
                                            <td class="td-kpi-pink fs-8">ครั้ง</td>
                                            <td class="td-kpi-pink fs-8">
                                                <input type="number" min="0" class="table-input-cell inp-dept-min ${deptMin > 0 ? 'text-danger fw-bold' : ''}" data-dept="${this.esc(deptName)}" value="${deptMin > 0 ? deptMin : ''}" placeholder="-" style="width: 42px;">
                                            </td>
                                            <td class="td-kpi-pink fs-8">นาที</td>
                                            <td class="td-kpi-pink fs-8 lbl-dept-pct ${deptMin > 0 ? 'text-danger fw-bold' : 'text-muted'}">
                                                ${deptMin > 0 ? deptActualPct + '%' : '0.00%'}
                                            </td>
                                            <td class="td-kpi-pink">
                                                <select class="table-input-cell sel-dept-sco fw-bold ${deptSco >= 3 ? 'text-success' : 'text-danger'}" data-dept="${this.esc(deptName)}" style="width: 42px; cursor: pointer;">
                                                    <option value="3" ${deptSco === 3 ? 'selected' : ''}>3</option>
                                                    <option value="2" ${deptSco === 2 ? 'selected' : ''}>2</option>
                                                    <option value="1" ${deptSco === 1 ? 'selected' : ''}>1</option>
                                                </select>
                                            </td>
                                        </tr>`;
                                    }).join('')}

                                    <tr class="fw-bold">
                                        <td class="td-kpi-pink text-start">Total</td>
                                        <td class="td-kpi-pink text-primary" id="lblTotalTgtPct">${this.numFmt(totalTargetLossPct, 2)}%</td>
                                        <td class="td-kpi-pink" id="lblTotalLossOccurrences">${totalLossOccurrences}</td>
                                        <td class="td-kpi-pink">ครั้ง</td>
                                        <td class="td-kpi-pink text-danger fs-6" id="lblTotalLossMinutes">${totalLossMinutes}</td>
                                        <td class="td-kpi-pink">นาที</td>
                                        <td class="td-kpi-pink text-danger fs-8" id="lblTotalLossPct">${this.numFmt(lossTotalPct, 2)}%</td>
                                        <td class="td-kpi-pink text-dark fs-6" id="lblTotalScore">${totalScore}</td>
                                    </tr>

                                    <tr>
                                        <td class="td-kpi-red-head text-start bg-white text-dark" colspan="8">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold text-dark">*สรุปผลการเดินงาน*</span>
                                                <span class="fs-9 fw-normal text-muted no-capture-inline"><i class="fa-solid fa-pen me-1"></i>พิมพ์แก้ไขได้</span>
                                            </div>
                                            <textarea name="summary_notes" id="inpSummaryNotes" rows="2" class="form-control form-control-sm bg-white text-dark fs-8 border">${this.esc(report.summary_notes)}</textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="td-kpi-red-head text-start bg-white text-dark" colspan="8">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold text-dark">*หมายเหตุ*</span>
                                                <span class="fs-9 fw-normal text-muted no-capture-inline"><i class="fa-solid fa-pen me-1"></i>พิมพ์แก้ไขได้</span>
                                            </div>
                                            <textarea name="remarks" id="inpRemarks" rows="2" class="form-control form-control-sm bg-white text-dark fs-8 border">${this.esc(report.remarks)}</textarea>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <!-- Section 4: รายการที่ต้องการ (แยกตามลอน) -->
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
                                <tr>
                                    <td class="td-flute-yellow text-start">จำนวนออเดอร์แยกลอน</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-yellow">${fs[fk].orders}</td>`).join('')}
                                    <td class="td-flute-green">${totFluteOrders}</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-yellow text-start">จำนวนเมตรแยกลอน</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-yellow">${this.numFmt(fs[fk].meters)}</td>`).join('')}
                                    <td class="td-flute-green">${this.numFmt(totFluteMeters)}</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-yellow text-start">เวลาผลิตเป็นนาที</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-yellow">${Math.round(fs[fk].seconds / 60)}</td>`).join('')}
                                    <td class="td-flute-green">${totFluteMin}</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-yellow text-start">เวลาผลิตเป็นชั่วโมง</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-yellow">${Math.round((fs[fk].seconds / 3600) * 10) / 10}</td>`).join('')}
                                    <td class="td-flute-green">${totFluteHours}</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-yellow text-start">น้ำหนักทั้งหมดแยกลอน</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-yellow">${this.numFmt(fs[fk].weight)}</td>`).join('')}
                                    <td class="td-flute-green">${this.numFmt(totFluteWeight)}</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-pink text-start">จำนวนออเดอร์ที่บวกเพิ่ม</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-pink">${fa[fk].add_orders}</td>`).join('')}
                                    <td class="td-flute-green">${totAddOrders}</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-pink text-start">จำนวนแผ่นเล็กที่บวกเพิ่ม</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-pink">${fa[fk].add_small}</td>`).join('')}
                                    <td class="td-flute-green">${totAddSmall}</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-pink text-start">น้ำหนักที่บวกเพิ่ม</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-pink">${this.numFmt(fa[fk].add_weight)}</td>`).join('')}
                                    <td class="td-flute-green">${this.numFmt(totAddWeight)}</td>
                                </tr>
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
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-pink">${totFluteMeters > 0 ? Math.round((fs[fk].meters / totFluteMeters) * 100) : 0}%</td>`).join('')}
                                    <td class="td-flute-green">100%</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-pink text-start">% จำนวนออเดอร์แยกลอน</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-pink">${totFluteOrders > 0 ? Math.round((fs[fk].orders / totFluteOrders) * 100) : 0}%</td>`).join('')}
                                    <td class="td-flute-green">100%</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-pink text-start">% น้ำหนักทั้งหมดแยกลอน</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => `<td class="td-flute-pink">${totFluteWeight > 0 ? Math.round((fs[fk].weight / totFluteWeight) * 100) : 0}%</td>`).join('')}
                                    <td class="td-flute-green">100%</td>
                                </tr>
                                <tr>
                                    <td class="td-flute-pink text-start">% จำนวนออเดอร์ที่บวกเพิ่ม</td>
                                    ${['B','C','BC','A','AB','E'].map(fk => {
                                        const ord = fs[fk].orders;
                                        const addO = fa[fk].add_orders;
                                        return `<td class="td-flute-pink">${ord > 0 ? Math.round((addO / ord) * 100) + '%' : '-'}</td>`;
                                    }).join('')}
                                    <td class="td-flute-green">${totFluteOrders > 0 ? Math.round((totAddOrders / totFluteOrders) * 100) : 0}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="col-lg-6">
                    <!-- Section 2: ตารางของเสียแยก Station -->
                    <div class="card card-theme shadow-sm border-0 mb-2">
                        <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1f4e79 0%, #2e75b6 100%);">
                            <span class="fw-bold text-white fs-7">
                                <i class="fa-solid fa-trash-can me-1 text-warning"></i> ของเสียแยก Station
                            </span>
                            <span class="badge bg-white text-dark fs-8 fw-semibold">
                                น้ำหนักผลิตรวม: ${this.numFmt(totFluteWeight)} KG
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
                                    ${defaultStations.map(st => {
                                        const kg = stationWasteKg[st] || 0;
                                        const prodPct = totFluteWeight > 0 ? Math.round((kg / totFluteWeight) * 10000) / 100 : 0;
                                        const targetPct = parseFloat(stationWasteTargets[st]?.target_pct ?? stationWasteTargets[st]?.target_kg ?? 0);
                                        const targetWeight = (totFluteWeight > 0 && targetPct > 0) ? Math.round(((targetPct / 100) * totFluteWeight) * 10) / 10 : 0;
                                        let actualBg = '#f8f9fa', actualColor = '#495057';
                                        if (targetPct > 0) {
                                            if (prodPct <= targetPct) { actualBg = '#d4edda'; actualColor = '#155724'; }
                                            else { actualBg = '#f8d7da'; actualColor = '#721c24'; }
                                        }
                                        return `
                                        <tr class="row-station-waste" data-station="${st}" data-target="${targetPct}">
                                            <td class="td-station-name fw-bold" style="background: #fdf2e9; color: #b94a00; font-size: 0.85rem; vertical-align: middle;">${st}</td>
                                            <td class="td-station-target text-center fw-bold" style="background: #eafaf1; color: #1a6b3c; font-size: 0.85rem; vertical-align: middle;">
                                                ${targetPct > 0 ? targetPct.toFixed(2) + '%' : '-'}
                                            </td>
                                            <td class="td-station-target-weight text-center fw-bold" style="background: #eafaf1; color: #1a6b3c; font-size: 0.85rem; vertical-align: middle;">
                                                ${targetWeight > 0 ? this.numFmt(targetWeight, 1) : '-'}
                                            </td>
                                            <td class="td-station-input p-0" style="background: #ffffff; vertical-align: middle;">
                                                <input type="number" step="any" min="0"
                                                       class="form-control form-control-sm table-input-cell text-center fw-bold inp-station-waste-kg"
                                                       data-station="${st}"
                                                       value="${kg > 0 ? kg : ''}"
                                                       placeholder="0"
                                                       style="font-size: 0.9rem; height: 36px;">
                                            </td>
                                            <td class="td-station-prod fw-bold lbl-station-prod" data-station="${st}" data-target="${targetPct}" style="background: ${actualBg}; color: ${actualColor}; vertical-align: middle; font-size: 0.85rem;">
                                                ${prodPct}%
                                            </td>
                                        </tr>`;
                                    }).join('')}
                                    <tr class="tr-station-waste-total fw-bold" style="background: #d9e1f2; border-top: 2px solid #1f4e79;">
                                        <td class="text-center text-dark py-2" style="font-size: 0.85rem;">รวมทั้งหมด</td>
                                        <td class="text-center py-2" style="color: #1a6b3c; font-size: 0.85rem;" id="lblStationTargetTotal">
                                            ${totalTargetPctSum > 0 ? totalTargetPctSum.toFixed(2) + '%' : '-'}
                                        </td>
                                        <td class="text-center py-2" style="color: #1a6b3c; font-size: 0.85rem;" id="lblStationTargetWeightTotal">
                                            ${totalTargetWeightSum > 0 ? this.numFmt(totalTargetWeightSum, 1) : '-'}
                                        </td>
                                        <td class="text-danger fs-6 py-2" id="lblStationWasteTotalKg">
                                            ${totalStationWasteKg > 0 ? this.numFmt(totalStationWasteKg, 1) : '0'}
                                        </td>
                                        <td class="fs-7 py-2" id="lblStationWasteTotalProdPct" data-target="${totalTargetPctSum}" style="background: ${totalActualBg}; color: ${totalActualColor};">
                                            ${(totFluteWeight > 0 && totalStationWasteKg > 0) ? totalActualProdPct + '%' : '0%'}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Section 5: Meter Range, Trim and Wo Prefix Tables -->
                    <div class="row g-1">
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
                                    ${meterRowsHtml}
                                    <tr class="tr-gsm-total fs-8">
                                        <td>รวม</td>
                                        <td>${totRangeOrders}</td>
                                        <td>${this.numFmt(totRangeMeters)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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
                                    ${trimRowsHtml}
                                    <tr class="tr-gsm-total fs-8">
                                        <td>รวม</td>
                                        <td>${totTrimOrders}</td>
                                        <td>${this.numFmt(totTrimMeters)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-4">
                            <table class="table-bhs text-center">
                                <thead>
                                    <tr>
                                        <th class="th-range-yellow">รายการ</th>
                                        <th class="th-range-yellow">จำนวน<br>เมตร</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${woRowsHtml}
                                    <tr class="tr-gsm-total fs-8">
                                        <td>รวม</td>
                                        <td>${this.numFmt(totWoMeters)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Section 6: 4 Charts 2x2 -->
                    <div class="row g-2 mt-2">
                        <div class="col-6"><div class="border rounded p-2 bg-white shadow-sm" style="height: 260px;"><canvas id="chartLossTime"></canvas></div></div>
                        <div class="col-6"><div class="border rounded p-2 bg-white shadow-sm" style="height: 260px;"><canvas id="chartFluteWeight"></canvas></div></div>
                        <div class="col-6"><div class="border rounded p-2 bg-white shadow-sm" style="height: 260px;"><canvas id="chartStationWasteBar"></canvas></div></div>
                        <div class="col-6"><div class="border rounded p-2 bg-white shadow-sm" style="height: 260px;"><canvas id="chartStationWasteDonut"></canvas></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RAW DATA INSPECTION TABLE (76 Columns A-BX) -->
        <div class="card card-theme shadow-sm border-0 mt-4 no-capture">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-bold fs-6 text-dark">
                        <i class="fa-solid fa-table-cells text-orange me-2"></i> ข้อมูลเครื่องจักรรายออเดอร์ (Raw Machine Records)
                    </span>
                    <span class="badge bg-secondary ms-2">${filteredRecords.length} แถว</span>
                </div>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#rawTableCollapse">
                    <i class="fa-solid fa-chevron-down"></i> ย่อ/ขยาย
                </button>
            </div>
            <div class="collapse show" id="rawTableCollapse">
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 560px; overflow-y: auto;">
                        <table class="table table-sm table-bordered table-striped table-hover mb-0 text-nowrap fs-9 text-end align-middle">
                            <thead class="table-dark sticky-top text-center" style="font-size: 0.76rem; z-index: 5;">
                                <tr>
                                    <th>A<br>ลำดับที่</th><th>B<br>กะ</th><th>C<br>ออร์เดอร์แผน</th><th>D<br>ออร์เดอร์</th><th>E<br>Wo. No.</th><th>F<br>ลอน</th>
                                    <th>G<br>M1Weight</th><th>H<br>M2Weight</th><th>I<br>M3Weight</th><th>J<br>M4Weight</th><th>K<br>M5Weight</th>
                                    <th>L<br>M1</th><th>M<br>M2</th><th>N<br>M3</th><th>O<br>M4</th><th>P<br>M5</th>
                                    <th>Q<br>หน้ากว้าง</th><th>R<br>ยาว/แผ่น</th><th>S<br>นน/แผ่น</th><th>T<br>แผ่นใหญ่ดี</th><th>U<br>แผ่นใหญ่เสีย</th>
                                    <th>V<br>T</th><th>W<br>S</th><th>X<br>F1</th><th>Y<br>F2</th><th>Z<br>F3</th><th>AA<br>F4</th><th>AB<br>F5</th><th>AC<br>F6</th>
                                    <th>AD<br>แผ่นเล็กรวม</th><th>AE<br>แผ่นเล็กดี</th>
                                    <th>AF<br>เวลาเริ่ม</th><th>AG<br>เวลาสิ้นสุด</th><th>AH<br>เวลาผลิต(วิ)</th><th>AI<br>หยุด(ครั้ง)</th><th>AJ<br>หยุด(วิ)</th><th>AK<br>Stackcount</th><th>AL<br>Speed</th>
                                    <th>AM<br>นน.รวม(กก)</th><th>AN<br>พื้นที่(ตรม)</th><th>AO<br>ยาว(เมตร)</th><th>AP<br>ปริมาตร(ตรม)</th>
                                    <th>AQ<br>ออเดอร์บวก</th><th>AR<br>แผ่นเล็กบวก</th><th>AS<br>นน.บวก</th><th>AT<br>เวลาผลิต(นาที)</th>
                                    <th>AU<br>M1</th><th>AV<br>M2</th><th>AW<br>M3</th><th>AX<br>M4</th><th>AY<br>M5</th>
                                    <th>AZ<br>MM1</th><th>BA<br>MM2</th><th>BB<br>MM3</th><th>BC<br>MM4</th><th>BD<br>MM5</th>
                                    <th>BE<br>MMM1</th><th>BF<br>MMM2</th><th>BG<br>MMM3</th><th>BH<br>MMM4</th><th>BI<br>MMM5</th>
                                    <th>BJ<br>light C</th><th>BK<br>Medium C</th><th>BL<br>Heavy C</th>
                                    <th>BM<br>light B</th><th>BN<br>Medium B</th><th>BO<br>Heavy B</th>
                                    <th>BP<br>light DF</th><th>BQ<br>Medium DF</th><th>BR<br>Heavy DF</th>
                                    <th>BS<br>เมตร</th><th>BT<br>Cutleng</th><th>BU<br>Trim/ข้าง</th><th>BV<br>Trim2</th><th>BW<br>Speed</th><th>BX<br>PDR</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${filteredRecords.map(rec => {
                                    const prodSec = parseInt(rec.production_seconds || 0, 10);
                                    const g1 = parseInt(rec.m1_gsm || BhsEngine.extractGsm(rec.m1_paper), 10);
                                    const g2 = parseInt(rec.m2_gsm || BhsEngine.extractGsm(rec.m2_paper), 10);
                                    const g3 = parseInt(rec.m3_gsm || BhsEngine.extractGsm(rec.m3_paper), 10);
                                    const g4 = parseInt(rec.m4_gsm || BhsEngine.extractGsm(rec.m4_paper), 10);
                                    const g5 = parseInt(rec.m5_gsm || BhsEngine.extractGsm(rec.m5_paper), 10);

                                    const mmm1 = rec.m1_class || BhsEngine.classifyGsm(g1);
                                    const mmm2 = rec.m2_class || BhsEngine.classifyGsm(g2);
                                    const mmm3 = rec.m3_class || BhsEngine.classifyGsm(g3);
                                    const mmm4 = rec.m4_class || BhsEngine.classifyGsm(g4);
                                    const mmm5 = rec.m5_class || BhsEngine.classifyGsm(g5);

                                    const mLen = Number(rec.total_length_m) || 0;
                                    const lightC = mmm1 === 'light' ? this.numFmt(mLen, 1) : '';
                                    const medC   = mmm1 === 'Medium' ? this.numFmt(mLen, 1) : '';
                                    const heavyC = mmm1 === 'Heavy' ? this.numFmt(mLen, 1) : '';
                                    const lightB = mmm3 === 'light' ? this.numFmt(mLen, 1) : '';
                                    const medB   = mmm3 === 'Medium' ? this.numFmt(mLen, 1) : '';
                                    const heavyB = mmm3 === 'Heavy' ? this.numFmt(mLen, 1) : '';
                                    const lightDF = mmm5 === 'light' ? this.numFmt(mLen, 1) : '';
                                    const medDF   = mmm5 === 'Medium' ? this.numFmt(mLen, 1) : '';
                                    const heavyDF = mmm5 === 'Heavy' ? this.numFmt(mLen, 1) : '';

                                    const bMeter   = rec.meter_bucket || BhsEngine.getMeterBucket(mLen);
                                    const bCutleng = rec.cutleng_bucket || BhsEngine.getCutlengBucket(rec.sheet_length_mm);
                                    const trimSide = Number(rec.trim_side_mm) || BhsEngine.calculateTrimSide(rec.paper_width_mm, rec.knife_t, rec.knife_s);
                                    const bTrim    = rec.trim_bucket || BhsEngine.getTrimBucket(trimSide);
                                    const bSpeed   = rec.speed_bucket || BhsEngine.getSpeedBucket(rec.avg_speed);
                                    const pdrCode  = rec.wo_prefix || String(rec.wo_no || '').substring(0, 3).toUpperCase();

                                    const addOrder = rec.add_order !== undefined ? parseInt(rec.add_order, 10) : (Number(rec.waste_big_sheets) >= 1 ? 1 : 0);
                                    const addSmall = rec.add_small_sheets !== undefined ? parseInt(rec.add_small_sheets, 10) : (Number(rec.waste_big_sheets) * Number(rec.knife_t));
                                    const addWt    = rec.add_weight !== undefined ? Number(rec.add_weight) : (addSmall * Number(rec.sheet_weight_kg));
                                    const prodMin  = Number(rec.production_minutes) || (prodSec > 0 ? prodSec / 60.0 : 0);

                                    return `
                                    <tr>
                                        <td class="text-center fw-bold">${rec.seq_no}</td>
                                        <td class="text-center"><span class="badge bg-info text-dark">${this.esc(rec.shift)}</span></td>
                                        <td class="text-center">${this.esc(rec.plan_order)}</td>
                                        <td class="text-center"><code>${this.esc(rec.order_no)}</code></td>
                                        <td class="text-start"><strong>${this.esc(rec.wo_no)}</strong></td>
                                        <td class="text-center"><span class="badge bg-warning text-dark">${this.esc(rec.flute)}</span></td>
                                        <td>${this.numFmt(rec.m1_weight, 3)}</td>
                                        <td>${this.numFmt(rec.m2_weight, 3)}</td>
                                        <td>${this.numFmt(rec.m3_weight, 3)}</td>
                                        <td>${this.numFmt(rec.m4_weight, 3)}</td>
                                        <td>${this.numFmt(rec.m5_weight, 3)}</td>
                                        <td class="text-center">${this.esc(rec.m1_paper)}</td>
                                        <td class="text-center">${this.esc(rec.m2_paper)}</td>
                                        <td class="text-center">${this.esc(rec.m3_paper)}</td>
                                        <td class="text-center">${this.esc(rec.m4_paper)}</td>
                                        <td class="text-center">${this.esc(rec.m5_paper)}</td>
                                        <td>${this.numFmt(rec.paper_width_mm)}</td>
                                        <td>${this.numFmt(rec.sheet_length_mm)}</td>
                                        <td>${this.numFmt(rec.sheet_weight_kg, 5)}</td>
                                        <td>${this.numFmt(rec.good_big_sheets)}</td>
                                        <td class="${Number(rec.waste_big_sheets) > 0 ? 'text-danger fw-bold' : ''}">${this.numFmt(rec.waste_big_sheets)}</td>
                                        <td class="text-center">${rec.knife_t}</td>
                                        <td>${this.numFmt(rec.knife_s)}</td>
                                        <td>${this.numFmt(rec.f1)}</td><td>${this.numFmt(rec.f2)}</td><td>${this.numFmt(rec.f3)}</td><td>${this.numFmt(rec.f4)}</td><td>${this.numFmt(rec.f5)}</td><td>${this.numFmt(rec.f6)}</td>
                                        <td>${this.numFmt(rec.total_small_sheets)}</td>
                                        <td>${this.numFmt(rec.good_small_sheets)}</td>
                                        <td class="text-center">${rec.start_time ? String(rec.start_time).substring(11, 19) : '-'}</td>
                                        <td class="text-center">${rec.end_time ? String(rec.end_time).substring(11, 19) : '-'}</td>
                                        <td>${this.numFmt(prodSec)}</td>
                                        <td>${rec.stop_count}</td>
                                        <td class="${Number(rec.stop_time_sec) > 0 ? 'text-danger fw-bold' : ''}">${this.numFmt(rec.stop_time_sec)}</td>
                                        <td>${rec.stack_count}</td>
                                        <td class="text-primary fw-bold">${this.numFmt(rec.avg_speed, 1)}</td>
                                        <td>${this.numFmt(rec.total_weight_kg, 2)}</td>
                                        <td>${this.numFmt(rec.total_area_sqm, 1)}</td>
                                        <td class="fw-bold text-success">${this.numFmt(mLen, 1)}</td>
                                        <td>${this.numFmt(rec.total_volume_sqm, 3)}</td>
                                        <td class="text-center fw-bold ${addOrder > 0 ? 'text-danger' : ''}">${addOrder}</td>
                                        <td class="text-center fw-bold ${addSmall > 0 ? 'text-danger' : ''}">${this.numFmt(addSmall)}</td>
                                        <td class="text-end ${addWt > 0 ? 'text-danger fw-bold' : ''}">${this.numFmt(addWt, 3)}</td>
                                        <td class="fw-bold">${this.numFmt(prodMin, 2)}</td>
                                        <td class="text-center">${g1 || ''}</td><td class="text-center">${g2 || ''}</td><td class="text-center">${g3 || ''}</td><td class="text-center">${g4 || ''}</td><td class="text-center">${g5 || ''}</td>
                                        <td class="text-center">${g1 || ''}</td><td class="text-center">${g2 || ''}</td><td class="text-center">${g3 || ''}</td><td class="text-center">${g4 || ''}</td><td class="text-center">${g5 || ''}</td>
                                        <td class="text-center"><span class="badge bg-light text-dark border">${mmm1}</span></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border">${mmm2}</span></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border">${mmm3}</span></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border">${mmm4}</span></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border">${mmm5}</span></td>
                                        <td>${lightC}</td><td>${medC}</td><td>${heavyC}</td>
                                        <td>${lightB}</td><td>${medB}</td><td>${heavyB}</td>
                                        <td>${lightDF}</td><td>${medDF}</td><td>${heavyDF}</td>
                                        <td class="text-center fw-bold">${bMeter}</td>
                                        <td class="text-center fw-bold">${bCutleng}</td>
                                        <td>${this.numFmt(trimSide, 1)}</td>
                                        <td class="text-center fw-bold">${bTrim}</td>
                                        <td class="text-center fw-bold">${bSpeed}</td>
                                        <td class="text-center"><span class="badge bg-primary">${this.esc(pdrCode)}</span></td>
                                    </tr>`;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: แก้ไขเวลาสูญเสีย / หมายเหตุ -->
        <div class="modal fade" id="modalEditLoss" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form id="modalEditLossForm" class="modal-content">
                    <div class="modal-header bg-theme-orange text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขเวลาสูญเสียรายแผนก & เวลาเริ่ม-จบกะ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold fs-7 text-secondary">เวลาเริ่มเดิน (24 ชม.)</label>
                                <input type="text" id="modShiftStart" class="form-control fw-bold text-center" value="${this.esc(shiftStartTime)}" placeholder="เช่น 08:00 หรือ 20:00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold fs-7 text-secondary">เวลาจบ/ตัดกะ (24 ชม.)</label>
                                <input type="text" id="modShiftEnd" class="form-control fw-bold text-center" value="${this.esc(shiftEndTime)}" placeholder="เช่น 16:00 หรือ 08:00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold fs-7 text-primary">เมตรเดินงานจริง (เมตร)</label>
                                <input type="number" step="any" min="0" id="modActualMeters" class="form-control fw-bold text-center text-primary" value="${totalMetersActual}" placeholder="เมตรเดินงานจริง">
                                <div class="form-text fs-9">ข้อมูลจากไฟล์: <strong>${this.numFmt(dataMetersActual)}</strong> เมตร</div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="fs-8 text-secondary fw-semibold"><i class="fa-solid fa-bolt text-orange me-1"></i> เลือกช่วงเวลาด่วน (24 ชม.):</span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-mod-time-preset" data-start="08:00" data-end="16:00">08:00 - 16:00</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-mod-time-preset" data-start="08:00" data-end="20:00">08:00 - 20:00</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-mod-time-preset" data-start="20:00" data-end="08:00">20:00 - 08:00</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-mod-time-preset" data-start="20:00" data-end="07:40">20:00 - 07:40</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-mod-time-preset" data-start="16:00" data-end="24:00">16:00 - 24:00</button>
                                </div>
                            </div>
                        </div>
                        <p class="text-muted fs-8 mb-0">คุณสามารถปรับเวลาเริ่มเดินและเวลาจบได้ที่นี่ หรือพิมพ์แก้ไขตัวเลขเวลาสูญเสียโดยตรงในตารางหน้าหลักได้เลย</p>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-orange btn-sm px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> บันทึกเวลาเดินกะ</button>
                    </div>
                </form>
            </div>
        </div>`;

        this.renderShell('dashboard', report.title, html);

        // Initialize Charts
        const chartPayload = {
            lossLabels: Object.keys(lossData),
            lossMinutes: Object.values(lossData).map(l => parseInt(l.minutes || 0, 10)),
            fluteLabels: ['ลอน B', 'ลอน C', 'ลอน BC', 'ลอน A', 'ลอน AB', 'ลอน E'],
            flutePcts: ['B','C','BC','A','AB','E'].map(fk => totFluteWeight > 0 ? Math.round((fs[fk].weight / totFluteWeight) * 1000) / 10 : 0),
            stationLabels: defaultStations,
            stationWasteKg: defaultStations.map(st => stationWasteKg[st] || 0),
            totalProdWeight: totFluteWeight
        };
        if (typeof window.initBhsCharts === 'function') {
            window.initBhsCharts(chartPayload);
        }

        // Bind Filter Form
        document.getElementById('filterReportForm')?.addEventListener('submit', e => {
            e.preventDefault();
            const s = document.getElementById('selFilterShift').value;
            const f = document.getElementById('selFilterFlute').value;
            const w = document.getElementById('selFilterWo').value;
            const q = new URLSearchParams({ id: reportId });
            if (s) q.set('shift', s);
            if (f) q.set('flute', f);
            if (w) q.set('wo_prefix', w);
            this.navigate(`#report_view?${q.toString()}`);
        });

        // Bind Realtime KPI & Station Waste Recalculation
        const kpiForm = document.getElementById('formInlineKpi');
        kpiForm?.addEventListener('input', () => window.recalculateTableKpi && window.recalculateTableKpi());
        kpiForm?.addEventListener('change', () => window.recalculateTableKpi && window.recalculateTableKpi());

        document.querySelectorAll('.inp-station-waste-kg').forEach(inp => {
            inp.addEventListener('input', () => window.recalculateStationWaste && window.recalculateStationWaste());
            inp.addEventListener('change', () => window.recalculateStationWaste && window.recalculateStationWaste());
        });

        // Bind Save Inline KPI Buttons
        document.querySelectorAll('.btn-save-action').forEach(btn => {
            btn.addEventListener('click', async () => {
                await this.saveReportKpiInline(report, btn);
            });
        });

        // Bind Modal Edit Shift Time
        document.querySelectorAll('.btn-mod-time-preset').forEach(btn => {
            btn.addEventListener('click', () => {
                const s = btn.getAttribute('data-start');
                const e = btn.getAttribute('data-end');
                if (s) document.getElementById('modShiftStart').value = s;
                if (e) document.getElementById('modShiftEnd').value = e;
            });
        });

        document.getElementById('modalEditLossForm')?.addEventListener('submit', async e => {
            e.preventDefault();
            document.getElementById('hiddenShiftStartTime').value = document.getElementById('modShiftStart').value.trim();
            document.getElementById('hiddenShiftEndTime').value = document.getElementById('modShiftEnd').value.trim();
            const modMeters = document.getElementById('modActualMeters');
            const inpActualMeters = document.getElementById('inpActualMeters');
            if (modMeters && inpActualMeters) {
                inpActualMeters.value = modMeters.value;
            }
            const modalEl = document.getElementById('modalEditLoss');
            bootstrap.Modal.getInstance(modalEl)?.hide();
            await this.saveReportKpiInline(report, document.getElementById('btnSaveInlineKpiTop'));
            this.renderReportView(params);
        });

        // Bind Google Sheet Sync
        const btnGs = document.getElementById('btnSyncGoogleSheet');
        btnGs?.addEventListener('click', async () => {
            const orig = btnGs.innerHTML;
            btnGs.disabled = true;
            btnGs.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> กำลังส่งไป Google Sheets...';
            const res = await BhsDB.syncReportToGoogleSheet(reportId);
            btnGs.disabled = false;
            btnGs.innerHTML = orig;
            alert(res.success ? '✅ ' + res.message : '⚠️ ' + res.message);
        });

        // Bind HD Image Capture
        this.bindHdImageButton();
    },

    async saveReportKpiInline(report, triggerBtn) {
        const origHtml = triggerBtn ? triggerBtn.innerHTML : '';
        if (triggerBtn) {
            triggerBtn.disabled = true;
            triggerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> กำลังบันทึก...';
        }
        const statusMsg = document.getElementById('saveStatusMsg');

        report.shift_start_time = document.getElementById('hiddenShiftStartTime')?.value || report.shift_start_time;
        report.shift_end_time   = document.getElementById('hiddenShiftEndTime')?.value || report.shift_end_time;
        report.actual_time_total= BhsEngine.calculateShiftDurationMinutes(report.shift_start_time, report.shift_end_time);

        report.order_start      = parseInt(document.getElementById('inpOrderStart')?.value || 0, 10);
        report.order_finit      = parseInt(document.getElementById('inpOrderFinit')?.value || 0, 10);
        report.order_plan_total = parseInt(document.getElementById('inpOrderPlanTotal')?.value || 0, 10);
        report.actual_meters    = parseFloat(document.getElementById('inpActualMeters')?.value || 0);
        report.inserted_meters  = parseFloat(document.getElementById('inpInsertedMeters')?.value || 0);
        report.pending_meters   = parseFloat(document.getElementById('inpPendingMeters')?.value || 0);
        report.shortage_orders  = parseInt(document.getElementById('inpShortageOrders')?.value || 0, 10);
        report.summary_notes    = document.getElementById('inpSummaryNotes')?.value || '';
        report.remarks          = document.getElementById('inpRemarks')?.value || '';

        const newLossDetails = {};
        document.querySelectorAll('.row-loss-dept').forEach(row => {
            const dept = row.getAttribute('data-dept');
            if (!dept) return;
            newLossDetails[dept] = {
                target_pct:  parseFloat(row.querySelector('.inp-dept-tgt')?.value || 0),
                occurrences: parseInt(row.querySelector('.inp-dept-occ')?.value || 0, 10),
                minutes:     parseInt(row.querySelector('.inp-dept-min')?.value || 0, 10),
                score:       parseInt(row.querySelector('.sel-dept-sco')?.value || 3, 10)
            };
        });
        report.loss_details = JSON.stringify(newLossDetails);

        const newStationWaste = {};
        document.querySelectorAll('.inp-station-waste-kg').forEach(inp => {
            const st = inp.getAttribute('data-station');
            if (st) newStationWaste[st] = { kg: parseFloat(inp.value || 0) };
        });
        report.station_waste = JSON.stringify(newStationWaste);

        await BhsDB.save();
        BhsDB.syncReportToGoogleSheet(report.id).catch(() => {});

        if (triggerBtn) {
            triggerBtn.disabled = false;
            triggerBtn.innerHTML = '<i class="fa-solid fa-check text-white me-1"></i> บันทึกแล้ว!';
            setTimeout(() => { triggerBtn.innerHTML = origHtml; }, 2500);
        }
        if (statusMsg) {
            statusMsg.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> บันทึกข้อมูลสำเร็จ!</span>';
            setTimeout(() => { statusMsg.innerHTML = ''; }, 3000);
        }
    },

    bindHdImageButton() {
        const btnSaveHd = document.getElementById('btnSaveHdImage');
        if (!btnSaveHd) return;
        btnSaveHd.addEventListener('click', () => {
            const reportArea = document.getElementById('hdReportCaptureArea');
            if (!reportArea || typeof html2canvas === 'undefined') return;
            const origHtml = btnSaveHd.innerHTML;
            btnSaveHd.disabled = true;
            btnSaveHd.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> กำลังบันทึกภาพ HD...';

            const reportTitle = document.getElementById('reportTitleHeader')?.textContent?.trim() || 'BHS_Shift_Report';
            const cleanFilename = reportTitle.replace(/[\/\s:?*"<>|]+/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.png';

            const hideElems = document.querySelectorAll('.no-capture');
            hideElems.forEach(el => el.classList.add('d-none'));
            reportArea.classList.add('is-capturing');

            html2canvas(reportArea, {
                scale: 3,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
                onclone: clonedDoc => {
                    const origCanvases = reportArea.querySelectorAll('canvas');
                    const clonedCanvases = clonedDoc.querySelectorAll('#hdReportCaptureArea canvas');
                    origCanvases.forEach((origCanvas, i) => {
                        const clonedCanvas = clonedCanvases[i];
                        if (clonedCanvas && origCanvas.width > 0 && origCanvas.height > 0) {
                            clonedCanvas.width = origCanvas.width;
                            clonedCanvas.height = origCanvas.height;
                            const ctx = clonedCanvas.getContext('2d');
                            if (ctx) { try { ctx.drawImage(origCanvas, 0, 0); } catch (e) {} }
                        }
                    });
                }
            }).then(canvas => {
                hideElems.forEach(el => el.classList.remove('d-none'));
                reportArea.classList.remove('is-capturing');
                const link = document.createElement('a');
                link.download = cleanFilename;
                link.href = canvas.toDataURL('image/png');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                btnSaveHd.disabled = false;
                btnSaveHd.innerHTML = '<i class="fa-solid fa-check text-success me-2"></i> บันทึกภาพสำเร็จ!';
                setTimeout(() => { btnSaveHd.innerHTML = origHtml; }, 2500);
            }).catch(err => {
                hideElems.forEach(el => el.classList.remove('d-none'));
                reportArea.classList.remove('is-capturing');
                btnSaveHd.disabled = false;
                btnSaveHd.innerHTML = origHtml;
                alert('เกิดข้อผิดพลาดในการบันทึกภาพ: ' + err.message);
            });
        });
    },

    // =========================================================================
    // 5. ADMIN TARGETS VIEW
    // =========================================================================
    renderAdminTargets(params) {
        const selectedReportId = parseInt(params.get('report_id') || '0', 10);
        const currentReport = selectedReportId > 0
            ? (BhsDB.state.bhs_shift_reports || []).find(r => Number(r.id) === selectedReportId)
            : null;

        let tpl = (BhsDB.state.bhs_target_templates || [])[0];
        if (!tpl) {
            tpl = {
                id: 1,
                template_name: 'default',
                machine_name: 'BHS',
                target_orders: 120,
                target_meters: 68056,
                target_speed: 100,
                target_avg_meters: 500,
                target_time_total: 720,
                target_time_run: 675,
                target_loss_pct: 10,
                target_shortage_pct: 15,
                loss_departments: JSON.stringify(BhsEngine.getDefaultLossDepartmentsArray()),
                station_waste_targets: '{}'
            };
            BhsDB.state.bhs_target_templates = [tpl];
        }

        const src = currentReport || tpl;
        const targetOrders    = parseInt(src.target_orders || 120, 10);
        const targetMeters    = parseFloat(src.target_meters || 68056);
        const targetSpeed     = parseFloat(src.target_speed || 100);
        const targetAvgMeters = parseFloat(src.target_avg_meters || 500);
        const targetTimeTotal = parseInt(src.target_time_total || 720, 10);
        const targetTimeRun   = parseInt(src.target_time_run || 675, 10);
        const targetLossPct   = parseFloat(src.target_loss_pct || 10);
        const targetShortage  = parseFloat(tpl.target_shortage_pct || 15);

        let departmentsList = [];
        if (currentReport && currentReport.loss_details) {
            try {
                const ld = typeof currentReport.loss_details === 'string' ? JSON.parse(currentReport.loss_details) : currentReport.loss_details;
                Object.entries(ld).forEach(([dName, dInfo]) => {
                    departmentsList.push({ name: dName, target_pct: parseFloat(dInfo.target_pct || 0), is_active: 1 });
                });
            } catch (e) {}
        } else if (tpl.loss_departments) {
            try {
                departmentsList = typeof tpl.loss_departments === 'string' ? JSON.parse(tpl.loss_departments) : tpl.loss_departments;
            } catch (e) {}
        }
        if (!Array.isArray(departmentsList) || departmentsList.length === 0) {
            departmentsList = BhsEngine.getDefaultLossDepartmentsArray();
        }

        let stationWasteTargets = {};
        try {
            stationWasteTargets = typeof tpl.station_waste_targets === 'string' ? JSON.parse(tpl.station_waste_targets) : (tpl.station_waste_targets || {});
        } catch (e) {}
        const defaultStations = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];

        const html = `
        <div class="row align-items-center mb-3">
            <div class="col-lg-6">
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-sliders text-orange me-2"></i> จัดการเป้าหมาย & หัวข้อตารางรายงาน
                </h4>
                <div class="text-muted fs-7">ปรับแต่ง Target ตัวเลขในตาราง, เพิ่ม/ลบ และเปลี่ยนชื่อหัวข้อแผนกได้อย่างอิสระ</div>
            </div>
            <div class="col-lg-6 text-lg-end mt-2 mt-lg-0 d-flex flex-wrap gap-2 justify-content-lg-end">
                ${selectedReportId > 0 ? `<a href="#report_view?id=${selectedReportId}" class="btn btn-outline-dark rounded-pill px-3 shadow-sm btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> กลับไปหน้ารายงาน</a>` : ''}
                <button type="button" id="btnResetFactoryDefaults" class="btn btn-outline-danger rounded-pill px-3 shadow-sm btn-sm">
                    <i class="fa-solid fa-rotate-left me-1"></i> คืนค่ามาตรฐานโรงงาน
                </button>
            </div>
        </div>

        <form id="formAdminTargets">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card card-theme shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-bullseye text-orange me-2"></i> 1. ค่าเป้าหมายหลักในตาราง (KPI Targets)</h6>
                        </div>
                        <div class="card-body p-3">
                            <table class="table table-bordered table-sm fs-8 align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr><th class="text-start">หัวข้อเป้าหมาย</th><th>ค่าเป้าหมาย (Target)</th><th>หน่วย</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="fw-semibold">จำนวนออเดอร์ที่เดินทั้งหมด+ซ่อม</td><td><input type="number" name="target_orders" class="form-control form-control-sm text-center fw-bold text-primary" value="${targetOrders}" required></td><td class="text-center">Order</td></tr>
                                    <tr><td class="fw-semibold">ผลิตได้ (ความยาวทั้งหมด)</td><td><input type="number" step="0.01" name="target_meters" class="form-control form-control-sm text-center fw-bold text-primary" value="${targetMeters}" required></td><td class="text-center">เมตร</td></tr>
                                    <tr><td class="fw-semibold">Speed เฉลี่ยเป้าหมาย</td><td><input type="number" step="0.1" name="target_speed" class="form-control form-control-sm text-center fw-bold text-primary" value="${targetSpeed}" required></td><td class="text-center">M/Min</td></tr>
                                    <tr><td class="fw-semibold">เมตรเฉลี่ย/order</td><td><input type="number" step="0.1" name="target_avg_meters" class="form-control form-control-sm text-center fw-bold text-primary" value="${targetAvgMeters}" required></td><td class="text-center">เมตร</td></tr>
                                    <tr><td class="fw-semibold">เวลาเดินทั้งหมด</td><td><input type="number" name="target_time_total" class="form-control form-control-sm text-center fw-bold text-primary" value="${targetTimeTotal}" required></td><td class="text-center">นาที</td></tr>
                                    <tr><td class="fw-semibold">เวลาเดินงานจริง</td><td><input type="number" name="target_time_run" class="form-control form-control-sm text-center fw-bold text-primary" value="${targetTimeRun}" required></td><td class="text-center">นาที</td></tr>
                                    <tr><td class="fw-semibold">ขาดจำนวนสูงสุด</td><td><input type="number" step="0.01" name="target_shortage_pct" class="form-control form-control-sm text-center fw-bold text-primary" value="${targetShortage}" required></td><td class="text-center">%</td></tr>
                                    <tr class="table-warning"><td class="fw-bold text-danger">เวลาสูญเสียรวม (Max Loss)</td><td><input type="number" step="0.01" name="target_loss_pct" class="form-control form-control-sm text-center fw-bold text-danger" value="${targetLossPct}" required></td><td class="text-center fw-bold text-danger">%</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card card-theme shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-ol text-orange me-2"></i> 2. หัวข้อ & เวลาสูญเสียรายแผนก</h6>
                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-bold" id="btnAddDepartmentRow">
                                <i class="fa-solid fa-plus me-1"></i> เพิ่มหัวข้อใหม่
                            </button>
                        </div>
                        <div class="card-body p-3">
                            <table class="table table-bordered table-sm fs-8 align-middle text-center mb-3">
                                <thead class="table-light">
                                    <tr><th>#</th><th class="text-start">ชื่อหัวข้อ / แผนก</th><th>Target (%)</th><th>สถานะ</th><th>ลบ</th></tr>
                                </thead>
                                <tbody id="deptTableBody">
                                    ${departmentsList.map((d, idx) => `
                                    <tr class="dept-row">
                                        <td class="text-muted fw-bold row-index">${idx + 1}</td>
                                        <td><input type="text" class="form-control form-control-sm fw-bold inp-dept-name" value="${this.esc(d.name)}" required></td>
                                        <td><input type="number" step="0.01" class="form-control form-control-sm text-center fw-semibold inp-dept-target" value="${Number(d.target_pct || 0).toFixed(2)}" required></td>
                                        <td><input type="checkbox" class="form-check-input chk-dept-active" ${Number(d.is_active ?? 1) ? 'checked' : ''}></td>
                                        <td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row"><i class="fa-solid fa-trash"></i></button></td>
                                    </tr>`).join('')}
                                </tbody>
                            </table>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="syncAllReports" checked>
                                <label class="form-check-label fs-8 text-danger fw-semibold" for="syncAllReports">
                                    อัปเดตค่าเป้าหมายนี้ไปยังรายงานทั้งหมดที่มีอยู่ในระบบด้วย
                                </label>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-orange px-4 py-2 rounded-pill fw-bold shadow-sm">
                                    <i class="fa-solid fa-save me-1"></i> บันทึกการตั้งค่าทั้งหมด (Save Settings)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card card-theme shadow-sm border-0">
                        <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #1f4e79 0%, #2e75b6 100%);">
                            <h6 class="fw-bold mb-0 text-white"><i class="fa-solid fa-trash-can text-warning me-2"></i> 3. เป้าหมายของเสียแยก Station (Target % per Station)</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                ${defaultStations.map(st => {
                                    const tPct = parseFloat(stationWasteTargets[st]?.target_pct ?? stationWasteTargets[st]?.target_kg ?? 0);
                                    return `
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label fs-8 fw-bold mb-1">${st}</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" min="0" max="100" class="form-control text-center fw-bold inp-station-target" data-station="${st}" value="${tPct > 0 ? tPct.toFixed(2) : ''}" placeholder="0.00">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>`;
                                }).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>`;

        this.renderShell('targets', 'จัดการเป้าหมาย & หัวข้อตาราง', html);

        const tbody = document.getElementById('deptTableBody');
        document.getElementById('btnAddDepartmentRow')?.addEventListener('click', () => {
            const count = tbody.querySelectorAll('.dept-row').length + 1;
            const tr = document.createElement('tr');
            tr.className = 'dept-row';
            tr.innerHTML = `
                <td class="text-muted fw-bold row-index">${count}</td>
                <td><input type="text" class="form-control form-control-sm fw-bold inp-dept-name" value="แผนกใหม่ ${count}" required></td>
                <td><input type="number" step="0.01" class="form-control form-control-sm text-center fw-semibold inp-dept-target" value="0.00" required></td>
                <td><input type="checkbox" class="form-check-input chk-dept-active" checked></td>
                <td><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row"><i class="fa-solid fa-trash"></i></button></td>`;
            tbody.appendChild(tr);
        });

        tbody?.addEventListener('click', e => {
            const del = e.target.closest('.btn-remove-row');
            if (del) {
                if (tbody.querySelectorAll('.dept-row').length <= 1) return alert('ต้องมีอย่างน้อย 1 แผนก');
                del.closest('.dept-row')?.remove();
            }
        });

        document.getElementById('btnResetFactoryDefaults')?.addEventListener('click', async () => {
            if (!confirm('ยืนยันคืนค่าเป้าหมายและหัวข้อเป็น 12 แผนกมาตรฐานโรงงาน?')) return;
            tpl.target_orders = 120;
            tpl.target_meters = 68056;
            tpl.target_speed = 100;
            tpl.target_avg_meters = 500;
            tpl.target_time_total = 720;
            tpl.target_time_run = 675;
            tpl.target_loss_pct = 10;
            tpl.target_shortage_pct = 15;
            tpl.loss_departments = JSON.stringify(BhsEngine.getDefaultLossDepartmentsArray());
            await BhsDB.save();
            this.setFlash('success', 'คืนค่ามาตรฐานโรงงานเรียบร้อยแล้ว');
            this.renderAdminTargets(params);
        });

        document.getElementById('formAdminTargets')?.addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            tpl.target_orders       = parseInt(fd.get('target_orders') || 120, 10);
            tpl.target_meters       = parseFloat(fd.get('target_meters') || 68056);
            tpl.target_speed        = parseFloat(fd.get('target_speed') || 100);
            tpl.target_avg_meters   = parseFloat(fd.get('target_avg_meters') || 500);
            tpl.target_time_total   = parseInt(fd.get('target_time_total') || 720, 10);
            tpl.target_time_run     = parseInt(fd.get('target_time_run') || 675, 10);
            tpl.target_loss_pct     = parseFloat(fd.get('target_loss_pct') || 10);
            tpl.target_shortage_pct = parseFloat(fd.get('target_shortage_pct') || 15);

            const depts = [];
            tbody.querySelectorAll('.dept-row').forEach(row => {
                const name = row.querySelector('.inp-dept-name')?.value?.trim();
                const tgt  = parseFloat(row.querySelector('.inp-dept-target')?.value || 0);
                const act  = row.querySelector('.chk-dept-active')?.checked ? 1 : 0;
                if (name) depts.push({ name, target_pct: tgt, is_active: act });
            });
            tpl.loss_departments = JSON.stringify(depts);

            const swt = {};
            document.querySelectorAll('.inp-station-target').forEach(inp => {
                const st = inp.getAttribute('data-station');
                const val = parseFloat(inp.value || 0);
                swt[st] = { target_pct: val, target_kg: val };
            });
            tpl.station_waste_targets = JSON.stringify(swt);

            const syncAll = document.getElementById('syncAllReports')?.checked;
            (BhsDB.state.bhs_shift_reports || []).forEach(rep => {
                if (syncAll || Number(rep.id) === selectedReportId) {
                    rep.target_orders     = tpl.target_orders;
                    rep.target_meters     = tpl.target_meters;
                    rep.target_speed      = tpl.target_speed;
                    rep.target_avg_meters = tpl.target_avg_meters;
                    rep.target_time_total = tpl.target_time_total;
                    rep.target_time_run   = tpl.target_time_run;
                    rep.target_loss_pct   = tpl.target_loss_pct;

                    let oldLoss = {};
                    try { oldLoss = typeof rep.loss_details === 'string' ? JSON.parse(rep.loss_details) : (rep.loss_details || {}); } catch (err) {}
                    const newLoss = {};
                    depts.filter(d => d.is_active).forEach(d => {
                        newLoss[d.name] = {
                            target_pct:  d.target_pct,
                            occurrences: oldLoss[d.name]?.occurrences || 0,
                            minutes:     oldLoss[d.name]?.minutes || 0,
                            score:       oldLoss[d.name]?.score ?? 3
                        };
                    });
                    rep.loss_details = JSON.stringify(newLoss);
                }
            });

            await BhsDB.save();
            this.setFlash('success', 'บันทึกค่าเป้าหมายและหัวข้อตารางเรียบร้อยแล้ว');
            if (selectedReportId > 0) {
                this.navigate(`#report_view?id=${selectedReportId}`);
            } else {
                this.renderAdminTargets(params);
            }
        });
    },

    // =========================================================================
    // 6. USERS MANAGEMENT VIEW
    // =========================================================================
    renderUsers() {
        const usersList = BhsDB.state.users || [];

        const html = `
        <div class="row align-items-center mb-4">
            <div class="col-md-7">
                <h3 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-users-gear text-orange me-2"></i> จัดการผู้ใช้งานในระบบ (User Management)
                </h3>
                <p class="text-muted fs-7 mb-0">เพิ่ม ลบ แก้ไขสิทธิ์ และรีเซ็ตรหัสผ่านของผู้ใช้งาน</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <button type="button" class="btn btn-orange rounded-pill px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddUser">
                    <i class="fa-solid fa-user-plus me-1"></i> เพิ่มผู้ใช้งานใหม่
                </button>
            </div>
        </div>

        <div class="card card-theme shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark"><i class="fa-solid fa-address-book text-orange me-2"></i> รายชื่อผู้ใช้งานทั้งหมด</span>
                <span class="badge bg-theme-orange text-white fs-8">${usersList.length} บัญชี</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-nowrap">
                        <thead class="table-light fs-8 text-secondary">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>ชื่อผู้ใช้งาน (Username)</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th class="text-center">ระดับสิทธิ์ (Role)</th>
                                <th class="text-center">สถานะ</th>
                                <th class="text-center pe-4">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${usersList.map(u => `
                            <tr>
                                <td class="ps-4 text-muted fw-bold">#${u.id}</td>
                                <td>
                                    <code>${this.esc(u.username)}</code>
                                    ${Number(u.id) === Number(this.currentUser?.id) ? '<span class="badge bg-info text-dark fs-9 ms-1">คุณกำลังใช้งาน</span>' : ''}
                                </td>
                                <td class="fw-semibold text-dark">${this.esc(u.full_name)}</td>
                                <td class="text-center">
                                    <span class="badge ${u.role === 'admin' ? 'bg-danger' : 'bg-secondary'} px-3 py-1 rounded-pill">
                                        ${u.role === 'admin' ? 'Admin (ผู้ดูแลระบบ)' : 'User (ผู้ใช้ทั่วไป)'}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge ${(u.status || 'active') === 'active' ? 'bg-success' : 'bg-warning text-dark'} rounded-pill px-2">
                                        ${(u.status || 'active') === 'active' ? 'ใช้งานปกติ' : 'ระงับการใช้'}
                                    </span>
                                </td>
                                <td class="text-center pe-4">
                                    ${Number(u.id) !== Number(this.currentUser?.id) ? `
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-del-user" data-id="${u.id}" data-uname="${this.esc(u.username)}">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>` : '-'}
                                </td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalAddUser" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form id="formAddUser" class="modal-content">
                    <div class="modal-header bg-theme-orange text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i> เพิ่มผู้ใช้งานใหม่</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-7">ชื่อผู้ใช้งาน (Username) *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-7">ชื่อ - นามสกุล *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-7">รหัสผ่านเริ่มต้น *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-7">สิทธิ์การใช้งาน (Role) *</label>
                            <select name="role" class="form-select">
                                <option value="user" selected>ผู้ใช้งานทั่วไป (User)</option>
                                <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-orange btn-sm px-4 fw-bold">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>`;

        this.renderShell('users', 'จัดการผู้ใช้งาน', html);

        document.getElementById('formAddUser')?.addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const username = fd.get('username').trim();
            if ((BhsDB.state.users || []).some(u => u.username.toLowerCase() === username.toLowerCase())) {
                return alert('ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว');
            }
            const maxId = (BhsDB.state.users || []).reduce((m, u) => Math.max(m, Number(u.id) || 0), 0);
            BhsDB.state.users.push({
                id: maxId + 1,
                username,
                password_plain: fd.get('password'),
                full_name: fd.get('full_name').trim(),
                role: fd.get('role'),
                status: 'active',
                created_at: new Date().toISOString().replace('T', ' ').substring(0, 19)
            });
            await BhsDB.save();
            bootstrap.Modal.getInstance(document.getElementById('modalAddUser'))?.hide();
            this.setFlash('success', `เพิ่มผู้ใช้งาน "${username}" เรียบร้อยแล้ว`);
            this.renderUsers();
        });

        document.querySelectorAll('.btn-del-user').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = Number(btn.getAttribute('data-id'));
                const uname = btn.getAttribute('data-uname');
                if (confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ "${uname}"?`)) {
                    BhsDB.state.users = BhsDB.state.users.filter(u => Number(u.id) !== id);
                    await BhsDB.save();
                    this.setFlash('success', 'ลบผู้ใช้งานเรียบร้อยแล้ว');
                    this.renderUsers();
                }
            });
        });
    }
});
