<?php
// account.php - Secure User Profile Viewer (FINAL FIX: Includes Registration Date and Time)

session_start();

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// --- 2. Database Connection and Data Fetch ---
if (file_exists('../includes/db.php')) {
    include('../includes/db.php'); 
} else {
    die("Error: Database connection file '../includes/db.php' not found. Cannot load user data.");
}

$user_id = (int)$_SESSION['user_id'];
$error_fetching = false;

if (isset($conn) && $conn instanceof mysqli) {
    
    // Selecting name, email, phone_number, and registration_date
    $stmt = $conn->prepare("SELECT name, email, phone_number, registration_date FROM users WHERE id = ?");
    
    if ($stmt === false) {
        $error_fetching = true;
    } else {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user_data = $result->fetch_assoc();
            
            // Assign fetched data to variables
            $user_name = htmlspecialchars($user_data['name'] ?? 'N/A');
            $user_email = htmlspecialchars($user_data['email'] ?? 'N/A');
            $user_phone = htmlspecialchars($user_data['phone_number'] ?? 'N/A');
            
            // ✅ FIX: Format the date and TIME using 'F d, Y \a\t h:i A'
            if (isset($user_data['registration_date']) && !empty($user_data['registration_date'])) {
                 $user_registration_date = date('F d, Y \a\t h:i A', strtotime($user_data['registration_date']));
            } else {
                 $user_registration_date = 'Date/Time not set for this user';
            }
            
        } else {
            session_destroy();
            header('Location: login.php?error=user_data_missing');
            exit;
        }
        $stmt->close();
    }
    $conn->close();
} else {
    $error_fetching = true;
}

if ($error_fetching) {
    // Fallback if DB connection failed
    $user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User (Error)');
    $user_email = 'Error fetching data';
    $user_phone = 'Error fetching data';
    $user_registration_date = 'DB Connection Error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | Shopping</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .account-card { 
            max-width: 600px; 
            margin: 50px auto; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .form-control-static {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: #fff;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="card account-card">
            <div class="card-header bg-primary text-white text-center py-3">
                <i class="fas fa-user-circle fa-2x me-2"></i>
                <h4 class="d-inline-block mb-0">Hello, <?= $user_name ?></h4>
            </div>
            <div class="card-body p-4">
                <h5 class="card-title mb-4">Account Information</h5>
                
                <?php if ($error_fetching): ?>
                     <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle"></i> Database error. Check your connection file.
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label fw-bold">Full Name:</label>
                    <p class="form-control-static"><?= $user_name ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Email Address:</label>
                    <p class="form-control-static"><?= $user_email ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Phone Number:</label>
                    <p class="form-control-static"><?= $user_phone ?></p>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold">Member Since (Date & Time):</label>
                    <p class="form-control-static"><?= $user_registration_date ?></p>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag me-1"></i> Continue Shopping
                    </a>
                    <a href="index.php?logout=true" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>