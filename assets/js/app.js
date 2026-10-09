/**
 * BHS Performance System - Frontend Scripts
 * - HD Image Export (html2canvas)
 * - Chart.js Initializers
 * - Dynamic Filtering & AJAX Handlers
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. จัดการปุ่มบันทึกภาพแบบ HD (Export High-Definition Image)
    const btnSaveHd = document.getElementById('btnSaveHdImage');
    if (btnSaveHd) {
        btnSaveHd.addEventListener('click', function () {
            const reportArea = document.getElementById('hdReportCaptureArea');
            if (!reportArea) {
                alert('ไม่พบพื้นที่รายงานสำหรับการบันทึกภาพ');
                return;
            }

            if (typeof html2canvas === 'undefined') {
                alert('ไลบรารีบันทึกภาพกำลังเริ่มต้น กรุณาลองกดปุ่มอีกครั้ง');
                return;
            }

            const originalBtnHtml = btnSaveHd.innerHTML;
            btnSaveHd.disabled = true;
            btnSaveHd.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> กำลังบันทึกภาพ HD...';

            // ดึงชื่อไฟล์จากข้อมูลรายงาน
            const reportTitle = document.getElementById('reportTitleHeader')?.textContent?.trim() || 'BHS_Shift_Report';
            const cleanFilename = reportTitle.replace(/[\/\s:?*"<>|]+/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.png';

            // ซ่อนองค์ประกอบที่ไม่ต้องการให้ติดในภาพชั่วคราว
            const hideElems = document.querySelectorAll('.no-capture');
            hideElems.forEach(el => el.classList.add('d-none'));

            // เพิ่ม class is-capturing เพื่อให้ช่องกรอกดูเป็นตารางพิมพ์เรียบเนียน
            reportArea.classList.add('is-capturing');

            function cleanup() {
                btnSaveHd.disabled = false;
                hideElems.forEach(el => el.classList.remove('d-none'));
                reportArea.classList.remove('is-capturing');
            }

            function triggerDownload(blobOrUrl) {
                const link = document.createElement('a');
                link.download = cleanFilename;
                link.href = typeof blobOrUrl === 'string' ? blobOrUrl : URL.createObjectURL(blobOrUrl);
                document.body.appendChild(link);
                link.click();
                setTimeout(() => {
                    document.body.removeChild(link);
                    if (typeof blobOrUrl !== 'string') {
                        URL.revokeObjectURL(link.href);
                    }
                }, 2000);

                btnSaveHd.disabled = false;
                btnSaveHd.innerHTML = '<i class="fa-solid fa-check text-success me-2"></i> บันทึกภาพสำเร็จ!';
                setTimeout(() => {
                    btnSaveHd.innerHTML = originalBtnHtml;
                }, 2500);
                cleanup();
            }

            // บันทึก style เดิมของ reportArea
            const origStyle = {
                width: reportArea.style.width,
                maxWidth: reportArea.style.maxWidth,
                padding: reportArea.style.padding,
                margin: reportArea.style.margin,
            };

            // ขยายให้เต็ม container จริง (ไม่จำกัดความกว้าง)
            const containerWidth = reportArea.closest('.container-fluid, .container') 
                ? reportArea.closest('.container-fluid, .container').offsetWidth 
                : window.innerWidth - 40;
            reportArea.style.width = containerWidth + 'px';
            reportArea.style.maxWidth = 'none';

            function restoreStyle() {
                reportArea.style.width = origStyle.width;
                reportArea.style.maxWidth = origStyle.maxWidth;
            }

            const pixelRatio = 3; // scale 3 = คมชัดระดับ Retina

            html2canvas(reportArea, {
                scale: pixelRatio,
                useCORS: true,
                allowTaint: true,
                logging: false,
                backgroundColor: '#ffffff',
                width: reportArea.offsetWidth,
                height: reportArea.scrollHeight,
                windowWidth: reportArea.offsetWidth + 40,
                onclone: function (clonedDoc) {
                    const clonedArea = clonedDoc.getElementById('hdReportCaptureArea');
                    if (clonedArea) {
                        // ทำให้ font คมชัด
                        clonedArea.style.fontFamily = '"Prompt", "Sarabun", sans-serif';
                        clonedArea.style.webkitFontSmoothing = 'antialiased';
                        clonedArea.style.textRendering = 'optimizeLegibility';
                        clonedArea.style.letterSpacing = '0.01em';
                    }

                    // คัดลอก Chart.js canvas pixels
                    const origCanvases = reportArea.querySelectorAll('canvas');
                    const clonedCanvases = clonedDoc.querySelectorAll('#hdReportCaptureArea canvas');
                    origCanvases.forEach((origCanvas, i) => {
                        const clonedCanvas = clonedCanvases[i];
                        if (clonedCanvas && origCanvas.width > 0 && origCanvas.height > 0) {
                            clonedCanvas.width = origCanvas.width;
                            clonedCanvas.height = origCanvas.height;
                            const ctx = clonedCanvas.getContext('2d');
                            if (ctx) {
                                try { ctx.drawImage(origCanvas, 0, 0); } catch (e) {}
                            }
                        }
                    });

                    // คัดลอกค่า Input/Textarea ทุกช่อง + ทำให้ดูเป็นข้อความ (ไม่มีขอบ)
                    const origInputs = reportArea.querySelectorAll('input, textarea, select');
                    const clonedInputs = clonedDoc.querySelectorAll('#hdReportCaptureArea input, #hdReportCaptureArea textarea, #hdReportCaptureArea select');
                    origInputs.forEach((orig, i) => {
                        if (!clonedInputs[i]) return;
                        clonedInputs[i].value = orig.value;
                        clonedInputs[i].setAttribute('value', orig.value);
                        if (orig.tagName === 'TEXTAREA') {
                            clonedInputs[i].textContent = orig.value;
                            clonedInputs[i].innerText = orig.value;
                            clonedInputs[i].style.backgroundColor = '#ffffff';
                            clonedInputs[i].style.color = '#1e293b';
                            clonedInputs[i].style.border = 'none';
                            clonedInputs[i].style.outline = 'none';
                            clonedInputs[i].style.resize = 'none';
                        } else {
                            clonedInputs[i].style.background = 'transparent';
                            clonedInputs[i].style.border = 'none';
                            clonedInputs[i].style.outline = 'none';
                        }
                        clonedInputs[i].style.boxShadow = 'none';
                        clonedInputs[i].style.padding = '0 2px';
                        clonedInputs[i].style.fontWeight = orig.style.fontWeight || 'inherit';
                    });
                }
            }).then(function (canvas) {
                restoreStyle();
                if (canvas.toBlob) {
                    canvas.toBlob(function (blob) {
                        triggerDownload(blob || canvas.toDataURL('image/png'));
                    }, 'image/png');
                } else {
                    triggerDownload(canvas.toDataURL('image/png'));
                }
            }).catch(function (error) {
                restoreStyle();
                console.error('HD Export Error (Attempt 1):', error);
                // Fallback scale 2
                html2canvas(reportArea, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff'
                }).then(function (fallbackCanvas) {
                    triggerDownload(fallbackCanvas.toDataURL('image/png'));
                }).catch(function (fallbackErr) {
                    console.error('HD Export Error (Fallback):', fallbackErr);
                    alert('เกิดข้อผิดพลาดในการบันทึกภาพ: ' + (error.message || fallbackErr.message));
                    btnSaveHd.disabled = false;
                    btnSaveHd.innerHTML = originalBtnHtml;
                    cleanup();
                });
            });
        });
    }

    // 1.1 จัดการปุ่มส่งข้อมูลเข้า Google Sheets
    const btnSyncGs = document.getElementById('btnSyncGoogleSheet');
    if (btnSyncGs) {
        btnSyncGs.addEventListener('click', function () {
            const reportId = btnSyncGs.getAttribute('data-report-id');
            if (!reportId) return;

            const form = document.getElementById('formInlineKpi');
            const syncUrl = form ? form.action.replace('report_action.php', 'sync_google_sheet.php') : '../actions/sync_google_sheet.php';

            const origHtml = btnSyncGs.innerHTML;
            btnSyncGs.disabled = true;
            btnSyncGs.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> กำลังส่งไป Google Sheets...';

            const formData = new FormData();
            formData.append('report_id', reportId);

            fetch(syncUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                btnSyncGs.disabled = false;
                btnSyncGs.innerHTML = origHtml;
                if (data.success) {
                    alert('✅ ' + (data.message || 'ส่งข้อมูลเข้า Google Sheets เรียบร้อยแล้ว!'));
                } else {
                    alert('⚠️ ไม่สามารถส่งได้: ' + (data.message || 'กรุณาตรวจสอบการตั้งค่า URL ใน config/google_sheet.php'));
                }
            })
            .catch(err => {
                btnSyncGs.disabled = false;
                btnSyncGs.innerHTML = origHtml;
                alert('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + err.message);
            });
        });
    }

    // 2. Drag & Drop File Upload Handler
    const dropzone = document.getElementById('uploadDropzone');
    const fileInput = document.getElementById('report_file');
    const selectedFileText = document.getElementById('selectedFileName');

    if (dropzone && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            }, false);
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileNameDisplay(files[0].name);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                updateFileNameDisplay(fileInput.files[0].name);
            }
        });

        function updateFileNameDisplay(name) {
            if (selectedFileText) {
                selectedFileText.innerHTML = `<span class="badge bg-success fs-7 p-2"><i class="fa-solid fa-file-code me-1"></i> เลือกไฟล์แล้ว: ${name}</span>`;
            }
        }
    }

    // 3. เริ่มต้นระบบกรอกและคำนวณสูตรตาราง KPI & เวลาสูญเสียแบบเรียลไทม์
    initInlineKpiListeners();
});

/**
 * คำนวณเวลาเดินงานข้ามคืนเป็นนาที
 */
function calcShiftDurationMinutes(start, end) {
    if (!start || !end) return 720;
    const s = start.replace('.', ':').trim();
    const e = end.replace('.', ':').trim();
    const pS = s.split(':').map(Number);
    const pE = e.split(':').map(Number);
    if (pS.length < 2 || pE.length < 2 || isNaN(pS[0]) || isNaN(pE[0])) return 720;
    let startMin = pS[0] * 60 + pS[1];
    let endMin   = pE[0] * 60 + pE[1];
    if (endMin <= startMin) {
        endMin += 24 * 60; // เดินข้ามคืน (Overnight)
    }
    return endMin - startMin;
}

/**
 * คำนวณสูตร KPI และเวลาสูญเสียทั้งหมดแบบ Real-Time ตามสูตรมาตรฐาน
 */
window.recalculateTableKpi = function () {
    const form = document.getElementById('formInlineKpi');
    if (!form) return;

    // 1. Production Actuals & Targets from hidden fields
    const totalOrdersActual    = Number(document.getElementById('valTotalOrdersActual')?.value || 0);
    const totalMetersActual    = Number(document.getElementById('valTotalMetersActual')?.value || 0);
    const targetOrders         = Number(document.getElementById('valTargetOrders')?.value || 120);
    const targetMeters         = Number(document.getElementById('valTargetMeters')?.value || 68056);
    const targetSpeed          = Number(document.getElementById('valTargetSpeed')?.value || 100);
    const targetAvgMeters      = Number(document.getElementById('valTargetAvgMeters')?.value || 500);
    const targetShiftTotalTime = Number(document.getElementById('valTargetShiftTotalTime')?.value || 720);
    const targetTimeRun        = Number(document.getElementById('valTargetTimeRun')?.value || 675);

    // 2. เวลาเดินทั้งหมด (คำนวณจากเวลาเริ่มเดิน และเวลาจบ เสมอ)
    const startTimeStr = document.getElementById('hiddenShiftStartTime')?.value || '20:00';
    const endTimeStr   = document.getElementById('hiddenShiftEndTime')?.value || '07:40';
    let actualShiftDuration = calcShiftDurationMinutes(startTimeStr, endTimeStr);
    if (actualShiftDuration <= 0) actualShiftDuration = 700;

    const lblActualShiftDuration = document.getElementById('lblActualShiftDuration');
    const lblDurationBadge = document.getElementById('lblDurationBadge');
    if (lblActualShiftDuration) lblActualShiftDuration.textContent = actualShiftDuration;
    if (lblDurationBadge) lblDurationBadge.textContent = actualShiftDuration + ' นาที';

    const shiftTotalPct = targetShiftTotalTime > 0 ? Math.round((actualShiftDuration / targetShiftTotalTime) * 100) : 100;
    const lblShiftTotalPct = document.getElementById('lblShiftTotalPct');
    if (lblShiftTotalPct) lblShiftTotalPct.textContent = shiftTotalPct + '%';

    // 3. Order Start / Finit จากแผน
    const inpOrderPlanTotal = document.getElementById('inpOrderPlanTotal');
    const orderPlanTotal = Number(inpOrderPlanTotal?.value || 0);
    const orderPlanPct = targetOrders > 0 ? Math.round((orderPlanTotal / targetOrders) * 100) : 0;
    const orderPlanScore = orderPlanPct >= 100 ? 3 : 1;
    const lblOrderPlanPct = document.getElementById('lblOrderPlanPct');
    const lblOrderPlanScore = document.getElementById('lblOrderPlanScore');
    if (lblOrderPlanPct) lblOrderPlanPct.textContent = orderPlanPct + '%';
    if (lblOrderPlanScore) {
        lblOrderPlanScore.textContent = orderPlanScore;
        lblOrderPlanScore.className = 'td-kpi-pink fw-bold ' + (orderPlanScore >= 3 ? 'text-success' : 'text-danger');
    }

    // 4. ขาดจำนวน (สูตร: %ขาดจำนวน = ขาดจำนวน (order) / ออเดอร์ที่เดินได้ทั้งหมดในกะ * 100%)
    const inpShortageOrders = document.getElementById('inpShortageOrders');
    const shortageOrders = Number(inpShortageOrders?.value || 0);
    const shortagePct = totalOrdersActual > 0 ? Math.round((shortageOrders / totalOrdersActual) * 100) : 0;
    const shortageScore = shortagePct <= 15 ? 3 : 1;
    const lblShortagePct = document.getElementById('lblShortagePct');
    const lblShortageScore = document.getElementById('lblShortageScore');
    if (lblShortagePct) lblShortagePct.textContent = shortagePct + '%';
    if (lblShortageScore) {
        lblShortageScore.textContent = shortageScore;
        lblShortageScore.className = 'td-kpi-pink fw-bold ' + (shortageScore >= 3 ? 'text-success' : 'text-danger');
    }

    // 5. รายการเวลาสูญเสียรายแผนก (12 แผนก: ลงเองทั้งหมดยกเว้นช่องรวมเวลา)
    let totalLossOccurrences = 0;
    let totalLossMinutes = 0;
    let totalScore = 0;
    let totalTargetPct = 0;

    const deptRows = document.querySelectorAll('.row-loss-dept');
    const updatedMinutesArray = [];

    deptRows.forEach(row => {
        const occInput = row.querySelector('.inp-dept-occ');
        const minInput = row.querySelector('.inp-dept-min');
        const tgtInput = row.querySelector('.inp-dept-tgt');
        const scoSelect = row.querySelector('.sel-dept-sco');
        const pctLabel = row.querySelector('.lbl-dept-pct');

        const occ = Number(occInput?.value || 0);
        const min = Number(minInput?.value || 0);
        const tgt = parseFloat(tgtInput?.value || 0);
        let sco = Number(scoSelect?.value || 3);

        totalLossOccurrences += occ;
        totalLossMinutes += min;
        totalTargetPct += tgt;

        // % เวลาสูญสียแต่ละแผนก = นาทีของแผนก / เวลาเดินทั้งหมด * 100%
        const deptPct = actualShiftDuration > 0 ? ((min / actualShiftDuration) * 100) : 0;

        if (pctLabel) {
            pctLabel.textContent = min > 0 ? deptPct.toFixed(2) + '%' : '0.00%';
            pctLabel.className = 'td-kpi-pink fs-8 lbl-dept-pct ' + (min > 0 ? 'text-danger fw-bold' : 'text-muted');
        }

        if (minInput) {
            if (min > 0) {
                minInput.classList.add('text-danger', 'fw-bold');
            } else {
                minInput.classList.remove('text-danger', 'fw-bold');
            }
        }

        totalScore += sco;
        updatedMinutesArray.push(min);
    });

    // 6. แถว Total ผลรวมเวลาสูญเสีย (ยกเว้นช่องรวมเวลา - คำนวณอัตโนมัติ ห้ามลงเอง)
    const lblTotalLossOcc = document.getElementById('lblTotalLossOccurrences');
    const lblTotalLossMin = document.getElementById('lblTotalLossMinutes');
    const lblTotalLossPct = document.getElementById('lblTotalLossPct');
    const lblTotalScore   = document.getElementById('lblTotalScore');
    const lblTotalTgtPct  = document.getElementById('lblTotalTgtPct');

    const lossTotalPct = actualShiftDuration > 0 ? ((totalLossMinutes / actualShiftDuration) * 100) : 0;

    if (lblTotalLossOcc) lblTotalLossOcc.textContent = totalLossOccurrences;
    if (lblTotalLossMin) lblTotalLossMin.textContent = totalLossMinutes;
    if (lblTotalLossPct) lblTotalLossPct.textContent = lossTotalPct.toFixed(2) + '%';
    if (lblTotalScore)   lblTotalScore.textContent = totalScore;
    if (lblTotalTgtPct)  lblTotalTgtPct.textContent = totalTargetPct.toFixed(2) + '%';

    // 7. ช่องแถวบน: เวลาสูญเสียรวม
    const lblTotalLossMinutesTop = document.getElementById('lblTotalLossMinutesTop');
    const lblLossTotalPctTop     = document.getElementById('lblLossTotalPctTop');
    const lblLossTotalScoreTop   = document.getElementById('lblLossTotalScoreTop');
    const lblTargetLossPctTop    = document.getElementById('lblTargetLossPctTop');

    if (lblTotalLossMinutesTop) lblTotalLossMinutesTop.textContent = totalLossMinutes;
    if (lblLossTotalPctTop)     lblLossTotalPctTop.textContent = lossTotalPct.toFixed(2) + '%';
    if (lblTargetLossPctTop)    lblTargetLossPctTop.textContent = totalTargetPct.toFixed(2) + '%';
    if (lblLossTotalScoreTop) {
        const scoTop = lossTotalPct <= totalTargetPct ? 3 : 1;
        lblLossTotalScoreTop.textContent = scoTop;
        lblLossTotalScoreTop.className = 'td-kpi-pink fw-bold ' + (scoTop >= 3 ? 'text-success' : 'text-danger');
    }

    // 8. ช่องแถวบน: เวลาเดินงานจริง = เวลาเดินทั้งหมด - เวลาสูญเสียรวม
    const runningTimeMin = Math.max(0, actualShiftDuration - totalLossMinutes);
    const runningPct = targetTimeRun > 0 ? Math.round((runningTimeMin / targetTimeRun) * 100) : 0;
    const lblRunningTimeMin = document.getElementById('lblRunningTimeMin');
    const lblRunningPct = document.getElementById('lblRunningPct');
    const lblRunningScore = document.getElementById('lblRunningScore');

    if (lblRunningTimeMin) lblRunningTimeMin.textContent = runningTimeMin;
    if (lblRunningPct)     lblRunningPct.textContent = runningPct + '%';
    if (lblRunningScore) {
        const scoRun = runningTimeMin >= targetTimeRun ? 3 : 1;
        lblRunningScore.textContent = scoRun;
        lblRunningScore.className = 'td-kpi-pink fw-bold ' + (scoRun >= 3 ? 'text-success' : 'text-danger');
    }

    // 9. ช่องแถวบน: Speed เฉลี่ยหักสูญเสีย = ผลิตได้ (เมตร) / เวลาเดินงานจริง (นาที)
    const avgSpeedNet = runningTimeMin > 0 ? Math.round(totalMetersActual / runningTimeMin) : 0;
    const avgSpeedNetPct = targetSpeed > 0 ? Math.round((avgSpeedNet / targetSpeed) * 100) : 0;
    const lblAvgSpeedNet = document.getElementById('lblAvgSpeedNet');
    const lblAvgSpeedNetPct = document.getElementById('lblAvgSpeedNetPct');
    const lblAvgSpeedNetScore = document.getElementById('lblAvgSpeedNetScore');

    if (lblAvgSpeedNet) lblAvgSpeedNet.textContent = avgSpeedNet;
    if (lblAvgSpeedNetPct) lblAvgSpeedNetPct.textContent = avgSpeedNetPct + '%';
    if (lblAvgSpeedNetScore) {
        const scoNet = avgSpeedNet >= targetSpeed ? 3 : 1;
        lblAvgSpeedNetScore.textContent = scoNet;
        lblAvgSpeedNetScore.className = 'td-kpi-pink fw-bold ' + (scoNet >= 3 ? 'text-success' : 'text-danger');
    }

    // 10. ช่องแถวบน: Speed เฉลี่ยไม่หักเวลาสูญเสีย = ผลิตได้ (เมตร) / เวลาเดินทั้งหมด (นาที)
    const avgSpeedGross = actualShiftDuration > 0 ? Math.round(totalMetersActual / actualShiftDuration) : 0;
    const avgSpeedGrossPct = targetSpeed > 0 ? Math.round((avgSpeedGross / targetSpeed) * 100) : 0;
    const lblAvgSpeedGross = document.getElementById('lblAvgSpeedGross');
    const lblAvgSpeedGrossPct = document.getElementById('lblAvgSpeedGrossPct');
    const lblAvgSpeedGrossScore = document.getElementById('lblAvgSpeedGrossScore');

    if (lblAvgSpeedGross) lblAvgSpeedGross.textContent = avgSpeedGross;
    if (lblAvgSpeedGrossPct) lblAvgSpeedGrossPct.textContent = avgSpeedGrossPct + '%';
    if (lblAvgSpeedGrossScore) {
        const scoGross = avgSpeedGross >= targetSpeed ? 3 : 1;
        lblAvgSpeedGrossScore.textContent = scoGross;
        lblAvgSpeedGrossScore.className = 'td-kpi-pink fw-bold ' + (scoGross >= 3 ? 'text-success' : 'text-danger');
    }

    // 11. อัปเดตกราฟแท่งเวลาสูญเสีย (Chart.js Bar Chart) ทันที
    if (window.bhsLossChart && window.bhsLossChart.data && window.bhsLossChart.data.datasets[0]) {
        window.bhsLossChart.data.datasets[0].data = updatedMinutesArray;
        window.bhsLossChart.update();
    }
};

/**
 * จัดการ Event Listener สำหรับตารางและปุ่มบันทึก
 */
function initInlineKpiListeners() {
    const form = document.getElementById('formInlineKpi');
    if (!form) return;

    // ตรวจจับการพิมพ์และแก้ไขค่าในตารางแบบเรียลไทม์
    form.addEventListener('input', function (e) {
        if (e.target.matches('.table-input-cell, input, select, textarea')) {
            window.recalculateTableKpi();
        }
    });

    form.addEventListener('change', function (e) {
        if (e.target.matches('.table-input-cell, input, select, textarea')) {
            window.recalculateTableKpi();
        }
    });

    // Event listener สำหรับตารางของเสียแยก Station
    const wasteInputs = document.querySelectorAll('.inp-station-waste-kg');
    wasteInputs.forEach(inp => {
        inp.addEventListener('input', function () {
            window.recalculateStationWaste();
        });
        inp.addEventListener('change', function () {
            window.recalculateStationWaste();
        });
    });

    // ปุ่มบันทึกข้อมูลตาราง (.btn-save-action)
    const saveButtons = document.querySelectorAll('.btn-save-action');
    saveButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            saveInlineKpiData(btn);
        });
    });
}

/**
 * คำนวณสูตรตารางของเสียแยก Station แบบ Real-Time
 */
window.recalculateStationWaste = function () {
    const wasteInputs = document.querySelectorAll('.inp-station-waste-kg');
    if (!wasteInputs.length) return;

    let totalWasteKg = 0;
    const valuesByStation = {};
    const values = [];

    wasteInputs.forEach(inp => {
        const station = inp.getAttribute('data-station');
        const val = parseFloat(inp.value) || 0;
        valuesByStation[station] = val;
        totalWasteKg += val;
        values.push(val);
    });

    const totProdWeight = parseFloat(document.getElementById('valTotalProductionWeight')?.value) || 0;

    // อัปเดตตารางแต่ละแถว
    wasteInputs.forEach(inp => {
        const station = inp.getAttribute('data-station');
        const val = valuesByStation[station] || 0;
        const ratio = totalWasteKg > 0 ? ((val / totalWasteKg) * 100).toFixed(1) : 0;
        const prodPct = totProdWeight > 0 ? ((val / totProdWeight) * 100).toFixed(2) : 0;

        const row = inp.closest('tr');
        if (row) {
            const lblRatio = row.querySelector('.lbl-station-ratio');
            if (lblRatio) lblRatio.textContent = ratio + '%';
            const lblProd = row.querySelector('.lbl-station-prod');
            if (lblProd) {
                lblProd.textContent = prodPct + '%';
                const targetPct = parseFloat(lblProd.getAttribute('data-target') || row.getAttribute('data-target')) || 0;
                if (targetPct > 0) {
                    if (parseFloat(prodPct) <= targetPct) {
                        lblProd.style.background = '#d4edda'; // สีเขียวอ่อน
                        lblProd.style.color = '#155724';
                    } else {
                        lblProd.style.background = '#f8d7da'; // สีแดงอ่อน
                        lblProd.style.color = '#721c24';
                    }
                }
            }
        }
    });

    // อัปเดตแถวรวม
    const lblTotalKg = document.getElementById('lblStationWasteTotalKg');
    if (lblTotalKg) {
        lblTotalKg.textContent = totalWasteKg > 0 ? totalWasteKg.toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) : '0';
    }

    const lblTotalRatio = document.getElementById('lblStationWasteTotalRatio');
    if (lblTotalRatio) {
        lblTotalRatio.textContent = totalWasteKg > 0 ? '100%' : '0%';
    }

    const lblTotalProdPct = document.getElementById('lblStationWasteTotalProdPct');
    if (lblTotalProdPct) {
        const totProdPct = totProdWeight > 0 ? ((totalWasteKg / totProdWeight) * 100).toFixed(2) : 0;
        lblTotalProdPct.textContent = totProdPct + '%';
        const totalTargetPct = parseFloat(lblTotalProdPct.getAttribute('data-target')) || 0;
        if (totalTargetPct > 0) {
            if (parseFloat(totProdPct) <= totalTargetPct) {
                lblTotalProdPct.style.background = '#d4edda'; // สีเขียวอ่อน
                lblTotalProdPct.style.color = '#155724';
            } else {
                lblTotalProdPct.style.background = '#f8d7da'; // สีแดงอ่อน
                lblTotalProdPct.style.color = '#721c24';
            }
        }
    }

    // อัปเดตกราฟ Station Waste แบบไดนามิก
    if (window.bhsWasteBarChart) {
        window.bhsWasteBarChart.data.datasets[0].data = values;
        const maxVal = Math.max(50, ...values);
        if (window.bhsWasteBarChart.options.scales && window.bhsWasteBarChart.options.scales.y) {
            window.bhsWasteBarChart.options.scales.y.suggestedMax = maxVal * 1.25;
        }
        window.bhsWasteBarChart.update();
    }
    if (window.bhsWasteDonutChart) {
        window.bhsWasteDonutChart.data.datasets[0].data = values;
        window.bhsWasteDonutChart.update();
    }
};

/**
 * ส่งข้อมูลตารางบันทึกผ่าน AJAX
 */
function saveInlineKpiData(triggerBtn) {
    const form = document.getElementById('formInlineKpi');
    if (!form) return;

    const originalHtml = triggerBtn.innerHTML;
    triggerBtn.disabled = true;
    triggerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> กำลังบันทึก...';

    const statusMsg = document.getElementById('saveStatusMsg');
    if (statusMsg) {
        statusMsg.innerHTML = '<span class="text-secondary"><i class="fa-solid fa-spinner fa-spin me-1"></i> กำลังบันทึก...</span>';
    }

    const formData = new FormData(form);

    // ใส่ข้อมูล Station Waste ลงใน formData เผื่อฟอร์มอยู่นอก formInlineKpi
    const wasteInputs = document.querySelectorAll('.inp-station-waste-kg');
    wasteInputs.forEach(inp => {
        formData.set(inp.name, inp.value);
    });

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        triggerBtn.disabled = false;
        if (data.success) {
            triggerBtn.innerHTML = '<i class="fa-solid fa-check text-white me-1"></i> บันทึกแล้ว!';
            if (statusMsg) {
                statusMsg.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> บันทึกข้อมูลสำเร็จ!</span>';
            }
            setTimeout(() => {
                triggerBtn.innerHTML = originalHtml;
                if (statusMsg) statusMsg.innerHTML = '';
            }, 3000);
        } else {
            triggerBtn.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> ผิดพลาด';
            if (statusMsg) {
                statusMsg.innerHTML = `<span class="text-danger"><i class="fa-solid fa-circle-xmark me-1"></i> ${data.message || 'บันทึกไม่สำเร็จ'}</span>`;
            }
            setTimeout(() => {
                triggerBtn.innerHTML = originalHtml;
            }, 3500);
        }
    })
    .catch(err => {
        console.error('Save KPI Error:', err);
        triggerBtn.disabled = false;
        triggerBtn.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-danger me-1"></i> ผิดพลาด';
        if (statusMsg) {
            statusMsg.innerHTML = '<span class="text-danger"><i class="fa-solid fa-circle-xmark me-1"></i> เกิดข้อผิดพลาดในการเชื่อมต่อ</span>';
        }
        setTimeout(() => {
            triggerBtn.innerHTML = originalHtml;
        }, 3500);
    });
}

/**
 * ฟังก์ชันสร้างกราฟด้วย Chart.js สำหรับหน้ารายงาน
 */
window.initBhsCharts = function (chartData) {
    if (typeof Chart === 'undefined') return;

    const dpr = Math.max(window.devicePixelRatio || 1, 2);

    // Custom Plugin สำหรับแสดงตัวเลขบนแท่งกราฟ (Bar Data Labels)
    const barDataLabelsPlugin = {
        id: 'barDataLabels',
        afterDatasetsDraw(chart, args, pluginOptions) {
            const { ctx } = chart;
            chart.data.datasets.forEach((dataset, i) => {
                const meta = chart.getDatasetMeta(i);
                if (meta.hidden) return;

                meta.data.forEach((bar, index) => {
                    const val = dataset.data[index];
                    if (val === null || val === undefined || parseFloat(val) <= 0) return;

                    ctx.save();
                    ctx.font = '700 13px "Prompt", sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.fillStyle = (pluginOptions && pluginOptions.color) || '#0f3b61';

                    const suffix = (pluginOptions && pluginOptions.suffix) || '';
                    let text = typeof val === 'number' && !Number.isInteger(val)
                        ? val.toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
                        : val.toLocaleString('en-US');
                    text += suffix;

                    ctx.fillText(text, bar.x, bar.y - 4);
                    ctx.restore();
                });
            });
        }
    };

    // Custom Plugin สำหรับแสดงตัวเลข % บนชิ้นโดนัท (Donut Data Labels)
    const donutDataLabelsPlugin = {
        id: 'donutDataLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            chart.data.datasets.forEach((dataset, i) => {
                const meta = chart.getDatasetMeta(i);
                if (meta.hidden) return;

                const total = dataset.data.reduce((a, b) => a + (parseFloat(b) || 0), 0);
                if (total <= 0) return;

                meta.data.forEach((arc, index) => {
                    const val = parseFloat(dataset.data[index]) || 0;
                    if (val <= 0) return;

                    const pct = ((val / total) * 100).toFixed(1) + '%';
                    if ((val / total) < 0.04) return; // ไม่แสดงถ้าชิ้นเล็กกว่า 4%

                    const pos = arc.tooltipPosition();
                    ctx.save();
                    ctx.font = '700 12px "Prompt", sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = '#ffffff';
                    ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
                    ctx.shadowBlur = 4;
                    ctx.fillText(pct, pos.x, pos.y);
                    ctx.restore();
                });
            });
        }
    };

    // 1. กราฟเวลาสูญเสีย (Loss Time Bar Chart)
    const ctxLoss = document.getElementById('chartLossTime')?.getContext('2d');
    if (ctxLoss && chartData.lossLabels) {
        if (window.bhsLossChart) {
            window.bhsLossChart.destroy();
        }
        const maxLoss = Math.max(10, ...chartData.lossMinutes.map(Number));
        window.bhsLossChart = new Chart(ctxLoss, {
            type: 'bar',
            data: {
                labels: chartData.lossLabels,
                datasets: [{
                    label: 'เวลาสูญเสีย (นาที)',
                    data: chartData.lossMinutes,
                    backgroundColor: '#1f4e79',
                    borderColor: '#112f4b',
                    borderWidth: 1,
                    barPercentage: 0.55
                }]
            },
            options: {
                devicePixelRatio: dpr,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'เวลาสูญเสีย (นาที)',
                        font: { size: 16, family: 'Prompt', weight: '700' },
                        color: '#b30000',
                        padding: { top: 4, bottom: 8 }
                    },
                    barDataLabels: {
                        color: '#b30000',
                        suffix: ' น.'
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '#e5e7eb' },
                        suggestedMax: maxLoss * 1.25,
                        ticks: { font: { size: 11, family: 'Prompt', weight: '600' }, color: '#111827' }
                    },
                    x: { ticks: { font: { size: 12, family: 'Prompt', weight: '600' }, color: '#111827' } }
                }
            },
            plugins: [barDataLabelsPlugin]
        });
    }

    // 2. กราฟ % น้ำหนักแยกลอน
    const ctxFlute = document.getElementById('chartFluteWeight')?.getContext('2d');
    if (ctxFlute && chartData.fluteLabels) {
        if (window.bhsFluteChart) {
            window.bhsFluteChart.destroy();
        }
        const maxFlute = Math.max(10, ...chartData.flutePcts.map(Number));
        window.bhsFluteChart = new Chart(ctxFlute, {
            type: 'bar',
            data: {
                labels: chartData.fluteLabels,
                datasets: [{
                    label: '% น้ำหนัก',
                    data: chartData.flutePcts,
                    backgroundColor: '#2e75b6',
                    borderColor: '#1f4e79',
                    borderWidth: 1,
                    barPercentage: 0.55
                }]
            },
            options: {
                devicePixelRatio: dpr,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: '% น้ำหนักแยกลอน',
                        font: { size: 16, family: 'Prompt', weight: '700' },
                        color: '#0f3b61',
                        padding: { top: 4, bottom: 8 }
                    },
                    barDataLabels: {
                        color: '#0f3b61',
                        suffix: '%'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            callback: v => v + '%',
                            font: { size: 11, family: 'Prompt', weight: '600' }, 
                            color: '#111827'
                        },
                        grid: { color: '#e5e7eb' },
                        suggestedMax: Math.min(100, maxFlute * 1.25)
                    },
                    x: { ticks: { font: { size: 12, family: 'Prompt', weight: '600' }, color: '#111827' } }
                }
            },
            plugins: [barDataLabelsPlugin]
        });
    }

    // 3. กราฟของเสียแยก Station (KG)
    const ctxWasteBar = document.getElementById('chartStationWasteBar')?.getContext('2d');
    if (ctxWasteBar && chartData.stationLabels) {
        if (window.bhsWasteBarChart) window.bhsWasteBarChart.destroy();
        const maxWaste = Math.max(50, ...chartData.stationWasteKg.map(Number));
        window.bhsWasteBarChart = new Chart(ctxWasteBar, {
            type: 'bar',
            data: {
                labels: chartData.stationLabels,
                datasets: [{
                    label: 'ของเสีย (KG)',
                    data: chartData.stationWasteKg,
                    backgroundColor: ['#e74c3c', '#e67e22', '#f39c12', '#2980b9', '#8e44ad'],
                    borderRadius: 4,
                    borderWidth: 1,
                    barPercentage: 0.6
                }]
            },
            options: {
                devicePixelRatio: dpr,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'ของเสียแยก Station (KG)',
                        font: { size: 16, family: 'Prompt', weight: '700' },
                        color: '#b30000',
                        padding: { top: 4, bottom: 8 }
                    },
                    barDataLabels: {
                        color: '#111827',
                        suffix: ' kg'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e5e7eb' },
                        ticks: { font: { size: 11, family: 'Prompt', weight: '600' }, color: '#111827' },
                        suggestedMax: maxWaste * 1.25
                    },
                    x: { ticks: { font: { size: 12, family: 'Prompt', weight: '600' }, color: '#111827' } }
                }
            },
            plugins: [barDataLabelsPlugin]
        });
    }

    // 4. กราฟ % สัดส่วนของเสียแยก Station
    const ctxWasteDonut = document.getElementById('chartStationWasteDonut')?.getContext('2d');
    if (ctxWasteDonut && chartData.stationLabels) {
        if (window.bhsWasteDonutChart) window.bhsWasteDonutChart.destroy();
        window.bhsWasteDonutChart = new Chart(ctxWasteDonut, {
            type: 'doughnut',
            data: {
                labels: chartData.stationLabels,
                datasets: [{
                    data: chartData.stationWasteKg,
                    backgroundColor: ['#e74c3c', '#e67e22', '#f39c12', '#2980b9', '#8e44ad'],
                    borderWidth: 2
                }]
            },
            options: {
                devicePixelRatio: dpr,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: { size: 12, family: 'Prompt', weight: '600' },
                            color: '#111827',
                            padding: 10
                        }
                    },
                    title: {
                        display: true,
                        text: '% สัดส่วนของเสีย',
                        font: { size: 16, family: 'Prompt', weight: '700' },
                        color: '#0f3b61',
                        padding: { top: 4, bottom: 8 }
                    }
                }
            },
            plugins: [donutDataLabelsPlugin]
        });
    }
};
