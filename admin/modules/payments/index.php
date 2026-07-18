<?php
$pageTitle = 'Payment Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';

if (isPost() && !verifyCSRF()) {
    setFlash('error', 'Invalid security token. Please try again.');
    redirect(url('admin/modules/payments/index.php'));
}

if (isPost()) {
    $actionPost = $_POST['action'] ?? '';

    // ── UPDATE PAYMENT STATUS ──
    if ($actionPost === 'update_status') {
        $paymentId     = (int)($_POST['payment_id'] ?? 0);
        $newStatus     = trim($_POST['payment_status'] ?? '');

        $validStatuses = array_keys(PAYMENT_STATUSES);
        if ($paymentId < 1 || !in_array($newStatus, $validStatuses)) {
            setFlash('error', 'Invalid payment or status.');
            redirect(url('admin/modules/payments/index.php'));
        }

        $payment = Database::fetch(
            "SELECT p.*, o.order_number, o.customer_id
             FROM payments p
             JOIN orders o ON p.order_id = o.id
             WHERE p.id = ?",
            [$paymentId]
        );

        if (!$payment) {
            setFlash('error', 'Payment not found.');
            redirect(url('admin/modules/payments/index.php'));
        }

        $updateData = ['status' => $newStatus];
        if ($newStatus === 'completed') {
            $updateData['paid_at'] = date('Y-m-d H:i:s');
        }

        Database::update('payments', $updateData, 'id = ?', [$paymentId]);

        Database::update('orders', ['payment_status' => $newStatus], 'id = ?', [$payment['order_id']]);

        if ($payment['customer_id']) {
            addNotification(
                $payment['customer_id'],
                'Payment Status Updated',
                "Your payment for order {$payment['order_number']} has been marked as " . PAYMENT_STATUSES[$newStatus] . ".",
                'info',
                url('customer/modules/orders/index.php?action=view&id=' . $payment['order_id'])
            );
        }

        logActivity('payment_status_updated', "Payment #{$paymentId} for order {$payment['order_number']} status changed to {$newStatus}");
        setFlash('success', 'Payment status updated successfully.');
        redirect(url('admin/modules/payments/index.php'));
    }
}

// ─── VIEW PAYMENT DETAIL ─────────────────────────────────────
if ($action === 'view'):
    $paymentId = (int)($_GET['id'] ?? 0);
    $payment = Database::fetch(
        "SELECT p.*,
                o.order_number, o.customer_id, o.total AS order_total, o.subtotal, o.discount,
                o.delivery_fee, o.tax, o.payment_method, o.order_status,
                u.first_name AS customer_first_name, u.last_name AS customer_last_name,
                u.email AS customer_email, u.phone AS customer_phone
         FROM payments p
         JOIN orders o ON p.order_id = o.id
         LEFT JOIN users u ON o.customer_id = u.id
         WHERE p.id = ?",
        [$paymentId]
    );

    if (!$payment) {
        setFlash('error', 'Payment not found.');
        redirect(url('admin/modules/payments/index.php'));
    }

    $paymentStatusColors = [
        'pending'   => 'warning',
        'completed' => 'success',
        'paid'      => 'success',
        'failed'    => 'danger',
        'refunded'  => 'info',
    ];

    $paymentIcons = [
        'cod'          => 'fa-money-bill-wave',
        'mobile_money' => 'fa-mobile-alt',
        'card'         => 'fa-credit-card',
    ];
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Payment #<?= $payment['id'] ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('admin/modules/payments/index.php') ?>" class="text-decoration-none">Payments</a></li>
                <li class="breadcrumb-item active">View Payment</li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/payments/index.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Payment Details Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-4">
                    <i class="fas fa-receipt me-1" style="color:var(--primary)"></i> Payment Details
                </h6>
                <div class="row g-4">
                    <div class="col-sm-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted small" style="width:140px">Payment ID</td>
                                <td class="fw-semibold">#<?= $payment['id'] ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Order</td>
                                <td>
                                    <a href="<?= url('admin/modules/orders/index.php?action=view&id=' . $payment['order_id']) ?>"
                                       class="fw-semibold text-decoration-none" style="color:var(--primary);">
                                        <?= sanitize($payment['order_number']) ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Transaction ID</td>
                                <td>
                                    <?php if (!empty($payment['transaction_id'])): ?>
                                        <code class="bg-light px-2 py-1 rounded"><?= sanitize($payment['transaction_id']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Amount</td>
                                <td class="fw-bold" style="color:var(--primary);font-size:1.15rem;"><?= currency((float)$payment['amount']) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-sm-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted small" style="width:120px">Method</td>
                                <td>
                                    <i class="fas <?= $paymentIcons[$payment['method']] ?? 'fa-wallet' ?> me-1" style="color:var(--primary)"></i>
                                    <?= PAYMENT_METHODS[$payment['method']] ?? $payment['method'] ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Status</td>
                                <td>
                                    <?php $pColor = $paymentStatusColors[$payment['status']] ?? 'secondary'; ?>
                                    <span class="badge bg-<?= $pColor ?> bg-opacity-10 text-<?= $pColor ?> px-2 py-1">
                                        <?= PAYMENT_STATUSES[$payment['status']] ?? $payment['status'] ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Paid At</td>
                                <td>
                                    <?php if ($payment['paid_at']): ?>
                                        <?= date('M d, Y h:i A', strtotime($payment['paid_at'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted small">Created</td>
                                <td><?= date('M d, Y h:i A', strtotime($payment['created_at'])) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-shopping-bag me-1" style="color:var(--primary)"></i> Order Summary
                </h6>
                <?php
                $orderItems = Database::fetchAll(
                    "SELECT * FROM order_items WHERE order_id = ?",
                    [$payment['order_id']]
                );
                ?>
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
                            <?php if (empty($orderItems)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No items found</td></tr>
                            <?php else: ?>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= sanitize($item['name']) ?></td>
                                        <td class="text-center"><?= $item['quantity'] ?></td>
                                        <td class="text-end"><?= currency((float)$item['price']) ?></td>
                                        <td class="text-end fw-semibold"><?= currency((float)$item['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <hr class="my-3">
                <div class="row justify-content-end">
                    <div class="col-5">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted">Subtotal</td><td class="text-end"><?= currency((float)$payment['subtotal']) ?></td></tr>
                            <?php if ((float)$payment['discount'] > 0): ?>
                                <tr><td class="text-muted">Discount</td><td class="text-end text-danger">-<?= currency((float)$payment['discount']) ?></td></tr>
                            <?php endif; ?>
                            <tr><td class="text-muted">Delivery Fee</td><td class="text-end"><?= currency((float)$payment['delivery_fee']) ?></td></tr>
                            <tr><td class="text-muted">Tax</td><td class="text-end"><?= currency((float)$payment['tax']) ?></td></tr>
                            <tr class="border-top"><td class="fw-bold">Total</td><td class="text-end fw-bold" style="color:var(--primary);font-size:1.1rem;"><?= currency((float)$payment['order_total']) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Customer Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-user me-1" style="color:var(--primary)"></i> Customer
                </h6>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-semibold"
                         style="width:40px;height:40px;font-size:0.85rem;background:linear-gradient(135deg,var(--primary),var(--secondary));">
                        <?= strtoupper(substr(sanitize($payment['customer_first_name'] ?? 'C'), 0, 1)) ?>
                    </div>
                    <div>
                        <div class="fw-semibold"><?= sanitize(($payment['customer_first_name'] ?? '') . ' ' . ($payment['customer_last_name'] ?? '')) ?></div>
                        <small class="text-muted"><?= sanitize($payment['customer_email'] ?? '') ?></small>
                    </div>
                </div>
                <?php if (!empty($payment['customer_phone'])): ?>
                    <div class="small text-muted mt-2">
                        <i class="fas fa-phone fa-sm me-1"></i> <?= sanitize($payment['customer_phone']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Status -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-info-circle me-1" style="color:var(--primary)"></i> Order Status
                </h6>
                <?php $oColor = ORDER_STATUS_COLORS[$payment['order_status']] ?? 'secondary'; ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-<?= $oColor ?> bg-opacity-10 text-<?= $oColor ?> px-3 py-2">
                        <?= ORDER_STATUSES[$payment['order_status']] ?? $payment['order_status'] ?>
                    </span>
                </div>
                <a href="<?= url('admin/modules/orders/index.php?action=view&id=' . $payment['order_id']) ?>"
                   class="btn btn-outline-primary btn-sm w-100 mt-2">
                    <i class="fas fa-external-link-alt me-1"></i> View Order
                </a>
            </div>
        </div>

        <!-- Update Payment Status -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-cog me-1" style="color:var(--primary)"></i> Update Payment Status
                </h6>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                    <div class="mb-3">
                        <select class="form-select" name="payment_status" required>
                            <?php foreach (PAYMENT_STATUSES as $psKey => $psLabel): ?>
                                <option value="<?= $psKey ?>" <?= $payment['status'] === $psKey ? 'selected' : '' ?>><?= $psLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i> Update Status
                    </button>
                </form>
            </div>
        </div>

        <!-- Timeline -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-clock me-1" style="color:var(--primary)"></i> Timeline
                </h6>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:32px;height:32px;background:rgba(255,107,53,0.1);font-size:0.7rem;">
                            <i class="fas fa-plus" style="color:var(--primary)"></i>
                        </div>
                        <div>
                            <div class="small fw-semibold">Payment Created</div>
                            <small class="text-muted"><?= date('M d, Y h:i A', strtotime($payment['created_at'])) ?></small>
                        </div>
                    </div>
                    <?php if ($payment['paid_at']): ?>
                        <div class="d-flex gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:32px;height:32px;background:rgba(40,167,69,0.1);font-size:0.7rem;">
                                <i class="fas fa-check" style="color:#28a745"></i>
                            </div>
                            <div>
                                <div class="small fw-semibold">Payment Received</div>
                                <small class="text-muted"><?= date('M d, Y h:i A', strtotime($payment['paid_at'])) ?></small>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:32px;height:32px;background:rgba(108,117,125,0.1);font-size:0.7rem;">
                            <i class="fas fa-circle" style="color:#6c757d"></i>
                        </div>
                        <div>
                            <div class="small fw-semibold">Status: <?= PAYMENT_STATUSES[$payment['status']] ?? $payment['status'] ?></div>
                            <small class="text-muted">Current</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: // ─── LIST VIEW ──────────────────────────────────────

$search       = trim($_GET['search'] ?? '');
$filterMethod = $_GET['method'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$where  = '1';
$params = [];

if ($search !== '') {
    $where .= " AND (o.order_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.transaction_id LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
}

if ($filterMethod !== '' && array_key_exists($filterMethod, PAYMENT_METHODS)) {
    $where .= " AND p.method = ?";
    $params[] = $filterMethod;
}

if ($filterStatus !== '' && array_key_exists($filterStatus, PAYMENT_STATUSES)) {
    $where .= " AND p.status = ?";
    $params[] = $filterStatus;
}

$pagination = paginate(
    'payments p JOIN orders o ON p.order_id = o.id LEFT JOIN users u ON o.customer_id = u.id',
    $where,
    $params,
    10
);

$payments = Database::fetchAll(
    "SELECT p.*,
            o.order_number, o.customer_id, o.total AS order_total,
            u.first_name AS customer_first_name, u.last_name AS customer_last_name
     FROM payments p
     JOIN orders o ON p.order_id = o.id
     LEFT JOIN users u ON o.customer_id = u.id
     WHERE {$where}
     ORDER BY p.id DESC
     LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
    $params
);

$queryParams = array_filter([
    'search' => $search ?: null,
    'method' => $filterMethod ?: null,
    'status' => $filterStatus ?: null,
]);
$baseUrl = url('admin/modules/payments/index.php') . '?' . http_build_query($queryParams);

$totalPayments   = Database::count('payments');
$completedAmount = (float)(Database::fetch("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE status = 'completed'")['total'] ?? 0);
$pendingAmount   = (float)(Database::fetch("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE status = 'pending'")['total'] ?? 0);
$failedCount     = Database::count('payments', "status = 'failed'");
$refundedAmount  = (float)(Database::fetch("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE status = 'refunded'")['total'] ?? 0);

$paymentStatusColors = [
    'pending'   => 'warning',
    'completed' => 'success',
    'paid'      => 'success',
    'failed'    => 'danger',
    'refunded'  => 'info',
];

$paymentIcons = [
    'cod'          => 'fa-money-bill-wave',
    'mobile_money' => 'fa-mobile-alt',
    'card'         => 'fa-credit-card',
];
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Payment Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Payments</li>
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
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,107,53,0.1)">
                        <i class="fas fa-credit-card" style="color:var(--primary);font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Payments</div>
                        <div class="fw-bold fs-5"><?= $totalPayments ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(40,167,69,0.1)">
                        <i class="fas fa-check-circle" style="color:#28a745;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Completed</div>
                        <div class="fw-bold fs-5"><?= currency($completedAmount) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,193,7,0.1)">
                        <i class="fas fa-clock" style="color:#ffc107;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Pending</div>
                        <div class="fw-bold fs-5"><?= currency($pendingAmount) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(220,53,69,0.1)">
                        <i class="fas fa-times-circle" style="color:#dc3545;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Failed</div>
                        <div class="fw-bold fs-5"><?= $failedCount ?></div>
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
            <div class="col-md-4">
                <label for="search" class="form-label fw-semibold small">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Order #, customer, or transaction ID..." value="<?= sanitize($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label for="filterMethod" class="form-label fw-semibold small">Payment Method</label>
                <select class="form-select" id="filterMethod" name="method">
                    <option value="">All Methods</option>
                    <?php foreach (PAYMENT_METHODS as $mKey => $mLabel): ?>
                        <option value="<?= $mKey ?>" <?= $filterMethod === $mKey ? 'selected' : '' ?>><?= $mLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filterStatus" class="form-label fw-semibold small">Status</label>
                <select class="form-select" id="filterStatus" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (PAYMENT_STATUSES as $sKey => $sLabel): ?>
                        <option value="<?= $sKey ?>" <?= $filterStatus === $sKey ? 'selected' : '' ?>><?= $sLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('admin/modules/payments/index.php') ?>" class="btn btn-outline-secondary" title="Clear">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Payments Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:50px">#</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th>Transaction ID</th>
                        <th>Paid At</th>
                        <th class="text-end pe-3" style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-credit-card fa-2x mb-3 d-block opacity-50"></i>
                                No payments found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $i => $p): ?>
                            <?php $pColor = $paymentStatusColors[$p['status']] ?? 'secondary'; ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $pagination['offset'] + $i + 1 ?></td>
                                <td>
                                    <a href="<?= url('admin/modules/orders/index.php?action=view&id=' . $p['order_id']) ?>"
                                       class="fw-semibold text-decoration-none" style="color:var(--primary);">
                                        <?= sanitize($p['order_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-semibold"
                                             style="width:30px;height:30px;font-size:0.65rem;background:linear-gradient(135deg,var(--primary),var(--secondary));">
                                            <?= strtoupper(substr(sanitize($p['customer_first_name'] ?? 'C'), 0, 1)) ?>
                                        </div>
                                        <span class="small"><?= sanitize(($p['customer_first_name'] ?? '') . ' ' . ($p['customer_last_name'] ?? '')) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark px-2 py-1">
                                        <i class="fas <?= $paymentIcons[$p['method']] ?? 'fa-wallet' ?> me-1"></i>
                                        <?= PAYMENT_METHODS[$p['method']] ?? $p['method'] ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold"><?= currency((float)$p['amount']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $pColor ?> bg-opacity-10 text-<?= $pColor ?> px-2 py-1">
                                        <?= PAYMENT_STATUSES[$p['status']] ?? $p['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($p['transaction_id'])): ?>
                                        <code class="small"><?= sanitize($p['transaction_id']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['paid_at']): ?>
                                        <div class="small"><?= date('M d, Y', strtotime($p['paid_at'])) ?></div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($p['paid_at'])) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="<?= url('admin/modules/payments/index.php?action=view&id=' . $p['id']) ?>"
                                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <!-- Quick Status Actions -->
                                        <?php if ($p['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="payment_status" value="completed">
                                                <button type="submit" class="btn btn-sm btn-outline-success"
                                                        data-bs-toggle="tooltip" title="Mark Completed"
                                                        onclick="return confirm('Mark this payment as completed?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="payment_status" value="failed">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="tooltip" title="Mark Failed"
                                                        onclick="return confirm('Mark this payment as failed?')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($p['status'] === 'completed'): ?>
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="payment_status" value="refunded">
                                                <button type="submit" class="btn btn-sm btn-outline-info"
                                                        data-bs-toggle="tooltip" title="Refund"
                                                        onclick="return confirm('Refund this payment?')">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
                    of <?= $pagination['total'] ?> payments
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

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
