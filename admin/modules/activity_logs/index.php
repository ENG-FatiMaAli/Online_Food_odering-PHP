<?php
$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

if (isPost()) {
    requireCSRF();

    if (isset($_POST['clear_old_logs'])) {
        $deleted = Database::delete('activity_logs', 'created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        logActivity('logs_cleared', 'Cleared activity logs older than 30 days');
        setFlash('success', 'Old activity logs cleared successfully!');
        header('Location: ' . url('admin/modules/activity_logs/'));
        exit;
    }
}

// Filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$userFilter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$viewMode = isset($_GET['view']) && $_GET['view'] === 'timeline' ? 'timeline' : 'table';

// Build query
$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (al.action LIKE ? OR al.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($userFilter) {
    $where .= " AND al.user_id = ?";
    $params[] = $userFilter;
}

if ($dateFrom) {
    $where .= " AND DATE(al.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where .= " AND DATE(al.created_at) <= ?";
    $params[] = $dateTo;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalRecords = Database::count("activity_logs al", $where, $params);
$totalPages = ceil($totalRecords / $perPage);

$logs = Database::fetchAll(
    "SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.email AS user_email
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     WHERE {$where}
     ORDER BY al.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// Stats
$totalLogs = Database::count("activity_logs");
$todayLogs = Database::count("activity_logs", "DATE(created_at) = CURDATE()");
$activeUsers = Database::count("activity_logs", "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

// All users for filter
$allUsers = Database::fetchAll("SELECT id, CONCAT(first_name, ' ', last_name) AS fullname FROM users ORDER BY first_name");

// Group logs by date for timeline view
$groupedLogs = [];
foreach ($logs as $log) {
    $dateKey = date('Y-m-d', strtotime($log['created_at']));
    $groupedLogs[$dateKey][] = $log;
}
?>

<style>
    .log-timeline { position: relative; padding-left: 30px; }
    .log-timeline::before { content: ''; position: absolute; left: 12px; top: 0; bottom: 0; width: 2px; background: #dee2e6; }
    .timeline-item { position: relative; margin-bottom: 1.5rem; animation: fadeIn 0.3s ease; }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -24px;
        top: 6px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #0d6efd;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #0d6efd;
    }
    .timeline-date { position: relative; font-weight: 700; color: #495057; margin-bottom: 1rem; padding: 8px 16px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #0d6efd; }
    .timeline-card { background: #fff; border: 1px solid #e9ecef; border-radius: 10px; padding: 16px; transition: all 0.3s; }
    .timeline-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateX(4px); }
    .action-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-card { border: none; border-radius: 12px; transition: transform 0.3s; }
    .stat-card:hover { transform: translateY(-3px); }
    .view-toggle .btn { border-radius: 8px; }
    .view-toggle .btn.active { font-weight: 700; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .log-action { font-weight: 600; text-transform: capitalize; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold">Activity Logs</h4>
            <p class="text-muted mb-0">Track all system and user activities</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group view-toggle no-print">
                <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'table'])) ?>" class="btn btn-outline-secondary <?= $viewMode === 'table' ? 'active' : '' ?>">
                    <i class="bi bi-table me-1"></i> Table
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'timeline'])) ?>" class="btn btn-outline-secondary <?= $viewMode === 'timeline' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history me-1"></i> Timeline
                </a>
            </div>
            <form method="POST" class="d-inline" onsubmit="return confirm('Clear all logs older than 30 days? This cannot be undone.');">
                <?= csrfField() ?>
                <input type="hidden" name="clear_old_logs" value="1">
                <button type="submit" class="btn btn-outline-danger no-print">
                    <i class="bi bi-trash3 me-1"></i> Clear Old Logs
                </button>
            </form>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary" style="width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small">Total Logs</h6>
                        <h3 class="mb-0 fw-bold"><?= number_format($totalLogs) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success" style="width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small">Today's Logs</h6>
                        <h3 class="mb-0 fw-bold"><?= number_format($todayLogs) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info" style="width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small">Active Users (7 days)</h6>
                        <h3 class="mb-0 fw-bold"><?= number_format($activeUsers) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Search Action/Description</label>
                    <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. login, order...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Filter by User</label>
                    <select name="user_id" class="form-select">
                        <option value="0">All Users</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userFilter == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i></button>
                </div>
                <div class="col-md-1">
                    <a href="<?= url('admin/modules/activity_logs/') ?>" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table View -->
    <?php if ($viewMode === 'table'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>Activity Log (<?= number_format($totalRecords) ?> entries)</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-journal-x display-1 text-muted"></i>
                        <p class="text-muted mt-3">No activity logs found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <?php if ($log['user_name']): ?>
                                                <span class="fw-semibold"><?= htmlspecialchars($log['user_name']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $actionColors = [
                                                'login' => 'bg-success-subtle text-success',
                                                'logout' => 'bg-secondary-subtle text-secondary',
                                                'order_placed' => 'bg-primary-subtle text-primary',
                                                'order_updated' => 'bg-warning-subtle text-warning',
                                                'order_cancelled' => 'bg-danger-subtle text-danger',
                                                'settings_updated' => 'bg-info-subtle text-info',
                                                'notification_sent' => 'bg-purple-subtle text-purple',
                                                'logs_cleared' => 'bg-danger-subtle text-danger',
                                            ];
                                            $actionClass = $actionColors[$log['action']] ?? 'bg-light text-dark';
                                            ?>
                                            <span class="action-badge <?= $actionClass ?>"><?= htmlspecialchars($log['action']) ?></span>
                                        </td>
                                        <td class="text-muted"><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                                        <td><code class="small"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code></td>
                                        <td>
                                            <small class="text-muted"><?= timeAgo($log['created_at']) ?></small><br>
                                            <small class="text-muted"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white">
                <?php
                $paginationUrl = url('admin/modules/activity_logs/?' . http_build_query(array_filter([
                    'search' => $search, 'user_id' => $userFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'view' => $viewMode
                ])));
                echo renderPagination(['page' => $page, 'pages' => $totalPages, 'total' => $totalRecords, 'offset' => $offset, 'perPage' => $perPage], $paginationUrl);
                ?>
            </div>
            <?php endif; ?>
        </div>

    <!-- Timeline View -->
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Timeline View (<?= number_format($totalRecords) ?> entries)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($groupedLogs)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-journal-x display-1 text-muted"></i>
                        <p class="text-muted mt-3">No activity logs found</p>
                    </div>
                <?php else: ?>
                    <div class="log-timeline">
                        <?php foreach ($groupedLogs as $date => $dayLogs): ?>
                            <div class="timeline-date">
                                <i class="bi bi-calendar3 me-2"></i><?= date('l, F j, Y', strtotime($date)) ?>
                                <span class="badge bg-primary-subtle text-primary ms-2"><?= count($dayLogs) ?> activities</span>
                            </div>
                            <?php foreach ($dayLogs as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="fw-semibold">
                                                    <?= htmlspecialchars($log['user_name'] ?? 'System') ?>
                                                </span>
                                                <span class="action-badge <?= $actionColors[$log['action']] ?? 'bg-light text-dark' ?> mx-2">
                                                    <?= htmlspecialchars($log['action']) ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i><?= date('H:i:s', strtotime($log['created_at'])) ?>
                                            </small>
                                        </div>
                                        <?php if ($log['description']): ?>
                                            <p class="mb-1 mt-2 text-muted small"><?= htmlspecialchars($log['description']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($log['ip_address']): ?>
                                            <small class="text-muted"><i class="bi bi-globe me-1"></i><code><?= htmlspecialchars($log['ip_address']) ?></code></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white">
                <?php
                $paginationUrl = url('admin/modules/activity_logs/?' . http_build_query(array_filter([
                    'search' => $search, 'user_id' => $userFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'view' => $viewMode
                ])));
                echo renderPagination(['page' => $page, 'pages' => $totalPages, 'total' => $totalRecords, 'offset' => $offset, 'perPage' => $perPage], $paginationUrl);
                ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
