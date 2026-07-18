<?php
$pageTitle = 'Reports Dashboard';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'daily';

// Summary Stats
$totalRevenue = Database::fetch("SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE created_at BETWEEN ? AND ? AND order_status != 'cancelled'", [$dateFrom, $dateTo . ' 23:59:59']);
$totalOrders = Database::count('orders', "created_at BETWEEN ? AND ? AND order_status != 'cancelled'", [$dateFrom, $dateTo . ' 23:59:59']);
$totalCustomers = Database::count('users', 'role_id = 4');
$avgOrderValue = $totalOrders > 0 ? $totalRevenue['total'] / $totalOrders : 0;
$todayRevenue = Database::fetch("SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE DATE(created_at) = CURDATE() AND order_status != 'cancelled'");
$pendingOrders = Database::count('orders', "order_status = 'pending'");

// Daily Sales Data
$dailySales = Database::fetchAll(
    "SELECT DATE(created_at) AS sale_date, COUNT(*) AS order_count, SUM(total) AS revenue
     FROM orders WHERE created_at BETWEEN ? AND ? AND order_status != 'cancelled'
     GROUP BY DATE(created_at) ORDER BY sale_date ASC",
    [$dateFrom, $dateTo . ' 23:59:59']
);

$dailyLabels = array_map(fn($d) => date('M d', strtotime($d['sale_date'])), $dailySales);
$dailyRevenue = array_map(fn($d) => (float)$d['revenue'], $dailySales);
$dailyOrders = array_map(fn($d) => (int)$d['order_count'], $dailySales);

// Monthly Sales Data (current year)
$currentYear = date('Y');
$monthlySales = Database::fetchAll(
    "SELECT MONTH(created_at) AS sale_month, COUNT(*) AS order_count, SUM(total) AS revenue
     FROM orders WHERE YEAR(created_at) = ? AND order_status != 'cancelled'
     GROUP BY MONTH(created_at) ORDER BY sale_month ASC",
    [$currentYear]
);

$monthlyData = array_fill(0, 12, 0);
$monthlyOrderData = array_fill(0, 12, 0);
foreach ($monthlySales as $ms) {
    $monthlyData[$ms['sale_month'] - 1] = (float)$ms['revenue'];
    $monthlyOrderData[$ms['sale_month'] - 1] = (int)$ms['order_count'];
}
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Annual Revenue (last 5 years)
$annualSales = Database::fetchAll(
    "SELECT YEAR(created_at) AS sale_year, SUM(total) AS revenue
     FROM orders WHERE YEAR(created_at) >= ? - 4 AND order_status != 'cancelled'
     GROUP BY YEAR(created_at) ORDER BY sale_year ASC",
    [$currentYear]
);

$annualLabels = [];
$annualRevenue = [];
$annualDataMap = [];
foreach ($annualSales as $as) {
    $annualLabels[] = $as['sale_year'];
    $annualRevenue[] = (float)$as['revenue'];
    $annualDataMap[$as['sale_year']] = (float)$as['revenue'];
}
for ($y = $currentYear - 4; $y <= $currentYear; $y++) {
    if (!in_array($y, $annualLabels)) {
        $annualLabels[] = $y;
        $annualRevenue[] = 0;
    }
}
array_multisort($annualLabels, SORT_ASC, $annualRevenue);

$annualTotal = array_sum($annualRevenue);

// Best Selling Foods
$bestSelling = Database::fetchAll(
    "SELECT fi.id, fi.name, fc.name AS category, COALESCE(SUM(oi.quantity), 0) AS total_ordered,
            COALESCE(SUM(oi.quantity * oi.price), 0) AS total_revenue,
            COALESCE(AVG(fi.rating_avg), 0) AS avg_rating
     FROM food_items fi
     LEFT JOIN order_items oi ON fi.id = oi.food_id
     LEFT JOIN orders o ON oi.order_id = o.id AND o.order_status != 'cancelled'
     LEFT JOIN food_categories fc ON fi.category_id = fc.id
     WHERE o.created_at BETWEEN ? AND ?
     GROUP BY fi.id ORDER BY total_ordered DESC LIMIT 15",
    [$dateFrom, $dateTo . ' 23:59:59']
);

$bestLabels = array_map(fn($b) => $b['name'], $bestSelling);
$bestRevenueData = array_map(fn($b) => (float)$b['total_revenue'], $bestSelling);
$bestOrderData = array_map(fn($b) => (int)$b['total_ordered'], $bestSelling);

// Customer Report
$topCustomers = Database::fetchAll(
    "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS fullname, u.email, COUNT(o.id) AS order_count, COALESCE(SUM(o.total), 0) AS total_spent
     FROM users u
     INNER JOIN orders o ON u.id = o.customer_id AND o.order_status != 'cancelled'
     WHERE o.created_at BETWEEN ? AND ?
     GROUP BY u.id ORDER BY total_spent DESC LIMIT 15",
    [$dateFrom, $dateTo . ' 23:59:59']
);

$customerLabels = array_map(fn($c) => $c['fullname'], $topCustomers);
$customerSpentData = array_map(fn($c) => (float)$c['total_spent'], $topCustomers);
$customerOrderData = array_map(fn($c) => (int)$c['order_count'], $topCustomers);
?>

<style>
    @media print {
        .no-print { display: none !important; }
        .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
        body { font-size: 12px; }
    }
    .report-tabs .nav-link { font-weight: 500; border-radius: 8px; margin-right: 4px; }
    .report-tabs .nav-link.active { font-weight: 700; }
    .stat-card { border: none; border-radius: 12px; transition: transform 0.3s; }
    .stat-card:hover { transform: translateY(-3px); }
    .chart-container { position: relative; height: 350px; }
    .rank-badge { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; }
    .rank-1 { background: #ffd700; color: #333; }
    .rank-2 { background: #c0c0c0; color: #333; }
    .rank-3 { background: #cd7f32; color: #fff; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h4 class="mb-1 fw-bold">Reports Dashboard</h4>
            <p class="text-muted mb-0">Comprehensive business analytics and insights</p>
        </div>
        <button onclick="window.print();" class="btn btn-outline-dark">
            <i class="bi bi-printer me-1"></i> Print Report
        </button>
    </div>

    <!-- Date Filter -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Apply</button>
                </div>
                <div class="col-md-2">
                    <a href="<?= url('admin/modules/reports/') ?>" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-primary mb-2"><i class="bi bi-currency-dollar display-6"></i></div>
                    <h6 class="text-muted small mb-1">Total Revenue</h6>
                    <h5 class="fw-bold mb-0"><?= currency($totalRevenue['total']) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-success mb-2"><i class="bi bi-receipt-cutoff display-6"></i></div>
                    <h6 class="text-muted small mb-1">Total Orders</h6>
                    <h5 class="fw-bold mb-0"><?= number_format($totalOrders) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-info mb-2"><i class="bi bi-people display-6"></i></div>
                    <h6 class="text-muted small mb-1">Total Customers</h6>
                    <h5 class="fw-bold mb-0"><?= number_format($totalCustomers) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-warning mb-2"><i class="bi bi-graph-up-arrow display-6"></i></div>
                    <h6 class="text-muted small mb-1">Avg. Order Value</h6>
                    <h5 class="fw-bold mb-0"><?= currency($avgOrderValue) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-danger mb-2"><i class="bi bi-cash-stack display-6"></i></div>
                    <h6 class="text-muted small mb-1">Today's Revenue</h6>
                    <h5 class="fw-bold mb-0"><?= currency($todayRevenue['total']) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-secondary mb-2"><i class="bi bi-hourglass-split display-6"></i></div>
                    <h6 class="text-muted small mb-1">Pending Orders</h6>
                    <h5 class="fw-bold mb-0"><?= number_format($pendingOrders) ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Tabs -->
    <div class="card shadow-sm">
        <div class="card-header bg-white no-print">
            <ul class="nav nav-tabs report-tabs" id="reportTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'daily' ? 'active' : '' ?>" href="?tab=daily&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                        <i class="bi bi-calendar-day me-1"></i> Daily Sales
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'monthly' ? 'active' : '' ?>" href="?tab=monthly&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                        <i class="bi bi-calendar-month me-1"></i> Monthly Sales
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'annual' ? 'active' : '' ?>" href="?tab=annual&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                        <i class="bi bi-calendar me-1"></i> Annual Revenue
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'bestselling' ? 'active' : '' ?>" href="?tab=bestselling&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                        <i class="bi bi-trophy me-1"></i> Best Selling
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'customers' ? 'active' : '' ?>" href="?tab=customers&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                        <i class="bi bi-people me-1"></i> Customers
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">

            <!-- Daily Sales Tab -->
            <?php if ($activeTab === 'daily'): ?>
                <h5 class="fw-bold mb-3"><i class="bi bi-calendar-day me-2"></i>Daily Sales Report</h5>
                <div class="chart-container mb-4">
                    <canvas id="dailySalesChart"></canvas>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr><th>Date</th><th class="text-center">Orders</th><th class="text-end">Revenue</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dailySales as $ds): ?>
                                <tr>
                                    <td><?= date('l, M d, Y', strtotime($ds['sale_date'])) ?></td>
                                    <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= $ds['order_count'] ?></span></td>
                                    <td class="text-end fw-semibold"><?= currency($ds['revenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($dailySales)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">No sales data for the selected period</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <!-- Monthly Sales Tab -->
            <?php elseif ($activeTab === 'monthly'): ?>
                <h5 class="fw-bold mb-3"><i class="bi bi-calendar-month me-2"></i>Monthly Sales Report (<?= $currentYear ?>)</h5>
                <div class="chart-container mb-4">
                    <canvas id="monthlySalesChart"></canvas>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr><th>Month</th><th class="text-center">Orders</th><th class="text-end">Revenue</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthLabels as $i => $ml): ?>
                                <tr>
                                    <td><?= $ml ?> <?= $currentYear ?></td>
                                    <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= $monthlyOrderData[$i] ?></span></td>
                                    <td class="text-end fw-semibold"><?= currency($monthlyData[$i]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <!-- Annual Revenue Tab -->
            <?php elseif ($activeTab === 'annual'): ?>
                <h5 class="fw-bold mb-3"><i class="bi bi-calendar me-2"></i>Annual Revenue Report</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h6 class="mb-1">5-Year Total Revenue</h6>
                                <h2 class="mb-0 fw-bold"><?= currency($annualTotal) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h6 class="mb-1">Current Year Revenue</h6>
                                <h2 class="mb-0 fw-bold"><?= currency($annualDataMap[$currentYear] ?? 0) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h6 class="mb-1">Avg. Annual Revenue</h6>
                                <h2 class="mb-0 fw-bold"><?= currency(count($annualLabels) > 0 ? $annualTotal / count($annualLabels) : 0) ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="chart-container mb-4">
                    <canvas id="annualRevenueChart"></canvas>
                </div>

            <!-- Best Selling Tab -->
            <?php elseif ($activeTab === 'bestselling'): ?>
                <h5 class="fw-bold mb-3"><i class="bi bi-trophy me-2"></i>Best Selling Foods</h5>
                <div class="chart-container mb-4">
                    <canvas id="bestSellingChart"></canvas>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="50">Rank</th>
                                <th>Food Name</th>
                                <th>Category</th>
                                <th class="text-center">Total Ordered</th>
                                <th class="text-end">Total Revenue</th>
                                <th class="text-center">Avg. Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bestSelling as $idx => $bs): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?= $idx < 3 ? 'rank-' . ($idx + 1) : 'bg-light text-dark' ?>"><?= $idx + 1 ?></span>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($bs['name']) ?></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($bs['category'] ?? 'N/A') ?></span></td>
                                    <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= number_format($bs['total_ordered']) ?></span></td>
                                    <td class="text-end fw-semibold"><?= currency($bs['total_revenue']) ?></td>
                                    <td class="text-center">
                                        <i class="bi bi-star-fill text-warning me-1"></i><?= number_format($bs['avg_rating'], 1) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($bestSelling)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No food sales data for the selected period</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <!-- Customer Report Tab -->
            <?php elseif ($activeTab === 'customers'): ?>
                <h5 class="fw-bold mb-3"><i class="bi bi-people me-2"></i>Customer Report</h5>
                <div class="chart-container mb-4">
                    <canvas id="customerReportChart"></canvas>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="50">Rank</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th class="text-center">Total Orders</th>
                                <th class="text-end">Total Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topCustomers as $idx => $tc): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?= $idx < 3 ? 'rank-' . ($idx + 1) : 'bg-light text-dark' ?>"><?= $idx + 1 ?></span>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($tc['fullname']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($tc['email']) ?></td>
                                    <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= number_format($tc['order_count']) ?></span></td>
                                    <td class="text-end fw-semibold"><?= currency($tc['total_spent']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topCustomers)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No customer data for the selected period</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartColors = {
    primary: 'rgba(13, 110, 253, 0.8)',
    primaryBg: 'rgba(13, 110, 253, 0.1)',
    success: 'rgba(25, 135, 84, 0.8)',
    successBg: 'rgba(25, 135, 84, 0.1)',
    warning: 'rgba(255, 193, 7, 0.8)',
    danger: 'rgba(220, 53, 69, 0.8)',
    info: 'rgba(13, 202, 240, 0.8)'
};

function createChart(id, config) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, config);
}

// Daily Sales Chart
createChart('dailySalesChart', {
    type: 'bar',
    data: {
        labels: <?= json_encode($dailyLabels) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode($dailyRevenue) ?>,
            backgroundColor: chartColors.primary,
            borderRadius: 6,
            yAxisID: 'y'
        }, {
            label: 'Orders',
            data: <?= json_encode($dailyOrders) ?>,
            backgroundColor: chartColors.successBg,
            borderColor: chartColors.success,
            borderWidth: 2,
            type: 'line',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Revenue ($)' } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Orders' } }
        }
    }
});

// Monthly Sales Chart
createChart('monthlySalesChart', {
    type: 'bar',
    data: {
        labels: <?= json_encode($monthLabels) ?>,
        datasets: [{
            label: 'Revenue (<?= $currentYear ?>)',
            data: <?= json_encode($monthlyData) ?>,
            backgroundColor: chartColors.primary,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Annual Revenue Chart
createChart('annualRevenueChart', {
    type: 'bar',
    data: {
        labels: <?= json_encode($annualLabels) ?>,
        datasets: [{
            label: 'Annual Revenue',
            data: <?= json_encode($annualRevenue) ?>,
            backgroundColor: [chartColors.primaryBg, chartColors.successBg, chartColors.warning, chartColors.danger, chartColors.info],
            borderColor: ['rgba(13,110,253,1)', 'rgba(25,135,84,1)', 'rgba(255,193,7,1)', 'rgba(220,53,69,1)', 'rgba(13,202,240,1)'],
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Best Selling Chart
createChart('bestSellingChart', {
    type: 'bar',
    data: {
        labels: <?= json_encode($bestLabels) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode($bestRevenueData) ?>,
            backgroundColor: chartColors.primary,
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true } }
    }
});

// Customer Report Chart
createChart('customerReportChart', {
    type: 'bar',
    data: {
        labels: <?= json_encode($customerLabels) ?>,
        datasets: [{
            label: 'Total Spent',
            data: <?= json_encode($customerSpentData) ?>,
            backgroundColor: chartColors.primary,
            borderRadius: 6,
            yAxisID: 'y'
        }, {
            label: 'Orders',
            data: <?= json_encode($customerOrderData) ?>,
            backgroundColor: chartColors.warning,
            borderRadius: 6,
            yAxisID: 'y1'
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            x: { beginAtZero: true, position: 'bottom' },
            y1: { display: false }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
