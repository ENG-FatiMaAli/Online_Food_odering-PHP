<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/constants.php';

if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        :root {
            --primary: #ff6b35;
            --primary-hover: #e55a28;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #fff !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--primary) !important;
            font-size: 1.4rem;
        }
        .navbar-brand i {
            color: var(--primary);
        }
        .nav-link {
            font-weight: 500;
            color: #333 !important;
            transition: color 0.2s;
            position: relative;
            padding: 0.5rem 1rem !important;
        }
        .nav-link:hover,
        .nav-link.active {
            color: var(--primary) !important;
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 2px;
            background-color: var(--primary);
            border-radius: 2px;
        }
        .cart-icon {
            position: relative;
            font-size: 1.2rem;
            color: #333;
            transition: color 0.2s;
        }
        .cart-icon:hover {
            color: var(--primary);
        }
        .cart-badge {
            position: absolute;
            top: -6px;
            right: -8px;
            background-color: var(--primary);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 600;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-icon {
            position: relative;
            font-size: 1.15rem;
            color: #333;
            transition: color 0.2s;
        }
        .notification-icon:hover {
            color: var(--primary);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -7px;
            background-color: #dc3545;
            color: #fff;
            font-size: 0.6rem;
            font-weight: 600;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 0.5rem;
        }
        .dropdown-item {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .dropdown-item:hover {
            background-color: #fff3ee;
            color: var(--primary);
        }
        .btn-primary-custom {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #fff;
            font-weight: 500;
            padding: 0.4rem 1.2rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .btn-primary-custom:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            color: #fff;
        }
        .btn-outline-custom {
            border: 2px solid var(--primary);
            color: var(--primary);
            font-weight: 500;
            padding: 0.35rem 1.2rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .btn-outline-custom:hover {
            background-color: var(--primary);
            color: #fff;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?= BASE_URL ?>/customer/index.php">
                <i class="fas fa-utensils me-2"></i><?= APP_NAME ?>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars" style="color: var(--primary);"></i>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/customer/index.php">
                            <i class="fas fa-store me-1"></i> Menu
                        </a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/customer/modules/orders/">
                                <i class="fas fa-receipt me-1"></i> My Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/customer/modules/favorites/">
                                <i class="fas fa-heart me-1"></i> Favorites
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <a href="<?= BASE_URL ?>/customer/modules/cart/" class="cart-icon text-decoration-none">
                        <i class="fas fa-shopping-cart"></i>
                        <?php $cartCount = getCartCount(); ?>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>

                    <?php if (isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?>/customer/modules/orders/" class="notification-icon text-decoration-none">
                            <i class="fas fa-bell"></i>
                            <?php $unreadCount = getUnreadNotifications($_SESSION['user_id'] ?? 0); ?>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-badge"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>

                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php $cu = currentUser(); ?>
                                <?php if (!empty($cu['avatar'])): ?>
                                    <img src="<?= url('uploads/avatar/' . $cu['avatar']) ?>" alt="Avatar"
                                         style="width:36px;height:36px;object-fit:cover;border-radius:50%;border:2px solid #eee;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="fas fa-user text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li class="px-2 py-1">
                                    <small class="text-muted d-block fw-semibold"><?= htmlspecialchars(($cu['first_name'] ?? '') . ' ' . ($cu['last_name'] ?? '')) ?></small>
                                    <small class="text-muted d-block"><?= htmlspecialchars($cu['email'] ?? '') ?></small>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>/customer/modules/profile/">
                                        <i class="fas fa-user-edit me-2"></i> Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= url('change_password.php') ?>">
                                        <i class="fas fa-key me-2"></i> Change Password
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?= url('logout.php') ?>">
                                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?= url('login.php') ?>" class="btn btn-outline-custom btn-sm">Login</a>
                        <a href="<?= url('register.php') ?>" class="btn btn-primary-custom btn-sm">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4">
