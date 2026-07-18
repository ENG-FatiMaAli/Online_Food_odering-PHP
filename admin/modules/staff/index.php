<?php
$pageTitle = 'Staff Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';

if (isPost() && !verifyCSRF()) {
    setFlash('error', 'Invalid security token. Please try again.');
    redirect(url('admin/modules/staff/index.php'));
}

if (isPost()) {
    $actionPost = $_POST['action'] ?? '';

    // ── CREATE ──
    if ($actionPost === 'add') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $password   = $_POST['password'] ?? '';
        $is_active  = isset($_POST['is_active']) ? 1 : 0;
        $position   = trim($_POST['position'] ?? '');
        $hire_date  = trim($_POST['hire_date'] ?? '');
        $salary     = trim($_POST['salary'] ?? '');

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
            setFlash('error', 'Please fill in all required fields.');
            redirect(url('admin/modules/staff/index.php?action=add'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            redirect(url('admin/modules/staff/index.php?action=add'));
        }

        $exists = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($exists) {
            setFlash('error', 'A user with this email already exists.');
            redirect(url('admin/modules/staff/index.php?action=add'));
        }

        $userId = Database::insert('users', [
            'role_id'    => 2,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'phone'      => $phone,
            'is_active'  => $is_active,
        ]);

        Database::insert('staff_profiles', [
            'user_id'   => $userId,
            'position'  => $position,
            'hire_date' => $hire_date ?: date('Y-m-d'),
            'salary'    => $salary !== '' ? (float)$salary : 0,
        ]);

        logActivity('staff_created', "Created staff: {$email}");
        setFlash('success', 'Staff member created successfully.');
        redirect(url('admin/modules/staff/index.php'));
    }

    // ── UPDATE ──
    if ($actionPost === 'edit') {
        $id         = (int)($_POST['staff_id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $password   = $_POST['password'] ?? '';
        $is_active  = isset($_POST['is_active']) ? 1 : 0;
        $position   = trim($_POST['position'] ?? '');
        $hire_date  = trim($_POST['hire_date'] ?? '');
        $salary     = trim($_POST['salary'] ?? '');

        if ($id < 1 || $first_name === '' || $last_name === '' || $email === '') {
            setFlash('error', 'Please fill in all required fields.');
            redirect(url('admin/modules/staff/index.php?action=edit&id=' . $id));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            redirect(url('admin/modules/staff/index.php?action=edit&id=' . $id));
        }

        $exists = Database::fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
        if ($exists) {
            setFlash('error', 'A user with this email already exists.');
            redirect(url('admin/modules/staff/index.php?action=edit&id=' . $id));
        }

        $data = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
            'phone'      => $phone,
            'is_active'  => $is_active,
        ];

        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', $data, 'id = ?', [$id]);

        $hasProfile = Database::count('staff_profiles', 'user_id = ?', [$id]);
        if ($hasProfile) {
            Database::update('staff_profiles', [
                'position'  => $position,
                'hire_date' => $hire_date,
                'salary'    => $salary !== '' ? (float)$salary : 0,
            ], 'user_id = ?', [$id]);
        } else {
            Database::insert('staff_profiles', [
                'user_id'   => $id,
                'position'  => $position,
                'hire_date' => $hire_date ?: date('Y-m-d'),
                'salary'    => $salary !== '' ? (float)$salary : 0,
            ]);
        }

        logActivity('staff_updated', "Updated staff ID: {$id}");
        setFlash('success', 'Staff member updated successfully.');
        redirect(url('admin/modules/staff/index.php'));
    }

    // ── DELETE ──
    if ($actionPost === 'delete') {
        $id = (int)($_POST['staff_id'] ?? 0);
        $currentUser = currentUser();

        if ($currentUser && (int)$currentUser['id'] === $id) {
            setFlash('error', 'You cannot delete your own account.');
            redirect(url('admin/modules/staff/index.php'));
        }

        $staff = Database::fetch("SELECT id, email FROM users WHERE id = ? AND role_id = 2", [$id]);
        if ($staff) {
            Database::delete('staff_profiles', 'user_id = ?', [$id]);
            Database::delete('users', 'id = ?', [$id]);
            logActivity('staff_deleted', "Deleted staff: {$staff['email']}");
            setFlash('success', 'Staff member deleted successfully.');
        } else {
            setFlash('error', 'Staff member not found.');
        }

        redirect(url('admin/modules/staff/index.php'));
    }

    // ── TOGGLE STATUS ──
    if ($actionPost === 'toggle') {
        $id     = (int)($_POST['staff_id'] ?? 0);
        $staff  = Database::fetch("SELECT id, is_active, email FROM users WHERE id = ? AND role_id = 2", [$id]);
        if ($staff) {
            $newStatus = $staff['is_active'] ? 0 : 1;
            Database::update('users', ['is_active' => $newStatus], 'id = ?', [$id]);
            $label = $newStatus ? 'activated' : 'deactivated';
            logActivity('staff_status_toggled', "Staff {$staff['email']} {$label}");
            setFlash('success', "Staff member {$label} successfully.");
        } else {
            setFlash('error', 'Staff member not found.');
        }

        redirect(url('admin/modules/staff/index.php'));
    }
}

// ─── Add / Edit Form ──────────────────────────────────────────
if ($action === 'add' || $action === 'edit'):
    $editStaff = null;
    $isEdit = $action === 'edit';

    if ($isEdit) {
        $editId = (int)($_GET['id'] ?? 0);
        $editStaff = Database::fetch(
            "SELECT u.*, sp.position, sp.hire_date, sp.salary
             FROM users u
             LEFT JOIN staff_profiles sp ON sp.user_id = u.id
             WHERE u.id = ? AND u.role_id = 2",
            [$editId]
        );
        if (!$editStaff) {
            setFlash('error', 'Staff member not found.');
            redirect(url('admin/modules/staff/index.php'));
        }
    }

    $formTitle = $isEdit ? 'Edit Staff Member' : 'Add New Staff Member';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><?= $formTitle ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('admin/modules/staff/index.php') ?>" class="text-decoration-none">Staff</a></li>
                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Add' ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/staff/index.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<!-- Form Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= url('admin/modules/staff/index.php') ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="staff_id" value="<?= $editStaff['id'] ?>">
            <?php endif; ?>

            <!-- Personal Information -->
            <h6 class="fw-bold text-uppercase small text-muted mb-3">
                <i class="fas fa-user me-1" style="color:var(--primary)"></i> Personal Information
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                           value="<?= sanitize($editStaff['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                           value="<?= sanitize($editStaff['last_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= sanitize($editStaff['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label fw-semibold">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone"
                           value="<?= sanitize($editStaff['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label fw-semibold">
                        Password <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
                    </label>
                    <input type="password" class="form-control" id="password" name="password"
                           <?= $isEdit ? '' : 'required' ?>
                           placeholder="<?= $isEdit ? 'Leave blank to keep current' : '' ?>">
                    <?php if ($isEdit): ?>
                        <small class="text-muted">Leave blank to keep the current password.</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold d-block">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                               value="1" <?= ($editStaff['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <!-- Staff Profile -->
            <h6 class="fw-bold text-uppercase small text-muted mb-3">
                <i class="fas fa-briefcase me-1" style="color:var(--primary)"></i> Employment Details
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="position" class="form-label fw-semibold">Position</label>
                    <input type="text" class="form-control" id="position" name="position"
                           value="<?= sanitize($editStaff['position'] ?? '') ?>"
                           placeholder="e.g. Chef, Cashier, Manager">
                </div>
                <div class="col-md-4">
                    <label for="hire_date" class="form-label fw-semibold">Hire Date</label>
                    <input type="date" class="form-control" id="hire_date" name="hire_date"
                           value="<?= sanitize($editStaff['hire_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label for="salary" class="form-label fw-semibold">Salary</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white">$</span>
                        <input type="number" class="form-control" id="salary" name="salary"
                               value="<?= sanitize($editStaff['salary'] ?? '') ?>"
                               placeholder="0.00" step="0.01" min="0">
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url('admin/modules/staff/index.php') ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Update Staff' : 'Create Staff' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php else: // ─── List View ──────────────────────────────────────

$search        = trim($_GET['search'] ?? '');
$filterPosition = trim($_GET['position'] ?? '');

$where  = 'u.role_id = 2';
$params = [];

if ($search !== '') {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($filterPosition !== '') {
    $where .= " AND sp.position LIKE ?";
    $params[] = "%{$filterPosition}%";
}

$pagination = paginate('users u LEFT JOIN staff_profiles sp ON sp.user_id = u.id', $where, $params, 10);

$staffMembers = Database::fetchAll(
    "SELECT u.*, sp.position, sp.hire_date, sp.salary
     FROM users u
     LEFT JOIN staff_profiles sp ON sp.user_id = u.id
     WHERE {$where}
     ORDER BY u.id DESC
     LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
    $params
);

$queryParams = array_filter(['search' => $search ?: null, 'position' => $filterPosition !== null ? $filterPosition : null]);
$baseUrl = url('admin/modules/staff/index.php') . '?' . http_build_query($queryParams);

$totalStaff   = Database::count('users', 'role_id = 2');
$activeStaff  = Database::count('users', 'role_id = 2 AND is_active = 1');
$inactiveStaff = $totalStaff - $activeStaff;

$avgSalary = Database::fetch("SELECT COALESCE(AVG(sp.salary), 0) AS avg_sal FROM staff_profiles sp JOIN users u ON sp.user_id = u.id WHERE u.role_id = 2 AND sp.salary > 0");
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Staff Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Staff</li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/staff/index.php?action=add') ?>" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Staff
    </a>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,107,53,0.1)">
                        <i class="fas fa-user-tie" style="color:var(--primary);font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Staff</div>
                        <div class="fw-bold fs-5"><?= $totalStaff ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(40,167,69,0.1)">
                        <i class="fas fa-user-check" style="color:#28a745;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Active</div>
                        <div class="fw-bold fs-5"><?= $activeStaff ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(220,53,69,0.1)">
                        <i class="fas fa-user-times" style="color:#dc3545;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Inactive</div>
                        <div class="fw-bold fs-5"><?= $inactiveStaff ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,193,7,0.1)">
                        <i class="fas fa-dollar-sign" style="color:#ffc107;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Avg. Salary</div>
                        <div class="fw-bold fs-5">$<?= number_format((float)($avgSalary['avg_sal'] ?? 0), 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="search" class="form-label fw-semibold small">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Search by name, email or phone..." value="<?= sanitize($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label for="filterPosition" class="form-label fw-semibold small">Position</label>
                <input type="text" class="form-control" id="filterPosition" name="position"
                       placeholder="Filter by position..." value="<?= sanitize($filterPosition) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('admin/modules/staff/index.php') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Staff Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:50px">#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Position</th>
                        <th>Hire Date</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th class="text-end pe-3" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffMembers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-user-tie fa-2x mb-3 d-block opacity-50"></i>
                                No staff members found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffMembers as $i => $s): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $pagination['offset'] + $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($s['avatar'])): ?>
                                            <img src="<?= url('uploads/avatar/' . sanitize($s['avatar'])) ?>"
                                                 alt="" class="rounded-circle" width="32" height="32" style="object-fit:cover">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-semibold"
                                                 style="width:32px;height:32px;font-size:0.75rem;background:linear-gradient(135deg,var(--primary),var(--secondary));">
                                                <?= strtoupper(substr(sanitize($s['first_name'] ?? 'S'), 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="fw-semibold"><?= sanitize($s['first_name'] . ' ' . $s['last_name']) ?></span>
                                    </div>
                                </td>
                                <td><?= sanitize($s['email']) ?></td>
                                <td><?= sanitize($s['phone'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($s['position'])): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">
                                            <?= sanitize($s['position']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?= $s['hire_date'] ? date('M d, Y', strtotime($s['hire_date'])) : '-' ?>
                                </td>
                                <td class="fw-semibold">
                                    <?= $s['salary'] > 0 ? '$' . number_format((float)$s['salary'], 2) : '-' ?>
                                </td>
                                <td>
                                    <?php if ($s['is_active']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <!-- Toggle Status -->
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $s['is_active'] ? 'warning' : 'success' ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $s['is_active'] ? 'ban' : 'check-circle' ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Edit -->
                                        <a href="<?= url('admin/modules/staff/index.php?action=edit&id=' . $s['id']) ?>"
                                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>

                                        <!-- Delete -->
                                        <form method="POST" class="d-inline" id="deleteForm_<?= $s['id'] ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="tooltip" title="Delete"
                                                    onclick="deleteStaff(<?= $s['id'] ?>, '<?= sanitize(addslashes($s['first_name'] . ' ' . $s['last_name'])) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pagination['total'] > $pagination['perPage']): ?>
        <div class="card-footer bg-white border-top-0 pt-0 pb-3">
            <div class="d-flex justify-content-between align-items-center px-3">
                <small class="text-muted">
                    Showing <?= $pagination['offset'] + 1 ?>-<?= min($pagination['offset'] + $pagination['perPage'], $pagination['total']) ?>
                    of <?= $pagination['total'] ?> staff members
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        for ($p = 1; $p <= $pagination['pages']; $p++):
                            $active = $p == $pagination['page'] ? ' active' : '';
                            $pageUrl = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . "page={$p}";
                        ?>
                            <li class="page-item<?= $active ?>">
                                <a class="page-link" href="<?= $pageUrl ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteStaff(id, name) {
    Swal.fire({
        title: 'Delete "' + name + '"?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById('deleteForm_' + id).submit();
        }
    });
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
