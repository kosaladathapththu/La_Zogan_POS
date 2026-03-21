<?php
include 'db.php';

$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;

if ($order_id <= 0) {
    die("Invalid order ID.");
}

$order_sql = "
    SELECT o.*, t.table_name, u.full_name
    FROM orders o
    LEFT JOIN restaurant_tables t ON o.table_id = t.table_id
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
";

$order_stmt = $conn->prepare($order_sql);
if (!$order_stmt) {
    die("Prepare failed: " . $conn->error);
}

$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order = $order_result->fetch_assoc();
$order_stmt->close();

if (!$order) {
    die("Order not found.");
}

$item_sql = "
    SELECT oi.*, p.product_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
";

$item_stmt = $conn->prepare($item_sql);
if (!$item_stmt) {
    die("Prepare failed: " . $conn->error);
}

$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$items = $item_stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Print Bill</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        .bill-container {
            width: 360px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.08);
        }

        .header {
            text-align: center;
            border-bottom: 1px dashed #999;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }

        .logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .restaurant-name {
            font-size: 26px;
            font-weight: bold;
            margin: 5px 0;
        }

        .tagline {
            font-size: 14px;
            font-style: italic;
            color: #555;
            margin-bottom: 6px;
        }

        .address {
            font-size: 14px;
            color: #444;
            line-height: 1.5;
        }

        .order-info {
            margin: 15px 0;
            font-size: 15px;
            line-height: 1.8;
        }

        .label {
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 8px 6px;
            border-bottom: 1px dashed #999;
            text-align: left;
        }

        th:nth-child(2),
        td:nth-child(2),
        th:nth-child(3),
        td:nth-child(3) {
            text-align: center;
        }

        .summary-table {
            margin-top: 12px;
        }

        .summary-table td {
            border-bottom: none;
            padding: 6px 0;
        }

        .summary-table td:last-child {
            text-align: right;
        }

        .total-row td {
            font-weight: bold;
            font-size: 16px;
            border-top: 1px dashed #999;
            padding-top: 10px;
        }

        .footer {
            text-align: center;
            margin-top: 18px;
            font-size: 14px;
            color: #444;
        }

        .print-btn {
            width: 100%;
            padding: 12px;
            margin-top: 18px;
            background: #1e88e5;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
        }

        .print-btn:hover {
            background: #1565c0;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .bill-container {
                box-shadow: none;
                border-radius: 0;
                width: 100%;
                margin: 0;
                padding: 10px;
            }

            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="bill-container">
    <div class="header">
        <img src="assets/images/logo.jpg" alt="The La-Zogan Logo" class="logo">
        <div class="restaurant-name">The La-Zogan</div>
        <div class="tagline">The La-Zogan</div>
        <div class="address">
            Anuradapura Road,<br>
            Magulagama, Padeniya <br>
            Tel: 070 070 550
        </div>
    </div>

    <div class="order-info">
        <span class="label">Order #:</span> <?php echo $order["order_id"]; ?><br>
        <span class="label">Order Type:</span> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order["order_type"]))); ?><br>
        <span class="label">Table:</span> <?php echo !empty($order["table_name"]) ? htmlspecialchars($order["table_name"]) : "N/A"; ?><br>
        <span class="label">Cashier:</span> <?php echo htmlspecialchars($order["full_name"]); ?><br>
        <span class="label">Payment Method:</span> <?php echo htmlspecialchars($order["payment_method"]); ?><br>
        <span class="label">Date:</span> <?php echo htmlspecialchars($order["created_at"]); ?>
    </div>

    <table>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Total</th>
        </tr>

        <?php while ($item = $items->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($item["product_name"]); ?></td>
            <td><?php echo (int)$item["quantity"]; ?></td>
            <td><?php echo number_format($item["line_total"], 2); ?></td>
        </tr>
        <?php } ?>
    </table>

    <table class="summary-table">
        <tr>
            <td>Subtotal</td>
            <td>Rs. <?php echo number_format($order["subtotal"], 2); ?></td>
        </tr>
        <tr>
            <td>Discount</td>
            <td>Rs. <?php echo number_format($order["discount"], 2); ?></td>
        </tr>
        <tr class="total-row">
            <td>Grand Total</td>
            <td>Rs. <?php echo number_format($order["total_amount"], 2); ?></td>
        </tr>
        <tr>
            <td>Cash Given</td>
            <td>Rs. <?php echo number_format($order["cash_given"], 2); ?></td>
        </tr>
        <tr>
            <td>Balance</td>
            <td>Rs. <?php echo number_format($order["balance"], 2); ?></td>
        </tr>
    </table>

    <div class="footer">
        Thank you for visiting The La-Zogan
    </div>

        <button class="print-btn" onclick="window.print()">Print Bill</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qz-tray/2.2.2/qz-tray.js"></script>

<script>
async function openDrawer() {
    try {
        await qz.websocket.connect();

        const config = qz.configs.create("Your_Printer_Name");

        const data = [
            '\x1B\x70\x00\x19\xFA'
        ];

        await qz.print(config, data);

        qz.websocket.disconnect();
    } catch (e) {
        console.error(e);
        alert("Drawer open failed!");
    }
}
</script>

<?php if ($order["payment_method"] == "Cash") { ?>
<script>
window.onload = function() {
    openDrawer();
};
</script>
<?php } ?>

</body>
</html>