<?php
require_once __DIR__ . '/includes/helpers.php';

$token = $_GET['token'] ?? '';
$message = '';
$error = '';

if (empty($token)) {
    redirect(url('login.php'));
}

$user = Database::fetch("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()", [$token]);
if (!$user) {
    setFlash('error', 'Invalid or expired reset token.');
    redirect(url('forgot_password.php'));
}

if (isPost()) {
    if (!verifyCSRF()) {
        $error = 'Invalid security token.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            Database::update('users', [
                'password'     => password_hash($password, PASSWORD_DEFAULT),
                'reset_token'  => null,
                'reset_expires' => null,
            ], 'id = ?', [$user['id']]);
            setFlash('success', 'Password reset successful! Please sign in.');
            redirect(url('login.php'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#ff6b35 0%,#f7c948 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
        .reset-card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.15);padding:40px;max-width:440px;width:100%}
        .form-control{border-radius:10px;padding:12px;border:2px solid #e0e0e0}
        .form-control:focus{border-color:#ff6b35;box-shadow:0 0 0 .2rem rgba(255,107,53,.25)}
        .btn-orange{background:linear-gradient(135deg,#ff6b35,#e85d04);border:none;border-radius:10px;padding:12px;font-weight:600;color:#fff;width:100%}
        .btn-orange:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(255,107,53,.4);color:#fff}
        a{color:#ff6b35}
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="text-center mb-4">
            <i class="fas fa-key fa-3x text-warning mb-3"></i>
            <h3 class="fw-bold">Reset Password</h3>
            <p class="text-muted">Enter your new password below.</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label fw-medium">New Password</label>
                <input type="password" class="form-control" name="password" minlength="6" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium">Confirm Password</label>
                <input type="password" class="form-control" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-orange mb-3"><i class="fas fa-save me-2"></i>Reset Password</button>
        </form>
        <div class="text-center"><a href="<?= url('login.php') ?>" class="small"><i class="fas fa-arrow-left me-1"></i>Back to Login</a></div>
    </div>
</body>
</html>
