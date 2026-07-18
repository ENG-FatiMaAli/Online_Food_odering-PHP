<?php
require_once __DIR__ . '/../../config/constants.php';

function getFoodImage($image)
{
    if (!empty($image) && file_exists(__DIR__ . '/../../assets/images/food/' . $image)) {
        return BASE_URL . '/assets/images/food/' . $image;
    }
    return BASE_URL . '/assets/images/food/placeholder.png';
}

function getAvatarUrl($avatar)
{
    if (!empty($avatar) && file_exists(__DIR__ . '/../../uploads/avatar/' . $avatar)) {
        return BASE_URL . '/uploads/avatar/' . $avatar;
    }
    return BASE_URL . '/assets/images/placeholder-food.jpg';
}

function getStarRating($rating)
{
    $rating = (float) $rating;
    $fullStars = (int) floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

    $html = '';

    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fas fa-star" style="color: #ffc107;"></i>';
    }

    if ($halfStar) {
        $html .= '<i class="fas fa-star-half-alt" style="color: #ffc107;"></i>';
    }

    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="far fa-star" style="color: #ffc107;"></i>';
    }

    return $html;
}

function getOrderStatusBadge($status)
{
    $badges = [
        'pending'        => 'bg-warning text-dark',
        'confirmed'      => 'bg-info text-white',
        'preparing'      => 'bg-primary',
        'ready'          => 'bg-success',
        'out_for_delivery' => 'bg-secondary',
        'delivered'      => 'bg-success',
        'cancelled'      => 'bg-danger',
        'completed'      => 'bg-success',
        'paid'           => 'bg-success',
    ];

    $class = $badges[strtolower($status)] ?? 'bg-secondary';
    $label = ucwords(str_replace('_', ' ', $status));

    return '<span class="badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
}

function getPaymentMethodIcon($method)
{
    $icons = [
        'credit_card'  => 'fas fa-credit-card',
        'debit_card'   => 'fas fa-credit-card',
        'card'         => 'fas fa-credit-card',
        'cash_on_delivery' => 'fas fa-money-bill-wave',
        'cod'          => 'fas fa-money-bill-wave',
        'cash'         => 'fas fa-money-bill-wave',
        'paypal'       => 'fab fa-paypal',
        'stripe'       => 'fas fa-stripe',
        'bank_transfer' => 'fas fa-university',
        'mobile_wallet' => 'fas fa-mobile-alt',
        'online'       => 'fas fa-globe',
    ];

    $icon = $icons[strtolower($method)] ?? 'fas fa-wallet';
    return '<i class="' . $icon . '"></i>';
}
