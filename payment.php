<?php
// ====================================================================
// File: payment.php (Main Payment Page) - MODIFIED FOR SHOPPING CART
// ====================================================================

session_start();
// Security Check: Ensure required files exist
if (!file_exists('../includes/db.php') || !file_exists('notify_telegram.php')) {
    die("❌ Configuration Error: Required includes are missing.");
}
include('../includes/db.php');

// --- Configuration ---
$bakongid = "tey_takakvitya@wing";
$merchantname = "CHHEANSMM";
$qrData = null; // Initialize $qrData
$stock_error_message = ""; // Variable to hold cart-wide stock errors

// ====================================================================
// CRITICAL FIX 1: Handle Resetting the Order / Clearing Session
// ====================================================================
if (isset($_GET['reset']) && $_GET['reset'] == 1) {
    // Clear all payment-in-progress session data
    unset($_SESSION['qrData']);
    unset($_SESSION['amount']);
    unset($_SESSION['transaction_id']);
    unset($_SESSION['cart_details']); // NEW: Clear the stored cart details
    unset($_SESSION['total_qty']);    // NEW: Clear total quantity
    // Keep 'customer_info' but it will be updated on the next submit
    
    // Redirect to clean the URL
    header('Location: payment.php');
    exit;
}

// --- CSRF Token Generation ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}

// --- Function to safely initialize cURL ---
function initialize_curl($url) {
    $ch = curl_init($url);
    if ($ch === false) { return false; }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    return $ch;
}

// ====================================================================
// 2. Get CART Info (Multi-Product Logic)
// ====================================================================
$cart_data = $_SESSION['cart'] ?? [];
if (empty($cart_data)) {
    die("❌ Your cart is empty. Please add items before proceeding to payment.");
}

$product_ids = array_keys($cart_data);
// Sanitize product IDs for the SQL IN clause
$sql_in = implode(',', array_map('intval', $product_ids));

$cart_details = [];
$total_amount = 0.00;
$total_qty = 0;
$stock_has_error = false;

// Fetch all product details from the database
$result = $conn->query("SELECT id, name, price, stock_qty FROM products WHERE id IN ({$sql_in})");

if ($result && $result->num_rows > 0) {
    while ($product = $result->fetch_assoc()) {
        $id = (int)$product['id'];
        $qty = $cart_data[$id];
        $stock = (int)$product['stock_qty'];
        
        if ($qty > $stock) {
            $stock_error_message .= "❌ **Stock Error:** You requested **{$qty}** units of **{$product['name']}**, but only **{$stock}** units are available. Please return to the cart and adjust.<br>";
            $stock_has_error = true;
        }
        
        $line_total = (float)$product['price'] * $qty;
        $total_amount += $line_total;
        $total_qty += $qty;
        
        $cart_details[$id] = [
            'id' => $id,
            'name' => $product['name'],
            'price' => (float)$product['price'], // Unit price
            'qty' => $qty,
            'line_total' => $line_total,
            'stock_qty' => $stock
        ];
    }
} else {
    die("❌ Error fetching product details for your cart.");
}

// Store critical final calculated data in session for `notify_telegram.php`
$_SESSION['cart_details'] = $cart_details;
$_SESSION['amount'] = $total_amount;
$_SESSION['total_qty'] = $total_qty;


// ====================================================================
// CRITICAL FIX 2: Aggressive Session Cleanup on Fresh Load
// ====================================================================
$is_payment_in_session = isset($_SESSION['qrData']) && isset($_SESSION['transaction_id']);

if (!isset($_POST['pay']) && !isset($_GET['check']) && $is_payment_in_session) {
    // If the user lands here on a fresh GET request, clear the previous QR to start over.
    unset($_SESSION['qrData']);
    unset($_SESSION['amount']);
    unset($_SESSION['transaction_id']);
    $is_payment_in_session = false;
}

// ====================================================================
// 3. Handle Form Submission to Generate QR Code
// ====================================================================
if (isset($_POST['pay'])) {
    
    // ... (CSRF logic) ...
    $submitted_token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';

    if (!hash_equals($session_token, $submitted_token)) {
        unset($_SESSION['csrf_token']); 
        die("⚠️ Security error: Invalid CSRF token. Please refresh and try again.");
    }
    
    // --- INPUT VALIDATION AND SANITIZATION ---
    $customer_name = trim(filter_input(INPUT_POST, 'customer_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_NUMBER_INT) ?? '');
    $location = trim(filter_input(INPUT_POST, 'location', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');

    // Validate email
    $email_raw = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $email = $email_raw === false ? '' : trim($email_raw); 
    
    // We get quantity/amount from the session, not the form.
    $amount = $_SESSION['amount'] ?? 0;
    
    // Store customer info 
    $_SESSION['customer_info'] = [
        'name' => $customer_name, 'username' => $username, 'phone' => $phone,
        'email' => $email, 'location' => $location,
        'cart_summary' => $cart_details // Store final cart summary
    ];

    if (!$customer_name || !$username || !$phone || !$location || $email_raw === false) {
        $stock_error_message .= "⚠️ Please ensure all required fields are filled correctly.";
    } elseif ($stock_has_error) {
        // Stock already checked in section 2, just rely on that error message.
    } else {
        
        // Stock is OK (total_amount is calculated based on cart_details)
        $url = "https://api.kunchhunlichhean.org/khqr/create?amount=" . urlencode($amount) . "&bakongid=" . urlencode($bakongid) . "&merchantname=" . urlencode($merchantname);

        $ch = initialize_curl($url);
        if ($ch === false) { $qrData = ["error" => "QR API call failed to initialize."]; }
        else {
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $qrData = ["error" => "QR API Curl Error: $error"];
            } elseif ($http_code === 429) { 
                 $qrData = ["error" => "The payment service is busy. Please click 'Proceed to QR Payment' again right now to retry."];
            } elseif ($http_code !== 200) { 
                $qrData = ["error" => "QR API returned HTTP status code: $http_code. Server issue."];
            } else { 
                
                $qrData = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $qrData = ["error" => "Invalid JSON response from QR API: " . json_last_error_msg()];
                } elseif (empty($qrData['qr']) || empty($qrData['md5']) || empty($qrData['tran'])) {
                    $qrData = ["error" => "❌ QR generation failed. Missing required identifiers."];
                } else {
                    // QR Success: Store all final transaction data in session
                    unset($_SESSION['last_payment_time']);
                    $_SESSION['transaction_id'] = $qrData['tran'];
                    $_SESSION['qrData'] = $qrData; 
                    // $_SESSION['amount'] is already set from total_amount
                    // $_SESSION['cart_details'] is already set
                    $stock_error_message = ""; 
                    $is_payment_in_session = true;
                }
            }
        }
    }
}

// ====================================================================
// 4. Handle API Check for Payment Status (AJAX call)
// ====================================================================
if (isset($_GET['check'])) {
    $md5 = filter_input(INPUT_GET, 'md5', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $check_bakongid = filter_input(INPUT_GET, 'bakongid', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    
    $checkUrl = "https://api.kunchhunlichhean.org/check_by_md5?md5=" . urlencode($md5) . "&bakongid=" . urlencode($check_bakongid);

    $ch = initialize_curl($checkUrl);
    if ($ch === false) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Curl initialization failed for check API."]);
        exit;
    }

    $result = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    header('Content-Type: application/json');
    if ($error) {
        echo json_encode(["error" => "Curl Error: $error", "responseCode" => $http_code]);
    } else {
        $data = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(["error" => "Invalid JSON response from API.", "responseCode" => $http_code]);
        } else {
            $data['http_code'] = $http_code; 
            echo json_encode($data); 
        }
    }
    exit;
}

// Load QR data from session if it exists and form wasn't just submitted with an error
if (empty($qrData) && empty($stock_error_message) && $is_payment_in_session) {
    $qrData = $_SESSION['qrData'] ?? null;
}

// Final calculated amount and total quantity for display
$amount_for_display = $_SESSION['amount'] ?? $total_amount;
$qty_for_button = $_SESSION['total_qty'] ?? $total_qty;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment | Shopping Cart</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #212121;
            --accent-color: #FF6B6B;
            --secondary-accent: #4ECDC4;
            --light-bg: #f8f8f8;
            --white: #ffffff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --form-bg: #f0f4f8; 
            --error-bg: #fff0f0;
            --error-text: #cc0000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: var(--primary-color);
            line-height: 1.6;
            padding: 40px 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .checkout-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
        }

        @media (min-width: 768px) {
            .checkout-grid {
                grid-template-columns: 3fr 2fr;
            }
        }
        
        .customer-form-section {
            background: var(--white);
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--accent-color);
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            text-align: left;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            border-color: var(--accent-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }

        .input-row {
            display: flex;
            gap: 20px;
        }

        .input-row > .form-group {
            flex: 1;
        }

        .submit-btn {
            display: block;
            width: 100%;
            padding: 16px;
            margin-top: 20px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
        }
        
        .submit-btn:hover {
            background-color: var(--secondary-accent);
        }
        
        .qr-section {
            background: var(--form-bg);
            padding: 30px 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
            height: fit-content;
        }
        
        .qr-section img {
            max-width: 200px;
            height: auto;
            border: 5px solid var(--white);
            border-radius: 10px;
            margin: 20px auto;
            display: block;
        }

        .qr-section h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
        }

        #status { 
            margin-top: 15px; 
            font-weight: 700; 
            font-size: 1.1rem;
        }
        #timer { 
            color: #555; 
            margin-top: 5px; 
            font-weight: 500;
        }
        
        .detail-row {
            padding: 5px 0;
            font-size: 0.9rem;
            color: #555;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dotted #ccc;
        }

        .detail-row strong {
            color: var(--primary-color);
        }
        
        .cart-item-row {
            padding: 8px 0;
            border-bottom: 1px dotted #ccc;
        }
        .cart-item-row .name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .cart-item-row .price {
            font-size: 0.85rem;
            color: #777;
        }

        .error-message {
            padding: 20px;
            background: var(--error-bg);
            color: var(--error-text);
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            font-weight: 500;
        }

        .fade-out { 
            opacity: 0; 
            transition: opacity 1s ease; 
        }
        
        .product-summary {
            padding: 15px;
            border-radius: 8px;
            background-color: #e9e9e9;
            margin-top: 20px;
            text-align: left;
        }
        
        .product-summary h4 {
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: var(--accent-color);
        }
        
        .product-summary p {
            margin: 0 0 5px 0;
            font-size: 0.95rem;
        }
        
        .total-price-row {
            padding-top: 10px;
            margin-top: 10px;
            border-top: 2px solid #333;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="checkout-header">
            <h1>Complete Your Cart Order</h1>
            <p>Secure payment via Bakong ID: **<?= htmlspecialchars($bakongid) ?>**</p>
        </div>

        <div class="checkout-grid">
            
            <div class="customer-form-section">
                
                <?php if (!empty($stock_error_message)): ?>
                    <div class="error-message">
                        <?= $stock_error_message ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$qrData): 
                    // This section displays if $qrData is NULL/Empty 
                ?>
                    <div class="form-title">1. Your Details</div>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        
                        <div class="form-group">
                            <label for="customer_name">Full Name</label>
                            <input type="text" id="customer_name" name="customer_name" required value="<?= htmlspecialchars($_SESSION['customer_info']['name'] ?? '') ?>">
                        </div>
                        
                        <div class="input-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" placeholder="e.g., 012 345 678" required value="<?= htmlspecialchars($_SESSION['customer_info']['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="username">Telegram/Contact User</label>
                                <input type="text" id="username" name="username" placeholder="e.g., @your_username" required value="<?= htmlspecialchars($_SESSION['customer_info']['username'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_SESSION['customer_info']['email'] ?? '') ?>"> 
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Shipping Location</label>
                            <input type="text" id="location" name="location" placeholder="e.g., House No, Street, City" required value="<?= htmlspecialchars($_SESSION['customer_info']['location'] ?? '') ?>">
                        </div>
                        
                        <button type="submit" name="pay" class="submit-btn">
                            Proceed to QR Payment (<?= $qty_for_button ?> Items, $<?= htmlspecialchars(number_format($amount_for_display, 2)) ?>)
                        </button>
                    </form>
                <?php else: ?>
                    <div class="form-title">Order Summary</div>
                    
                    <div class="detail-row"><span>Total Items:</span> <strong><?= htmlspecialchars($qty_for_button) ?></strong></div>
                    <div class="detail-row total-price-row"><span>Total Price:</span> <strong style="color: var(--accent-color);">$<?= htmlspecialchars(number_format($amount_for_display, 2)) ?></strong></div>
                    
                    <div style="margin-top: 20px; padding-bottom: 10px; border-bottom: 2px solid #eee;">
                        <h5 style="font-size: 1rem; font-weight: 600; color: var(--primary-color);">Customer Info</h5>
                    </div>
                    <div class="detail-row"><span>Name:</span> <strong><?= htmlspecialchars($_SESSION['customer_info']['name'] ?? 'N/A') ?></strong></div>
                    <div class="detail-row"><span>Contact:</span> <strong><?= htmlspecialchars($_SESSION['customer_info']['username'] ?? 'N/A') ?></strong></div>
                    <div class="detail-row"><span>Shipping:</span> <strong><?= htmlspecialchars($_SESSION['customer_info']['location'] ?? 'N/A') ?></strong></div>
                    
                    <a href="?reset=1" class="submit-btn" style="background-color: #333; margin-top: 30px;">
                        Cancel Order / Change Details
                    </a>
                    
                <?php endif; ?>
            </div>
            
            <div class="qr-section">
                
                <?php if (!empty($qrData) && !empty($qrData['qr'])): ?>
                    <div id="qr-section">
                        <h3>2. Scan to Pay</h3>
                        <p>Total Due: <strong style="color: var(--accent-color);">$<?= htmlspecialchars(number_format($_SESSION['amount'] ?? 0, 2)) ?></strong></p>
                        
                        <img src="<?= htmlspecialchars($qrData['qr']) ?>" alt="QR Code">
                        
                        <div id="status" style="color: orange;">⏳ Waiting for payment...</div>
                        <div id="timer">⏰ Expires in <span id="time-left">60</span> seconds</div>
                        
                        <div class="detail-row" style="margin-top: 15px; border: none; font-size: 0.85rem;">
                            <span>Transaction ID:</span> 
                            <strong><?= htmlspecialchars($_SESSION['transaction_id'] ?? 'N/A') ?></strong>
                        </div>
                        
                        <a href="?reset=1" class="submit-btn" style="background-color: #888; margin-top: 20px; padding: 10px;">
                            Cancel QR / Start New Order
                        </a>
                    </div>
                <?php elseif (!empty($qrData['error'])): ?>
                    <div class="error-message">
                        <h4>Payment Error</h4>
                        <p>The system failed to generate a QR code. Please check your network connection or try again.</p>
                        <p><strong>Reason:</strong> <?= htmlspecialchars($qrData['error']) ?></p>
                    </div>
                    <a href="?reset=1" class="submit-btn" style="background-color: #333;">Try Again / Start New Order</a>
                <?php else: ?>
                    <div class="product-summary">
                        <h4>Order Preview (<?= $qty_for_button ?> Items)</h4>
                        <?php foreach($cart_details as $item): ?>
                            <div class="cart-item-row">
                                <p class="name"><?= htmlspecialchars($item['name']) ?></p>
                                <p class="price">Qty: <?= $item['qty'] ?> x $<?= number_format($item['price'], 2) ?> = $<?= number_format($item['line_total'], 2) ?></p>
                            </div>
                        <?php endforeach; ?>
                        <div class="total-price-row d-flex justify-content-between">
                            <span>Order Total:</span>
                            <span style="color: var(--accent-color);">$<?= number_format($total_amount, 2) ?></span>
                        </div>
                        <p style="margin-top: 15px;">Fill out your details on the left to complete your purchase.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($qrData) && !empty($qrData['qr'])): ?>
            <script>
                // === JS LOGIC (No changes needed, already correct) ===
                const md5 = "<?= htmlspecialchars($qrData['md5']) ?>";
                const bakongid = "<?= htmlspecialchars($bakongid) ?>";
                
                const statusEl = document.getElementById("status");
                const timeEl = document.getElementById("time-left");
                const qrSection = document.getElementById("qr-section");
                
                let timeLeft = 60;
                let paymentConfirmed = false; 
                let notificationAttempted = false; 
                let checkInterval = null;
                let timer = null; 

                async function notifyServer() {
                    if (notificationAttempted) return;
                    notificationAttempted = true;

                    statusEl.innerHTML = "✅ Payment confirmed. Notifying server (stock deduction)...";
                    statusEl.style.color = "green";
                    
                    try {
                        const form = new FormData();
                        form.append('action', 'notify');
                        form.append('md5', md5);
                        
                        // NOTE: This now calls the multi-product version of notify_telegram.php
                        const notifyResp = await fetch('notify_telegram.php', { 
                            method: 'POST', 
                            body: form 
                        });
                        
                        const notifyJson = await notifyResp.json();
                        
                        if (notifyJson && notifyJson.ok) {
                            statusEl.innerHTML = '✅ Order complete! Redirecting...';
                            qrSection.classList.add('fade-out');
                            clearInterval(checkInterval); 
                            clearInterval(timer);         
                            
                            // Redirect to success.php, which should clean up final session data
                            setTimeout(() => { window.location.href = 'success.php?order_complete=1'; }, 1000);
                        } else {
                            console.error('Notification failed:', notifyJson.error || notifyJson.warning);
                            statusEl.style.color = 'orange';
                            statusEl.innerHTML = `⚠️ Payment confirmed but notification failed: ${notifyJson.error || notifyJson.warning || 'Unknown error'}. Please contact support.`;
                            notificationAttempted = false; 
                        }
                    } catch (err) {
                        statusEl.style.color = 'red';
                        statusEl.innerHTML = '⚠️ Error communicating with the notification server. Please contact support.';
                        console.error('Error notifying server:', err);
                        notificationAttempted = false;
                    }
                }

                async function checkPaymentStatus() {
                    if (paymentConfirmed || timeLeft <= 0) {
                        clearInterval(checkInterval);
                        return;
                    }

                    try {
                        const response = await fetch(`?check=1&md5=${md5}&bakongid=${bakongid}`);
                        const data = await response.json();
                        
                        const responseCode = data.responseCode; 
                        
                        if (responseCode === 0) {
                            if (paymentConfirmed) return; 
                            paymentConfirmed = true; 
                            clearInterval(checkInterval); 
                            
                            await notifyServer();

                        } else {
                            if (statusEl.innerHTML.includes('Waiting')) {
                                statusEl.innerHTML = "⏳ Waiting for payment...";
                                statusEl.style.color = "orange";
                            }
                        }
                    } catch (err) {
                        console.error("Error during status check:", err);
                    }
                }
                
                checkInterval = setInterval(checkPaymentStatus, 5000); 

                timer = setInterval(() => {
                    if (paymentConfirmed || timeLeft <= 0) {
                        clearInterval(timer);
                        return;
                    }
                    timeLeft--;
                    timeEl.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(checkInterval); 
                        statusEl.style.color = "red";
                        statusEl.innerHTML = "❌ QR expired! Please <a href='?reset=1' style='color:red; font-weight:bold;'>refresh the page</a> to generate a new QR.";
                        qrSection.classList.add("fade-out");
                    }
                }, 1000);
            </script>
            <?php endif; ?>
                
        </div> 
    </div>
</body>
</html>
<?php 
if (isset($conn)) {
    $conn->close(); 
}
?>