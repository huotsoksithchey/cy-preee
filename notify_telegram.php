<?php
// ====================================================================
// File: notify_telegram.php (Telegram Notification Handler) - FINAL MULTI-PRODUCT VERSION
// ====================================================================
session_start();
// --- FIX 1: Start output buffering to prevent any unexpected output (like PHP warnings/notices) 
ob_start(); 
header('Content-Type: application/json');

// CRITICAL: Database Connection
if (!file_exists('../includes/db.php')) {
    error_log("Configuration Error: Database include file is missing at ../includes/db.php");
    echo json_encode(['ok' => false, 'error' => 'Server configuration missing DB link.']);
    exit;
}
include('../includes/db.php');

// --- Helper function for error responses ---
function sendError($message, $conn = null) {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
    if (ob_get_level() > 0) {
        ob_clean(); 
    }
    error_log("Payment Notification Error: " . $message); 
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

// CRITICAL: Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method', $conn); 
}

$action = $_POST['action'] ?? '';
$postedMd5 = $_POST['md5'] ?? '';

// --- Validate Session Data and MD5 Mismatch ---
$qrData = $_SESSION['qrData'] ?? null;
$customer = $_SESSION['customer_info'] ?? null;
$transactionId = $_SESSION['transaction_id'] ?? null;
$amount = $_SESSION['amount'] ?? null; // Total amount
$cart_details = $_SESSION['cart_details'] ?? null; // NEW: Multi-product details

// Validate all necessary session data
if (empty($qrData) || empty($transactionId) || empty($customer) || $amount === null || empty($cart_details)) {
    sendError('No complete session payment data found. Cannot proceed. Cart session data missing.', $conn);
}

$sessionMd5 = $qrData['md5'] ?? '';
if (empty($postedMd5) || empty($sessionMd5) || $postedMd5 !== $sessionMd5) {
    sendError('MD5 mismatch or missing. Security check failed.', $conn);
}

// --- NEW CRITICAL CHECK: Prevent Double Processing (Idempotency) ---
if (isset($_SESSION['transaction_completed']) && $_SESSION['transaction_completed'] === $postedMd5) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    $conn->close();
    echo json_encode(["ok" => true, "warning" => "Transaction already processed. No further action needed."]);
    exit;
}


// ====================================================================
// CRITICAL FIX 3: TOTAL AMOUNT CONSISTENCY CHECK (FOR ALL ITEMS)
// ====================================================================
$recalculated_total = 0.00;

// Re-fetch prices for all products in the cart from DB
$product_ids = array_keys($cart_details);
// Protect against empty cart details, though validated earlier
if (empty($product_ids)) {
     sendError('Cart details were empty during final integrity check.', $conn);
}
$sql_in = implode(',', array_map('intval', $product_ids));

$result_product = $conn->query("SELECT id, price FROM products WHERE id IN ({$sql_in})");
$db_prices = [];
if ($result_product) {
    while ($p = $result_product->fetch_assoc()) {
        $db_prices[$p['id']] = (float)$p['price'];
    }
} else {
    sendError("Failed to re-fetch product prices from DB during consistency check.", $conn);
}

// Recalculate total based on fresh DB prices and session quantity
foreach ($cart_details as $id => $item) {
    if (!isset($db_prices[$id])) {
         sendError("Product ID {$id} not found in database during final check.", $conn);
    }
    $db_price = $db_prices[$id]; 
    $recalculated_total += round($db_price * $item['qty'], 2);
}

$session_amount = (float)($amount ?? 0);

// Check if the total amount in session matches the recalculated total amount
if ($session_amount <= 0 || $recalculated_total <= 0 || abs($session_amount - $recalculated_total) > 0.01) {
    $errorMessage = "Transaction data integrity failure. Session amount: \${$session_amount}, Expected Total: \${$recalculated_total}. Transaction aborted.";
    sendError($errorMessage, $conn);
}


// ====================================================================
// 2. CRITICAL: STOCK DEDUCTION (Iterate and deduct for each product)
// ====================================================================
foreach ($cart_details as $id => $item) {
    $qty_to_deduct = $item['qty'];
    
    // Safely decrement the stock only if sufficient stock exists for this item
    $stmt_update = $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?");
    if ($stmt_update === false) {
        sendError("DB Prepare Error for stock update on ID {$id}: " . $conn->error, $conn);
    }
    
    $stmt_update->bind_param("iii", $qty_to_deduct, $id, $qty_to_deduct);
    
    if (!$stmt_update->execute()) {
        $error = "DB Execute Error during stock update for ID {$id}: " . $stmt_update->error;
        $stmt_update->close();
        sendError("Failed to update stock for product {$item['name']}: " . $error, $conn);
    }
    
    // Check if stock was actually deducted (affected_rows will be 1 if successful)
    if ($stmt_update->affected_rows === 0) {
        $stmt_update->close();
        // WARNING: Stock deductions for previously processed items remain.
        sendError("Stock deduction failed for product {$item['name']}. Stock became insufficient or ID invalid.", $conn);
    }
    
    $stmt_update->close();
}
// All stock deductions successful.


// ====================================================================
// 3. TELEGRAM NOTIFICATION
// ====================================================================

// --- CRITICAL FIX: Robustly escape all special Markdown V2 characters ---
function escapeMarkdownV2($text) {
    // List of all special characters that MUST be escaped in Telegram's MarkdownV2
    $special_chars = [
        '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'
    ];
    
    // Create an array of their escaped counterparts (e.g., '\_', '\*', '\.')
    $replacements = array_map(fn($c) => '\\' . $c, $special_chars);
    
    // Use str_replace with arrays for atomic, one-pass replacement.
    return str_replace($special_chars, $replacements, $text);
}

// --- Send Telegram message logic (Updated to handle cart_details) ---
function sendTelegramMessage(array $customer, array $cart_details, $total_amount, $transactionId) {
    // !!! CRITICAL: CHECK THESE VALUES !!!
    $bot_token = "8053537043:AAG3k9DrQ4pLemg6jn8a6zmz_typ_ysGuc4"; 
    $chat_id = "-4803523638";
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    
    // --- ESCAPING DYNAMIC DATA ---
    $escaped_transaction = escapeMarkdownV2($transactionId);
    $escaped_name = escapeMarkdownV2($customer['name'] ?? 'N/A');
    $escaped_username = escapeMarkdownV2($customer['username'] ?? 'N/A');
    $escaped_phone = escapeMarkdownV2($customer['phone'] ?? 'N/A');
    $escaped_email = escapeMarkdownV2($customer['email'] ?? 'N/A');
    $escaped_location = escapeMarkdownV2($customer['location'] ?? 'N/A');
    $escaped_amount = escapeMarkdownV2(number_format($total_amount, 2));

    // --- ESCAPING STATIC SEPARATORS ---
    $separator = str_replace('-', '\-', "------------------------------------");

    // Build the list of products for the message body
    $product_list = "";
    foreach ($cart_details as $item) {
        $escaped_product_name = escapeMarkdownV2($item['name']);
        // Escape unit price before embedding
        $escaped_unit_price = escapeMarkdownV2(number_format($item['price'], 2));
        
        $product_list .= " \\- {$escaped_product_name} x {$item['qty']} @ \${$escaped_unit_price} \n";
    }
    $product_list = trim($product_list);

    // Build the message using Markdown
    $message = "💵 *NEW CART PAYMENT RECEIVED*\n";
    $message .= $separator . "\n";
    $message .= "🛒 *ITEMS:*\n" . $product_list . "\n";
    $message .= $separator . "\n";
    $message .= "💰 *TOTAL AMOUNT:* $" . $escaped_amount . "\n";
    $message .= "🆔 *TRANSACTION:* " . $escaped_transaction . "\n";
    $message .= $separator . "\n";
    $message .= "👤 *CUSTOMER:* " . $escaped_name . "\n";
    $message .= "🔗 *USERNAME:* " . $escaped_username . "\n";
    $message .= "📱 *PHONE:* " . $escaped_phone . "\n";
    $message .= "📧 *EMAIL:* " . $escaped_email . "\n";
    $message .= "📍 *LOCATION:* " . $escaped_location;

    $post_fields = [
        'chat_id' => $chat_id, 
        'text' => $message,
        'parse_mode' => 'MarkdownV2' 
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
    
    $result = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($curl_error) {
        $debug_info = curl_getinfo($ch);
        curl_close($ch);
        error_log("CURL TELEGRAM FAILED. Error: {$curl_error}. Details: Total Time: {$debug_info['total_time']}");
        return ['success' => false, 'message' => "cURL failed: {$curl_error}."];
    }

    curl_close($ch); 

    $tg_data = json_decode($result, true);
    if ($http_code !== 200 || !isset($tg_data['ok']) || $tg_data['ok'] !== true) {
        $tg_error = $tg_data['description'] ?? 'Unknown API error';
        error_log("Telegram API Error (HTTP {$http_code}): " . $tg_error);
        return ['success' => false, 'message' => "API failed (Code {$http_code}): " . $tg_error];
    }
    
    return ['success' => true, 'message' => 'Notification sent successfully.'];
}

$notification_result = sendTelegramMessage(
    $customer,
    $cart_details, // Passes the full cart details
    $amount,
    $transactionId
);

// ====================================================================
// 4. FINAL CLEANUP AND RESPONSE
// ====================================================================

// --- FIX 3: Clean the buffer right before the final response is sent.
if (ob_get_level() > 0) {
    ob_clean();
}

if ($notification_result['success']) {
    // Mark as completed and clear ALL order-related session data
    $_SESSION['transaction_completed'] = $postedMd5; 
    unset($_SESSION['qrData'], $_SESSION['transaction_id'], $_SESSION['amount'], $_SESSION['customer_info'], $_SESSION['cart_details'], $_SESSION['total_qty'], $_SESSION['cart']); 
    
    $conn->close();
    echo json_encode(['ok' => true, 'message' => 'Payment confirmed, stock updated for all items, and notification sent.']);
} else {
    // If notification fails, the stock has already been deducted.
    $_SESSION['transaction_completed'] = $postedMd5; 
    $conn->close();
    echo json_encode(['ok' => false, 'warning' => 'Stock deducted successfully for all items, but notification failed: ' . $notification_result['message']]);
}
// Cleanly end the output buffer.
ob_end_flush(); 
?>