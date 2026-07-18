<?php
$pageTitle = 'Customer Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';

if (isPost() && !verifyCSRF()) {
    setFlash('error', 'Invalid security token. Please try again.');
    redirect(url('admin/modules/customers/index.php'));
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
        $address    = trim($_POST['address'] ?? '');
        $city       = trim($_POST['city'] ?? '');
        $state      = trim($_POST['state'] ?? '');
        $zip_code   = trim($_POST['zip_code'] ?? '');

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
            setFlash('error', 'Please fill in all required fields.');
            redirect(url('admin/modules/customers/index.php?action=add'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            redirect(url('admin/modules/customers/index.php?action=add'));
        }

        $exists = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($exists) {
            setFlash('error', 'A user with this email already exists.');
            redirect(url('admin/modules/customers/index.php?action=add'));
        }

        $userId = Database::insert('users', [
            'role_id'    => 4,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'phone'      => $phone,
            'is_active'  => $is_active,
        ]);

        Database::insert('customer_profiles', [
            'user_id'        => $userId,
            'address'        => $address,
            'city'           => $city,
            'state'          => $state,
            'zip_code'       => $zip_code,
            'loyalty_points' => 0,
        ]);

        logActivity('customer_created', "Created customer: {$email}");
        setFlash('success', 'Customer created successfully.');
        redirect(url('admin/modules/customers/index.php'));
    }

    // ── UPDATE ──
    if ($actionPost === 'edit') {
        $id         = (int)($_POST['customer_id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $password   = $_POST['password'] ?? '';
        $is_active  = isset($_POST['is_active']) ? 1 : 0;
        $address    = trim($_POST['address'] ?? '');
        $city       = trim($_POST['city'] ?? '');
        $state      = trim($_POST['state'] ?? '');
        $zip_code   = trim($_POST['zip_code'] ?? '');

        if ($id < 1 || $first_name === '' || $last_name === '' || $email === '') {
            setFlash('error', 'Please fill in all required fields.');
            redirect(url('admin/modules/customers/index.php?action=edit&id=' . $id));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            redirect(url('admin/modules/customers/index.php?action=edit&id=' . $id));
        }

        $exists = Database::fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
        if ($exists) {
            setFlash('error', 'A user with this email already exists.');
            redirect(url('admin/modules/customers/index.php?action=edit&id=' . $id));
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

        $hasProfile = Database::count('customer_profiles', 'user_id = ?', [$id]);
        if ($hasProfile) {
            Database::update('customer_profiles', [
                'address'  => $address,
                'city'     => $city,
                'state'    => $state,
                'zip_code' => $zip_code,
            ], 'user_id = ?', [$id]);
        } else {
            Database::insert('customer_profiles', [
                'user_id'        => $id,
                'address'        => $address,
                'city'           => $city,
                'state'          => $state,
                'zip_code'       => $zip_code,
                'loyalty_points' => 0,
            ]);
        }

        logActivity('customer_updated', "Updated customer ID: {$id}");
        setFlash('success', 'Customer updated successfully.');
        redirect(url('admin/modules/customers/index.php'));
    }

    // ── DELETE ──
    if ($actionPost === 'delete') {
        $id = (int)($_POST['customer_id'] ?? 0);

        $customer = Database::fetch("SELECT id, email FROM users WHERE id = ? AND role_id = 4", [$id]);
        if ($customer) {
            Database::delete('users', 'id = ?', [$id]);
            logActivity('customer_deleted', "Deleted customer: {$customer['email']}");
            setFlash('success', 'Customer deleted successfully.');
        } else {
            setFlash('error', 'Customer not found.');
        }

        redirect(url('admin/modules/customers/index.php'));
    }

    // ── TOGGLE STATUS ──
    if ($actionPost === 'toggle') {
        $id       = (int)($_POST['customer_id'] ?? 0);
        $customer = Database::fetch("SELECT id, is_active, email FROM users WHERE id = ? AND role_id = 4", [$id]);
        if ($customer) {
            $newStatus = $customer['is_active'] ? 0 : 1;
            Database::update('users', ['is_active' => $newStatus], 'id = ?', [$id]);
            $label = $newStatus ? 'activated' : 'deactivated';
            logActivity('customer_status_toggled', "Customer {$customer['email']} {$label}");
            setFlash('success', "Customer {$label} successfully.");
        } else {
            setFlash('error', 'Customer not found.');
        }

        redirect(url('admin/modules/customers/index.php'));
    }
}

// ─── View Detail ──────────────────────────────────────────────
if ($action === 'view'):
    $viewId = (int)($_GET['id'] ?? 0);
    $viewCustomer = Database::fetch(
        "SELECT u.*, cp.address, cp.city, cp.state, cp.zip_code, cp.loyalty_points, cp.created_at AS profile_created
         FROM users u
         LEFT JOIN customer_profiles cp ON cp.user_id = u.id
         WHERE u.id = ? AND u.role_id = 4",
        [$viewId]
    );
    if (!$viewCustomer) {
        setFlash('error', 'Customer not found.');
        redirect(url('admin/modules/customers/index.php'));
    }

    $orderStats = Database::fetch(
        "SELECT COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS total_spent
         FROM orders WHERE customer_id = ?",
        [$viewId]
    );

    $recentOrders = Database::fetchAll(
        "SELECT order_number, total, order_status, payment_status, created_at
         FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5",
        [$viewId]
    );
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Customer Details</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('admin/modules/customers/index.php') ?>" class="text-decoration-none">Customers</a></li>
                <li class="breadcrumb-item active">View</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/modules/customers/index.php?action=edit&id=' . $viewCustomer['id']) ?>" class="btn btn-primary">
            <i class="fas fa-pen me-1"></i> Edit
        </a>
        <a href="<?= url('admin/modules/customers/index.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to List
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Profile Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center p-4">
                <?php if (!empty($viewCustomer['avatar'])): ?>
                    <img src="<?= url('uploads/avatar/' . sanitize($viewCustomer['avatar'])) ?>"
                         alt="Avatar" class="rounded-circle mb-3" width="100" height="100" style="object-fit:cover">
                <?php else: ?>
                    <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                         style="width:100px;height:100px;font-size:2.2rem;background:linear-gradient(135deg,var(--primary),var(--secondary));">
                        <?= strtoupper(substr(sanitize($viewCustomer['first_name']), 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <h5 class="fw-bold mb-1"><?= sanitize($viewCustomer['first_name'] . ' ' . $viewCustomer['last_name']) ?></h5>
                <p class="text-muted mb-3"><?= sanitize($viewCustomer['email']) ?></p>

                <?php if ($viewCustomer['is_active']): ?>
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 mb-3">
                        <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Active
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 mb-3">
                        <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Inactive
                    </span>
                <?php endif; ?>

                <hr>

                <div class="text-start">
                    <p class="mb-2"><i class="fas fa-phone text-muted me-2" style="width:18px"></i> <?= sanitize($viewCustomer['phone'] ?: 'N/A') ?></p>
                    <p class="mb-2"><i class="fas fa-envelope text-muted me-2" style="width:18px"></i> <?= sanitize($viewCustomer['email']) ?></p>
                    <p class="mb-2"><i class="fas fa-map-marker-alt text-muted me-2" style="width:18px"></i>
                        <?= sanitize(implode(', ', array_filter([$viewCustomer['address'], $viewCustomer['city'], $viewCustomer['state'], $viewCustomer['zip_code']]))) ?: 'N/A' ?>
                    </p>
                    <p class="mb-2"><i class="fas fa-calendar text-muted me-2" style="width:18px"></i> Joined <?= timeAgo($viewCustomer['created_at']) ?></p>
                    <p class="mb-0"><i class="fas fa-clock text-muted me-2" style="width:18px"></i>
                        Last login: <?= $viewCustomer['last_login'] ? timeAgo($viewCustomer['last_login']) : 'Never' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Details & Orders -->
    <div class="col-lg-8">
        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,107,53,0.1)">
                                <i class="fas fa-receipt" style="color:var(--primary);font-size:1.1rem"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Total Orders</div>
                                <div class="fw-bold fs-5"><?= (int)$orderStats['order_count'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(40,167,69,0.1)">
                                <i class="fas fa-dollar-sign" style="color:#28a745;font-size:1.1rem"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Total Spent</div>
                                <div class="fw-bold fs-5"><?= currency((float)$orderStats['total_spent']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,193,7,0.1)">
                                <i class="fas fa-star" style="color:#ffc107;font-size:1.1rem"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Loyalty Points</div>
                                <div class="fw-bold fs-5"><?= (int)($viewCustomer['loyalty_points'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-receipt me-2" style="color:var(--primary)"></i>Recent Orders</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentOrders)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-shopping-bag fa-2x mb-3 d-block opacity-50"></i>
                        No orders yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Order #</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $o): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <a href="<?= url('admin/modules/orders/index.php?action=view&order=' . $o['order_number']) ?>" class="text-decoration-none fw-semibold">
                                                <?= sanitize($o['order_number']) ?>
                                            </a>
                                        </td>
                                        <td class="fw-semibold"><?= currency((float)$o['total']) ?></td>
                                        <td>
                                            <?php
                                                $statusColor = ORDER_STATUS_COLORS[$o['order_status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $statusColor ?> bg-opacity-10 text-<?= $statusColor ?> px-2 py-1">
                                                <?= ORDER_STATUSES[$o['order_status']] ?? ucfirst($o['order_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                                $payColors = ['pending' => 'warning', 'completed' => 'success', 'paid' => 'success', 'failed' => 'danger', 'refunded' => 'info'];
                                                $payColor = $payColors[$o['payment_status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $payColor ?> bg-opacity-10 text-<?= $payColor ?> px-2 py-1">
                                                <?= ucfirst($o['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small"><?= timeAgo($o['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// ─── Add / Edit Form ──────────────────────────────────────────
elseif ($action === 'add' || $action === 'edit'):
    $editCustomer = null;
    $isEdit = $action === 'edit';

    if ($isEdit) {
        $editId = (int)($_GET['id'] ?? 0);
        $editCustomer = Database::fetch(
            "SELECT u.*, cp.address, cp.city, cp.state, cp.zip_code, cp.loyalty_points
             FROM users u
             LEFT JOIN customer_profiles cp ON cp.user_id = u.id
             WHERE u.id = ? AND u.role_id = 4",
            [$editId]
        );
        if (!$editCustomer) {
            setFlash('error', 'Customer not found.');
            redirect(url('admin/modules/customers/index.php'));
        }
    }

    $formTitle = $isEdit ? 'Edit Customer' : 'Add New Customer';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><?= $formTitle ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('admin/modules/customers/index.php') ?>" class="text-decoration-none">Customers</a></li>
                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Add' ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/customers/index.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<!-- Form Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= url('admin/modules/customers/index.php') ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="customer_id" value="<?= $editCustomer['id'] ?>">
            <?php endif; ?>

            <!-- Personal Information -->
            <h6 class="fw-bold text-uppercase small text-muted mb-3">
                <i class="fas fa-user me-1" style="color:var(--primary)"></i> Personal Information
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                           value="<?= sanitize($editCustomer['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                           value="<?= sanitize($editCustomer['last_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= sanitize($editCustomer['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label fw-semibold">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone"
                           value="<?= sanitize($editCustomer['phone'] ?? '') ?>">
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
                               value="1" <?= ($editCustomer['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <!-- Address / Profile -->
            <h6 class="fw-bold text-uppercase small text-muted mb-3">
                <i class="fas fa-map-marker-alt me-1" style="color:var(--primary)"></i> Address Details
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <label for="address" class="form-label fw-semibold">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?= sanitize($editCustomer['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label for="city" class="form-label fw-semibold">City</label>
                    <input type="text" class="form-control" id="city" name="city"
                           value="<?= sanitize($editCustomer['city'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="state" class="form-label fw-semibold">State</label>
                    <input type="text" class="form-control" id="state" name="state"
                           value="<?= sanitize($editCustomer['state'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="zip_code" class="form-label fw-semibold">Zip Code</label>
                    <input type="text" class="form-control" id="zip_code" name="zip_code"
                           value="<?= sanitize($editCustomer['zip_code'] ?? '') ?>">
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url('admin/modules/customers/index.php') ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Update Customer' : 'Create Customer' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php else: // ─── List View ──────────────────────────────────────

$search      = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';

$where  = 'role_id = 4';
$params = [];

if ($search !== '') {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($filterStatus !== '' && in_array($filterStatus, ['0', '1'])) {
    $where .= " AND is_active = ?";
    $params[] = (int)$filterStatus;
}

$pagination = paginate('users', $where, $params, 10);

$customers = Database::fetchAll(
    "SELECT u.*, cp.address, cp.city, cp.state, cp.zip_code, cp.loyalty_points
     FROM users u
     LEFT JOIN customer_profiles cp ON cp.user_id = u.id
     WHERE {$where}
     ORDER BY u.id DESC
     LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
    $params
);

$queryParams = array_filter(['search' => $search ?: null, 'status' => $filterStatus !== '' ? $filterStatus : null]);
$baseUrl = url('admin/modules/customers/index.php') . '?' . http_build_query($queryParams);

$totalCustomers = Database::count('users', 'role_id = 4');
$activeCustomers = Database::count('users', 'role_id = 4 AND is_active = 1');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Customer Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Customers</li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/customers/index.php?action=add') ?>" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Customer
    </a>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,107,53,0.1)">
                        <i class="fas fa-users" style="color:var(--primary);font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Customers</div>
                        <div class="fw-bold fs-5"><?= $totalCustomers ?></div>
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
                        <div class="fw-bold fs-5"><?= $activeCustomers ?></div>
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
                        <div class="fw-bold fs-5"><?= $totalCustomers - $activeCustomers ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <?php
            $pointsRow = Database::fetch("SELECT COALESCE(SUM(cp.loyalty_points), 0) AS total_pts FROM customer_profiles cp JOIN users u ON cp.user_id = u.id WHERE u.role_id = 4");
            $totalPoints = (int)($pointsRow['total_pts'] ?? 0);
        ?>
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,193,7,0.1)">
                        <i class="fas fa-star" style="color:#ffc107;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Loyalty Points</div>
                        <div class="fw-bold fs-5"><?= number_format($totalPoints) ?></div>
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
                <label for="filterStatus" class="form-label fw-semibold small">Status</label>
                <select class="form-select" id="filterStatus" name="status">
                    <option value="">All Status</option>
                    <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('admin/modules/customers/index.php') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
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
                        <th>Address</th>
                        <th>Loyalty</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-end pe-3" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-user-friends fa-2x mb-3 d-block opacity-50"></i>
                                No customers found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $i => $c): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $pagination['offset'] + $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($c['avatar'])): ?>
                                            <img src="<?= url('uploads/avatar/' . sanitize($c['avatar'])) ?>"
                                                 alt="" class="rounded-circle" width="32" height="32" style="object-fit:cover">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-semibold"
                                                 style="width:32px;height:32px;font-size:0.75rem;background:linear-gradient(135deg,var(--primary),var(--secondary));">
                                                <?= strtoupper(substr(sanitize($c['first_name'] ?? 'C'), 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <a href="<?= url('admin/modules/customers/index.php?action=view&id=' . $c['id']) ?>"
                                           class="text-decoration-none fw-semibold">
                                            <?= sanitize($c['first_name'] . ' ' . $c['last_name']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td><?= sanitize($c['email']) ?></td>
                                <td><?= sanitize($c['phone'] ?? '-') ?></td>
                                <td>
                                    <?php
                                        $addrParts = array_filter([$c['city'] ?? '', $c['state'] ?? '']);
                                        echo $addrParts ? sanitize(implode(', ', $addrParts)) : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <span class="fw-semibold" style="color:var(--primary)">
                                        <?= number_format((int)($c['loyalty_points'] ?? 0)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($c['is_active']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= timeAgo($c['created_at']) ?></td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <!-- View -->
                                        <a href="<?= url('admin/modules/customers/index.php?action=view&id=' . $c['id']) ?>"
                                           class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <!-- Toggle Status -->
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $c['is_active'] ? 'warning' : 'success' ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $c['is_active'] ? 'ban' : 'check-circle' ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Edit -->
                                        <a href="<?= url('admin/modules/customers/index.php?action=edit&id=' . $c['id']) ?>"
                                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>

                                        <!-- Delete -->
                                        <form method="POST" class="d-inline" id="deleteForm_<?= $c['id'] ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="tooltip" title="Delete"
                                                    onclick="deleteCustomer(<?= $c['id'] ?>, '<?= sanitize(addslashes($c['first_name'] . ' ' . $c['last_name'])) ?>')">
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
                    of <?= $pagination['total'] ?> customers
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
function deleteCustomer(id, name) {
    Swal.fire({
        title: 'Delete "' + name + '"?',
        text: 'All customer data and orders will be permanently removed. This action cannot be undone.',
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
