/**
 * BHS Performance System - Full Single Page Application (SPA) Controller for GitHub Pages
 * Renders 100% Identical UI & Logic to the PHP Views
 */

const BhsApp = {
    currentUser: null,
    flash: null,
    basePath: '.',

    async start() {
        await BhsDB.init(this.basePath);

        // Restore session
        const savedUser = sessionStorage.getItem('bhs_current_user') || localStorage.getItem('bhs_current_user');
        if (savedUser) {
            try { this.currentUser = JSON.parse(savedUser); } catch (e) {}
        }

        window.addEventListener('hashchange', () => this.route());
        this.route();
    },

    setFlash(type, message) {
        this.flash = { type, message };
    },

    getFlashHtml() {
        if (!this.flash) return '';
        const f = this.flash;
        this.flash = null;
        const icon = f.type === 'success'
            ? 'fa-circle-check text-success'
            : (f.type === 'danger' ? 'fa-circle-xmark text-danger' : 'fa-circle-info text-info');
        return `
        <div class="container-fluid px-3 px-xl-4 mt-3 no-capture">
            <div class="alert alert-${this.esc(f.type)} alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert">
                <i class="fa-solid ${icon} fa-lg me-2"></i>
                <div>${this.esc(f.message)}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>`;
    },

    esc(val) {
        if (val === null || val === undefined) return '';
        return String(val)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    numFmt(val, decimals = 0) {
        const n = Number(val) || 0;
        return n.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    },

    isAdmin() {
        return this.currentUser && this.currentUser.role === 'admin';
    },

    navigate(hash) {
        if (window.location.hash === hash) {
            this.route();
        } else {
            window.location.hash = hash;
        }
    },

    parseHash() {
        let h = window.location.hash.replace(/^#\/?/, '');
        if (!h) h = 'dashboard';
        const [page, queryStr] = h.split('?');
        const params = new URLSearchParams(queryStr || '');
        return { page, params };
    },

    route() {
        const { page, params } = this.parseHash();

        if (!this.currentUser && page !== 'login') {
            this.renderLogin();
            return;
        }

        if (this.currentUser && page === 'login') {
            this.navigate('#dashboard');
            return;
        }

        window.scrollTo(0, 0);

        switch (page) {
            case 'login':
                this.renderLogin();
                break;
            case 'upload':
                this.renderShell('upload', 'นำเข้าข้อมูลเครื่องจักร', this.getUploadHtml());
                this.bindUploadEvents();
                break;
            case 'report_view':
                this.renderReportView(params);
                break;
            case 'admin_targets':
                if (!this.isAdmin()) {
                    this.setFlash('danger', 'เฉพาะผู้ดูแลระบบ (Admin) เท่านั้น');
                    this.navigate('#dashboard');
                    return;
                }
                this.renderAdminTargets(params);
                break;
            case 'users':
                if (!this.isAdmin()) {
                    this.setFlash('danger', 'เฉพาะผู้ดูแลระบบ (Admin) เท่านั้น');
                    this.navigate('#dashboard');
                    return;
                }
                this.renderUsers();
                break;
            case 'dashboard':
            default:
                this.renderDashboard();
                break;
        }
    },

    renderShell(activeNav, pageTitle, mainContentHtml) {
        document.title = `${pageTitle} | ระบบรายงานประสิทธิภาพการเดินงาน BHS ประจำกะ`;
        const u = this.currentUser;
        const appRoot = document.getElementById('appRoot');
        appRoot.className = 'bg-light d-flex flex-column min-vh-100';
        appRoot.innerHTML = `
        <nav class="navbar navbar-expand-lg navbar-dark bg-theme-orange sticky-top shadow-sm py-2 no-capture">
            <div class="container-fluid px-3 px-xl-4">
                <a class="navbar-brand d-flex align-items-center fw-bold" href="#dashboard">
                    <span class="brand-icon bg-white text-orange rounded-3 p-2 me-2 shadow-sm d-inline-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-industry fa-lg"></i>
                    </span>
                    <div>
                        <span class="fs-5 text-white brand-text">BHS Performance</span>
                        <small class="d-block text-white-50 fs-8 fw-normal">ระบบรายงานประสิทธิภาพการเดินงาน BHS</small>
                    </div>
                </a>

                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="mainNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                        <li class="nav-item">
                            <a class="nav-link text-white ${activeNav === 'dashboard' ? 'active fw-bold' : ''}" href="#dashboard">
                                <i class="fa-solid fa-chart-line me-1"></i> รายการรายงานประจำกะ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white ${activeNav === 'upload' ? 'active fw-bold' : ''}" href="#upload">
                                <i class="fa-solid fa-cloud-arrow-up me-1"></i> นำเข้าไฟล์เครื่องจักร (Import)
                            </a>
                        </li>
                        ${this.isAdmin() ? `
                        <li class="nav-item">
                            <a class="nav-link text-white ${activeNav === 'targets' ? 'active fw-bold' : ''}" href="#admin_targets">
                                <i class="fa-solid fa-sliders me-1"></i> จัดการเป้าหมาย & หัวข้อ (Targets)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white ${activeNav === 'users' ? 'active fw-bold' : ''}" href="#users">
                                <i class="fa-solid fa-users-gear me-1"></i> จัดการผู้ใช้ (Users)
                            </a>
                        </li>
                        ` : ''}
                    </ul>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-light text-secondary fw-semibold rounded-start-pill px-2 shadow-sm" id="btnExportDbJson" title="สำรองข้อมูลทั้งหมดเป็นไฟล์ JSON">
                                <i class="fa-solid fa-download text-orange me-1"></i> สำรองข้อมูล
                            </button>
                            <button type="button" class="btn btn-light text-secondary fw-semibold rounded-end-pill px-2 shadow-sm border-start" id="btnImportDbJson" title="นำเข้าไฟล์สำรองข้อมูล JSON">
                                <i class="fa-solid fa-upload text-primary me-1"></i> กู้คืนข้อมูล
                            </button>
                            <input type="file" id="inpImportDbFile" class="d-none" accept=".json">
                        </div>

                        <div class="user-badge px-3 py-1 bg-white rounded-pill text-dark d-flex align-items-center shadow-sm">
                            <i class="fa-solid fa-circle-user text-orange me-2 fs-5"></i>
                            <div class="lh-1 me-2 text-start">
                                <div class="fw-bold fs-7">${this.esc(u.full_name)}</div>
                                <span class="badge ${this.isAdmin() ? 'bg-danger' : 'bg-secondary'} fs-9 py-0">
                                    ${this.isAdmin() ? 'ผู้ดูแลระบบ (Admin)' : 'ผู้ใช้งาน (User)'}
                                </span>
                            </div>
                        </div>
                        <button type="button" id="btnLogout" class="btn btn-outline-light btn-sm rounded-pill px-3 shadow-sm">
                            <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> ออกจากระบบ
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        ${this.getFlashHtml()}

        <main class="flex-grow-1 py-3">
            <div class="container-fluid px-3 px-xl-4">
                ${mainContentHtml}
            </div>
        </main>

        <footer class="bg-white border-top py-3 mt-auto no-capture">
            <div class="container-fluid px-3 px-xl-4 text-center text-md-between d-flex flex-column flex-md-row justify-content-between align-items-center">
                <span class="text-muted fs-8">
                    &copy; ${new Date().getFullYear()} <strong>BHS Performance</strong> | ระบบติดตามและรายงานประสิทธิภาพการเดินงาน BHS
                </span>
                <span class="text-muted fs-8 mt-1 mt-md-0">
                    <i class="fa-brands fa-github text-dark me-1"></i> GitHub Cloud Edition | Online 24/7 Unlimited
                </span>
            </div>
        </footer>`;

        document.getElementById('btnLogout')?.addEventListener('click', () => {
            if (confirm('ยืนยันออกจากระบบหรือไม่?')) {
                this.currentUser = null;
                sessionStorage.removeItem('bhs_current_user');
                localStorage.removeItem('bhs_current_user');
                this.setFlash('info', 'ออกจากระบบเรียบร้อยแล้ว');
                this.navigate('#login');
            }
        });

        document.getElementById('btnExportDbJson')?.addEventListener('click', () => {
            const dataStr = JSON.stringify(BhsDB.state, null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `dataMACHINE_backup_${new Date().toISOString().slice(0, 10)}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });

        const inpImport = document.getElementById('inpImportDbFile');
        document.getElementById('btnImportDbJson')?.addEventListener('click', () => inpImport?.click());
        inpImport?.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            try {
                const txt = await file.text();
                const parsed = JSON.parse(txt);
                if (parsed && Array.isArray(parsed.bhs_shift_reports)) {
                    BhsDB.state = parsed;
                    await BhsDB.save();
                    this.setFlash('success', 'กู้คืนข้อมูลฐานข้อมูลเรียบร้อยแล้ว!');
                    this.route();
                } else {
                    alert('รูปแบบไฟล์สำรองข้อมูลไม่ถูกต้อง');
                }
            } catch (err) {
                alert('ไม่สามารถอ่านไฟล์ได้: ' + err.message);
            }
        });
    },

    // =========================================================================
    // 1. LOGIN VIEW
    // =========================================================================
    renderLogin() {
        document.title = 'เข้าสู่ระบบ | ระบบรายงานประสิทธิภาพการเดินงาน BHS ประจำกะ';
        const appRoot = document.getElementById('appRoot');
        appRoot.className = 'bg-light d-flex align-items-center justify-content-center min-vh-100 p-3';
        const flashHtml = this.flash ? `
            <div class="alert alert-${this.esc(this.flash.type)} alert-dismissible fade show fs-7 py-2" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i> ${this.esc(this.flash.message)}
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>` : '';
        this.flash = null;

        appRoot.innerHTML = `
        <div class="card card-theme shadow-lg border-0" style="max-width: 440px; width: 100%; border-top: 5px solid var(--theme-orange) !important;">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-theme-orange text-white rounded-circle shadow-sm mb-3" style="width: 70px; height: 70px;">
                        <i class="fa-solid fa-industry fa-2x"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">BHS Performance</h4>
                    <p class="text-muted fs-7 mb-0">ระบบรายงานประสิทธิภาพการเดินงาน BHS ประจำกะ</p>
                </div>

                ${flashHtml}

                <form id="loginForm" autocomplete="off">
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold fs-7 text-secondary">
                            <i class="fa-solid fa-user me-1 text-orange"></i> ชื่อผู้ใช้งาน (Username)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" placeholder="เช่น admin" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold fs-7 text-secondary">
                            <i class="fa-solid fa-lock me-1 text-orange"></i> รหัสผ่าน (Password)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-key"></i></span>
                            <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="เช่น 123456" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-orange w-100 py-2 rounded-3 shadow-sm mb-3 fw-bold">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> เข้าสู่ระบบ (Login)
                    </button>
                </form>

                <div class="card bg-theme-orange-light border-orange rounded-3 mt-4">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fa-solid fa-circle-info text-orange me-2"></i>
                            <span class="fw-bold text-dark fs-7">บัญชีในระบบ:</span>
                        </div>
                        <ul class="list-unstyled mb-0 fs-8 text-secondary ps-3">
                            <li><i class="fa-solid fa-angle-right text-orange me-1"></i> <strong>Admin:</strong> <code>admin</code> / รหัสผ่าน: <code>123456</code></li>
                            <li><i class="fa-solid fa-angle-right text-orange me-1"></i> <strong>User:</strong> <code>user</code> / รหัสผ่าน: <code>123456</code></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light text-center py-2 border-0">
                <small class="text-muted fs-9">GitHub Cloud Edition | Online 24/7 Unlimited</small>
            </div>
        </div>`;

        document.getElementById('loginForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            const uname = document.getElementById('username').value.trim();
            const pwd = document.getElementById('password').value;

            const user = (BhsDB.state.users || []).find(u => u.username.toLowerCase() === uname.toLowerCase());
            if (!user) {
                this.setFlash('danger', 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
                this.renderLogin();
                return;
            }
            if ((user.status || 'active') !== 'active') {
                this.setFlash('warning', 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
                this.renderLogin();
                return;
            }

            // Check password (support password_plain or default 123456 for known bcrypt accounts)
            let valid = false;
            if (user.password_plain !== undefined) {
                valid = (pwd === user.password_plain);
            } else if (pwd === '123456') {
                valid = true;
            }

            if (!valid) {
                this.setFlash('danger', 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
                this.renderLogin();
                return;
            }

            this.currentUser = {
                id: user.id,
                username: user.username,
                full_name: user.full_name,
                role: user.role
            };
            sessionStorage.setItem('bhs_current_user', JSON.stringify(this.currentUser));
            localStorage.setItem('bhs_current_user', JSON.stringify(this.currentUser));
            this.setFlash('success', `ยินดีต้อนรับเข้าสู่ระบบ, ${user.full_name}`);
            this.navigate('#dashboard');
        });
    },

    // =========================================================================
    // 2. DASHBOARD VIEW
    // =========================================================================
    renderDashboard() {
        const reports = [...(BhsDB.state.bhs_shift_reports || [])].sort((a, b) => {
            if (b.report_date !== a.report_date) return String(b.report_date).localeCompare(String(a.report_date));
            return Number(b.id) - Number(a.id);
        });
        const allRecords = BhsDB.state.bhs_production_records || [];
        const usersMap = {};
        (BhsDB.state.users || []).forEach(u => { usersMap[u.id] = u.full_name; });

        let totalOrders = 0;
        let totalMeters = 0;
        let totalWeight = 0;

        const enrichedReports = reports.map(r => {
            const recs = allRecords.filter(p => Number(p.report_id) === Number(r.id));
            const sumM = recs.reduce((acc, p) => acc + (Number(p.total_length_m) || 0), 0);
            const sumW = recs.reduce((acc, p) => acc + (Number(p.total_weight_kg) || 0), 0);
            totalOrders += recs.length;
            totalMeters += sumM;
            totalWeight += sumW;
            return {
                ...r,
                record_count: recs.length,
                sum_meters: sumM,
                sum_weight: sumW,
                author_name: usersMap[r.created_by] || 'ผู้ดูแลระบบ (Admin BHS)'
            };
        });

        const html = `
        <div class="row align-items-center mb-4">
            <div class="col-md-7">
                <h3 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-gauge-high text-orange me-2"></i> แดชบอร์ดรายงานประสิทธิภาพการเดินงาน BHS
                </h3>
                <p class="text-muted fs-7 mb-0">ระบบติดตามและสรุปผลประสิทธิภาพเครื่องจักรรีดลอนลูกฟูก BHS ประจำแต่ละกะ</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <a href="#upload" class="btn btn-orange rounded-pill px-4 shadow-sm fw-bold">
                    <i class="fa-solid fa-cloud-arrow-up me-2"></i> นำเข้าไฟล์เครื่องจักรใหม่ (Import)
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card card-theme border-0 shadow-sm p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-3 bg-theme-orange-light p-3 text-orange me-3">
                            <i class="fa-solid fa-file-waveform fa-2x"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-8 fw-semibold">จำนวนรอบรายงาน</span>
                            <h4 class="fw-bold mb-0 text-dark">${this.numFmt(reports.length)}</h4>
                            <span class="fs-9 text-secondary">รอบการเดินงาน</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-theme border-0 shadow-sm p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-3 bg-primary bg-opacity-10 p-3 text-primary me-3">
                            <i class="fa-solid fa-boxes-stacked fa-2x"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-8 fw-semibold">ออเดอร์สะสมทั้งหมด</span>
                            <h4 class="fw-bold mb-0 text-dark">${this.numFmt(totalOrders)}</h4>
                            <span class="fs-9 text-secondary">ออเดอร์</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-theme border-0 shadow-sm p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-3 bg-success bg-opacity-10 p-3 text-success me-3">
                            <i class="fa-solid fa-ruler-combined fa-2x"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-8 fw-semibold">ความยาวผลิตสะสม</span>
                            <h4 class="fw-bold mb-0 text-dark">${this.numFmt(totalMeters)}</h4>
                            <span class="fs-9 text-secondary">เมตร</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-theme border-0 shadow-sm p-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-3 bg-warning bg-opacity-10 p-3 text-warning me-3">
                            <i class="fa-solid fa-weight-hanging fa-2x"></i>
                        </div>
                        <div>
                            <span class="text-muted fs-8 fw-semibold">น้ำหนักกระดาษสะสม</span>
                            <h4 class="fw-bold mb-0 text-dark">${this.numFmt(totalWeight)}</h4>
                            <span class="fs-9 text-secondary">กิโลกรัม</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-theme shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-6 text-dark">
                    <i class="fa-solid fa-table-list text-orange me-2"></i> รายการรายงานการเดินงาน BHS ทั้งหมด
                </span>
                <span class="badge bg-theme-orange text-white fs-8">${reports.length} รายงาน</span>
            </div>
            <div class="card-body p-0">
                ${enrichedReports.length === 0 ? `
                <div class="text-center py-5">
                    <div class="mb-3 text-muted">
                        <i class="fa-solid fa-file-circle-question fa-4x text-orange opacity-50"></i>
                    </div>
                    <h5 class="fw-bold text-dark">ยังไม่มีข้อมูลรายงานในระบบ</h5>
                    <p class="text-muted fs-7 mb-3">กรุณานำเข้าไฟล์ข้อมูลจากเครื่องจักร BHS เพื่อเริ่มต้นสร้างรายงาน</p>
                    <a href="#upload" class="btn btn-orange rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> นำเข้าไฟล์แรกเลย
                    </a>
                </div>
                ` : `
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-nowrap">
                        <thead class="table-light fs-8 text-secondary">
                            <tr>
                                <th class="ps-4">ชื่อรายงาน</th>
                                <th class="text-center">เครื่องจักร</th>
                                <th class="text-center">กะ (Shift)</th>
                                <th class="text-center">วันที่ผลิต</th>
                                <th class="text-center">เวลาตัดกะ</th>
                                <th class="text-center">จำนวนออเดอร์</th>
                                <th class="text-end">ความยาวรวม (เมตร)</th>
                                <th class="text-end">น้ำหนักรวม (กก.)</th>
                                <th>ผู้นำเข้า</th>
                                <th class="text-center pe-4" style="width: 180px;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${enrichedReports.map(rep => {
                                const mName = rep.machine_name || 'BHS';
                                const mBadge = mName === 'YUELI' ? 'bg-success' : (mName === 'ISOWA' ? 'bg-dark' : 'bg-primary');
                                return `
                                <tr>
                                    <td class="ps-4">
                                        <a href="#report_view?id=${rep.id}" class="fw-bold text-dark text-decoration-none hover-orange">
                                            <i class="fa-solid fa-file-lines text-orange me-2"></i>${this.esc(rep.title)}
                                        </a>
                                        ${rep.raw_filename ? `<small class="d-block text-muted fs-9 ps-4">ไฟล์: ${this.esc(rep.raw_filename)}</small>` : ''}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge ${mBadge} px-3 py-1 rounded-pill fw-bold">${this.esc(mName)}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold">กะ ${this.esc(rep.shift)}</span>
                                    </td>
                                    <td class="text-center fw-semibold text-secondary">
                                        ${BhsEngine.formatThaiDate(rep.report_date)}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">${this.esc(rep.shift_end_time)}</span>
                                    </td>
                                    <td class="text-center fw-bold">${this.numFmt(rep.record_count)}</td>
                                    <td class="text-end fw-bold text-primary">${this.numFmt(rep.sum_meters)}</td>
                                    <td class="text-end fw-bold text-success">${this.numFmt(rep.sum_weight)}</td>
                                    <td class="fs-8 text-muted">
                                        <i class="fa-regular fa-user me-1"></i> ${this.esc(rep.author_name)}
                                    </td>
                                    <td class="text-center pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="#report_view?id=${rep.id}" class="btn btn-outline-orange">
                                                <i class="fa-solid fa-eye me-1"></i> ดูรายงาน
                                            </a>
                                            ${this.isAdmin() ? `
                                            <button type="button" class="btn btn-outline-danger btn-del-report" data-id="${rep.id}" data-title="${this.esc(rep.title)}">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                            ` : ''}
                                        </div>
                                    </td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
                `}
            </div>
        </div>`;

        this.renderShell('dashboard', 'แดชบอร์ดรายงานประจำกะ', html);

        document.querySelectorAll('.btn-del-report').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = Number(btn.getAttribute('data-id'));
                const title = btn.getAttribute('data-title');
                if (confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบรายงาน "${title}"?\nข้อมูลการผลิตทั้งหมดในรายงานนี้จะถูกลบออกถาวร`)) {
                    BhsDB.state.bhs_shift_reports = BhsDB.state.bhs_shift_reports.filter(r => Number(r.id) !== id);
                    BhsDB.state.bhs_production_records = BhsDB.state.bhs_production_records.filter(p => Number(p.report_id) !== id);
                    await BhsDB.save();
                    this.setFlash('success', 'ลบรายงานเรียบร้อยแล้ว');
                    this.renderDashboard();
                }
            });
        });
    },

    // =========================================================================
    // 3. UPLOAD / IMPORT VIEW
    // =========================================================================
    getUploadHtml() {
        const today = new Date().toISOString().slice(0, 10);
        const stations = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];
        const stColors = ['#e8f5e9', '#e3f2fd', '#fff3e0', '#f3e5f5', '#fce4ec'];
        const lossTypes = [
            { key: 'loss_person',   label: 'ผลิต',        icon: 'fa-industry',      color: 'text-primary' },
            { key: 'loss_mc',       label: 'เครื่องจักร', icon: 'fa-wrench',        color: 'text-danger' },
            { key: 'loss_material', label: 'วัตถุดิบ',    icon: 'fa-boxes-stacked', color: 'text-info' },
            { key: 'loss_other',    label: 'อื่นๆ',        icon: 'fa-circle-dot',    color: 'text-secondary' },
        ];

        return `
        <div class="row mb-3 align-items-center">
            <div class="col-md-8">
                <h3 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-cloud-arrow-up text-orange me-2"></i> นำเข้าไฟล์ข้อมูลเครื่องจักร (Import Data)
                </h3>
                <p class="text-muted fs-7 mb-0">เลือกเครื่องจักร กะ และวันที่เดินงาน จากนั้นโยนไฟล์หรือวางโค้ด HTML เพื่อคำนวณรายงานประสิทธิภาพ</p>
            </div>
            <div class="col-md-4 text-md-end mt-2 mt-md-0">
                <a href="#dashboard" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> กลับหน้ารายการ
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-theme shadow-sm border-0">
                    <div class="card-header card-header-theme d-flex justify-content-between align-items-center">
                        <span class="fs-6 fw-bold"><i class="fa-solid fa-sliders text-orange me-2"></i> 1. กำหนดข้อมูลการเดินงาน และ 2. เลือกไฟล์</span>
                        <span class="badge bg-theme-orange text-white fs-8">BHS / YUELI / ISOWA</span>
                    </div>
                    <div class="card-body p-4">
                        <form id="mainUploadForm">
                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold fs-7 text-dark mb-2">
                                            <i class="fa-solid fa-gears text-orange me-1"></i> 1. เลือกเครื่องจักร (Machine) *
                                        </label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="machine_name" id="machineBHS" value="BHS" checked>
                                            <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="machineBHS"><i class="fa-solid fa-industry me-1"></i> BHS</label>
                                            <input type="radio" class="btn-check" name="machine_name" id="machineYUELI" value="YUELI">
                                            <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="machineYUELI"><i class="fa-solid fa-gear me-1"></i> YUELI</label>
                                            <input type="radio" class="btn-check" name="machine_name" id="machineISOWA" value="ISOWA">
                                            <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="machineISOWA"><i class="fa-solid fa-cogs me-1"></i> ISOWA</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold fs-7 text-dark mb-2">
                                            <i class="fa-solid fa-user-clock text-orange me-1"></i> 2. เลือกกะ (Shift) *
                                        </label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="shift" id="shiftA" value="A">
                                            <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="shiftA">กะ A</label>
                                            <input type="radio" class="btn-check" name="shift" id="shiftB" value="B" checked>
                                            <label class="btn btn-outline-orange py-2 fs-7 fw-bold" for="shiftB">กะ B</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-7 text-dark mb-2">
                                            <i class="fa-regular fa-calendar-days text-orange me-1"></i> 3. วันที่เดินงาน *
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white text-orange border-end-0"><i class="fa-solid fa-calendar-day"></i></span>
                                            <input type="date" name="report_date" id="report_date" class="form-control border-start-0 ps-1" value="${today}" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 align-items-center mt-1 pt-2 border-top">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-7 text-dark mb-1">
                                            <i class="fa-regular fa-clock text-orange me-1"></i> 4. เวลาเริ่มเดิน (24 ชม.) *
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white text-orange border-end-0"><i class="fa-solid fa-play fa-xs"></i></span>
                                            <input type="text" name="shift_start_time" id="shift_start_time" list="time24List" class="form-control border-start-0 ps-1 fw-bold text-center" value="20:00" placeholder="เช่น 08:00 หรือ 20:00" maxlength="5" required>
                                            <span class="input-group-text bg-light text-secondary fs-8">น.</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-7 text-dark mb-1">
                                            <i class="fa-regular fa-clock text-orange me-1"></i> 5. เวลาจบ (24 ชม.) *
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white text-orange border-end-0"><i class="fa-solid fa-stop fa-xs"></i></span>
                                            <input type="text" name="shift_end_time" id="shift_end_time" list="time24List" class="form-control border-start-0 ps-1 fw-bold text-center" value="08:00" placeholder="เช่น 16:00 หรือ 20:00" maxlength="5" required>
                                            <span class="input-group-text bg-light text-secondary fs-8">น.</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-7 text-dark mb-1">
                                            <i class="fa-solid fa-stopwatch text-orange me-1"></i> เวลาเดินทั้งหมด (เสมอ)
                                        </label>
                                        <div class="alert alert-warning py-1 px-3 mb-0 border rounded-3 d-flex align-items-center justify-content-between">
                                            <span class="fs-8 fw-semibold text-dark">คำนวณอัตโนมัติ:</span>
                                            <span class="fs-6 fw-bold text-danger" id="displayShiftDuration">720 นาที</span>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="fs-8 text-secondary fw-semibold"><i class="fa-solid fa-bolt text-orange me-1"></i> เลือกช่วงเวลาด่วน (24 ชม.):</span>
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-time-preset" data-start="08:00" data-end="16:00" data-shift="A">08:00 - 16:00 (8 ชม.)</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-time-preset" data-start="08:00" data-end="20:00" data-shift="A">08:00 - 20:00 (12 ชม.)</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-time-preset" data-start="20:00" data-end="08:00" data-shift="B">20:00 - 08:00 (12 ชม.)</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-time-preset" data-start="20:00" data-end="07:40" data-shift="B">20:00 - 07:40 (700 น.)</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-8 btn-time-preset" data-start="16:00" data-end="24:00" data-shift="B">16:00 - 24:00 (8 ชม.)</button>
                                        </div>
                                        <datalist id="time24List">
                                            <option value="00:00"><option value="06:00"><option value="07:00"><option value="07:30"><option value="07:40">
                                            <option value="08:00"><option value="12:00"><option value="16:00"><option value="16:30"><option value="17:00">
                                            <option value="19:30"><option value="19:40"><option value="20:00"><option value="23:30"><option value="24:00">
                                        </datalist>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-clipboard-list text-orange me-2 fs-6"></i>
                                    <span class="fw-bold fs-7 text-dark">3. ข้อมูลเพิ่มเติม (กรอกหรือไม่ก็ได้)</span>
                                    <span class="badge bg-secondary ms-2 fs-9">Optional</span>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-8 text-dark mb-1">
                                            <i class="fa-solid fa-flag-checkered text-success me-1"></i> Order เริ่มเดิน (Start)
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-success text-white border-0"><i class="fa-solid fa-play fa-xs"></i></span>
                                            <input type="text" name="order_start" id="order_start" class="form-control form-control-sm" placeholder="เช่น 10">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-8 text-dark mb-1">
                                            <i class="fa-solid fa-flag text-danger me-1"></i> Order เดินจบ (Finit)
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-danger text-white border-0"><i class="fa-solid fa-stop fa-xs"></i></span>
                                            <input type="text" name="order_finit" id="order_finit" class="form-control form-control-sm" placeholder="เช่น 1120">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-8 text-dark mb-1">
                                            <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> งานขาดจำนวน (Shortage Orders)
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="shortage_orders" id="shortage_orders" class="form-control form-control-sm text-center" placeholder="0" min="0">
                                            <span class="input-group-text bg-light text-secondary">Order</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-top pt-3 mb-3">
                                    <div class="fw-bold fs-8 text-secondary mb-2">
                                        <i class="fa-solid fa-clock-rotate-left text-warning me-1"></i> เวลาสูญเสีย (Loss Time) — กรอกครั้ง/นาที
                                    </div>
                                    <div class="row g-2">
                                        ${lossTypes.map(lt => `
                                        <div class="col-md-3 col-6">
                                            <div class="bg-white rounded border p-2">
                                                <div class="fw-bold fs-9 mb-1 ${lt.color}">
                                                    <i class="fa-solid ${lt.icon} me-1"></i>${lt.label}
                                                </div>
                                                <div class="row g-1">
                                                    <div class="col-6">
                                                        <label class="form-label fs-9 text-muted mb-0">ครั้ง</label>
                                                        <input type="number" name="${lt.key}_occ" class="form-control form-control-sm text-center p-1" placeholder="0" min="0" style="font-size:0.78rem;">
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label fs-9 text-muted mb-0">นาที</label>
                                                        <input type="number" name="${lt.key}_min" class="form-control form-control-sm text-center p-1" placeholder="0" min="0" style="font-size:0.78rem;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>`).join('')}
                                    </div>
                                </div>

                                <div class="border-top pt-3">
                                    <div class="fw-bold fs-8 text-secondary mb-2">
                                        <i class="fa-solid fa-trash-can text-primary me-1"></i> น้ำหนักของเสียแยก Station (KG)
                                    </div>
                                    <div class="row g-2">
                                        ${stations.map((st, si) => `
                                        <div class="col">
                                            <div class="rounded border p-2 text-center" style="background:${stColors[si]};">
                                                <div class="fw-bold fs-9 mb-1" style="color:#1f4e79;">${st}</div>
                                                <input type="number" name="station_waste_${st}" class="form-control form-control-sm text-center fw-bold p-1" placeholder="0" min="0" step="any" style="font-size:0.85rem; background:transparent; border-color:#ccc;">
                                                <div class="fs-9 text-muted mt-1">KG</div>
                                            </div>
                                        </div>`).join('')}
                                    </div>
                                </div>
                            </div>

                            <label class="form-label fw-bold fs-7 text-dark mb-2">
                                <i class="fa-solid fa-file-arrow-up text-orange me-1"></i> 4. เลือกวิธีการนำเข้าข้อมูลไฟล์
                            </label>

                            <ul class="nav nav-pills mb-3 nav-justified bg-light p-1 rounded-3" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active rounded-3 fw-semibold py-2" data-bs-toggle="pill" data-bs-target="#tabFile" type="button" role="tab">
                                        <i class="fa-solid fa-file-arrow-up me-1"></i> โยนไฟล์ข้อมูล (File Upload)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link rounded-3 fw-semibold py-2" data-bs-toggle="pill" data-bs-target="#tabPaste" type="button" role="tab">
                                        <i class="fa-solid fa-paste me-1"></i> คัดลอกและวางโค้ด (Paste HTML)
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="tabFile" role="tabpanel">
                                    <div class="upload-dropzone mb-3" id="uploadDropzone">
                                        <div class="mb-3"><i class="fa-solid fa-cloud-arrow-up fa-3x text-orange"></i></div>
                                        <h5 class="fw-bold text-dark mb-1">ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</h5>
                                        <p class="text-muted fs-7 mb-2">รองรับไฟล์รายงานเครื่องจักรทั้ง Excel (<code>.xlsx</code>, <code>.xls</code>) และ <code>.html</code>, <code>.htm</code>, <code>.txt</code></p>
                                        <input type="file" name="report_file" id="report_file" class="d-none" accept=".xlsx,.xls,.html,.htm,.txt">
                                        <div id="selectedFileName" class="mt-2"></div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="tabPaste" role="tabpanel">
                                    <div class="mb-3">
                                        <textarea name="pasted_html" id="pasted_html" rows="9" class="form-control font-monospace fs-8" placeholder="วางโค้ด HTML เริ่มตั้งแต่ <table> หรือ <tr> ของตารางเครื่องจักรที่นี่..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" id="btnSubmitUpload" class="btn btn-orange py-2 rounded-3 shadow-sm fw-bold fs-6">
                                    <i class="fa-solid fa-microchip me-2"></i> ประมวลผลและสร้างรายงานประสิทธิภาพ
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-theme shadow-sm border-0 mb-4 bg-theme-orange-light border-orange">
                    <div class="card-body p-4 text-center">
                        <div class="badge bg-theme-orange text-white mb-2 px-3 py-1 rounded-pill">ข้อมูลตัวอย่างพร้อมใช้</div>
                        <h5 class="fw-bold text-dark mb-2">ทดสอบด้วยไฟล์ testing (23)</h5>
                        <p class="text-muted fs-7 mb-3">
                            ระบบได้เตรียมไฟล์ข้อมูลเครื่องจักร <code>testing (23)</code> ไว้ให้เรียบร้อยแล้ว คลิกปุ่มด้านล่างเพื่อนำเข้าข้อมูลตัวอย่างตามเครื่องจักรและกะที่เลือกไว้ได้ทันที
                        </p>
                        <button type="button" id="btnUseSample" class="btn btn-orange w-100 py-2 rounded-pill shadow-sm fw-bold">
                            <i class="fa-solid fa-bolt me-2"></i> โหลดข้อมูลตัวอย่าง testing (23) ทันที
                        </button>
                    </div>
                </div>

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
                            <li class="mb-2"><strong class="text-dark"><i class="fa-solid fa-clock text-orange me-1"></i> กะการทำงาน:</strong> กะ A (กะเช้า) / กะ B (กะดึก)</li>
                            <li class="mb-0"><strong class="text-dark"><i class="fa-solid fa-circle-check text-success me-1"></i> การประมวลผล:</strong> คำนวณอัตโนมัติทั้งเมตรกระดาษ, GSM น้ำหนัก (M1-M5), แยกลอน, Trim และเรนเดอร์กราฟ</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>`;
    },

    bindUploadEvents() {
        const startInp = document.getElementById('shift_start_time');
        const endInp = document.getElementById('shift_end_time');
        const dispDur = document.getElementById('displayShiftDuration');

        const updateDur = () => {
            const mins = BhsEngine.calculateShiftDurationMinutes(startInp?.value, endInp?.value);
            if (dispDur) dispDur.textContent = `${mins} นาที`;
        };

        document.querySelectorAll('input[name="shift"]').forEach(el => {
            el.addEventListener('change', function () {
                if (this.value === 'A') {
                    if (startInp) startInp.value = '08:00';
                    if (endInp) endInp.value = '20:00';
                } else {
                    if (startInp) startInp.value = '20:00';
                    if (endInp) endInp.value = '08:00';
                }
                updateDur();
            });
        });

        document.querySelectorAll('.btn-time-preset').forEach(btn => {
            btn.addEventListener('click', () => {
                const s = btn.getAttribute('data-start');
                const e = btn.getAttribute('data-end');
                const sh = btn.getAttribute('data-shift');
                if (startInp && s) startInp.value = s;
                if (endInp && e) endInp.value = e;
                if (sh) {
                    const radio = document.querySelector(`input[name="shift"][value="${sh}"]`);
                    if (radio) radio.checked = true;
                }
                updateDur();
            });
        });

        startInp?.addEventListener('input', updateDur);
        endInp?.addEventListener('input', updateDur);
        startInp?.addEventListener('change', updateDur);
        endInp?.addEventListener('change', updateDur);
        updateDur();

        const dropzone = document.getElementById('uploadDropzone');
        const fileInput = document.getElementById('report_file');
        const selectedFileName = document.getElementById('selectedFileName');

        if (dropzone && fileInput) {
            dropzone.addEventListener('click', () => fileInput.click());
            ['dragenter', 'dragover'].forEach(ev => {
                dropzone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropzone.classList.add('dragover'); });
            });
            ['dragleave', 'drop'].forEach(ev => {
                dropzone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropzone.classList.remove('dragover'); });
            });
            dropzone.addEventListener('drop', e => {
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    selectedFileName.innerHTML = `<span class="badge bg-success fs-7 p-2"><i class="fa-solid fa-file-code me-1"></i> เลือกไฟล์แล้ว: ${this.esc(fileInput.files[0].name)}</span>`;
                }
            });
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    selectedFileName.innerHTML = `<span class="badge bg-success fs-7 p-2"><i class="fa-solid fa-file-code me-1"></i> เลือกไฟล์แล้ว: ${this.esc(fileInput.files[0].name)}</span>`;
                }
            });
        }

        document.getElementById('mainUploadForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleProcessUpload(false);
        });

        document.getElementById('btnUseSample')?.addEventListener('click', async () => {
            await this.handleProcessUpload(true);
        });
    },

    async handleProcessUpload(useSample = false) {
        const form = document.getElementById('mainUploadForm');
        const btn = document.getElementById('btnSubmitUpload');
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> กำลังประมวลผลข้อมูล...';
        }

        try {
            let parsedResult = null;
            let rawFilename = 'testing (23).html';
            const fileInput = document.getElementById('report_file');
            const pastedHtml = document.getElementById('pasted_html')?.value?.trim() || '';

            if (useSample) {
                const resp = await fetch(`${this.basePath}/assets/data/sample_testing23.html`);
                const rawHtml = await resp.text();
                parsedResult = BhsEngine.parseHtmlTable(rawHtml);
                rawFilename = 'testing (23).html';
            } else if (fileInput && fileInput.files.length > 0) {
                const file = fileInput.files[0];
                rawFilename = file.name;
                const buf = await file.arrayBuffer();
                const bytes = new Uint8Array(buf.slice(0, 4));
                const isZip = (bytes[0] === 0x50 && bytes[1] === 0x4B);
                const ext = rawFilename.split('.').pop().toLowerCase();

                if (isZip || ext === 'xlsx') {
                    parsedResult = BhsEngine.parseExcelBuffer(buf);
                } else {
                    const text = new TextDecoder('utf-8').decode(buf);
                    if (text.toLowerCase().includes('<table') || text.toLowerCase().includes('<tr')) {
                        parsedResult = BhsEngine.parseHtmlTable(text);
                    } else {
                        parsedResult = BhsEngine.parseExcelBuffer(buf);
                    }
                }
            } else if (pastedHtml) {
                rawFilename = `pasted_html_${new Date().toISOString().slice(0, 10)}.html`;
                parsedResult = BhsEngine.parseHtmlTable(pastedHtml);
            } else {
                throw new Error('กรุณาเลือกไฟล์ Excel (.xlsx) หรือไฟล์ตาราง HTML หรือวางโค้ดตารางเครื่องจักร');
            }

            if (!parsedResult || !parsedResult.parsedRows || parsedResult.parsedRows.length === 0) {
                throw new Error('ไม่พบรายการข้อมูลการผลิตที่สมบูรณ์ในไฟล์');
            }

            const fd = new FormData(form);
            const machineName = (fd.get('machine_name') || 'BHS').toUpperCase();
            const primaryShift = (fd.get('shift') || 'B').toUpperCase();
            const reportDate = fd.get('report_date') || parsedResult.earliestDate || new Date().toISOString().slice(0, 10);
            const shiftStartTime = fd.get('shift_start_time') || (primaryShift === 'A' ? '08:00' : '20:00');
            const shiftEndTime = fd.get('shift_end_time') || (primaryShift === 'A' ? '20:00' : '07:40');

            let actualTimeTotal = BhsEngine.calculateShiftDurationMinutes(shiftStartTime, shiftEndTime);
            if (parsedResult.reliveData?.shift_duration && actualTimeTotal === 720) {
                actualTimeTotal = parsedResult.reliveData.shift_duration;
            }

            // Get template defaults
            const tpl = (BhsDB.state.bhs_target_templates || [])[0] || {};
            const targetOrders = parseInt(tpl.target_orders || 120, 10);
            const targetMeters = parseFloat(tpl.target_meters || 68056.00);
            const targetSpeed = parseFloat(tpl.target_speed || 100.00);
            const targetAvgMeters = parseFloat(tpl.target_avg_meters || 500.00);
            const targetTimeTotal = parseInt(tpl.target_time_total || 720, 10);
            const targetTimeRun = parseInt(tpl.target_time_run || 675, 10);
            const targetLossPct = parseFloat(tpl.target_loss_pct || 10.00);

            let defaultLoss = {};
            if (tpl.loss_departments) {
                try {
                    const arr = typeof tpl.loss_departments === 'string' ? JSON.parse(tpl.loss_departments) : tpl.loss_departments;
                    if (Array.isArray(arr)) {
                        arr.forEach(d => {
                            if (d && d.name && (d.is_active === undefined || Number(d.is_active) === 1)) {
                                defaultLoss[d.name] = { target_pct: parseFloat(d.target_pct || 0), occurrences: 0, minutes: 0, score: 3 };
                            }
                        });
                    }
                } catch (e) {}
            }
            if (Object.keys(defaultLoss).length === 0) {
                defaultLoss = BhsEngine.getDefaultLossDepartments();
            }

            // Map 4 loss types
            const lossMapping = {
                loss_person:   ['คน', 'ผลิต', 'person', 'people'],
                loss_mc:       ['เครื่องจักร', 'MC', 'machine', 'เครื่อง'],
                loss_material: ['วัตถุดิบ', 'ม้วน', 'material', 'คลัง'],
                loss_other:    ['อื่นๆ', 'other', 'อื่น'],
            };

            Object.entries(lossMapping).forEach(([key, keywords]) => {
                let occ = fd.get(`${key}_occ`) !== '' ? parseInt(fd.get(`${key}_occ`), 10) : null;
                let min = fd.get(`${key}_min`) !== '' ? parseInt(fd.get(`${key}_min`), 10) : null;
                if ((!occ) && (!min) && parsedResult.reliveData?.losses?.[key]) {
                    occ = parsedResult.reliveData.losses[key].occ;
                    min = parsedResult.reliveData.losses[key].min;
                }
                occ = Math.max(0, parseInt(occ || 0, 10));
                min = Math.max(0, parseInt(min || 0, 10));
                if (occ <= 0 && min <= 0) return;

                let matched = Object.keys(defaultLoss).find(deptName =>
                    keywords.some(kw => deptName.toLowerCase().includes(kw.toLowerCase()))
                );
                if (matched) {
                    defaultLoss[matched].occurrences += occ;
                    defaultLoss[matched].minutes += min;
                    if (min > 0) defaultLoss[matched].score = 1;
                }
            });

            const stationsUpload = ['SF-C', 'SF-B', 'DF', 'Control', 'Stacker'];
            const stationWasteUpload = {};
            stationsUpload.forEach(st => {
                stationWasteUpload[st] = { kg: Math.max(0, parseFloat(fd.get(`station_waste_${st}`) || 0)) };
            });

            const maxRepId = (BhsDB.state.bhs_shift_reports || []).reduce((m, r) => Math.max(m, Number(r.id) || 0), 0);
            const newReportId = maxRepId + 1;

            const newReport = {
                id: newReportId,
                title: `รายงานประสิทธิภาพการเดินงาน ${machineName} กะ ${primaryShift}`,
                machine_name: machineName,
                shift: primaryShift,
                report_date: reportDate,
                shift_start_time: shiftStartTime,
                shift_end_time: shiftEndTime,
                actual_time_total: actualTimeTotal,
                order_start: parseInt(fd.get('order_start') || parsedResult.reliveData?.order_start || 10, 10) || 10,
                order_finit: parseInt(fd.get('order_finit') || parsedResult.reliveData?.order_finit || 1120, 10) || 1120,
                order_plan_total: 120,
                inserted_meters: 0,
                pending_meters: 0,
                shortage_orders: Math.max(0, parseInt(fd.get('shortage_orders') || 0, 10)),
                station_waste: JSON.stringify(stationWasteUpload),
                target_orders: targetOrders,
                target_meters: targetMeters,
                target_speed: targetSpeed,
                target_avg_meters: targetAvgMeters,
                target_time_total: targetTimeTotal,
                target_time_run: targetTimeRun,
                target_loss_pct: targetLossPct,
                loss_details: JSON.stringify(defaultLoss),
                summary_notes: 'ผลการเดินงานโดยรวมปกติ เดินตามแผนงาน',
                remarks: 'เครื่องจักรทำงานเรียบร้อย',
                raw_filename: rawFilename,
                created_by: this.currentUser?.id || 1,
                created_at: new Date().toISOString().replace('T', ' ').substring(0, 19)
            };

            BhsDB.state.bhs_shift_reports.push(newReport);

            let maxRecId = (BhsDB.state.bhs_production_records || []).reduce((m, r) => Math.max(m, Number(r.id) || 0), 0);
            parsedResult.parsedRows.forEach(r => {
                maxRecId++;
                BhsDB.state.bhs_production_records.push({
                    id: maxRecId,
                    report_id: newReportId,
                    ...r
                });
            });

            await BhsDB.save();
            BhsDB.syncReportToGoogleSheet(newReportId).catch(() => {});

            this.setFlash('success', `นำเข้าข้อมูลเครื่องจักรเรียบร้อยแล้ว จำนวน ${parsedResult.parsedRows.length} รายการ (กะ ${primaryShift})`);
            this.navigate(`#report_view?id=${newReportId}`);
        } catch (err) {
            alert('เกิดข้อผิดพลาดในการประมวลผล: ' + err.message);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    }
};

window.BhsApp = BhsApp;
