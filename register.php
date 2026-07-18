<?php
require_once __DIR__ . '/includes/helpers.php';
if (isLoggedIn()) redirect(url('index.php'));

$errors = [];
$old = ['first_name'=>'','last_name'=>'','email'=>'','phone'=>''];

if (isPost()) {
    if (!verifyCSRF()) {
        $errors[] = 'Invalid security token.';
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $password   = $_POST['password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';
        $old = compact('first_name','last_name','email','phone');

        if (empty($first_name)) $errors[] = 'First name is required.';
        if (empty($last_name))  $errors[] = 'Last name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
        if (Database::count('users', 'email = ?', [$email])) $errors[] = 'Email already registered.';

        if (empty($errors)) {
            $userId = Database::insert('users', [
                'role_id'    => 4,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $email,
                'password'   => password_hash($password, PASSWORD_DEFAULT),
                'phone'      => $phone,
                'is_active'  => 1,
            ]);
            Database::insert('customer_profiles', ['user_id' => $userId, 'loyalty_points' => 0]);
            logActivity('Register', 'New customer registered: ' . $email);
            addNotification($userId, 'Welcome!', 'Welcome to ' . APP_NAME . '. Enjoy your first order!', 'success');
            setFlash('success', 'Registration successful! Please sign in.');
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
    <title>Register - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#ff6b35 0%,#f7c948 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px 0}
        .reg-card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.15);overflow:hidden;max-width:500px;width:100%}
        .reg-header{background:linear-gradient(135deg,#ff6b35,#e85d04);color:#fff;padding:30px;text-align:center}
        .reg-body{padding:30px}
        .form-control,.form-select{border-radius:10px;padding:10px 15px;border:2px solid #e0e0e0}
        .form-control:focus,.form-select:focus{border-color:#ff6b35;box-shadow:0 0 0 .2rem rgba(255,107,53,.25)}
        .btn-reg{background:linear-gradient(135deg,#ff6b35,#e85d04);border:none;border-radius:10px;padding:12px;font-weight:600;color:#fff;width:100%}
        .btn-reg:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(255,107,53,.4);color:#fff}
        .form-label{font-weight:500;color:#555}
        a{color:#ff6b35;text-decoration:none}
    </style>
</head>
<body>
    <div class="reg-card">
        <div class="reg-header">
            <i class="fas fa-utensils fa-3x mb-2"></i>
            <h3 class="mb-0">Create Account</h3>
            <small class="opacity-75">Join <?= APP_NAME ?> today</small>
        </div>
        <div class="reg-body">
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= sanitize($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <form method="POST">
                <?= csrfField() ?>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">First Name *</label>
                        <input type="text" class="form-control" name="first_name" value="<?= sanitize($old['first_name']) ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Last Name *</label>
                        <input type="text" class="form-control" name="last_name" value="<?= sanitize($old['last_name']) ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address *</label>
                    <input type="email" class="form-control" name="email" value="<?= sanitize($old['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" value="<?= sanitize($old['phone']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" class="form-control" name="password" minlength="6" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-reg mb-3"><i class="fas fa-user-plus me-2"></i>Create Account</button>
            </form>
            <div class="text-center">
                <p class="mb-0">Already have an account? <a href="<?= url('login.php') ?>" class="fw-bold">Sign In</a></p>
                <a href="<?= url('index.php') ?>" class="text-muted small"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
