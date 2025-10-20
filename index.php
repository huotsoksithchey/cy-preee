<?php
session_start();

// --- CATEGORY HIERARCHY DEFINITION ---
// This map defines the sub-categories visually in the Admin Add Product form.
$parent_categories = [
    'Clothing' => ['Clothing Boy', 'Clothing Girl'],
    'Shoes' => ['Shoes Boy', 'Shoes Girl'],
];

// --- DATABASE CONFIGURATION ---
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = ""; 
$DB_NAME = "shop_db";

// Enable MySQLi to throw exceptions for better error handling
if (class_exists('mysqli') && defined('MYSQLI_REPORT_ERROR')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

$conn = false;
try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn) {
        $conn->set_charset("utf8mb4");
    }
} catch (mysqli_sql_exception $e) {
    die("
        <div style='padding: 20px; border: 2px solid red; background-color: #fee; font-family: sans-serif;'>
            <h2>❌ CRITICAL DATABASE CONNECTION ERROR!</h2>
            <p>Your database connection failed. Error: " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>FIX:</strong> Ensure MySQL is running and your database 'shop_db' exists.</p>
        </div>
    ");
}

// --- ADMIN CREDENTIALS ---
$ADMIN_EMAIL = 'houtsoksithchey@gmail.com';
$ADMIN_PASSWORD_PLAINTEXT = '123chey';

$message = "";

// 1. Handle Login Attempt
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($email === $ADMIN_EMAIL && $password === $ADMIN_PASSWORD_PLAINTEXT) {
        $_SESSION['admin_logged_in'] = true;
        session_regenerate_id(true); 
        header("Location: index.php"); 
        exit(); 
    } else {
        $message = "❌ Invalid email or password.";
    }
}

// 2. Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// 3. Check Authentication and Display Login Form
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * { font-family: 'Inter', sans-serif; }
            body { background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        </style>
    </head>
    <body>
        <div class="w-full max-w-md">
            <div class="bg-white p-8 rounded-xl shadow-2xl border border-gray-100">
                <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Admin Panel Login</h2>
                
                <?php if (!empty($message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="index.php">
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($ADMIN_EMAIL) ?>" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" id="password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                    </div>
                    <div>
                        <button type="submit" name="login" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-lg shadow-sm text-lg font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                            Login
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit(); 
}

// --------------------------------------------------------------------
// CODE BELOW THIS LINE IS ONLY EXECUTED IF THE ADMIN IS LOGGED IN
// --------------------------------------------------------------------

$current_view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'products';
$target_dir_name = "product_images";
$target_dir = __DIR__ . DIRECTORY_SEPARATOR . $target_dir_name . DIRECTORY_SEPARATOR; 

// --- Category Setup ---
$categories_list = [];
$sub_category_names = [];
if ($conn) {
    $categories_list['All'] = 0; 
    try {
        $cat_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
        if ($cat_result) {
            while ($row = $cat_result->fetch_assoc()) {
                $categories_list[$row['name']] = $row['id'];
            }
            $cat_result->free();
        }
    } catch (mysqli_sql_exception $e) {
        $message .= "⚠️ Error fetching categories (Table 'categories' likely missing): " . htmlspecialchars($e->getMessage());
    }
}
foreach ($parent_categories as $subs) {
    $sub_category_names = array_merge($sub_category_names, $subs);
}


// --- GLOBAL ACTION HANDLERS (Product & User) ---

// 1. Handle User Deletion (NEW LOGIC)
if (isset($_POST['remove_user'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if ($user_id) {
        try {
            // Note: In a real app, you should check for associated orders and delete/reassign them first.
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $message = "🗑️ User ID {$user_id} removed successfully.";
                header("Location: index.php?view=users&msg=" . urlencode($message));
                exit();
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
             $message = "❌ Database error on user deletion: " . htmlspecialchars($e->getMessage());
        }
    }
}

// --------------------------------------------------------------------
// PRODUCT MANAGEMENT LOGIC
// --------------------------------------------------------------------
$products_result = null;
$total_products = 0;
$low_stock_count = 0;

if ($current_view === 'products') {
    
    $current_category_id = filter_input(INPUT_GET, 'cat_id', FILTER_VALIDATE_INT) ?? 0;
    $current_category_name = array_search($current_category_id, $categories_list) ?: 'Unknown Category';

    // 2. Handle Product Addition
    if (isset($_POST['add_product'])) {
        $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $stock_qty = filter_input(INPUT_POST, 'stock_qty', FILTER_VALIDATE_INT) ?? 0;
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $details = trim(filter_input(INPUT_POST, 'details', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $image_path_db = null;

        if ($name && $price !== false && $category_id !== false && $category_id > 0) {
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $image_name = basename($_FILES["image"]["name"]);
                $file_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
                $new_file_name = uniqid('prod_') . '.' . $file_extension;
                $image_path_db = $target_dir_name . '/' . $new_file_name;
                
                if (move_uploaded_file($_FILES["image"]["tmp_name"], __DIR__ . '/../' . $image_path_db)) {
                    // Success
                } else {
                    $message .= "⚠️ Error uploading file. Check 'product_images' folder permissions.";
                    $image_path_db = null; 
                }
            }
            
            try {
                $stmt = $conn->prepare("INSERT INTO products (name, price, stock_qty, category_id, image_path, details) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sdiiss", $name, $price, $stock_qty, $category_id, $image_path_db, $details);
                
                if ($stmt->execute()) {
                    $message = "✅ Product '{$name}' added successfully!";
                } else {
                    $message = "❌ Database error: " . htmlspecialchars($conn->error);
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                 $message = "❌ Database error on insert (Table 'products' likely missing): " . htmlspecialchars($e->getMessage());
            }
        } else {
            $message = "⚠️ Please fill out all required fields correctly (including selecting a category).";
        }
    }

    // 3. Handle Product Deletion and Stock Update
    if (isset($_POST['update_stock']) || isset($_POST['remove_product'])) {
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if ($product_id) {
            if (isset($_POST['remove_product'])) {
                try {
                    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->bind_param("i", $product_id);
                    if ($stmt->execute()) {
                        $message = "🗑️ Product ID {$product_id} removed.";
                        header("Location: index.php?view=products&cat_id={$current_category_id}&msg=" . urlencode($message));
                        exit();
                    }
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                     $message = "❌ Database error on delete: " . htmlspecialchars($e->getMessage());
                }
            
            } elseif (isset($_POST['update_stock'])) {
                $change = (int)$_POST['stock_change']; 
                try {
                    $stmt = $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
                    $stmt->bind_param("ii", $change, $product_id);
                    
                    if ($stmt->execute()) {
                        $message = "📈 Stock updated for product ID {$product_id}.";
                        header("Location: index.php?view=products&cat_id={$current_category_id}&msg=" . urlencode($message));
                        exit();
                    }
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                     $message = "❌ Database error on stock update: " . htmlspecialchars($e->getMessage());
                }
            }
        }
    }

    // 4. Handle Product Editing/Update 
    if (isset($_POST['edit_product'])) {
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $name = trim(filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $price = filter_input(INPUT_POST, 'edit_price', FILTER_VALIDATE_FLOAT);
        $stock_qty = filter_input(INPUT_POST, 'edit_stock_qty', FILTER_VALIDATE_INT);
        $category_id = filter_input(INPUT_POST, 'edit_category_id', FILTER_VALIDATE_INT);
        $details = trim(filter_input(INPUT_POST, 'edit_details', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $current_image_path = filter_input(INPUT_POST, 'current_image_path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $image_path_db = $current_image_path; 

        if ($product_id && $name && $price !== false && $category_id !== false && $category_id > 0) {
            
            if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] == 0) {
                $image_name = basename($_FILES["edit_image"]["name"]);
                $file_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
                $new_file_name = uniqid('prod_') . '.' . $file_extension;
                $image_path_db = $target_dir_name . '/' . $new_file_name;

                if (move_uploaded_file($_FILES["edit_image"]["tmp_name"], __DIR__ . '/../' . $image_path_db)) {
                    if ($current_image_path && file_exists(__DIR__ . '/../' . $current_image_path)) {
                        @unlink(__DIR__ . '/../' . $current_image_path);
                    }
                } else {
                    $image_path_db = $current_image_path; 
                }
            }
            
            try {
                $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, stock_qty = ?, category_id = ?, image_path = ?, details = ? WHERE id = ?");
                $stmt->bind_param("sdiissi", $name, $price, $stock_qty, $category_id, $image_path_db, $details, $product_id);
                
                if ($stmt->execute()) {
                    $message = "✅ Product ID **{$product_id}** updated successfully!";
                    header("Location: index.php?view=products&cat_id={$current_category_id}&msg=" . urlencode($message));
                    exit();
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                 $message = "❌ Database error on update: " . htmlspecialchars($e->getMessage());
            }
        }
    }

    // Check for and display messages passed via URL from the redirection
    if (isset($_GET['msg'])) {
        $message = urldecode($_GET['msg']);
    }

    // 5. Fetch Products & Stats
    $sql = "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id";
    $products_result = false;
    $stmt_products = null;

    if ($current_category_id !== 0) { 
        $sql .= " WHERE p.category_id = ?"; 
        $stmt_products = $conn->prepare($sql . " ORDER BY p.id DESC");
        $stmt_products->bind_param('i', $current_category_id);
        $stmt_products->execute(); 
        $products_result = $stmt_products->get_result();
    } else {
        $products_result = $conn->query($sql . " ORDER BY p.id DESC");
    }

    $total_products = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0] ?? 0;
    $low_stock_count = $conn->query("SELECT COUNT(*) FROM products WHERE stock_qty < 5")->fetch_row()[0] ?? 0;
} 

// --------------------------------------------------------------------
// USER MANAGEMENT LOGIC
// --------------------------------------------------------------------
$users_list = [];
$total_users = 0;
if ($current_view === 'users' && $conn) {
    try {
        $users_result = $conn->query("SELECT id, name, email, created_at FROM users ORDER BY created_at DESC");
        if ($users_result) {
            $total_users = $users_result->num_rows;
            while ($row = $users_result->fetch_assoc()) {
                $users_list[] = $row;
            }
            $users_result->free();
        }
    } catch (mysqli_sql_exception $e) {
        $message .= "⚠️ Error fetching users: " . htmlspecialchars($e->getMessage());
    }

    // Check for and display messages passed via URL from the redirection (user view)
    if (isset($_GET['msg'])) {
        $message = urldecode($_GET['msg']);
    }
}
// --------------------------------------------------------------------

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | <?= $current_view === 'users' ? 'User Management' : 'Product Management' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: #3b82f6; }
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #f9fafb; }
        .sidebar { transition: width 0.3s ease; width: 64px; }
        @media (min-width: 768px) { .sidebar:hover { width: 256px; } }
        .sidebar-item-text { opacity: 0; transition: opacity 0.3s ease; white-space: nowrap; }
        @media (min-width: 768px) { .sidebar:hover .sidebar-item-text { opacity: 1; } }
        .main-content { margin-left: 64px; transition: margin-left 0.3s ease; }
        @media (min-width: 768px) { .sidebar:hover ~ .main-content { margin-left: 256px; } }
        @media (max-width: 768px) { .sidebar { width: 0; display: none; } .main-content { margin-left: 0; } }
        .cat-btn-admin.active { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06); }
        .product-image { width: 48px; height: 48px; object-fit: cover; }
        .select-parent-option { font-weight: bold; background-color: #f3f4f6; color: #1f2937; }
    </style>
</head>
<body class="min-h-screen">

    <nav class="fixed top-0 left-0 right-0 h-14 bg-white shadow-lg z-30 flex items-center justify-between px-4">
        <a class="text-xl font-extrabold text-gray-800 tracking-wider" href="#">
            <i class="fas fa-layer-group text-primary-blue mr-2"></i> ADMIN CONSOLE
        </a>
        <div class="flex items-center space-x-4">
             <a href="../shopping/index.php" target="_blank" class="text-sm font-semibold text-gray-600 hover:text-primary-blue transition">
                 <i class="fas fa-store mr-1"></i> View Shop
             </a>
            <a class="text-sm font-semibold px-3 py-1 bg-red-500 text-white rounded-lg hover:bg-red-600 transition shadow-md" href="?logout=true">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="sidebar fixed top-14 bottom-0 left-0 bg-gray-800 z-20 flex-col p-2 text-gray-300 hover:shadow-xl hidden md:flex">
        <div class="sidebar-header border-b border-gray-700 py-3 mb-2">
            <span class="text-lg font-bold ml-1 text-white sidebar-item-text">FASHION SHOP</span>
        </div>
        <ul class="flex flex-col space-y-1">
            <li class="rounded-lg <?= $current_view === 'products' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>"> 
                <a class="flex items-center p-3 <?= $current_view === 'products' ? 'text-white font-semibold' : 'text-gray-300 hover:text-white' ?>" href="index.php?view=products">
                    <i class="fas fa-chart-bar w-6"></i>
                    <span class="ml-3 sidebar-item-text">Product Mgmt</span>
                </a>
            </li>
            
            <li class="rounded-lg <?= $current_view === 'users' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>"> 
                <a class="flex items-center p-3 <?= $current_view === 'users' ? 'text-white font-semibold' : 'text-gray-300 hover:text-white' ?>" href="index.php?view=users">
                    <i class="fas fa-users w-6"></i>
                    <span class="ml-3 sidebar-item-text">User Management</span>
                </a>
            </li>
            
            <?php if ($current_view === 'products'): ?>
                <?php 
                $category_icons = ['Clothing' => 'fas fa-tshirt', 'Shoes' => 'fas fa-shoe-prints', 'Accessories' => 'fas fa-glasses', 'Skin Care' => 'fas fa-spa', 'Pants' => 'fas fa-socks'];
                
                foreach ($categories_list as $name => $id): 
                    if ($id > 0):
                        $icon_class = $category_icons[$name] ?? 'fas fa-tag';
                ?>
                    <li class="rounded-lg hover:bg-gray-700">
                        <a class="flex items-center p-3 text-gray-300 hover:text-white" href="index.php?view=products&cat_id=<?= htmlspecialchars($id) ?>">
                            <i class="<?= htmlspecialchars($icon_class) ?> w-6"></i>
                            <span class="ml-3 sidebar-item-text"><?= htmlspecialchars($name) ?></span>
                        </a>
                    </li>
                <?php 
                    endif;
                endforeach; 
                ?>
            <?php endif; ?>
            </ul>
    </div>

    <div class="main-content pt-16 p-4 md:p-8 min-h-screen">
        
        <h1 class="text-3xl font-extrabold mb-6 text-gray-800">
            <?= $current_view === 'users' ? 'User Management' : 'Product Management Dashboard' ?>
        </h1>

        <?php if(!empty($message)): ?>
            <?php 
                $class = strpos($message, '✅') !== false || strpos($message, '📈') !== false || strpos($message, '🗑️') !== false ? 'bg-green-100 border-green-400 text-green-700' : (strpos($message, '❌') !== false ? 'bg-red-100 border-red-400 text-red-700' : 'bg-yellow-100 border-yellow-400 text-yellow-700');
            ?>
            <div class="p-4 mb-6 border-l-4 <?= $class ?> rounded-xl shadow-sm" role="alert">
                <p class="font-bold">Notification</p>
                <p><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($current_view === 'products'): ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-primary-blue">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Products</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $total_products ?></p>
                    </div>
                    <i class="fas fa-boxes text-3xl text-primary-blue opacity-70"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-yellow-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Low Stock Items (< 5)</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-1"><?= $low_stock_count ?></p>
                    </div>
                    <i class="fas fa-exclamation-triangle text-3xl text-yellow-500 opacity-70"></i>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-green-500 hover:bg-gray-50 transition duration-150 cursor-pointer" onclick="window.open('../shopping/index.php', '_blank');">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium text-gray-500">View Live Store</p>
                        <p class="text-3xl font-bold text-green-600 mt-1">Go Shopping <i class="fas fa-arrow-right text-xl ml-2"></i></p>
                    </div>
                    <i class="fas fa-store text-3xl text-green-500 opacity-70"></i>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">
                <i class="fas fa-plus-circle text-primary-blue mr-2"></i> Add New Product
            </h2>
            <form method="post" enctype="multipart/form-data">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="name" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" required>
                    </div>
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="category_id" id="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" required>
                            <option value="">Select Category</option>
                            <?php 
                            foreach ($categories_list as $name => $id): 
                                if ($id > 0):
                                    $is_parent = array_key_exists($name, $parent_categories);
                            ?>
                                <option 
                                    value="<?= htmlspecialchars($id) ?>" 
                                    <?= $is_parent ? 'disabled class="select-parent-option"' : 'class="font-semibold"' ?>
                                >
                                    <?= htmlspecialchars($name) ?> <?= $is_parent ? '(Parent)' : '' ?>
                                </option>
                                <?php 
                                    if ($is_parent):
                                        foreach ($parent_categories[$name] as $sub_name):
                                            if (isset($categories_list[$sub_name])):
                                ?>
                                                <option 
                                                    value="<?= htmlspecialchars($categories_list[$sub_name]) ?>" 
                                                    style="padding-left: 20px;"
                                                    class="text-gray-700"
                                                >
                                                    &nbsp;&nbsp;&nbsp;— <?= htmlspecialchars($sub_name) ?>
                                                </option>
                                <?php 
                                            endif;
                                        endforeach;
                                    endif;
                                endif;
                            endforeach; 
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700">Price ($)</label>
                        <input type="number" step="0.01" name="price" id="price" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" required>
                    </div>
                    <div>
                        <label for="stock_qty" class="block text-sm font-medium text-gray-700">Stock Qty</label>
                        <input type="number" name="stock_qty" id="stock_qty" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" value="0" required>
                    </div>
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700">Image</label>
                        <input type="file" name="image" id="image" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-2 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary-blue file:text-white hover:file:bg-blue-600">
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="details" class="block text-sm font-medium text-gray-700">Product Details</label>
                    <textarea name="details" id="details" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" placeholder="Enter a detailed description of the product..."></textarea>
                </div>
                <div class="mt-6">
                    <button type="submit" name="add_product" class="px-6 py-2 bg-primary-blue text-white font-semibold rounded-lg shadow-md hover:bg-blue-600 transition">
                        <i class="fas fa-save mr-2"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-800">
                <i class="fas fa-list-ul mr-2 text-primary-blue"></i> Product List 
                <span class="text-sm font-normal text-gray-500">(Category: <?= htmlspecialchars($current_category_name) ?>)</span>
            </h2>
            <div class="flex flex-wrap space-x-2 mt-4 md:mt-0">
                <?php foreach ($categories_list as $name => $id): ?>
                    <?php 
                    if (in_array($name, $sub_category_names)) continue; 
                    ?>
                    <a href="?view=products&cat_id=<?= htmlspecialchars($id) ?>" class="cat-btn-admin px-3 py-1 text-sm font-medium rounded-full 
                        <?= $current_category_id == $id ? 'bg-primary-blue text-white active shadow-blue-300/50' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 shadow-gray-300/50' ?>">
                        <?= htmlspecialchars($name) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                        <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    if ($products_result && $products_result->num_rows > 0):
                        while ($product = $products_result->fetch_assoc()):
                            $stock_class = $product['stock_qty'] < 5 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
                            $escaped_details = htmlspecialchars($product['details']); 
                    ?>
                    <tr class="hover:bg-gray-50 transition duration-150">
                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($product['id']) ?></td>
                        <td class="px-3 py-4 whitespace-nowrap">
                            <?php 
                            $image_src = $product['image_path'] ? '../' . htmlspecialchars($product['image_path']) : 'https://via.placeholder.com/48?text=N/A';
                            ?>
                            <img class="product-image rounded-lg shadow-sm" src="<?= $image_src ?>" alt="Product Image">
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold"><?= htmlspecialchars($product['name']) ?></td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($product['category_name']) ?></td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">$<?= number_format($product['price'], 2) ?></td>
                        <td class="px-3 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $stock_class ?>">
                                <?= htmlspecialchars($product['stock_qty']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-center space-x-2 flex justify-center items-center">
                            <button type="button" 
                                    class="text-primary-blue hover:text-blue-700 transition edit-product-btn" 
                                    title="Edit Product Details"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editProductModal"
                                    data-id="<?= htmlspecialchars($product['id']) ?>"
                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-price="<?= htmlspecialchars(number_format($product['price'], 2, '.', '')) ?>"
                                    data-stock-qty="<?= htmlspecialchars($product['stock_qty']) ?>"
                                    data-category-id="<?= htmlspecialchars($product['category_id']) ?>"
                                    data-details="<?= $escaped_details ?>"
                                    data-image-path="<?= htmlspecialchars($product['image_path']) ?>"
                            >
                                <i class="fas fa-edit text-lg"></i>
                            </button>
                            <form method="post" class="inline-block">
                                <input type="hidden" name="view" value="products">
                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                <input type="hidden" name="stock_change" value="1">
                                <button type="submit" name="update_stock" class="text-green-600 hover:text-green-900 transition" title="Add 1 Stock">
                                    <i class="fas fa-plus-square text-lg"></i>
                                </button>
                            </form>
                            <form method="post" class="inline-block">
                                <input type="hidden" name="view" value="products">
                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                <input type="hidden" name="stock_change" value="-1">
                                <button type="submit" name="update_stock" class="text-yellow-600 hover:text-yellow-900 transition" title="Deduct 1 Stock">
                                    <i class="fas fa-minus-square text-lg"></i>
                                </button>
                            </form>
                            <button type="button" class="text-red-600 hover:text-red-900 transition delete-product-btn" data-product-id="<?= htmlspecialchars($product['id']) ?>" data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal" title="Remove Product">
                                <i class="fas fa-trash text-lg"></i>
                            </button>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                        $products_result->free();
                    else:
                    ?>
                    <tr>
                        <td colspan="7" class="px-3 py-4 text-center text-gray-500">No products found in this category.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php elseif ($current_view === 'users'): ?>
        
        <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-primary-blue">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Registered Users</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $total_users ?></p>
                    </div>
                    <i class="fas fa-users text-3xl text-primary-blue opacity-70"></i>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg overflow-x-auto">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">
                <i class="fas fa-user-circle mr-2 text-primary-blue"></i> Registered Users List
            </h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered On</th>
                        <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($users_list)): ?>
                        <?php foreach ($users_list as $user): ?>
                        <tr class="hover:bg-gray-50 transition duration-150">
                            <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($user['id']) ?></td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold"><?= htmlspecialchars($user['name']) ?></td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('Y-m-d H:i:s', strtotime($user['created_at'])) ?></td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-center">
                                <button type="button" 
                                        class="text-red-600 hover:text-red-900 transition remove-user-btn" 
                                        data-user-id="<?= htmlspecialchars($user['id']) ?>" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteUserConfirmationModal" 
                                        title="Remove User">
                                    <i class="fas fa-trash text-lg"></i> Remove
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-gray-500">No users have registered yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
    </div>

    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-xl shadow-2xl">
          <div class="modal-header bg-primary-blue text-white rounded-t-xl border-b-0">
            <h5 class="modal-title font-bold" id="editProductModalLabel"><i class="fas fa-edit mr-2"></i> Edit Product: <span id="edit_product_id_display"></span></h5>
            <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="view" value="products">
            <div class="modal-body p-6">
                <input type="hidden" name="product_id" id="edit_product_id">
                <input type="hidden" name="current_image_path" id="edit_current_image_path">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                    <div>
                        <label for="edit_name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="edit_name" id="edit_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" required>
                    </div>
                    <div>
                        <label for="edit_category_id" class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="edit_category_id" id="edit_category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" required>
                            <option value="">Select Category</option>
                            <?php 
                            foreach ($categories_list as $name => $id): 
                                if ($id > 0):
                                    $is_parent = array_key_exists($name, $parent_categories);
                            ?>
                                <option 
                                    value="<?= htmlspecialchars($id) ?>" 
                                    <?= $is_parent ? 'disabled class="select-parent-option"' : 'class="font-semibold"' ?>
                                >
                                    <?= htmlspecialchars($name) ?> <?= $is_parent ? '(Parent)' : '' ?>
                                </option>
                                
                                <?php 
                                    if ($is_parent):
                                        foreach ($parent_categories[$name] as $sub_name):
                                            if (isset($categories_list[$sub_name])):
                                ?>
                                            <option 
                                                value="<?= htmlspecialchars($categories_list[$sub_name]) ?>" 
                                                style="padding-left: 20px;"
                                                class="text-gray-700"
                                            >
                                                &nbsp;&nbsp;&nbsp;— <?= htmlspecialchars($sub_name) ?>
                                            </option>
                                <?php 
                                        endif;
                                    endforeach;
                                endif;
                            endif;
                        endforeach; 
                        ?>
                        </select>
                    </div>
                    <div>
                        <label for="edit_price" class="block text-sm font-medium text-gray-700">Price ($)</label>
                        <input type="number" step="0.01" name="edit_price" id="edit_price" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" required>
                    </div>
                    <div>
                        <label for="edit_stock_qty" class="block text-sm font-medium text-gray-700">Stock Qty</label>
                        <input type="number" name="edit_stock_qty" id="edit_stock_qty" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" required>
                    </div>
                    <div class="flex flex-col">
                        <label for="edit_image" class="block text-sm font-medium text-gray-700">New Image</label>
                        <input type="file" name="edit_image" id="edit_image" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-2 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300">
                        <p class="text-xs text-gray-500 mt-1">Leave blank to keep current image.</p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="edit_details" class="block text-sm font-medium text-gray-700">Product Details</label>
                    <textarea name="edit_details" id="edit_details" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-primary-blue focus:border-primary-blue" placeholder="Enter a detailed description of the product..."></textarea>
                </div>

            </div>
            <div class="modal-footer justify-content-between p-4 bg-gray-50 rounded-b-xl border-t">
              <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 font-semibold rounded-lg shadow-md hover:bg-gray-400 transition" data-bs-dismiss="modal">
                  <i class="fas fa-times mr-2"></i> Cancel
              </button>
              <button type="submit" name="edit_product" class="px-4 py-2 bg-primary-blue text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition">
                  <i class="fas fa-save mr-2"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>


    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-xl shadow-2xl">
          <div class="modal-header bg-red-500 text-white rounded-t-xl border-b-0">
            <h5 class="modal-title font-bold" id="deleteConfirmationModalLabel"><i class="fas fa-exclamation-triangle mr-2"></i> PERMANENT PRODUCT REMOVAL</h5>
            <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-6">
            <p class="text-gray-700">Are you absolutely sure you want to **PERMANENTLY** remove this product (ID: <span id="modal_product_id_display" class="font-extrabold text-red-600"></span>)?</p>
          </div>
          <div class="modal-footer justify-content-between p-4 bg-gray-50 rounded-b-xl border-t">
            <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 font-semibold rounded-lg shadow-md hover:bg-gray-400 transition" data-bs-dismiss="modal">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
            <form method="post" class="inline-block">
                <input type="hidden" name="view" value="products">
                <input type="hidden" name="product_id" id="modal_product_id" value="">
                <button type="submit" name="remove_product" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition">
                    <i class="fas fa-trash mr-2"></i> Yes, Remove Product
                </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="deleteUserConfirmationModal" tabindex="-1" aria-labelledby="deleteUserConfirmationModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-xl shadow-2xl">
          <div class="modal-header bg-red-500 text-white rounded-t-xl border-b-0">
            <h5 class="modal-title font-bold" id="deleteUserConfirmationModalLabel"><i class="fas fa-exclamation-triangle mr-2"></i> PERMANENT USER REMOVAL</h5>
            <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-6">
            <p class="text-gray-700">Are you absolutely sure you want to **PERMANENTLY** remove User ID: <span id="modal_user_id_display" class="font-extrabold text-red-600"></span>?</p>
            <p class="mt-3 text-red-600 font-medium text-sm">This will delete the user's account permanently from the `users` table.</p>
          </div>
          <div class="modal-footer justify-content-between p-4 bg-gray-50 rounded-b-xl border-t">
            <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 font-semibold rounded-lg shadow-md hover:bg-gray-400 transition" data-bs-dismiss="modal">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
            <form method="post" class="inline-block">
                <input type="hidden" name="view" value="users">
                <input type="hidden" name="user_id" id="modal_user_id" value="">
                <button type="submit" name="remove_user" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition">
                    <i class="fas fa-trash mr-2"></i> Yes, Remove User
                </button>
            </form>
          </div>
        </div>
      </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- Product Delete Modal Handlers ---
            const deleteProductButtons = document.querySelectorAll('.delete-product-btn');
            const modalProductIdField = document.getElementById('modal_product_id');
            const modalProductIdDisplay = document.getElementById('modal_product_id_display');

            deleteProductButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const productId = this.getAttribute('data-product-id');
                    modalProductIdField.value = productId;
                    modalProductIdDisplay.textContent = productId; 
                });
            });

            // --- NEW: User Delete Modal Handlers ---
            const removeUserButtons = document.querySelectorAll('.remove-user-btn');
            const modalUserIdField = document.getElementById('modal_user_id');
            const modalUserIdDisplay = document.getElementById('modal_user_id_display');

            removeUserButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const userId = this.getAttribute('data-user-id');
                    modalUserIdField.value = userId;
                    modalUserIdDisplay.textContent = userId; 
                });
            });
            
            // --- Edit Product Modal Handlers ---
            const editButtons = document.querySelectorAll('.edit-product-btn');
            const editModalIdDisplay = document.getElementById('edit_product_id_display');
            const editModalId = document.getElementById('edit_product_id');
            const editModalName = document.getElementById('edit_name');
            const editModalPrice = document.getElementById('edit_price');
            const editModalStockQty = document.getElementById('edit_stock_qty');
            const editModalCategory = document.getElementById('edit_category_id');
            const editModalDetails = document.getElementById('edit_details');
            const editModalImagePath = document.getElementById('edit_current_image_path');

            editButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const productId = this.getAttribute('data-id');
                    const productName = this.getAttribute('data-name');
                    const productPrice = this.getAttribute('data-price');
                    const productStockQty = this.getAttribute('data-stock-qty');
                    const productCategoryId = this.getAttribute('data-category-id');
                    const productDetails = this.getAttribute('data-details');
                    const productImagePath = this.getAttribute('data-image-path');
                    
                    editModalIdDisplay.textContent = productId;
                    editModalId.value = productId;
                    editModalName.value = productName;
                    editModalPrice.value = productPrice;
                    editModalStockQty.value = productStockQty;
                    editModalCategory.value = productCategoryId;
                    editModalDetails.value = productDetails;
                    editModalImagePath.value = productImagePath;
                    
                    document.getElementById('edit_image').value = '';
                });
            });
        });
    </script>

</body>
</html>
<?php 
// Close the database connection at the end of the script
if (isset($stmt_products) && $stmt_products instanceof mysqli_stmt) {
    @$stmt_products->close();
}
if (isset($conn)) {
    @$conn->close();
}
?>