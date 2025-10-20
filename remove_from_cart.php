<?php
// remove_from_cart.php (AJAX endpoint for removing items from session cart)

session_start();
header('Content-Type: application/json');

$product_id_remove = (int)($_POST['product_id_remove'] ?? 0);

if ($product_id_remove > 0) {
    if (isset($_SESSION['cart'][$product_id_remove])) {
        unset($_SESSION['cart'][$product_id_remove]);
    }
    
    // Count unique items in the cart
    $cart_count = count($_SESSION['cart'] ?? []);
    
    echo json_encode([
        'success' => true,
        'cart_count' => $cart_count,
        'message' => 'Product removed from cart successfully.'
    ]);
    exit;
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid product ID for removal.'
    ]);
    exit;
}
?>