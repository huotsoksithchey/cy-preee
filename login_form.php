<?php
// Note: We use the existing $message and configuration variables 
// from index.php which included this file.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3f51b5; /* Indigo */
            --danger-color: #f44336;
            --bg-light: #f4f6f9;
            --bg-white: #ffffff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        * {
            box-sizing: border-box;
            font-family: 'Roboto', sans-serif;
        }
        body {
            background-color: var(--bg-light);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            background: var(--bg-white);
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .login-container h2 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .form-control {
            margin-bottom: 20px;
        }
        .form-control label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            color: #555;
        }
        .form-control input[type="text"],
        .form-control input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-control input:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .btn-login {
            width: 100%;
            background: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 700;
            transition: background-color 0.3s;
        }
        .btn-login:hover {
            background: #303f9f;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-weight: 700;
            text-align: center;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Panel Login</h2>
        <?php if (!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-control">
                <label for="email">Email:</label>
                <input type="text" name="email" id="email" required value="<?= htmlspecialchars($ADMIN_EMAIL) ?>">
            </div>
            <div class="form-control">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit" name="login" class="btn-login">Login</button>
        </form>
    </div>
</body>
</html>