<?php
// index.php (Shopping Page - Modern Minimalist Design with Animation)

session_start(); 

// --- Logout Handling (NEW) ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
// -----------------------------

// --- CATEGORY HIERARCHY DEFINITION ---
// Keys are Parent categories. Values are the Sub-categories.
$parent_categories = [
    'Clothing' => ['Clothing Boy', 'Clothing Girl'],
    'Shoes' => ['Shoes Boy', 'Shoes Girl'],
];

// --- DATABASE INCLUSION & MOCK SETUP ---
// NOTE: This mock logic ensures the page works even if '../includes/db.php' is missing.
if (!file_exists('../includes/db.php')) {
    // ----------------------------------------------------------------------------------
    // MOCK DATABASE SETUP (Updated with separate IDs for sub-categories)
    // ----------------------------------------------------------------------------------
    $categories_mock_array = [
        1 => 'Clothing', 
        2 => 'Accessories', 
        3 => 'Shoes', 
        4 => 'Skin Care',
        5 => 'Clothing Boy',    // New Mock ID
        6 => 'Clothing Girl',   // New Mock ID
        7 => 'Shoes Boy',       // New Mock ID
        8 => 'Shoes Girl',      // New Mock ID
    ];
    
    // MOCK PRODUCTS (Updated category_id)
    $products_mock = [ // Changed variable name to avoid conflict
        // Linked to Parent: Clothing (ID 1)
        ['id' => 1, 'name' => 'Linen Blazer', 'price' => 149.99, 'image_path' => 'product_images/blazer.jpg', 'video_path' => null, 'category_id' => 1, 'details' => "A beautifully tailored linen blazer, perfect for spring and summer evenings. Breathable fabric and a modern fit.", 'stock_qty' => 10],
        ['id' => 2, 'name' => 'Flowy Dress (Girl)', 'price' => 89.99, 'image_path' => 'product_images/dress.jpg', 'video_path' => null, 'category_id' => 6, 'details' => "An elegant, ankle-length dress with a comfortable, flowy design. Ideal for casual outings or a beach vacation.\n\nMaterial: 100% Rayon\nCare: Machine Wash Cold", 'stock_qty' => 5], 
        ['id' => 3, 'name' => 'Leather Loafers (Boy)', 'price' => 110.00, 'image_path' => 'product_images/loafers.jpg', 'video_path' => null, 'category_id' => 7, 'details' => "Classic Italian leather loafers. Hand-stitched sole for superior comfort and durability. Available in black and brown.", 'stock_qty' => 12],
        
        // Linked to Parent: Shoes (ID 3)
        ['id' => 4, 'name' => 'Running Sneakers (Girl)', 'price' => 75.00, 'image_path' => 'product_images/earrings.jpg', 'video_path' => null, 'category_id' => 8, 'details' => "Lightweight and durable running shoes with memory foam insoles.", 'stock_qty' => 20],
        
        // Linked to Accessories/Skin Care
        ['id' => 5, 'name' => 'Gold Hoop Earrings', 'price' => 29.99, 'image_path' => 'product_images/earrings.jpg', 'video_path' => null, 'category_id' => 2, 'details' => "Hypoallergenic 18k gold-plated hoop earrings. Subtle yet stylish, perfect for daily wear.", 'stock_qty' => 50],
        ['id' => 6, 'name' => 'Hydrating Serum', 'price' => 45.00, 'image_path' => 'product_images/serum.jpg', 'video_path' => null, 'category_id' => 4, 'details' => "A powerful hydrating serum infused with Hyaluronic Acid and Vitamin C. Reduces fine lines and brightens skin tone. Use morning and night.", 'stock_qty' => 8],
    ];
    
    // Simple query simulation for mock data
    $conn = new class {
        public $categories_map = ['Clothing' => 1, 'Accessories' => 2, 'Shoes' => 3, 'Skin Care' => 4, 'Clothing Boy' => 5, 'Clothing Girl' => 6, 'Shoes Boy' => 7, 'Shoes Girl' => 8]; // Updated Mock map
        public $products_mock = []; // Store a copy of mock products here
        
        public function __construct() {
            $this->products_mock = $GLOBALS['products_mock'];
        }
        
        // Mock query function
        public function query($sql) {
            
            // --- MOCK CATEGORY LIST QUERY ---
            if (strpos($sql, 'SELECT id, name FROM categories') !== false) {
                 $mock_cats = array_map(function($id, $name) { return ['id' => $id, 'name' => $name]; }, array_keys($GLOBALS['categories_mock_array']), array_values($GLOBALS['categories_mock_array']));
                return (object)['num_rows' => count($mock_cats), 'fetch_assoc' => function() use (&$mock_cats) {
                    return array_shift($mock_cats);
                }];
            }
            
            // --- MOCK AUTH CHECK (Used by auth_check.php or fallback if mock is enabled) ---
            // Assumes user exists and is verified in mock environment
            if (strpos($sql, 'SELECT id FROM users WHERE id =') !== false) {
                 return (object)['num_rows' => 1, 'fetch_assoc' => function() { return ['id' => $_SESSION['user_id']]; }, 'close' => function() {}];
            }
            
            // --- MOCK PRODUCT LIST QUERY (Filtering) ---
            $current_selection_name = $_GET['cat'] ?? 'All';
            $base_category_to_filter = $current_selection_name;

            $categories_map = $this->categories_map;
            $parent_categories = $GLOBALS['parent_categories'];
            $is_parent_category = array_key_exists($base_category_to_filter, $parent_categories);

            $filtered_products = $this->products_mock;
            if ($base_category_to_filter !== 'All') {
                $target_id = $categories_map[$base_category_to_filter] ?? null;

                $filtered_products = array_filter($this->products_mock, function($p) use ($target_id, $is_parent_category, $categories_map, $parent_categories) {
                    if ($is_parent_category) {
                        // Logic for parent category filter
                        $sub_category_ids = array_filter(array_map(function($sub_name) use ($categories_map) {
                            return $categories_map[$sub_name] ?? null;
                        }, $parent_categories[$_GET['cat']]));
                        
                        return in_array($p['category_id'], array_merge([$target_id], $sub_category_ids));
                    }
                    // Filter by specific category ID (Sub-category or standalone)
                    return $p['category_id'] === $target_id;
                });
            }
            // --- MOCK PRODUCT LIST QUERY (Specific IDs for Cart Fetch) ---
            if (preg_match('/WHERE id IN \((.*?)\)/', $sql, $matches)) {
                $ids = array_map('intval', explode(',', $matches[1]));
                // FIX: Replaced PHP 7.4 arrow function (fn) with compatible anonymous function
                $filtered_products = array_filter($this->products_mock, function($p) use ($ids) {
                    return in_array($p['id'], $ids);
                });
            }
            
            $filtered_products = array_values($filtered_products);
            
            return (object)['num_rows' => count($filtered_products), 'fetch_assoc' => function() use (&$filtered_products) {
                return array_shift($filtered_products);
            }, 'close' => function() {}];
        }
        public function close() {}
        public function real_escape_string($str) { return $str; }
    };
    $products_list = $GLOBALS['products_mock']; // Ensure products list is available for cart fetch
} else {
    // REAL DATABASE CONNECTION
    include('../includes/db.php'); 
    $products_list = []; // Products fetched later
}

// ------------------------------------------------------------------
// --- FIX: ROBUST ACCESS CONTROL (AFTER $conn IS DEFINED) ---
// ------------------------------------------------------------------

// 1. If auth_check.php exists (it should), use it to verify the session against the DB
if (file_exists('auth_check.php')) {
    // THIS LINE WILL RUN THE CODE IN auth_check.php
    // If the user is deleted or unverified, auth_check.php will run header('Location: login.php') and exit.
    include('auth_check.php'); 
} else {
    // 2. Fallback: Simple Session Check if auth_check.php is missing (less secure)
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
// ------------------------------------------------------------------

// =====================================
// === CART MANAGEMENT LOGIC (ONLY FOR FALLBACK/FAILOVER) ===
// =====================================

// 1. ADD TO CART Logic (From product card) - KEPT FOR FALLBACK ONLY
if (isset($_POST['add_to_cart']) && isset($_POST['product_id'])) {
    $product_id_to_add = (int)$_POST['product_id'];
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$product_id_to_add])) {
        $_SESSION['cart'][$product_id_to_add] = (int)$_SESSION['cart'][$product_id_to_add] + 1;
    } else {
        $_SESSION['cart'][$product_id_to_add] = 1;
    }

    // Redirect to prevent form resubmission on refresh
    header("Location: index.php" . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
    exit;
}

// 2. REMOVE ITEM Logic (From Cart Modal) - KEPT FOR FALLBACK ONLY
if (isset($_POST['remove_item']) && isset($_POST['product_id_remove'])) {
    $product_id_remove = (int)$_POST['product_id_remove'];
    
    if (isset($_SESSION['cart'][$product_id_remove])) {
        unset($_SESSION['cart'][$product_id_remove]);
    }
    
    // Redirect to prevent form resubmission on refresh
    header("Location: index.php" . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
    exit;
}
// =====================================
// === END FALLBACK CART MANAGEMENT ===
// =====================================


// 3. CART FETCH AND CALCULATION (For Initial Modal Display - this will be overwritten by AJAX)
$cart_details = [];
$cart_total = 0.00;
$simple_cart = $_SESSION['cart'] ?? [];
$cart_count = count(array_keys($simple_cart)); 

if ($cart_count > 0) {
    $cart_product_ids = array_keys($simple_cart);
    $sql_in = implode(',', array_map('intval', $cart_product_ids));
    
    // Check if $conn is a valid object before using it
    if (isset($conn) && method_exists($conn, 'query')) {
        $result_cart = $conn->query("SELECT id, name, price, image_path, stock_qty FROM products WHERE id IN ({$sql_in})");

        if ($result_cart && $result_cart->num_rows > 0) {
            while ($product_data = $result_cart->fetch_assoc()) {
                $product_id = $product_data['id'];
                $qty = $simple_cart[$product_id]; 
                
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
}


// --- Category & Product Fetching Logic (Unchanged) ---
$categories_map = []; 
$categories_list = ['All']; 
$current_parent_category = null; 

if (isset($conn) && method_exists($conn, 'query')) {
    $cat_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($cat_result && $cat_result->num_rows > 0) {
        while ($row = $cat_result->fetch_assoc()) {
            $categories_map[$row['name']] = $row['id'];
            
            $is_sub_category = false;
            foreach ($parent_categories as $sub_cats) {
                if (in_array($row['name'], $sub_cats)) {
                    $is_sub_category = true;
                    break;
                }
            }
            if (!$is_sub_category) {
                $categories_list[] = $row['name'];
            }
        }
    }
} else {
    $categories_list = array_merge(['All'], array_filter(array_values($GLOBALS['categories_mock_array'] ?? []), function($name) use ($parent_categories) {
        foreach ($parent_categories as $sub_cats) {
            if (in_array($name, $sub_cats)) {
                return false;
            }
        }
        return true;
    }));
    // Note: The mock object itself should be accessed via $GLOBALS['conn'] to avoid scope issues in the mock context
    $categories_map = $GLOBALS['conn']->categories_map ?? [];
}

$current_selection_name = $_GET['cat'] ?? 'All'; 
$base_category_to_filter = $current_selection_name; 
$current_category_id = null; 

if (array_key_exists($current_selection_name, $parent_categories)) {
    $current_parent_category = $current_selection_name;
} else {
    foreach ($parent_categories as $parent_name => $sub_cats) {
        if (in_array($current_selection_name, $sub_cats)) {
            $base_category_to_filter = $current_selection_name;
            $current_parent_category = $parent_name; 
            break;
        }
    }
}

if ($base_category_to_filter !== 'All') {
    $current_category_id = $categories_map[$base_category_to_filter] ?? null; 
}

$result = (object)['num_rows' => 0]; 

if (isset($conn) && method_exists($conn, 'query')) {
    $sql = "SELECT id, name, price, image_path, details, category_id FROM products"; 

    if ($current_category_id !== null) {
        $safe_id = (int)$current_category_id;
        $filter_clause = " WHERE category_id = {$safe_id}";

        if (array_key_exists($base_category_to_filter, $parent_categories)) {
            $sub_category_ids = array_filter(array_map(function($sub_name) use ($categories_map) {
                return $categories_map[$sub_name] ?? null;
            }, $parent_categories[$base_category_to_filter]));
            
            if (!empty($sub_category_ids)) {
                $sub_id_list = implode(',', array_map('intval', $sub_category_ids));
                $filter_clause = " WHERE category_id = {$safe_id} OR category_id IN ({$sub_id_list})";
            } else {
                $filter_clause = " WHERE category_id = {$safe_id}";
            }
        }
        
        $sql .= $filter_clause;
    }
    
    $sql .= " ORDER BY name"; 
    $result = $conn->query($sql);

    if ($result === false && isset($GLOBALS['products_mock'])) {
        $result = $GLOBALS['conn']->query(""); 
    }

} elseif (isset($GLOBALS['products_mock'])) {
    $result = $GLOBALS['conn']->query(""); 
}
// --- End Category & Product Fetching Logic ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Fashion Shop | Modern Collection</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #333333;
            --accent-color: #cc0000; 
            --white: #ffffff;
            --light-gray: #f9f9f9;
        }

        * {
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }

        body {
            background-color: var(--white);
            color: var(--secondary-color);
        }
        
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }

        .hero-section {
            background-color: var(--primary-color); 
            padding: 100px 0 60px; 
            text-align: center;
            border-bottom: 1px solid var(--secondary-color);
        }
        .hero-section .container {
            min-height: 150px; 
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .glitch-text {
            font-size: 5em; 
            font-weight: 900;
            color: var(--white);
            position: relative;
            text-transform: uppercase;
            font-family: 'Montserrat', sans-serif; 
            text-shadow: 0 0 10px #ff00ff, 0 0 20px #00ffff; 
            animation: glitch-main 2s infinite alternate;
            line-height: 1; 
        }

        .glitch-text::before,
        .glitch-text::after {
            content: attr(data-text); 
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0; 
        }

        .glitch-text::before {
            left: 2px;
            text-shadow: -2px 0 #ff00ff; 
            animation: glitch-secondary-1 3.5s infinite alternate-reverse;
        }

        .glitch-text::after {
            left: -2px;
            text-shadow: 2px 0 #00ffff; 
            animation: glitch-secondary-2 3.5s infinite alternate;
        }

        @media (max-width: 768px) {
            .glitch-text {
                font-size: 3em;
            }
        }


        .category-filter {
            padding: 30px 0 20px; 
            text-align: center;
        }
        .cat-btn {
            border: 1px solid var(--secondary-color);
            color: var(--secondary-color);
            background-color: var(--white);
            padding: 8px 18px;
            margin: 5px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        .cat-btn:hover {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }
        .cat-btn.active {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }

        .sub-category-bar {
            background-color: #eee;
            padding: 15px 0;
            margin-top: 15px;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
        }
        .sub-cat-btn {
            padding: 6px 15px;
            margin: 0 5px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            background-color: #fff;
            color: var(--secondary-color);
            border: 1px solid #ccc;
            transition: all 0.3s; 
        }
        .sub-cat-btn:hover {
             transform: translateY(-2px); 
             box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .sub-cat-btn.active {
            background-color: var(--accent-color);
            color: var(--white);
            border-color: var(--accent-color);
        }
        
        .product-card {
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px); 
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }
        .product-image-wrapper {
            overflow: hidden;
            position: relative;
            padding-top: 133.33%; 
        }
        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        .product-info {
            padding: 15px 0 20px 0;
            text-align: center;
        }
        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 15px;
        }

        .buy-btn {
            background-color: var(--primary-color);
            color: var(--white);
            border: 1px solid var(--primary-color);
            border-radius: 0; 
            padding: 10px 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: background-color 0.3s, transform 0.3s, box-shadow 0.3s;
        }
        .buy-btn:hover {
            background-color: var(--secondary-color);
            color: var(--white);
            transform: scale(1.02); 
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Glitch Animation Keyframes */
        @keyframes glitch-main {
            0% { transform: translate(0); }
            20% { transform: translate(-3px, 3px); }
            40% { transform: translate(-3px, -3px); }
            60% { transform: translate(3px, 3px); }
            80% { transform: translate(3px, -3px); }
            100% { transform: translate(0); }
        }

        @keyframes glitch-secondary-1 {
            0% { clip: rect(44px, 9999px, 56px, 0); opacity: 0; }
            40% { clip: rect(44px, 9999px, 56px, 0); opacity: 1; }
            50% { clip: rect(110px, 9999px, 120px, 0); opacity: 0; }
            90% { clip: rect(110px, 9999px, 120px, 0); opacity: 1; }
            100% { clip: rect(0, 0, 0, 0); opacity: 0; }
        }

        @keyframes glitch-secondary-2 {
            0% { clip: rect(10px, 9999px, 20px, 0); opacity: 1; }
            30% { clip: rect(10px, 9999px, 20px, 0); opacity: 0; }
            40% { clip: rect(70px, 9999px, 80px, 0); opacity: 1; }
            80% { clip: rect(70px, 9999px, 80px, 0); opacity: 0; }
            100% { clip: rect(0, 0, 0, 0); opacity: 0; }
        }
        
        .contact-icon-btn {
            background-color: var(--primary-color);
            color: var(--white);
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        .contact-icon-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
        }
        .facebook-btn { background-color: #1877f2; border-color: #1877f2; }
        .telegram-btn { background-color: #0088cc; border-color: #0088cc; }
        
        .cart-item-row {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .cart-item-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            margin-right: 15px;
            border-radius: 4px;
        }
        .cart-item-details {
            flex-grow: 1;
        }
        .cart-item-name {
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
        }
        .cart-item-price {
            font-size: 0.9rem;
            color: #666;
        }
        .cart-item-actions {
            text-align: right;
            min-width: 100px;
        }
        #cart-modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-top: 1px solid #eee;
        }
        #cart-total-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-color);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg bg-white sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">SHOPPING</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="?cat=All">Shop</a></li>
                    <li class="nav-item"><a id="contact-link" class="nav-link" href="#contact-section">Contact</a></li>
                    
                    <li class="nav-item d-none d-lg-block"> | </li> 
                    
                    <li class="nav-item me-3">
                         <a class="nav-link btn btn-sm contact-icon-btn" 
                            href="account.php" 
                            style="background-color: var(--secondary-color); color: var(--white); padding: 5px 12px; font-weight: 600; border-radius: 4px;">
                            <i class="fas fa-user-circle me-1"></i> Account
                        </a>
                    </li>
                    <li class="nav-item">
                         <span class="navbar-text me-2" style="font-size: 0.9rem;">
                            Hello, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></strong>!
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="?logout=true" style="font-weight: 700; color: var(--accent-color);">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#shoppingCartModal" style="font-weight: 700;">
                            <i class="fas fa-shopping-cart"></i> Cart (<span id="cart-item-count"><?= $cart_count ?></span>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero-section">
        <div class="container">
            <div class="glitch-text" data-text="JUST DO IT">CY SHOPPING WEBSITE TRUSTY AND SAFETY</div>
        </div>
    </div>
    
    <div class="category-filter">
        <div class="container">
            <?php foreach ($categories_list as $label): ?>
                <a href="?cat=<?= urlencode($label) ?>" class="btn cat-btn <?= ($base_category_to_filter === $label && !array_key_exists($label, $parent_categories)) || $current_parent_category === $label ? 'active' : '' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php 
    $sub_cats_to_display = [];
    $active_sub_label = $current_selection_name;

    if ($current_parent_category !== null && array_key_exists($current_parent_category, $parent_categories)) {
           $sub_cats_to_display = $parent_categories[$current_parent_category];
    }
    
    if (!empty($sub_cats_to_display)): 
    ?>
    <div class="sub-category-bar">
        <div class="container text-center">
            <h6 class="text-muted mb-3" style="font-weight: 500;">Filter **<?= htmlspecialchars($current_parent_category) ?>** by:</h6>
            <?php foreach ($sub_cats_to_display as $sub_label): ?>
                <a href="?cat=<?= urlencode($sub_label) ?>" class="btn sub-cat-btn <?= ($active_sub_label === $sub_label) ? 'active' : '' ?>">
                    <?= htmlspecialchars($sub_label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="container py-4">
        <p class="mt-4 text-center text-muted" style="font-size: 0.9rem;">
            
        </p>
        
        <?php if($result->num_rows == 0): ?>
            <div class="alert alert-warning text-center p-5 my-5 shadow-sm">
                <h2><i class="fas fa-exclamation-triangle"></i> No Products Found!</h2>
                <p class="lead">No products match the category: <strong><?= htmlspecialchars($base_category_to_filter) ?></strong>.</p>
            </div>
        <?php else: ?>
            <h3 class="text-center mb-5" style="font-weight: 300; letter-spacing: 3px;">
                <?= ($base_category_to_filter === 'All' ? 'ALL PRODUCTS' : strtoupper(htmlspecialchars($base_category_to_filter)) . ' COLLECTION') ?>
            </h3>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4 mb-5">
                <?php 
                if (method_exists($result, 'fetch_assoc')): 
                    while($product = $result->fetch_assoc()): 
                        $video_src = null; 
                        // FIX: Removed "../admin/" from the image path
                        $image_src = "../" . htmlspecialchars($product['image_path']); 
                ?>
                    <div class="col">
                        <div class="card product-card">
                            <div class="product-image-wrapper">
                                <?php if (!empty($video_src)): ?>
                                    <video autoplay loop muted playsinline 
                                        class="product-image" 
                                        poster="<?= $image_src ?>" 
                                        title="<?= htmlspecialchars($product['name']) ?>">
                                        <source src="<?= htmlspecialchars($video_src) ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php else: ?>
                                    <img src="<?= $image_src ?>" 
                                        alt="<?= htmlspecialchars($product['name']) ?>" 
                                        class="product-image" 
                                        loading="lazy" 
                                        onerror="this.onerror=null; this.src='https://via.placeholder.com/600x800?text=No+Image';"> 
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info card-body">
                                <h5 class="product-name"><?= htmlspecialchars($product['name']) ?></h5>
                                <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                                
                                <div class="d-grid gap-2 d-sm-flex justify-content-center">
                                    <button type="button" 
                                        class="btn btn-outline-dark detail-btn w-100"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#productDetailModal"
                                        data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                        data-product-details="<?= htmlspecialchars($product['details'] ?? 'No details provided.') ?>"
                                        style="border-radius: 0; padding: 10px 15px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                        <i class="fas fa-info-circle me-1"></i> Details
                                    </button>
                                    <form method="post" class="d-grid w-100 add-to-cart-form">
                                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                        <input type="hidden" name="add_to_cart" value="1"> 
                                        <button type="submit" class="buy-btn btn" style="background-color: var(--secondary-color);">
                                            ADD TO CART
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile; 
                endif; 
                ?> 
            </div>
        <?php endif; ?>
    </div>
    
    <div id="contact-section" class="container py-5 my-5 text-center border-top">
        <h3 class="display-6 fw-bold mb-4" style="color: var(--primary-color);">Get In Touch</h3>
        <p class="lead mb-4" style="font-weight: 400;">Connect with us instantly via our direct messaging channels.</p>
        
        <div class="d-flex justify-content-center flex-column flex-sm-row gap-3">
            <a href="https://www.facebook.com/share/17KB2ZNU2s/?mibextid=wwXIfr" target="_blank" class="btn btn-lg contact-icon-btn facebook-btn">
                <i class="fab fa-facebook-f me-2"></i> Message on Facebook
            </a>
            <a href="https://t.me/cheycutie123" target="_blank" class="btn btn-lg contact-icon-btn telegram-btn">
                <i class="fab fa-telegram-plane me-2"></i> Chat on Telegram
            </a>
        </div>
        
    </div>
    <footer class="bg-light border-top mt-5">
        <div class="container text-center py-4">
            <p class="mb-0 text-muted">  CS SHOPPING.</p>
        </div>
    </footer>
    
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-muted" id="productDetailModalLabel">Product Details & Description</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h4 id="modal-product-name" class="fw-bold mb-3 text-dark"></h4>
        <div id="modal-product-details" class="text-secondary" style="white-space: pre-wrap; line-height: 1.6;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="shoppingCartModal" tabindex="-1" aria-labelledby="shoppingCartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="shoppingCartModalLabel"><i class="fas fa-shopping-cart me-2"></i> Your Shopping Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info text-center" id="cart-initial-message">
                    Loading cart contents...
                </div>
            </div>
            <div class="modal-footer justify-content-between" id="cart-modal-footer">
                 </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Function to dynamically load and display the cart contents via AJAX
        async function loadCartModalContent() {
            const shoppingCartModal = document.getElementById('shoppingCartModal');
            const modalBody = shoppingCartModal.querySelector('.modal-body');
            const modalFooter = document.getElementById('cart-modal-footer');

            // Set loading state
            modalBody.innerHTML = '<div class="text-center p-5"><i class="fas fa-spinner fa-spin me-2"></i> Loading cart contents...</div>';
            modalFooter.innerHTML = ''; 
            document.getElementById('cart-item-count').textContent = '...';

            try {
                // Fetch the updated cart contents from the new dedicated endpoint
                const response = await fetch('fetch_cart_modal.php'); 
                if (!response.ok) {
                    throw new Error(`Failed to fetch cart data (Status: ${response.status})`);
                }
                const data = await response.json();
                
                // Update the DOM elements with fresh HTML
                if (data.body_html) {
                    modalBody.innerHTML = data.body_html;
                }
                if (data.footer_html) {
                    modalFooter.innerHTML = data.footer_html;
                }
                document.getElementById('cart-item-count').textContent = data.cart_count;
                
                // Re-attach the AJAX listeners to the newly created "Remove" buttons
                attachRemoveItemListeners();

            } catch (error) {
                console.error('Error loading cart modal:', error);
                modalBody.innerHTML = '<div class="alert alert-danger text-center">Error loading cart. Please check console.</div>';
                document.getElementById('cart-item-count').textContent = '0';
            }
        }
        
        // Function to attach listeners for the dynamically created "Remove" forms
        function attachRemoveItemListeners() {
            document.querySelectorAll('.remove-from-cart-form').forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault(); // Stop the reload

                    const formData = new FormData(this);
                    const removeButton = this.querySelector('.btn-sm');
                    const originalText = removeButton.innerHTML;
                    
                    removeButton.disabled = true;
                    removeButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                    try {
                        // Send data to the new remove handler file
                        const response = await fetch('remove_from_cart.php', { 
                            method: 'POST',
                            body: formData
                        });
                        
                        if (!response.ok) {
                             throw new Error('Server removal failed');
                        }
                        const data = await response.json();
                        
                        if (data.success) {
                            // After successful removal, reload the whole modal content
                            await loadCartModalContent(); 
                        } else {
                            alert('Failed to remove item: ' + (data.error || 'Unknown error.'));
                            removeButton.innerHTML = originalText;
                            removeButton.disabled = false;
                        }
                    } catch (error) {
                        console.error('AJAX Removal Error:', error);
                        alert('An error occurred during cart removal. Check console.');
                        removeButton.innerHTML = originalText;
                        removeButton.disabled = false;
                    }
                });
            });
        }


        document.addEventListener('DOMContentLoaded', function() {
            
            // ===============================================
            // === 1. ADD TO CART AJAX FUNCTIONALITY (NO RELOAD) ===
            // ===============================================
            document.querySelectorAll('.add-to-cart-form').forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault(); 
                    
                    const formData = new FormData(this);
                    const submitButton = this.querySelector('.buy-btn');
                    const originalText = submitButton.textContent;
                    const originalBgColor = submitButton.style.backgroundColor;

                    submitButton.disabled = true;
                    submitButton.textContent = 'Adding...';

                    try {
                        const response = await fetch('add_to_cart.php', { // Path to the new handler file
                            method: 'POST',
                            body: formData
                        });

                        if (!response.ok) {
                            throw new Error(`Server status: ${response.status}`);
                        }
                        const data = await response.json();

                        if (data.success) {
                            // 1. Update the Cart Count
                            document.getElementById('cart-item-count').textContent = data.cart_count;
                            
                            // 2. Visual feedback
                            submitButton.textContent = 'ADDED!';
                            submitButton.style.backgroundColor = '#28a745'; 
                            
                            // 3. Reset button after a short delay
                            setTimeout(() => {
                                submitButton.textContent = originalText;
                                submitButton.style.backgroundColor = originalBgColor || ''; 
                                submitButton.disabled = false;
                            }, 1000);
                            
                        } else {
                            alert('Failed to add item: ' + (data.error || 'Unknown error.'));
                            submitButton.textContent = originalText;
                            submitButton.style.backgroundColor = originalBgColor || '';
                            submitButton.disabled = false;
                        }

                    } catch (error) {
                        console.error('AJAX Error:', error);
                        alert('An error occurred during cart addition. Check console.');
                        submitButton.textContent = originalText;
                        submitButton.style.backgroundColor = originalBgColor || '';
                        submitButton.disabled = false;
                    }
                });
            });


            // ===============================================
            // === 2. DYNAMICALLY LOAD CART MODAL CONTENT ===
            // ===============================================
            const shoppingCartModal = document.getElementById('shoppingCartModal');
            
            if (shoppingCartModal) {
                // Intercept the modal show event 
                shoppingCartModal.addEventListener('show.bs.modal', function(event) {
                    // Load the content every time the modal is opened
                    loadCartModalContent(); 
                });
                
                // Note: The listener for attaching remove item forms is inside loadCartModalContent
            }


            // --- Other JS (Detail Modal, Contact Link) ---
            const contactLink = document.getElementById('contact-link');
            const contactSection = document.getElementById('contact-section');

            if (contactLink && contactSection) {
                contactLink.addEventListener('click', function(e) {
                    e.preventDefault(); 
                    
                    contactSection.scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            }

            const detailModal = document.getElementById('productDetailModal');
            if (detailModal) {
                detailModal.addEventListener('show.bs.modal', event => {
                    const button = event.relatedTarget;
                    
                    const productName = button.getAttribute('data-product-name');
                    const productDetails = button.getAttribute('data-product-details');

                    const modalTitle = detailModal.querySelector('#modal-product-name');
                    const modalBodyDetails = detailModal.querySelector('#modal-product-details');

                    modalTitle.textContent = productName;
                    modalBodyDetails.textContent = productDetails;
                });
            }
        });
    </script>
    </body>
</html>
<?php 
// Close the database connection at the end of the script
if (isset($conn) && method_exists($conn, 'close')) {
    @$conn->close(); 
}
?>