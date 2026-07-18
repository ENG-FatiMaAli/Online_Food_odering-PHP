<?php
$pageTitle = 'My Favorites';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../includes/functions.php';

$userId = $_SESSION['user_id'] ?? 0;

if (!isLoggedIn() || !isCustomer()) {
    setFlash('warning', 'Please login to view your favorites.');
    redirect(url('login.php'));
}

// ─── CSRF Check ──────────────────────────────────────────────
if (isPost()) {
    requireCSRF();
}

// ─── Remove Favorite ─────────────────────────────────────────
if (isPost() && ($_POST['action'] ?? '') === 'remove') {
    $foodId = (int)($_POST['food_id'] ?? 0);
    Database::delete('favorites', 'user_id = ? AND food_id = ?', [$userId, $foodId]);
    setFlash('success', 'Item removed from favorites.');
    redirect(url('customer/modules/favorites/'));
}

// ─── Add to Cart from Favorites ─────────────────────────────
if (isPost() && ($_POST['action'] ?? '') === 'add_to_cart') {
    $foodId = (int)($_POST['food_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

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
        setFlash('error', 'Food item not available.');
    }
    redirect(url('customer/modules/favorites/'));
}

// ─── Fetch Favorites ────────────────────────────────────────
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$total = Database::count('favorites', 'user_id = ?', [$userId]);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$favorites = Database::fetchAll("
    SELECT f.id as fav_id, f.created_at as favorited_at,
           fi.id as food_id, fi.name, fi.slug, fi.description, fi.price, fi.discount_price,
           fi.image, fi.is_available, fi.rating_avg, fi.rating_count, fi.preparation_time,
           fc.name as category_name
    FROM favorites f
    JOIN food_items fi ON f.food_id = fi.id
    LEFT JOIN food_categories fc ON fi.category_id = fc.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
    LIMIT $perPage OFFSET $offset
", [$userId]);

$cartItems = [];
$cartResult = Database::fetchAll("SELECT food_id, quantity FROM cart WHERE user_id = ?", [$userId]);
foreach ($cartResult as $ci) {
    $cartItems[$ci['food_id']] = $ci['quantity'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .fav-card {
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .fav-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    }
    .fav-image {
        height: 180px;
        object-fit: cover;
    }
    .fav-heart-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(255,255,255,0.9);
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        z-index: 2;
    }
    .fav-heart-btn:hover {
        background: #dc3545;
        color: #fff;
    }
    .empty-fav-icon {
        font-size: 5rem;
        opacity: 0.2;
    }
</style>

<section class="bg-light py-3 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('customer/index.php') ?>" class="text-decoration-none">Menu</a></li>
                <li class="breadcrumb-item active">My Favorites</li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h4 class="fw-bold mb-4">
            <i class="fas fa-heart text-danger me-2"></i>My Favorites
            <?php if ($total > 0): ?>
                <span class="text-muted fs-6">(<?= $total ?> item<?= $total > 1 ? 's' : '' ?>)</span>
            <?php endif; ?>
        </h4>

        <?php if (empty($favorites)): ?>
            <div class="text-center py-5">
                <i class="fas fa-heart empty-fav-icon text-muted mb-3 d-block"></i>
                <h4 class="text-muted">No favorites yet</h4>
                <p class="text-muted mb-4">Save your favorite dishes for quick access!</p>
                <a href="<?= url('customer/index.php') ?>" class="btn btn-warning btn-lg rounded-pill px-4">
                    <i class="fas fa-utensils me-2"></i>Browse Menu
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($favorites as $fav):
                    $image = !empty($fav['image']) ? url('uploads/food/' . $fav['image']) : url('assets/images/placeholder-food.jpg');
                    $hasDiscount = !empty($fav['discount_price']) && $fav['discount_price'] < $fav['price'];
                    $price = $hasDiscount ? $fav['discount_price'] : $fav['price'];
                    $inCart = $cartItems[$fav['food_id']] ?? 0;
                ?>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="card border-0 shadow-sm fav-card h-100">
                            <div class="position-relative">
                                <a href="<?= url('customer/modules/menu/detail.php?id=' . $fav['food_id']) ?>">
                                    <img src="<?= $image ?>" alt="<?= sanitize($fav['name']) ?>" class="card-img-top fav-image">
                                </a>
                                <?php if ($hasDiscount): ?>
                                    <span class="badge bg-danger position-absolute top-0 start-0 m-2">
                                        -<?= round((1 - $fav['discount_price'] / $fav['price']) * 100) ?>%
                                    </span>
                                <?php endif; ?>
                                <form method="POST" class="fav-heart-btn">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="food_id" value="<?= $fav['food_id'] ?>">
                                    <button type="submit" class="btn p-0 text-danger" title="Remove from favorites">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div class="mb-1">
                                    <span class="badge bg-light text-dark small"><?= sanitize($fav['category_name'] ?? 'Uncategorized') ?></span>
                                </div>
                                <h6 class="fw-bold">
                                    <a href="<?= url('customer/modules/menu/detail.php?id=' . $fav['food_id']) ?>" class="text-decoration-none text-dark">
                                        <?= sanitize($fav['name']) ?>
                                    </a>
                                </h6>
                                <div class="mb-2">
                                    <?php if ((float)$fav['rating_avg'] > 0): ?>
                                        <span class="small">
                                            <?= getStarRating($fav['rating_avg']) ?>
                                            <span class="text-muted ms-1">(<?= $fav['rating_count'] ?>)</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($fav['preparation_time']): ?>
                                    <small class="text-muted mb-2">
                                        <i class="far fa-clock me-1"></i><?= $fav['preparation_time'] ?> min
                                    </small>
                                <?php endif; ?>
                                <div class="mt-auto d-flex align-items-center justify-content-between pt-2">
                                    <div>
                                        <?php if ($hasDiscount): ?>
                                            <span class="text-decoration-line-through text-muted small"><?= currency($fav['price']) ?></span>
                                            <span class="fw-bold text-danger ms-1"><?= currency($fav['discount_price']) ?></span>
                                        <?php else: ?>
                                            <span class="fw-bold text-primary"><?= currency($fav['price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($fav['is_available']): ?>
                                        <form method="POST">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="food_id" value="<?= $fav['food_id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="btn btn-sm <?= $inCart > 0 ? 'btn-success' : 'btn-warning' ?> rounded-pill px-3">
                                                <?php if ($inCart > 0): ?>
                                                    <i class="fas fa-check me-1"></i>In Cart (<?= $inCart ?>)
                                                <?php else: ?>
                                                    <i class="fas fa-cart-plus me-1"></i>Add
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unavailable</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
