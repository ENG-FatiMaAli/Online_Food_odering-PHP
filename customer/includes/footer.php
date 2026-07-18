    </main>

    <footer class="text-white pt-5 pb-3 mt-5" style="background-color: #1a1a2e;">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-utensils fa-2x me-2" style="color: #ff6b35;"></i>
                        <h5 class="mb-0 fw-bold"><?= APP_NAME ?></h5>
                    </div>
                    <p class="text-white-50 mb-3">
                        Delivering delicious meals straight to your door. Fresh ingredients, amazing flavors, and quick delivery.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white-50 fs-5"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white-50 fs-5"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white-50 fs-5"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white-50 fs-5"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6">
                    <h6 class="fw-bold mb-3" style="color: #ff6b35;">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?= url('customer/index.php') ?>" class="text-white-50 text-decoration-none">Menu</a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= url('index.php') ?>" class="text-white-50 text-decoration-none">Home</a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= url('customer/modules/orders/') ?>" class="text-white-50 text-decoration-none">My Orders</a>
                        </li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h6 class="fw-bold mb-3" style="color: #ff6b35;">Contact Info</h6>
                    <ul class="list-unstyled text-white-50">
                        <li class="mb-2">
                            <i class="fas fa-map-marker-alt me-2" style="color: #ff6b35;"></i>
                            123 Food Street, Flavor Town
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone me-2" style="color: #ff6b35;"></i>
                            +1 (555) 123-4567
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-envelope me-2" style="color: #ff6b35;"></i>
                            info@<?= strtolower(str_replace(' ', '', APP_NAME)) ?>.com
                        </li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h6 class="fw-bold mb-3" style="color: #ff6b35;">Opening Hours</h6>
                    <ul class="list-unstyled text-white-50">
                        <li class="mb-2">
                            <i class="fas fa-clock me-2" style="color: #ff6b35;"></i>
                            Mon - Fri: 9:00 AM - 10:00 PM
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-clock me-2" style="color: #ff6b35;"></i>
                            Sat - Sun: 10:00 AM - 11:00 PM
                        </li>
                    </ul>
                </div>
            </div>

            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="text-white-50 mb-0">
                        &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="text-white-50 mb-0">
                        Made with <i class="fas fa-heart" style="color: #ff6b35;"></i> for food lovers
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
            }

            const toggle = document.getElementById('darkModeToggle');
            if (toggle) {
                toggle.addEventListener('click', function() {
                    const isDark = document.body.classList.toggle('dark-mode');
                    const theme = isDark ? 'dark' : 'light';
                    document.body.setAttribute('data-theme', theme);
                    localStorage.setItem('theme', theme);
                });
            }
        })();
    </script>

    <?php if (!empty($_SESSION['flash'])): ?>
        <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '<?= $flash['type'] === 'danger' ? 'error' : ($flash['type'] ?? 'info') ?>',
                    title: <?= json_encode(ucfirst($flash['type'] ?? 'info')) ?>,
                    text: <?= json_encode($flash['message'] ?? '') ?>,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        </script>
    <?php endif; ?>
</body>
</html>
