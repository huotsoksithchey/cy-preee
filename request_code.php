<?php
// request_code.php (Simulates sending a verification code)
session_start();

$message = '';
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_input = $_POST['email'] ?? '';
    
    if (filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        // --- MOCK: Code Generation and Storage ---
        // In a real system, you'd check if the email exists, generate a secure code,
        // store the code/expiry in the database, and use PHPMailer to send the email.
        
        $mock_verification_code = rand(100000, 999999);
        
        // Store the code and target email in the session for the next step (Mock DB)
        $_SESSION['verification_target_email'] = $email_input;
        $_SESSION['mock_verification_code'] = $mock_verification_code; 
        
        // Redirect to the verification page with a flag
        header('Location: verify_code.php?sent=true');
        exit;
    } else {
        $message = 'Please enter a valid email address.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Code</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container-box { width: 100%; max-width: 400px; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container-box">
        <h2 class="text-center mb-4">Request Verification Code</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <p class="text-center text-muted">Enter your email to receive a login verification code.</p>

        <form method="POST" action="request_code.php">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email_input) ?>" required>
            </div>
            <button type="submit" class="btn btn-dark w-100 mb-3">Send Code</button>
        </form>
        
        <p class="text-center"><a href="login.php">Back to Login</a></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>