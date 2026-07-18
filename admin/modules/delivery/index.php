<?php
$pageTitle = 'Delivery Tracking Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (isPost()) {
    requireCSRF();

    if (isset($_POST['assign_driver'])) {
        $orderId = (int)$_POST['order_id'];
        $driverId = (int)$_POST['driver_id'];
        Database::update('orders', ['driver_id' => $driverId, 'order_status' => 'out_for_delivery'], 'id = ?', [$orderId]);
        Database::insert('delivery_tracking', [
            'order_id' => $orderId,
            'driver_id' => $driverId,
            'latitude' => 0,
            'longitude' => 0,
            'status' => 'assigned',
        ]);
        logActivity('Assigned driver to order #' . $orderId);
        setFlash('success', 'Driver assigned successfully.');
        header('Location: ' . url('admin/modules/delivery/index.php'));
        exit;
    }

    if (isset($_POST['update_status'])) {
        $orderId = (int)$_POST['order_id'];
        $status = sanitize($_POST['status']);
        $updateData = ['order_status' => $status];
        if ($status === 'delivered') {
            $updateData['actual_delivery'] = date('Y-m-d H:i:s');
        }
        Database::update('orders', $updateData, 'id = ?', [$orderId]);
        $driverId = $_POST['driver_id'] ?? 0;
        if ($driverId) {
            Database::insert('delivery_tracking', [
                'order_id' => $orderId,
                'driver_id' => $driverId,
                'latitude' => 0,
                'longitude' => 0,
                'status' => $status,
            ]);
        }
        logActivity('Updated order #' . $orderId . ' status to ' . $status);
        setFlash('success', 'Delivery status updated.');
        header('Location: ' . url('admin/modules/delivery/index.php'));
        exit;
    }

    if (isset($_POST['mark_delivered'])) {
        $orderId = (int)$_POST['order_id'];
        Database::update('orders', [
            'order_status' => 'delivered',
            'actual_delivery' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$orderId]);
        $driverId = $_POST['driver_id'] ?? 0;
        if ($driverId) {
            Database::insert('delivery_tracking', [
                'order_id' => $orderId,
                'driver_id' => $driverId,
                'latitude' => 0,
                'longitude' => 0,
                'status' => 'delivered',
            ]);
        }
        logActivity('Marked order #' . $orderId . ' as delivered');
        setFlash('success', 'Order marked as delivered.');
        header('Location: ' . url('admin/modules/delivery/index.php'));
        exit;
    }
}

$statusFilter = $_GET['status'] ?? '';
$driverFilter = $_GET['driver'] ?? '';
$dateFilter = $_GET['date'] ?? '';

if ($action === 'list' || $action === 'track') {

    $stats = [
        'total' => Database::count('orders', "order_status IN ('confirmed','preparing','ready','out_for_delivery','delivered')"),
        'in_transit' => Database::count('orders', "order_status = 'out_for_delivery'"),
        'delivered_today' => Database::count('orders', "order_status = 'delivered' AND DATE(actual_delivery) = CURDATE()"),
        'pending_assignment' => Database::count('orders', "order_status = 'ready' AND driver_id IS NULL"),
    ];
}

if ($action === 'list'):

    $where = "o.order_status IN ('confirmed','preparing','ready','out_for_delivery')";
    $params = [];

    if ($statusFilter) {
        $where .= " AND o.order_status = ?";
        $params[] = $statusFilter;
    }
    if ($driverFilter) {
        $where .= " AND o.driver_id = ?";
        $params[] = (int)$driverFilter;
    }
    if ($dateFilter) {
        $where .= " AND DATE(o.created_at) = ?";
        $params[] = $dateFilter;
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = 15;
    $offset = ($page - 1) * $perPage;
    $total = Database::count('orders o', $where, $params);
    $totalPages = max(1, ceil($total / $perPage));
    $deliveries = Database::fetchAll(
        "SELECT o.*, 
                CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
                CONCAT(du.first_name, ' ', du.last_name) AS driver_name,
                dp.vehicle_type, dp.license_plate, dp.is_available
         FROM orders o
         LEFT JOIN users cu ON o.customer_id = cu.id
         LEFT JOIN users du ON o.driver_id = du.id
         LEFT JOIN driver_profiles dp ON du.id = dp.user_id
         WHERE $where
         ORDER BY 
            CASE o.order_status 
                WHEN 'out_for_delivery' THEN 1 
                WHEN 'ready' THEN 2 
                WHEN 'preparing' THEN 3 
                WHEN 'confirmed' THEN 4 
            END,
            o.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, ($page - 1) * $perPage])
    );

    $availableDrivers = Database::fetchAll(
        "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, dp.vehicle_type, dp.license_plate
         FROM users u
         JOIN driver_profiles dp ON u.id = dp.user_id
         WHERE dp.is_available = 1"
    );

    $allDrivers = Database::fetchAll(
        "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, dp.vehicle_type
         FROM users u
         JOIN driver_profiles dp ON u.id = dp.user_id"
    );
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-truck me-2"></i>Delivery Tracking</h4>
    <div>
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#filterModal">
            <i class="fas fa-filter me-1"></i>Filters
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-shipping-fast fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['total'] ?></h3>
                <small>Total Deliveries</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-road fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['in_transit'] ?></h3>
                <small>In Transit</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['delivered_today'] ?></h3>
                <small>Delivered Today</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body text-center">
                <i class="fas fa-user-clock fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['pending_assignment'] ?></h3>
                <small>Pending Assignment</small>
            </div>
        </div>
    </div>
</div>

<?php if ($statusFilter || $driverFilter || $dateFilter): ?>
<div class="mb-3">
    <span class="text-muted">Active Filters:</span>
    <?php if ($statusFilter): ?>
        <span class="badge bg-info me-1"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $statusFilter))) ?>
            <a href="?status=&driver=<?= $driverFilter ?>&date=<?= $dateFilter ?>" class="text-white ms-1">&times;</a>
        </span>
    <?php endif; ?>
    <?php if ($driverFilter): ?>
        <?php $dName = Database::fetchAll("SELECT CONCAT(first_name,' ',last_name) AS n FROM users WHERE id = ?", [(int)$driverFilter]); ?>
        <span class="badge bg-info me-1"><?= htmlspecialchars($dName[0]['n'] ?? 'Driver') ?>
            <a href="?status=<?= $statusFilter ?>&driver=&date=<?= $dateFilter ?>" class="text-white ms-1">&times;</a>
        </span>
    <?php endif; ?>
    <?php if ($dateFilter): ?>
        <span class="badge bg-info me-1"><?= htmlspecialchars($dateFilter) ?>
            <a href="?status=<?= $statusFilter ?>&driver=<?= $driverFilter ?>&date=" class="text-white ms-1">&times;</a>
        </span>
    <?php endif; ?>
    <a href="<?= url('admin/modules/delivery/index.php') ?>" class="btn btn-sm btn-outline-secondary ms-2">Clear All</a>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Driver</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Est. Delivery</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deliveries)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>No deliveries found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deliveries as $d): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($d['order_number']) ?></strong></td>
                                <td><?= htmlspecialchars($d['customer_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($d['driver_name']): ?>
                                        <span><?= htmlspecialchars($d['driver_name']) ?></span><br>
                                        <small class="text-muted"><?= htmlspecialchars($d['vehicle_type'] ?? '') ?> <?= htmlspecialchars($d['license_plate'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= htmlspecialchars($d['delivery_address'] ?? 'N/A') ?></small></td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'confirmed' => 'primary',
                                        'preparing' => 'info',
                                        'ready' => 'warning',
                                        'out_for_delivery' => 'success',
                                        'delivered' => 'secondary',
                                    ];
                                    $color = $statusColors[$d['order_status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $d['order_status'])) ?></span>
                                </td>
                                <td>
                                    <?php if ($d['estimated_delivery']): ?>
                                        <small><?= date('M d, g:i A', strtotime($d['estimated_delivery'])) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">N/A</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if (!$d['driver_id']): ?>
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignModal<?= $d['id'] ?>" title="Assign Driver">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="?action=track&id=<?= $d['id'] ?>" class="btn btn-outline-info" title="Track">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </a>
                                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#statusModal<?= $d['id'] ?>" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($d['order_status'] !== 'delivered'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Mark this order as delivered?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="order_id" value="<?= $d['id'] ?>">
                                                <input type="hidden" name="driver_id" value="<?= $d['driver_id'] ?? 0 ?>">
                                                <button type="submit" name="mark_delivered" class="btn btn-outline-success" title="Mark Delivered">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Assign Driver Modal -->
                                    <div class="modal fade" id="assignModal<?= $d['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="order_id" value="<?= $d['id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Assign Driver - Order #<?= htmlspecialchars($d['order_number']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <?php if (empty($availableDrivers)): ?>
                                                            <div class="alert alert-warning mb-0">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>No available drivers at the moment.
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="mb-3">
                                                                <label class="form-label">Select Driver</label>
                                                                <select name="driver_id" class="form-select" required>
                                                                    <option value="">-- Select Driver --</option>
                                                                    <?php foreach ($availableDrivers as $drv): ?>
                                                                        <option value="<?= $drv['id'] ?>">
                                                                            <?= htmlspecialchars($drv['name']) ?> (<?= htmlspecialchars($drv['vehicle_type'] ?? '') ?> - <?= htmlspecialchars($drv['license_plate'] ?? '') ?>)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <?php if (!empty($availableDrivers)): ?>
                                                            <button type="submit" name="assign_driver" class="btn btn-primary">Assign Driver</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Update Status Modal -->
                                    <div class="modal fade" id="statusModal<?= $d['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="order_id" value="<?= $d['id'] ?>">
                                                    <input type="hidden" name="driver_id" value="<?= $d['driver_id'] ?? 0 ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Status - Order #<?= htmlspecialchars($d['order_number']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Order Status</label>
                                                            <select name="status" class="form-select" required>
                                                                <?php foreach (ORDER_STATUSES as $stKey => $stLabel): ?>
                                                                    <option value="<?= $stKey ?>" <?= $stKey === $d['order_status'] ? 'selected' : '' ?>>
                                                                        <?= $stLabel ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$paginationUrl = url('admin/modules/delivery/index.php') . '?status=' . urlencode($statusFilter) . '&driver=' . urlencode($driverFilter) . '&date=' . urlencode($dateFilter);
echo renderPagination(['page' => $page, 'pages' => $totalPages, 'total' => $total, 'offset' => $offset, 'perPage' => $perPage], $paginationUrl);
?>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-filter me-2"></i>Filter Deliveries</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="list">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach (ORDER_STATUSES as $stKey => $stLabel): ?>
                                <option value="<?= $stKey ?>" <?= $statusFilter === $stKey ? 'selected' : '' ?>>
                                    <?= $stLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Driver</label>
                        <select name="driver" class="form-select">
                            <option value="">All Drivers</option>
                            <?php foreach ($allDrivers as $drv): ?>
                                <option value="<?= $drv['id'] ?>" <?= (int)$driverFilter === (int)$drv['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($drv['name']) ?> (<?= htmlspecialchars($drv['vehicle_type'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?= url('admin/modules/delivery/index.php') ?>" class="btn btn-outline-secondary">Clear Filters</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($action === 'track' && $id): ?>

<?php
$order = Database::fetchAll(
    "SELECT o.*, 
            CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
            CONCAT(du.first_name, ' ', du.last_name) AS driver_name
     FROM orders o
     LEFT JOIN users cu ON o.customer_id = cu.id
     LEFT JOIN users du ON o.driver_id = du.id
     WHERE o.id = ?",
    [$id]
);

if (empty($order)) {
    setFlash('error', 'Order not found.');
    header('Location: ' . url('admin/modules/delivery/index.php'));
    exit;
}
$order = $order[0];

$trackingPoints = Database::fetchAll(
    "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) AS driver_name
     FROM delivery_tracking t
     LEFT JOIN users u ON t.driver_id = u.id
     WHERE t.order_id = ?
     ORDER BY t.created_at ASC",
    [$id]
);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= url('admin/modules/delivery/index.php') ?>" class="text-decoration-none">
            <i class="fas fa-arrow-left me-2"></i>Back to Deliveries
        </a>
        <h4 class="mb-0 mt-1"><i class="fas fa-map-marker-alt me-2"></i>Track Order #<?= htmlspecialchars($order['order_number']) ?></h4>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-1"></i>Order Details</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Order #</td>
                        <td><strong>#<?= htmlspecialchars($order['order_number']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Customer</td>
                        <td><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Driver</td>
                        <td><?= htmlspecialchars($order['driver_name'] ?? 'Unassigned') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Address</td>
                        <td><small><?= htmlspecialchars($order['delivery_address'] ?? 'N/A') ?></small></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td>
                            <?php
                            $statusColors = [
                                'confirmed' => 'primary',
                                'preparing' => 'info',
                                'ready' => 'warning',
                                'out_for_delivery' => 'success',
                                'delivered' => 'secondary',
                            ];
                            $color = $statusColors[$order['order_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $order['order_status'])) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Est. Delivery</td>
                        <td><?= $order['estimated_delivery'] ? date('M d, g:i A', strtotime($order['estimated_delivery'])) : 'N/A' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Actual Delivery</td>
                        <td><?= $order['actual_delivery'] ? date('M d, g:i A', strtotime($order['actual_delivery'])) : 'Pending' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created</td>
                        <td><?= timeAgo($order['created_at']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-route me-1"></i>Delivery Timeline</h6>
            </div>
            <div class="card-body">
                <?php if (empty($trackingPoints)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-satellite-dish fa-3x mb-3 d-block"></i>
                        <p class="mb-0">No tracking updates yet.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($trackingPoints as $index => $point): ?>
                            <div class="d-flex mb-4">
                                <div class="me-3 position-relative">
                                    <div class="rounded-circle bg-<?= $index === count($trackingPoints) - 1 ? 'success' : 'primary' ?> text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                        <i class="fas fa-<?= $point['status'] === 'delivered' ? 'check' : ($point['status'] === 'assigned' ? 'user-plus' : 'location-arrow') ?>"></i>
                                    </div>
                                    <?php if ($index < count($trackingPoints) - 1): ?>
                                        <div class="position-absolute" style="left:19px;top:40px;width:2px;height:calc(100% + 1rem);background:#dee2e6;"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="card border-0 shadow-sm <?= $index === count($trackingPoints) - 1 ? 'border-start border-success border-3' : '' ?>">
                                        <div class="card-body py-2 px-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong class="text-capitalize"><?= str_replace('_', ' ', $point['status']) ?></strong>
                                                <small class="text-muted"><?= date('M d, g:i:s A', strtotime($point['created_at'])) ?></small>
                                            </div>
                                            <small class="text-muted">
                                                Driver: <?= htmlspecialchars($point['driver_name'] ?? 'N/A') ?>
                                                <?php if ($point['latitude'] && $point['longitude']): ?>
                                                    | Coordinates: <?= round($point['latitude'], 6) ?>, <?= round($point['longitude'], 6) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
    <div class="alert alert-danger">Invalid action.</div>
    <?php header('Location: ' . url('admin/modules/delivery/index.php')); exit; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
