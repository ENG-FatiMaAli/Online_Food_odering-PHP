    </div><!-- /.admin-content -->
</div><!-- /.admin-wrapper -->

<!-- Footer -->
<footer class="admin-footer">
    <div class="footer-content">
        <span>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</span>
        <span class="footer-version">v<?= APP_VERSION ?? '1.0.0' ?></span>
    </div>
</footer>

<!-- Bootstrap 5.3.2 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Admin JS -->
<script src="<?= url('assets/js/admin.js') ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ─── Dark Mode Toggle ──────────────────────
    const darkModeBtn = document.getElementById('darkModeToggle');
    const htmlEl = document.documentElement;
    const darkModeKey = 'admin_dark_mode';

    const savedMode = localStorage.getItem(darkModeKey);
    if (savedMode === 'dark') {
        htmlEl.setAttribute('data-bs-theme', 'dark');
        if (darkModeBtn) darkModeBtn.innerHTML = '<i class="fas fa-sun"></i>';
    }

    if (darkModeBtn) {
        darkModeBtn.addEventListener('click', function() {
            const current = htmlEl.getAttribute('data-bs-theme');
            if (current === 'dark') {
                htmlEl.setAttribute('data-bs-theme', 'light');
                localStorage.setItem(darkModeKey, 'light');
                this.innerHTML = '<i class="fas fa-moon"></i>';
            } else {
                htmlEl.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem(darkModeKey, 'dark');
                this.innerHTML = '<i class="fas fa-sun"></i>';
            }
        });
    }

    // ─── Sidebar Toggle (Desktop) ───────────────
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('adminSidebar');
    const body = document.body;

    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', function() {
            if (window.innerWidth >= 992) {
                body.classList.toggle('sidebar-collapsed');
                if (body.classList.contains('sidebar-collapsed')) {
                    sidebar.style.transform = 'translateX(-100%)';
                    document.querySelector('.admin-topbar').style.left = '0';
                    document.querySelector('.admin-wrapper').style.marginLeft = '0';
                } else {
                    sidebar.style.transform = 'translateX(0)';
                    document.querySelector('.admin-topbar').style.left = 'var(--sidebar-width)';
                    document.querySelector('.admin-wrapper').style.marginLeft = 'var(--sidebar-width)';
                }
            }
        });
    }

    // ─── Initialize DataTables ──────────────────
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 10,
            language: {
                search: '<i class="fas fa-search"></i>',
                searchPlaceholder: 'Search records...',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                emptyTable: 'No data available',
                zeroRecords: 'No matching records found'
            }
        });
    }

    // ─── Tooltip Init ──────────────────────────
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) {
        return new bootstrap.Tooltip(el);
    });
});

// ─── Global Confirm Delete Helper ─────────────
function confirmDelete(url, name) {
    Swal.fire({
        title: 'Delete "' + name + '"?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
</script>

<style>
    /* ─── Footer ───────────────────────────────── */
    .admin-footer {
        padding: 16px 24px;
        border-top: 1px solid #e9ecef;
        background: #fff;
        margin-top: auto;
    }

    .footer-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.8rem;
        color: #888;
    }

    .footer-version {
        background: #f0f0f0;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        color: #aaa;
    }

    [data-bs-theme="dark"] .admin-footer {
        background: #161b22;
        border-top-color: #30363d;
    }

    [data-bs-theme="dark"] .footer-content {
        color: #8b949e;
    }

    [data-bs-theme="dark"] .footer-version {
        background: #21262d;
        color: #8b949e;
    }
</style>

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
<?php ob_end_flush(); ?>
