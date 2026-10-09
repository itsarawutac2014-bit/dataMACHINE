<?php
/**
 * User Management View (Admin Only)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/auth.php';

require_admin(); // จำกัดสิทธิ์เฉพาะ Admin

$pdo = Database::getConnection();

// ดึงรายชื่อผู้ใช้งานทั้งหมด
$stmt = $pdo->query("SELECT id, username, full_name, role, can_edit, status, created_at, updated_at FROM users ORDER BY id ASC");
$usersList = $stmt->fetchAll();

$pageTitle = 'จัดการผู้ใช้งาน';
$activeNav = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold mb-1 text-dark">
            <i class="fa-solid fa-users-gear text-orange me-2"></i> จัดการผู้ใช้งานในระบบ (User Management)
        </h3>
        <p class="text-muted fs-7 mb-0">เพิ่ม ลบ แก้ไขสิทธิ์ และรีเซ็ตรหัสผ่านของผู้ใช้งาน (เฉพาะผู้ดูแลระบบ)</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-orange rounded-pill px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddUser">
            <i class="fa-solid fa-user-plus me-1"></i> เพิ่มผู้ใช้งานใหม่
        </button>
    </div>
</div>

<!-- Users Table Card -->
<div class="card card-theme shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold fs-6 text-dark">
            <i class="fa-solid fa-address-book text-orange me-2"></i> รายชื่อผู้ใช้งานทั้งหมด
        </span>
        <span class="badge bg-theme-orange text-white fs-8"><?= count($usersList) ?> บัญชี</span>
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
                        <th>สร้างเมื่อ</th>
                        <th class="text-center pe-4" style="width: 150px;">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersList as $u): ?>
                    <tr>
                        <td class="ps-4 text-muted fw-bold">#<?= $u['id'] ?></td>
                        <td>
                            <code><?= e($u['username']) ?></code>
                            <?php if ($u['id'] == current_user()['id']): ?>
                            <span class="badge bg-info text-dark fs-9 ms-1">คุณกำลังใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold text-dark">
                            <?= e($u['full_name']) ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?> px-3 py-1 rounded-pill">
                                <?= $u['role'] === 'admin' ? 'Admin (ผู้ดูแลระบบ)' : 'User (ผู้ใช้ทั่วไป)' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= ($u['status'] ?? 'active') === 'active' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill px-2">
                                <?= ($u['status'] ?? 'active') === 'active' ? 'ใช้งานปกติ' : 'ระงับการใช้' ?>
                            </span>
                        </td>
                        <td class="fs-8 text-muted">
                            <?= $u['created_at'] ? date('d/m/Y H:i', strtotime($u['created_at'])) : '-' ?>
                        </td>
                        <td class="text-center pe-4">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" title="แก้ไขผู้ใช้" 
                                    onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if ($u['id'] != current_user()['id']): ?>
                                <button type="button" class="btn btn-outline-danger" title="ลบผู้ใช้" 
                                    onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= e($u['username']) ?>')">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: เพิ่มผู้ใช้ใหม่ -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalAddUser" tabindex="-1" aria-labelledby="modalAddUserLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= BASE_URL ?>/actions/user_action.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="modal-header bg-theme-orange text-white">
                <h5 class="modal-title fw-bold" id="modalAddUserLabel">
                    <i class="fa-solid fa-user-plus me-2"></i> เพิ่มผู้ใช้งานใหม่
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold fs-7 text-secondary">ชื่อผู้ใช้งาน (Username) *</label>
                    <input type="text" name="username" class="form-control" placeholder="เช่น operator1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold fs-7 text-secondary">ชื่อ - นามสกุล *</label>
                    <input type="text" name="full_name" class="form-control" placeholder="เช่น สมชาย ใจดี" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold fs-7 text-secondary">รหัสผ่านเริ่มต้น *</label>
                    <input type="password" name="password" class="form-control" placeholder="รหัสผ่าน" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold fs-7 text-secondary">สิทธิ์การใช้งาน (Role) *</label>
                    <select name="role" class="form-select" required>
                        <option value="user" selected>ผู้ใช้งานทั่วไป (User)</option>
                        <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-orange btn-sm px-4 fw-bold">
                    <i class="fa-solid fa-save me-1"></i> บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: แก้ไขผู้ใช้งาน -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-labelledby="modalEditUserLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= BASE_URL ?>/actions/user_action.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="user_id" id="editUserId" value="">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="modalEditUserLabel">
                    <i class="fa-solid fa-user-pen me-2"></i> แก้ไขข้อมูลผู้ใช้งาน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold fs-7 text-secondary">ชื่อผู้ใช้งาน (Username)</label>
                    <input type="text" id="editUsername" class="form-control bg-light" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold fs-7 text-secondary">ชื่อ - นามสกุล *</label>
                    <input type="text" name="full_name" id="editFullName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold fs-7 text-secondary">เปลี่ยนรหัสผ่านใหม่ (ปล่อยว่างถ้าไม่ต้องการเปลี่ยน)</label>
                    <input type="password" name="new_password" class="form-control" placeholder="กรอกเฉพาะเมื่อต้องการตั้งรหัสใหม่">
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold fs-7 text-secondary">สิทธิ์การใช้งาน (Role)</label>
                        <select name="role" id="editRole" class="form-select">
                            <option value="user">User (ผู้ใช้ทั่วไป)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold fs-7 text-secondary">สถานะบัญชี</label>
                        <select name="status" id="editStatus" class="form-select">
                            <option value="active">ใช้งานปกติ (Active)</option>
                            <option value="inactive">ระงับการใช้งาน (Inactive)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-orange btn-sm px-4 fw-bold">
                    <i class="fa-solid fa-save me-1"></i> บันทึกการแก้ไข
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteUserForm" action="<?= BASE_URL ?>/actions/user_action.php" method="POST" class="d-none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="user_id" id="deleteUserId" value="">
</form>

<script>
function openEditUserModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editFullName').value = user.full_name;
    document.getElementById('editRole').value = user.role;
    document.getElementById('editStatus').value = user.status || 'active';
    
    const editModal = new bootstrap.Modal(document.getElementById('modalEditUser'));
    editModal.show();
}

function confirmDeleteUser(id, username) {
    if (confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ "${username}"?`)) {
        document.getElementById('deleteUserId').value = id;
        document.getElementById('deleteUserForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
