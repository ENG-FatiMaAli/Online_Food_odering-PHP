<?php
require_once __DIR__ . '/includes/helpers.php';

// Fetch data for landing page
try {
    $categories = Database::fetchAll("SELECT * FROM food_categories WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 8");
    $featured = Database::fetchAll("
        SELECT fi.*, fc.name as category_name
        FROM food_items fi
        JOIN food_categories fc ON fi.category_id = fc.id
        WHERE fi.is_featured = 1 AND fi.is_available = 1
        ORDER BY fi.rating_avg DESC LIMIT 6
    ");
    $popular = Database::fetchAll("
        SELECT fi.*, fc.name as category_name
        FROM food_items fi
        JOIN food_categories fc ON fi.category_id = fc.id
        WHERE fi.is_available = 1
        ORDER BY fi.order_count DESC LIMIT 8
    ");
    $totalOrders = Database::count('orders');
    $totalFoods = Database::count('food_items', 'is_available = 1');
    $totalCustomers = Database::count('users', 'role_id = 4');
    $settings = [];
    foreach (Database::fetchAll("SELECT setting_key, setting_value FROM settings") as $s) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
} catch (Exception $e) {
    $categories = [];
    $featured = [];
    $popular = [];
    $totalOrders = 0;
    $totalFoods = 0;
    $totalCustomers = 0;
    $settings = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Fresh Food, Fast Delivery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .landing-hero {
            min-height: 100vh; display: flex; align-items: center;
            background: linear-gradient(135deg, rgba(255,107,53,.95) 0%, rgba(232,93,4,.9) 50%, rgba(26,26,46,.95) 100%);
            color: #fff; position: relative; overflow: hidden;
        }
        .landing-hero::before {
            content: ''; position: absolute; top: -100px; right: -100px;
            width: 400px; height: 400px; background: rgba(247,201,72,.15); border-radius: 50%;
        }
        .landing-hero::after {
            content: ''; position: absolute; bottom: -80px; left: -60px;
            width: 300px; height: 300px; background: rgba(255,255,255,.05); border-radius: 50%;
        }
        .hero-content { position: relative; z-index: 2; }
        .hero-content h1 { font-size: 4rem; font-weight: 800; line-height: 1.1; margin-bottom: 1.5rem; }
        .hero-content h1 span { color: var(--secondary); }
        .hero-content p { font-size: 1.2rem; opacity: .9; max-width: 500px; margin-bottom: 2rem; }
        .hero-image { position: relative; z-index: 2; text-align: center; }
        .hero-image .food-circle {
            width: 400px; height: 400px; border-radius: 50%;
            background: rgba(255,255,255,.1); display: flex; align-items: center; justify-content: center;
            font-size: 8rem; margin: 0 auto; animation: float 3s ease-in-out infinite;
        }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }

        .feature-card { text-align: center; padding: 40px 20px; border-radius: var(--radius); transition: var(--transition); background: #fff; box-shadow: var(--shadow); }
        .feature-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); }
        .feature-icon { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 20px; }

        .stat-box { text-align: center; padding: 30px; }
        .stat-box h2 { font-size: 2.5rem; font-weight: 800; color: var(--primary); }
        .stat-box p { color: var(--gray); font-weight: 500; }

        .cta-section {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; padding: 80px 0; text-align: center;
        }

        .nav-landing { padding: 1rem 0; background: transparent; position: absolute; top: 0; left: 0; right: 0; z-index: 100; }
        .nav-landing.scrolled { background: #fff; box-shadow: 0 2px 20px rgba(0,0,0,.1); }
        .nav-landing .navbar-brand { color: #fff !important; -webkit-text-fill-color: #fff !important; }
        .nav-landing.scrolled .navbar-brand { background: linear-gradient(135deg,var(--primary),var(--primary-dark)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .nav-landing .nav-link { color: rgba(255,255,255,.85) !important; }
        .nav-landing.scrolled .nav-link { color: #555 !important; }

        @media(max-width:768px) {
            .hero-content h1 { font-size: 2.2rem; }
            .hero-image { margin-top: 40px; }
            .hero-image .food-circle { width: 250px; height: 250px; font-size: 5rem; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg nav-landing" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="<?= url('index.php') ?>">
                <i class="fas fa-utensils"></i> <?= APP_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#menu">Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item ms-2">
                            <a href="<?= url(isAdmin() ? 'admin/index.php' : 'customer/index.php') ?>" class="btn btn-light px-4 py-2 fw-semibold">Dashboard</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-2">
                            <a href="<?= url('login.php') ?>" class="btn btn-light px-4 py-2 fw-semibold">Sign In</a>
                        </li>
                        <li class="nav-item ms-2">
                            <a href="<?= url('register.php') ?>" class="btn btn-outline-light px-4 py-2 fw-semibold">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="landing-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1>Delicious Food,<br><span>Delivered Fast</span></h1>
                    <p>Order your favorite meals from the best restaurants in town. Fresh ingredients, authentic recipes, and lightning-fast delivery to your doorstep.</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="<?= url('register.php') ?>" class="btn btn-light btn-lg px-4 py-3 fw-semibold">
                            <i class="fas fa-shopping-bag me-2"></i>Order Now
                        </a>
                        <a href="#menu" class="btn btn-outline-light btn-lg px-4 py-3">
                            <i class="fas fa-utensils me-2"></i>View Menu
                        </a>
                    </div>
                    <div class="d-flex gap-4 mt-4">
                        <div><h4 class="mb-0 fw-bold"><?= $totalFoods ?>+</h4><small class="opacity-75">Menu Items</small></div>
                        <div><h4 class="mb-0 fw-bold"><?= $totalOrders ?>+</h4><small class="opacity-75">Orders Served</small></div>
                        <div><h4 class="mb-0 fw-bold"><?= $totalCustomers ?>+</h4><small class="opacity-75">Happy Customers</small></div>
                    </div>
                </div>
                <div class="col-lg-6 hero-image d-none d-lg-block">
                    <div class="food-circle">
                        <i class="fas fa-hamburger" style="animation:float 3s ease-in-out infinite"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="py-5 mt-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Why Choose Us</h2>
                <p class="section-subtitle">We deliver the best food experience</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:rgba(255,107,53,.1);color:var(--primary)"><i class="fas fa-utensils"></i></div>
                        <h5 class="fw-bold">Best Quality</h5>
                        <p class="text-muted small">Fresh ingredients sourced from local farms for the best taste.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:rgba(28,200,138,.1);color:#1cc88a"><i class="fas fa-bolt"></i></div>
                        <h5 class="fw-bold">Fast Delivery</h5>
                        <p class="text-muted small">Lightning-fast delivery to your doorstep in 30 minutes or less.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:rgba(78,115,223,.1);color:#4e73df"><i class="fas fa-shield-alt"></i></div>
                        <h5 class="fw-bold">Secure Payment</h5>
                        <p class="text-muted small">Multiple secure payment options including mobile money.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:rgba(246,194,62,.1);color:#f6c23e"><i class="fas fa-headset"></i></div>
                        <h5 class="fw-bold">24/7 Support</h5>
                        <p class="text-muted small">Round-the-clock customer support for all your queries.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Menu -->
    <section id="menu" class="py-5" style="background:#f8f9fa">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Our Menu</h2>
                <p class="section-subtitle">Explore our delicious range of food items</p>
            </div>

            <!-- Categories -->
            <div class="category-pills mb-4" id="categoryPills">
                <a href="#menu" class="category-pill active" data-filter="all"><i class="fas fa-th"></i> All</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="#menu" class="category-pill" data-filter="<?= sanitize($cat['name']) ?>"><?= sanitize($cat['name']) ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Featured Items -->
            <?php if ($featured): ?>
            <h4 class="fw-bold mb-3"><i class="fas fa-star text-warning me-2"></i>Featured Items</h4>
            <div class="row g-4 mb-5" id="featuredItems">
                <?php foreach ($featured as $item): ?>
                <div class="col-md-6 col-lg-4 menu-item" data-category="<?= sanitize($item['category_name']) ?>">
                    <div class="food-card">
                        <div class="food-card-img">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?= url('uploads/food/' . sanitize($item['image'])) ?>" alt="<?= sanitize($item['name']) ?>" style="height:200px;width:100%;object-fit:cover;">
                            <?php else: ?>
                                <div style="height:200px;background:linear-gradient(135deg,rgba(255,107,53,.2),rgba(247,201,72,.2));display:flex;align-items:center;justify-content:center">
                                    <i class="fas fa-utensils" style="font-size:3rem;color:var(--primary);opacity:.5"></i>
                                </div>
                            <?php endif; ?>
                            <span class="food-card-badge"><?= sanitize($item['category_name']) ?></span>
                            <span class="food-card-featured"><i class="fas fa-star"></i> Featured</span>
                        </div>
                        <div class="food-card-body">
                            <h5 class="food-card-title"><?= sanitize($item['name']) ?></h5>
                            <p class="food-card-desc"><?= sanitize($item['description']) ?></p>
                            <div class="food-card-rating">
                                <i class="fas fa-star"></i> <?= number_format($item['rating_avg'], 1) ?>
                                <span>(<?= $item['rating_count'] ?> reviews)</span>
                            </div>
                        </div>
                        <div class="food-card-footer">
                            <div>
                                <?php if ($item['discount_price']): ?>
                                    <span class="food-price"><?= currency($item['discount_price']) ?></span>
                                    <span class="food-price-original"><?= currency($item['price']) ?></span>
                                <?php else: ?>
                                    <span class="food-price"><?= currency($item['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="<?= url('register.php') ?>" class="btn btn-add-cart"><i class="fas fa-cart-plus me-1"></i>Add</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Popular Items -->
            <h4 class="fw-bold mb-3"><i class="fas fa-fire text-danger me-2"></i>Popular Items</h4>
            <div class="row g-4" id="popularItems">
                <?php foreach ($popular as $item): ?>
                <div class="col-md-6 col-lg-3 menu-item" data-category="<?= sanitize($item['category_name']) ?>">
                    <div class="food-card">
                        <div class="food-card-img">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?= url('uploads/food/' . sanitize($item['image'])) ?>" alt="<?= sanitize($item['name']) ?>" style="height:160px;width:100%;object-fit:cover;">
                            <?php else: ?>
                                <div style="height:160px;background:linear-gradient(135deg,rgba(255,107,53,.15),rgba(247,201,72,.15));display:flex;align-items:center;justify-content:center">
                                    <i class="fas fa-utensils" style="font-size:2.5rem;color:var(--primary);opacity:.4"></i>
                                </div>
                            <?php endif; ?>
                            <span class="food-card-badge"><?= sanitize($item['category_name']) ?></span>
                        </div>
                        <div class="food-card-body py-3">
                            <h6 class="food-card-title mb-1"><?= sanitize($item['name']) ?></h6>
                            <div class="food-card-rating" style="font-size:.8rem">
                                <i class="fas fa-star"></i> <?= number_format($item['rating_avg'], 1) ?>
                                <span>(<?= $item['order_count'] ?> orders)</span>
                            </div>
                        </div>
                        <div class="food-card-footer py-2">
                            <span class="food-price" style="font-size:1rem">
                                <?php if ($item['discount_price']): ?>
                                    <?= currency($item['discount_price']) ?>
                                    <span class="food-price-original"><?= currency($item['price']) ?></span>
                                <?php else: ?>
                                    <?= currency($item['price']) ?>
                                <?php endif; ?>
                            </span>
                            <a href="<?= url('register.php') ?>" class="btn btn-add-cart btn-sm"><i class="fas fa-cart-plus"></i></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <a href="<?= url('register.php') ?>" class="btn btn-primary-custom btn-lg px-5 py-3">
                    <i class="fas fa-utensils me-2"></i>View Full Menu
                </a>
            </div>
        </div>
    </section>

    <!-- About -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="section-title">About <?= APP_NAME ?></h2>
                    <p class="text-muted mb-4">We are passionate about delivering the best food experience to your doorstep. Our team of expert chefs prepares each dish with love, using only the freshest ingredients.</p>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:50px;height:50px;border-radius:50%;background:rgba(255,107,53,.1);display:flex;align-items:center;justify-content:center"><i class="fas fa-check text-primary"></i></div>
                                <span class="fw-medium">Fresh Ingredients</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:50px;height:50px;border-radius:50%;background:rgba(28,200,138,.1);display:flex;align-items:center;justify-content:center"><i class="fas fa-check" style="color:#1cc88a"></i></div>
                                <span class="fw-medium">Expert Chefs</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:50px;height:50px;border-radius:50%;background:rgba(78,115,223,.1);display:flex;align-items:center;justify-content:center"><i class="fas fa-check" style="color:#4e73df"></i></div>
                                <span class="fw-medium">Fast Delivery</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:50px;height:50px;border-radius:50%;background:rgba(247,201,72,.2);display:flex;align-items:center;justify-content:center"><i class="fas fa-check" style="color:#f6c23e"></i></div>
                                <span class="fw-medium">24/7 Available</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-center mt-4 mt-lg-0">
                    <div style="width:350px;height:350px;border-radius:50%;background:linear-gradient(135deg,rgba(255,107,53,.15),rgba(247,201,72,.15));display:flex;align-items:center;justify-content:center;margin:0 auto">
                        <i class="fas fa-utensils" style="font-size:6rem;color:var(--primary);opacity:.4"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
        <div class="container">
            <h2 class="fw-bold mb-3">Ready to Order?</h2>
            <p class="mb-4 opacity-90">Join thousands of happy customers and order your favorite food today!</p>
            <a href="<?= url('register.php') ?>" class="btn btn-light btn-lg px-5 py-3 fw-semibold">
                <i class="fas fa-rocket me-2"></i>Get Started Free
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5><i class="fas fa-utensils me-2" style="color:var(--primary)"></i><?= APP_NAME ?></h5>
                    <p class="small">Your favorite food, delivered fast. Fresh ingredients, authentic recipes, and the best service in town.</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h6 class="fw-bold text-white mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#menu">Menu</a></li>
                        <li class="mb-2"><a href="#features">Features</a></li>
                        <li class="mb-2"><a href="#about">About</a></li>
                        <li class="mb-2"><a href="<?= url('register.php') ?>">Register</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h6 class="fw-bold text-white mb-3">Contact</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2" style="color:var(--primary)"></i><?= $settings['restaurant_address'] ?? '123 Food Street' ?></li>
                        <li class="mb-2"><i class="fas fa-phone me-2" style="color:var(--primary)"></i><?= $settings['restaurant_phone'] ?? '+1234567890' ?></li>
                        <li class="mb-2"><i class="fas fa-envelope me-2" style="color:var(--primary)"></i><?= $settings['restaurant_email'] ?? 'info@foodapp.com' ?></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h6 class="fw-bold text-white mb-3">Hours</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2">Mon - Fri: <?= $settings['opening_time'] ?? '9:00 AM' ?> - <?= $settings['closing_time'] ?? '10:00 PM' ?></li>
                        <li class="mb-2">Saturday: 10:00 AM - 11:00 PM</li>
                        <li class="mb-2">Sunday: 11:00 AM - 9:00 PM</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('mainNav');
            if (window.scrollY > 50) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        });
        // Dark mode
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) document.documentElement.setAttribute('data-bs-theme', savedTheme);

        // Category filter
        document.querySelectorAll('#categoryPills .category-pill').forEach(function(pill) {
            pill.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('#categoryPills .category-pill').forEach(function(p) { p.classList.remove('active'); });
                this.classList.add('active');
                var filter = this.getAttribute('data-filter');
                document.querySelectorAll('.menu-item').forEach(function(card) {
                    if (filter === 'all' || card.getAttribute('data-category') === filter) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
