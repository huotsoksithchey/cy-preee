<?php
// fetch_cart_modal.php (Dedicated endpoint to fetch fresh cart HTML)

session_start();
// --- Output setup ---
header('Content-Type: application/json');
ob_start(); // Start output buffering to capture generated HTML

// --- CATEGORY & PRODUCT MOCK SETUP (Copied from index.php) ---
// This ensures the script can run standalone even without a real DB connection.
if (!file_exists('../includes/db.php')) {
    $products_mock = $GLOBALS['products_mock'] ?? [ /* define a default mock array here if needed */ ];
    $conn = new class { // Mock DB object
        public $products_mock = [];
        public function __construct() { 
            // Fetch mock data from index.php's scope if available
            $this->products_mock = $GLOBALS['products_mock'] ?? []; 
        }
        public function query($sql) {
            if (preg_match('/WHERE id IN \((.*?)\)/', $sql, $matches)) {
                $ids = array_map('intval', explode(',', $matches[1]));
                // Compatible anonymous function
                $filtered_products = array_filter($this->products_mock, function($p) use ($ids) {
                    return in_array($p['id'], $ids);
                });
                $filtered_products = array_values($filtered_products);
                return (object)['num_rows' => count($filtered_products), 'fetch_assoc' => function() use (&$filtered_products) {
                    return array_shift($filtered_products);
                }, 'close' => function() {}];
            }
            return (object)['num_rows' => 0, 'fetch_assoc' => fn() => null, 'close' => fn() => null];
        }
        public function close() {}
    };
} else {
    include('../includes/db.php'); // REAL DATABASE CONNECTION
}
// ---------------------------------------------------------------------

// 1. CART FETCH AND CALCULATION
// ---------------------------------------------------------------------
$cart_details = [];
$cart_total = 0.00;
$simple_cart = $_SESSION['cart'] ?? [];
$cart_count = count(array_keys($simple_cart)); 

if ($cart_count > 0) {
    $cart_product_ids = array_keys($simple_cart);
    $sql_in = implode(',', array_map('intval', $cart_product_ids));
    
    $result_cart = $conn->query("SELECT id, name, price, image_path, stock_qty FROM products WHERE id IN ({$sql_in})");

    if ($result_cart && $result_cart->num_rows > 0) {
        while ($product_data = $result_cart->fetch_assoc()) {
            $product_id = $product_data['id'];
            $qty = $simple_cart[$product_id] ?? 0; 
            
            if ($qty > 0) {
                $line_total = (float)$product_data['price'] * (int)$qty;
                $cart_total += $line_total;

                $cart_details[$product_id] = [
                    'name' => $product_data['name'],
                    'price' => $product_data['price'],
                    'image_path' => $product_data['image_path'],
                    'qty' => $qty,
                    'line_total' => $line_total
                ];
            }
        }
    }
    if (isset($result_cart) && method_exists($result_cart, 'close')) { @$result_cart->close(); }
}
if (isset($conn) && method_exists($conn, 'close')) { @$conn->close(); }

// ---------------------------------------------------------------------
// 2. GENERATE HTML FOR MODAL BODY
// ---------------------------------------------------------------------
if ($cart_count > 0): ?>
    <?php foreach ($cart_details as $id => $item): ?>
        <div class="cart-item-row">
            <img src="../<?= htmlspecialchars($item['image_path']) ?>" 
                 alt="<?= htmlspecialchars($item['name']) ?>" 
                 class="cart-item-img"
                 onerror="this.onerror=null; this.src='https://via.placeholder.com/60x60?text=IMG';">
            <div class="cart-item-details">
                <p class="cart-item-name"><?= htmlspecialchars($item['name']) ?></p>
                <p class="cart-item-price">Qty: <?= htmlspecialchars($item['qty']) ?> | @$<?= number_format($item['price'], 2) ?></p>
                <strong class="text-dark">$<?= number_format($item['line_total'], 2) ?></strong>
            </div>
            <div class="cart-item-actions">
                <form method="post" class="remove-from-cart-form"> 
                    <input type="hidden" name="product_id_remove" value="<?= $id ?>">
                    <input type="hidden" name="remove_item" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="alert alert-info text-center">
        <i class="fas fa-box-open me-2"></i> Your cart is currently empty.
    </div>
<?php endif;
$body_html = ob_get_clean(); // Capture and clear body HTML

// ---------------------------------------------------------------------
// 3. GENERATE HTML FOR MODAL FOOTER
// ---------------------------------------------------------------------
ob_start(); // Start output buffering for the footer HTML
if ($cart_count > 0): ?>
    <div class="total-info">
        <span class="text-muted fw-bold">TOTAL:</span>
        <span id="cart-total-price">$<?= number_format($cart_total, 2) ?></span>
    </div>
    <a href="payment.php" class="btn btn-lg btn-success fw-bold">
        <i class="fas fa-lock me-1"></i> Proceed to Checkout
    </a>
<?php else: ?>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Continue Shopping</button>
<?php endif;
$footer_html = ob_get_clean(); // Capture and clear footer HTML


// 4. Send the final JSON response
echo json_encode([
    'success' => true,
    'body_html' => $body_html,
    'footer_html' => $footer_html,
    'cart_count' => $cart_count
]);
exit;
?>