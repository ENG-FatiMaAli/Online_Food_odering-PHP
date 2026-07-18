<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/helpers.php';
ob_start();

if (!isLoggedIn() || (!isAdmin() && !isStaff())) {
    header('Location: ' . url('login.php'));
    exit;
}

$user = currentUser();
$unreadCount = $user ? getUnreadNotifications($user['id']) : 0;
$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF']);
$currentUri = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - <?= APP_NAME ?> Admin</title>

    <!-- Bootstrap 5.3.2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6.5.1 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #ff6b35;
            --secondary: #e85d04;
            --sidebar-bg: #1a1a2e;
            --sidebar-hover: #16213e;
            --sidebar-active: #0f3460;
            --sidebar-width: 270px;
            --topbar-height: 65px;
            --font-family: 'Poppins', sans-serif;
        }

        * { font-family: var(--font-family); }

        body {
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* ─── Top Navbar ─────────────────────────── */
        .admin-topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 1030;
            transition: left 0.3s ease;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .sidebar-toggle-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: #333;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .sidebar-toggle-btn:hover {
            background: #f0f0f0;
        }

        .topbar-search {
            position: relative;
        }
        .topbar-search input {
            width: 300px;
            padding: 8px 16px 8px 40px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            background: #f8f9fa;
            font-size: 0.875rem;
            transition: all 0.3s;
        }
        .topbar-search input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.15);
            background: #fff;
            width: 360px;
        }
        .topbar-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 0.875rem;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .topbar-icon-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: none;
            background: #f8f9fa;
            color: #555;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .topbar-icon-btn:hover {
            background: var(--primary);
            color: #fff;
        }

        .notification-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #dc3545;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }

        .dark-mode-toggle {
            background: none;
            border: none;
            font-size: 1.15rem;
            color: #555;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .dark-mode-toggle:hover {
            background: #f0f0f0;
        }

        [data-bs-theme="dark"] .dark-mode-toggle i::before {
            content: "\f186";
        }

        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 12px 4px 4px;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s;
            border: none;
            background: none;
        }
        .user-dropdown:hover {
            background: #f0f0f0;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .user-avatar-placeholder {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-info {
            text-align: left;
            line-height: 1.2;
        }
        .user-info .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: #333;
        }
        .user-info .user-role {
            font-size: 0.7rem;
            color: #888;
            text-transform: capitalize;
        }

        .dropdown-menu-user {
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            border-radius: 12px;
            padding: 8px;
            min-width: 220px;
        }
        .dropdown-menu-user .dropdown-item {
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }
        .dropdown-menu-user .dropdown-item:hover {
            background: #f8f4f0;
            color: var(--primary);
        }
        .dropdown-menu-user .dropdown-item i {
            width: 20px;
            text-align: center;
            color: #888;
        }
        .dropdown-menu-user .dropdown-item:hover i {
            color: var(--primary);
        }
        .dropdown-menu-user .dropdown-divider {
            margin: 4px 0;
        }

        /* ─── Main Content ───────────────────────── */
        .admin-wrapper {
            margin-left: var(--sidebar-width);
            padding-top: var(--topbar-height);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .admin-content {
            padding: 24px;
        }

        /* ─── Dark Mode ──────────────────────────── */
        [data-bs-theme="dark"] body {
            background-color: #0d1117;
        }
        [data-bs-theme="dark"] .admin-topbar {
            background: #161b22;
            border-bottom-color: #30363d;
        }
        [data-bs-theme="dark"] .topbar-search input {
            background: #21262d;
            border-color: #30363d;
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .topbar-search input:focus {
            background: #0d1117;
        }
        [data-bs-theme="dark"] .topbar-icon-btn {
            background: #21262d;
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .sidebar-toggle-btn {
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .user-info .user-name {
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .user-info .user-role {
            color: #8b949e;
        }
        [data-bs-theme="dark"] .user-dropdown:hover {
            background: #21262d;
        }
        [data-bs-theme="dark"] .dropdown-menu-user {
            background: #161b22;
            border: 1px solid #30363d;
        }
        [data-bs-theme="dark"] .dropdown-menu-user .dropdown-item {
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .dropdown-menu-user .dropdown-item:hover {
            background: #21262d;
            color: var(--primary);
        }
        [data-bs-theme="dark"] .dark-mode-toggle:hover {
            background: #21262d;
        }
        [data-bs-theme="dark"] .admin-sidebar {
            background: #0d1117;
        }
        [data-bs-theme="dark"] .admin-sidebar .sidebar-link {
            color: #8b949e;
        }
        [data-bs-theme="dark"] .admin-sidebar .sidebar-link:hover,
        [data-bs-theme="dark"] .admin-sidebar .sidebar-link.active {
            background: rgba(255,107,53,.15);
            color: #ff6b35;
        }
        [data-bs-theme="dark"] .sidebar-section-title {
            color: #484f58;
        }
        [data-bs-theme="dark"] .admin-content {
            background: #0d1117;
        }
        [data-bs-theme="dark"] .card,
        [data-bs-theme="dark"] .stats-card,
        [data-bs-theme="dark"] .admin-card {
            background: #161b22;
            border-color: #30363d;
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .card-header,
        [data-bs-theme="dark"] .admin-card-header {
            background: #1c2128;
            border-color: #30363d;
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .table,
        [data-bs-theme="dark"] .admin-table {
            color: #c9d1d9;
        }
        [data-bs-theme="dark"] .table thead th,
        [data-bs-theme="dark"] .admin-table thead th {
            background: #1c2128;
            color: #8b949e;
        }
        [data-bs-theme="dark"] .table td,
        [data-bs-theme="dark"] .admin-table td {
            border-color: #21262d;
        }
        [data-bs-theme="dark"] .table-hover tbody tr:hover {
            background: rgba(255,107,53,.05);
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background: #0d1117;
            border-color: #30363d;
            color: #c9d1d9;
        }
        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background: #161b22;
            border-color: #ff6b35;
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .form-label,
        [data-bs-theme="dark"] h5,
        [data-bs-theme="dark"] h4,
        [data-bs-theme="dark"] h3,
        [data-bs-theme="dark"] h2 {
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .text-muted {
            color: #8b949e !important;
        }
        [data-bs-theme="dark"] .breadcrumb-item a {
            color: #ff6b35;
        }
        [data-bs-theme="dark"] .breadcrumb-item.active {
            color: #8b949e;
        }
        [data-bs-theme="dark"] .page-link {
            background: #161b22;
            border-color: #30363d;
            color: #c9d1d9;
        }
        [data-bs-theme="dark"] .page-item.active .page-link {
            background: #ff6b35;
            border-color: #ff6b35;
            color: #fff;
        }
        [data-bs-theme="dark"] .alert-success {
            background: rgba(28,200,138,.15);
            border-color: rgba(28,200,138,.3);
            color: #4dd4a6;
        }
        [data-bs-theme="dark"] .alert-danger {
            background: rgba(231,74,59,.15);
            border-color: rgba(231,74,59,.3);
            color: #f06c6b;
        }
        [data-bs-theme="dark"] .modal-content {
            background: #161b22;
            color: #e6edf3;
        }
        [data-bs-theme="dark"] .modal-header {
            border-color: #30363d;
        }
        [data-bs-theme="dark"] .modal-footer {
            border-color: #30363d;
        }
        [data-bs-theme="dark"] .dropdown-menu {
            background: #161b22;
            border-color: #30363d;
        }
        [data-bs-theme="dark"] .dropdown-item {
            color: #c9d1d9;
        }
        [data-bs-theme="dark"] .dropdown-item:hover {
            background: #21262d;
            color: #ff6b35;
        }
        [data-bs-theme="dark"] .btn-close {
            filter: invert(1);
        }

        /* ─── Responsive ─────────────────────────── */
        @media (max-width: 991.98px) {
            .admin-topbar {
                left: 0;
            }
            .sidebar-toggle-btn {
                display: flex;
            }
            .admin-wrapper {
                margin-left: 0;
            }
            .topbar-search input {
                width: 200px;
            }
            .topbar-search input:focus {
                width: 240px;
            }
            .user-info {
                display: none;
            }
        }

        @media (max-width: 575.98px) {
            .topbar-search {
                display: none;
            }
            .admin-content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>

<!-- Top Navbar -->
<nav class="admin-topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search..." id="globalSearch">
        </div>
    </div>

    <div class="topbar-right">
        <button class="topbar-icon-btn" id="darkModeToggle" title="Toggle Dark Mode">
            <i class="fas fa-moon"></i>
        </button>

        <a href="<?= url('admin/modules/notifications/index.php') ?>" class="topbar-icon-btn" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
                <span class="notification-badge"><?= $unreadCount > 99 ? '99+' : sanitize((string)$unreadCount) ?></span>
            <?php endif; ?>
        </a>

        <div class="dropdown">
            <button class="user-dropdown dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= url('uploads/avatar/' . sanitize($user['avatar'])) ?>" alt="Avatar" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?= strtoupper(substr(sanitize($user['full_name'] ?? $user['email'] ?? 'U'), 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <span class="user-info d-none d-md-block">
                    <span class="user-name"><?= sanitize($user['full_name'] ?? $user['email'] ?? 'User') ?></span>
                    <span class="user-role"><?= sanitize($user['role_name'] ?? 'Admin') ?></span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-user">
                <li>
                    <span class="dropdown-item-text px-3 py-2">
                        <div class="fw-semibold"><?= sanitize($user['full_name'] ?? 'User') ?></div>
                        <small class="text-muted"><?= sanitize($user['email'] ?? '') ?></small>
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="<?= url('admin/modules/profile/index.php') ?>">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?= url('change_password.php') ?>">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="<?= url('logout.php') ?>">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash Messages -->
<?php flash(); ?>

<!-- Main Content Wrapper -->
<div class="admin-wrapper">
    <div class="admin-content">
