<?php
$pageTitle = 'Food Categories';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';
$CAT_UPLOADS = __DIR__ . '/../../../uploads/food/';

if (!is_dir($CAT_UPLOADS)) {
    mkdir($CAT_UPLOADS, 0755, true);
}

if (isPost() && !verifyCSRF()) {
    setFlash('error', 'Invalid security token. Please try again.');
    redirect(url('admin/modules/categories/index.php'));
}

if (isPost()) {
    $actionPost = $_POST['action'] ?? '';

    // ── CREATE ──
    if ($actionPost === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sort_order  = (int)($_POST['sort_order'] ?? 0);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            setFlash('error', 'Category name is required.');
            redirect(url('admin/modules/categories/index.php?action=add'));
        }

        $slug = slugify($name);
        $existing = Database::fetch("SELECT id FROM food_categories WHERE slug = ?", [$slug]);
        if ($existing) {
            $slug = $slug . '-' . time();
        }

        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $uploaded = uploadFile($_FILES['image'], $CAT_UPLOADS);
            if ($uploaded) {
                $image = $uploaded;
            } else {
                setFlash('error', 'Invalid image file. Allowed: jpg, jpeg, png, gif, webp.');
                redirect(url('admin/modules/categories/index.php?action=add'));
            }
        }

        Database::insert('food_categories', [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'image'       => $image,
            'is_active'   => $is_active,
            'sort_order'  => $sort_order,
        ]);

        logActivity('category_created', "Created category: {$name}");
        setFlash('success', 'Category created successfully.');
        redirect(url('admin/modules/categories/index.php'));
    }

    // ── UPDATE ──
    if ($actionPost === 'edit') {
        $id          = (int)($_POST['category_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sort_order  = (int)($_POST['sort_order'] ?? 0);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if ($id < 1 || $name === '') {
            setFlash('error', 'Category name is required.');
            redirect(url('admin/modules/categories/index.php?action=edit&id=' . $id));
        }

        $slug = slugify($name);
        $existing = Database::fetch("SELECT id FROM food_categories WHERE slug = ? AND id != ?", [$slug, $id]);
        if ($existing) {
            $slug = $slug . '-' . time();
        }

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'is_active'   => $is_active,
            'sort_order'  => $sort_order,
        ];

        if (!empty($_FILES['image']['name'])) {
            $uploaded = uploadFile($_FILES['image'], $CAT_UPLOADS);
            if ($uploaded) {
                $oldCat = Database::fetch("SELECT image FROM food_categories WHERE id = ?", [$id]);
                if ($oldCat && !empty($oldCat['image']) && file_exists($CAT_UPLOADS . $oldCat['image'])) {
                    unlink($CAT_UPLOADS . $oldCat['image']);
                }
                $data['image'] = $uploaded;
            } else {
                setFlash('error', 'Invalid image file. Allowed: jpg, jpeg, png, gif, webp.');
                redirect(url('admin/modules/categories/index.php?action=edit&id=' . $id));
            }
        }

        Database::update('food_categories', $data, 'id = ?', [$id]);

        logActivity('category_updated', "Updated category ID: {$id}");
        setFlash('success', 'Category updated successfully.');
        redirect(url('admin/modules/categories/index.php'));
    }

    // ── DELETE ──
    if ($actionPost === 'delete') {
        $id = (int)($_POST['category_id'] ?? 0);

        $category = Database::fetch("SELECT id, name, image FROM food_categories WHERE id = ?", [$id]);
        if (!$category) {
            setFlash('error', 'Category not found.');
            redirect(url('admin/modules/categories/index.php'));
        }

        $itemCount = Database::count('food_items', 'category_id = ?', [$id]);
        if ($itemCount > 0) {
            setFlash('error', "Cannot delete \"{$category['name']}\" — it has {$itemCount} food item(s). Remove or reassign them first.");
            redirect(url('admin/modules/categories/index.php'));
        }

        if (!empty($category['image']) && file_exists($CAT_UPLOADS . $category['image'])) {
            unlink($CAT_UPLOADS . $category['image']);
        }

        Database::delete('food_categories', 'id = ?', [$id]);
        logActivity('category_deleted', "Deleted category: {$category['name']}");
        setFlash('success', 'Category deleted successfully.');
        redirect(url('admin/modules/categories/index.php'));
    }

    // ── TOGGLE STATUS ──
    if ($actionPost === 'toggle') {
        $id       = (int)($_POST['category_id'] ?? 0);
        $category = Database::fetch("SELECT id, is_active, name FROM food_categories WHERE id = ?", [$id]);
        if ($category) {
            $newStatus = $category['is_active'] ? 0 : 1;
            Database::update('food_categories', ['is_active' => $newStatus], 'id = ?', [$id]);
            $label = $newStatus ? 'activated' : 'deactivated';
            logActivity('category_status_toggled', "Category \"{$category['name']}\" {$label}");
            setFlash('success', "Category {$label} successfully.");
        } else {
            setFlash('error', 'Category not found.');
        }
        redirect(url('admin/modules/categories/index.php'));
    }
}

// ─── Add / Edit Form ──────────────────────────────────────────
if ($action === 'add' || $action === 'edit'):
    $editCategory = null;
    $isEdit = $action === 'edit';

    if ($isEdit) {
        $editId = (int)($_GET['id'] ?? 0);
        $editCategory = Database::fetch("SELECT * FROM food_categories WHERE id = ?", [$editId]);
        if (!$editCategory) {
            setFlash('error', 'Category not found.');
            redirect(url('admin/modules/categories/index.php'));
        }
    }

    $formTitle = $isEdit ? 'Edit Category' : 'Add New Category';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><?= $formTitle ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('admin/modules/categories/index.php') ?>" class="text-decoration-none">Categories</a></li>
                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Add' ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/categories/index.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<!-- Form Card -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= url('admin/modules/categories/index.php') ?>" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Name -->
                <div class="col-md-8">
                    <label for="name" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= sanitize($editCategory['name'] ?? '') ?>"
                           placeholder="e.g. Burgers, Pizzas, Drinks" required
                           oninput="document.getElementById('slugPreview').textContent = slugify(this.value)">
                    <small class="text-muted">Slug: <span id="slugPreview"><?= sanitize($editCategory['slug'] ?? '') ?></span></small>
                </div>

                <!-- Sort Order -->
                <div class="col-md-4">
                    <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order"
                           value="<?= (int)($editCategory['sort_order'] ?? 0) ?>" min="0">
                    <small class="text-muted">Lower numbers appear first</small>
                </div>

                <!-- Description -->
                <div class="col-12">
                    <label for="description" class="form-label fw-semibold">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"
                              placeholder="Brief description of this category..."><?= sanitize($editCategory['description'] ?? '') ?></textarea>
                </div>

                <!-- Image Upload -->
                <div class="col-md-6">
                    <label for="image" class="form-label fw-semibold">Category Image</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*"
                           onchange="previewImage(this, 'imagePreview')">
                    <small class="text-muted">Allowed: JPG, PNG, GIF, WebP. Recommended: 400x300px</small>
                    <?php if ($isEdit && !empty($editCategory['image'])): ?>
                        <div class="mt-2">
                            <img src="<?= url('uploads/food/' . sanitize($editCategory['image'])) ?>"
                                 alt="Current image" class="rounded" style="max-height:80px">
                            <small class="text-muted d-block mt-1">Current image</small>
                        </div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <img id="imagePreview" src="#" alt="Preview" class="rounded d-none" style="max-height:80px">
                    </div>
                </div>

                <!-- Active Toggle -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold d-block">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                               value="1" <?= ($editCategory['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active (visible on menu)</label>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= url('admin/modules/categories/index.php') ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Update Category' : 'Create Category' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function slugify(text) {
    return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}
function previewImage(input, previewId) {
    var preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.classList.add('d-none');
    }
}
</script>

<?php else: // ─── List View ──────────────────────────────────────

$search = trim($_GET['search'] ?? '');
$viewMode = $_GET['view'] ?? 'grid';
$filterStatus = $_GET['status'] ?? '';

$where  = '1';
$params = [];

if ($search !== '') {
    $where .= " AND (fc.name LIKE ? OR fc.description LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like]);
}

if ($filterStatus !== '' && in_array($filterStatus, ['0', '1'])) {
    $where .= " AND fc.is_active = ?";
    $params[] = (int)$filterStatus;
}

$pagination = paginate('food_categories fc', $where, $params, 12);

$categories = Database::fetchAll(
    "SELECT fc.*,
            (SELECT COUNT(*) FROM food_items fi WHERE fi.category_id = fc.id) AS item_count
     FROM food_categories fc
     WHERE {$where}
     ORDER BY fc.sort_order ASC, fc.name ASC
     LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
    $params
);

$queryParams = array_filter(['search' => $search ?: null, 'view' => $viewMode !== 'grid' ? $viewMode : null, 'status' => $filterStatus !== '' ? $filterStatus : null]);
$baseUrl = url('admin/modules/categories/index.php') . '?' . http_build_query($queryParams);

$totalCategories = Database::count('food_categories');
$activeCategories = Database::count('food_categories', 'is_active = 1');
$totalItems = Database::count('food_items');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Food Categories</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Categories</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/modules/categories/index.php?view=grid') ?>"
           class="btn btn-sm <?= $viewMode === 'grid' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-th-large"></i>
        </a>
        <a href="<?= url('admin/modules/categories/index.php?view=table') ?>"
           class="btn btn-sm <?= $viewMode === 'table' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="fas fa-list"></i>
        </a>
        <a href="<?= url('admin/modules/categories/index.php?action=add') ?>" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add Category
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,107,53,0.1)">
                        <i class="fas fa-tags" style="color:var(--primary);font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Categories</div>
                        <div class="fw-bold fs-5"><?= $totalCategories ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(40,167,69,0.1)">
                        <i class="fas fa-check-circle" style="color:#28a745;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Active</div>
                        <div class="fw-bold fs-5"><?= $activeCategories ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(220,53,69,0.1)">
                        <i class="fas fa-ban" style="color:#dc3545;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Inactive</div>
                        <div class="fw-bold fs-5"><?= $totalCategories - $activeCategories ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,193,7,0.1)">
                        <i class="fas fa-utensils" style="color:#ffc107;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Food Items</div>
                        <div class="fw-bold fs-5"><?= $totalItems ?></div>
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
            <input type="hidden" name="view" value="<?= sanitize($viewMode) ?>">
            <div class="col-md-4">
                <label for="search" class="form-label fw-semibold small">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Search categories..." value="<?= sanitize($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label for="filterStatus" class="form-label fw-semibold small">Status</label>
                <select class="form-select" id="filterStatus" name="status">
                    <option value="">All Status</option>
                    <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('admin/modules/categories/index.php?view=' . $viewMode) ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($viewMode === 'table'): ?>
<!-- Table View -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:50px">#</th>
                        <th style="width:50px"></th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Items</th>
                        <th>Sort</th>
                        <th>Status</th>
                        <th class="text-end pe-3" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-tags fa-2x mb-3 d-block opacity-50"></i>
                                No categories found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $i => $cat): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $pagination['offset'] + $i + 1 ?></td>
                                <td>
                                    <?php if (!empty($cat['image'])): ?>
                                        <img src="<?= url('uploads/food/' . sanitize($cat['image'])) ?>"
                                             alt="" class="rounded" width="40" height="40" style="object-fit:cover">
                                    <?php else: ?>
                                        <div class="rounded d-flex align-items-center justify-content-center"
                                             style="width:40px;height:40px;background:rgba(255,107,53,0.1)">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= sanitize($cat['name']) ?></span>
                                </td>
                                <td><code class="small"><?= sanitize($cat['slug']) ?></code></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">
                                        <?= (int)$cat['item_count'] ?> items
                                    </span>
                                </td>
                                <td class="text-muted"><?= (int)$cat['sort_order'] ?></td>
                                <td>
                                    <?php if ($cat['is_active']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1">
                                            <i class="fas fa-circle me-1" style="font-size:0.45rem;vertical-align:middle"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $cat['is_active'] ? 'warning' : 'success' ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $cat['is_active'] ? 'ban' : 'check-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <a href="<?= url('admin/modules/categories/index.php?action=edit&id=' . $cat['id']) ?>"
                                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form method="POST" class="d-inline" id="deleteForm_<?= $cat['id'] ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="tooltip" title="Delete"
                                                    onclick="deleteCategory(<?= $cat['id'] ?>, '<?= sanitize(addslashes($cat['name'])) ?>')">
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

    <?php if ($pagination['total'] > $pagination['perPage']): ?>
        <div class="card-footer bg-white border-top-0 pt-0 pb-3">
            <div class="d-flex justify-content-between align-items-center px-3">
                <small class="text-muted">
                    Showing <?= $pagination['offset'] + 1 ?>-<?= min($pagination['offset'] + $pagination['perPage'], $pagination['total']) ?>
                    of <?= $pagination['total'] ?> categories
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

<?php else: ?>
<!-- Grid View -->
<?php if (empty($categories)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-tags fa-2x mb-3 d-block opacity-50"></i>
            No categories found.
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($categories as $cat): ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100 category-card">
                    <div class="position-relative">
                        <?php if (!empty($cat['image'])): ?>
                            <img src="<?= url('uploads/food/' . sanitize($cat['image'])) ?>"
                                 class="card-img-top" alt="<?= sanitize($cat['name']) ?>"
                                 style="height:180px;object-fit:cover">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center bg-light" style="height:180px">
                                <i class="fas fa-image fa-3x text-muted opacity-25"></i>
                            </div>
                        <?php endif; ?>
                        <?php if (!$cat['is_active']): ?>
                            <span class="badge bg-danger position-absolute top-0 end-0 m-2">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h6 class="card-title fw-bold mb-1"><?= sanitize($cat['name']) ?></h6>
                        <p class="text-muted small mb-2">
                            <?= $cat['description'] ? sanitize(mb_strimwidth($cat['description'], 0, 80, '...')) : '<em>No description</em>' ?>
                        </p>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1">
                                <i class="fas fa-utensils me-1"></i> <?= (int)$cat['item_count'] ?> items
                            </span>
                            <small class="text-muted">Order: <?= (int)$cat['sort_order'] ?></small>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0 pb-3 pt-0">
                        <div class="d-flex gap-1">
                            <form method="POST" class="d-inline flex-fill">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $cat['is_active'] ? 'warning' : 'success' ?> w-100"
                                        title="<?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <i class="fas fa-<?= $cat['is_active'] ? 'ban' : 'check-circle' ?> me-1"></i>
                                    <?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <a href="<?= url('admin/modules/categories/index.php?action=edit&id=' . $cat['id']) ?>"
                               class="btn btn-sm btn-outline-primary flex-fill" title="Edit">
                                <i class="fas fa-pen me-1"></i> Edit
                            </a>
                            <form method="POST" class="d-inline flex-fill" id="deleteForm_<?= $cat['id'] ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger w-100" title="Delete"
                                        onclick="deleteCategory(<?= $cat['id'] ?>, '<?= sanitize(addslashes($cat['name'])) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($pagination['total'] > $pagination['perPage']): ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <small class="text-muted">
                Showing <?= $pagination['offset'] + 1 ?>-<?= min($pagination['offset'] + $pagination['perPage'], $pagination['total']) ?>
                of <?= $pagination['total'] ?> categories
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
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<script>
function deleteCategory(id, name) {
    Swal.fire({
        title: 'Delete "' + name + '"?',
        text: 'This category will be permanently removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById('deleteForm_' + id).submit();
        }
    });
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
