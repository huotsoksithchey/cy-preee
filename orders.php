<?php
// orders.php - Orders Management Page
session_start();

// Security check: Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// CRITICAL: Database Connection
if (!file_exists('../includes/db.php')) {
    die("❌ Configuration Error: Database include file is missing.");
}
include('../includes/db.php');

// --- Order List Retrieval (FIXED SQL) ---
// Join orders table with products table to get the product name
$sql = "SELECT 
            o.id, 
            o.product_id, 
            o.customer_name, 
            o.customer_phone, 
            o.customer_address,
            o.quantity,
            o.amount, 
            o.transaction_id, 
            o.created_at,
            p.name AS product_name
        FROM orders o 
        LEFT JOIN products p ON o.product_id = p.id
        ORDER BY o.id DESC 
        LIMIT 50"; // Use o.id for sorting as o.created_at might not exist yet if you haven't run SQL fix
$orders_result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Orders Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }</style>
</head>
<body class="min-h-screen pt-16 p-4 md:p-8">

    <h1 class="text-3xl font-extrabold mb-6 text-gray-800">
        <i class="fas fa-truck text-blue-600 mr-2"></i> Orders Management
    </h1>
    
    <a href="index.php" class="text-blue-600 hover:text-blue-800 font-semibold mb-6 block">
        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
    </a>

    <div class="bg-white p-6 rounded-xl shadow-lg">
        <h2 class="text-xl font-semibold mb-4 border-b pb-2">Recent Orders</h2>
        
        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
            <p class="text-sm text-gray-500 mb-4">Showing the first <?= $orders_result->num_rows ?> orders from the database.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product / QTY</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Info</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount / Txn ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($row = $orders_result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['id']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <span class="font-semibold"><?= htmlspecialchars($row['product_name'] ?? 'ID: ' . $row['product_id']) ?></span><br>
                                    <span class="text-xs font-bold text-blue-600">QTY: <?= htmlspecialchars($row['quantity']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <span class="font-semibold"><?= htmlspecialchars($row['customer_name'] ?? 'N/A') ?></span><br>
                                    <span class="text-xs text-gray-500">📞 <?= htmlspecialchars($row['customer_phone'] ?? 'N/A') ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 max-w-xs overflow-hidden">
                                    <?= htmlspecialchars($row['customer_address'] ?? 'N/A') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="font-bold text-lg text-green-600">$<?= number_format($row['amount'] ?? 0, 2) ?></span><br>
                                    <span class="text-xs text-gray-500 font-mono">ID: <?= htmlspecialchars($row['transaction_id'] ?? 'N/A') ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M j, Y', strtotime($row['created_at'] ?? 'now')) ?><br>
                                    <span class="text-xs"><?= date('H:i:s', strtotime($row['created_at'] ?? 'now')) ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 p-4 border-l-4 border-yellow-400 text-yellow-700">
                <p class="font-bold">No Orders Found</p>
                <p>The system did not find any records in the 'orders' table. New orders will appear here after a customer successfully pays.</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
<?php 
if (isset($conn)) {
    $conn->close();
}
?>