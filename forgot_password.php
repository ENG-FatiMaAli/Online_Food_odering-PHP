<?php
require_once __DIR__ . '/includes/helpers.php';
if (isLoggedIn()) redirect(url('index.php'));

$message = '';
$error = '';

if (isPost()) {
    if (!verifyCSRF()) {
        $error = 'Invalid security token.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $user = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
            if ($user) {
                $token = bin2hex(random_bytes(32));
                Database::update('users', [
                    'reset_token'   => $token,
                    'reset_expires' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                ], 'id = ?', [$user['id']]);
                $message = 'Password reset link has been sent to your email. (For demo, use: ' . url('reset_password.php?token=' . $token) . ')';
                logActivity('Password Reset Request', 'Reset requested for: ' . $email);
            } else {
                $message = 'If that email exists, a reset link has been sent.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#ff6b35 0%,#f7c948 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
        .forgot-card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.15);padding:40px;max-width:440px;width:100%}
        .form-control{border-radius:10px;padding:12px;border:2px solid #e0e0e0}
        .form-control:focus{border-color:#ff6b35;box-shadow:0 0 0 .2rem rgba(255,107,53,.25)}
        .btn-orange{background:linear-gradient(135deg,#ff6b35,#e85d04);border:none;border-radius:10px;padding:12px;font-weight:600;color:#fff;width:100%}
        .btn-orange:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(255,107,53,.4);color:#fff}
        a{color:#ff6b35}
    </style>
</head>
<body>
    <div class="forgot-card text-center">
        <i class="fas fa-lock fa-3x text-warning mb-3"></i>
        <h3 class="fw-bold">Forgot Password?</h3>
        <p class="text-muted mb-4">Enter your email and we'll send you a reset link.</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= sanitize($message) ?></div>
        <?php endif; ?>
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3 text-start">
                <label class="form-label fw-medium">Email Address</label>
                <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
            </div>
            <button type="submit" class="btn btn-orange mb-3"><i class="fas fa-paper-plane me-2"></i>Send Reset Link</button>
        </form>
        <a href="<?= url('login.php') ?>" class="small"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
    </div>
</body>
</html>
