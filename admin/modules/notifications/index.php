<?php
$pageTitle = 'Notification Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Handle actions
if (isPost()) {
    requireCSRF();

    if (isset($_POST['send_notification'])) {
        $userId = sanitize($_POST['user_id']);
        $title = sanitize($_POST['title']);
        $message = sanitize($_POST['message']);
        $type = sanitize($_POST['type']);

        if ($userId === 'all') {
            $users = Database::fetchAll("SELECT id FROM users WHERE role_id != 1");
            foreach ($users as $u) {
                Database::insert('notifications', [
                    'user_id' => $u['id'],
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'is_read' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            setFlash('success', 'Notification sent to all users!');
        } else {
            Database::insert('notifications', [
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            setFlash('success', 'Notification sent successfully!');
        }
        logActivity('notification_sent', "Sent notification: {$title}");
        header('Location: ' . url('admin/modules/notifications/'));
        exit;
    }

    if (isset($_POST['toggle_read'])) {
        $notifId = (int)$_POST['notif_id'];
        $current = Database::fetch("SELECT is_read FROM notifications WHERE id = ?", [$notifId]);
        if ($current) {
            Database::update('notifications', ['is_read' => $current['is_read'] ? 0 : 1], 'id = ?', [$notifId]);
            setFlash('success', 'Notification status updated!');
        }
        header('Location: ' . url('admin/modules/notifications/'));
        exit;
    }

    if (isset($_POST['mark_all_read'])) {
        Database::update('notifications', ['is_read' => 1], 'is_read = 0');
        setFlash('success', 'All notifications marked as read!');
        header('Location: ' . url('admin/modules/notifications/'));
        exit;
    }

    if (isset($_POST['delete_notification'])) {
        $notifId = (int)$_POST['notif_id'];
        Database::delete('notifications', 'id = ?', [$notifId]);
        setFlash('success', 'Notification deleted!');
        header('Location: ' . url('admin/modules/notifications/'));
        exit;
    }
}

// Filters
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$readFilter = isset($_GET['read_status']) ? $_GET['read_status'] : '';

// Build query
$where = "1=1";
$params = [];

if ($typeFilter && in_array($typeFilter, ['info', 'success', 'warning', 'danger'])) {
    $where .= " AND n.type = ?";
    $params[] = $typeFilter;
}

if ($readFilter === 'unread') {
    $where .= " AND n.is_read = 0";
} elseif ($readFilter === 'read') {
    $where .= " AND n.is_read = 1";
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$totalRecords = Database::count("notifications n", $where, $params);
$totalPages = ceil($totalRecords / $perPage);

$notifications = Database::fetchAll(
    "SELECT n.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.email AS user_email
     FROM notifications n
     LEFT JOIN users u ON n.user_id = u.id
     WHERE {$where}
     ORDER BY n.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// Stats
$totalNotifications = Database::count("notifications");
$unreadCount = Database::count("notifications", "is_read = 0");
$sentToday = Database::count("notifications", "DATE(created_at) = CURDATE()");

// Get users for dropdown
$users = Database::fetchAll("SELECT id, CONCAT(first_name, ' ', last_name) AS fullname, email FROM users WHERE role_id != 1 ORDER BY first_name");
?>

<style>
    .notification-card { border-left: 4px solid; transition: all 0.3s; }
    .notification-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); transform: translateY(-1px); }
    .notification-card.info { border-left-color: #0d6efd; }
    .notification-card.success { border-left-color: #198754; }
    .notification-card.warning { border-left-color: #ffc107; }
    .notification-card.danger { border-left-color: #dc3545; }
    .unread-badge { font-size: 8px; width: 10px; height: 10px; border-radius: 50%; display: inline-block; background: #dc3545; }
    .stat-card { border: none; border-radius: 12px; overflow: hidden; transition: transform 0.3s; }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-card .card-body { padding: 1.25rem; }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold">Notification Management</h4>
            <p class="text-muted mb-0">Manage and send notifications to users</p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="mark_all_read" value="1">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-check-all me-1"></i> Mark All Read
                </button>
            </form>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
                <i class="bi bi-send me-1"></i> Send Notification
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-bell"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small">Total Notifications</h6>
                        <h3 class="mb-0 fw-bold"><?= number_format($totalNotifications) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-envelope-exclamation"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small">Unread</h6>
                        <h3 class="mb-0 fw-bold"><?= number_format($unreadCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-send-check"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0 small">Sent Today</h6>
                        <h3 class="mb-0 fw-bold"><?= number_format($sentToday) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Filter by Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="info" <?= $typeFilter === 'info' ? 'selected' : '' ?>>Info</option>
                        <option value="success" <?= $typeFilter === 'success' ? 'selected' : '' ?>>Success</option>
                        <option value="warning" <?= $typeFilter === 'warning' ? 'selected' : '' ?>>Warning</option>
                        <option value="danger" <?= $typeFilter === 'danger' ? 'selected' : '' ?>>Danger</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Read Status</label>
                    <select name="read_status" class="form-select">
                        <option value="">All</option>
                        <option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>Unread</option>
                        <option value="read" <?= $readFilter === 'read' ? 'selected' : '' ?>>Read</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="<?= url('admin/modules/notifications/') ?>" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>Notifications (<?= number_format($totalRecords) ?>)</h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bell-slash display-1 text-muted"></i>
                    <p class="text-muted mt-3">No notifications found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="30"></th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Type</th>
                                <th>User</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th width="140" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notif): ?>
                                <tr class="<?= !$notif['is_read'] ? 'table-light' : '' ?>">
                                    <td>
                                        <?php if (!$notif['is_read']): ?>
                                            <span class="unread-badge" title="Unread"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($notif['title']) ?></td>
                                    <td>
                                        <span class="text-muted d-inline-block text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($notif['message']) ?>">
                                            <?= htmlspecialchars($notif['message']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = match($notif['type']) {
                                            'info' => 'bg-info text-dark',
                                            'success' => 'bg-success',
                                            'warning' => 'bg-warning text-dark',
                                            'danger' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?> text-capitalize"><?= $notif['type'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($notif['user_name']): ?>
                                            <span class="fw-semibold"><?= htmlspecialchars($notif['user_name']) ?></span><br>
                                            <small class="text-muted"><?= htmlspecialchars($notif['user_email']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">User #<?= $notif['user_id'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($notif['is_read']): ?>
                                            <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Read</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger"><i class="bi bi-envelope me-1"></i>Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= timeAgo($notif['created_at']) ?></small><br>
                                        <small class="text-muted"><?= date('M d, Y H:i', strtotime($notif['created_at'])) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="toggle_read" value="1">
                                            <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $notif['is_read'] ? 'warning' : 'success' ?>" title="<?= $notif['is_read'] ? 'Mark Unread' : 'Mark Read' ?>">
                                                <i class="bi bi-<?= $notif['is_read'] ? 'envelope' : 'check-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this notification?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="delete_notification" value="1">
                                            <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
            $paginationUrl = url('admin/modules/notifications/?' . http_build_query(array_filter(['type' => $typeFilter, 'read_status' => $readFilter])));
            echo renderPagination(['page' => $page, 'pages' => $totalPages, 'total' => $totalRecords, 'offset' => $offset, 'perPage' => $perPage], $paginationUrl);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Send Notification Modal -->
<div class="modal fade" id="sendNotificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-send me-2"></i>Send Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Send To <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="all">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="255" placeholder="Enter notification title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="4" required placeholder="Enter notification message"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select" required>
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="danger">Danger</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_notification" value="1" class="btn btn-primary"><i class="bi bi-send me-1"></i> Send</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
