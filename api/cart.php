<?php
/**
 * Cart API Endpoint
 * Handles AJAX cart operations
 */
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isCustomer()) {
    jsonResponse(['success' => false, 'message' => 'Please login first'], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'add':
        $foodId = (int)($_POST['food_id'] ?? 0);
        $qty    = max(1, (int)($_POST['quantity'] ?? 1));

        $food = Database::fetch("SELECT id, name, is_available FROM food_items WHERE id = ?", [$foodId]);
        if (!$food || !$food['is_available']) {
            jsonResponse(['success' => false, 'message' => 'Item not available']);
        }

        $existing = Database::fetch("SELECT id, quantity FROM cart WHERE user_id = ? AND food_id = ?", [$userId, $foodId]);
        if ($existing) {
            Database::update('cart', ['quantity' => $existing['quantity'] + $qty], 'id = ?', [$existing['id']]);
        } else {
            Database::insert('cart', ['user_id' => $userId, 'food_id' => $foodId, 'quantity' => $qty]);
        }

        $cartCount = getCartCount();
        jsonResponse(['success' => true, 'message' => 'Added to cart', 'cart_count' => $cartCount]);
        break;

    case 'remove':
        $cartId = (int)($_POST['cart_id'] ?? 0);
        if ($cartId) {
            Database::delete('cart', 'id = ? AND user_id = ?', [$cartId, $userId]);
        }
        jsonResponse(['success' => true, 'message' => 'Removed from cart', 'cart_count' => getCartCount()]);
        break;

    case 'update':
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $qty    = max(1, (int)($_POST['quantity'] ?? 1));
        if ($cartId) {
            Database::update('cart', ['quantity' => $qty], 'id = ? AND user_id = ?', [$cartId, $userId]);
        }
        jsonResponse(['success' => true, 'message' => 'Cart updated', 'cart_count' => getCartCount()]);
        break;

    case 'count':
        jsonResponse(['success' => true, 'cart_count' => getCartCount(), 'cart_total' => getCartTotal()]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
