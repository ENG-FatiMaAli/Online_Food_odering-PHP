<?php
$pageTitle = 'Food Detail';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../includes/functions.php';

$foodId = (int)($_GET['id'] ?? 0);
if ($foodId <= 0) {
    setFlash('error', 'Invalid food item.');
    header('Location: ' . url('customer/index.php'));
    exit;
}

$food = Database::fetch("
    SELECT fi.*, fc.name as category_name
    FROM food_items fi
    LEFT JOIN food_categories fc ON fi.category_id = fc.id
    WHERE fi.id = ? AND fi.is_available = 1
", [$foodId]);

if (!$food) {
    setFlash('error', 'Food item not found.');
    header('Location: ' . url('customer/index.php'));
    exit;
}

$galleryImages = Database::fetchAll("SELECT * FROM food_images WHERE food_id = ? ORDER BY id ASC", [$foodId]);

$reviews = Database::fetchAll("
    SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.food_id = ? AND r.is_visible = 1
    ORDER BY r.created_at DESC
    LIMIT 10
", [$foodId]);

$ratingStats = Database::fetch("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE food_id = ? AND is_visible = 1", [$foodId]);
$avgRating = round($ratingStats['avg_rating'] ?? 0, 1);
$totalReviews = $ratingStats['total_reviews'] ?? 0;

$relatedFoods = Database::fetchAll("
    SELECT fi.*, fc.name as category_name
    FROM food_items fi
    LEFT JOIN food_categories fc ON fi.category_id = fc.id
    WHERE fi.category_id = ? AND fi.id != ? AND fi.is_available = 1
    ORDER BY RAND()
    LIMIT 4
", [$food['category_id'], $foodId]);

$userId = $_SESSION['user_id'] ?? 0;
$hasReviewed = false;
$hasOrdered = false;
$isFav = false;

if ($userId) {
    $hasReviewed = Database::fetch("SELECT id FROM reviews WHERE user_id = ? AND food_id = ?", [$userId, $foodId]) !== null;
    $hasOrdered = Database::fetch("SELECT oi.id FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.customer_id = ? AND oi.food_id = ? AND o.order_status = 'delivered'", [$userId, $foodId]) !== null;
    $isFav = Database::fetch("SELECT id FROM favorites WHERE user_id = ? AND food_id = ?", [$userId, $foodId]) !== null;
}

if (isPost() && isset($_POST['add_to_cart'])) {
    requireCSRF();
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    if ($userId) {
        $existing = Database::fetch("SELECT id, quantity FROM cart WHERE user_id = ? AND food_id = ?", [$userId, $foodId]);
        if ($existing) {
            Database::update('cart', ['quantity' => $existing['quantity'] + $quantity], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('cart', ['user_id' => $userId, 'food_id' => $foodId, 'quantity' => $quantity]);
        }
        setFlash('success', 'Item added to cart!');
    } else {
        setFlash('warning', 'Please login to add items to cart.');
    }
    header('Location: ' . url('customer/modules/menu/detail.php?id=' . $foodId));
    exit;
}

if (isPost() && isset($_POST['add_review'])) {
    requireCSRF();
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = sanitize($_POST['comment'] ?? '');
    if ($userId && $hasOrdered && !$hasReviewed && !empty($comment)) {
        Database::insert('reviews', [
            'user_id' => $userId,
            'food_id' => $foodId,
            'rating' => $rating,
            'comment' => $comment,
            'is_visible' => 0
        ]);
        $newStats = Database::fetch("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE food_id = ? AND is_visible = 1", [$foodId]);
        Database::update('food_items', [
            'rating_avg' => round($newStats['avg_rating'] ?? 0, 1),
            'rating_count' => $newStats['total_reviews'] ?? 0
        ], 'id = ?', [$foodId]);
        setFlash('success', 'Review submitted! It will appear after approval.');
    }
    header('Location: ' . url('customer/modules/menu/detail.php?id=' . $foodId . '#reviews'));
    exit;
}

if (isPost() && isset($_POST['toggle_favorite'])) {
    requireCSRF();
    if ($userId) {
        if ($isFav) {
            Database::delete('favorites', 'user_id = ? AND food_id = ?', [$userId, $foodId]);
            setFlash('info', 'Removed from favorites.');
        } else {
            Database::insert('favorites', ['user_id' => $userId, 'food_id' => $foodId]);
            setFlash('success', 'Added to favorites!');
        }
    }
    header('Location: ' . url('customer/modules/menu/detail.php?id=' . $foodId));
    exit;
}

function renderStars($rating, $count = 0, $size = '1rem') {
    $fullStars = (int)$rating;
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    $html = '<div class="rating d-inline-flex align-items-center">';
    for ($i = 0; $i < $fullStars; $i++) $html .= '<i class="fas fa-star text-warning" style="font-size:' . $size . '"></i>';
    if ($halfStar) $html .= '<i class="fas fa-star-half-alt text-warning" style="font-size:' . $size . '"></i>';
    for ($i = 0; $i < $emptyStars; $i++) $html .= '<i class="far fa-star text-warning" style="font-size:' . $size . '"></i>';
    if ($count > 0) $html .= " <span class=\"text-muted small ms-1\">($count reviews)</span>";
    $html .= '</div>';
    return $html;
}

$hasDiscount = !empty($food['discount_price']) && $food['discount_price'] < $food['price'];
$mainImage = !empty($food['image']) ? url('uploads/food/' . $food['image']) : url('assets/images/placeholder-food.jpg');

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="bg-light py-3 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('customer/index.php') ?>" class="text-decoration-none">Menu</a></li>
                <?php if (!empty($food['category_name'])): ?>
                    <li class="breadcrumb-item">
                        <a href="<?= url('customer/index.php?category=' . $food['category_id']) ?>" class="text-decoration-none">
                            <?= htmlspecialchars($food['category_name']) ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($food['name']) ?></li>
            </ol>
        </nav>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="main-image-container mb-3">
                    <img src="<?= $mainImage ?>" alt="<?= htmlspecialchars($food['name']) ?>" 
                         class="img-fluid rounded shadow" id="mainImage"
                         style="width:100%; height:400px; object-fit:cover;">
                </div>
                <?php if (!empty($galleryImages)): ?>
                <div class="gallery-thumbs d-flex gap-2 overflow-auto pb-2">
                    <img src="<?= $mainImage ?>" alt="Main" class="gallery-thumb rounded border border-2 border-warning" 
                         style="width:80px; height:80px; object-fit:cover; cursor:pointer;" onclick="changeMainImage(this.src)">
                    <?php foreach ($galleryImages as $img): ?>
                        <?php $imgUrl = url('uploads/food/' . $img['image']); ?>
                        <img src="<?= $imgUrl ?>" alt="Gallery" class="gallery-thumb rounded border" 
                             style="width:80px; height:80px; object-fit:cover; cursor:pointer;" onclick="changeMainImage(this.src)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-6">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <span class="badge bg-warning text-dark mb-2"><?= htmlspecialchars($food['category_name'] ?? 'Uncategorized') ?></span>
                        <h1 class="fw-bold mb-2"><?= htmlspecialchars($food['name']) ?></h1>
                    </div>
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <button type="submit" name="toggle_favorite" class="btn btn-lg <?= $isFav ? 'btn-danger' : 'btn-outline-secondary' ?> rounded-circle">
                            <i class="fas fa-heart"></i>
                        </button>
                    </form>
                </div>

                <div class="mb-3">
                    <?= renderStars($avgRating, $totalReviews, '1.2rem') ?>
                </div>

                <div class="mb-4">
                    <?php if ($hasDiscount): ?>
                        <span class="text-decoration-line-through text-muted fs-5">$<?= number_format($food['price'], 2) ?></span>
                        <span class="fw-bold text-danger fs-3 ms-2">$<?= number_format($food['discount_price'], 2) ?></span>
                        <span class="badge bg-danger ms-2">Save <?= number_format($food['price'] - $food['discount_price'], 2) ?></span>
                    <?php else: ?>
                        <span class="fw-bold text-primary fs-3">$<?= number_format($food['price'], 2) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($food['description'])): ?>
                <div class="mb-4">
                    <h5 class="fw-bold"><i class="fas fa-info-circle text-warning me-2"></i>Description</h5>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($food['description'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($food['ingredients'])): ?>
                <div class="mb-4">
                    <h5 class="fw-bold"><i class="fas fa-list text-warning me-2"></i>Ingredients</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (explode(',', $food['ingredients']) as $ing): ?>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars(trim($ing)) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <?php if (!empty($food['preparation_time'])): ?>
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clock text-warning me-2 fs-5"></i>
                            <div>
                                <small class="text-muted d-block">Preparation</small>
                                <strong><?= htmlspecialchars($food['preparation_time']) ?></strong>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($food['calories'])): ?>
                    <div class="col-6">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-fire text-warning me-2 fs-5"></i>
                            <div>
                                <small class="text-muted d-block">Calories</small>
                                <strong><?= htmlspecialchars($food['calories']) ?> kcal</strong>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="POST">
                            <?= csrfField() ?>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <label class="fw-bold">Quantity:</label>
                                    <div class="input-group" style="width:130px;">
                                        <button type="button" class="btn btn-outline-secondary" onclick="changeQty(-1)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" name="quantity" id="qty" value="1" min="1" max="20" class="form-control text-center">
                                        <button type="button" class="btn btn-outline-secondary" onclick="changeQty(1)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" name="add_to_cart" class="btn btn-warning btn-lg rounded-pill px-4">
                                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <?php if ($food['is_featured']): ?>
                    <span class="badge bg-warning text-dark px-3 py-2"><i class="fas fa-fire me-1"></i>Featured</span>
                    <?php endif; ?>
                    <?php if ($hasDiscount): ?>
                    <span class="badge bg-danger px-3 py-2"><i class="fas fa-tag me-1"></i>On Sale</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="reviews" class="py-5 bg-light">
    <div class="container">
        <h3 class="fw-bold mb-4"><i class="fas fa-star text-warning me-2"></i>Reviews & Ratings</h3>

        <?php if ($userId && $hasOrdered && !$hasReviewed): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title fw-bold">Write a Review</h5>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Your Rating</label>
                        <div class="star-rating d-flex gap-1" id="starRating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star text-warning star-btn" data-rating="<?= $i ?>" 
                                   onclick="setRating(<?= $i ?>)" style="cursor:pointer; font-size:1.5rem;"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="ratingInput" value="5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Your Review</label>
                        <textarea name="comment" class="form-control" rows="3" required placeholder="Tell us about your experience..."></textarea>
                    </div>
                    <button type="submit" name="add_review" class="btn btn-warning rounded-pill px-4">
                        <i class="fas fa-paper-plane me-1"></i>Submit Review
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($userId && !$hasOrdered): ?>
        <div class="alert alert-info border-0 shadow-sm">
            <i class="fas fa-info-circle me-2"></i>Order this item first to leave a review.
        </div>
        <?php elseif ($hasReviewed): ?>
        <div class="alert alert-success border-0 shadow-sm">
            <i class="fas fa-check-circle me-2"></i>You have already reviewed this item.
        </div>
        <?php endif; ?>

        <?php if (empty($reviews)): ?>
        <div class="text-center py-4">
            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No reviews yet</h5>
            <p class="text-muted">Be the first to review this item!</p>
        </div>
        <?php else: ?>
        <div class="reviews-list">
            <?php foreach ($reviews as $review): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="fw-bold mb-1">
                                <i class="fas fa-user-circle me-1 text-warning"></i>
                                <?= htmlspecialchars($review['user_name'] ?? 'Anonymous') ?>
                            </h6>
                            <?= renderStars($review['rating']) ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            <?= timeAgo($review['created_at']) ?>
                        </small>
                    </div>
                    <p class="mt-2 mb-0 text-muted"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($relatedFoods)): ?>
<section class="py-5">
    <div class="container">
        <h3 class="fw-bold mb-4"><i class="fas fa-th-large text-warning me-2"></i>Related Items</h3>
        <div class="row">
            <?php foreach ($relatedFoods as $rel): ?>
            <?php
                $relImage = !empty($rel['image']) ? url('uploads/food/' . $rel['image']) : url('assets/images/placeholder-food.jpg');
                $relHasDiscount = !empty($rel['discount_price']) && $rel['discount_price'] < $rel['price'];
            ?>
            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                <a href="<?= url('customer/modules/menu/detail.php?id=' . $rel['id']) ?>" class="text-decoration-none">
                    <div class="card food-card h-100 shadow-sm border-0">
                        <div class="position-relative">
                            <img src="<?= $relImage ?>" class="card-img-top" alt="<?= htmlspecialchars($rel['name']) ?>" style="height:160px; object-fit:cover;">
                            <?php if ($relHasDiscount): ?>
                                <span class="badge bg-danger position-absolute top-0 start-0 m-2">
                                    -<?= round((1 - $rel['discount_price'] / $rel['price']) * 100) ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <span class="badge bg-light text-dark small"><?= htmlspecialchars($rel['category_name'] ?? '') ?></span>
                            <h6 class="card-title fw-bold mt-1 text-dark"><?= htmlspecialchars($rel['name']) ?></h6>
                            <?php if (!empty($rel['rating_avg'])): ?>
                                <?= renderStars($rel['rating_avg'], $rel['rating_count'] ?? 0) ?>
                            <?php endif; ?>
                            <div class="mt-2">
                                <?php if ($relHasDiscount): ?>
                                    <span class="text-decoration-line-through text-muted small">$<?= number_format($rel['price'], 2) ?></span>
                                    <span class="fw-bold text-danger ms-1">$<?= number_format($rel['discount_price'], 2) ?></span>
                                <?php else: ?>
                                    <span class="fw-bold text-primary">$<?= number_format($rel['price'], 2) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
function changeQty(delta) {
    const input = document.getElementById('qty');
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > 20) val = 20;
    input.value = val;
}
function changeMainImage(src) {
    document.getElementById('mainImage').src = src;
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('border-warning', 'border-2'));
    event.target.classList.add('border-warning', 'border-2');
}
function setRating(rating) {
    document.getElementById('ratingInput').value = rating;
    document.querySelectorAll('.star-btn').forEach((star, index) => {
        if (index < rating) {
            star.classList.add('fas');
            star.classList.remove('far');
        } else {
            star.classList.remove('fas');
            star.classList.add('far');
        }
    });
}
document.addEventListener('DOMContentLoaded', function() { setRating(5); });
</script>

<style>
.food-card { transition: transform 0.2s, box-shadow 0.2s; border-radius: 12px; overflow: hidden; }
.food-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important; }
.gallery-thumb:hover { opacity: 0.8; }
.star-rating .star-btn { transition: transform 0.1s; }
.star-rating .star-btn:hover { transform: scale(1.2); }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>