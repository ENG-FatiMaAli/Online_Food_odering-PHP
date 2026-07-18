<?php
$pageTitle = 'Reviews & Ratings';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (isPost()) {
    requireCSRF();

    if (isset($_POST['toggle_visibility'])) {
        $reviewId = (int)$_POST['review_id'];
        $current = Database::fetchAll("SELECT is_visible FROM reviews WHERE id = ?", [$reviewId]);
        $newVisibility = !empty($current) ? ($current[0]['is_visible'] ? 0 : 1) : 0;
        Database::update('reviews', ['is_visible' => $newVisibility], 'id = ?', [$reviewId]);
        logActivity(($newVisibility ? 'Showed' : 'Hidden') . ' review #' . $reviewId);
        setFlash('success', 'Review visibility updated.');
        header('Location: ' . url('admin/modules/reviews/index.php'));
        exit;
    }

    if (isset($_POST['delete_review'])) {
        $reviewId = (int)$_POST['review_id'];
        Database::delete('reviews', 'id = ?', [$reviewId]);
        logActivity('Deleted review #' . $reviewId);
        setFlash('success', 'Review deleted successfully.');
        header('Location: ' . url('admin/modules/reviews/index.php'));
        exit;
    }
}

$ratingFilter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$foodFilter = $_GET['food'] ?? '';

if ($action === 'list'):

    $stats = [
        'total' => Database::count('reviews'),
        'average' => Database::fetchAll("SELECT COALESCE(AVG(rating), 0) AS avg_rating FROM reviews")[0]['avg_rating'] ?? 0,
        'five_star' => Database::count('reviews', "rating = 5"),
        'hidden' => Database::count('reviews', "is_visible = 0"),
    ];

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = 15;

    $where = "1=1";
    $params = [];
    if ($ratingFilter >= 1 && $ratingFilter <= 5) {
        $where .= " AND r.rating = ?";
        $params[] = $ratingFilter;
    }
    if ($foodFilter) {
        $where .= " AND r.food_id = ?";
        $params[] = (int)$foodFilter;
    }

    $total = Database::count('reviews r', $where, $params);
    $reviews = Database::fetchAll(
        "SELECT r.*, 
                f.name AS food_name, f.image AS food_image,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM reviews r
         LEFT JOIN food_items f ON r.food_id = f.id
         LEFT JOIN users u ON r.user_id = u.id
         WHERE $where
         ORDER BY r.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, ($page - 1) * $perPage])
    );

    $foodItems = Database::fetchAll("SELECT id, name FROM food_items ORDER BY name ASC");

    function renderStars($rating) {
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $html .= '<i class="fas fa-star text-warning"></i>';
            } else {
                $html .= '<i class="far fa-star text-warning"></i>';
            }
        }
        return $html;
    }
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-star me-2"></i>Reviews & Ratings</h4>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#filterModal">
        <i class="fas fa-filter me-1"></i>Filters
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-comments fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['total'] ?></h3>
                <small>Total Reviews</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-star fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= number_format($stats['average'], 1) ?></h3>
                <small>Average Rating</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-award fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['five_star'] ?></h3>
                <small>5-Star Reviews</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-secondary text-white">
            <div class="card-body text-center">
                <i class="fas fa-eye-slash fa-2x mb-2 opacity-75"></i>
                <h3 class="mb-0"><?= $stats['hidden'] ?></h3>
                <small>Hidden Reviews</small>
            </div>
        </div>
    </div>
</div>

<?php if ($ratingFilter || $foodFilter): ?>
<div class="mb-3">
    <span class="text-muted">Active Filters:</span>
    <?php if ($ratingFilter): ?>
        <span class="badge bg-warning text-dark me-1">
            <?= $ratingFilter ?>-Star
            <a href="?rating=&food=<?= urlencode($foodFilter) ?>" class="text-dark ms-1">&times;</a>
        </span>
    <?php endif; ?>
    <?php if ($foodFilter): ?>
        <?php $fName = Database::fetchAll("SELECT name FROM food_items WHERE id = ?", [(int)$foodFilter]); ?>
        <span class="badge bg-info me-1">
            <?= htmlspecialchars($fName[0]['name'] ?? 'Food Item') ?>
            <a href="?rating=<?= $ratingFilter ?>&food=" class="text-white ms-1">&times;</a>
        </span>
    <?php endif; ?>
    <a href="<?= url('admin/modules/reviews/index.php') ?>" class="btn btn-sm btn-outline-secondary ms-2">Clear All</a>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Food Item</th>
                        <th>User</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Visible</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reviews)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>No reviews found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reviews as $r): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($r['food_image']): ?>
                                            <img src="<?= url('uploads/food/' . $r['food_image']) ?>" alt="" class="rounded me-2" style="width:35px;height:35px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" style="width:35px;height:35px;">
                                                <i class="fas fa-utensils text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($r['food_name'] ?? 'N/A') ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($r['user_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?= renderStars($r['rating']) ?>
                                    <span class="ms-1 fw-bold"><?= $r['rating'] ?></span>
                                </td>
                                <td>
                                    <?php if ($r['comment']): ?>
                                        <span class="text-truncate d-inline-block" style="max-width:200px;" title="<?= htmlspecialchars($r['comment']) ?>">
                                            <?= htmlspecialchars($r['comment']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">No comment</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                        <button type="submit" name="toggle_visibility" class="btn btn-sm btn-<?= $r['is_visible'] ? 'success' : 'secondary' ?> btn-sm" title="<?= $r['is_visible'] ? 'Currently visible - click to hide' : 'Currently hidden - click to show' ?>">
                                            <i class="fas fa-<?= $r['is_visible'] ? 'eye' : 'eye-slash' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                                <td><small><?= timeAgo($r['created_at']) ?></small></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewModal<?= $r['id'] ?>" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                            <button type="submit" name="delete_review" class="btn btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- View Detail Modal -->
                                    <div class="modal fade" id="viewModal<?= $r['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="fas fa-comment-dots me-2"></i>Review Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row g-4">
                                                        <div class="col-md-4 text-center">
                                                            <?php if ($r['food_image']): ?>
                                                                <img src="<?= url('uploads/food/' . $r['food_image']) ?>" alt="<?= htmlspecialchars($r['food_name']) ?>" class="img-fluid rounded mb-3" style="max-height:200px;object-fit:cover;">
                                                            <?php else: ?>
                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" style="height:200px;">
                                                                    <i class="fas fa-utensils fa-3x text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <h6><?= htmlspecialchars($r['food_name'] ?? 'N/A') ?></h6>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <table class="table table-borderless">
                                                                <tr>
                                                                    <td class="text-muted" style="width:120px;">User</td>
                                                                    <td><strong><?= htmlspecialchars($r['user_name'] ?? 'N/A') ?></strong></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="text-muted">Rating</td>
                                                                    <td>
                                                                        <?= renderStars($r['rating']) ?>
                                                                        <span class="ms-2 fw-bold fs-5"><?= $r['rating'] ?>/5</span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="text-muted">Review</td>
                                                                    <td><?= nl2br(htmlspecialchars($r['comment'] ?? 'No comment')) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="text-muted">Visibility</td>
                                                                    <td>
                                                                        <?php if ($r['is_visible']): ?>
                                                                            <span class="badge bg-success"><i class="fas fa-eye me-1"></i>Visible</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-secondary"><i class="fas fa-eye-slash me-1"></i>Hidden</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="text-muted">Date</td>
                                                                    <td><?= date('M d, Y \a\t g:i A', strtotime($r['created_at'])) ?></td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST" class="me-auto">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                                        <button type="submit" name="toggle_visibility" class="btn btn-<?= $r['is_visible'] ? 'warning' : 'success' ?>">
                                                            <i class="fas fa-<?= $r['is_visible'] ? 'eye-slash' : 'eye' ?> me-1"></i>
                                                            <?= $r['is_visible'] ? 'Hide Review' : 'Show Review' ?>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
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
$paginationUrl = url('admin/modules/reviews/index.php') . '?rating=' . $ratingFilter . '&food=' . urlencode($foodFilter);
$totalPages = ceil($total / $perPage);
echo renderPagination(['page' => $page, 'pages' => $totalPages, 'total' => $total, 'offset' => ($page - 1) * $perPage, 'perPage' => $perPage], $paginationUrl);
?>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-filter me-2"></i>Filter Reviews</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="list">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Rating</label>
                        <select name="rating" class="form-select">
                            <option value="">All Ratings</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>" <?= $ratingFilter === $i ? 'selected' : '' ?>>
                                    <?= str_repeat('&#9733;', $i) ?> (<?= $i ?>-Star)
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Food Item</label>
                        <select name="food" class="form-select">
                            <option value="">All Food Items</option>
                            <?php foreach ($foodItems as $fi): ?>
                                <option value="<?= $fi['id'] ?>" <?= (int)$foodFilter === (int)$fi['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fi['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?= url('admin/modules/reviews/index.php') ?>" class="btn btn-outline-secondary">Clear Filters</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
    <div class="alert alert-danger">Invalid action.</div>
    <?php header('Location: ' . url('admin/modules/reviews/index.php')); exit; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
