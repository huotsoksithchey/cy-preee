<?php
include "../includes/db.php";
$id = $_GET['id'] ?? 0;
$res = $conn->query("SELECT * FROM products WHERE id=$id");
$product = $res->fetch_assoc();
if(!$product){ echo "Product not found"; exit; }

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $amount = $product['price'];
    $md5 = md5(uniqid());
    $stmt = $conn->prepare("INSERT INTO orders (product_id, customer_name, email, phone, address, amount, md5) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("issssds",$id,$name,$email,$phone,$address,$amount,$md5);
    $stmt->execute();
    $stmt->close();
    header("Location: payment.php?product_id=$id&md5=$md5"); // Ensure product_id is passed
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Buy <?=htmlspecialchars($product['name'])?></title></head>
<body>
<h1>Buy <?=htmlspecialchars($product['name'])?></h1>
<form method="post">
Name: <input type="text" name="name" required><br><br>
Email: <input type="email" name="email" required><br><br>
Phone: <input type="text" name="phone" required><br><br>
Address:<br>
<textarea name="address" required></textarea><br><br>
<button type="submit">Proceed to Payment</button>
</form>
</body>
</html>