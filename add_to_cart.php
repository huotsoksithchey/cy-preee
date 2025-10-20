<?php
// add_to_cart.php (AJAX endpoint for adding items to session cart)

// FIX: Added session persistence settings for reliable cart storage (24 hours).
ini_set('session.cookie_lifetime', 86400); 
ini_set('session.gc_maxlifetime', 86400);

session_start();
header('Content-Type: application/json');

$product_id_to_add = (int)($_POST['product_id'] ?? 0);

if ($product_id_to_add > 0) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Add 1 to the current quantity, ensuring the current value is treated as an integer
    $_SESSION['cart'][$product_id_to_add] = ((int)($_SESSION['cart'][$product_id_to_add] ?? 0)) + 1;
    
    // Count unique items in the cart
    $cart_count = count($_SESSION['cart']);
    
    echo json_encode([
        'success' => true,
        'cart_count' => $cart_count,
        'message' => 'Product added to cart successfully.'
    ]);
    exit;
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid product ID.'
    ]);
    exit;
}
?>