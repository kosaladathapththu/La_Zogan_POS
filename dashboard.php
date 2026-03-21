<?php
session_start();
include 'db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: login.php");
    exit;
}

$total_sales = $conn->query("SELECT SUM(total_amount) AS total FROM orders")->fetch_assoc()["total"];
$total_orders = $conn->query("SELECT COUNT(*) AS count FROM orders")->fetch_assoc()["count"];
$today_sales = $conn->query("SELECT SUM(total_amount) AS total FROM orders WHERE DATE(created_at) = CURDATE()")->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 8px rgba(0,0,0,0.08);
        }
        a { text-decoration: none; }
    </style>
</head>
<body>
    <h1>Admin Dashboard</h1>

    <div class="cards">
        <div class="card">
            <h3>Total Sales</h3>
            <p>Rs. <?php echo number_format($total_sales ?? 0, 2); ?></p>
        </div>
        <div class="card">
            <h3>Total Orders</h3>
            <p><?php echo $total_orders; ?></p>
        </div>
        <div class="card">
            <h3>Today Sales</h3>
            <p>Rs. <?php echo number_format($today_sales ?? 0, 2); ?></p>
        </div>
    </div>

    <p><a href="admin/products.php">Manage Products</a></p>
    <p><a href="admin/sales.php">View Sales</a></p>
</body>
</html>