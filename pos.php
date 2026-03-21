<?php
session_start();
include 'db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

/* -----------------------------
   CART INITIALIZATION
------------------------------ */
if (!isset($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

/* -----------------------------
   HANDLE CART ACTIONS
------------------------------ */

/* ADD ITEM */
if (isset($_GET["add"])) {
    $product_id = (int) $_GET["add"];

    $product_query = $conn->query("SELECT * FROM products WHERE product_id = $product_id AND status = 1");
    if ($product_query && $product_query->num_rows > 0) {
        $product = $product_query->fetch_assoc();

        if (isset($_SESSION["cart"][$product_id])) {
            $_SESSION["cart"][$product_id]["quantity"] += 1;
        } else {
            $_SESSION["cart"][$product_id] = [
                "product_id"   => $product["product_id"],
                "product_name" => $product["product_name"],
                "price"        => $product["price"],
                "quantity"     => 1
            ];
        }
    }

    header("Location: pos.php");
    exit;
}

/* INCREASE QUANTITY */
if (isset($_GET["inc"])) {
    $product_id = (int) $_GET["inc"];

    if (isset($_SESSION["cart"][$product_id])) {
        $_SESSION["cart"][$product_id]["quantity"] += 1;
    }

    header("Location: pos.php");
    exit;
}

/* DECREASE QUANTITY */
if (isset($_GET["dec"])) {
    $product_id = (int) $_GET["dec"];

    if (isset($_SESSION["cart"][$product_id])) {
        $_SESSION["cart"][$product_id]["quantity"] -= 1;

        if ($_SESSION["cart"][$product_id]["quantity"] <= 0) {
            unset($_SESSION["cart"][$product_id]);
        }
    }

    header("Location: pos.php");
    exit;
}

/* REMOVE ITEM */
if (isset($_GET["remove"])) {
    $product_id = (int) $_GET["remove"];

    if (isset($_SESSION["cart"][$product_id])) {
        unset($_SESSION["cart"][$product_id]);
    }

    header("Location: pos.php");
    exit;
}

/* CLEAR CART */
if (isset($_GET["clear"])) {
    $_SESSION["cart"] = [];
    header("Location: pos.php");
    exit;
}

/* -----------------------------
   FILTERS
------------------------------ */
$filter_category = isset($_GET["category"]) ? (int) $_GET["category"] : 0;
$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";

/* CATEGORIES */
$categories = $conn->query("SELECT * FROM categories WHERE status = 1 ORDER BY category_name ASC");

/* PRODUCTS QUERY */
$product_sql = "SELECT * FROM products WHERE status = 1";

if ($filter_category > 0) {
    $product_sql .= " AND category_id = $filter_category";
}

if ($search !== "") {
    $search_safe = $conn->real_escape_string($search);
    $product_sql .= " AND product_name LIKE '%$search_safe%'";
}

$product_sql .= " ORDER BY product_name ASC";
$products = $conn->query($product_sql);

/* CART TOTAL */
$grand_total = 0;
foreach ($_SESSION["cart"] as $item) {
    $grand_total += ($item["price"] * $item["quantity"]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant POS</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .topbar {
            background: #1e88e5;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar h1 {
            margin: 0;
            font-size: 22px;
        }

        .topbar .right-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .topbar a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .pos-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            padding: 20px;
        }

        .left-panel,
        .right-panel {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 0 8px rgba(0,0,0,0.08);
        }

        .section-title {
            margin-top: 0;
            margin-bottom: 15px;
        }

        .filter-box {
            margin-bottom: 15px;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .filter-form input,
        .filter-form button {
            padding: 10px;
            font-size: 14px;
        }

        .filter-form input {
            flex: 1;
            min-width: 180px;
        }

        .filter-form button {
            background: #1e88e5;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        .category-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .category-links a {
            text-decoration: none;
            background: #e9eef5;
            color: #333;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
        }

        .category-links a.active {
            background: #1e88e5;
            color: white;
        }

        .products {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .product-card {
            background: #eef3f8;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .product-card h4 {
            margin: 0 0 8px;
            font-size: 16px;
        }

        .product-card p {
            margin: 0 0 10px;
            color: #333;
            font-weight: bold;
        }

        .product-card a {
            display: inline-block;
            margin-top: 5px;
            padding: 8px 12px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .no-products {
            padding: 20px;
            background: #fafafa;
            border: 1px dashed #ccc;
            border-radius: 8px;
            text-align: center;
            color: #666;
        }

        .right-panel label {
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
        }

        .right-panel select,
        .right-panel input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table th,
        .cart-table td {
            border-bottom: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: middle;
        }

        .qty-controls a {
            text-decoration: none;
            display: inline-block;
            min-width: 24px;
            text-align: center;
            padding: 3px 6px;
            background: #e9eef5;
            color: #000;
            border-radius: 4px;
            font-weight: bold;
        }

        .qty-controls span {
            margin: 0 8px;
            display: inline-block;
            min-width: 20px;
            text-align: center;
        }

        .remove-link,
        .clear-link {
            color: red;
            text-decoration: none;
        }

        .total-box {
            margin-top: 15px;
            font-size: 20px;
            font-weight: bold;
        }

        .payment-box {
            margin-top: 20px;
            padding: 15px;
            background: #f8fbff;
            border: 1px solid #d8e8fb;
            border-radius: 8px;
        }

        .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .summary-line strong {
            font-size: 16px;
        }

        .balance-text {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            margin-top: 10px;
        }

        .due-text {
            font-size: 18px;
            font-weight: bold;
            color: #dc3545;
            margin-top: 10px;
        }

        .save-btn {
            width: 100%;
            padding: 12px;
            background: #1e88e5;
            color: white;
            border: none;
            margin-top: 15px;
            cursor: pointer;
            border-radius: 6px;
            font-size: 16px;
        }

        .save-btn:disabled {
            background: #9fbfe8;
            cursor: not-allowed;
        }

        .empty-cart {
            text-align: center;
            color: #666;
            padding: 20px 0;
        }

        .cart-actions {
            margin-top: 10px;
        }

        @media (max-width: 992px) {
            .pos-container {
                grid-template-columns: 1fr;
            }

            .products {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .products {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<div class="topbar">
    <h1>Restaurant POS</h1>
    <div class="right-info">
        <span>Cashier: <?php echo htmlspecialchars($_SESSION["full_name"] ?? "User"); ?></span>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="pos-container">
    <!-- LEFT PANEL -->
    <div class="left-panel">
        <h2 class="section-title">Menu Items</h2>

        <div class="filter-box">
            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Search product..." value="<?php echo htmlspecialchars($search); ?>">
                <?php if ($filter_category > 0) { ?>
                    <input type="hidden" name="category" value="<?php echo $filter_category; ?>">
                <?php } ?>
                <button type="submit">Search</button>
            </form>

            <div class="category-links">
                <a href="pos.php" class="<?php echo ($filter_category == 0 && $search == '') ? 'active' : ''; ?>">All</a>

                <?php
                if ($categories && $categories->num_rows > 0) {
                    mysqli_data_seek($categories, 0);
                    while ($cat = $categories->fetch_assoc()) {
                        $activeClass = ($filter_category == $cat["category_id"]) ? "active" : "";
                        $categoryLink = "pos.php?category=" . $cat["category_id"];
                        ?>
                        <a href="<?php echo $categoryLink; ?>" class="<?php echo $activeClass; ?>">
                            <?php echo htmlspecialchars($cat["category_name"]); ?>
                        </a>
                    <?php
                    }
                }
                ?>
            </div>
        </div>

        <div class="products">
            <?php if ($products && $products->num_rows > 0) { ?>
                <?php while ($row = $products->fetch_assoc()) { ?>
                    <div class="product-card">
                        <h4><?php echo htmlspecialchars($row["product_name"]); ?></h4>
                        <p>Rs. <?php echo number_format($row["price"], 2); ?></p>
                        <a href="pos.php?add=<?php echo $row["product_id"]; ?>">Add</a>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="no-products">
                    No products found.
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <h2 class="section-title">Current Order</h2>

        <form method="POST" action="save_order.php" id="orderForm">
            <label for="order_type">Order Type</label>
            <select name="order_type" id="order_type" required>
                <option value="dine_in">Dine In</option>
                <option value="takeaway">Takeaway</option>
            </select>

            <?php if (!empty($_SESSION["cart"])) { ?>
                <table class="cart-table">
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>

                    <?php foreach ($_SESSION["cart"] as $item) {
                        $line_total = $item["price"] * $item["quantity"];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item["product_name"]); ?></td>
                            <td>
                                <div class="qty-controls">
                                    <a href="pos.php?dec=<?php echo $item["product_id"]; ?>">-</a>
                                    <span><?php echo $item["quantity"]; ?></span>
                                    <a href="pos.php?inc=<?php echo $item["product_id"]; ?>">+</a>
                                </div>
                            </td>
                            <td>Rs. <?php echo number_format($line_total, 2); ?></td>
                            <td>
                                <a class="remove-link" href="pos.php?remove=<?php echo $item["product_id"]; ?>">Remove</a>
                            </td>
                        </tr>
                    <?php } ?>
                </table>

                <div class="cart-actions">
                    <a class="clear-link" href="pos.php?clear=1" onclick="return confirm('Clear cart?')">Clear Cart</a>
                </div>
            <?php } else { ?>
                <div class="empty-cart">Cart is empty.</div>
            <?php } ?>

            <div class="total-box">
                Grand Total: Rs. <span id="grandTotal"><?php echo number_format($grand_total, 2, '.', ''); ?></span>
            </div>

            <?php if (!empty($_SESSION["cart"])) { ?>
                <div class="payment-box">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" required onchange="updatePayment()">
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="QR">QR</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>

                    <label for="cash_given">Cash Given (Rs.)</label>
                    <input 
                        type="number" 
                        name="cash_given" 
                        id="cash_given" 
                        step="0.01" 
                        min="0" 
                        placeholder="Enter cash amount"
                        required
                        oninput="updatePayment()"
                    >

                    <div class="summary-line">
                        <span>Total Bill</span>
                        <strong>Rs. <span id="billAmount"><?php echo number_format($grand_total, 2, '.', ''); ?></span></strong>
                    </div>

                    <div class="summary-line">
                        <span>Cash Given</span>
                        <strong>Rs. <span id="cashGivenText">0.00</span></strong>
                    </div>

                    <div id="balanceBox" class="balance-text">
                        Balance: Rs. 0.00
                    </div>

                    <div id="dueBox" class="due-text" style="display:none;">
                        Due: Rs. 0.00
                    </div>

                    <input type="hidden" name="subtotal" value="<?php echo number_format($grand_total, 2, '.', ''); ?>">
                    <input type="hidden" name="discount" value="0.00">
                    <input type="hidden" name="total_amount" value="<?php echo number_format($grand_total, 2, '.', ''); ?>">
                    <input type="hidden" name="balance" id="balance_input" value="0.00">
                </div>
            <?php } ?>

            <button type="submit" class="save-btn" <?php echo empty($_SESSION["cart"]) ? "disabled" : ""; ?>>
                Pay & Save Order
            </button>
        </form>
    </div>
</div>

<script>
    const grandTotal = parseFloat(document.getElementById('grandTotal')?.innerText || 0);

    function updatePayment() {
        const paymentMethod = document.getElementById('payment_method');
        const cashGivenInput = document.getElementById('cash_given');
        const cashGivenText = document.getElementById('cashGivenText');
        const balanceBox = document.getElementById('balanceBox');
        const dueBox = document.getElementById('dueBox');
        const balanceInput = document.getElementById('balance_input');

        let cashGiven = parseFloat(cashGivenInput.value) || 0;
        const method = paymentMethod.value;

        if (method === 'Cash') {
            cashGivenInput.readOnly = false;
            cashGivenInput.value = cashGivenInput.value;
        } else {
            cashGivenInput.value = grandTotal.toFixed(2);
            cashGivenInput.readOnly = true;
            cashGiven = grandTotal;
        }

        cashGivenText.innerText = cashGiven.toFixed(2);

        let balance = cashGiven - grandTotal;

        if (balance >= 0) {
            balanceBox.style.display = 'block';
            dueBox.style.display = 'none';
            balanceBox.innerText = 'Balance: Rs. ' + balance.toFixed(2);
        } else {
            balanceBox.style.display = 'none';
            dueBox.style.display = 'block';
            dueBox.innerText = 'Due: Rs. ' + Math.abs(balance).toFixed(2);
        }

        balanceInput.value = balance.toFixed(2);
    }

    document.getElementById('orderForm').addEventListener('submit', function(e) {
        const paymentMethod = document.getElementById('payment_method').value;
        const cashGiven = parseFloat(document.getElementById('cash_given').value) || 0;

        if (paymentMethod === 'Cash' && cashGiven < grandTotal) {
            alert('Cash given is less than total amount.');
            e.preventDefault();
            return;
        }
    });

    updatePayment();
</script>

</body>
</html>