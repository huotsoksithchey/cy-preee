<?php
include "../includes/db.php";
$res = $conn->query("SELECT * FROM products");
?>
<!DOCTYPE html>
<html>
<head><title>Manage Products</title></head>
<body>
<h1>Manage Products</h1>
<table border="1" cellpadding="5">
<tr><th>ID</th><th>Name</th><th>Price</th><th>Image</th><th>Action</th></tr>
<?php while($row=$res->fetch_assoc()): ?>
<tr>
<td><?=$row['id']?></td>
<td><?=$row['name']?></td>
<td><?=$row['price']?></td>
<td><img src="../imgs/products/<?=$row['image']?>" width="50"></td>
<td><a href="delete_product.php?id=<?=$row['id']?>">Delete</a></td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>