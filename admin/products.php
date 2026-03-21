<?php
include '../includes/auth.php';
include '../db.php';

/* ADD PRODUCT */
if (isset($_POST['add_product'])) {
    $category_name = trim($_POST['category_name']);
    $product_name = trim($_POST['product_name']);
    $price = (float) $_POST['price'];

    if ($category_name != "" && $product_name != "" && $price > 0) {

        // Check if category already exists
        $category_name_safe = mysqli_real_escape_string($conn, $category_name);
        $product_name_safe = mysqli_real_escape_string($conn, $product_name);

        $checkCategory = $conn->query("SELECT category_id FROM categories WHERE category_name = '$category_name_safe' LIMIT 1");

        if ($checkCategory->num_rows > 0) {
            $cat = $checkCategory->fetch_assoc();
            $category_id = $cat['category_id'];
        } else {
            // Insert new category
            $conn->query("INSERT INTO categories (category_name, status) VALUES ('$category_name_safe', 1)");
            $category_id = $conn->insert_id;
        }

        // Insert product
        $sql = "INSERT INTO products (category_id, product_name, price, status)
                VALUES ($category_id, '$product_name_safe', $price, 1)";
        $conn->query($sql);
    }

    header("Location: products.php");
    exit;
}

/* DELETE PRODUCT */
if (isset($_GET['delete'])) {
    $product_id = (int) $_GET['delete'];
    $conn->query("DELETE FROM products WHERE product_id = $product_id");
    header("Location: products.php");
    exit;
}

$products = $conn->query("
    SELECT p.*, c.category_name
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Products</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 0 8px rgba(0,0,0,0.08);
        }

        h1, h2 {
            margin-top: 0;
        }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: center;
        }

        input, button {
            padding: 10px;
            font-size: 14px;
        }

        button {
            background: #1e88e5;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .delete-btn {
            color: red;
            text-decoration: none;
        }

        .top-links a {
            margin-right: 15px;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">

    <div class="top-links">
        <a href="../dashboard.php">Dashboard</a>
        <a href="sales.php">Sales</a>
        <a href="../logout.php">Logout</a>
    </div>

    <div class="card">
        <h1>Manage Products</h1>

        <form method="POST">
            <input type="text" name="category_name" placeholder="Enter Category" required>
            <input type="text" name="product_name" placeholder="Product Name" required>
            <input type="number" step="0.01" name="price" placeholder="Price" required>
            <button type="submit" name="add_product">Add Product</button>
        </form>
    </div>

    <div class="card">
        <h2>Product List</h2>

        <table>
            <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Product</th>
                <th>Price</th>
                <th>Status</th>
                <th>Action</th>
            </tr>

            <?php while ($row = $products->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $row['product_id']; ?></td>
                    <td><?php echo $row['category_name']; ?></td>
                    <td><?php echo $row['product_name']; ?></td>
                    <td>Rs. <?php echo number_format($row['price'], 2); ?></td>
                    <td><?php echo $row['status'] ? 'Active' : 'Inactive'; ?></td>
                    <td>
                        <a class="delete-btn"
                           href="products.php?delete=<?php echo $row['product_id']; ?>"
                           onclick="return confirm('Delete this product?')">
                           Delete
                        </a>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>

</div>
</body>
</html>