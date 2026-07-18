<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../includes/functions.php';

$userId = $_SESSION['user_id'] ?? 0;

if (!isLoggedIn() || !isCustomer()) {
    setFlash('warning', 'Please login to view your profile.');
    redirect(url('login.php'));
}

// ─── CSRF Check ──────────────────────────────────────────────
if (isPost()) {
    requireCSRF();
}

// ─── Update Profile Photo ────────────────────────────────────
if (isPost() && ($_POST['action'] ?? '') === 'upload_photo') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed)) {
            setFlash('error', 'Only JPG, PNG, GIF, and WEBP images are allowed.');
            redirect(url('customer/modules/profile/'));
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            setFlash('error', 'Image must be under 2MB.');
            redirect(url('customer/modules/profile/'));
        }

        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../../../uploads/avatar/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Delete old avatar
        $oldUser = Database::fetch("SELECT avatar FROM users WHERE id = ?", [$userId]);
        if (!empty($oldUser['avatar']) && file_exists($uploadDir . $oldUser['avatar'])) {
            unlink($uploadDir . $oldUser['avatar']);
        }

        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            Database::update('users', ['avatar' => $filename], 'id = ?', [$userId]);
            logActivity('avatar_updated', 'Profile photo updated.');
            setFlash('success', 'Profile photo updated successfully.');
        } else {
            setFlash('error', 'Failed to upload image. Please try again.');
        }
    } else {
        setFlash('error', 'Please select an image to upload.');
    }
    redirect(url('customer/modules/profile/'));
}

// ─── Update Profile ─────────────────────────────────────────
if (isPost() && ($_POST['action'] ?? '') === 'update_profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');

    $errors = [];
    if (empty($firstName)) $errors[] = 'First name is required.';
    if (empty($lastName)) $errors[] = 'Last name is required.';

    if (empty($errors)) {
        Database::update('users', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
        ], 'id = ?', [$userId]);

        $profile = Database::fetch("SELECT id FROM customer_profiles WHERE user_id = ?", [$userId]);
        if ($profile) {
            Database::update('customer_profiles', [
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip_code' => $zip,
            ], 'user_id = ?', [$userId]);
        } else {
            Database::insert('customer_profiles', [
                'user_id' => $userId,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip_code' => $zip,
            ]);
        }

        logActivity('profile_updated', 'Customer profile updated.');

        // Handle avatar upload if included
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
                $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../../../uploads/avatar/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $oldAvatar = Database::fetch("SELECT avatar FROM users WHERE id = ?", [$userId]);
                if (!empty($oldAvatar['avatar']) && file_exists($uploadDir . $oldAvatar['avatar'])) {
                    unlink($uploadDir . $oldAvatar['avatar']);
                }

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    Database::update('users', ['avatar' => $filename], 'id = ?', [$userId]);
                }
            }
        }

        setFlash('success', 'Profile updated successfully.');
    } else {
        setFlash('error', implode(' ', $errors));
    }
    redirect(url('customer/modules/profile/'));
}

// ─── Change Password ────────────────────────────────────────
if (isPost() && ($_POST['action'] ?? '') === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];
    $user = Database::fetch("SELECT id, password FROM users WHERE id = ?", [$userId]);

    if (!password_verify($current, $user['password'])) {
        $errors[] = 'Current password is incorrect.';
    }
    if (strlen($newPass) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($newPass !== $confirm) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors)) {
        Database::update('users', ['password' => password_hash($newPass, PASSWORD_DEFAULT)], 'id = ?', [$userId]);
        logActivity('password_changed', 'Customer password changed.');
        setFlash('success', 'Password changed successfully.');
    } else {
        setFlash('error', implode(' ', $errors));
    }
    redirect(url('customer/modules/profile/'));
}

// ─── Fetch User Data ────────────────────────────────────────
$user = Database::fetch("SELECT id, first_name, last_name, email, phone, avatar, created_at FROM users WHERE id = ?", [$userId]);
$profile = Database::fetch("SELECT * FROM customer_profiles WHERE user_id = ?", [$userId]);

// Stats
$totalOrders = Database::count('orders', 'customer_id = ?', [$userId]);
$totalSpent = Database::fetch("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE customer_id = ? AND order_status = 'delivered'", [$userId]);
$loyaltyPoints = $profile['loyalty_points'] ?? 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .avatar-wrapper {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .profile-stat-card {
        border-radius: 12px;
        transition: transform 0.2s;
        text-align: center;
        padding: 1.5rem;
    }
    .profile-stat-card:hover {
        transform: translateY(-3px);
    }
    .profile-stat-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    .section-card {
        border-radius: 12px;
        overflow: hidden;
    }
</style>

<section class="bg-light py-3 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('customer/index.php') ?>" class="text-decoration-none">Menu</a></li>
                <li class="breadcrumb-item active">My Profile</li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <!-- Sidebar / Avatar Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm section-card">
                    <div class="card-body text-center py-4">
                        <form method="POST" enctype="multipart/form-data" id="avatarForm">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="upload_photo">
                            <div class="position-relative d-inline-block mb-3">
                                <img src="<?= getAvatarUrl($user['avatar'] ?? '') ?>"
                                     alt="Avatar"
                                     class="avatar-wrapper" id="avatarPreview"
                                     style="cursor:pointer;" title="Click to change photo">
                                <label for="avatarInput" class="position-absolute bottom-0 end-0 rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center shadow"
                                       style="width:36px;height:36px;cursor:pointer;border:3px solid #fff;">
                                    <i class="fas fa-camera" style="font-size:0.85rem;"></i>
                                </label>
                                <input type="file" name="avatar" id="avatarInput" accept="image/*" class="d-none" onchange="previewAvatar(this)">
                            </div>
                        </form>
                        <h5 class="fw-bold mb-1"><?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                        <p class="text-muted mb-1"><i class="fas fa-envelope me-1"></i><?= sanitize($user['email']) ?></p>
                        <?php if (!empty($user['phone'])): ?>
                            <p class="text-muted mb-0"><i class="fas fa-phone me-1"></i><?= sanitize($user['phone']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Account Stats -->
                <div class="card border-0 shadow-sm section-card mt-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-chart-bar text-warning me-2"></i>Account Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="profile-stat-card bg-light">
                                    <div class="profile-stat-icon text-warning"><i class="fas fa-user-clock"></i></div>
                                    <h6 class="fw-bold mb-0"><?= date('M Y', strtotime($user['created_at'])) ?></h6>
                                    <small class="text-muted">Member Since</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="profile-stat-card bg-light">
                                    <div class="profile-stat-icon text-info"><i class="fas fa-receipt"></i></div>
                                    <h6 class="fw-bold mb-0"><?= $totalOrders ?></h6>
                                    <small class="text-muted">Total Orders</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="profile-stat-card bg-light">
                                    <div class="profile-stat-icon text-success"><i class="fas fa-dollar-sign"></i></div>
                                    <h6 class="fw-bold mb-0"><?= currency($totalSpent['total'] ?? 0) ?></h6>
                                    <small class="text-muted">Total Spent</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="profile-stat-card bg-light">
                                    <div class="profile-stat-icon text-primary"><i class="fas fa-star"></i></div>
                                    <h6 class="fw-bold mb-0"><?= $loyaltyPoints ?></h6>
                                    <small class="text-muted">Loyalty Points</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Nav Tabs -->
                <ul class="nav nav-tabs mb-4" id="profileTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                            <i class="fas fa-user-edit me-1"></i>Profile Info
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" type="button" role="tab">
                            <i class="fas fa-map-marker-alt me-1"></i>Address
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                            <i class="fas fa-key me-1"></i>Change Password
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="profileTabContent">
                    <!-- Profile Info Tab -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="card border-0 shadow-sm section-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold"><i class="fas fa-user-edit text-warning me-2"></i>Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_profile">

                                    <!-- Profile Photo -->
                                    <div class="text-center mb-4">
                                        <div class="position-relative d-inline-block">
                                            <img src="<?= getAvatarUrl($user['avatar'] ?? '') ?>"
                                                 alt="Avatar" id="avatarPreview2"
                                                 class="avatar-wrapper mb-2">
                                            <label for="avatarInput2" class="position-absolute bottom-0 end-0 rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center shadow"
                                                   style="width:36px;height:36px;cursor:pointer;border:3px solid #fff;">
                                                <i class="fas fa-camera" style="font-size:0.85rem;"></i>
                                            </label>
                                            <input type="file" name="avatar" id="avatarInput2" accept="image/*" class="d-none" onchange="previewAvatar2(this)">
                                        </div>
                                        <div><small class="text-muted">Click camera icon to change photo (JPG, PNG, GIF, WEBP &mdash; max 2MB)</small></div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">First Name</label>
                                            <input type="text" name="first_name" class="form-control" required
                                                   value="<?= sanitize($user['first_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Last Name</label>
                                            <input type="text" name="last_name" class="form-control" required
                                                   value="<?= sanitize($user['last_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Email</label>
                                            <input type="email" class="form-control" value="<?= sanitize($user['email'] ?? '') ?>" disabled>
                                            <small class="text-muted">Email cannot be changed.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Phone</label>
                                            <input type="text" name="phone" class="form-control"
                                                   value="<?= sanitize($user['phone'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-warning rounded-pill px-4">
                                            <i class="fas fa-save me-1"></i>Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Address Tab -->
                    <div class="tab-pane fade" id="address" role="tabpanel">
                        <div class="card border-0 shadow-sm section-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold"><i class="fas fa-map-marker-alt text-warning me-2"></i>Delivery Address</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($profile['address']) && empty($profile['city']) && empty($profile['state']) && empty($profile['zip_code'])): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-map-marked-alt fa-3x text-muted mb-3" style="opacity:0.4;"></i>
                                        <p class="text-muted">No address set yet. Add your delivery address below.</p>
                                    </div>
                                <?php endif; ?>
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Address</label>
                                        <textarea name="address" class="form-control" rows="2"><?= sanitize($profile['address'] ?? '') ?></textarea>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">City</label>
                                            <input type="text" name="city" class="form-control" value="<?= sanitize($profile['city'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">State</label>
                                            <input type="text" name="state" class="form-control" value="<?= sanitize($profile['state'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">ZIP Code</label>
                                            <input type="text" name="zip" class="form-control" value="<?= sanitize($profile['zip_code'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-warning rounded-pill px-4">
                                            <i class="fas fa-save me-1"></i>Save Address
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password Tab -->
                    <div class="tab-pane fade" id="password" role="tabpanel">
                        <div class="card border-0 shadow-sm section-card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold"><i class="fas fa-key text-warning me-2"></i>Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required minlength="8">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">New Password</label>
                                        <input type="password" name="new_password" class="form-control" required minlength="8">
                                        <small class="text-muted">Minimum 8 characters.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                                    </div>
                                    <button type="submit" class="btn btn-warning rounded-pill px-4">
                                        <i class="fas fa-key me-1"></i>Update Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
        document.getElementById('avatarForm').submit();
    }
}
function previewAvatar2(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview2').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
