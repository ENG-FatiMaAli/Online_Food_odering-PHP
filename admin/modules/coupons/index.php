<?php
$pageTitle = 'Coupon Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (isPost()) {
    requireCSRF();

    if (isset($_POST['save_coupon'])) {
        $code = strtoupper(sanitize($_POST['code']));
        $type = sanitize($_POST['type']);
        $value = (float)$_POST['value'];
        $minOrder = (float)$_POST['min_order'];
        $maxUses = (int)$_POST['max_uses'];
        $startDate = sanitize($_POST['starts_at']);
        $expiryDate = sanitize($_POST['expires_at']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $editId = (int)($_POST['coupon_id'] ?? 0);

        if (empty($code) || $value <= 0) {
            setFlash('error', 'Please fill in all required fields with valid values.');
            header('Location: ' . url('admin/modules/coupons/index.php?action=' . ($editId ? 'edit&id=' . $editId : 'add')));
            exit;
        }

        $existing = Database::fetchAll("SELECT id FROM coupons WHERE code = ? AND id != ?", [$code, $editId]);
        if (!empty($existing)) {
            setFlash('error', 'Coupon code already exists.');
            header('Location: ' . url('admin/modules/coupons/index.php?action=' . ($editId ? 'edit&id=' . $editId : 'add')));
            exit;
        }

        $data = [
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'min_order' => $minOrder,
            'max_uses' => $maxUses,
            'starts_at' => $startDate,
            'expires_at' => $expiryDate,
            'is_active' => $isActive,
        ];

        if ($editId) {
            Database::update('coupons', $data, 'id = ?', [$editId]);
            logActivity('Updated coupon: ' . $code);
            setFlash('success', 'Coupon updated successfully.');
        } else {
            $data['used_count'] = 0;
            $data['created_at'] = date('Y-m-d H:i:s');
            Database::insert('coupons', $data);
            logActivity('Created coupon: ' . $code);
            setFlash('success', 'Coupon created successfully.');
        }
        header('Location: ' . url('admin/modules/coupons/index.php'));
        exit;
    }

    if (isset($_POST['delete_coupon'])) {
        $couponId = (int)$_POST['coupon_id'];
        $coupon = Database::fetchAll("SELECT code FROM coupons WHERE id = ?", [$couponId]);
        Database::delete('coupons', 'id = ?', [$couponId]);
        logActivity('Deleted coupon: ' . ($coupon[0]['code'] ?? 'unknown'));
        setFlash('success', 'Coupon deleted successfully.');
        header('Location: ' . url('admin/modules/coupons/index.php'));
        exit;
    }

    if (isset($_POST['toggle_active'])) {
        $couponId = (int)$_POST['coupon_id'];
        $current = Database::fetchAll("SELECT is_active FROM coupons WHERE id = ?", [$couponId]);
        $newStatus = !empty($current) ? ($current[0]['is_active'] ? 0 : 1) : 0;
        Database::update('coupons', ['is_active' => $newStatus], 'id = ?', [$couponId]);
        logActivity(($newStatus ? 'Activated' : 'Deactivated') . ' coupon #' . $couponId);
        setFlash('success', 'Coupon status updated.');
        header('Location: ' . url('admin/modules/coupons/index.php'));
        exit;
    }
}

$search = $_GET['search'] ?? '';

if ($action === 'list'):

    $stats = [
        'total' => Database::count('coupons'),
        'active' => Database::count('coupons', "is_active = 1 AND (expires_at IS NULL OR expires_at >= NOW())"),
        'expired' => Database::count('coupons', "expires_at < NOW()"),
        'total_savings' => Database::fetchAll("SELECT COALESCE(SUM(CASE WHEN type = 'fixed' THEN value * used_count WHEN type = 'percentage' THEN 0 END), 0) AS total FROM coupons")[0]['total'] ?? 0,
    ];

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = 15;

    $where = "1=1";
    $params = [];
    if ($search) {
        $where .= " AND code LIKE ?";
        $params[] = '%' . $search . '%';
    }

    $total = Database::count('coupons', $where, $params);
    $coupons = Database::fetchAll(
        "SELECT * FROM coupons WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, ($page - 1) * $perPage])
    );
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-tags me-2"></i>Coupon Management</h4>
    <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add Coupon</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-ticket-alt fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['total'] ?></h3>
                <small>Total Coupons</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['active'] ?></h3>
                <small>Active</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body text-center">
                <i class="fas fa-times-circle fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['expired'] ?></h3>
                <small>Expired</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-piggy-bank fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= currency($stats['total_savings']) ?></h3>
                <small>Total Savings Given</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="list">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" placeholder="Search by coupon code..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button>
            </div>
            <div class="col-md-2">
                <a href="?action=list" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Min Order</th>
                        <th>Used / Max</th>
                        <th>Status</th>
                        <th>Date Range</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>No coupons found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $c): ?>
                            <?php
                            $isExpired = $c['expires_at'] && strtotime($c['expires_at']) < time();
                            $statusClass = $c['is_active'] && !$isExpired ? 'success' : ($isExpired ? 'danger' : 'secondary');
                            $statusText = $c['is_active'] && !$isExpired ? 'Active' : ($isExpired ? 'Expired' : 'Inactive');
                            ?>
                            <tr>
                                <td><code class="fs-6 fw-bold text-primary"><?= htmlspecialchars($c['code']) ?></code></td>
                                <td>
                                    <?php if ($c['type'] === 'percentage'): ?>
                                        <span class="badge bg-info">Percentage</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Fixed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>
                                        <?= $c['type'] === 'percentage' ? $c['value'] . '%' : currency($c['value']) ?>
                                    </strong>
                                </td>
                                <td><?= currency($c['min_order']) ?></td>
                                <td>
                                    <span class="<?= $c['max_uses'] > 0 && $c['used_count'] >= $c['max_uses'] ? 'text-danger fw-bold' : '' ?>">
                                        <?= $c['used_count'] ?>
                                    </span>
                                    / <?= $c['max_uses'] > 0 ? $c['max_uses'] : '&infin;' ?>
                                </td>
                                <td><span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span></td>
                                <td>
                                    <small>
                                        <?= $c['starts_at'] ? date('M d, Y', strtotime($c['starts_at'])) : 'N/A' ?>
                                        <br>
                                        <i class="fas fa-arrow-down text-muted"></i><br>
                                        <?= $c['expires_at'] ? date('M d, Y', strtotime($c['expires_at'])) : 'No expiry' ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                                            <button type="submit" name="toggle_active" class="btn btn-outline-<?= $c['is_active'] ? 'warning' : 'success' ?>" title="<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $c['is_active'] ? 'toggle-off' : 'toggle-on' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this coupon?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                                            <button type="submit" name="delete_coupon" class="btn btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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
$paginationUrl = url('admin/modules/coupons/index.php') . '?search=' . urlencode($search);
$totalPages = ceil($total / $perPage);
echo renderPagination(['page' => $page, 'pages' => $totalPages, 'total' => $total, 'offset' => ($page - 1) * $perPage, 'perPage' => $perPage], $paginationUrl);
?>

<?php elseif ($action === 'add' || ($action === 'edit' && $id)):

    $coupon = null;
    if ($action === 'edit' && $id) {
        $coupon = Database::fetchAll("SELECT * FROM coupons WHERE id = ?", [$id]);
        if (empty($coupon)) {
            setFlash('error', 'Coupon not found.');
            header('Location: ' . url('admin/modules/coupons/index.php'));
            exit;
        }
        $coupon = $coupon[0];
    }
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= url('admin/modules/coupons/index.php') ?>" class="text-decoration-none">
            <i class="fas fa-arrow-left me-2"></i>Back to Coupons
        </a>
        <h4 class="mb-0 mt-1">
            <i class="fas fa-<?= $coupon ? 'edit' : 'plus-circle' ?> me-2"></i>
            <?= $coupon ? 'Edit Coupon' : 'Add New Coupon' ?>
        </h4>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST">
                    <?= csrfField() ?>
                    <?php if ($coupon): ?>
                        <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Coupon Code <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                <input type="text" name="code" class="form-control text-uppercase" required maxlength="20" placeholder="e.g. SAVE20"
                                    value="<?= htmlspecialchars($coupon['code'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Discount Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="percentage" <?= ($coupon['type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                <option value="fixed" <?= ($coupon['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Discount Value <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                <input type="number" name="value" class="form-control" step="0.01" min="0.01" required placeholder="0.00"
                                    value="<?= $coupon['value'] ?? '' ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Minimum Order Amount</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-shopping-cart"></i></span>
                                <input type="number" name="min_order" class="form-control" step="0.01" min="0" placeholder="0.00"
                                    value="<?= $coupon['min_order'] ?? '0' ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Maximum Uses</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-users"></i></span>
                                <input type="number" name="max_uses" class="form-control" min="0" placeholder="0 = unlimited"
                                    value="<?= $coupon['max_uses'] ?? '0' ?>">
                            </div>
                            <small class="text-muted">Set to 0 for unlimited uses.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Active</label>
                            <div class="form-check form-switch mt-2">
                                <input type="checkbox" name="is_active" class="form-check-input" role="switch" id="activeSwitch"
                                    <?= ($coupon['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activeSwitch">Enable this coupon</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" name="starts_at" class="form-control"
                                value="<?= $coupon['starts_at'] ? date('Y-m-d', strtotime($coupon['starts_at'])) : '' ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Expiry Date</label>
                            <input type="date" name="expires_at" class="form-control"
                                value="<?= $coupon['expires_at'] ? date('Y-m-d', strtotime($coupon['expires_at'])) : '' ?>">
                            <small class="text-muted">Leave blank for no expiry.</small>
                        </div>

                        <?php if ($coupon && $coupon['used_count'] > 0): ?>
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    This coupon has been used <strong><?= $coupon['used_count'] ?></strong> time(s).
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('admin/modules/coupons/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" name="save_coupon" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i><?= $coupon ? 'Update Coupon' : 'Create Coupon' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
    <div class="alert alert-danger">Invalid action.</div>
    <?php header('Location: ' . url('admin/modules/coupons/index.php')); exit; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
