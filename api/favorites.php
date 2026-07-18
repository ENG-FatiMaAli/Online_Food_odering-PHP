<?php
/**
 * Favorites API Endpoint
 * Handles AJAX favorite toggle operations
 */
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid method'], 405);
}

if (!isLoggedIn() || !isCustomer()) {
    jsonResponse(['success' => false, 'message' => 'Please login first'], 401);
}

if (!verifyCSRF()) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

$foodId = (int)($_POST['food_id'] ?? 0);

if (!$foodId) {
    jsonResponse(['success' => false, 'message' => 'Invalid food item'], 400);
}

$userId = $_SESSION['user_id'];

$existing = Database::fetch("SELECT id FROM favorites WHERE user_id = ? AND food_id = ?", [$userId, $foodId]);

if ($existing) {
    Database::delete('favorites', 'id = ?', [$existing['id']]);
    jsonResponse(['success' => true, 'message' => 'Removed from favorites', 'action' => 'removed']);
} else {
    Database::insert('favorites', ['user_id' => $userId, 'food_id' => $foodId]);
    jsonResponse(['success' => true, 'message' => 'Added to favorites', 'action' => 'added']);
}
