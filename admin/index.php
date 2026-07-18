<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// ─── Statistics ─────────────────────────────────────────────
$totalOrders      = Database::count('orders');
$totalCustomers   = Database::count('users', 'role_id = ?', [4]);
$totalRevenue     = Database::fetch("SELECT COALESCE(SUM(total), 0) AS revenue FROM orders WHERE payment_status = 'paid'")['revenue'] ?? 0;
$totalFoodItems   = Database::count('food_items');

$pendingOrders    = Database::count('orders', "order_status = 'pending'");
$completedOrders  = Database::count('orders', "order_status = 'confirmed'");
$deliveredOrders  = Database::count('orders', "order_status = 'delivered'");
$cancelledOrders  = Database::count('orders', "order_status = 'cancelled'");

// ─── Monthly Revenue (last 6 months) ────────────────────────
$monthlyRevenue = Database::fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           DATE_FORMAT(created_at, '%b %Y') AS month_label,
           COALESCE(SUM(total), 0) AS revenue
    FROM orders
    WHERE payment_status = 'paid'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
");

$revenueLabels = [];
$revenueData   = [];
foreach ($monthlyRevenue as $row) {
    $revenueLabels[] = $row['month_label'];
    $revenueData[]   = (float) $row['revenue'];
}

// ─── Orders by Status ───────────────────────────────────────
$statusCounts = Database::fetchAll("
    SELECT order_status as `status`, COUNT(*) AS cnt
    FROM orders
    GROUP BY order_status
    ORDER BY FIELD(order_status, 'pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled')
");

$statusLabels = [];
$statusData   = [];
$statusColors = [];
$chartPalette = ['#ffc107','#17a2b8','#0d6efd','#198754','#6c757d','#20c997','#dc3545'];
$colorIndex   = 0;
foreach ($statusCounts as $row) {
    $label = ORDER_STATUSES[$row['status']] ?? ucfirst(str_replace('_', ' ', $row['status']));
    $statusLabels[] = $label;
    $statusData[]   = (int) $row['cnt'];
    $statusColors[] = $chartPalette[$colorIndex % count($chartPalette)];
    $colorIndex++;
}

// ─── Recent Orders (last 10) ────────────────────────────────
$recentOrders = Database::fetchAll("
    SELECT o.id, o.order_number, o.total, o.order_status, o.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS customer_name
    FROM orders o
    LEFT JOIN users u ON o.customer_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
");

// ─── Recent Activities ──────────────────────────────────────
$recentActivities = Database::fetchAll("
    SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
");

// ─── Popular Food Items (top 5) ────────────────────────────
$popularItems = Database::fetchAll("
    SELECT fi.id, fi.name, fi.price, fi.image,
           COALESCE(SUM(oi.quantity), 0) AS total_ordered
    FROM food_items fi
    LEFT JOIN order_items oi ON fi.id = oi.food_id
    GROUP BY fi.id, fi.name, fi.price, fi.image
    ORDER BY total_ordered DESC
    LIMIT 5
");
?>

<style>
    .dash-stat-card {
        border: none;
        border-radius: 14px;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    }
    .dash-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    }
    .dash-stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    .dash-stat-value {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.1;
        color: #212529;
    }
    .dash-stat-label {
        font-size: 0.78rem;
        color: #888;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .dash-stat-card .card-body {
        padding: 20px 22px;
    }

    .dash-mini-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 1px 8px rgba(0,0,0,0.04);
        transition: transform 0.2s;
    }
    .dash-mini-card:hover {
        transform: translateY(-2px);
    }
    .dash-mini-card .card-body {
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .dash-mini-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .dash-mini-value {
        font-size: 1.3rem;
        font-weight: 700;
        color: #212529;
    }
    .dash-mini-label {
        font-size: 0.72rem;
        color: #888;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .dash-section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .dash-section-title i {
        color: var(--primary);
    }

    .chart-card {
        border: none;
        border-radius: 14px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    }
    .chart-card .card-header {
        background: transparent;
        border-bottom: 1px solid #f0f0f0;
        padding: 16px 22px;
    }
    .chart-card .card-body {
        padding: 20px 22px;
    }

    .order-table {
        font-size: 0.85rem;
    }
    .order-table thead th {
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #888;
        border-bottom: 2px solid #f0f0f0;
        padding: 10px 14px;
    }
    .order-table tbody td {
        padding: 12px 14px;
        vertical-align: middle;
        border-bottom: 1px solid #f5f5f5;
    }
    .order-table tbody tr:hover {
        background: #fafafa;
    }

    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #f5f5f5;
    }
    .activity-item:last-child {
        border-bottom: none;
    }
    .activity-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--primary);
        margin-top: 5px;
        flex-shrink: 0;
    }
    .activity-text {
        font-size: 0.85rem;
        color: #555;
        line-height: 1.4;
    }
    .activity-text strong {
        color: #333;
    }
    .activity-time {
        font-size: 0.72rem;
        color: #aaa;
        margin-top: 2px;
    }

    .popular-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid #f5f5f5;
    }
    .popular-item:last-child {
        border-bottom: none;
    }
    .popular-img {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        object-fit: cover;
        background: #f0f0f0;
        flex-shrink: 0;
    }
    .popular-rank {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        font-size: 0.7rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .popular-name {
        font-size: 0.85rem;
        font-weight: 600;
        color: #333;
    }
    .popular-orders {
        font-size: 0.75rem;
        color: #888;
    }

    [data-bs-theme="dark"] .dash-stat-value,
    [data-bs-theme="dark"] .dash-mini-value,
    [data-bs-theme="dark"] .dash-section-title,
    [data-bs-theme="dark"] .popular-name {
        color: #e6edf3;
    }
    [data-bs-theme="dark"] .order-table tbody tr:hover {
        background: #161b22;
    }
    [data-bs-theme="dark"] .activity-text {
        color: #c9d1d9;
    }
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Dashboard</h4>
        <small class="text-muted">Welcome back, <?= sanitize($user['full_name'] ?? 'Admin') ?>. Here's what's happening today.</small>
    </div>
    <div class="text-muted" style="font-size:0.82rem;">
        <i class="fas fa-calendar-alt me-1"></i> <?= date('l, F j, Y') ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- STATISTICS CARDS -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card dash-stat-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="dash-stat-label mb-1">Total Orders</div>
                    <div class="dash-stat-value"><?= number_format($totalOrders) ?></div>
                </div>
                <div class="dash-stat-icon" style="background:#e7f1ff;color:#0d6efd;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card dash-stat-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="dash-stat-label mb-1">Total Customers</div>
                    <div class="dash-stat-value"><?= number_format($totalCustomers) ?></div>
                </div>
                <div class="dash-stat-icon" style="background:#d4edda;color:#198754;">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card dash-stat-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="dash-stat-label mb-1">Total Revenue</div>
                    <div class="dash-stat-value"><?= currency((float)$totalRevenue) ?></div>
                </div>
                <div class="dash-stat-icon" style="background:#fff3cd;color:#ff6b35;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card dash-stat-card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="dash-stat-label mb-1">Food Items</div>
                    <div class="dash-stat-value"><?= number_format($totalFoodItems) ?></div>
                </div>
                <div class="dash-stat-icon" style="background:#f3e8ff;color:#7c3aed;">
                    <i class="fas fa-utensils"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MINI STATUS CARDS -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card dash-mini-card">
            <div class="card-body">
                <div class="dash-mini-icon" style="background:#fff3cd;color:#ffc107;">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="dash-mini-value"><?= number_format($pendingOrders) ?></div>
                    <div class="dash-mini-label">Pending</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card dash-mini-card">
            <div class="card-body">
                <div class="dash-mini-icon" style="background:#d1ecf1;color:#0dcaf0;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="dash-mini-value"><?= number_format($completedOrders) ?></div>
                    <div class="dash-mini-label">Completed</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card dash-mini-card">
            <div class="card-body">
                <div class="dash-mini-icon" style="background:#d4edda;color:#198754;">
                    <i class="fas fa-truck"></i>
                </div>
                <div>
                    <div class="dash-mini-value"><?= number_format($deliveredOrders) ?></div>
                    <div class="dash-mini-label">Delivered</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card dash-mini-card">
            <div class="card-body">
                <div class="dash-mini-icon" style="background:#f8d7da;color:#dc3545;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div>
                    <div class="dash-mini-value"><?= number_format($cancelledOrders) ?></div>
                    <div class="dash-mini-label">Cancelled</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CHARTS -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-bar me-2" style="color:var(--primary);"></i>Monthly Revenue</h6>
                <span class="badge" style="background:rgba(255,107,53,0.1);color:var(--primary);">Last 6 Months</span>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="300"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-chart-pie me-2" style="color:var(--primary);"></i>Orders by Status</h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="statusChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- RECENT ORDERS + ACTIVITY LOGS -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-receipt me-2" style="color:var(--primary);"></i>Recent Orders</h6>
                <a href="<?= url('admin/modules/orders/') ?>" class="btn btn-sm" style="color:var(--primary);">View All <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table order-table mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No orders yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= sanitize($order['order_number']) ?></td>
                                        <td><?= sanitize($order['customer_name']) ?></td>
                                        <td class="fw-semibold" style="color:var(--primary);"><?= currency((float)$order['total']) ?></td>
                                        <td>
                                            <?php
                                            $statusKey = $order['order_status'];
                                            $badgeColor = ORDER_STATUS_COLORS[$statusKey] ?? 'secondary';
                                            $statusLabel = ORDER_STATUSES[$statusKey] ?? ucfirst(str_replace('_', ' ', $statusKey));
                                            ?>
                                            <span class="badge bg-<?= $badgeColor ?> bg-opacity-10 text-<?= $badgeColor ?> rounded-pill px-2 py-1" style="font-size:0.72rem;">
                                                <?= sanitize($statusLabel) ?>
                                            </span>
                                        </td>
                                        <td class="text-muted"><?= timeAgo($order['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-history me-2" style="color:var(--primary);"></i>Recent Activities</h6>
            </div>
            <div class="card-body" style="max-height:420px;overflow-y:auto;">
                <?php if (empty($recentActivities)): ?>
                    <p class="text-muted text-center mb-0">No recent activity.</p>
                <?php else: ?>
                    <?php foreach ($recentActivities as $act): ?>
                        <div class="activity-item">
                            <div class="activity-dot"></div>
                            <div>
                                <div class="activity-text">
                                    <strong><?= sanitize($act['user_name']) ?></strong>
                                    <?= sanitize($act['action']) ?>
                                    <?php if (!empty($act['description'])): ?>
                                        &mdash; <span><?= sanitize($act['description']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time"><?= timeAgo($act['created_at']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- POPULAR FOOD ITEMS -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-fire me-2" style="color:var(--primary);"></i>Popular Food Items</h6>
                <span class="badge" style="background:rgba(255,107,53,0.1);color:var(--primary);">Top 5</span>
            </div>
            <div class="card-body">
                <?php if (empty($popularItems)): ?>
                    <p class="text-muted text-center mb-0">No order data yet.</p>
                <?php else: ?>
                    <?php foreach ($popularItems as $idx => $item): ?>
                        <div class="popular-item">
                            <div class="popular-rank"><?= $idx + 1 ?></div>
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?= url('uploads/food/' . sanitize($item['image'])) ?>" alt="<?= sanitize($item['name']) ?>" class="popular-img">
                            <?php else: ?>
                                <div class="popular-img d-flex align-items-center justify-content-center">
                                    <i class="fas fa-utensils text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="popular-name"><?= sanitize($item['name']) ?></div>
                                <div class="popular-orders"><?= number_format($item['total_ordered']) ?> orders &bull; <?= currency((float)$item['price']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card chart-card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-info-circle me-2" style="color:var(--primary);"></i>Quick Summary</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background:#e7f1ff;">
                            <div class="fw-bold" style="color:#0d6efd;font-size:1.2rem;"><?= number_format($totalOrders) ?></div>
                            <div style="font-size:0.75rem;color:#555;">All Time Orders</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background:#d4edda;">
                            <div class="fw-bold" style="color:#198754;font-size:1.2rem;"><?= currency((float)$totalRevenue) ?></div>
                            <div style="font-size:0.75rem;color:#555;">Total Revenue</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background:#fff3cd;">
                            <div class="fw-bold" style="color:#cc8800;font-size:1.2rem;"><?= $totalOrders > 0 ? currency((float)$totalRevenue / $totalOrders) : currency(0) ?></div>
                            <div style="font-size:0.75rem;color:#555;">Avg. Order Value</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3" style="background:#f3e8ff;">
                            <div class="fw-bold" style="color:#7c3aed;font-size:1.2rem;"><?= $totalCustomers > 0 ? number_format($totalOrders / $totalCustomers, 1) : '0' ?></div>
                            <div style="font-size:0.75rem;color:#555;">Orders / Customer</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CHART.JS INITIALIZATION -->
<!-- ═══════════════════════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ─── Revenue Bar Chart ───────────────────────────
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($revenueLabels) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($revenueData) ?>,
                    backgroundColor: 'rgba(255, 107, 53, 0.75)',
                    borderColor: '#ff6b35',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a1a2e',
                        titleFont: { family: 'Poppins', size: 13 },
                        bodyFont: { family: 'Poppins', size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                return 'Revenue: $' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Poppins', size: 11 }, color: '#999' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            font: { family: 'Poppins', size: 11 },
                            color: '#999',
                            callback: function(val) { return '$' + val.toLocaleString(); }
                        }
                    }
                }
            }
        });
    }

    // ─── Status Doughnut Chart ───────────────────────
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statusLabels) ?>,
                datasets: [{
                    data: <?= json_encode($statusData) ?>,
                    backgroundColor: <?= json_encode($statusColors) ?>,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverBorderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            usePointStyle: true,
                            pointStyleWidth: 10,
                            font: { family: 'Poppins', size: 11 },
                            color: '#888'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1a1a2e',
                        titleFont: { family: 'Poppins', size: 13 },
                        bodyFont: { family: 'Poppins', size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
