<?php
$pageTitle = 'Menu Management';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';
$MENU_UPLOADS = __DIR__ . '/../../../uploads/food/';

if (!is_dir($MENU_UPLOADS)) {
    mkdir($MENU_UPLOADS, 0755, true);
}

if (isPost() && !verifyCSRF()) {
    setFlash('error', 'Invalid security token. Please try again.');
    redirect(url('admin/modules/menu/index.php'));
}

if (isPost()) {
    $actionPost = $_POST['action'] ?? '';

    // ── CREATE ──
    if ($actionPost === 'add') {
        $name              = trim($_POST['name'] ?? '');
        $category_id       = (int)($_POST['category_id'] ?? 0);
        $description       = trim($_POST['description'] ?? '');
        $ingredients       = trim($_POST['ingredients'] ?? '');
        $price             = (float)($_POST['price'] ?? 0);
        $discount_price    = trim($_POST['discount_price'] ?? '');
        $preparation_time  = (int)($_POST['preparation_time'] ?? 0);
        $calories          = (int)($_POST['calories'] ?? 0);
        $is_available      = isset($_POST['is_available']) ? 1 : 0;
        $is_featured       = isset($_POST['is_featured']) ? 1 : 0;

        if ($name === '' || $category_id < 1 || $price <= 0) {
            setFlash('error', 'Please fill in all required fields (Name, Category, Price).');
            redirect(url('admin/modules/menu/index.php?action=add'));
        }

        $slug = slugify($name);
        $existing = Database::fetch("SELECT id FROM food_items WHERE slug = ?", [$slug]);
        if ($existing) {
            $slug = $slug . '-' . time();
        }

        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $uploaded = uploadFile($_FILES['image'], $MENU_UPLOADS);
            if ($uploaded) {
                $image = $uploaded;
            } else {
                setFlash('error', 'Invalid main image file. Allowed: jpg, jpeg, png, gif, webp.');
                redirect(url('admin/modules/menu/index.php?action=add'));
            }
        }

        $foodId = Database::insert('food_items', [
            'category_id'      => $category_id,
            'name'             => $name,
            'slug'             => $slug,
            'description'      => $description,
            'ingredients'      => $ingredients,
            'price'            => $price,
            'discount_price'   => $discount_price !== '' ? (float)$discount_price : null,
            'image'            => $image,
            'is_available'     => $is_available,
            'is_featured'      => $is_featured,
            'preparation_time' => $preparation_time,
            'calories'         => $calories,
        ]);

        // Handle multiple additional images
        if (!empty($_FILES['gallery']['name'][0])) {
            $sortOrder = 0;
            foreach ($_FILES['gallery']['name'] as $idx => $fileName) {
                $galleryFile = [
                    'name'     => $_FILES['gallery']['name'][$idx],
                    'type'     => $_FILES['gallery']['type'][$idx],
                    'tmp_name' => $_FILES['gallery']['tmp_name'][$idx],
                    'error'    => $_FILES['gallery']['error'][$idx],
                    'size'     => $_FILES['gallery']['size'][$idx],
                ];
                if ($galleryFile['error'] === UPLOAD_ERR_OK) {
                    $uploaded = uploadFile($galleryFile, $MENU_UPLOADS);
                    if ($uploaded) {
                        Database::insert('food_images', [
                            'food_id'    => $foodId,
                            'image'      => $uploaded,
                            'sort_order' => $sortOrder++,
                        ]);
                    }
                }
            }
        }

        logActivity('food_item_created', "Created food item: {$name}");
        setFlash('success', 'Menu item created successfully.');
        redirect(url('admin/modules/menu/index.php'));
    }

    // ── UPDATE ──
    if ($actionPost === 'edit') {
        $id                = (int)($_POST['food_id'] ?? 0);
        $name              = trim($_POST['name'] ?? '');
        $category_id       = (int)($_POST['category_id'] ?? 0);
        $description       = trim($_POST['description'] ?? '');
        $ingredients       = trim($_POST['ingredients'] ?? '');
        $price             = (float)($_POST['price'] ?? 0);
        $discount_price    = trim($_POST['discount_price'] ?? '');
        $preparation_time  = (int)($_POST['preparation_time'] ?? 0);
        $calories          = (int)($_POST['calories'] ?? 0);
        $is_available      = isset($_POST['is_available']) ? 1 : 0;
        $is_featured       = isset($_POST['is_featured']) ? 1 : 0;

        if ($id < 1 || $name === '' || $category_id < 1 || $price <= 0) {
            setFlash('error', 'Please fill in all required fields (Name, Category, Price).');
            redirect(url('admin/modules/menu/index.php?action=edit&id=' . $id));
        }

        $slug = slugify($name);
        $existing = Database::fetch("SELECT id FROM food_items WHERE slug = ? AND id != ?", [$slug, $id]);
        if ($existing) {
            $slug = $slug . '-' . time();
        }

        $data = [
            'category_id'      => $category_id,
            'name'             => $name,
            'slug'             => $slug,
            'description'      => $description,
            'ingredients'      => $ingredients,
            'price'            => $price,
            'discount_price'   => $discount_price !== '' ? (float)$discount_price : null,
            'is_available'     => $is_available,
            'is_featured'      => $is_featured,
            'preparation_time' => $preparation_time,
            'calories'         => $calories,
        ];

        if (!empty($_FILES['image']['name'])) {
            $uploaded = uploadFile($_FILES['image'], $MENU_UPLOADS);
            if ($uploaded) {
                $oldItem = Database::fetch("SELECT image FROM food_items WHERE id = ?", [$id]);
                if ($oldItem && !empty($oldItem['image']) && file_exists($MENU_UPLOADS . $oldItem['image'])) {
                    unlink($MENU_UPLOADS . $oldItem['image']);
                }
                $data['image'] = $uploaded;
            } else {
                setFlash('error', 'Invalid main image file. Allowed: jpg, jpeg, png, gif, webp.');
                redirect(url('admin/modules/menu/index.php?action=edit&id=' . $id));
            }
        }

        Database::update('food_items', $data, 'id = ?', [$id]);

        // Handle additional gallery images
        if (!empty($_FILES['gallery']['name'][0])) {
            $maxOrder = Database::fetch("SELECT COALESCE(MAX(sort_order), -1) AS max_ord FROM food_images WHERE food_id = ?", [$id]);
            $sortOrder = ((int)($maxOrder['max_ord'] ?? -1)) + 1;
            foreach ($_FILES['gallery']['name'] as $idx => $fileName) {
                $galleryFile = [
                    'name'     => $_FILES['gallery']['name'][$idx],
                    'type'     => $_FILES['gallery']['type'][$idx],
                    'tmp_name' => $_FILES['gallery']['tmp_name'][$idx],
                    'error'    => $_FILES['gallery']['error'][$idx],
                    'size'     => $_FILES['gallery']['size'][$idx],
                ];
                if ($galleryFile['error'] === UPLOAD_ERR_OK) {
                    $uploaded = uploadFile($galleryFile, $MENU_UPLOADS);
                    if ($uploaded) {
                        Database::insert('food_images', [
                            'food_id'    => $id,
                            'image'      => $uploaded,
                            'sort_order' => $sortOrder++,
                        ]);
                    }
                }
            }
        }

        // Delete specific gallery images
        if (!empty($_POST['delete_gallery_ids'])) {
            $deleteIds = array_map('intval', $_POST['delete_gallery_ids']);
            foreach ($deleteIds as $delId) {
                $delImage = Database::fetch("SELECT image FROM food_images WHERE id = ? AND food_id = ?", [$delId, $id]);
                if ($delImage) {
                    if (!empty($delImage['image']) && file_exists($MENU_UPLOADS . $delImage['image'])) {
                        unlink($MENU_UPLOADS . $delImage['image']);
                    }
                    Database::delete('food_images', 'id = ?', [$delId]);
                }
            }
        }

        logActivity('food_item_updated', "Updated food item ID: {$id}");
        setFlash('success', 'Menu item updated successfully.');
        redirect(url('admin/modules/menu/index.php'));
    }

    // ── DELETE ──
    if ($actionPost === 'delete') {
        $id = (int)($_POST['food_id'] ?? 0);

        $item = Database::fetch("SELECT id, name, image FROM food_items WHERE id = ?", [$id]);
        if (!$item) {
            setFlash('error', 'Menu item not found.');
            redirect(url('admin/modules/menu/index.php'));
        }

        // Delete main image
        if (!empty($item['image']) && file_exists($MENU_UPLOADS . $item['image'])) {
            unlink($MENU_UPLOADS . $item['image']);
        }

        // Delete gallery images
        $galleryImages = Database::fetchAll("SELECT image FROM food_images WHERE food_id = ?", [$id]);
        foreach ($galleryImages as $gi) {
            if (!empty($gi['image']) && file_exists($MENU_UPLOADS . $gi['image'])) {
                unlink($MENU_UPLOADS . $gi['image']);
            }
        }
        Database::delete('food_images', 'food_id = ?', [$id]);
        Database::delete('food_items', 'id = ?', [$id]);

        logActivity('food_item_deleted', "Deleted food item: {$item['name']}");
        setFlash('success', 'Menu item deleted successfully.');
        redirect(url('admin/modules/menu/index.php'));
    }

    // ── TOGGLE AVAILABILITY ──
    if ($actionPost === 'toggle_availability') {
        $id   = (int)($_POST['food_id'] ?? 0);
        $item = Database::fetch("SELECT id, is_available, name FROM food_items WHERE id = ?", [$id]);
        if ($item) {
            $new = $item['is_available'] ? 0 : 1;
            Database::update('food_items', ['is_available' => $new], 'id = ?', [$id]);
            $label = $new ? 'available' : 'unavailable';
            logActivity('food_item_availability_toggled', "\"{$item['name']}\" set as {$label}");
            setFlash('success', "Item marked as {$label}.");
        } else {
            setFlash('error', 'Menu item not found.');
        }
        redirect(url('admin/modules/menu/index.php'));
    }

    // ── TOGGLE FEATURED ──
    if ($actionPost === 'toggle_featured') {
        $id   = (int)($_POST['food_id'] ?? 0);
        $item = Database::fetch("SELECT id, is_featured, name FROM food_items WHERE id = ?", [$id]);
        if ($item) {
            $new = $item['is_featured'] ? 0 : 1;
            Database::update('food_items', ['is_featured' => $new], 'id = ?', [$id]);
            $label = $new ? 'featured' : 'unfeatured';
            logActivity('food_item_featured_toggled', "\"{$item['name']}\" set as {$label}");
            setFlash('success', "Item marked as {$label}.");
        } else {
            setFlash('error', 'Menu item not found.');
        }
        redirect(url('admin/modules/menu/index.php'));
    }
}

// ─── Add / Edit Form ──────────────────────────────────────────
if ($action === 'add' || $action === 'edit'):
    $editItem = null;
    $editGallery = [];
    $isEdit = $action === 'edit';

    $categories = Database::fetchAll("SELECT id, name FROM food_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");

    if ($isEdit) {
        $editId = (int)($_GET['id'] ?? 0);
        $editItem = Database::fetch("SELECT * FROM food_items WHERE id = ?", [$editId]);
        if (!$editItem) {
            setFlash('error', 'Menu item not found.');
            redirect(url('admin/modules/menu/index.php'));
        }
        $editGallery = Database::fetchAll("SELECT * FROM food_images WHERE food_id = ? ORDER BY sort_order ASC", [$editId]);
    }

    $formTitle = $isEdit ? 'Edit Menu Item' : 'Add New Menu Item';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><?= $formTitle ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('admin/modules/menu/index.php') ?>" class="text-decoration-none">Menu</a></li>
                <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Add' ?></li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/menu/index.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<form method="POST" action="<?= url('admin/modules/menu/index.php') ?>" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="food_id" value="<?= $editItem['id'] ?>">
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Column: Main Details -->
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-info-circle me-2" style="color:var(--primary)"></i>Basic Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?= sanitize($editItem['name'] ?? '') ?>"
                                   placeholder="e.g. Classic Beef Burger" required>
                        </div>
                        <div class="col-md-4">
                            <label for="category_id" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"
                                        <?= ((int)($editItem['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Describe this menu item..."><?= sanitize($editItem['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="ingredients" class="form-label fw-semibold">Ingredients</label>
                            <textarea class="form-control" id="ingredients" name="ingredients" rows="2"
                                      placeholder="List ingredients separated by commas..."><?= sanitize($editItem['ingredients'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-dollar-sign me-2" style="color:var(--primary)"></i>Pricing
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="price" class="form-label fw-semibold">Price <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">$</span>
                                <input type="number" class="form-control" id="price" name="price"
                                       value="<?= $editItem['price'] ?? '' ?>"
                                       placeholder="0.00" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="discount_price" class="form-label fw-semibold">Discount Price <small class="text-muted">(optional)</small></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">$</span>
                                <input type="number" class="form-control" id="discount_price" name="discount_price"
                                       value="<?= $editItem['discount_price'] ?? '' ?>"
                                       placeholder="0.00" step="0.01" min="0">
                            </div>
                            <?php if ($isEdit && !empty($editItem['discount_price'])): ?>
                                <small class="text-success fw-semibold">
                                    <?= number_format(((float)$editItem['price'] - (float)$editItem['discount_price']) / (float)$editItem['price'] * 100, 0) ?>% off
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Details -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-sliders-h me-2" style="color:var(--primary)"></i>Additional Details
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="preparation_time" class="form-label fw-semibold">Preparation Time <small class="text-muted">(minutes)</small></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-clock"></i></span>
                                <input type="number" class="form-control" id="preparation_time" name="preparation_time"
                                       value="<?= (int)($editItem['preparation_time'] ?? 0) ?>"
                                       placeholder="e.g. 15" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="calories" class="form-label fw-semibold">Calories <small class="text-muted">(kcal)</small></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-fire"></i></span>
                                <input type="number" class="form-control" id="calories" name="calories"
                                       value="<?= (int)($editItem['calories'] ?? 0) ?>"
                                       placeholder="e.g. 450" min="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isEdit && !empty($editGallery)): ?>
            <!-- Existing Gallery Images -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-images me-2" style="color:var(--primary)"></i>Gallery Images
                        <small class="text-muted fw-normal">(<?= count($editGallery) ?> images)</small>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($editGallery as $gi): ?>
                            <div class="col-md-3 col-4" id="galleryItem_<?= $gi['id'] ?>">
                                <div class="position-relative border rounded overflow-hidden">
                                    <img src="<?= url('uploads/food/' . sanitize($gi['image'])) ?>"
                                         alt="" class="w-100" style="height:100px;object-fit:cover">
                                    <div class="form-check m-2 position-absolute bottom-0 start-0 bg-white rounded px-1 py-0"
                                         style="opacity:0.9">
                                        <input class="form-check-input" type="checkbox"
                                               name="delete_gallery_ids[]" value="<?= $gi['id'] ?>"
                                               id="delGallery_<?= $gi['id'] ?>">
                                        <label class="form-check-label small text-danger" for="delGallery_<?= $gi['id'] ?>">Delete</label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Image & Status -->
        <div class="col-lg-4">
            <!-- Main Image -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-camera me-2" style="color:var(--primary)"></i>Main Image
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($isEdit && !empty($editItem['image'])): ?>
                        <div class="text-center mb-3">
                            <img src="<?= url('uploads/food/' . sanitize($editItem['image'])) ?>"
                                 alt="Current image" class="rounded" id="mainImagePreview"
                                 style="max-width:100%;max-height:180px;object-fit:cover">
                        </div>
                    <?php else: ?>
                        <div class="text-center mb-3">
                            <img id="mainImagePreview" src="#" alt="Preview" class="rounded d-none"
                                 style="max-width:100%;max-height:180px;object-fit:cover">
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" name="image" accept="image/*"
                           onchange="previewMainImage(this)">
                    <small class="text-muted">JPG, PNG, GIF, WebP</small>
                </div>
            </div>

            <!-- Gallery Upload -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-layer-group me-2" style="color:var(--primary)"></i>Additional Images
                    </h6>
                </div>
                <div class="card-body">
                    <input type="file" class="form-control" name="gallery[]" accept="image/*" multiple>
                    <small class="text-muted">Select multiple images</small>
                </div>
            </div>

            <!-- Status & Options -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-toggle-on me-2" style="color:var(--primary)"></i>Status & Options
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="is_available" name="is_available"
                               value="1" <?= ($editItem['is_available'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_available">Available for Order</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured"
                               value="1" <?= ($editItem['is_featured'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_featured">Featured Item</label>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 px-4 py-2">
                        <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Update Item' : 'Create Item' ?>
                    </button>
                    <a href="<?= url('admin/modules/menu/index.php') ?>" class="btn btn-light w-100 mt-2">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function previewMainImage(input) {
    var preview = document.getElementById('mainImagePreview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php else: // ─── List View ──────────────────────────────────────

$search          = trim($_GET['search'] ?? '');
$filterCategory  = (int)($_GET['category'] ?? 0);
$filterAvail     = $_GET['availability'] ?? '';
$filterFeatured  = $_GET['featured'] ?? '';

$where  = '1';
$params = [];

if ($search !== '') {
    $where .= " AND (fi.name LIKE ? OR fi.description LIKE ? OR fi.ingredients LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

if ($filterCategory > 0) {
    $where .= " AND fi.category_id = ?";
    $params[] = $filterCategory;
}

if ($filterAvail !== '' && in_array($filterAvail, ['0', '1'])) {
    $where .= " AND fi.is_available = ?";
    $params[] = (int)$filterAvail;
}

if ($filterFeatured !== '' && in_array($filterFeatured, ['0', '1'])) {
    $where .= " AND fi.is_featured = ?";
    $params[] = (int)$filterFeatured;
}

$pagination = paginate('food_items fi', $where, $params, 10);

$menuItems = Database::fetchAll(
    "SELECT fi.*, fc.name AS category_name
     FROM food_items fi
     LEFT JOIN food_categories fc ON fi.category_id = fc.id
     WHERE {$where}
     ORDER BY fi.is_featured DESC, fi.name ASC
     LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}",
    $params
);

$allCategories = Database::fetchAll("SELECT id, name FROM food_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");

$queryParams = array_filter([
    'search'       => $search ?: null,
    'category'     => $filterCategory ?: null,
    'availability' => $filterAvail !== '' ? $filterAvail : null,
    'featured'     => $filterFeatured !== '' ? $filterFeatured : null,
]);
$baseUrl = url('admin/modules/menu/index.php') . '?' . http_build_query($queryParams);

$totalItems      = Database::count('food_items');
$availableItems  = Database::count('food_items', 'is_available = 1');
$featuredItems   = Database::count('food_items', 'is_featured = 1');
$totalCategories = Database::count('food_categories', 'is_active = 1');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Menu Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Menu Items</li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('admin/modules/menu/index.php?action=add') ?>" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Menu Item
    </a>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,107,53,0.1)">
                        <i class="fas fa-hamburger" style="color:var(--primary);font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Items</div>
                        <div class="fw-bold fs-5"><?= $totalItems ?></div>
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
                        <div class="text-muted small">Available</div>
                        <div class="fw-bold fs-5"><?= $availableItems ?></div>
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
                        <i class="fas fa-star" style="color:#ffc107;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Featured</div>
                        <div class="fw-bold fs-5"><?= $featuredItems ?></div>
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
                        <i class="fas fa-tags" style="color:#dc3545;font-size:1.1rem"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Categories</div>
                        <div class="fw-bold fs-5"><?= $totalCategories ?></div>
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
            <div class="col-md-3">
                <label for="search" class="form-label fw-semibold small">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Search items..." value="<?= sanitize($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label for="filterCategory" class="form-label fw-semibold small">Category</label>
                <select class="form-select" id="filterCategory" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterAvail" class="form-label fw-semibold small">Availability</label>
                <select class="form-select" id="filterAvail" name="availability">
                    <option value="">All</option>
                    <option value="1" <?= $filterAvail === '1' ? 'selected' : '' ?>>Available</option>
                    <option value="0" <?= $filterAvail === '0' ? 'selected' : '' ?>>Unavailable</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="filterFeatured" class="form-label fw-semibold small">Featured</label>
                <select class="form-select" id="filterFeatured" name="featured">
                    <option value="">All</option>
                    <option value="1" <?= $filterFeatured === '1' ? 'selected' : '' ?>>Featured</option>
                    <option value="0" <?= $filterFeatured === '0' ? 'selected' : '' ?>>Not Featured</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('admin/modules/menu/index.php') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Menu Items Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:50px">#</th>
                        <th style="width:50px"></th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Available</th>
                        <th>Featured</th>
                        <th>Rating</th>
                        <th>Orders</th>
                        <th class="text-end pe-3" style="width:190px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($menuItems)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fas fa-hamburger fa-2x mb-3 d-block opacity-50"></i>
                                No menu items found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($menuItems as $i => $item): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $pagination['offset'] + $i + 1 ?></td>
                                <td>
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="<?= url('uploads/food/' . sanitize($item['image'])) ?>"
                                             alt="" class="rounded" width="40" height="40" style="object-fit:cover">
                                    <?php else: ?>
                                        <div class="rounded d-flex align-items-center justify-content-center"
                                             style="width:40px;height:40px;background:rgba(255,107,53,0.1)">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <span class="fw-semibold"><?= sanitize($item['name']) ?></span>
                                        <?php if (!empty($item['preparation_time'])): ?>
                                            <br><small class="text-muted"><i class="fas fa-clock me-1"></i><?= (int)$item['preparation_time'] ?> min</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1">
                                        <?= sanitize($item['category_name'] ?? 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($item['discount_price']) && (float)$item['discount_price'] > 0): ?>
                                        <div>
                                            <span class="text-decoration-line-through text-muted small"><?= currency((float)$item['price']) ?></span>
                                            <span class="fw-bold" style="color:var(--primary)"><?= currency((float)$item['discount_price']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="fw-bold"><?= currency((float)$item['price']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_availability">
                                        <input type="hidden" name="food_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?= $item['is_available'] ? 'success' : 'secondary' ?>"
                                                data-bs-toggle="tooltip"
                                                title="<?= $item['is_available'] ? 'Mark Unavailable' : 'Mark Available' ?>">
                                            <i class="fas fa-<?= $item['is_available'] ? 'eye' : 'eye-slash' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_featured">
                                        <input type="hidden" name="food_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?= $item['is_featured'] ? 'warning' : 'secondary' ?>"
                                                data-bs-toggle="tooltip"
                                                title="<?= $item['is_featured'] ? 'Remove from Featured' : 'Mark as Featured' ?>">
                                            <i class="fas fa-star"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ((int)($item['rating_count'] ?? 0) > 0): ?>
                                        <span class="fw-semibold" style="color:var(--primary)">
                                            <i class="fas fa-star me-1" style="color:#ffc107"></i>
                                            <?= number_format((float)$item['rating_avg'], 1) ?>
                                        </span>
                                        <br><small class="text-muted">(<?= (int)$item['rating_count'] ?>)</small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= (int)($item['order_count'] ?? 0) ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <!-- Toggle Availability -->
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle_availability">
                                            <input type="hidden" name="food_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $item['is_available'] ? 'warning' : 'success' ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?= $item['is_available'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $item['is_available'] ? 'ban' : 'check-circle' ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Edit -->
                                        <a href="<?= url('admin/modules/menu/index.php?action=edit&id=' . $item['id']) ?>"
                                           class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>

                                        <!-- Delete -->
                                        <form method="POST" class="d-inline" id="deleteForm_<?= $item['id'] ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="food_id" value="<?= $item['id'] ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="tooltip" title="Delete"
                                                    onclick="deleteItem(<?= $item['id'] ?>, '<?= sanitize(addslashes($item['name'])) ?>')">
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
                    of <?= $pagination['total'] ?> items
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

<script>
function deleteItem(id, name) {
    Swal.fire({
        title: 'Delete "' + name + '"?',
        text: 'This item and all its images will be permanently removed.',
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
