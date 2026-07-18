<?php
$pageTitle = 'Checkout';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../includes/functions.php';

$userId = $_SESSION['user_id'] ?? 0;

if (!isLoggedIn() || !isCustomer()) {
    setFlash('warning', 'Please login to proceed with checkout.');
    redirect(url('login.php'));
}

// ─── Place Order ────────────────────────────────────────────
if (isPost() && ($_POST['action'] ?? '') === 'place_order') {
    requireCSRF();

    // Validate terms
    if (!isset($_POST['terms'])) {
        setFlash('error', 'You must agree to the Terms and Conditions.');
        redirect(url('customer/modules/checkout/'));
    }

    // Get cart items
    $cartItems = Database::fetchAll("
        SELECT c.*, fi.name, fi.price, fi.discount_price, fi.is_available
        FROM cart c
        JOIN food_items fi ON c.food_id = fi.id
        WHERE c.user_id = ?
    ", [$userId]);

    if (empty($cartItems)) {
        setFlash('error', 'Your cart is empty.');
        redirect(url('customer/modules/cart/'));
    }

    // Validate all items are active
    foreach ($cartItems as $item) {
        if (!$item['is_available']) {
            setFlash('error', '"' . $item['name'] . '" is no longer available. Please remove it from your cart.');
            redirect(url('customer/modules/cart/'));
        }
    }

    // Get form data
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $state = sanitize($_POST['state'] ?? '');
    $zip = sanitize($_POST['zip'] ?? '');
    $paymentMethod = sanitize($_POST['payment_method'] ?? '');
    $orderNotes = sanitize($_POST['order_notes'] ?? '');

    // Validate required fields
    if (empty($address) || empty($city) || empty($state) || empty($zip)) {
        setFlash('error', 'Please fill in all delivery address fields.');
        redirect(url('customer/modules/checkout/'));
    }

    $validPaymentMethods = ['cod', 'mobile_money', 'card'];
    if (!in_array($paymentMethod, $validPaymentMethods)) {
        setFlash('error', 'Please select a valid payment method.');
        redirect(url('customer/modules/checkout/'));
    }

    // Calculate totals
    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $unitPrice = (!empty($item['discount_price']) && $item['discount_price'] < $item['price'])
            ? $item['discount_price']
            : $item['price'];
        $subtotal += $unitPrice * $item['quantity'];
    }

    $discount = 0.0;
    $couponId = null;
    $appliedCoupon = $_SESSION['applied_coupon'] ?? null;
    if ($appliedCoupon) {
        // Re-validate coupon
        $coupon = Database::fetch("SELECT * FROM coupons WHERE id = ? AND is_active = 1", [$appliedCoupon['id']]);
        if ($coupon) {
            if (empty($coupon['expires_at']) || strtotime($coupon['expires_at']) >= time()) {
                if (empty($coupon['max_uses']) || $coupon['used_count'] < $coupon['max_uses']) {
                    if (empty($coupon['min_order']) || $subtotal >= $coupon['min_order']) {
                        $couponId = $coupon['id'];
                        if ($coupon['type'] === 'percentage') {
                            $discount = $subtotal * ($coupon['value'] / 100);
                        } else {
                            $discount = min($coupon['value'], $subtotal);
                        }
                    }
                }
            }
        }
    }

    $deliveryFee = (float)getSetting('delivery_fee', '5.00');
    $taxRate = (float)getSetting('tax_rate', '8');
    $taxableAmount = $subtotal - $discount;
    $tax = $taxableAmount * ($taxRate / 100);
    $total = max(0, $taxableAmount + $deliveryFee + $tax);

    // Generate order number
    $orderNumber = generateOrderNumber();

    // Create order
    $orderId = Database::insert('orders', [
        'order_number'     => $orderNumber,
        'customer_id'      => $userId,
        'subtotal'         => $subtotal,
        'discount'         => $discount,
        'delivery_fee'     => $deliveryFee,
        'tax'              => $tax,
        'total'            => $total,
        'order_status'     => 'pending',
        'payment_method'   => $paymentMethod,
        'payment_status'   => 'pending',
        'delivery_address' => $address . ', ' . $city . ', ' . $state . ' ' . $zip,
        'order_notes'      => $orderNotes,
        'coupon_id'        => $couponId,
    ]);

    // Create order items
    foreach ($cartItems as $item) {
        $unitPrice = (!empty($item['discount_price']) && $item['discount_price'] < $item['price'])
            ? $item['discount_price']
            : $item['price'];
        Database::insert('order_items', [
            'order_id' => $orderId,
            'food_id'  => $item['food_id'],
            'quantity' => $item['quantity'],
            'price'    => $unitPrice,
            'total'    => $unitPrice * $item['quantity'],
            'name'     => $item['name'],
        ]);
    }

    // Create payment record
    Database::insert('payments', [
        'order_id'       => $orderId,
        'amount'         => $total,
        'method'         => $paymentMethod,
        'status'         => 'pending',
        'transaction_id' => null,
    ]);

    // Increment coupon usage
    if ($couponId) {
        Database::query("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?", [$couponId]);
    }

    // Clear cart
    Database::delete('cart', 'user_id = ?', [$userId]);

    // Clear coupon from session
    unset($_SESSION['applied_coupon']);

    // Add notification
    addNotification(
        $userId,
        'Order Placed Successfully!',
        'Your order ' . $orderNumber . ' has been placed. Total: ' . currency($total),
        'success',
        url('customer/modules/orders/view.php?id=' . $orderId)
    );

    // Log activity
    logActivity('order_placed', 'Order ' . $orderNumber . ' placed. Total: ' . currency($total));

    // Redirect to confirmation
    setFlash('success', 'Order ' . $orderNumber . ' placed successfully!');
    redirect(url('customer/modules/orders/view.php?id=' . $orderId));
}

// ─── Fetch Cart Items for Display ───────────────────────────
$cartItems = Database::fetchAll("
    SELECT c.*, fi.name, fi.price, fi.discount_price, fi.image, fi.is_available
    FROM cart c
    JOIN food_items fi ON c.food_id = fi.id
    WHERE c.user_id = ?
    ORDER BY c.created_at ASC
", [$userId]);

// Redirect if cart is empty
if (empty($cartItems)) {
    setFlash('warning', 'Your cart is empty. Add some items before checkout.');
    redirect(url('customer/modules/cart/'));
}

// ─── Calculate Totals for Display ───────────────────────────
$subtotal = 0.0;
foreach ($cartItems as $item) {
    $unitPrice = (!empty($item['discount_price']) && $item['discount_price'] < $item['price'])
        ? $item['discount_price']
        : $item['price'];
    $subtotal += $unitPrice * $item['quantity'];
}

$discount = 0.0;
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
if ($appliedCoupon) {
    if ($appliedCoupon['type'] === 'percentage') {
        $discount = $subtotal * ($appliedCoupon['value'] / 100);
    } else {
        $discount = min($appliedCoupon['value'], $subtotal);
    }
}

$deliveryFee = (float)getSetting('delivery_fee', '5.00');
$taxRate = (float)getSetting('tax_rate', '8');
$taxableAmount = $subtotal - $discount;
$tax = $taxableAmount * ($taxRate / 100);
$total = max(0, $taxableAmount + $deliveryFee + $tax);

// Get user info for pre-filling
$user = currentUser();
$profile = Database::fetch("SELECT * FROM customer_profiles WHERE user_id = ?", [$userId]);
if ($profile) {
    $user['address']  = $profile['address'] ?? $user['address'] ?? '';
    $user['city']     = $profile['city'] ?? $user['city'] ?? '';
    $user['state']    = $profile['state'] ?? $user['state'] ?? '';
    $user['zip_code'] = $profile['zip_code'] ?? $user['zip_code'] ?? '';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .checkout-step {
        border-radius: 12px;
        overflow: hidden;
    }
    .step-header {
        background: linear-gradient(135deg, #ff6b35, #ff8f5e);
        color: #fff;
    }
    .payment-option {
        border: 2px solid #dee2e6;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .payment-option:hover {
        border-color: var(--primary);
        background-color: #fff8f5;
    }
    .payment-option input[type="radio"]:checked + .payment-label {
        color: var(--primary);
        font-weight: 600;
    }
    .payment-option:has(input[type="radio"]:checked) {
        border-color: var(--primary);
        background-color: #fff8f5;
    }
    .order-item-img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 8px;
    }
    .summary-card {
        border-radius: 12px;
        overflow: hidden;
        position: sticky;
        top: 90px;
    }
</style>

<!-- Breadcrumb -->
<section class="bg-light py-3 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('customer/index.php') ?>" class="text-decoration-none">Menu</a></li>
                <li class="breadcrumb-item"><a href="<?= url('customer/modules/cart/') ?>" class="text-decoration-none">Cart</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h3 class="fw-bold mb-4">
            <i class="fas fa-credit-card text-warning me-2"></i>Checkout
        </h3>

        <form method="POST" id="checkoutForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="place_order">

            <div class="row">
                <!-- Left Column: Forms -->
                <div class="col-lg-8 mb-4">

                    <!-- Delivery Address -->
                    <div class="card border-0 shadow-sm mb-4 checkout-step">
                        <div class="card-header step-header py-3">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-map-marker-alt me-2"></i>Delivery Address
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <label for="address" class="form-label fw-bold">Street Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="address" name="address" 
                                       placeholder="Enter your full address" required
                                       value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <label for="city" class="form-label fw-bold">City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           placeholder="City" required
                                           value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="state" class="form-label fw-bold">State <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                           placeholder="State" required
                                           value="<?= htmlspecialchars($user['state'] ?? '') ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="zip" class="form-label fw-bold">ZIP Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="zip" name="zip" 
                                           placeholder="ZIP" required
                                           value="<?= htmlspecialchars($user['zip_code'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="card border-0 shadow-sm mb-4 checkout-step">
                        <div class="card-header step-header py-3">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-wallet me-2"></i>Payment Method
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex flex-column gap-3">
                                <label class="payment-option d-flex align-items-center gap-3">
                                    <input type="radio" name="payment_method" value="cod" class="form-check-input" checked>
                                    <div class="payment-label">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-money-bill-wave text-success fs-5"></i>
                                            <div>
                                                <div class="fw-bold">Cash on Delivery</div>
                                                <small class="text-muted">Pay when your order arrives</small>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                                <label class="payment-option d-flex align-items-center gap-3">
                                    <input type="radio" name="payment_method" value="mobile_money" class="form-check-input">
                                    <div class="payment-label">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-mobile-alt text-primary fs-5"></i>
                                            <div>
                                                <div class="fw-bold">Mobile Money</div>
                                                <small class="text-muted">Pay via MTN, Airtel, or other mobile wallets</small>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                                <label class="payment-option d-flex align-items-center gap-3">
                                    <input type="radio" name="payment_method" value="card" class="form-check-input">
                                    <div class="payment-label">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-credit-card text-warning fs-5"></i>
                                            <div>
                                                <div class="fw-bold">Card Payment</div>
                                                <small class="text-muted">Pay with credit/debit card</small>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="card border-0 shadow-sm mb-4 checkout-step">
                        <div class="card-header step-header py-3">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-sticky-note me-2"></i>Order Notes
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <textarea class="form-control" name="order_notes" rows="3" 
                                      placeholder="Special instructions, allergies, delivery preferences..."></textarea>
                            <small class="text-muted">Optional</small>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="card border-0 shadow-sm mb-4 checkout-step">
                        <div class="card-body p-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label fw-bold" for="terms">
                                    I agree to the <a href="#" class="text-primary">Terms and Conditions</a> 
                                    and <a href="#" class="text-primary">Privacy Policy</a> <span class="text-danger">*</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Place Order Button -->
                    <div class="d-lg-none mb-4">
                        <button type="submit" class="btn btn-warning btn-lg w-100 rounded-pill">
                            <i class="fas fa-lock me-2"></i>Place Order - <?= currency($total) ?>
                        </button>
                    </div>
                </div>

                <!-- Right Column: Order Summary -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm summary-card">
                        <div class="card-header bg-warning text-dark py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-clipboard-list me-2"></i>Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <!-- Items List -->
                            <div class="mb-3" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($cartItems as $item):
                                    $unitPrice = (!empty($item['discount_price']) && $item['discount_price'] < $item['price'])
                                        ? $item['discount_price']
                                        : $item['price'];
                                    $itemImage = !empty($item['image']) ? url('uploads/food/' . $item['image']) : url('assets/images/placeholder-food.jpg');
                                ?>
                                <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                                    <img src="<?= $itemImage ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="order-item-img">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold small"><?= htmlspecialchars($item['name']) ?></div>
                                        <small class="text-muted">x<?= $item['quantity'] ?> &times; <?= currency($unitPrice) ?></small>
                                    </div>
                                    <span class="fw-bold small"><?= currency($unitPrice * $item['quantity']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <hr>

                            <!-- Price Breakdown -->
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span class="fw-bold"><?= currency($subtotal) ?></span>
                            </div>

                            <?php if ($discount > 0): ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span><i class="fas fa-tag me-1"></i>Discount (<?= htmlspecialchars($appliedCoupon['code']) ?>)</span>
                                <span class="fw-bold">-<?= currency($discount) ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Delivery Fee</span>
                                <span class="fw-bold"><?= currency($deliveryFee) ?></span>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Tax (<?= number_format($taxRate, 1) ?>%)</span>
                                <span class="fw-bold"><?= currency($tax) ?></span>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between mb-4">
                                <span class="fs-5 fw-bold">Total</span>
                                <span class="fs-5 fw-bold text-primary"><?= currency($total) ?></span>
                            </div>

                            <!-- Desktop Place Order Button -->
                            <div class="d-none d-lg-block">
                                <button type="submit" class="btn btn-warning btn-lg w-100 rounded-pill">
                                    <i class="fas fa-lock me-2"></i>Place Order
                                </button>
                            </div>

                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>Secure &amp; encrypted checkout
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
