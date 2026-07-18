<?php
$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../includes/functions.php';

$userId = $_SESSION['user_id'] ?? 0;

if (!isLoggedIn() || !isCustomer()) {
    setFlash('warning', 'Please login to view your cart.');
    redirect(url('login.php'));
}

// ─── Action Handlers ────────────────────────────────────────

// Add to Cart
if (isPost() && ($_POST['action'] ?? '') === 'add') {
    requireCSRF();
    $foodId = (int)($_POST['food_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    if ($foodId > 0) {
        $food = Database::fetch("SELECT id, name FROM food_items WHERE id = ? AND is_available = 1", [$foodId]);
        if ($food) {
            $existing = Database::fetch("SELECT id, quantity FROM cart WHERE user_id = ? AND food_id = ?", [$userId, $foodId]);
            if ($existing) {
                Database::update('cart', ['quantity' => $existing['quantity'] + $quantity], 'id = ?', [$existing['id']]);
            } else {
                Database::insert('cart', ['user_id' => $userId, 'food_id' => $foodId, 'quantity' => $quantity]);
            }
            setFlash('success', '"' . $food['name'] . '" added to cart!');
        } else {
            setFlash('error', 'Food item not found.');
        }
    }
    redirect(url('customer/modules/cart/'));
}

// Update Quantity
if (isPost() && ($_POST['action'] ?? '') === 'update') {
    requireCSRF();
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);

    $cartItem = Database::fetch("SELECT id FROM cart WHERE id = ? AND user_id = ?", [$cartId, $userId]);
    if ($cartItem) {
        if ($quantity <= 0) {
            Database::delete('cart', 'id = ?', [$cartId]);
            setFlash('info', 'Item removed from cart.');
        } else {
            Database::update('cart', ['quantity' => $quantity], 'id = ?', [$cartId]);
        }
    }
    redirect(url('customer/modules/cart/'));
}

// Remove Item
if (isPost() && ($_POST['action'] ?? '') === 'remove') {
    requireCSRF();
    $cartId = (int)($_POST['cart_id'] ?? 0);

    $cartItem = Database::fetch("SELECT id FROM cart WHERE id = ? AND user_id = ?", [$cartId, $userId]);
    if ($cartItem) {
        Database::delete('cart', 'id = ?', [$cartId]);
        setFlash('success', 'Item removed from cart.');
    }
    redirect(url('customer/modules/cart/'));
}

// Apply Coupon
if (isPost() && ($_POST['action'] ?? '') === 'coupon') {
    requireCSRF();
    $couponCode = trim($_POST['coupon_code'] ?? '');

    if (empty($couponCode)) {
        setFlash('error', 'Please enter a coupon code.');
        redirect(url('customer/modules/cart/'));
    }

    $coupon = Database::fetch("SELECT * FROM coupons WHERE code = ? AND is_active = 1", [strtoupper($couponCode)]);

    if (!$coupon) {
        setFlash('error', 'Invalid coupon code.');
        unset($_SESSION['applied_coupon']);
        redirect(url('customer/modules/cart/'));
    }

    if (!empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < time()) {
        setFlash('error', 'This coupon has expired.');
        unset($_SESSION['applied_coupon']);
        redirect(url('customer/modules/cart/'));
    }

    if (!empty($coupon['max_uses']) && $coupon['used_count'] >= $coupon['max_uses']) {
        setFlash('error', 'This coupon has reached its usage limit.');
        unset($_SESSION['applied_coupon']);
        redirect(url('customer/modules/cart/'));
    }

    $subtotal = getCartTotal();
    if (!empty($coupon['min_order']) && $subtotal < $coupon['min_order']) {
        setFlash('error', 'Minimum order amount of ' . currency($coupon['min_order']) . ' required for this coupon.');
        unset($_SESSION['applied_coupon']);
        redirect(url('customer/modules/cart/'));
    }

    $_SESSION['applied_coupon'] = [
        'id'       => $coupon['id'],
        'code'     => $coupon['code'],
        'type'     => $coupon['type'],
        'value'    => $coupon['value'],
    ];
    setFlash('success', 'Coupon "' . $coupon['code'] . '" applied successfully!');
    redirect(url('customer/modules/cart/'));
}

// ─── Fetch Cart Items ───────────────────────────────────────
$cartItems = Database::fetchAll("
    SELECT c.*, fi.name, fi.price, fi.discount_price, fi.image, fi.is_available
    FROM cart c
    JOIN food_items fi ON c.food_id = fi.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
", [$userId]);

// ─── Calculate Totals ──────────────────────────────────────
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

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .cart-item-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 10px;
    }
    .qty-btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .qty-btn:hover {
        background-color: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    .cart-summary-card {
        border-radius: 12px;
        overflow: hidden;
        position: sticky;
        top: 90px;
    }
    .empty-cart-icon {
        font-size: 5rem;
        opacity: 0.3;
    }
    .remove-btn:hover {
        color: #dc3545 !important;
    }
</style>

<!-- Breadcrumb -->
<section class="bg-light py-3 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('customer/index.php') ?>" class="text-decoration-none">Menu</a></li>
                <li class="breadcrumb-item active">Shopping Cart</li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h3 class="fw-bold mb-4">
            <i class="fas fa-shopping-cart text-warning me-2"></i>Shopping Cart
            <?php if (!empty($cartItems)): ?>
                <span class="text-muted fs-6">(<?= count($cartItems) ?> item<?= count($cartItems) > 1 ? 's' : '' ?>)</span>
            <?php endif; ?>
        </h3>

        <?php if (empty($cartItems)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart empty-cart-icon text-muted mb-3 d-block"></i>
                <h4 class="text-muted">Your cart is empty</h4>
                <p class="text-muted mb-4">Looks like you haven't added any items to your cart yet.</p>
                <a href="<?= url('customer/index.php') ?>" class="btn btn-warning btn-lg rounded-pill px-4">
                    <i class="fas fa-utensils me-2"></i>Browse Menu
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8 mb-4">
                    <div class="card border-0 shadow-sm cart-summary-card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="border-0 ps-3" style="width:45%">Item</th>
                                            <th class="border-0 text-center">Unit Price</th>
                                            <th class="border-0 text-center" style="width:180px">Quantity</th>
                                            <th class="border-0 text-center">Total</th>
                                            <th class="border-0 text-center pe-3" style="width:60px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cartItems as $item):
                                            $unitPrice = (!empty($item['discount_price']) && $item['discount_price'] < $item['price'])
                                                ? $item['discount_price']
                                                : $item['price'];
                                            $lineTotal = $unitPrice * $item['quantity'];
                                            $itemImage = !empty($item['image']) ? url('uploads/food/' . $item['image']) : url('assets/images/placeholder-food.jpg');
                                        ?>
                                        <tr class="<?= !$item['is_available'] ? 'table-secondary' : '' ?>">
                                            <td class="ps-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?= $itemImage ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-img">
                                                    <div>
                                                        <h6 class="fw-bold mb-1">
                                                            <a href="<?= url('customer/modules/menu/detail.php?id=' . $item['food_id']) ?>" class="text-decoration-none text-dark">
                                                                <?= htmlspecialchars($item['name']) ?>
                                                            </a>
                                                        </h6>
                                                        <?php if (!$item['is_available']): ?>
                                                            <span class="badge bg-danger">No longer available</span>
                                                        <?php elseif (!empty($item['discount_price']) && $item['discount_price'] < $item['price']): ?>
                                                            <span class="badge bg-danger">Sale</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($item['discount_price']) && $item['discount_price'] < $item['price']): ?>
                                                    <span class="text-decoration-line-through text-muted small d-block"><?= currency($item['price']) ?></span>
                                                    <span class="fw-bold text-danger"><?= currency($item['discount_price']) ?></span>
                                                <?php else: ?>
                                                    <span class="fw-bold"><?= currency($unitPrice) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <form method="POST" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                                        <button type="submit" name="quantity" value="<?= $item['quantity'] - 1 ?>" class="btn btn-outline-secondary btn-sm qty-btn">
                                                            <i class="fas fa-minus" style="font-size:0.7rem"></i>
                                                        </button>
                                                        <span class="mx-2 fw-bold" style="min-width:24px; text-align:center;"><?= $item['quantity'] ?></span>
                                                        <button type="submit" name="quantity" value="<?= $item['quantity'] + 1 ?>" class="btn btn-outline-secondary btn-sm qty-btn">
                                                            <i class="fas fa-plus" style="font-size:0.7rem"></i>
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                            <td class="text-center fw-bold">
                                                <?= currency($lineTotal) ?>
                                            </td>
                                            <td class="text-center pe-3">
                                                <form method="POST">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm text-muted remove-btn" title="Remove item">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <a href="<?= url('customer/index.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                        </a>
                    </div>
                </div>

                <!-- Cart Summary Sidebar -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm cart-summary-card">
                        <div class="card-header bg-warning text-dark py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-receipt me-2"></i>Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <!-- Coupon Form -->
                            <form method="POST" class="mb-4">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="coupon">
                                <label class="form-label fw-bold small text-muted">HAVE A COUPON?</label>
                                <div class="input-group">
                                    <input type="text" name="coupon_code" class="form-control form-control-sm" 
                                           placeholder="Enter code"
                                           value="<?= htmlspecialchars($appliedCoupon['code'] ?? '') ?>"
                                           <?= $appliedCoupon ? 'disabled' : '' ?>>
                                    <?php if ($appliedCoupon): ?>
                                        <a href="<?= url('customer/modules/cart/') ?>" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-times"></i> Remove
                                        </a>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-outline-warning btn-sm">
                                            <i class="fas fa-tag me-1"></i>Apply
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($appliedCoupon): ?>
                                    <small class="text-success mt-1 d-block">
                                        <i class="fas fa-check-circle me-1"></i>Coupon "<?= htmlspecialchars($appliedCoupon['code']) ?>" applied!
                                    </small>
                                <?php endif; ?>
                            </form>

                            <hr>

                            <!-- Price Breakdown -->
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span class="fw-bold"><?= currency($subtotal) ?></span>
                            </div>

                            <?php if ($discount > 0): ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>
                                    <i class="fas fa-tag me-1"></i>Discount
                                    <small>(<?= htmlspecialchars($appliedCoupon['code']) ?>)</small>
                                </span>
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

                            <a href="<?= url('customer/modules/checkout/') ?>" class="btn btn-warning btn-lg w-100 rounded-pill">
                                <i class="fas fa-lock me-2"></i>Proceed to Checkout
                            </a>

                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>Secure checkout
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
