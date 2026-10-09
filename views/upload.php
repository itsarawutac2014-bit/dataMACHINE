<?php
/**
 * Machine Data Upload & Paste View
 * รองรับการเลือกกะ (กะ A, B), เลือกเครื่องจักร (BHS, YUELI, ISOWA) และเลือกวันที่เดินงาน
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$pageTitle = 'นำเข้าข้อมูลเครื่องจักร';
$activeNav = 'upload';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col-md-8">
        <h3 class="fw-bold mb-1 text-dark">
            <i class="fa-solid fa-cloud-arrow-up text-orange me-2"></i> นำเข้าไฟล์ข้อมูลเครื่องจักร (Import Data)
        </h3>
        <p class="text-muted fs-7 mb-0">เลือกเครื่องจักร กะ และวันที่เดินงาน จากนั้นโยนไฟล์หรือวางโค้ด HTML เพื่อคำนวณรายงานประสิทธิภาพ</p>
    </div>
    <div class="col-md-4 text-md-end mt-2 mt-md-0">
        <a href="<?= BASE_URL ?>/views/dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> กลับหน้ารายการ
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Main Import Card -->
    <div class="col-lg-8">
        <div class="card card-theme shadow-sm border-0">
            <div class="card-header card-header-theme d-flex justify-content-between align-items-center">
                <span class="fs-6 fw-bold"><i class="fa-solid fa-sliders text-orange me-2"></i> 1. กำหนดข้อมูลการเดินงาน และ 2. เลือกไฟล์</span>
                <span class="badge bg-theme-orange text-white fs-8">BHS / YUELI / ISOWA</span>
            </div>
            <div class="card-body p-4">

                <!-- MAIN FORM WRAPPER (Sends Machine, Shift, Date + File or Paste) -->
                <form id="mainUploadForm" action="<?= BASE_URL ?>/actions/upload_action.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <!-- ============================================================== -->
                    <!-- SECTION 1: SETTINGS (เลือกเครื่อง, เลือกกะ, เลือกวันที่เดินงาน) -->
                    <!-- ============================================================== -->
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <div class="row g-3 align-items-center">
                            
                            <!-- 1. เลือกเครื่องจักร (3 เครื่อง: BHS, YUELI, ISOWA) -->
                            <div class="col-md-5">
                                <label class="form-label fw-bold fs-7 text-dark mb-2">
                                    <i class="fa-solid fa-gears text-orange me-1"></i> 1. เลือกเครื่องจักร (Machine) *
                                </label>
                                <div class="btn-group w-100" role="group" aria-label="Machine Selection">
                                    <input type="radio" class="btn-check" name="machine_name" id="machineBHS" value="BHS" checked>
                                    <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="machineBHS">
                                        <i class="fa-solid fa-industry me-1"></i> BHS
                                    </label>

                                    <input type="radio" class="btn-check" name="machine_name" id="machineYUELI" value="YUELI">
                                    <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="machineYUELI">
                                        <i class="fa-solid fa-gear me-1"></i> YUELI
                                    </label>

                                    <input type="radio" class="btn-check" name="machine_name" id="machineISOWA" value="ISOWA">
                                    <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="machineISOWA">
                                        <i class="fa-solid fa-cogs me-1"></i> ISOWA
                                    </label>
                                </div>
                            </div>

                            <!-- 2. เลือกกะ (2 กะ: กะ A, กะ B) -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold fs-7 text-dark mb-2">
                                    <i class="fa-solid fa-user-clock text-orange me-1"></i> 2. เลือกกะ (Shift) *
                                </label>
                                <div class="btn-group w-100" role="group" aria-label="Shift Selection">
                                    <input type="radio" class="btn-check" name="shift" id="shiftA" value="A">
                                    <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="shiftA">
                                        กะ A
                                    </label>

                                    <input type="radio" class="btn-check" name="shift" id="shiftB" value="B" checked>
                                    <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="shiftB">
                                        กะ B
                                    </label>
                                </div>
                            </div>

                            <!-- 3. เลือกวันที่เดินงาน -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold fs-7 text-dark mb-2">
                                    <i class="fa-regular fa-calendar-days text-orange me-1"></i> 3. วันที่เดินงาน *
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-orange border-end-0">
                                        <i class="fa-solid fa-calendar-day"></i>
                                    </span>
                                    <input type="date" name="report_date" id="report_date" class="form-control border-start-0 ps-1" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                        </div>

                        <!-- แถวที่ 2: เวลาเริ่มเดิน, เวลาจบ และสรุปเวลาเดินทั้งหมด -->
                        <div class="row g-3 align-items-center mt-1 pt-2 border-top">
                            <!-- 4. เวลาเริ่มเดิน -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold fs-7 text-dark mb-1">
                                    <i class="fa-regular fa-clock text-orange me-1"></i> 4. เวลาเริ่มเดิน *
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-orange border-end-0">
                                        <i class="fa-solid fa-play fa-xs"></i>
                                    </span>
                                    <input type="time" name="shift_start_time" id="shift_start_time" class="form-control border-start-0 ps-1" value="20:00" required>
                                </div>
                            </div>

                            <!-- 5. เวลาจบ -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold fs-7 text-dark mb-1">
                                    <i class="fa-regular fa-clock text-orange me-1"></i> 5. เวลาจบ *
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-orange border-end-0">
                                        <i class="fa-solid fa-stop fa-xs"></i>
                                    </span>
                                    <input type="time" name="shift_end_time" id="shift_end_time" class="form-control border-start-0 ps-1" value="07:40" required>
                                </div>
                            </div>

                            <!-- กล่องแสดงผลเวลาเดินทั้งหมด (เสมอ) -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold fs-7 text-dark mb-1">
                                    <i class="fa-solid fa-stopwatch text-orange me-1"></i> เวลาเดินทั้งหมด (เสมอ)
                                </label>
                                <div class="alert alert-warning py-1 px-3 mb-0 border rounded-3 d-flex align-items-center justify-content-between">
                                    <span class="fs-8 fw-semibold text-dark">คำนวณอัตโนมัติ:</span>
                                    <span class="fs-6 fw-bold text-danger" id="displayShiftDuration">700 นาที</span>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ============================================================== -->
                    <!-- SECTION 3: ข้อมูลเพิ่มเติม (Optional Manual Inputs)            -->
                    <!-- ============================================================== -->
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fa-solid fa-clipboard-list text-orange me-2 fs-6"></i>
                            <span class="fw-bold fs-7 text-dark">3. ข้อมูลเพิ่มเติม (กรอกหรือไม่ก็ได้)</span>
                            <span class="badge bg-secondary ms-2 fs-9">Optional</span>
                        </div>

                        <!-- แถว A: Order Start / Finit + ขาดจำนวน -->
                        <div class="row g-3 mb-3">
                            <!-- Order Start (ออเดอร์แรกที่เริ่มเดิน) -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold fs-8 text-dark mb-1">
                                    <i class="fa-solid fa-flag-checkered text-success me-1"></i>
                                    Order เริ่มเดิน (Start)
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-success text-white border-0">
                                        <i class="fa-solid fa-play fa-xs"></i>
                                    </span>
                                    <input type="text" name="order_start" id="order_start"
                                           class="form-control form-control-sm"
                                           placeholder="เช่น PDC-001234"
                                           maxlength="30">
                                </div>
                            </div>

                            <!-- Order Finit (ออเดอร์สุดท้ายที่เดินจบ) -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold fs-8 text-dark mb-1">
                                    <i class="fa-solid fa-flag text-danger me-1"></i>
                                    Order เดินจบ (Finit)
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-danger text-white border-0">
                                        <i class="fa-solid fa-stop fa-xs"></i>
                                    </span>
                                    <input type="text" name="order_finit" id="order_finit"
                                           class="form-control form-control-sm"
                                           placeholder="เช่น PDC-001456"
                                           maxlength="30">
                                </div>
                            </div>

                            <!-- งานขาดจำนวน -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold fs-8 text-dark mb-1">
                                    <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>
                                    งานขาดจำนวน (Shortage Orders)
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="shortage_orders" id="shortage_orders"
                                           class="form-control form-control-sm text-center"
                                           placeholder="0" min="0">
                                    <span class="input-group-text bg-light text-secondary">Order</span>
                                </div>
                            </div>
                        </div>

                        <!-- แถว B: เวลาสูญเสีย 4 ประเภท -->
                        <div class="border-top pt-3 mb-3">
                            <div class="fw-bold fs-8 text-secondary mb-2">
                                <i class="fa-solid fa-clock-rotate-left text-warning me-1"></i>
                                เวลาสูญเสีย (Loss Time) — กรอกครั้ง/นาที
                            </div>
                            <div class="row g-2">
                                <?php
                                $lossTypes = [
                                    ['key' => 'loss_person',   'label' => 'ผลิต',        'icon' => 'fa-industry',       'color' => 'text-primary'],
                                    ['key' => 'loss_mc',       'label' => 'เครื่องจักร', 'icon' => 'fa-wrench',         'color' => 'text-danger'],
                                    ['key' => 'loss_material', 'label' => 'วัตถุดิบ',    'icon' => 'fa-boxes-stacked',  'color' => 'text-info'],
                                    ['key' => 'loss_other',    'label' => 'อื่นๆ',        'icon' => 'fa-circle-dot',     'color' => 'text-secondary'],
                                ];
                                foreach ($lossTypes as $lt):
                                ?>
                                <div class="col-md-3 col-6">
                                    <div class="bg-white rounded border p-2">
                                        <div class="fw-bold fs-9 mb-1 <?= $lt['color'] ?>">
                                            <i class="fa-solid <?= $lt['icon'] ?> me-1"></i><?= $lt['label'] ?>
                                        </div>
                                        <div class="row g-1">
                                            <div class="col-6">
                                                <label class="form-label fs-9 text-muted mb-0">ครั้ง</label>
                                                <input type="number" name="<?= $lt['key'] ?>_occ"
                                                       class="form-control form-control-sm text-center p-1"
                                                       placeholder="0" min="0" style="font-size:0.78rem;">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label fs-9 text-muted mb-0">นาที</label>
                                                <input type="number" name="<?= $lt['key'] ?>_min"
                                                       class="form-control form-control-sm text-center p-1"
                                                       placeholder="0" min="0" style="font-size:0.78rem;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- แถว C: น้ำหนักของเสียแยก Station -->
                        <div class="border-top pt-3">
                            <div class="fw-bold fs-8 text-secondary mb-2">
                                <i class="fa-solid fa-trash-can text-primary me-1"></i>
                                น้ำหนักของเสียแยก Station (KG)
                            </div>
                            <div class="row g-2">
                                <?php
                                $stations = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];
                                $stColors = ['#e8f5e9','#e3f2fd','#fff3e0','#f3e5f5','#fce4ec'];
                                foreach ($stations as $si => $st):
                                ?>
                                <div class="col">
                                    <div class="rounded border p-2 text-center" style="background:<?= $stColors[$si] ?>;">
                                        <div class="fw-bold fs-9 mb-1" style="color:#1f4e79;"><?= $st ?></div>
                                        <input type="number" name="station_waste_upload[<?= $st ?>]"
                                               class="form-control form-control-sm text-center fw-bold p-1"
                                               placeholder="0" min="0" step="any"
                                               style="font-size:0.85rem; background:transparent; border-color:#ccc;">
                                        <div class="fs-9 text-muted mt-1">KG</div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================== -->
                    <!-- SECTION 4 (was 2): IMPORT METHOD (โยนไฟล์ หรือ Paste HTML)   -->
                    <!-- ============================================================== -->

                    <!-- ============================================================== -->
                    <label class="form-label fw-bold fs-7 text-dark mb-2">
                        <i class="fa-solid fa-file-arrow-up text-orange me-1"></i> 4. เลือกวิธีการนำเข้าข้อมูลไฟล์
                    </label>

                    <!-- Nav Tabs for Upload Options -->
                    <ul class="nav nav-pills mb-3 nav-justified bg-light p-1 rounded-3" id="importTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-3 fw-semibold py-2" id="file-tab" data-bs-toggle="pill" data-bs-target="#tabFile" type="button" role="tab">
                                <i class="fa-solid fa-file-arrow-up me-1"></i> โยนไฟล์ข้อมูล (File Upload)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-3 fw-semibold py-2" id="paste-tab" data-bs-toggle="pill" data-bs-target="#tabPaste" type="button" role="tab">
                                <i class="fa-solid fa-paste me-1"></i> คัดลอกและวางโค้ด (Paste HTML)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="importTabsContent">
                        <!-- Tab 1: File Upload -->
                        <div class="tab-pane fade show active" id="tabFile" role="tabpanel">
                            <div class="upload-dropzone mb-3" id="uploadDropzone" onclick="document.getElementById('report_file').click();">
                                <div class="mb-3">
                                    <i class="fa-solid fa-cloud-arrow-up fa-3x text-orange"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</h5>
                                <p class="text-muted fs-7 mb-2">รองรับไฟล์รายงานเครื่องจักรทั้ง Excel (<code>.xlsx</code>, <code>.xls</code>) และ <code>.html</code>, <code>.htm</code>, <code>.txt</code></p>
                                <input type="file" name="report_file" id="report_file" class="d-none" accept=".xlsx,.xls,.html,.htm,.txt">
                                <div id="selectedFileName" class="mt-2"></div>
                            </div>
                        </div>

                        <!-- Tab 2: Paste HTML Table -->
                        <div class="tab-pane fade" id="tabPaste" role="tabpanel">
                            <div class="mb-3">
                                <textarea name="pasted_html" id="pasted_html" rows="9" class="form-control font-monospace fs-8" placeholder="วางโค้ด HTML เริ่มตั้งแต่ <table> หรือ <tr> ของตารางเครื่องจักรที่นี่..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-orange py-2 rounded-3 shadow-sm fw-bold fs-6">
                            <i class="fa-solid fa-microchip me-2"></i> ประมวลผลและสร้างรายงานประสิทธิภาพ
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- Right Column: Quick Sample Preset & Instructions -->
    <div class="col-lg-4">
        <!-- 1-Click Sample Import Button -->
        <div class="card card-theme shadow-sm border-0 mb-4 bg-theme-orange-light border-orange">
            <div class="card-body p-4 text-center">
                <div class="badge bg-theme-orange text-white mb-2 px-3 py-1 rounded-pill">ข้อมูลตัวอย่างพร้อมใช้</div>
                <h5 class="fw-bold text-dark mb-2">ทดสอบด้วยไฟล์ testing (23)</h5>
                <p class="text-muted fs-7 mb-3">
                    ระบบได้เตรียมไฟล์ข้อมูลเครื่องจักร <code>testing (23)</code> ไว้ให้เรียบร้อยแล้ว คุณสามารถคลิกปุ่มด้านล่างเพื่อนำเข้าข้อมูลตัวอย่างตามเครื่องจักรและกะที่เลือกไว้ได้ทันที
                </p>
                <form id="samplePresetForm" action="<?= BASE_URL ?>/actions/upload_action.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="use_sample" value="1">
                    <input type="hidden" name="machine_name" id="sampleMachine" value="BHS">
                    <input type="hidden" name="shift" id="sampleShift" value="B">
                    <input type="hidden" name="report_date" id="sampleDate" value="<?= date('Y-m-d') ?>">
                    <input type="hidden" name="shift_start_time" id="sampleStartTime" value="20:00">
                    <input type="hidden" name="shift_end_time" id="sampleEndTime" value="07:40">

                    <button type="submit" class="btn btn-orange w-100 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fa-solid fa-bolt me-2"></i> โหลดข้อมูลตัวอย่าง testing (23) ทันที
                    </button>
                </form>
            </div>
        </div>

        <!-- Specifications & Columns Guide -->
        <div class="card card-theme shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <span class="fw-semibold fs-7 text-dark"><i class="fa-solid fa-list-check text-orange me-2"></i> สรุปตัวเลือกที่กำหนดได้</span>
            </div>
            <div class="card-body p-3 fs-8">
                <ul class="list-unstyled mb-0 text-muted">
                    <li class="mb-2">
                        <strong class="text-dark"><i class="fa-solid fa-industry text-orange me-1"></i> เครื่องจักรที่รองรับ:</strong>
                        <div class="mt-1 ps-3">
                            <span class="badge bg-primary me-1">BHS</span>
                            <span class="badge bg-success me-1">YUELI</span>
                            <span class="badge bg-dark me-1">ISOWA</span>
                        </div>
                    </li>
                    <li class="mb-2">
                        <strong class="text-dark"><i class="fa-solid fa-clock text-orange me-1"></i> กะการทำงาน:</strong>
                        <span class="text-dark ms-1">กะ A (กะเช้า) / กะ B (กะดึก)</span>
                    </li>
                    <li class="mb-2">
                        <strong class="text-dark"><i class="fa-solid fa-calendar-day text-orange me-1"></i> วันที่เดินงาน:</strong>
                        <span class="text-dark ms-1">เลือกวันที่จากปฏิทินได้ตามจริง</span>
                    </li>
                    <li class="mb-2">
                        <strong class="text-dark"><i class="fa-solid fa-stopwatch text-orange me-1"></i> เวลาเดินทั้งหมด:</strong>
                        <span class="text-dark ms-1">คำนวณจาก (เวลาจบ - เวลาเริ่ม) นำไปลงช่องรายงานเสมอ</span>
                    </li>
                    <li class="mb-0">
                        <strong class="text-dark"><i class="fa-solid fa-circle-check text-success me-1"></i> การประมวลผล:</strong>
                        <span class="text-muted ms-1">คำนวณอัตโนมัติทั้งเมตรกระดาษ, GSM น้ำหนัก (M1-M5), แยกลอน, Trim และเรนเดอร์กราฟ</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// คำนวณเวลาเดินทั้งหมดและซิงค์การเลือกเครื่องจักร กะ วันที่ และเวลาไปยังฟอร์มตัวอย่าง
document.addEventListener('DOMContentLoaded', function() {
    function calculateDuration() {
        const startVal = document.getElementById('shift_start_time')?.value || '20:00';
        const endVal   = document.getElementById('shift_end_time')?.value || '07:40';
        
        const [sh, sm] = startVal.split(':').map(Number);
        const [eh, em] = endVal.split(':').map(Number);

        let startMins = sh * 60 + sm;
        let endMins   = eh * 60 + em;

        if (endMins <= startMins) {
            endMins += 24 * 60; // เดินงานข้ามคืน
        }

        const diff = endMins - startMins;
        const hrs = Math.floor(diff / 60);
        const remainMins = diff % 60;

        const displayEl = document.getElementById('displayShiftDuration');
        if (displayEl) {
            displayEl.textContent = `${diff} นาที (${hrs} ชม. ${remainMins} นาที)`;
        }

        syncSettings();
    }

    function syncSettings() {
        const selectedMachine = document.querySelector('input[name="machine_name"]:checked')?.value || 'BHS';
        const selectedShift   = document.querySelector('input[name="shift"]:checked')?.value || 'B';
        const selectedDate    = document.getElementById('report_date')?.value || '';
        const startTime       = document.getElementById('shift_start_time')?.value || '20:00';
        const endTime         = document.getElementById('shift_end_time')?.value || '07:40';

        document.getElementById('sampleMachine').value   = selectedMachine;
        document.getElementById('sampleShift').value     = selectedShift;
        document.getElementById('sampleDate').value      = selectedDate;
        document.getElementById('sampleStartTime').value = startTime;
        document.getElementById('sampleEndTime').value   = endTime;
    }

    // เมื่อเปลี่ยนกะ ให้เปลี่ยนเวลาเริ่มต้นอัตโนมัติ
    document.querySelectorAll('input[name="shift"]').forEach(el => {
        el.addEventListener('change', function() {
            if (this.value === 'A') {
                document.getElementById('shift_start_time').value = '08:00';
                document.getElementById('shift_end_time').value   = '20:00';
            } else if (this.value === 'B') {
                document.getElementById('shift_start_time').value = '20:00';
                document.getElementById('shift_end_time').value   = '07:40';
            }
            calculateDuration();
        });
    });

    document.querySelectorAll('input[name="machine_name"]').forEach(el => el.addEventListener('change', syncSettings));
    document.getElementById('report_date')?.addEventListener('change', syncSettings);
    document.getElementById('shift_start_time')?.addEventListener('input', calculateDuration);
    document.getElementById('shift_end_time')?.addEventListener('input', calculateDuration);

    calculateDuration();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
