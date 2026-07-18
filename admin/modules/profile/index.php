<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$user = currentUser();
$userId = $user['id'];

$message = '';
$error = '';

if (isPost()) {
    if (!verifyCSRF()) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'upload_avatar') {
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','gif','webp']) && $file['size'] <= 2 * 1024 * 1024) {
                    $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                    $uploadDir = __DIR__ . '/../../../uploads/avatar/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    if (!empty($user['avatar']) && file_exists($uploadDir . $user['avatar'])) {
                        unlink($uploadDir . $user['avatar']);
                    }
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        Database::update('users', ['avatar' => $filename], 'id = ?', [$userId]);
                        $message = 'Profile photo updated!';
                    } else {
                        $error = 'Failed to upload image.';
                    }
                } else {
                    $error = 'Only JPG, PNG, GIF, WEBP images under 2MB allowed.';
                }
            } else {
                $error = 'Please select an image.';
            }
            $user = currentUser();
        }

        if ($action === 'update_profile') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name'] ?? '');
            $phone     = trim($_POST['phone'] ?? '');

            if (empty($firstName) || empty($lastName)) {
                $error = 'First and last name are required.';
            } else {
                Database::update('users', [
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'phone'      => $phone,
                ], 'id = ?', [$userId]);

                $_SESSION['full_name'] = $firstName . ' ' . $lastName;
                logActivity('Profile Updated', 'Admin updated their profile');
                $message = 'Profile updated successfully!';
                $user = currentUser();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><i class="fas fa-user-circle me-2 text-primary"></i>My Profile</h2>
    <a href="<?= url('change_password.php') ?>" class="btn btn-outline-warning"><i class="fas fa-key me-2"></i>Change Password</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= sanitize($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm text-center">
            <div class="card-body py-5">
                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="position-relative d-inline-block mb-3">
                        <?php $avatarPath = !empty($user['avatar']) && file_exists(__DIR__ . '/../../../uploads/avatar/' . $user['avatar']) ? url('uploads/avatar/' . $user['avatar']) : ''; ?>
                        <?php if (!empty($avatarPath)): ?>
                            <img src="<?= $avatarPath ?>" alt="Avatar" id="avatarPreview"
                                 style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:4px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        <?php else: ?>
                            <div id="avatarPreview" style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-size:2.5rem;font-weight:700;border:4px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                                <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <label for="avatarInput" class="position-absolute bottom-0 end-0 rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center shadow"
                               style="width:32px;height:32px;cursor:pointer;border:3px solid #fff;">
                            <i class="fas fa-camera" style="font-size:0.8rem;"></i>
                        </label>
                        <input type="file" name="avatar" id="avatarInput" accept="image/*" class="d-none" onchange="previewAvatar(this)">
                    </div>
                    <div><small class="text-muted">Click camera to change photo</small></div>
                </form>
                <h5 class="fw-bold"><?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill mb-2">
                    <?= ucfirst(sanitize($user['role_name'])) ?>
                </span>
                <p class="text-muted small mb-1"><?= sanitize($user['email']) ?></p>
                <p class="text-muted small"><i class="fas fa-phone me-1"></i><?= sanitize($user['phone'] ?: 'Not set') ?></p>
                <hr>
                <div class="text-start">
                    <p class="small mb-1"><i class="fas fa-calendar me-2 text-muted"></i>Joined: <strong><?= date('M d, Y', strtotime($user['created_at'])) ?></strong></p>
                    <p class="small mb-0"><i class="fas fa-clock me-2 text-muted"></i>Last Login: <strong><?= $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never' ?></strong></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2 text-primary"></i>Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">First Name *</label>
                            <input type="text" class="form-control" name="first_name" value="<?= sanitize($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" value="<?= sanitize($user['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Email Address</label>
                            <input type="email" class="form-control" value="<?= sanitize($user['email']) ?>" disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Phone</label>
                            <input type="tel" class="form-control" name="phone" value="<?= sanitize($user['phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Role</label>
                            <input type="text" class="form-control" value="<?= ucfirst(sanitize($user['role_name'])) ?>" disabled>
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var el = document.getElementById('avatarPreview');
            if (el.tagName === 'IMG') {
                el.src = e.target.result;
            } else {
                var img = document.createElement('img');
                img.src = e.target.result;
                img.id = 'avatarPreview';
                img.style.cssText = 'width:100px;height:100px;object-fit:cover;border-radius:50%;border:4px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
                el.parentNode.replaceChild(img, el);
            }
            // Auto-submit the small avatar form
            document.getElementById('avatarForm').submit();
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
