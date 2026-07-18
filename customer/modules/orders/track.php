<?php
$pageTitle = 'Track Delivery';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../includes/functions.php';

$userId = $_SESSION['user_id'] ?? 0;

if (!isLoggedIn() || !isCustomer()) {
    setFlash('warning', 'Please login to track your order.');
    redirect(url('login.php'));
}

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId < 1) {
    setFlash('error', 'Invalid order ID.');
    redirect(url('customer/modules/orders/'));
}

$order = Database::fetch(
    "SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) AS driver_name, u.phone AS driver_phone
     FROM orders o
     LEFT JOIN users u ON o.driver_id = u.id
     WHERE o.id = ? AND o.customer_id = ?",
    [$orderId, $userId]
);

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect(url('customer/modules/orders/'));
}

if (!in_array($order['order_status'], ['out_for_delivery', 'delivered'])) {
    setFlash('warning', 'This order is not currently out for delivery.');
    redirect(url('customer/modules/orders/view.php?id=' . $orderId));
}

$orderItems = Database::fetchAll(
    "SELECT oi.*, fi.image AS food_image
     FROM order_items oi
     LEFT JOIN food_items fi ON oi.food_id = fi.id
     WHERE oi.order_id = ?",
    [$orderId]
);

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .tracking-card {
        border-radius: 16px;
        overflow: hidden;
    }
    .tracking-step {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem 0;
        position: relative;
    }
    .tracking-step:not(:last-child) {
        border-left: 2px solid #e9ecef;
        margin-left: 15px;
        padding-left: 20px;
    }
    .tracking-step.completed {
        border-left-color: var(--primary);
    }
    .tracking-step .step-dot {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: #e9ecef;
        color: #aaa;
        font-size: 0.8rem;
    }
    .tracking-step.completed .step-dot {
        background: var(--primary);
        color: #fff;
    }
    .tracking-step.active .step-dot {
        background: #fff;
        border: 3px solid var(--primary);
        color: var(--primary);
        box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.15);
    }
    .driver-card {
        border-radius: 16px;
        overflow: hidden;
    }
    .pulse-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #28a745;
        display: inline-block;
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.3); }
    }
    .order-item-img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 8px;
    }
</style>

<!-- Breadcrumb -->
<section class="bg-light py-3 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('customer/index.php') ?>" class="text-decoration-none">Menu</a></li>
                <li class="breadcrumb-item"><a href="<?= url('customer/modules/orders/') ?>" class="text-decoration-none">My Orders</a></li>
                <li class="breadcrumb-item active">Track Delivery</li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="fas fa-truck text-warning me-2"></i>Track Delivery
                </h3>
                <small class="text-muted">Order <?= htmlspecialchars($order['order_number']) ?></small>
            </div>
            <a href="<?= url('customer/modules/orders/view.php?id=' . $orderId) ?>" class="btn btn-outline-secondary rounded-pill">
                <i class="fas fa-arrow-left me-1"></i>Back to Order
            </a>
        </div>

        <div class="row g-4">
            <!-- Left: Tracking Status -->
            <div class="col-lg-8">
                <!-- Live Status Banner -->
                <?php if ($order['order_status'] === 'out_for_delivery'): ?>
                <div class="card border-0 shadow-sm mb-4 tracking-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="pulse-dot"></div>
                            <div>
                                <h5 class="fw-bold mb-0 text-success">Your order is on its way!</h5>
                                <small class="text-muted">Estimated arrival: <?= $order['estimated_delivery'] ? date('g:i A', strtotime($order['estimated_delivery'])) : 'Calculating...' ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card border-0 shadow-sm mb-4 tracking-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                                <i class="fas fa-check-circle text-success fs-4"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">Order Delivered!</h5>
                                <small class="text-muted">Delivered at <?= $order['actual_delivery'] ? date('M d, Y g:i A', strtotime($order['actual_delivery'])) : 'N/A' ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tracking Steps -->
                <div class="card border-0 shadow-sm mb-4 tracking-card">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-route text-warning me-2"></i>Delivery Progress</h6>
                    </div>
                    <div class="card-body px-4">
                        <?php
                        $steps = [
                            'out_for_delivery' => ['label' => 'Picked Up', 'icon' => 'fa-store', 'desc' => 'Your order has been picked up from the restaurant'],
                            'in_transit'        => ['label' => 'In Transit', 'icon' => 'fa-motorcycle', 'desc' => 'Your order is on the way to you'],
                            'nearby'            => ['label' => 'Nearby', 'icon' => 'fa-map-marker-alt', 'desc' => 'Your delivery is close to your location'],
                            'delivered'         => ['label' => 'Delivered', 'icon' => 'fa-check-circle', 'desc' => 'Your order has been delivered successfully'],
                        ];

                        $currentStep = match($order['order_status']) {
                            'out_for_delivery' => 0,
                            'delivered'        => 3,
                            default            => 0,
                        };
                        $i = 0;
                        foreach ($steps as $step):
                            $state = $i < $currentStep ? 'completed' : ($i === $currentStep ? 'active' : '');
                        ?>
                        <div class="tracking-step <?= $state ?>">
                            <div class="step-dot">
                                <i class="fas <?= $state === 'completed' ? 'fa-check' : ($step['icon']) ?>"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 <?= $state === '' ? 'text-muted' : '' ?>"><?= $step['label'] ?></h6>
                                <small class="text-muted"><?= $step['desc'] ?></small>
                                <?php if ($state === 'completed'): ?>
                                    <br><small class="text-success"><i class="fas fa-check me-1"></i>Completed</small>
                                <?php elseif ($state === 'active'): ?>
                                    <br><small class="text-primary"><i class="fas fa-clock me-1"></i>In progress...</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php $i++; endforeach; ?>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card border-0 shadow-sm mb-4 tracking-card">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-utensils text-warning me-2"></i>Order Items (<?= count($orderItems) ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($orderItems as $item): ?>
                        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
                            <?php $img = !empty($item['food_image']) ? url('uploads/food/' . $item['food_image']) : url('assets/images/placeholder-food.jpg'); ?>
                            <img src="<?= $img ?>" alt="" class="order-item-img">
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-0 small"><?= htmlspecialchars($item['name']) ?></h6>
                                <small class="text-muted">x<?= $item['quantity'] ?> &times; <?= currency((float)$item['price']) ?></small>
                            </div>
                            <span class="fw-bold small"><?= currency((float)$item['total']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Driver Info & Summary -->
            <div class="col-lg-4">
                <!-- Driver Card -->
                <div class="card border-0 shadow-sm mb-4 driver-card">
                    <div class="card-header bg-warning text-dark py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-motorcycle me-2"></i>Your Driver</h6>
                    </div>
                    <div class="card-body text-center py-4">
                        <?php if ($order['driver_name']): ?>
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center mx-auto mb-3" style="width:80px;height:80px;">
                                <i class="fas fa-user text-primary" style="font-size:2rem;"></i>
                            </div>
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($order['driver_name']) ?></h5>
                            <?php if (!empty($order['driver_phone'])): ?>
                                <a href="tel:<?= htmlspecialchars($order['driver_phone']) ?>" class="btn btn-outline-primary rounded-pill mt-2 px-4">
                                    <i class="fas fa-phone me-2"></i><?= htmlspecialchars($order['driver_phone']) ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-muted py-3">
                                <i class="fas fa-user-clock fa-2x mb-2 d-block opacity-50"></i>
                                <p class="mb-0">Driver assignment pending...</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="card border-0 shadow-sm mb-4 driver-card">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2"><i class="fas fa-map-marker-alt text-warning me-2"></i>Delivering To</h6>
                        <p class="text-muted mb-0 small"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="card border-0 shadow-sm driver-card">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-receipt text-warning me-2"></i>Order Summary</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal</span>
                            <span class="fw-bold"><?= currency((float)$order['subtotal']) ?></span>
                        </div>
                        <?php if ((float)$order['discount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Discount</span>
                            <span class="fw-bold">-<?= currency((float)$order['discount']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Delivery Fee</span>
                            <span class="fw-bold"><?= currency((float)$order['delivery_fee']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tax</span>
                            <span class="fw-bold"><?= currency((float)$order['tax']) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fs-5 fw-bold">Total</span>
                            <span class="fs-5 fw-bold text-primary"><?= currency((float)$order['total']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
