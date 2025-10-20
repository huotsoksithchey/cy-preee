<?php
// verify.php (FINAL CODE - Copy & Paste Verification)
session_start();

if (file_exists('../includes/db.php')) {
    include('../includes/db.php');
} else {
    die("Database connection file not found at '../includes/db.php'.");
}

$error_message = '';
$phone_to_verify = $_SESSION['pending_verification_phone'] ?? null;
$display_code = $_SESSION['display_code'] ?? null;

if (!$phone_to_verify) {
    header('Location: register.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if (empty($code)) {
        $error_message = 'Please enter the verification code.';
    } else {
        // Verification logic
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone_number = ? AND verification_code = ? AND is_verified = 0");
        $stmt->bind_param("ss", $phone_to_verify, $code);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            // Success: Update status
            $stmt_update = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE phone_number = ?");
            $stmt_update->bind_param("s", $phone_to_verify);
            $stmt_update->execute();
            $stmt_update->close();

            unset($_SESSION['pending_verification_phone']);
            unset($_SESSION['display_code']);
            
            $_SESSION['success_message'] = '🎉 Account verified successfully! You can now log in.';
            header('Location: login.php');
            exit;
        } else {
            $error_message = 'Invalid verification code or account already verified.';
        }
    }
}
if (isset($conn)) $conn->close();

$show_code_form = (isset($_GET['display']) && $_GET['display'] == 'true');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Arial', sans-serif; }
        .container { width: 100%; max-width: 450px; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .code-display-box { background-color: #e9f7ef; border: 2px solid #28a745; padding: 25px; border-radius: 8px; }
        .code-display-box h3 { color: #28a745; font-size: 2.2rem; letter-spacing: 5px; }
        .btn-copy { background-color: #007bff; border-color: #007bff; }
        .btn-copy:hover { background-color: #0056b3; border-color: #0056b3; }
        .form-control-lg { font-size: 1.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4 text-success">🔐 Verify Your Account</h2>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if ($show_code_form && $display_code): ?>
            <div id="code-display" class="text-center code-display-box mb-4">
                <p class="mb-2">A code has been generated for: <strong><?= htmlspecialchars($phone_to_verify) ?></strong></p>
                <h3 class="mb-3">Code: <strong id="verification-code-value"><?= htmlspecialchars($display_code) ?></strong></h3>
                
                <button class="btn btn-copy w-100 btn-lg" onclick="copyCodeAndShowInput()">
                    <i class="fas fa-copy me-2"></i> COPY CODE & PASTE BELOW
                </button>
                <p class="text-muted mt-2">This code is valid for this session.</p>
            </div>
            
            <script>
                function copyCodeAndShowInput() {
                    const codeValue = document.getElementById('verification-code-value').innerText;
                    navigator.clipboard.writeText(codeValue).then(() => {
                        document.getElementById('code-display').style.display = 'none';
                        document.getElementById('code-input-form').style.display = 'block';
                        const codeInput = document.getElementById('code');
                        codeInput.value = codeValue;
                        codeInput.focus();
                    }).catch(err => {
                        alert('Failed to copy code. Please manually enter: ' + codeValue);
                        document.getElementById('code-display').style.display = 'none';
                        document.getElementById('code-input-form').style.display = 'block';
                        document.getElementById('code').value = codeValue;
                        document.getElementById('code').focus();
                    });
                }
            </script>
        <?php endif; ?>
        
        <form method="POST" action="verify.php" id="code-input-form" style="display: <?= ($show_code_form && $display_code) ? 'none' : 'block' ?>;">
            <div class="mb-4">
                <label for="code" class="form-label">Enter Verification Code</label>
                <input type="text" class="form-control form-control-lg text-center" id="code" name="code" placeholder="6-digit code" required maxlength="6" autofocus>
            </div>
            <button type="submit" class="btn btn-success w-100 btn-lg">Complete Verification</button>
            <p class="text-center mt-3 text-muted">
                <a href="register.php" class="text-decoration-none">Go back to registration</a>
            </p>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>