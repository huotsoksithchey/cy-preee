<?php
// register.php (FINAL DESIGN - Copy & Paste Verification)
session_start();

// ត្រូវប្រាកដថាឯកសារនេះនៅផ្លូវត្រូវ (ឧទាហរណ៍: C:\xampp\htdocs\cy-pre\shopping\includes\db.php)
if (file_exists('../includes/db.php')) {
    include('../includes/db.php');
} else {
    // 🛑 ERROR FIX: បង្ហាញ Error ឱ្យច្បាស់ ជំនួសឱ្យ Blank Page
    die("Database connection file not found at '../includes/db.php'. Please check the path."); 
}

$error_message = '';
$input_data = ['name' => '', 'email' => '', 'phone' => ''];

function generateVerificationCode($length = 6) {
    return strval(mt_rand(100000, 999999));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (Code for handling POST request remains the same as before) ...
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? ''); 
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Format phone number
    if (strpos($phone, '855') === 0) {
        $phone = '+' . $phone;
    } elseif (strpos($phone, '+') !== 0) {
        $phone = '+' . $phone;
    }

    $input_data['name'] = $name;
    $input_data['email'] = $email;
    $input_data['phone'] = $phone;

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || $password !== $confirm_password) {
        $error_message = 'Please correct the errors in the form.';
    } else {
        // Check if email or phone number already exists
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone_number = ?");
        $stmt_check->bind_param("ss", $email, $phone);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error_message = 'This email or phone number is already registered.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verification_code = generateVerificationCode();
            
            // Insert user
            $stmt_insert = $conn->prepare("INSERT INTO users (name, email, phone_number, password_hash, verification_code, is_verified) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt_insert->bind_param("sssss", $name, $email, $phone, $hashed_password, $verification_code);

            if ($stmt_insert->execute()) {
                
                // Success: Redirect to verify page with the code displayed
                $_SESSION['pending_verification_phone'] = $phone;
                $_SESSION['display_code'] = $verification_code; 
                
                header('Location: verify.php?display=true');
                exit;
            } else {
                $error_message = 'Registration failed due to a server error: ' . $conn->error;
            }
        }
        if (isset($stmt_check)) $stmt_check->close();
    }
}
if (isset($conn)) $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Arial', sans-serif; }
        .register-container { width: 100%; max-width: 450px; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .form-label { font-weight: 600; color: #333; }
        .btn-dark { background-color: #007bff; border-color: #007bff; transition: background-color 0.3s; }
        .btn-dark:hover { background-color: #0056b3; border-color: #0056b3; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2 class="text-center mb-4 text-primary">🛍️ Register for Fashion Shop</h2>
        <p class="text-center text-muted mb-4">Create your account to start shopping.</p>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($input_data['name']) ?>" placeholder="Your full name" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($input_data['email']) ?>" placeholder="example@mail.com" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Phone (+855...)</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($input_data['phone']) ?>" placeholder="+85599xxxxxx" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Min 6 characters" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-dark w-100 mt-2">Create Account & Verify</button>
        </form>
        
        <p class="text-center text-muted mt-4">
            Already have an account? <a href="login.php" class="text-decoration-none">Login here</a>
        </p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>