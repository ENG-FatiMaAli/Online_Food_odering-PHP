<?php
$pageTitle = 'Driver Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';

if (isPost() && !verifyCSRF()) {
    setFlash('error', 'Invalid security token. Please try again.');
    redirect(url('admin/modules/drivers/index.php'));
}

if (isPost()) {
    $actionPost = $_POST['action'] ?? '';

    // ── CREATE ──
    if ($actionPost === 'add') {
        $first_name    = trim($_POST['first_name'] ?? '');
        $last_name     = trim($_POST['last_name'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $password      = $_POST['password'] ?? '';
        $is_active     = isset($_POST['is_active']) ? 1 : 0;
        $vehicle_type  = trim($_POST['vehicle_type'] ?? '');
        $license_plate = trim($_POST['license_plate'] ?? '');

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
            setFlash('error', 'Please fill in all required fields.');
            redirect(url('admin/modules/drivers/index.php?action=add'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            redirect(url('admin/modules/drivers/index.php?action=add'));
        }

        $exists = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($exists) {
            setFlash('error', 'A user with this email already exists.');
            redirect(url('admin/modules/drivers/index.php?action=add'));
        }

        $userId = Database::insert('users', [
            'role_id'    => 3,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'phone'      => $phone,
            'is_active'  => $is_active,
        ]);

        Database::insert('driver_profiles', [
            'user_id'       => $userId,
            'vehicle_type'  => $vehicle_type,
            'license_plate' => $license_plate,
            'is_available'  => 1,
        ]);

        logActivity('driver_created', "Created driver: {$email}");
        setFlash('success', 'Driver created successfully.');
        redirect(url('admin/modules/drivers/index.php'));
    }

    // ── UPDATE ──
    if ($actionPost === 'edit') {
        $id            = (int)($_POST['driver_id'] ?? 0);
        $first_name    = trim($_POST['first_name'] ?? '');
        $last_name     = trim($_POST['last_name'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $password      = $_POST['password'] ?? '';
        $is_active     = isset($_POST['is_active']) ? 1 : 0;
        $vehicle_type  = trim($_POST['vehicle_type'] ?? '');
        $license_plate = trim($_POST['license_plate'] ?? '');

        if ($id < 1 || $first_name === '' || $last_name === '' || $email === '') {
            setFlash('error', 'Please fill in all required fields.');
            redirect(url('admin/modules/drivers/index.php?action=edit&id=' . $id));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            redirect(url('admin/modules/drivers/index.php?action=edit&id=' . $id));
        }

        $exists = Database::fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
        if ($exists) {
            setFlash('error', 'A user with this email already exists.');
            redirect(url('admin/modules/drivers/index.php?action=edit&id=' . $id));
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

        $hasProfile = Database::count('driver_profiles', 'user_id = ?', [$id]);
        if ($hasProfile) {
            Database::update('driver_profiles', [
                'vehicle_type'  => $vehicle_type,
                'license_plate' => $license_plate,
            ], 'user_id = ?', [$id]);
        } else {
            Database::insert('driver_profiles', [
                'user_id'       => $id,
                'vehicle_type'  => $vehicle_type,
                'license_plate' => $license_plate,
                'is_available'  => 1,
            ]);
        }

        logActivity('driver_updated', "Updated driver ID: {$id}");
        setFlash('success', 'Driver updated successfully.');
        redirect(url('admin/modules/drivers/index.php'));
    }

    // ── DELETE ──
    if ($actionPost === 'delete') {
        $id = (int)($_POST['driver_id'] ?? 0);

        $driver = Database::fetch("SELECT id, email FROM users WHERE id = ? AND role_id = 3", [$id]);
        if ($driver) {
            Database::delete('driver_profiles', 'user_id = ?', [$id]);
            Database::delete('users', 'id = ?', [$id]);
            logActivity('driver_deleted', "Deleted driver: {$driver['email']}");
            setFlash('success', 'Driver deleted successfully.');
        } else {
            setFlash('error', 'Driver not found.');
        }

        redirect(url('admin/modules/drivers/index.php'));
    }

    // ── TOGGLE STATUS ──
    if ($actionPost === 'toggle') {
        $id     = (int)($_POST['driver_id'] ?? 0);
        $driver = Database::fetch("SELECT id, is_active, email FROM users WHERE id = ? AND role_id = 3", [$id]);
        if ($driver) {
            $newStatus = $driver['is_active'] ? 0 : 1;
            Database::update('users', ['is_active' => $newStatus], 'id = ?', [$id]);
            $label = $newStatus ? 'activated' : 'deactivated';
            logActivity('driver_status_toggled', "Driver {$driver['email']} {$label}");
            setFlash('success', "Driver {$label} successfully.");
        } else {
            setFlash('error', 'Driver not found.');
        }

        redirect(url('admin/modules/drivers/index.php'));
    }

    // ── TOGGLE AVAILABILITY ──
    if ($actionPost === 'toggle_availability') {
        $id     = (int)($_POST['driver_id'] ?? 0);
        $driver = Database::fetch(
            "SELECT dp.id, dp.is_available, u.email
             FROM driver_profiles dp
             JOIN users u ON dp.user_id = u.id
             WHERE dp.user_id = ? AND u.role_id = 3",
            [$id]
        );
        if ($driver) {
            $newAvail = $driver['is_available'] ? 0 : 1;
            Database::update('driver_profiles', ['is_available' => $newAvail], 'user_id = ?', [$id]);
            $label = $newAvail ? 'available' : 'unavailable';
            logActivity('driver_availability_toggled', "Driver {$driver['email']} set as {$label}");
            setFlash('success', "Driver marked as {$label}.");
        } else {
            setFlash('error', 'Driver not found.');
        }

        redirect(url('admin/modules/drivers/index.php'));
    }
}

// ─── Add / Edit Form ──────────────────────────────────────────
if ($action === 'add' || $action === 'edit'):
    $editDriver = null;
    $isEdit = $action === 'edit';

    if ($isEdit) {
        $editId = (int)($_GET['id'] ?? 0);
        $editDriver = Database::fetch(
            "SELECT u.*, dp.vehicle_type, dp.license_plate, dp.is_available, dp.current_latitude, dp.current_longitude
             FROM users u
             LEFT JOIN driver_profiles dp ON dp.user_id = u.id
             WHERE u.id = ? AND u.role_id = 3",
            [$editId]
        );
        if (!$editDriver) {
            setFlash('error', 'Driver not found.');
            redirect(url('admin/modules/drivers/index.php'));
        }
    }

    $formTitle = $isEdit ? 'Edit Driver' : 'Add New Driver';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><?= $formTitle ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('admin/modules/drivers/index.php') ?>" class="text-decoration-none">Drivers</a></li>
                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Add' ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/drivers/index.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<!-- Form Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= url('admin/modules/drivers/index.php') ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="driver_id" value="<?= $editDriver['id'] ?>">
            <?php endif; ?>

            <!-- Personal Information -->
            <h6 class="fw-bold text-uppercase small text-muted mb-3">
                <i class="fas fa-user me-1" style="color:var(--primary)"></i> Personal Information
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                           value="<?= sanitize($editDriver['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                           value="<?= sanitize($editDriver['last_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= sanitize($editDriver['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label fw-semibold">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone"
                           value="<?= sanitize($editDriver['phone'] ?? '') ?>">
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
                    <label class="form-label fw-semibold d-block">Account Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                               value="1" <?= ($editDriver['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <!-- Driver Profile -->
            <h6 class="fw-bold text-uppercase small text-muted mb-3">
                <i class="fas fa-truck me-1" style="color:var(--primary)"></i> Vehicle Details
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="vehicle_type" class="form-label fw-semibold">Vehicle Type</label>
                    <select class="form-select" id="vehicle_type" name="vehicle_type">
                        <option value="">Select vehicle type</option>
                        <?php
                        $vehicleTypes = ['Bicycle', 'Motorcycle', 'Car', 'Van', 'Truck'];
                        $currentVehicle = $editDriver['vehicle_type'] ?? '';
                        foreach ($vehicleTypes as $vt):
                        ?>
                            <option value="<?= $vt ?>" <?= $currentVehicle === $vt ? 'selected' : '' ?>><?= $vt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="license_plate" class="form-label fw-semibold">License Plate</label>
                    <input type="text" class="form-control" id="license_plate" name="license_plate"
                           value="<?= sanitize($editDriver['license_plate'] ?? '') ?>"
                           placeholder="e.g. ABC-1234">
                </div>
                <?php if ($isEdit && !empty($editDriver['current_latitude']) && !empty($editDriver['current_longitude'])): ?>
                    <div class="col-12">
                        <div class="alert alert-light border mb-0 py-2">
                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                            <strong>Last Known Location:</strong>
                            <?= number_format((float)$editDriver['current_latitude'], 6) ?>,
                            <?= number_format((float)$editDriver['current_longitude'], 6) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url('admin/modules/drivers/index.php') ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Update Driver' : 'Create Driver' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php else: // ─── List View ──────────────────────────────────────

$search           = trim($_GET['search'] ?? '');
$filterAvail      = $_GET['availability'] ?? '';

$where  = 'u.role_id = 3';
$params = [];

if ($search !== '') {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($filterAvail !== '' && in_array($filterAvail, ['0', '1'])) {
    $where .= " AND dp.is_available = ?";
    $params[] = (int)$filterAvail;
}

$pagination = paginate('users u LEFT JOIN driver_profiles dp ON dp.user_id = u.id', $where, $params, 10);

$drivers = Database::fetchAll(
    "SELECT u.*, dp.vehicle_type, dp.license_plate, dp.is_available, dp.current_latitude, dp.current_longitude
     FROM users u
     LEFT JOIN driver_profiles dp ON dp.user_id = u.id
     WHERE {$where}
     ORDER BY u.id DESC
     LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
    $params
);

$queryParams = array_filter(['search' => $search ?: null, 'availability' => $filterAvail !== '' ? $filterAvail : null]);
$baseUrl = url('admin/modules/drivers/index.php') . '?' . http_build_query($queryParams);

$totalDrivers    = Database::count('users', 'role_id = 3');
$activeDrivers   = Database::count('users', 'role_id = 3 AND is_active = 1');
$availableDrivers = Database::fetch("SELECT COUNT(*) as cnt FROM driver_profiles dp JOIN users u ON dp.user_id = u.id WHERE u.role_id = 3 AND dp.is_available = 1")['cnt'] ?? 0;
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Driver Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Drivers</li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/drivers/index.php?action=add') ?>" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Driver
    </a>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,107,53,0.1)">
                        <i class="fas fa-truck" style="color:var(--primary);font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Drivers</div>
                        <div class="fw-bold fs-5"><?= $totalDrivers ?></div>
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
                        <div class="fw-bold fs-5"><?= $activeDrivers ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(13,202,240,0.1)">
                        <i class="fas fa-check-circle" style="color:#0dcaf0;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Available</div>
                        <div class="fw-bold fs-5"><?= $availableDrivers ?></div>
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
                        <div class="fw-bold fs-5"><?= $totalDrivers - $activeDrivers ?></div>
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
                <label for="filterAvail" class="form-label fw-semibold small">Availability</label>
                <select class="form-select" id="filterAvail" name="availability">
                    <option value="">All</option>
                    <option value="1" <?= $filterAvail === '1' ? 'selected' : '' ?>>Available</option>
                    <option value="0" <?= $filterAvail === '0' ? 'selected' : '' ?>>Unavailable</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('admin/modules/drivers/index.php') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Drivers Table -->
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
                        <th>Vehicle</th>
                        <th>License Plate</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th class="text-end pe-3" style="width:170px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-truck fa-2x mb-3 d-block opacity-50"></i>
                                No drivers found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($drivers as $i => $d): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $pagination['offset'] + $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($d['avatar'])): ?>
                                            <img src="<?= url('uploads/avatar/' . sanitize($d['avatar'])) ?>"
                                                 alt="" class="rounded-circle" width="32" height="32" style="object-fit:cover">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-semibold"
                                                 style="width:32px;height:32px;font-size:0.75rem;background:linear-gradient(135deg,var(--primary),var(--secondary));">
                                                <?= strtoupper(substr(sanitize($d['first_name'] ?? 'D'), 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="fw-semibold"><?= sanitize($d['first_name'] . ' ' . $d['last_name']) ?></span>
                                    </div>
                                </td>
                                <td><?= sanitize($d['email']) ?></td>
                                <td><?= sanitize($d['phone'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($d['vehicle_type'])): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info px-2 py-1">
                                            <i class="fas fa-<?= match($d['vehicle_type']) {
                                                'Bicycle' => 'bicycle',
                                                'Motorcycle' => 'motorcycle',
                                                'Car' => 'car',
                                                'Van' => 'shuttle-van',
                                                'Truck' => 'truck',
                                                default => 'vehicle'
                                            } ?> me-1"></i>
                                            <?= sanitize($d['vehicle_type']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($d['license_plate'])): ?>
                                        <code class="text-dark"><?= sanitize($d['license_plate']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($d['is_available'])): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Available
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Unavailable
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d['is_active']): ?>
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
                                        <!-- Toggle Availability -->
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle_availability">
                                            <input type="hidden" name="driver_id" value="<?= $d['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= !empty($d['is_available']) ? 'secondary' : 'info' ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?= !empty($d['is_available']) ? 'Mark Unavailable' : 'Mark Available' ?>">
                                                <i class="fas fa-<?= !empty($d['is_available']) ? 'eye-slash' : 'eye' ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Toggle Status -->
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="driver_id" value="<?= $d['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $d['is_active'] ? 'warning' : 'success' ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?= $d['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $d['is_active'] ? 'ban' : 'check-circle' ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Edit -->
                                        <a href="<?= url('admin/modules/drivers/index.php?action=edit&id=' . $d['id']) ?>"
                                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>

                                        <!-- Delete -->
                                        <form method="POST" class="d-inline" id="deleteForm_<?= $d['id'] ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="driver_id" value="<?= $d['id'] ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="tooltip" title="Delete"
                                                    onclick="deleteDriver(<?= $d['id'] ?>, '<?= sanitize(addslashes($d['first_name'] . ' ' . $d['last_name'])) ?>')">
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
                    of <?= $pagination['total'] ?> drivers
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
function deleteDriver(id, name) {
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
