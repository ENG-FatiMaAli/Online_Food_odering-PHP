<?php
$pageTitle = 'My Orders';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../includes/functions.php';

$userId = $_SESSION['user_id'] ?? 0;

if (!isLoggedIn() || !isCustomer()) {
    setFlash('warning', 'Please login to view your orders.');
    redirect(url('login.php'));
}

$action = $_GET['action'] ?? 'list';

// ─── CSRF Check ──────────────────────────────────────────────
if (isPost()) {
    requireCSRF();
}

// ─── Cancel Order ────────────────────────────────────────────
if (isPost() && ($_POST['action'] ?? '') === 'cancel') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $order = Database::fetch("SELECT id, order_number, order_status FROM orders WHERE id = ? AND customer_id = ?", [$orderId, $userId]);

    if ($order && in_array($order['order_status'], ['pending', 'confirmed'])) {
        Database::update('orders', ['order_status' => 'cancelled'], 'id = ?', [$orderId]);
        logActivity('order_cancelled', "Order {$order['order_number']} cancelled by customer.");
        setFlash('success', 'Order has been cancelled successfully.');
    } else {
        setFlash('error', 'This order cannot be cancelled.');
    }
    redirect(url('customer/modules/orders/'));
}

// ─── Reorder ─────────────────────────────────────────────────
if (isPost() && ($_POST['action'] ?? '') === 'reorder') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $order = Database::fetch("SELECT id FROM orders WHERE id = ? AND customer_id = ?", [$orderId, $userId]);

    if ($order) {
        $items = Database::fetchAll("SELECT food_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);
        foreach ($items as $item) {
            $existing = Database::fetch("SELECT id, quantity FROM cart WHERE user_id = ? AND food_id = ?", [$userId, $item['food_id']]);
            if ($existing) {
                Database::update('cart', ['quantity' => $existing['quantity'] + $item['quantity']], 'id = ?', [$existing['id']]);
            } else {
                Database::insert('cart', ['user_id' => $userId, 'food_id' => $item['food_id'], 'quantity' => $item['quantity']]);
            }
        }
        setFlash('success', 'All items from this order have been added to your cart.');
    } else {
        setFlash('error', 'Order not found.');
    }
    redirect(url('customer/modules/cart/'));
}

// ─── View Order Detail ──────────────────────────────────────
if ($action === 'view') {
    $orderId = (int)($_GET['id'] ?? 0);
    $order = Database::fetch("
        SELECT o.*, u.first_name, u.last_name, u.email, u.phone
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        WHERE o.id = ? AND o.customer_id = ?
    ", [$orderId, $userId]);

    if (!$order) {
        setFlash('error', 'Order not found.');
        redirect(url('customer/modules/orders/'));
    }

    $items = Database::fetchAll("
        SELECT oi.*, fi.image
        FROM order_items oi
        LEFT JOIN food_items fi ON oi.food_id = fi.id
        WHERE oi.order_id = ?
    ", [$orderId]);

    require_once __DIR__ . '/../../includes/header.php';
    ?>

    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        .timeline-dot {
            position: absolute;
            left: -24px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #e9ecef;
            border: 2px solid #fff;
            z-index: 1;
        }
        .timeline-dot.active {
            background: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.2);
        }
        .timeline-dot.completed {
            background: #28a745;
        }
        .order-detail-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
    </style>

    <section class="bg-light py-3 border-bottom">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= url('customer/index.php') ?>" class="text-decoration-none">Menu</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('customer/modules/orders/') ?>" class="text-decoration-none">My Orders</a></li>
                    <li class="breadcrumb-item active"><?= sanitize($order['order_number']) ?></li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="fas fa-receipt text-warning me-2"></i><?= sanitize($order['order_number']) ?>
                    </h4>
                    <small class="text-muted">Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></small>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <?php if (in_array($order['order_status'], ['pending', 'confirmed'])): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger rounded-pill px-3">
                                <i class="fas fa-times me-1"></i>Cancel Order
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($order['order_status'] === 'out_for_delivery'): ?>
                        <a href="<?= url('customer/modules/orders/track.php?id=' . $order['id']) ?>" class="btn btn-warning rounded-pill px-3">
                            <i class="fas fa-truck me-1"></i>Track Delivery
                        </a>
                    <?php endif; ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reorder">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button type="submit" class="btn btn-outline-warning rounded-pill px-3">
                            <i class="fas fa-redo me-1"></i>Reorder
                        </button>
                    </form>
                </div>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                    <?= sanitize($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Order Info & Timeline -->
                <div class="col-lg-8">
                    <!-- Status Badge -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body text-center py-4">
                            <h5 class="text-muted mb-2">Current Status</h5>
                            <span class="badge fs-6 py-2 px-3 bg-<?= ORDER_STATUS_COLORS[$order['order_status']] ?? 'secondary' ?>">
                                <?= ORDER_STATUSES[$order['order_status']] ?? ucwords(str_replace('_', ' ', $order['order_status'])) ?>
                            </span>
                            <?php if ($order['estimated_delivery']): ?>
                                <p class="mt-2 mb-0 text-muted small">
                                    <i class="fas fa-clock me-1"></i>Estimated delivery: <?= date('g:i A', strtotime($order['estimated_delivery'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Status Timeline -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-clock text-warning me-2"></i>Order Timeline</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $statuses = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered'];
                            $currentIndex = array_search($order['order_status'], $statuses);
                            if ($order['order_status'] === 'cancelled'): ?>
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-dot bg-danger"></div>
                                        <div>
                                            <h6 class="mb-1 text-danger">Cancelled</h6>
                                            <small class="text-muted"><?= date('M j, Y g:i A', strtotime($order['updated_at'])) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php else:
                                foreach ($statuses as $i => $status):
                                    $isCompleted = $i <= $currentIndex;
                                    $isCurrent = $i === $currentIndex;
                                    $label = ORDER_STATUSES[$status] ?? ucwords(str_replace('_', ' ', $status));
                            ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot <?= $isCompleted ? ($isCurrent ? 'active' : 'completed') : '' ?>"></div>
                                    <div>
                                        <h6 class="mb-1 <?= $isCompleted ? '' : 'text-muted' ?>"><?= $label ?></h6>
                                        <?php if ($isCompleted && $i === 0): ?>
                                            <small class="text-muted"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></small>
                                        <?php elseif ($isCompleted && $i === $currentIndex && $order['updated_at']): ?>
                                            <small class="text-muted"><?= date('M j, Y g:i A', strtotime($order['updated_at'])) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-shopping-bag text-warning me-2"></i>Order Items</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="border-0 ps-3">Item</th>
                                            <th class="border-0 text-center">Price</th>
                                            <th class="border-0 text-center">Qty</th>
                                            <th class="border-0 text-center pe-3">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item):
                                            $itemImage = !empty($item['image']) ? url('uploads/food/' . $item['image']) : url('assets/images/placeholder-food.jpg');
                                        ?>
                                        <tr>
                                            <td class="ps-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?= $itemImage ?>" alt="<?= sanitize($item['name']) ?>" class="order-detail-img">
                                                    <div>
                                                        <h6 class="fw-bold mb-0"><?= sanitize($item['name']) ?></h6>
                                                        <?php if ($item['food_id']): ?>
                                                            <a href="<?= url('customer/modules/menu/detail.php?id=' . $item['food_id']) ?>" class="small text-decoration-none">View Item</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center"><?= currency($item['price']) ?></td>
                                            <td class="text-center"><?= $item['quantity'] ?></td>
                                            <td class="text-center pe-3 fw-bold"><?= currency($item['total']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($order['order_notes'])): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-sticky-note text-warning me-2"></i>Order Notes</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0"><?= nl2br(sanitize($order['order_notes'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Delivery Address -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-map-marker-alt text-warning me-2"></i>Delivery Address</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0"><?= nl2br(sanitize($order['delivery_address'])) ?></p>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-credit-card text-warning me-2"></i>Payment Info</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Method</span>
                                <span class="fw-bold"><?= PAYMENT_METHODS[$order['payment_method']] ?? ucfirst($order['payment_method']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Status</span>
                                <?php
                                $psClass = match($order['payment_status']) {
                                    'paid', 'completed' => 'success',
                                    'failed' => 'danger',
                                    'refunded' => 'info',
                                    default => 'warning'
                                };
                                ?>
                                <span class="badge bg-<?= $psClass ?>">
                                    <?= PAYMENT_STATUSES[$order['payment_status']] ?? ucfirst($order['payment_status']) ?>
                                </span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Subtotal</span>
                                <span><?= currency($order['subtotal']) ?></span>
                            </div>
                            <?php if ((float)$order['discount'] > 0): ?>
                            <div class="d-flex justify-content-between mb-1 text-success">
                                <span class="text-muted">Discount</span>
                                <span>-<?= currency($order['discount']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Delivery Fee</span>
                                <span><?= currency($order['delivery_fee']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Tax</span>
                                <span><?= currency($order['tax']) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span class="fs-5 fw-bold">Total</span>
                                <span class="fs-5 fw-bold text-primary"><?= currency($order['total']) ?></span>
                            </div>
                        </div>
                    </div>

                    <a href="<?= url('customer/modules/orders/') ?>" class="btn btn-outline-secondary rounded-pill w-100">
                        <i class="fas fa-arrow-left me-1"></i>Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// ─── Order List ──────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'];

$where = "customer_id = ?";
$params = [$userId];

if (!empty($statusFilter) && in_array($statusFilter, $validStatuses)) {
    $where .= " AND order_status = ?";
    $params[] = $statusFilter;
}

$perPage = ORDERS_PER_PAGE;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = Database::count("orders", $where, $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$orders = Database::fetchAll("
    SELECT o.*,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    WHERE $where
    ORDER BY o.created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);

// Build items summary per order
$orderIds = array_column($orders, 'id');
$itemSummaries = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $allItems = Database::fetchAll("
        SELECT order_id, GROUP_CONCAT(CONCAT(quantity, 'x ', name) SEPARATOR ', ') as summary
        FROM order_items WHERE order_id IN ($placeholders) GROUP BY order_id
    ", $orderIds);
    foreach ($allItems as $row) {
        $itemSummaries[$row['order_id']] = $row['summary'];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .order-card {
        border-radius: 12px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .order-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.1) !important;
    }
    .status-tab {
        border-radius: 50px;
        padding: 0.35rem 1rem;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .status-tab:hover, .status-tab.active {
        background-color: var(--primary);
        color: #fff !important;
        border-color: var(--primary);
    }
</style>

<section class="bg-light py-3 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('customer/index.php') ?>" class="text-decoration-none">Menu</a></li>
                <li class="breadcrumb-item active">My Orders</li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h4 class="fw-bold mb-4">
            <i class="fas fa-receipt text-warning me-2"></i>My Orders
            <span class="text-muted fs-6">(<?= $total ?> total)</span>
        </h4>

        <!-- Status Tabs -->
        <div class="d-flex flex-wrap gap-2 mb-4 pb-2 overflow-auto">
            <a href="<?= url('customer/modules/orders/') ?>" class="btn status-tab <?= empty($statusFilter) ? 'active' : 'btn-outline-secondary' ?>">
                <i class="fas fa-list me-1"></i>All
            </a>
            <?php foreach (ORDER_STATUSES as $key => $label):
                $count = Database::count('orders', "customer_id = ? AND order_status = ?", [$userId, $key]);
            ?>
                <a href="?status=<?= $key ?>" class="btn status-tab <?= $statusFilter === $key ? 'active' : 'btn-outline-secondary' ?>">
                    <?= $label ?>
                    <span class="ms-1 small">(<?= $count ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-receipt fa-4x text-muted mb-3" style="opacity:0.3;"></i>
                <h4 class="text-muted">No orders found</h4>
                <p class="text-muted mb-4">
                    <?= $statusFilter ? 'No orders with this status yet.' : 'You haven\'t placed any orders yet.' ?>
                </p>
                <a href="<?= url('customer/index.php') ?>" class="btn btn-warning btn-lg rounded-pill px-4">
                    <i class="fas fa-utensils me-2"></i>Browse Menu
                </a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($orders as $order): ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm order-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3 mb-2 mb-md-0">
                                        <h6 class="fw-bold mb-1"><?= sanitize($order['order_number']) ?></h6>
                                        <small class="text-muted">
                                            <i class="far fa-calendar-alt me-1"></i><?= date('M j, Y', strtotime($order['created_at'])) ?>
                                        </small>
                                    </div>
                                    <div class="col-md-4 mb-2 mb-md-0">
                                        <p class="mb-1 small text-truncate">
                                            <?= sanitize($itemSummaries[$order['id']] ?? 'N/A') ?>
                                        </p>
                                        <small class="text-muted"><?= $order['item_count'] ?> item<?= $order['item_count'] > 1 ? 's' : '' ?></small>
                                    </div>
                                    <div class="col-md-2 mb-2 mb-md-0 text-start text-md-center">
                                        <span class="fw-bold"><?= currency($order['total']) ?></span>
                                    </div>
                                    <div class="col-md-2 mb-2 mb-md-0 text-start text-md-center">
                                        <?= getOrderStatusBadge($order['order_status']) ?>
                                    </div>
                                    <div class="col-md-1 text-start text-md-end">
                                        <a href="?action=view&id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-warning rounded-pill" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&status=<?= $statusFilter ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
