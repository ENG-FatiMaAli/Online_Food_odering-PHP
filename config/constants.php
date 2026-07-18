<?php
/**
 * Application Constants
 */

define('APP_NAME', 'FoodieApp');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/Final_PROJECTS_PHP/Food_Ordering_System');
define('UPLOADS_URL', BASE_URL . '/uploads');
define('FOOD_UPLOADS', __DIR__ . '/../uploads/food/');
define('AVATAR_UPLOADS', __DIR__ . '/../uploads/avatar/');
define('ITEMS_PER_PAGE', 12);
define('ORDERS_PER_PAGE', 10);

define('ORDER_STATUSES', [
    'pending'           => 'Pending',
    'confirmed'         => 'Confirmed',
    'preparing'         => 'Preparing',
    'ready'             => 'Ready',
    'out_for_delivery'  => 'Out for Delivery',
    'delivered'         => 'Delivered',
    'cancelled'         => 'Cancelled',
]);

define('ORDER_STATUS_COLORS', [
    'pending'           => 'warning',
    'confirmed'         => 'info',
    'preparing'         => 'primary',
    'ready'             => 'success',
    'out_for_delivery'  => 'secondary',
    'delivered'         => 'success',
    'cancelled'         => 'danger',
]);

define('PAYMENT_METHODS', [
    'cod'          => 'Cash on Delivery',
    'mobile_money' => 'Mobile Money',
    'card'         => 'Card Payment',
]);

define('PAYMENT_STATUSES', [
    'pending'   => 'Pending',
    'completed' => 'Completed',
    'paid'      => 'Paid',
    'failed'    => 'Failed',
    'refunded'  => 'Refunded',
]);

define('PAYMENT_ORDER_STATUSES', [
    'pending'   => 'pending',
    'completed' => 'paid',
    'failed'    => 'failed',
    'refunded'  => 'refunded',
]);

define('ROLES', [
    1 => 'admin',
    2 => 'staff',
    3 => 'driver',
    4 => 'customer',
]);
