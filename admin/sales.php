<?php
include '../includes/auth.php';
include '../db.php';

$sales = $conn->query("
    SELECT o.order_id, o.total_amount, o.payment_status, o.created_at,
           t.table_name, u.full_name
    FROM orders o
    JOIN restaurant_tables t ON o.table_id = t.table_id
    JOIN users u ON o.user_id = u.user_id
    ORDER BY o.order_id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Sales History</title>
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
            box-shadow: 0 0 8px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .top-links a {
            margin-right: 15px;
            text-decoration: none;
        }

        .view-btn {
            text-decoration: none;
            color: #1e88e5;
        }
    </style>
</head>
<body>
<div class="container">

    <div class="top-links">
        <a href="../dashboard.php">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="../logout.php">Logout</a>
    </div>

    <div class="card">
        <h1>Sales History</h1>

        <table>
            <tr>
                <th>Order ID</th>
                <th>Table</th>
                <th>Cashier</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
                <th>View</th>
            </tr>

            <?php while ($row = $sales->fetch_assoc()) { ?>
                <tr>
                    <td>#<?php echo $row['order_id']; ?></td>
                    <td><?php echo $row['table_name']; ?></td>
                    <td><?php echo $row['full_name']; ?></td>
                    <td>Rs. <?php echo number_format($row['total_amount'], 2); ?></td>
                    <td><?php echo ucfirst($row['payment_status']); ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td>
                        <a class="view-btn" href="view_sale.php?order_id=<?php echo $row['order_id']; ?>">
                            View
                        </a>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>

</div>
</body>
</html>