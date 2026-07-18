<?php
$pageTitle = 'Order Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';

if (isPost() && !verifyCSRF()) {
    setFlash('error', 'Invalid security token. Please try again.');
    redirect(url('admin/modules/orders/index.php'));
}

if (isPost()) {
    $actionPost = $_POST['action'] ?? '';

    // ── UPDATE ORDER STATUS ──
    if ($actionPost === 'update_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['order_status'] ?? '');

        if ($orderId < 1 || !array_key_exists($newStatus, ORDER_STATUSES)) {
            setFlash('error', 'Invalid order or status.');
            redirect(url('admin/modules/orders/index.php'));
        }

        $order = Database::fetch("SELECT id, order_number, customer_id, order_status FROM orders WHERE id = ?", [$orderId]);
        if (!$order) {
            setFlash('error', 'Order not found.');
            redirect(url('admin/modules/orders/index.php'));
        }

        $updateData = ['order_status' => $newStatus];
        if ($newStatus === 'delivered') {
            $updateData['actual_delivery'] = date('Y-m-d H:i:s');
        }

        Database::update('orders', $updateData, 'id = ?', [$orderId]);

        if ($order['customer_id']) {
            addNotification(
                $order['customer_id'],
                'Order Status Updated',
                "Your order {$order['order_number']} status has been updated to " . ORDER_STATUSES[$newStatus] . ".",
                'info',
                url('customer/modules/orders/index.php?action=view&id=' . $orderId)
            );
        }

        logActivity('order_status_updated', "Order {$order['order_number']} status changed to {$newStatus}");
        setFlash('success', 'Order status updated successfully.');
        redirect(url('admin/modules/orders/index.php?action=view&id=' . $orderId));
    }

    // ── ASSIGN DRIVER ──
    if ($actionPost === 'assign_driver') {
        $orderId  = (int)($_POST['order_id'] ?? 0);
        $driverId = (int)($_POST['driver_id'] ?? 0);

        if ($orderId < 1) {
            setFlash('error', 'Invalid order.');
            redirect(url('admin/modules/orders/index.php'));
        }

        $order = Database::fetch("SELECT id, order_number FROM orders WHERE id = ?", [$orderId]);
        if (!$order) {
            setFlash('error', 'Order not found.');
            redirect(url('admin/modules/orders/index.php'));
        }

        Database::update('orders', ['driver_id' => $driverId > 0 ? $driverId : null], 'id = ?', [$orderId]);

        if ($driverId > 0) {
            $driver = Database::fetch("SELECT first_name, last_name FROM users WHERE id = ?", [$driverId]);
            logActivity('driver_assigned', "Driver {$driver['first_name']} {$driver['last_name']} assigned to order {$order['order_number']}");
        } else {
            logActivity('driver_unassigned', "Driver removed from order {$order['order_number']}");
        }

        setFlash('success', 'Driver assignment updated.');
        redirect(url('admin/modules/orders/index.php?action=view&id=' . $orderId));
    }

    // ── UPDATE PAYMENT STATUS ──
    if ($actionPost === 'update_payment_status') {
        $orderId       = (int)($_POST['order_id'] ?? 0);
        $paymentStatus = trim($_POST['payment_status'] ?? '');

        $validPaymentStatuses = array_keys(PAYMENT_STATUSES);
        if ($orderId < 1 || !in_array($paymentStatus, $validPaymentStatuses)) {
            setFlash('error', 'Invalid order or payment status.');
            redirect(url('admin/modules/orders/index.php'));
        }

        $order = Database::fetch("SELECT id, order_number FROM orders WHERE id = ?", [$orderId]);
        if (!$order) {
            setFlash('error', 'Order not found.');
            redirect(url('admin/modules/orders/index.php'));
        }

        Database::update('orders', ['payment_status' => PAYMENT_ORDER_STATUSES[$paymentStatus] ?? $paymentStatus], 'id = ?', [$orderId]);

        Database::update('payments', ['status' => $paymentStatus], 'order_id = ?', [$orderId]);

        logActivity('payment_status_updated', "Order {$order['order_number']} payment status changed to {$paymentStatus}");
        setFlash('success', 'Payment status updated successfully.');
        redirect(url('admin/modules/orders/index.php?action=view&id=' . $orderId));
    }

    // ── CANCEL ORDER ──
    if ($actionPost === 'cancel_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);

        $order = Database::fetch("SELECT id, order_number, customer_id, order_status FROM orders WHERE id = ?", [$orderId]);
        if (!$order) {
            setFlash('error', 'Order not found.');
            redirect(url('admin/modules/orders/index.php'));
        }

        if ($order['order_status'] === 'delivered') {
            setFlash('error', 'Cannot cancel a delivered order.');
            redirect(url('admin/modules/orders/index.php?action=view&id=' . $orderId));
        }

        Database::update('orders', ['order_status' => 'cancelled'], 'id = ?', [$orderId]);

        if ($order['customer_id']) {
            addNotification(
                $order['customer_id'],
                'Order Cancelled',
                "Your order {$order['order_number']} has been cancelled.",
                'warning',
                url('customer/modules/orders/index.php?action=view&id=' . $orderId)
            );
        }

        logActivity('order_cancelled', "Order {$order['order_number']} cancelled");
        setFlash('success', 'Order has been cancelled.');
        redirect(url('admin/modules/orders/index.php'));
    }
}

// ─── VIEW ORDER DETAIL ──────────────────────────────────────
if ($action === 'view'):
    $orderId = (int)($_GET['id'] ?? 0);
    $order = Database::fetch(
        "SELECT o.*,
                cu.first_name AS customer_first_name, cu.last_name AS customer_last_name,
                cu.email AS customer_email, cu.phone AS customer_phone,
                du.first_name AS driver_first_name, du.last_name AS driver_last_name,
                du.phone AS driver_phone,
                c.code AS coupon_code
         FROM orders o
         LEFT JOIN users cu ON o.customer_id = cu.id
         LEFT JOIN users du ON o.driver_id = du.id
         LEFT JOIN coupons c ON o.coupon_id = c.id
         WHERE o.id = ?",
        [$orderId]
    );

    if (!$order) {
        setFlash('error', 'Order not found.');
        redirect(url('admin/modules/orders/index.php'));
    }

    $orderItems = Database::fetchAll(
        "SELECT oi.*, fi.image AS food_image
         FROM order_items oi
         LEFT JOIN food_items fi ON oi.food_id = fi.id
         WHERE oi.order_id = ?",
        [$orderId]
    );

    $drivers = Database::fetchAll(
        "SELECT u.id, u.first_name, u.last_name
         FROM users u
         LEFT JOIN driver_profiles dp ON dp.user_id = u.id
         WHERE u.role_id = 3 AND u.is_active = 1
         ORDER BY u.first_name ASC"
    );

    $statusOrder = array_keys(ORDER_STATUSES);
    $currentStatusIndex = array_search($order['order_status'], $statusOrder);
    if ($currentStatusIndex === false) $currentStatusIndex = 0;
    $progress = ($currentStatusIndex / max(count($statusOrder) - 2, 1)) * 100;
    if ($order['order_status'] === 'cancelled') $progress = 0;
    if ($order['order_status'] === 'delivered') $progress = 100;

    $paymentStatusColors = [
        'pending'   => 'warning',
        'completed' => 'success',
        'paid'      => 'success',
        'failed'    => 'danger',
        'refunded'  => 'info',
    ];
?>

<style>
    .order-status-timeline { position: relative; padding: 0; list-style: none; }
    .order-status-timeline li { position: relative; padding: 0 0 24px 32px; }
    .order-status-timeline li:last-child { padding-bottom: 0; }
    .order-status-timeline li .timeline-dot {
        position: absolute;
        left: 0;
        top: 2px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #e9ecef;
        border: 3px solid #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
    }
    .order-status-timeline li.completed .timeline-dot {
        background: var(--primary);
        border-color: var(--primary);
    }
    .order-status-timeline li.completed .timeline-dot::after {
        content: '\f00c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        color: #fff;
        font-size: 0.6rem;
    }
    .order-status-timeline li.active .timeline-dot {
        background: #fff;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(255,107,53,0.15);
    }
    .order-status-timeline li.active .timeline-dot::after {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary);
    }
    .order-status-timeline li.cancelled .timeline-dot {
        background: #dc3545;
        border-color: #dc3545;
    }
    .order-status-timeline li.cancelled .timeline-dot::after {
        content: '\f00d';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        color: #fff;
        font-size: 0.6rem;
    }
    .order-status-timeline li:not(:last-child)::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 26px;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }
    .order-status-timeline li.completed:not(:last-child)::before {
        background: var(--primary);
    }
    .order-status-timeline li .timeline-label { font-size: 0.85rem; font-weight: 500; color: #888; }
    .order-status-timeline li.completed .timeline-label,
    .order-status-timeline li.active .timeline-label { color: #333; }
    .order-status-timeline li .timeline-time { font-size: 0.72rem; color: #aaa; }
    .print-receipt { display: none; }
    @media print {
        body * { visibility: hidden !important; }
        .print-receipt, .print-receipt * { visibility: visible !important; }
        .print-receipt {
            display: block !important;
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 20px;
            background: #fff;
        }
    }
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Order #<?= sanitize($order['order_number']) ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('admin/modules/orders/index.php') ?>" class="text-decoration-none">Orders</a></li>
                <li class="breadcrumb-item active">View Order</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="fas fa-print me-1"></i> Print
        </button>
        <a href="<?= url('admin/modules/orders/index.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<!-- Print Receipt (hidden on screen) -->
<div class="print-receipt" id="printReceipt">
    <div style="text-align:center;margin-bottom:20px;">
        <h3 style="margin:0;"><?= APP_NAME ?></h3>
        <p style="margin:0;color:#666;">Order Receipt</p>
    </div>
    <table style="width:100%;margin-bottom:10px;font-size:13px;">
        <tr><td><strong>Order #:</strong></td><td><?= sanitize($order['order_number']) ?></td></tr>
        <tr><td><strong>Date:</strong></td><td><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></td></tr>
        <tr><td><strong>Status:</strong></td><td><?= ORDER_STATUSES[$order['order_status']] ?? $order['order_status'] ?></td></tr>
        <tr><td><strong>Customer:</strong></td><td><?= sanitize(($order['customer_first_name'] ?? '') . ' ' . ($order['customer_last_name'] ?? '')) ?></td></tr>
        <tr><td><strong>Payment:</strong></td><td><?= PAYMENT_METHODS[$order['payment_method']] ?? $order['payment_method'] ?> (<?= PAYMENT_STATUSES[$order['payment_status']] ?? $order['payment_status'] ?>)</td></tr>
    </table>
    <hr>
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
        <thead><tr style="border-bottom:1px solid #ddd;"><th style="text-align:left;padding:4px;">Item</th><th style="text-align:center;padding:4px;">Qty</th><th style="text-align:right;padding:4px;">Price</th><th style="text-align:right;padding:4px;">Total</th></tr></thead>
        <tbody>
        <?php foreach ($orderItems as $item): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:4px;"><?= sanitize($item['name']) ?></td>
                <td style="text-align:center;padding:4px;"><?= $item['quantity'] ?></td>
                <td style="text-align:right;padding:4px;"><?= currency((float)$item['price']) ?></td>
                <td style="text-align:right;padding:4px;"><?= currency((float)$item['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <hr>
    <table style="width:100%;font-size:13px;">
        <tr><td>Subtotal:</td><td style="text-align:right;"><?= currency((float)$order['subtotal']) ?></td></tr>
        <?php if ((float)$order['discount'] > 0): ?>
            <tr><td>Discount:</td><td style="text-align:right;color:red;">-<?= currency((float)$order['discount']) ?></td></tr>
        <?php endif; ?>
        <tr><td>Delivery Fee:</td><td style="text-align:right;"><?= currency((float)$order['delivery_fee']) ?></td></tr>
        <tr><td>Tax:</td><td style="text-align:right;"><?= currency((float)$order['tax']) ?></td></tr>
        <tr><td><strong>Total:</strong></td><td style="text-align:right;"><strong><?= currency((float)$order['total']) ?></strong></td></tr>
    </table>
    <?php if (!empty($order['order_notes'])): ?>
        <hr>
        <p style="font-size:12px;"><strong>Notes:</strong> <?= sanitize($order['order_notes']) ?></p>
    <?php endif; ?>
    <div style="text-align:center;margin-top:30px;font-size:11px;color:#999;">Thank you for your order!</div>
</div>

<div class="row g-4">
    <!-- Left Column: Order Info & Items -->
    <div class="col-lg-8">
        <!-- Order Progress -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-route me-1" style="color:var(--primary)"></i> Order Progress
                </h6>
                <?php if ($order['order_status'] === 'cancelled'): ?>
                    <div class="alert alert-danger mb-0 py-2">
                        <i class="fas fa-times-circle me-1"></i> This order has been cancelled.
                    </div>
                <?php else: ?>
                    <div class="progress mb-4" style="height:8px;">
                        <div class="progress-bar" role="progressbar"
                             style="width:<?= $progress ?>%;background:var(--primary);border-radius:4px;"
                             aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                <?php endif; ?>

                <div class="row mt-3">
                    <?php
                    $statusTimeline = [
                        'pending'          => 'Order Placed',
                        'confirmed'        => 'Confirmed',
                        'preparing'        => 'Preparing',
                        'ready'            => 'Ready',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered'        => 'Delivered',
                    ];
                    $statusIcons = [
                        'pending'          => 'fa-clock',
                        'confirmed'        => 'fa-check',
                        'preparing'        => 'fa-fire',
                        'ready'            => 'fa-check-double',
                        'out_for_delivery' => 'fa-truck',
                        'delivered'        => 'fa-flag-checkered',
                    ];
                    foreach ($statusTimeline as $sKey => $sLabel):
                        $sIndex = array_search($sKey, $statusOrder);
                        $isCompleted = $currentStatusIndex !== false && $sIndex < $currentStatusIndex && $order['order_status'] !== 'cancelled';
                        $isActive = $sKey === $order['order_status'];
                    ?>
                        <div class="col-4 col-md-2 mb-2 text-center">
                            <div class="rounded-circle mx-auto mb-1 d-flex align-items-center justify-content-center
                                <?= $isActive ? 'border border-3' : '' ?>"
                                style="width:36px;height:36px;
                                background:<?= $isCompleted ? 'var(--primary)' : ($isActive ? '#fff' : '#f0f0f0') ?>;
                                border-color:<?= ($isCompleted || $isActive) ? 'var(--primary)' : '#e0e0e0' ?> !important;">
                                <i class="fas <?= $statusIcons[$sKey] ?? 'fa-circle' ?> fa-sm"
                                   style="color:<?= $isCompleted ? '#fff' : ($isActive ? 'var(--primary)' : '#ccc') ?>"></i>
                            </div>
                            <small class="d-block" style="font-size:0.65rem;color:<?= $isActive ? 'var(--primary)' : '#888' ?>;font-weight:<?= $isActive ? '600' : '400' ?>">
                                <?= $sLabel ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-list me-1" style="color:var(--primary)"></i> Order Items (<?= count($orderItems) ?>)
                </h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-center" style="width:80px">Qty</th>
                                <th class="text-end" style="width:110px">Price</th>
                                <th class="text-end" style="width:110px">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($item['food_image'])): ?>
                                                <img src="<?= url('uploads/food/' . sanitize($item['food_image'])) ?>"
                                                     alt="" class="rounded" width="40" height="40" style="object-fit:cover">
                                            <?php else: ?>
                                                <div class="rounded d-flex align-items-center justify-content-center text-white"
                                                     style="width:40px;height:40px;background:#ddd;font-size:0.7rem;">
                                                    <i class="fas fa-utensils"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span class="fw-semibold"><?= sanitize($item['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= $item['quantity'] ?></td>
                                    <td class="text-end"><?= currency((float)$item['price']) ?></td>
                                    <td class="text-end fw-semibold"><?= currency((float)$item['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <hr class="my-3">
                <div class="row justify-content-end">
                    <div class="col-5">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted">Subtotal</td><td class="text-end"><?= currency((float)$order['subtotal']) ?></td></tr>
                            <?php if ((float)$order['discount'] > 0): ?>
                                <tr><td class="text-muted">Discount <?= $order['coupon_code'] ? '(' . sanitize($order['coupon_code']) . ')' : '' ?></td><td class="text-end text-danger">-<?= currency((float)$order['discount']) ?></td></tr>
                            <?php endif; ?>
                            <tr><td class="text-muted">Delivery Fee</td><td class="text-end"><?= currency((float)$order['delivery_fee']) ?></td></tr>
                            <tr><td class="text-muted">Tax</td><td class="text-end"><?= currency((float)$order['tax']) ?></td></tr>
                            <tr class="border-top"><td class="fw-bold">Total</td><td class="text-end fw-bold" style="color:var(--primary);font-size:1.1rem;"><?= currency((float)$order['total']) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery Address -->
        <?php if (!empty($order['delivery_address'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2">
                        <i class="fas fa-map-marker-alt me-1" style="color:var(--primary)"></i> Delivery Address
                    </h6>
                    <p class="mb-0 text-muted"><?= nl2br(sanitize($order['delivery_address'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Order Notes -->
        <?php if (!empty($order['order_notes'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2">
                        <i class="fas fa-sticky-note me-1" style="color:var(--primary)"></i> Order Notes
                    </h6>
                    <p class="mb-0 text-muted"><?= nl2br(sanitize($order['order_notes'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Info Cards & Actions -->
    <div class="col-lg-4">
        <!-- Order Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-info-circle me-1" style="color:var(--primary)"></i> Order Info
                </h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted small">Order #</td>
                        <td class="text-end fw-semibold"><?= sanitize($order['order_number']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Date</td>
                        <td class="text-end"><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Time</td>
                        <td class="text-end"><?= date('h:i A', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Status</td>
                        <td class="text-end">
                            <?php $sColor = ORDER_STATUS_COLORS[$order['order_status']] ?? 'secondary'; ?>
                            <span class="badge bg-<?= $sColor ?> bg-opacity-10 text-<?= $sColor ?> px-2 py-1">
                                <?= ORDER_STATUSES[$order['order_status']] ?? $order['order_status'] ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Est. Delivery</td>
                        <td class="text-end"><?= $order['estimated_delivery'] ? date('M d, h:i A', strtotime($order['estimated_delivery'])) : '-' ?></td>
                    </tr>
                    <?php if ($order['actual_delivery']): ?>
                        <tr>
                            <td class="text-muted small">Actual Delivery</td>
                            <td class="text-end"><?= date('M d, h:i A', strtotime($order['actual_delivery'])) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-user me-1" style="color:var(--primary)"></i> Customer
                </h6>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-semibold"
                         style="width:36px;height:36px;font-size:0.8rem;background:linear-gradient(135deg,var(--primary),var(--secondary));">
                        <?= strtoupper(substr(sanitize($order['customer_first_name'] ?? 'C'), 0, 1)) ?>
                    </div>
                    <div>
                        <div class="fw-semibold small"><?= sanitize(($order['customer_first_name'] ?? '') . ' ' . ($order['customer_last_name'] ?? '')) ?></div>
                        <small class="text-muted"><?= sanitize($order['customer_email'] ?? '') ?></small>
                    </div>
                </div>
                <?php if (!empty($order['customer_phone'])): ?>
                    <div class="small text-muted mt-1">
                        <i class="fas fa-phone fa-sm me-1"></i> <?= sanitize($order['customer_phone']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Driver Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-truck me-1" style="color:var(--primary)"></i> Driver
                </h6>
                <?php if ($order['driver_id']): ?>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-semibold"
                             style="width:36px;height:36px;font-size:0.8rem;background:linear-gradient(135deg,#0dcaf0,#0aa2ed);">
                            <?= strtoupper(substr(sanitize($order['driver_first_name'] ?? 'D'), 0, 1)) ?>
                        </div>
                        <div>
                            <div class="fw-semibold small"><?= sanitize(($order['driver_first_name'] ?? '') . ' ' . ($order['driver_last_name'] ?? '')) ?></div>
                            <?php if (!empty($order['driver_phone'])): ?>
                                <small class="text-muted"><?= sanitize($order['driver_phone']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="text-muted small">No driver assigned</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-credit-card me-1" style="color:var(--primary)"></i> Payment
                </h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted small">Method</td>
                        <td class="text-end">
                            <i class="fas fa-<?= match($order['payment_method'] ?? '') {
                                'cod' => 'money-bill-wave',
                                'mobile_money' => 'mobile-alt',
                                'card' => 'credit-card',
                                default => 'wallet'
                            } ?> me-1" style="color:var(--primary)"></i>
                            <?= PAYMENT_METHODS[$order['payment_method']] ?? $order['payment_method'] ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Status</td>
                        <td class="text-end">
                            <?php $pColor = $paymentStatusColors[$order['payment_status']] ?? 'secondary'; ?>
                            <span class="badge bg-<?= $pColor ?> bg-opacity-10 text-<?= $pColor ?> px-2 py-1">
                                <?= PAYMENT_STATUSES[$order['payment_status']] ?? $order['payment_status'] ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-cog me-1" style="color:var(--primary)"></i> Actions
                </h6>

                <!-- Update Order Status -->
                <form method="POST" class="mb-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <label class="form-label fw-semibold small">Change Order Status</label>
                    <div class="input-group">
                        <select class="form-select form-select-sm" name="order_status" required>
                            <?php foreach (ORDER_STATUSES as $sKey => $sLabel): ?>
                                <option value="<?= $sKey ?>" <?= $order['order_status'] === $sKey ? 'selected' : '' ?>><?= $sLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </form>

                <hr>

                <!-- Assign Driver -->
                <form method="POST" class="mb-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="assign_driver">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <label class="form-label fw-semibold small">Assign Driver</label>
                    <div class="input-group">
                        <select class="form-select form-select-sm" name="driver_id">
                            <option value="0">-- No Driver --</option>
                            <?php foreach ($drivers as $drv): ?>
                                <option value="<?= $drv['id'] ?>" <?= ($order['driver_id'] ?? 0) == $drv['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($drv['first_name'] . ' ' . $drv['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </form>

                <hr>

                <!-- Update Payment Status -->
                <form method="POST" class="mb-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_payment_status">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <label class="form-label fw-semibold small">Change Payment Status</label>
                    <div class="input-group">
                        <select class="form-select form-select-sm" name="payment_status" required>
                            <?php foreach (PAYMENT_STATUSES as $psKey => $psLabel): ?>
                                <option value="<?= $psKey ?>" <?= $order['payment_status'] === $psKey ? 'selected' : '' ?>><?= $psLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </form>

                <hr>

                <!-- Cancel Order -->
                <?php if ($order['order_status'] !== 'cancelled' && $order['order_status'] !== 'delivered'): ?>
                    <form method="POST" id="cancelOrderForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="cancel_order">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button type="button" class="btn btn-outline-danger w-100 btn-sm"
                                onclick="cancelOrder(<?= $order['id'] ?>, '<?= sanitize(addslashes($order['order_number'])) ?>')">
                            <i class="fas fa-times-circle me-1"></i> Cancel Order
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function cancelOrder(id, orderNum) {
    Swal.fire({
        title: 'Cancel Order "' + orderNum + '"?',
        text: 'This will mark the order as cancelled.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, cancel it!',
        cancelButtonText: 'No'
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById('cancelOrderForm').submit();
        }
    });
}
</script>

<?php else: // ─── LIST VIEW ──────────────────────────────────────

$search       = trim($_GET['search'] ?? '');
$filterStatus = $_GET['order_status'] ?? '';
$filterPay    = $_GET['payment_status'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo   = $_GET['date_to'] ?? '';

$where  = '1';
$params = [];

if ($search !== '') {
    $where .= " AND (o.order_number LIKE ? OR CONCAT(cu.first_name, ' ', cu.last_name) LIKE ? OR cu.first_name LIKE ? OR cu.last_name LIKE ? OR cu.email LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($filterStatus !== '' && array_key_exists($filterStatus, ORDER_STATUSES)) {
    $where .= " AND o.order_status = ?";
    $params[] = $filterStatus;
}

if ($filterPay !== '' && array_key_exists($filterPay, PAYMENT_STATUSES)) {
    $where .= " AND o.payment_status = ?";
    $params[] = $filterPay;
}

if ($filterDateFrom !== '') {
    $where .= " AND o.created_at >= ?";
    $params[] = $filterDateFrom . ' 00:00:00';
}

if ($filterDateTo !== '') {
    $where .= " AND o.created_at <= ?";
    $params[] = $filterDateTo . ' 23:59:59';
}

$pagination = paginate(
    'orders o LEFT JOIN users cu ON o.customer_id = cu.id',
    $where,
    $params,
    ORDERS_PER_PAGE
);

$orders = Database::fetchAll(
    "SELECT o.*,
            cu.first_name AS customer_first_name, cu.last_name AS customer_last_name,
            du.first_name AS driver_first_name, du.last_name AS driver_last_name,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
     FROM orders o
     LEFT JOIN users cu ON o.customer_id = cu.id
     LEFT JOIN users du ON o.driver_id = du.id
     WHERE {$where}
     ORDER BY o.id DESC
     LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
    $params
);

$queryParams = array_filter([
    'search'         => $search ?: null,
    'order_status'   => $filterStatus ?: null,
    'payment_status' => $filterPay ?: null,
    'date_from'      => $filterDateFrom ?: null,
    'date_to'        => $filterDateTo ?: null,
]);
$baseUrl = url('admin/modules/orders/index.php') . '?' . http_build_query($queryParams);

$totalOrders     = Database::count('orders');
$pendingOrders   = Database::count('orders', "order_status = 'pending'");
$confirmedOrders = Database::count('orders', "order_status = 'confirmed'");
$preparingOrders = Database::count('orders', "order_status = 'preparing'");
$readyOrders     = Database::count('orders', "order_status = 'ready'");
$deliveringOrders = Database::count('orders', "order_status = 'out_for_delivery'");
$deliveredOrders = Database::count('orders', "order_status = 'delivered'");
$cancelledOrders = Database::count('orders', "order_status = 'cancelled'");

$paymentStatusColors = [
    'pending'   => 'warning',
    'completed' => 'success',
    'paid'      => 'success',
    'failed'    => 'danger',
    'refunded'  => 'info',
];
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Order Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Orders</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(255,107,53,0.1)">
                        <i class="fas fa-receipt" style="color:var(--primary);font-size:1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Orders</div>
                        <div class="fw-bold fs-5"><?= $totalOrders ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(255,193,7,0.1)">
                        <i class="fas fa-clock" style="color:#ffc107;font-size:1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending</div>
                        <div class="fw-bold fs-5"><?= $pendingOrders ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(13,202,240,0.1)">
                        <i class="fas fa-fire" style="color:#0dcaf0;font-size:1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Preparing</div>
                        <div class="fw-bold fs-5"><?= $preparingOrders + $confirmedOrders + $readyOrders ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(40,167,69,0.1)">
                        <i class="fas fa-check-circle" style="color:#28a745;font-size:1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Delivered</div>
                        <div class="fw-bold fs-5"><?= $deliveredOrders ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Extra Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(23,162,184,0.1)">
                        <i class="fas fa-check-double" style="color:#17a2b8;font-size:1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Ready</div>
                        <div class="fw-bold fs-5"><?= $readyOrders ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(108,117,125,0.1)">
                        <i class="fas fa-truck" style="color:#6c757d;font-size:1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Out for Delivery</div>
                        <div class="fw-bold fs-5"><?= $deliveringOrders ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(220,53,69,0.1)">
                        <i class="fas fa-times-circle" style="color:#dc3545;font-size:1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Cancelled</div>
                        <div class="fw-bold fs-5"><?= $cancelledOrders ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(255,107,53,0.06)">
                        <i class="fas fa-coins" style="color:var(--primary);font-size:1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Confirmed</div>
                        <div class="fw-bold fs-5"><?= $confirmedOrders ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="search" class="form-label fw-semibold small">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Order # or customer name..." value="<?= sanitize($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label for="filterStatus" class="form-label fw-semibold small">Order Status</label>
                <select class="form-select" id="filterStatus" name="order_status">
                    <option value="">All Statuses</option>
                    <?php foreach (ORDER_STATUSES as $sKey => $sLabel): ?>
                        <option value="<?= $sKey ?>" <?= $filterStatus === $sKey ? 'selected' : '' ?>><?= $sLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterPay" class="form-label fw-semibold small">Payment Status</label>
                <select class="form-select" id="filterPay" name="payment_status">
                    <option value="">All</option>
                    <?php foreach (PAYMENT_STATUSES as $psKey => $psLabel): ?>
                        <option value="<?= $psKey ?>" <?= $filterPay === $psKey ? 'selected' : '' ?>><?= $psLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="dateFrom" class="form-label fw-semibold small">From Date</label>
                <input type="date" class="form-control" id="dateFrom" name="date_from" value="<?= sanitize($filterDateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label for="dateTo" class="form-label fw-semibold small">To Date</label>
                <input type="date" class="form-control" id="dateTo" name="date_to" value="<?= sanitize($filterDateTo) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="fas fa-filter"></i>
                </button>
                <a href="<?= url('admin/modules/orders/index.php') ?>" class="btn btn-outline-secondary" title="Clear">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:50px">#</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Total</th>
                        <th>Payment Status</th>
                        <th>Order Status</th>
                        <th>Date</th>
                        <th class="text-end pe-3" style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fas fa-receipt fa-2x mb-3 d-block opacity-50"></i>
                                No orders found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $i => $o): ?>
                            <?php
                            $sColor = ORDER_STATUS_COLORS[$o['order_status']] ?? 'secondary';
                            $pColor = $paymentStatusColors[$o['payment_status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $pagination['offset'] + $i + 1 ?></td>
                                <td>
                                    <a href="<?= url('admin/modules/orders/index.php?action=view&id=' . $o['id']) ?>"
                                       class="fw-semibold text-decoration-none" style="color:var(--primary);">
                                        <?= sanitize($o['order_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-semibold"
                                             style="width:30px;height:30px;font-size:0.65rem;background:linear-gradient(135deg,var(--primary),var(--secondary));">
                                            <?= strtoupper(substr(sanitize($o['customer_first_name'] ?? 'C'), 0, 1)) ?>
                                        </div>
                                        <span class="small"><?= sanitize(($o['customer_first_name'] ?? '') . ' ' . ($o['customer_last_name'] ?? '')) ?></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark px-2"><?= $o['item_count'] ?></span>
                                </td>
                                <td class="text-end text-muted small"><?= currency((float)$o['subtotal']) ?></td>
                                <td class="text-end fw-semibold"><?= currency((float)$o['total']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $pColor ?> bg-opacity-10 text-<?= $pColor ?> px-2 py-1">
                                        <?= PAYMENT_STATUSES[$o['payment_status']] ?? $o['payment_status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $sColor ?> bg-opacity-10 text-<?= $sColor ?> px-2 py-1">
                                        <?= ORDER_STATUSES[$o['order_status']] ?? $o['order_status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small"><?= date('M d, Y', strtotime($o['created_at'])) ?></div>
                                    <small class="text-muted"><?= date('h:i A', strtotime($o['created_at'])) ?></small>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="<?= url('admin/modules/orders/index.php?action=view&id=' . $o['id']) ?>"
                                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Print"
                                                onclick="printOrder(<?= $o['id'] ?>)">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pagination['total'] > $pagination['perPage']): ?>
        <div class="card-footer bg-white border-top-0 pt-0 pb-3">
            <div class="d-flex justify-content-between align-items-center px-3">
                <small class="text-muted">
                    Showing <?= $pagination['offset'] + 1 ?>-<?= min($pagination['offset'] + $pagination['perPage'], $pagination['total']) ?>
                    of <?= $pagination['total'] ?> orders
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        for ($p = 1; $p <= $pagination['pages']; $p++):
                            $active = $p == $pagination['page'] ? ' active' : '';
                            $pageUrl = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . "page={$p}";
                        ?>
                            <li class="page-item<?= $active ?>">
                                <a class="page-link" href="<?= $pageUrl ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function printOrder(id) {
    window.open('<?= url('admin/modules/orders/index.php?action=view&id=') ?>' + id + '&print=1', '_blank');
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
