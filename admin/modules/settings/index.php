<?php
$pageTitle = 'Restaurant Settings';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

if (isPost()) {
    requireCSRF();

    if (isset($_POST['save_general'])) {
        setSetting('restaurant_name', sanitize($_POST['restaurant_name']));
        setSetting('restaurant_email', sanitize($_POST['restaurant_email']));
        setSetting('restaurant_phone', sanitize($_POST['restaurant_phone']));
        setSetting('restaurant_address', sanitize($_POST['restaurant_address']));
        logActivity('settings_updated', 'General settings updated');
        setFlash('success', 'General settings saved successfully!');
        header('Location: ' . url('admin/modules/settings/?tab=general'));
        exit;
    }

    if (isset($_POST['save_business'])) {
        setSetting('delivery_fee', sanitize($_POST['delivery_fee']));
        setSetting('free_delivery_minimum', sanitize($_POST['free_delivery_minimum']));
        setSetting('tax_rate', sanitize($_POST['tax_rate']));
        setSetting('currency', sanitize($_POST['currency']));
        setSetting('min_order_amount', sanitize($_POST['min_order_amount']));
        logActivity('settings_updated', 'Business settings updated');
        setFlash('success', 'Business settings saved successfully!');
        header('Location: ' . url('admin/modules/settings/?tab=business'));
        exit;
    }

    if (isset($_POST['save_hours'])) {
        setSetting('opening_time', sanitize($_POST['opening_time']));
        setSetting('closing_time', sanitize($_POST['closing_time']));
        logActivity('settings_updated', 'Operating hours updated');
        setFlash('success', 'Operating hours saved successfully!');
        header('Location: ' . url('admin/modules/settings/?tab=hours'));
        exit;
    }
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

$restaurantName = getSetting('restaurant_name') ?? 'Food Order System';
$restaurantEmail = getSetting('restaurant_email') ?? '';
$restaurantPhone = getSetting('restaurant_phone') ?? '';
$restaurantAddress = getSetting('restaurant_address') ?? '';
$deliveryFee = getSetting('delivery_fee') ?? '5.00';
$freeDeliveryMin = getSetting('free_delivery_minimum') ?? '50.00';
$taxRate = getSetting('tax_rate') ?? '8.00';
$currency = getSetting('currency') ?? 'USD';
$minOrderAmount = getSetting('min_order_amount') ?? '10.00';
$openingTime = getSetting('opening_time') ?? '09:00';
$closingTime = getSetting('closing_time') ?? '22:00';
?>

<style>
    .settings-nav .nav-link { border-radius: 10px; padding: 12px 20px; font-weight: 500; transition: all 0.3s; margin-bottom: 4px; }
    .settings-nav .nav-link:hover { background: #f8f9fa; }
    .settings-nav .nav-link.active { background: #0d6efd; color: #fff; font-weight: 600; }
    .settings-section { animation: fadeIn 0.4s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .section-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
</style>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="mb-1 fw-bold">Restaurant Settings</h4>
        <p class="text-muted mb-0">Configure your restaurant's general, business, and operating settings</p>
    </div>

    <div class="row g-4">
        <!-- Settings Navigation -->
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <h6 class="text-muted text-uppercase small fw-bold mb-3">Settings Sections</h6>
                    <div class="nav flex-column settings-nav">
                        <a class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" href="?tab=general">
                            <i class="bi bi-shop me-2"></i> General
                        </a>
                        <a class="nav-link <?= $activeTab === 'business' ? 'active' : '' ?>" href="?tab=business">
                            <i class="bi bi-briefcase me-2"></i> Business
                        </a>
                        <a class="nav-link <?= $activeTab === 'hours' ? 'active' : '' ?>" href="?tab=hours">
                            <i class="bi bi-clock me-2"></i> Operating Hours
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="col-lg-9">

            <!-- General Settings -->
            <?php if ($activeTab === 'general'): ?>
                <div class="settings-section">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex align-items-center gap-3">
                            <div class="section-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-shop"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 fw-bold">General Settings</h5>
                                <small class="text-muted">Restaurant name, contact info, and address</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?= csrfField() ?>
                                <div class="row g-4">
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold">Restaurant Name <span class="text-danger">*</span></label>
                                        <input type="text" name="restaurant_name" class="form-control form-control-lg" value="<?= htmlspecialchars($restaurantName) ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Currency</label>
                                        <select name="currency_display" class="form-select form-select-lg" disabled>
                                            <option><?= htmlspecialchars($currency) ?></option>
                                        </select>
                                        <small class="text-muted">Change in Business settings</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                            <input type="email" name="restaurant_email" class="form-control" value="<?= htmlspecialchars($restaurantEmail) ?>" required placeholder="restaurant@example.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                            <input type="tel" name="restaurant_phone" class="form-control" value="<?= htmlspecialchars($restaurantPhone) ?>" required placeholder="+1 (555) 000-0000">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                            <input type="text" name="restaurant_address" class="form-control" value="<?= htmlspecialchars($restaurantAddress) ?>" required placeholder="123 Main St, City, State, ZIP">
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="save_general" value="1" class="btn btn-primary btn-lg px-4">
                                        <i class="bi bi-check-circle me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <!-- Business Settings -->
            <?php elseif ($activeTab === 'business'): ?>
                <div class="settings-section">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex align-items-center gap-3">
                            <div class="section-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-briefcase"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 fw-bold">Business Settings</h5>
                                <small class="text-muted">Delivery, tax, and pricing configuration</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?= csrfField() ?>
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Delivery Fee (<?= htmlspecialchars($currency) ?>) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?= htmlspecialchars($currency) ?></span>
                                            <input type="number" name="delivery_fee" class="form-control" value="<?= htmlspecialchars($deliveryFee) ?>" step="0.01" min="0" required>
                                        </div>
                                        <small class="text-muted">Charge per delivery order</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Free Delivery Minimum (<?= htmlspecialchars($currency) ?>)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?= htmlspecialchars($currency) ?></span>
                                            <input type="number" name="free_delivery_minimum" class="form-control" value="<?= htmlspecialchars($freeDeliveryMin) ?>" step="0.01" min="0">
                                        </div>
                                        <small class="text-muted">Min order for free delivery (0 to disable)</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Tax Rate (%) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" name="tax_rate" class="form-control" value="<?= htmlspecialchars($taxRate) ?>" step="0.01" min="0" max="100" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">Applied to order subtotal</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                                        <select name="currency" class="form-select" required>
                                            <?php
                                            $currencies = ['USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)', 'PKR' => 'PKR (Rs)', 'INR' => 'INR (₹)', 'AED' => 'AED (د.إ)', 'SAR' => 'SAR (﷼)'];
                                            foreach ($currencies as $code => $label): ?>
                                                <option value="<?= $code ?>" <?= $currency === $code ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Minimum Order Amount (<?= htmlspecialchars($currency) ?>) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?= htmlspecialchars($currency) ?></span>
                                            <input type="number" name="min_order_amount" class="form-control" value="<?= htmlspecialchars($minOrderAmount) ?>" step="0.01" min="0" required>
                                        </div>
                                        <small class="text-muted">Minimum order to place an order</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="save_business" value="1" class="btn btn-success btn-lg px-4">
                                        <i class="bi bi-check-circle me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <!-- Operating Hours Settings -->
            <?php elseif ($activeTab === 'hours'): ?>
                <div class="settings-section">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex align-items-center gap-3">
                            <div class="section-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 fw-bold">Operating Hours</h5>
                                <small class="text-muted">Set your restaurant's opening and closing times</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?= csrfField() ?>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Opening Time <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-clock-fill text-success"></i></span>
                                            <input type="time" name="opening_time" class="form-control form-control-lg" value="<?= htmlspecialchars($openingTime) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Closing Time <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-clock-fill text-danger"></i></span>
                                            <input type="time" name="closing_time" class="form-control form-control-lg" value="<?= htmlspecialchars($closingTime) ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info mt-4">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Current Status:</strong>
                                    <?php
                                    $now = date('H:i');
                                    if ($openingTime <= $closingTime) {
                                        $isOpen = ($now >= $openingTime && $now < $closingTime);
                                    } else {
                                        $isOpen = ($now >= $openingTime || $now < $closingTime);
                                    }
                                    if ($isOpen): ?>
                                        <span class="text-success fw-bold">Open Now</span> (<?= date('h:i A', strtotime($openingTime)) ?> - <?= date('h:i A', strtotime($closingTime)) ?>)
                                    <?php else: ?>
                                        <span class="text-danger fw-bold">Currently Closed</span> (Opens at <?= date('h:i A', strtotime($openingTime)) ?>)
                                    <?php endif; ?>
                                </div>

                                <hr>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="save_hours" value="1" class="btn btn-warning btn-lg px-4">
                                        <i class="bi bi-check-circle me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
