<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';

// ─── Action Handlers ─────────────────────────────────────────
if (isPost() && !verifyCSRF()) {
    setFlash('error', 'Invalid security token. Please try again.');
    redirect(url('admin/modules/users/index.php'));
}

if (isPost()) {
    $actionPost = $_POST['action'] ?? '';

    // ── CREATE ──
    if ($actionPost === 'add') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $role_id    = (int)($_POST['role_id'] ?? 0);
        $password   = $_POST['password'] ?? '';
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $role_id < 1) {
            setFlash('error', 'Please fill in all required fields.');
            redirect(url('admin/modules/users/index.php?action=add'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            redirect(url('admin/modules/users/index.php?action=add'));
        }

        $exists = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($exists) {
            setFlash('error', 'A user with this email already exists.');
            redirect(url('admin/modules/users/index.php?action=add'));
        }

        $newUserId = Database::insert('users', [
            'role_id'       => $role_id,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'email'         => $email,
            'password'      => password_hash($password, PASSWORD_DEFAULT),
            'phone'         => $phone,
            'is_active'     => $is_active,
        ]);

        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $file['size'] <= 2 * 1024 * 1024) {
                $filename = 'avatar_' . $newUserId . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../../../uploads/avatar/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    Database::update('users', ['avatar' => $filename], 'id = ?', [$newUserId]);
                }
            }
        }

        logActivity('user_created', "Created user: {$email}");
        setFlash('success', 'User created successfully.');
        redirect(url('admin/modules/users/index.php'));
    }

    // ── UPDATE ──
    if ($actionPost === 'edit') {
        $id         = (int)($_POST['user_id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $role_id    = (int)($_POST['role_id'] ?? 0);
        $password   = $_POST['password'] ?? '';
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if ($id < 1 || $first_name === '' || $last_name === '' || $email === '' || $role_id < 1) {
            setFlash('error', 'Please fill in all required fields.');
            redirect(url('admin/modules/users/index.php?action=edit&id=' . $id));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            redirect(url('admin/modules/users/index.php?action=edit&id=' . $id));
        }

        $exists = Database::fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
        if ($exists) {
            setFlash('error', 'A user with this email already exists.');
            redirect(url('admin/modules/users/index.php?action=edit&id=' . $id));
        }

        $data = [
            'role_id'    => $role_id,
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

        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $file['size'] <= 2 * 1024 * 1024) {
                $filename = 'avatar_' . $id . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../../../uploads/avatar/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                // Delete old avatar
                if (!empty($editUser['avatar']) && file_exists($uploadDir . $editUser['avatar'])) {
                    unlink($uploadDir . $editUser['avatar']);
                }
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    Database::update('users', ['avatar' => $filename], 'id = ?', [$id]);
                }
            }
        }

        logActivity('user_updated', "Updated user ID: {$id}");
        setFlash('success', 'User updated successfully.');
        redirect(url('admin/modules/users/index.php'));
    }

    // ── DELETE ──
    if ($actionPost === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);

        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            setFlash('error', 'You cannot delete your own account.');
            redirect(url('admin/modules/users/index.php'));
        }

        $user = Database::fetch("SELECT email FROM users WHERE id = ?", [$id]);
        if ($user) {
            Database::delete('users', 'id = ?', [$id]);
            logActivity('user_deleted', "Deleted user: {$user['email']}");
            setFlash('success', 'User deleted successfully.');
        } else {
            setFlash('error', 'User not found.');
        }

        redirect(url('admin/modules/users/index.php'));
    }

    // ── TOGGLE STATUS ──
    if ($actionPost === 'toggle') {
        $id   = (int)($_POST['user_id'] ?? 0);
        $user = Database::fetch("SELECT id, is_active, email FROM users WHERE id = ?", [$id]);
        if ($user) {
            $newStatus = $user['is_active'] ? 0 : 1;
            Database::update('users', ['is_active' => $newStatus], 'id = ?', [$id]);
            $label = $newStatus ? 'activated' : 'deactivated';
            logActivity('user_status_toggled', "User {$user['email']} {$label}");
            setFlash('success', "User {$label} successfully.");
        } else {
            setFlash('error', 'User not found.');
        }

        redirect(url('admin/modules/users/index.php'));
    }
}

// ─── Data for Views ──────────────────────────────────────────
$currentUser = currentUser();
$roles = ROLES;

// ─── Add/Edit Form ───────────────────────────────────────────
if ($action === 'add' || $action === 'edit'):
    $editUser = null;
    if ($action === 'edit') {
        $editId = (int)($_GET['id'] ?? 0);
        $editUser = Database::fetch(
            "SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?",
            [$editId]
        );
        if (!$editUser) {
            setFlash('error', 'User not found.');
            redirect(url('admin/modules/users/index.php'));
        }
    }
    $isEdit = $action === 'edit';
    $formTitle = $isEdit ? 'Edit User' : 'Add New User';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><?= $formTitle ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('admin/modules/users/index.php') ?>" class="text-decoration-none">Users</a></li>
                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Add' ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/users/index.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<!-- Form Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= url('admin/modules/users/index.php') ?>" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
            <?php endif; ?>

            <!-- Profile Photo -->
            <div class="text-center mb-4">
                <div class="position-relative d-inline-block">
                    <?php $currentAvatar = $editUser['avatar'] ?? ''; ?>
                    <?php $avatarPath = !empty($currentAvatar) && file_exists(__DIR__ . '/../../../uploads/avatar/' . $currentAvatar) ? url('uploads/avatar/' . $currentAvatar) : url('assets/images/placeholder-food.jpg'); ?>
                    <img src="<?= $avatarPath ?>"
                         alt="Avatar" id="avatarPreview"
                         style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:4px solid #e9ecef;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                    <label for="avatarInput" class="position-absolute bottom-0 end-0 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow"
                           style="width:32px;height:32px;cursor:pointer;border:3px solid #fff;">
                        <i class="fas fa-camera" style="font-size:0.8rem;"></i>
                    </label>
                    <input type="file" name="avatar" id="avatarInput" accept="image/*" class="d-none" onchange="previewAvatar(this)">
                </div>
                <div><small class="text-muted">Click camera icon to choose photo</small></div>
            </div>

            <div class="row g-3">
                <!-- First Name -->
                <div class="col-md-6">
                    <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                           value="<?= sanitize($editUser['first_name'] ?? '') ?>" required>
                </div>

                <!-- Last Name -->
                <div class="col-md-6">
                    <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                           value="<?= sanitize($editUser['last_name'] ?? '') ?>" required>
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= sanitize($editUser['email'] ?? '') ?>" required>
                </div>

                <!-- Phone -->
                <div class="col-md-6">
                    <label for="phone" class="form-label fw-semibold">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone"
                           value="<?= sanitize($editUser['phone'] ?? '') ?>">
                </div>

                <!-- Role -->
                <div class="col-md-6">
                    <label for="role_id" class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <option value="">-- Select Role --</option>
                        <?php foreach ($roles as $roleId => $roleName): ?>
                            <option value="<?= $roleId ?>" <?= ((int)($editUser['role_id'] ?? 0) === $roleId) ? 'selected' : '' ?>>
                                <?= ucfirst($roleName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Password -->
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

                <!-- Status -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold d-block">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                               value="1" <?= ($editUser['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url('admin/modules/users/index.php') ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Update User' : 'Create User' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php else: // ─── List View ──────────────────────────────────────

// Search & Filters
$search   = trim($_GET['search'] ?? '');
$filterRole = (int)($_GET['role'] ?? 0);

// Build query
$where  = '1';
$params = [];

if ($search !== '') {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($filterRole > 0) {
    $where .= " AND u.role_id = ?";
    $params[] = $filterRole;
}

// Pagination
$pagination = paginate('users', $where, $params, 10);

// Fetch users
$users = Database::fetchAll(
    "SELECT u.*, r.name as role_name
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     WHERE {$where}
     ORDER BY u.id DESC
     LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
    $params
);

// Build base URL for pagination
$queryParams = array_filter(['search' => $search ?: null, 'role' => $filterRole ?: null]);
$baseUrl = url('admin/modules/users/index.php') . '?' . http_build_query($queryParams);
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">User Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Users</li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/users/index.php?action=add') ?>" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add User
    </a>
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
                           placeholder="Search by name or email..." value="<?= sanitize($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label for="filterRole" class="form-label fw-semibold small">Role</label>
                <select class="form-select" id="filterRole" name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $roleId => $roleName): ?>
                        <option value="<?= $roleId ?>" <?= $filterRole === $roleId ? 'selected' : '' ?>>
                            <?= ucfirst($roleName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('admin/modules/users/index.php') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
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
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end pe-3" style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-users fa-2x mb-3 d-block opacity-50"></i>
                                No users found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $i => $u): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $pagination['offset'] + $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($u['avatar'])): ?>
                                            <img src="<?= url('uploads/avatar/' . sanitize($u['avatar'])) ?>"
                                                 alt="" class="rounded-circle" width="32" height="32" style="object-fit:cover">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-semibold"
                                                 style="width:32px;height:32px;font-size:0.75rem;background:linear-gradient(135deg,var(--primary),var(--secondary));">
                                                <?= strtoupper(substr(sanitize($u['first_name'] ?? 'U'), 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="fw-semibold"><?= sanitize($u['first_name'] . ' ' . $u['last_name']) ?></span>
                                    </div>
                                </td>
                                <td><?= sanitize($u['email']) ?></td>
                                <td><?= sanitize($u['phone'] ?? '-') ?></td>
                                <td>
                                    <?php
                                        $roleColors = [1 => 'danger', 2 => 'primary', 3 => 'info', 4 => 'success'];
                                        $color = $roleColors[$u['role_id']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> px-2 py-1">
                                        <?= ucfirst(sanitize($u['role_name'] ?? 'Unknown')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $u['last_login'] ? timeAgo($u['last_login']) : '<span class="text-muted">Never</span>' ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <!-- Toggle Status -->
                                        <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                    <i class="fas fa-<?= $u['is_active'] ? 'ban' : 'check-circle' ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Edit -->
                                        <a href="<?= url('admin/modules/users/index.php?action=edit&id=' . $u['id']) ?>"
                                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>

                                        <!-- Delete -->
                                        <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                            <form method="POST" class="d-inline" id="deleteForm_<?= $u['id'] ?>">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="tooltip" title="Delete"
                                                        onclick="deleteUser(<?= $u['id'] ?>, '<?= sanitize(addslashes($u['first_name'] . ' ' . $u['last_name'])) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
                    of <?= $pagination['total'] ?> users
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
function deleteUser(id, name) {
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

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
