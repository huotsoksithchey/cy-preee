<?php
// login.php (FINAL CODE)
session_start();

if (file_exists('../includes/db.php')) {
    include('../includes/db.php');
} else {
    die("Database connection file not found at '../includes/db.php'.");
}

$error_message = '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// 🛑 កូដថ្មីនេះបន្ថែមសម្រាប់សារត្រឡប់មកពី auth_check.php
if (isset($_GET['error']) && $_GET['error'] === 'reauth_needed') {
    $error_message = '⚠️ Security alert: Your account details were changed by the administrator. Please log in again to confirm your identity.';
}
// 🛑 បញ្ចប់កូដថ្មី

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error_message = 'Please enter your email/phone and password.';
    } else {
        $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $column = $is_email ? 'email' : 'phone_number';

        $stmt = $conn->prepare("SELECT id, password_hash, is_verified, name FROM users WHERE {$column} = ?");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['is_verified'] == 0) {
                $error_message = 'Your account has not been verified. Please verify your account first.';
            } elseif (password_verify($password, $user['password_hash'])) {
                // Login Success!
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                
                header('Location: index.php'); // Redirect to the main shopping page
                exit;
            } else {
                $error_message = 'Invalid email/phone or password.';
            }
        } else {
            $error_message = 'Invalid email/phone or password.';
        }
    }
}
if (isset($conn)) $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Arial', sans-serif; }
        .login-container { width: 100%; max-width: 400px; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .btn-primary { background-color: #007bff; border-color: #007bff; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
        .form-label { font-weight: 600; color: #333; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4 text-primary">👋 Welcome Back!</h2>
        <p class="text-center text-muted mb-4">Login to your Fashion Shop account.</p>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label for="identifier" class="form-label">Email or Phone Number</label>
                <input type="text" class="form-control" id="identifier" name="identifier" placeholder="Email or +855xxxxxx" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Your password" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mt-3">Log In</button>
        </form>
        
        <p class="text-center text-muted mt-4">
            Don't have an account? <a href="register.php" class="text-decoration-none">Register here</a>
        </p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>