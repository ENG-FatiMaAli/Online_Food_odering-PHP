<?php
$pageTitle = 'Order Details';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../includes/functions.php';

$userId = $_SESSION['user_id'] ?? 0;

if (!isLoggedIn() || !isCustomer()) {
    setFlash('warning', 'Please login to view your order.');
    redirect(url('login.php'));
}

// Handle Cancel Order
if (isPost() && ($_POST['action'] ?? '') === 'cancel') {
    requireCSRF();
    $cancelOrderId = (int)($_POST['order_id'] ?? 0);
    $cancelOrder = Database::fetch("SELECT id, order_number, order_status FROM orders WHERE id = ? AND customer_id = ?", [$cancelOrderId, $userId]);

    if ($cancelOrder && in_array($cancelOrder['order_status'], ['pending', 'confirmed'])) {
        Database::update('orders', ['order_status' => 'cancelled'], 'id = ?', [$cancelOrderId]);
        logActivity('order_cancelled', "Order {$cancelOrder['order_number']} cancelled by customer.");
        setFlash('success', 'Order has been cancelled successfully.');
    } else {
        setFlash('error', 'This order cannot be cancelled.');
    }
    redirect(url('customer/modules/orders/view.php?id=' . $cancelOrderId));
}

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId < 1) {
    setFlash('error', 'Invalid order ID.');
    redirect(url('customer/modules/orders/'));
}

$order = Database::fetch(
    "SELECT o.*, c.code AS coupon_code
     FROM orders o
     LEFT JOIN coupons c ON o.coupon_id = c.id
     WHERE o.id = ? AND o.customer_id = ?",
    [$orderId, $userId]
);

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect(url('customer/modules/orders/'));
}

$orderItems = Database::fetchAll(
    "SELECT oi.*, fi.image AS food_image
     FROM order_items oi
     LEFT JOIN food_items fi ON oi.food_id = fi.id
     WHERE oi.order_id = ?",
    [$orderId]
);

$payment = Database::fetch("SELECT * FROM payments WHERE order_id = ?", [$orderId]);

$driver = null;
if ($order['driver_id']) {
    $driver = Database::fetch("SELECT first_name, last_name, phone FROM users WHERE id = ?", [$order['driver_id']]);
}

$statusOrder = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered'];
$currentStatusIndex = array_search($order['order_status'], $statusOrder);
if ($currentStatusIndex === false) $currentStatusIndex = -1;

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .order-status-timeline { position: relative; padding: 0; list-style: none; }
    .order-status-timeline li { position: relative; padding: 0 0 24px 32px; }
    .order-status-timeline li:last-child { padding-bottom: 0; }
    .order-status-timeline li .timeline-dot {
        position: absolute; left: 0; top: 2px; width: 22px; height: 22px;
        border-radius: 50%; background: #e9ecef; border: 3px solid #e9ecef;
        display: flex; align-items: center; justify-content: center; z-index: 1;
    }
    .order-status-timeline li.completed .timeline-dot {
        background: var(--primary); border-color: var(--primary);
    }
    .order-status-timeline li.completed .timeline-dot::after {
        content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
        color: #fff; font-size: 0.6rem;
    }
    .order-status-timeline li.active .timeline-dot {
        background: #fff; border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(255,107,53,0.15);
    }
    .order-status-timeline li.active .timeline-dot::after {
        content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--primary);
    }
    .order-status-timeline li.cancelled .timeline-dot { background: #dc3545; border-color: #dc3545; }
    .order-status-timeline li.cancelled .timeline-dot::after {
        content: '\f00d'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
        color: #fff; font-size: 0.6rem;
    }
    .order-status-timeline li:not(:last-child)::before {
        content: ''; position: absolute; left: 10px; top: 26px; bottom: 0; width: 2px; background: #e9ecef;
    }
    .order-status-timeline li.completed:not(:last-child)::before { background: var(--primary); }
    .order-status-timeline li .timeline-label { font-size: 0.85rem; font-weight: 500; color: #888; }
    .order-status-timeline li.completed .timeline-label,
    .order-status-timeline li.active .timeline-label { color: #333; }
    .order-item-img { width: 60px; height: 60px; object-fit: cover; border-radius: 10px; }
</style>

<!-- Breadcrumb -->
<section class="bg-light py-3 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('customer/index.php') ?>" class="text-decoration-none">Menu</a></li>
                <li class="breadcrumb-item"><a href="<?= url('customer/modules/orders/') ?>" class="text-decoration-none">My Orders</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($order['order_number']) ?></li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="fas fa-receipt text-warning me-2"></i><?= htmlspecialchars($order['order_number']) ?>
                </h3>
                <small class="text-muted">
                    Placed on <?= date('M d, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                </small>
            </div>
            <div class="d-flex gap-2">
                <?php if (in_array($order['order_status'], ['pending', 'confirmed'])): ?>
                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger rounded-pill">
                        <i class="fas fa-times me-1"></i>Cancel Order
                    </button>
                </form>
                <?php endif; ?>
                <a href="<?= url('customer/modules/orders/') ?>" class="btn btn-outline-secondary rounded-pill">
                    <i class="fas fa-arrow-left me-1"></i>Back to Orders
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8 mb-4">

                <!-- Order Status Timeline -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-truck me-2 text-warning"></i>Order Status</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($order['order_status'] === 'cancelled'): ?>
                            <div class="d-flex align-items-center gap-3 py-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10" style="width:48px;height:48px;">
                                    <i class="fas fa-times-circle text-danger fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-danger">Order Cancelled</h6>
                                    <small class="text-muted">This order has been cancelled.</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="progress mb-4" style="height: 8px;">
                                <div class="progress-bar bg-warning" role="progressbar"
                                     style="width: <?= ($currentStatusIndex / max(count($statusOrder) - 1, 1)) * 100 ?>%"></div>
                            </div>
                            <ul class="order-status-timeline">
                                <?php foreach ($statusOrder as $i => $status): ?>
                                    <?php
                                    $state = 'pending';
                                    if ($i < $currentStatusIndex) $state = 'completed';
                                    elseif ($i === $currentStatusIndex) $state = 'active';
                                    ?>
                                    <li class="<?= $state ?>">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-label"><?= ORDER_STATUSES[$status] ?></div>
                                        <?php if ($state === 'completed'): ?>
                                            <div class="timeline-time"><i class="fas fa-check me-1"></i>Done</div>
                                        <?php elseif ($state === 'active'): ?>
                                            <div class="timeline-time"><i class="fas fa-clock me-1"></i>Current status</div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-utensils me-2 text-warning"></i>Order Items (<?= count($orderItems) ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Item</th>
                                        <th class="text-center">Price</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-center pe-3">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <?php
                                                $itemImage = !empty($item['food_image']) ? url('uploads/food/' . $item['food_image']) : url('assets/images/placeholder-food.jpg');
                                                ?>
                                                <img src="<?= $itemImage ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="order-item-img">
                                                <div>
                                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($item['name']) ?></h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?= currency($item['price']) ?></td>
                                        <td class="text-center">x<?= $item['quantity'] ?></td>
                                        <td class="text-center fw-bold pe-3"><?= currency($item['total']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Delivery Address & Notes -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-2"><i class="fas fa-map-marker-alt text-warning me-2"></i>Delivery Address</h6>
                                <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
                            </div>
                            <?php if (!empty($order['order_notes'])): ?>
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-2"><i class="fas fa-sticky-note text-warning me-2"></i>Order Notes</h6>
                                <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($order['order_notes'])) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if ($driver): ?>
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-2"><i class="fas fa-motorcycle text-warning me-2"></i>Assigned Driver</h6>
                                <p class="mb-0">
                                    <strong><?= htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']) ?></strong>
                                    <?php if (!empty($driver['phone'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($driver['phone']) ?></small>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4" style="position:sticky; top:90px;">
                    <div class="card-header bg-warning text-dark py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-clipboard-list me-2"></i>Order Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal</span>
                            <span class="fw-bold"><?= currency($order['subtotal']) ?></span>
                        </div>

                        <?php if ($order['discount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span><i class="fas fa-tag me-1"></i>Discount<?= $order['coupon_code'] ? " ({$order['coupon_code']})" : '' ?></span>
                            <span class="fw-bold">-<?= currency($order['discount']) ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Delivery Fee</span>
                            <span class="fw-bold"><?= currency($order['delivery_fee']) ?></span>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Tax</span>
                            <span class="fw-bold"><?= currency($order['tax']) ?></span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-3">
                            <span class="fs-5 fw-bold">Total</span>
                            <span class="fs-5 fw-bold text-primary"><?= currency($order['total']) ?></span>
                        </div>

                        <hr>

                        <!-- Status Badges -->
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Order Status</span>
                            <span class="badge bg-<?= ORDER_STATUS_COLORS[$order['order_status']] ?? 'secondary' ?> text-capitalize">
                                <?= ORDER_STATUSES[$order['order_status']] ?? $order['order_status'] ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Payment Method</span>
                            <span class="fw-bold"><?= PAYMENT_METHODS[$order['payment_method']] ?? $order['payment_method'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Payment Status</span>
                            <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                <?= ucfirst($order['payment_status']) ?>
                            </span>
                        </div>

                        <?php if ($order['estimated_delivery']): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Est. Delivery</span>
                            <span class="fw-bold small"><?= date('M d, g:i A', strtotime($order['estimated_delivery'])) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($order['actual_delivery']): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Delivered At</span>
                            <span class="fw-bold small text-success"><?= date('M d, g:i A', strtotime($order['actual_delivery'])) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($order['order_status'] === 'delivered'): ?>
                        <hr>
                        <form method="POST" action="<?= url('customer/modules/orders/index.php') ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reorder">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" class="btn btn-warning w-100 rounded-pill">
                                <i class="fas fa-redo me-2"></i>Reorder
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
