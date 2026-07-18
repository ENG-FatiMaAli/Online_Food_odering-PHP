<?php
require_once __DIR__ . '/includes/helpers.php';
requireLogin();

$error = '';
$success = '';

if (isPost()) {
    if (!verifyCSRF()) {
        $error = 'Invalid security token.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $user = Database::fetch("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);

        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            Database::update('users', ['password' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$_SESSION['user_id']]);
            logActivity('Password Changed', 'User changed their password');
            $success = 'Password changed successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Poppins',sans-serif;background:#f4f6f9}
        .pass-card{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:40px;max-width:480px;margin:40px auto}
        .form-control{border-radius:10px;padding:12px;border:2px solid #e0e0e0}
        .form-control:focus{border-color:#ff6b35;box-shadow:0 0 0 .2rem rgba(255,107,53,.25)}
        .btn-orange{background:linear-gradient(135deg,#ff6b35,#e85d04);border:none;border-radius:10px;padding:12px;font-weight:600;color:#fff;width:100%}
        .btn-orange:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(255,107,53,.4);color:#fff}
        a{color:#ff6b35}
    </style>
</head>
<body>
    <div class="pass-card">
        <h4 class="fw-bold mb-1"><i class="fas fa-key me-2 text-warning"></i>Change Password</h4>
        <p class="text-muted mb-4">Update your account password</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= sanitize($success) ?></div>
        <?php endif; ?>
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label fw-medium">Current Password</label>
                <input type="password" class="form-control" name="current_password" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium">New Password</label>
                <input type="password" class="form-control" name="new_password" minlength="6" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium">Confirm New Password</label>
                <input type="password" class="form-control" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-orange mb-3"><i class="fas fa-save me-2"></i>Update Password</button>
        </form>
        <a href="javascript:history.back()" class="small"><i class="fas fa-arrow-left me-1"></i>Go Back</a>
    </div>
</body>
</html>
