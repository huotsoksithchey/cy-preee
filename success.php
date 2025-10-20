<?php
// ====================================================================
// File: success.php (Final Order Confirmation and Cleanup)
// FIX: Logs order, decrements stock atomically, clears ALL session data, and uses a reliable homepage link.
// ====================================================================

session_start();

// Essential security check
if (!file_exists('../includes/db.php')) {
    die("❌ Configuration Error: Database include is missing.");
}
include('../includes/db.php');

$order_status = "Payment Confirmed";
// Retrieve necessary data from the session for logging and display
$transaction_id = $_SESSION['transaction_id'] ?? null;
$amount = $_SESSION['amount'] ?? 0;
$product_id = $_SESSION['product_id'] ?? null;
$customer_info = $_SESSION['customer_info'] ?? null;

// ====================================================================
// CRITICAL LOGGING: Log the successful transaction and decrement stock.
// ====================================================================

if ($transaction_id && $product_id && $amount > 0 && $customer_info) {
    try {
        // Use database transactions to ensure logging and stock decrement are atomic
        $conn->begin_transaction();
        
        // 1. Log the order
        $log_stmt = $conn->prepare("INSERT INTO orders (product_id, customer_name, contact_user, phone, email, location, quantity, total_amount, transaction_id, status, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', NOW())");
        
        $log_stmt->bind_param("isssssids", 
            $product_id, 
            $customer_info['name'], 
            $customer_info['username'], 
            $customer_info['phone'], 
            $customer_info['email'], 
            $customer_info['location'],
            $customer_info['qty'],
            $amount,
            $transaction_id
        );
        $log_stmt->execute();
        $log_stmt->close();

        // 2. Update stock level (Decrement)
        $qty = $customer_info['qty'] ?? 1;
        $stock_stmt = $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?");
        $stock_stmt->bind_param("iii", $qty, $product_id, $qty);
        $stock_stmt->execute();
        
        if ($stock_stmt->affected_rows === 0) {
            $conn->rollback();
            $order_status = "Payment confirmed, but stock was sold out simultaneously (Race condition). Please contact support with T-ID: " . htmlspecialchars($transaction_id);
            error_log("Stock update failed for T-ID: {$transaction_id}. Stock inconsistency detected.");
        } else {
            $conn->commit();
        }
        $stock_stmt->close();

    } catch (Exception $e) {
        $conn->rollback();
        $order_status = "Payment confirmed, but order logging failed. Please contact support with T-ID: " . htmlspecialchars($transaction_id);
        error_log("Transaction failed to commit: " . $e->getMessage());
    }
} else {
    if (!isset($_GET['order_complete'])) {
        $order_status = "You accessed this page directly or the payment was not confirmed.";
        $customer_info = ["name" => "N/A"];
    }
}

// ====================================================================
// CRITICAL CLEANUP: Destroy all payment-related session data.
// ====================================================================

unset($_SESSION['qrData']);
unset($_SESSION['amount']);
unset($_SESSION['transaction_id']);
unset($_SESSION['product_id']);
unset($_SESSION['customer_info']);
unset($_SESSION['csrf_token']);
unset($_SESSION['last_payment_time']);
unset($_SESSION['notification_sent']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success!</title>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f8f8; text-align: center; padding: 50px; }
        .success-box { max-width: 500px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); border-top: 5px solid #4ECDC4; }
        h1 { color: #4ECDC4; font-size: 2.5rem; margin-bottom: 10px; }
        p { color: #555; margin-bottom: 20px; }
        .details { text-align: left; padding: 15px; background: #f0f4f8; border-radius: 8px; margin-top: 20px; }
        .details strong { display: block; margin-top: 5px; color: #212121; }
        .home-btn { display: inline-block; padding: 10px 20px; background-color: #FF6B6B; color: white; border-radius: 8px; text-decoration: none; margin-top: 20px; transition: background-color 0.3s; }
        .home-btn:hover { background-color: #E65C5C; }
    </style>
</head>
<body>
    <div class="success-box">
        <?php if (isset($_GET['order_complete']) && $transaction_id): ?>
            <h1>🎉 Payment Successful!</h1>
            <p>Thank you, **<?= htmlspecialchars($customer_info['name'] ?? 'Customer') ?>**! Your order is complete and is being processed.</p>
            
            <div class="details">
                **Order Details:**
                <strong>Transaction ID: <?= htmlspecialchars($transaction_id ?? 'N/A') ?></strong>
                <strong>Amount Paid: $<?= htmlspecialchars(number_format($amount, 2)) ?></strong>
                <strong>Product: <?= htmlspecialchars($customer_info['product_name'] ?? 'N/A') ?> (x<?= htmlspecialchars($customer_info['qty'] ?? 1) ?>)</strong>
                <p style="font-size: 0.9em; margin-top: 10px;">A confirmation was sent to **<?= htmlspecialchars($customer_info['email'] ?? 'your email') ?>** and notification to our staff.</p>
            </div>
        <?php else: ?>
            <h1>⚠️ Status</h1>
            <p><?= htmlspecialchars($order_status) ?></p>
        <?php endif; ?>
        
        <a href="/cy-pre/shopping/index.php" class="home-btn">Return to Homepage</a>
    </div>
</body>
</html>