<?php
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    $role = $_SESSION['role_id'] ?? 0;
    if ($role == 1) redirect(url('admin/index.php'));
    if ($role == 4) redirect(url('customer/index.php'));
    redirect(url('index.php'));
}

$error = '';
$email = '';

if (isPost()) {
    if (!verifyCSRF()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $user = Database::fetch("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ?", [$email]);
            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_active']) {
                    $error = 'Your account has been deactivated.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['role_id']    = $user['role_id'];
                    $_SESSION['role_name']  = $user['role_name'];
                    $_SESSION['full_name']  = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['email']      = $user['email'];

                    Database::update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
                    logActivity('Login', 'User logged in successfully');

                    switch ($user['role_id']) {
                        case 1: redirect(url('admin/index.php')); break;
                        case 2: redirect(url('admin/index.php')); break;
                        case 3: redirect(url('admin/index.php')); break;
                        default: redirect(url('customer/index.php'));
                    }
                }
            } else {
                $error = 'Invalid email or password.';
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
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#ff6b35 0%,#f7c948 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
        .login-card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.15);overflow:hidden;max-width:960px;width:100%}
        .login-left{background:linear-gradient(135deg,#ff6b35,#e85d04);color:#fff;padding:60px 40px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center}
        .login-left h1{font-size:2.5rem;font-weight:700;margin-bottom:10px}
        .login-left p{font-size:1rem;opacity:.85}
        .login-right{padding:50px 40px}
        .login-right h2{font-weight:700;color:#333}
        .form-control{border-radius:10px;padding:12px 15px;border:2px solid #e0e0e0}
        .form-control:focus{border-color:#ff6b35;box-shadow:0 0 0 .2rem rgba(255,107,53,.25)}
        .btn-login{background:linear-gradient(135deg,#ff6b35,#e85d04);border:none;border-radius:10px;padding:12px;font-weight:600;font-size:1rem;color:#fff;transition:.3s}
        .btn-login:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(255,107,53,.4);color:#fff}
        .form-label{font-weight:500;color:#555}
        a{color:#ff6b35;text-decoration:none}
        a:hover{color:#e85d04}
        .input-group-text{background:#fff;border:2px solid #e0e0e0;border-radius:10px}
        @media(max-width:768px){.login-left{display:none}.login-right{padding:30px 20px}}
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card row g-0">
            <div class="col-lg-5 login-left">
                <i class="fas fa-utensils fa-4x mb-3"></i>
                <h1><?= APP_NAME ?></h1>
                <p>Welcome back! Sign in to order delicious food and track your deliveries.</p>
                <hr class="w-50 my-3" style="opacity:.3">
                <small class="mt-3 opacity-75">Fresh Food &bull; Fast Delivery &bull; Best Prices</small>
            </div>
            <div class="col-lg-7 login-right">
                <h2 class="mb-1">Sign In</h2>
                <p class="text-muted mb-4">Enter your credentials to access your account</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" placeholder="you@example.com" value="<?= sanitize($email) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" id="password" placeholder="Enter your password" required>
                            <button class="input-group-text" type="button" onclick="togglePassword()"><i class="fas fa-eye" id="toggleIcon"></i></button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <a href="<?= url('forgot_password.php') ?>">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-login w-100 mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </form>

                <div class="text-center">
                    <p class="mb-0">Don't have an account? <a href="<?= url('register.php') ?>" class="fw-bold">Create Account</a></p>
                    <a href="<?= url('index.php') ?>" class="text-muted small"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
                </div>

                <hr class="my-4">
                <div class="text-center small text-muted">
                    <strong>Demo Accounts:</strong><br>
                    Admin: admin@foodapp.com<br>
                    Customer: customer@foodapp.com<br>
                    Staff: staff@foodapp.com<br>
                    Driver: driver@foodapp.com<br>
                    Password for all: <code>password</code>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function togglePassword(){
            const p=document.getElementById('password'),i=document.getElementById('toggleIcon');
            if(p.type==='password'){p.type='text';i.classList.replace('fa-eye','fa-eye-slash')}
            else{p.type='password';i.classList.replace('fa-eye-slash','fa-eye')}
        }
    </script>
</body>
</html>
