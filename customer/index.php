<?php
$pageTitle = 'Menu - Food Ordering System';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/functions.php';

// Handle Add to Cart (BEFORE any HTML output)
if (isPost() && isset($_POST['add_to_cart'])) {
    requireCSRF();
    $foodId = (int)($_POST['food_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $userId = $_SESSION['user_id'] ?? 0;

    if ($userId && $foodId > 0) {
        $existing = Database::fetch("SELECT id, quantity FROM cart WHERE user_id = ? AND food_id = ?", [$userId, $foodId]);
        if ($existing) {
            Database::update('cart', ['quantity' => $existing['quantity'] + $quantity], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('cart', ['user_id' => $userId, 'food_id' => $foodId, 'quantity' => $quantity]);
        }
        setFlash('success', 'Item added to cart!');
    } else {
        setFlash('error', 'Please login to add items to cart.');
    }
    header('Location: ' . url('customer/index.php'));
    exit;
}

// Handle Favorite Toggle (BEFORE any HTML output)
if (isPost() && isset($_POST['toggle_favorite'])) {
    requireCSRF();
    $foodId = (int)($_POST['food_id'] ?? 0);
    $userId = $_SESSION['user_id'] ?? 0;

    if ($userId && $foodId > 0) {
        $existing = Database::fetch("SELECT id FROM favorites WHERE user_id = ? AND food_id = ?", [$userId, $foodId]);
        if ($existing) {
            Database::delete('favorites', 'id = ?', [$existing['id']]);
            setFlash('success', 'Removed from favorites.');
        } else {
            Database::insert('favorites', ['user_id' => $userId, 'food_id' => $foodId]);
            setFlash('success', 'Added to favorites!');
        }
    } else {
        setFlash('error', 'Please login first.');
    }
    header('Location: ' . url('customer/index.php'));
    exit;
}

require_once __DIR__ . '/includes/header.php';

// Get search and category filters
$search = $_GET['search'] ?? '';
$categoryFilter = (int)($_GET['category'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["fi.is_available = 1"];
$params = [];

if (!empty($search)) {
    $where[] = "(fi.name LIKE ? OR fi.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryFilter > 0) {
    $where[] = "fi.category_id = ?";
    $params[] = $categoryFilter;
}

$whereClause = implode(' AND ', $where);

// Get total count
$totalItems = Database::count("food_items fi", $whereClause, $params);
$totalPages = ceil($totalItems / $perPage);

// Get food items
$foods = Database::fetchAll("
    SELECT fi.*, fc.name as category_name
    FROM food_items fi
    LEFT JOIN food_categories fc ON fi.category_id = fc.id
    WHERE $whereClause
    ORDER BY fi.is_featured DESC, fi.created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);

// Get featured items
$featuredFoods = Database::fetchAll("
    SELECT fi.*, fc.name as category_name
    FROM food_items fi
    LEFT JOIN food_categories fc ON fi.category_id = fc.id
    WHERE fi.is_available = 1 AND fi.is_featured = 1
    ORDER BY fi.created_at DESC
    LIMIT 6
");

// Get all categories
$categories = Database::fetchAll("SELECT * FROM food_categories ORDER BY name ASC");

// Get user's cart items for quantity display
$userId = $_SESSION['user_id'] ?? 0;
$cartItems = [];
if ($userId) {
    $cartResult = Database::fetchAll("SELECT food_id, quantity FROM cart WHERE user_id = ?", [$userId]);
    foreach ($cartResult as $ci) {
        $cartItems[$ci['food_id']] = $ci['quantity'];
    }
}

// Get user's favorites
$favorites = [];
if ($userId) {
    $favResult = Database::fetchAll("SELECT food_id FROM favorites WHERE user_id = ?", [$userId]);
    foreach ($favResult as $f) {
        $favorites[] = $f['food_id'];
    }
}

// Helper: Rating Stars HTML
function renderStars($rating, $count = 0) {
    $fullStars = (int)$rating;
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    $html = '<div class="rating">';
    for ($i = 0; $i < $fullStars; $i++) $html .= '<i class="fas fa-star text-warning"></i>';
    if ($halfStar) $html .= '<i class="fas fa-star-half-alt text-warning"></i>';
    for ($i = 0; $i < $emptyStars; $i++) $html .= '<i class="far fa-star text-warning"></i>';
    if ($count > 0) $html .= " <span class=\"text-muted small\">($count)</span>";
    $html .= '</div>';
    return $html;
}

// Helper: Food Card HTML
function renderFoodCard($food, $cartItems, $favorites) {
    $image = !empty($food['image']) ? url('uploads/food/' . $food['image']) : url('assets/images/placeholder-food.jpg');
    $hasDiscount = !empty($food['discount_price']) && $food['discount_price'] < $food['price'];
    $isFav = in_array($food['id'], $favorites);
    $inCart = $cartItems[$food['id']] ?? 0;
    ?>
    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
        <div class="card food-card h-100 shadow-sm border-0">
            <div class="position-relative">
                <a href="<?= url('customer/modules/menu/detail.php?id=' . $food['id']) ?>">
                    <img src="<?= $image ?>" class="card-img-top food-image" alt="<?= htmlspecialchars($food['name']) ?>">
                </a>
                <?php if ($hasDiscount): ?>
                    <span class="badge bg-danger position-absolute top-0 start-0 m-2">
                        -<?= round((1 - $food['discount_price'] / $food['price']) * 100) ?>%
                    </span>
                <?php endif; ?>
                <?php if ($food['is_featured']): ?>
                    <span class="badge bg-warning position-absolute top-0 end-0 m-2">
                        <i class="fas fa-fire"></i> Featured
                    </span>
                <?php endif; ?>
                <form method="POST" class="position-absolute bottom-0 end-0 m-2" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="food_id" value="<?= $food['id'] ?>">
                    <button type="submit" name="toggle_favorite" class="btn btn-sm <?= $isFav ? 'btn-danger' : 'btn-outline-light' ?> rounded-circle shadow">
                        <i class="fas fa-heart"></i>
                    </button>
                </form>
            </div>
            <div class="card-body d-flex flex-column">
                <div class="mb-1">
                    <span class="badge bg-light text-dark small"><?= htmlspecialchars($food['category_name'] ?? 'Uncategorized') ?></span>
                </div>
                <h6 class="card-title fw-bold">
                    <a href="<?= url('customer/modules/menu/detail.php?id=' . $food['id']) ?>" class="text-decoration-none text-dark">
                        <?= htmlspecialchars($food['name']) ?>
                    </a>
                </h6>
                <?php if (!empty($food['rating_avg'])): ?>
                    <?= renderStars($food['rating_avg'], $food['rating_count'] ?? 0) ?>
                <?php endif; ?>
                <div class="mt-auto d-flex align-items-center justify-content-between">
                    <div class="price-section">
                        <?php if ($hasDiscount): ?>
                            <span class="text-decoration-line-through text-muted small">$<?= number_format($food['price'], 2) ?></span>
                            <span class="fw-bold text-danger ms-1">$<?= number_format($food['discount_price'], 2) ?></span>
                        <?php else: ?>
                            <span class="fw-bold text-primary">$<?= number_format($food['price'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="food_id" value="<?= $food['id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" name="add_to_cart" class="btn btn-sm btn-warning rounded-pill px-3">
                            <?php if ($inCart > 0): ?>
                                <i class="fas fa-check"></i> In Cart (<?= $inCart ?>)
                            <?php else: ?>
                                <i class="fas fa-cart-plus"></i> Add
                            <?php endif; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>

<!-- Hero Banner -->
<section class="hero-banner bg-warning text-dark py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-5 fw-bold mb-3">
                    <i class="fas fa-utensils me-2"></i> Welcome to Our Restaurant
                </h1>
                <p class="lead mb-4">Discover delicious food crafted with love. Order now and enjoy fast delivery!</p>
                
                <!-- Search Bar -->
                <form method="GET" class="mb-3">
                    <div class="input-group input-group-lg shadow">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0" 
                               placeholder="Search for food items..." 
                               value="<?= htmlspecialchars($search) ?>">
                        <?php if ($categoryFilter > 0): ?>
                            <input type="hidden" name="category" value="<?= $categoryFilter ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-dark px-4">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="<?= url('customer/index.php' . ($categoryFilter ? "?category=$categoryFilter" : '')) ?>" 
                               class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="col-lg-4 text-center d-none d-lg-block">
                <i class="fas fa-hamburger fa-6x opacity-50"></i>
            </div>
        </div>
    </div>
</section>

<!-- Category Filters -->
<section class="py-3 bg-light border-bottom">
    <div class="container">
        <div class="d-flex align-items-center gap-2 overflow-auto py-1" style="white-space: nowrap;">
            <a href="<?= url('customer/index.php' . (!empty($search) ? "?search=" . urlencode($search) : '')) ?>" 
               class="btn btn-sm <?= !$categoryFilter ? 'btn-warning' : 'btn-outline-secondary' ?> rounded-pill px-3">
                All Items
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?= url('customer/index.php?category=' . $cat['id'] . (!empty($search) ? '&search=' . urlencode($search) : '')) ?>" 
                   class="btn btn-sm <?= $categoryFilter == $cat['id'] ? 'btn-warning' : 'btn-outline-secondary' ?> rounded-pill px-3">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Items -->
<?php if (!empty($featuredFoods) && !$search && !$categoryFilter): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">
                <i class="fas fa-fire text-warning me-2"></i>Featured Items
            </h3>
            <a href="<?= url('customer/index.php?featured=1') ?>" class="btn btn-outline-warning btn-sm rounded-pill">
                View All <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="row">
            <?php foreach ($featuredFoods as $food): ?>
                <?php renderFoodCard($food, $cartItems, $favorites); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- All Food Items -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0">
                <i class="fas fa-list text-warning me-2"></i>
                <?= $search ? 'Search Results' : ($categoryFilter ? 'Filtered Items' : 'All Menu Items') ?>
                <span class="text-muted fs-6">(<?= $totalItems ?> items)</span>
            </h3>
        </div>

        <?php if (empty($foods)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No food items found</h4>
                <p class="text-muted">Try adjusting your search or filters</p>
                <a href="<?= url('customer/index.php') ?>" class="btn btn-warning rounded-pill">View All Menu</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($foods as $food): ?>
                    <?php renderFoodCard($food, $cartItems, $favorites); ?>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Menu pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= $categoryFilter ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    if ($startPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&category=<?= $categoryFilter ?>">1</a></li>
                        <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $categoryFilter ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>&category=<?= $categoryFilter ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= $categoryFilter ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Quick Add Modal -->
<div class="modal fade" id="quickAddModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-cart-plus me-2"></i>Add to Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <div class="modal-body">
                    <input type="hidden" name="food_id" id="modalFoodId">
                    <div class="text-center mb-3">
                        <img id="modalFoodImage" src="" alt="" class="rounded" style="max-height:150px;">
                        <h5 id="modalFoodName" class="mt-2"></h5>
                        <p id="modalFoodPrice" class="text-warning fw-bold"></p>
                    </div>
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle" onclick="changeQty(-1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" name="quantity" id="modalQty" value="1" min="1" max="20" 
                               class="form-control text-center" style="width:70px;">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle" onclick="changeQty(1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_to_cart" class="btn btn-warning">
                        <i class="fas fa-cart-plus me-1"></i>Add to Cart
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changeQty(delta) {
    const input = document.getElementById('modalQty');
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > 20) val = 20;
    input.value = val;
}

function openQuickAdd(id, name, price, image) {
    document.getElementById('modalFoodId').value = id;
    document.getElementById('modalFoodName').textContent = name;
    document.getElementById('modalFoodPrice').textContent = '$' + parseFloat(price).toFixed(2);
    document.getElementById('modalFoodImage').src = image;
    document.getElementById('modalQty').value = 1;
    new bootstrap.Modal(document.getElementById('quickAddModal')).show();
}
</script>

<style>
.food-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-radius: 12px;
    overflow: hidden;
}
.food-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}
.food-image {
    height: 180px;
    object-fit: cover;
}
.hero-banner {
    background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%);
}
.rating .fas, .rating .far {
    font-size: 0.85rem;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
