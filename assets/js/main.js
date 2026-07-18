/**
 * FoodieApp - Main Customer JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    initDarkMode();
    initBackToTop();
    initCartQty();
    initFavoriteToggle();
    initSearch();
});

/* ─── Dark Mode ──────────────────────────────── */
function initDarkMode() {
    const toggle = document.getElementById('darkModeToggle');
    if (!toggle) return;
    const saved = localStorage.getItem('theme');
    if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
    toggle.addEventListener('click', function() {
        const current = document.documentElement.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);
        const icon = this.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-moon');
            icon.classList.toggle('fa-sun');
        }
    });
}

/* ─── Back to Top ────────────────────────────── */
function initBackToTop() {
    const btn = document.createElement('button');
    btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    btn.className = 'btn-back-to-top';
    btn.style.cssText = 'position:fixed;bottom:30px;right:30px;width:44px;height:44px;border-radius:50%;background:var(--primary);color:#fff;border:none;cursor:pointer;display:none;z-index:999;box-shadow:0 4px 15px rgba(255,107,53,.4);transition:.3s';
    document.body.appendChild(btn);
    window.addEventListener('scroll', function() {
        btn.style.display = window.scrollY > 300 ? 'flex' : 'none';
        btn.style.alignItems = 'center';
        btn.style.justifyContent = 'center';
    });
    btn.addEventListener('click', function() { window.scrollTo({top: 0, behavior: 'smooth'}); });
}

/* ─── Cart Quantity Controls ─────────────────── */
function initCartQty() {
    document.querySelectorAll('.cart-qty-plus').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const input = this.closest('.cart-qty-control').querySelector('.cart-qty-input');
            input.value = parseInt(input.value) + 1;
            input.dispatchEvent(new Event('change'));
        });
    });
    document.querySelectorAll('.cart-qty-minus').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const input = this.closest('.cart-qty-control').querySelector('.cart-qty-input');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                input.dispatchEvent(new Event('change'));
            }
        });
    });
}

/* ─── Favorite Toggle ────────────────────────── */
function initFavoriteToggle() {
    document.querySelectorAll('.favorite-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const foodId = this.dataset.foodId;
            fetch('<?= BASE_URL ?>/api/favorites.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'food_id=' + foodId + '&csrf_token=' + (document.querySelector('meta[name="csrf-token"]')?.content || '')
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.classList.toggle('active');
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fas');
                    icon.classList.toggle('far');
                    showToast(data.message, 'success');
                }
            })
            .catch(() => {});
        });
    });
}

/* ─── Search ─────────────────────────────────── */
function initSearch() {
    const searchInput = document.querySelector('.hero-search input, .menu-search');
    if (!searchInput) return;
    let timer;
    searchInput.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() {
            const form = searchInput.closest('form');
            if (form) form.submit();
        }, 500);
    });
}

/* ─── Toast Notification ─────────────────────── */
function showToast(message, type) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({toast: true, position: 'top-end', icon: type, title: message, showConfirmButton: false, timer: 3000});
    }
}

/* ─── Confirm Action ─────────────────────────── */
function confirmAction(message, callback) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({title: 'Are you sure?', text: message, icon: 'warning', showCancelButton: true, confirmButtonColor: '#ff6b35', cancelButtonColor: '#6c757d', confirmButtonText: 'Yes'}).then(result => {
            if (result.isConfirmed) callback();
        });
    } else {
        if (confirm(message)) callback();
    }
}

/* ─── Image Preview ──────────────────────────── */
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
