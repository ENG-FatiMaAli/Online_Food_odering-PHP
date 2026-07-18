<?php
/**
 * Helper Functions
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Auth Helpers ───────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

function isStaff(): bool {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2;
}

function isDriver(): bool {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 3;
}

function isCustomer(): bool {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 4;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireRole(int ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role_id'], $roles)) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireRole(1);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return Database::fetch("SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) AS full_name, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?", [$_SESSION['user_id']]);
}

// ─── CSRF Protection ────────────────────────────────────────
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfToken(): string {
    return generateCSRFToken();
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

function verifyCSRF(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function requireCSRF(): void {
    if (!verifyCSRF()) {
        setFlash('error', 'Invalid security token. Please try again.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('index.php')));
        exit;
    }
}

// ─── Input Helpers ──────────────────────────────────────────
function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function input(string $key, string $default = ''): string {
    return sanitize($_POST[$key] ?? $_GET[$key] ?? $default);
}

function isPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// ─── URL Helpers ────────────────────────────────────────────
function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function currentUrl(): string {
    return $_SERVER['REQUEST_URI'];
}

// ─── Flash Messages ─────────────────────────────────────────
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flash(): void {
    $flash = getFlash();
    if ($flash) {
        $type = sanitize($flash['type']);
        $msg  = sanitize($flash['message']);
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({icon:'{$type}',title:'{$type}',text:'{$msg}',timer:3000,showConfirmButton:false});
            });
        </script>";
    }
}

// ─── Currency ───────────────────────────────────────────────
function currency(float $amount): string {
    return '$' . number_format($amount, 2);
}

// ─── Pagination ─────────────────────────────────────────────
function paginate(string $table, string $where = '1', array $params = [], int $perPage = ITEMS_PER_PAGE): array {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $total = Database::count($table, $where, $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min($page, $pages);
    $offset = ($page - 1) * $perPage;
    return [
        'page'    => $page,
        'pages'   => $pages,
        'total'   => $total,
        'offset'  => $offset,
        'perPage' => $perPage,
    ];
}

function renderPagination(array $pagination, string $baseUrl = '?'): string {
    if ($pagination['pages'] <= 1) return '';
    $html = '<nav><ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $pagination['pages']; $i++) {
        $active = $i == $pagination['page'] ? ' active' : '';
        $url = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . "page={$i}";
        $html .= "<li class=\"page-item{$active}\"><a class=\"page-link\" href=\"{$url}\">{$i}</a></li>";
    }
    $html .= '</ul></nav>';
    return $html;
}

// ─── Order Number Generator ─────────────────────────────────
function generateOrderNumber(): string {
    $year = date('Y');
    $last = Database::fetch("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1", ["ORD-{$year}-%"]);
    if ($last) {
        $num = (int)substr($last['order_number'], -4) + 1;
    } else {
        $num = 1;
    }
    return sprintf("ORD-%s-%04d", $year, $num);
}

// ─── Slug Generator ─────────────────────────────────────────
function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

// ─── File Upload Helper ─────────────────────────────────────
function uploadFile(array $file, string $directory, array $allowedExts = ['jpg','jpeg','png','gif','webp']): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) return null;
    $name = uniqid('img_', true) . '.' . $ext;
    $dest = rtrim($directory, '/') . '/' . $name;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return $name;
    }
    return null;
}

// ─── Time Helpers ───────────────────────────────────────────
function timeAgo(string $datetime): string {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// ─── Notification Helper ────────────────────────────────────
function addNotification(int $userId, string $title, string $message, string $type = 'info', ?string $link = null): void {
    Database::insert('notifications', [
        'user_id'    => $userId,
        'title'      => $title,
        'message'    => $message,
        'type'       => $type,
        'link'       => $link,
    ]);
}

function getUnreadNotifications(int $userId): int {
    return Database::count('notifications', 'user_id = ? AND is_read = 0', [$userId]);
}

// ─── Activity Log ───────────────────────────────────────────
function logActivity(string $action, ?string $description = null): void {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId && (int)$userId > 0) {
            $exists = Database::fetch("SELECT id FROM users WHERE id = ?", [(int)$userId]);
            if (!$exists) $userId = null;
        } else {
            $userId = null;
        }
        Database::insert('activity_logs', [
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);
    } catch (\Exception $e) {
        // Activity logging should never break the app
    }
}

// ─── Settings Helper ────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $row ? $row['setting_value'] : $default;
}

function setSetting(string $key, string $value): void {
    $exists = Database::count('settings', 'setting_key = ?', [$key]);
    if ($exists) {
        Database::update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
    } else {
        Database::insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}

// ─── Cart Helpers ───────────────────────────────────────────
function getCartCount(): int {
    if (!isLoggedIn() || !isCustomer()) return 0;
    $row = Database::fetch("SELECT COALESCE(SUM(quantity),0) as cnt FROM cart WHERE user_id = ?", [$_SESSION['user_id']]);
    return (int)($row['cnt'] ?? 0);
}

function getCartTotal(): float {
    if (!isLoggedIn() || !isCustomer()) return 0.0;
    $row = Database::fetch(
        "SELECT COALESCE(SUM(c.quantity * COALESCE(fi.discount_price, fi.price)),0) as total FROM cart c JOIN food_items fi ON c.food_id = fi.id WHERE c.user_id = ?",
        [$_SESSION['user_id']]
    );
    return (float)($row['total'] ?? 0.0);
}

// ─── JSON Response ──────────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
