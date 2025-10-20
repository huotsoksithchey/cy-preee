<?php
// clearcart.php

// 1. Ensure the session starts so we can access session variables.
session_start();

// 2. Clear all cart data from the session.
if (isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
}

// OPTIONAL: This clears ALL session variables, which is safer for a full reset.
session_unset();

// OPTIONAL: Destroys the entire session and deletes the session cookie.
session_destroy();

// 3. Provide feedback to the user and redirect.
echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <title>Cart Reset</title>
        <meta http-equiv='refresh' content='3; url=index.php'> <style>
            body { font-family: sans-serif; text-align: center; padding: 50px; background-color: #f7f7f7; }
            .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); display: inline-block; }
            h2 { color: #28a745; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>✅ Cart Successfully Reset!</h2>
            <p>Your shopping cart session has been completely cleared.</p>
            <p>You will be redirected to the main shop page in 3 seconds, or click the link below:</p>
            <p><a href='index.php' style='color: #007bff; text-decoration: none; font-weight: bold;'>Continue Shopping</a></p>
        </div>
    </body>
    </html>
";
exit;
?>