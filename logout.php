<?php
require_once __DIR__ . '/includes/helpers.php';

$userId = $_SESSION['user_id'] ?? null;
logActivity('Logout', 'User logged out');
session_unset();
session_destroy();
header('Location: ' . url('login.php'));
exit;
