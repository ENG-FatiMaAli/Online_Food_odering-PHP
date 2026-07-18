/**
 * FoodieApp - Admin Panel JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initConfirmDelete();
    initAutoHideAlerts();
    initPrintReceipt();
    initFormValidation();
});

/* ─── Sidebar Toggle ─────────────────────────── */
function initSidebar() {
    const toggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', function() {
        if (window.innerWidth <= 991) {
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('active');
        } else {
            sidebar.classList.toggle('collapsed');
            document.querySelector('.admin-main').classList.toggle('expanded');
        }
    });

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('active');
        });
    }

    // Close sidebar on nav link click (mobile)
    sidebar.querySelectorAll('.sidebar-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 991) {
                sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('active');
            }
        });
    });
}

/* ─── Confirm Delete ─────────────────────────── */
function initConfirmDelete() {
    document.querySelectorAll('.btn-delete, .confirm-delete').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.href || this.dataset.url;
            const name = this.dataset.name || 'this item';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Delete Confirmation',
                    html: 'Are you sure you want to delete <strong>' + name + '</strong>?<br><small class="text-muted">This action cannot be undone.</small>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74a3b',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-trash me-1"></i>Yes, Delete',
                    cancelButtonText: 'Cancel'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        if (url) window.location.href = url;
                        else {
                            const form = btn.closest('form');
                            if (form) form.submit();
                        }
                    }
                });
            } else {
                if (confirm('Are you sure you want to delete ' + name + '?')) {
                    if (url) window.location.href = url;
                    else {
                        const form = btn.closest('form');
                        if (form) form.submit();
                    }
                }
            }
        });
    });
}

/* ─── Auto-hide Alerts ───────────────────────── */
function initAutoHideAlerts() {
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 500);
        }, 5000);
    });
}

/* ─── Print Receipt ──────────────────────────── */
function initPrintReceipt() {
    document.querySelectorAll('.btn-print').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
    });
}

/* ─── Form Validation ────────────────────────── */
function initFormValidation() {
    document.querySelectorAll('form[data-validate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let valid = true;
            this.querySelectorAll('[required]').forEach(function(input) {
                if (!input.value.trim()) {
                    valid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            if (!valid) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Validation Error', 'Please fill in all required fields.', 'error');
                }
            }
        });
    });
}

/* ─── Toggle Status ──────────────────────────── */
function toggleStatus(url, csrfToken) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Toggle Status',
            text: 'Are you sure you want to change the status?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ff6b35',
            confirmButtonText: 'Yes, Change'
        }).then(function(result) {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrfToken + '"><input type="hidden" name="action" value="toggle">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
}

/* ─── Utility: Show Toast ────────────────────── */
function showToast(message, type) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({toast: true, position: 'top-end', icon: type || 'success', title: message, showConfirmButton: false, timer: 3000});
    }
}
