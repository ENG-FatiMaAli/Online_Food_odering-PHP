<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentUri = $_SERVER['REQUEST_URI'];

function isActive(string ...$paths): string {
    foreach ($paths as $path) {
        if (str_contains($_SERVER['REQUEST_URI'], $path)) {
            return 'active';
        }
    }
    return '';
}

function isSectionActive(array $paths): string {
    foreach ($paths as $path) {
        if (str_contains($_SERVER['REQUEST_URI'], $path)) {
            return 'show';
        }
    }
    return '';
}

$user = currentUser();
?>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-inner">
        <!-- Logo -->
        <div class="sidebar-logo">
            <a href="<?= url('admin/index.php') ?>" class="logo-link">
                <span class="logo-icon">
                    <i class="fas fa-utensils"></i>
                </span>
                <span class="logo-text">
                    <span class="logo-brand"><?= APP_NAME ?></span>
                    <span class="logo-sub">Admin Panel</span>
                </span>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav" id="sidebarNav">
            <ul class="nav-list">

                <!-- MAIN -->
                <li class="nav-section-title">Main</li>
                <li>
                    <a href="<?= url('admin/index.php') ?>" class="nav-link <?= isActive('admin/index.php') ?>">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <!-- MANAGEMENT -->
                <li class="nav-section-title">Management</li>
                <li>
                    <a href="<?= url('admin/modules/users/') ?>" class="nav-link <?= isActive('admin/modules/users') ?>">
                        <i class="fas fa-users-cog"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/customers/') ?>" class="nav-link <?= isActive('admin/modules/customers') ?>">
                        <i class="fas fa-user-friends"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/staff/') ?>" class="nav-link <?= isActive('admin/modules/staff') ?>">
                        <i class="fas fa-user-tie"></i>
                        <span>Staff</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/drivers/') ?>" class="nav-link <?= isActive('admin/modules/drivers') ?>">
                        <i class="fas fa-truck"></i>
                        <span>Drivers</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/categories/') ?>" class="nav-link <?= isActive('admin/modules/categories') ?>">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/menu/') ?>" class="nav-link <?= isActive('admin/modules/menu') ?>">
                        <i class="fas fa-hamburger"></i>
                        <span>Menu Items</span>
                    </a>
                </li>

                <!-- OPERATIONS -->
                <li class="nav-section-title">Operations</li>
                <li>
                    <a href="<?= url('admin/modules/orders/') ?>" class="nav-link <?= isActive('admin/modules/orders') ?>">
                        <i class="fas fa-receipt"></i>
                        <span>Orders</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/payments/') ?>" class="nav-link <?= isActive('admin/modules/payments') ?>">
                        <i class="fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/delivery/') ?>" class="nav-link <?= isActive('admin/modules/delivery') ?>">
                        <i class="fas fa-shipping-fast"></i>
                        <span>Delivery</span>
                    </a>
                </li>

                <!-- ENGAGEMENT -->
                <li class="nav-section-title">Engagement</li>
                <li>
                    <a href="<?= url('admin/modules/coupons/') ?>" class="nav-link <?= isActive('admin/modules/coupons') ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Coupons</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/reviews/') ?>" class="nav-link <?= isActive('admin/modules/reviews') ?>">
                        <i class="fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/notifications/') ?>" class="nav-link <?= isActive('admin/modules/notifications') ?>">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </a>
                </li>

                <!-- SYSTEM -->
                <li class="nav-section-title">System</li>
                <li>
                    <a href="<?= url('admin/modules/reports/') ?>" class="nav-link <?= isActive('admin/modules/reports') ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/settings/') ?>" class="nav-link <?= isActive('admin/modules/settings') ?>">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li>
                    <a href="<?= url('admin/modules/activity_logs/') ?>" class="nav-link <?= isActive('admin/modules/activity_logs') ?>">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>

            </ul>
        </nav>

        <!-- Sidebar User Footer -->
        <div class="sidebar-user">
            <div class="sidebar-user-info">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= url('uploads/avatar/' . sanitize($user['avatar'])) ?>" alt="Avatar" class="sidebar-user-avatar">
                <?php else: ?>
                    <div class="sidebar-user-avatar-placeholder">
                        <?= strtoupper(substr(sanitize($user['full_name'] ?? $user['email'] ?? 'U'), 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="sidebar-user-details">
                    <span class="sidebar-user-name"><?= sanitize($user['full_name'] ?? 'User') ?></span>
                    <span class="sidebar-user-role"><?= sanitize($user['role_name'] ?? 'Admin') ?></span>
                </div>
            </div>
            <a href="<?= url('logout.php') ?>" class="sidebar-logout-btn" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>

<style>
    /* ─── Sidebar ──────────────────────────────── */
    .admin-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--sidebar-bg);
        z-index: 1040;
        transition: transform 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .sidebar-inner {
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    /* Logo */
    .sidebar-logo {
        padding: 20px 20px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .logo-link {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .logo-icon {
        width: 42px;
        height: 42px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .logo-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .logo-brand {
        font-size: 1.15rem;
        font-weight: 700;
        color: #fff;
    }

    .logo-sub {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.45);
        font-weight: 400;
    }

    /* Navigation */
    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding: 8px 0;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.1) transparent;
    }

    .sidebar-nav::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.1);
        border-radius: 4px;
    }

    .nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .nav-section-title {
        padding: 16px 24px 6px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: rgba(255,255,255,0.3);
    }

    .nav-list .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 24px;
        color: rgba(255,255,255,0.6);
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 400;
        border-left: 3px solid transparent;
        transition: all 0.2s ease;
        margin: 1px 8px;
        border-radius: 0 8px 8px 0;
    }

    .nav-list .nav-link i {
        width: 22px;
        text-align: center;
        font-size: 0.95rem;
        flex-shrink: 0;
    }

    .nav-list .nav-link:hover {
        color: #fff;
        background: rgba(255,255,255,0.06);
    }

    .nav-list .nav-link.active {
        color: #fff;
        background: rgba(255, 107, 53, 0.15);
        border-left-color: var(--primary);
        font-weight: 500;
    }

    .nav-list .nav-link.active i {
        color: var(--primary);
    }

    /* Sidebar User Footer */
    .sidebar-user {
        padding: 16px;
        border-top: 1px solid rgba(255,255,255,0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .sidebar-user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .sidebar-user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .sidebar-user-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .sidebar-user-details {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .sidebar-user-name {
        font-size: 0.8rem;
        font-weight: 600;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar-user-role {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.4);
        text-transform: capitalize;
    }

    .sidebar-logout-btn {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255,255,255,0.4);
        text-decoration: none;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .sidebar-logout-btn:hover {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
    }

    /* Overlay */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1035;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
        display: block;
        opacity: 1;
    }

    /* ─── Sidebar Collapsed ────────────────────── */
    body.sidebar-collapsed .admin-sidebar {
        transform: translateX(0);
    }

    /* ─── Responsive ───────────────────────────── */
    @media (max-width: 991.98px) {
        .admin-sidebar {
            transform: translateX(-100%);
        }

        .admin-sidebar.show {
            transform: translateX(0);
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('active');
        });
    }

    // Close sidebar on nav link click (mobile)
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                sidebar.classList.remove('show');
                overlay.classList.remove('active');
            }
        });
    });
});
</script>
