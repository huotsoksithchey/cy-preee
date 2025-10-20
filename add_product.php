<?php
include "../includes/db.php";
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];
    $imgName = $_FILES['image']['name'];
    $tmpName = $_FILES['image']['tmp_name'];
    move_uploaded_file($tmpName, "../imgs/products/$imgName");
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, image) VALUES (?,?,?,?)");
    $stmt->bind_param("ssds",$name,$desc,$price,$imgName);
    $stmt->execute();
    $stmt->close();
    echo "<p style='color:green;'>Product added!</p>";
}
?>
<form method="post" enctype="multipart/form-data">
Name: <input type="text" name="name" required><br><br>
Description:<br><textarea name="description" required></textarea><br><br>
Price: <input type="number" name="price" step="0.01" required><br><br>
Image: <input type="file" name="image" required><br><br>
<button type="submit">Add Product</button>
</form>