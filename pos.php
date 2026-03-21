<?php
session_start();
include 'db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

/* ── CART ACTIONS ── */
if (isset($_GET["add"])) {
    $product_id = (int) $_GET["add"];
    $q = $conn->query("SELECT * FROM products WHERE product_id = $product_id AND status = 1");
    if ($q && $q->num_rows > 0) {
        $p = $q->fetch_assoc();
        $k = "product_" . $product_id;
        if (isset($_SESSION["cart"][$k])) {
            $_SESSION["cart"][$k]["quantity"] += 1;
        } else {
            $_SESSION["cart"][$k] = [
                "cart_key" => $k, "product_id" => $p["product_id"],
                "custom_item_name" => null, "product_name" => $p["product_name"],
                "price" => $p["price"], "quantity" => 1, "item_type" => "product"
            ];
        }
    }
    header("Location: pos.php"); exit;
}

if (isset($_POST["add_manual_item"])) {
    $mn = trim($_POST["manual_item_name"] ?? "");
    $mp = (float)($_POST["manual_item_price"] ?? 0);
    $mq = (int)($_POST["manual_item_qty"] ?? 1);
    if ($mn !== "" && $mp > 0 && $mq > 0) {
        $mk = "manual_" . time() . "_" . rand(1000,9999);
        $_SESSION["cart"][$mk] = [
            "cart_key" => $mk, "product_id" => null, "custom_item_name" => $mn,
            "product_name" => $mn, "price" => $mp, "quantity" => $mq, "item_type" => "manual"
        ];
    }
    header("Location: pos.php"); exit;
}

if (isset($_GET["inc"])) {
    $k = $_GET["inc"];
    if (isset($_SESSION["cart"][$k])) $_SESSION["cart"][$k]["quantity"] += 1;
    header("Location: pos.php"); exit;
}

if (isset($_GET["dec"])) {
    $k = $_GET["dec"];
    if (isset($_SESSION["cart"][$k])) {
        $_SESSION["cart"][$k]["quantity"] -= 1;
        if ($_SESSION["cart"][$k]["quantity"] <= 0) unset($_SESSION["cart"][$k]);
    }
    header("Location: pos.php"); exit;
}

if (isset($_GET["remove"])) {
    $k = $_GET["remove"];
    if (isset($_SESSION["cart"][$k])) unset($_SESSION["cart"][$k]);
    header("Location: pos.php"); exit;
}

if (isset($_GET["clear"])) {
    $_SESSION["cart"] = [];
    header("Location: pos.php"); exit;
}

/* ── ADMIN LOGIN (POST) ── */
$admin_error = "";
if (isset($_POST["admin_login_submit"])) {
    $au = trim($_POST["admin_username"] ?? "");
    $ap = $_POST["admin_password"] ?? "";
    $au_safe = $conn->real_escape_string($au);
    $ar = $conn->query("SELECT * FROM users WHERE username='$au_safe' AND role='admin' AND status=1 LIMIT 1");
    if ($ar && $ar->num_rows > 0) {
        $admin_row = $ar->fetch_assoc();
        if (password_verify($ap, $admin_row["password"])) {
            $_SESSION["user_id"]   = $admin_row["user_id"];
            $_SESSION["full_name"] = $admin_row["full_name"];
            $_SESSION["role"]      = "admin";
            header("Location: dashboard.php"); exit;
        } else {
            $admin_error = "Invalid username or password.";
        }
    } else {
        $admin_error = "Invalid username or password.";
    }
}

/* ── FILTERS ── */
$filter_category = isset($_GET["category"]) ? (int)$_GET["category"] : 0;
$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$categories = $conn->query("SELECT * FROM categories WHERE status = 1 ORDER BY category_name ASC");
$product_sql = "SELECT * FROM products WHERE status = 1";
if ($filter_category > 0) $product_sql .= " AND category_id = $filter_category";
if ($search !== "") {
    $ss = $conn->real_escape_string($search);
    $product_sql .= " AND product_name LIKE '%$ss%'";
}
$product_sql .= " ORDER BY product_name ASC";
$products = $conn->query($product_sql);

$grand_total = 0;
foreach ($_SESSION["cart"] as $item) $grand_total += $item["price"] * $item["quantity"];
$cart_count = count($_SESSION["cart"]);
$user_role  = $_SESSION["role"] ?? "cashier";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>The La-zogan — POS</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Lora:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --primary:      #d95c2b;
    --primary-dk:   #b84a1f;
    --primary-lt:   #fef3ed;
    --primary-mid:  #f5a07a;
    --accent:       #1a7a5e;
    --accent-lt:    #e8f5f0;
    --bg:           #f2f4f8;
    --white:        #ffffff;
    --border:       #dde0ea;
    --border-dk:    #c8ccd8;
    --text:         #1c2038;
    --text-mid:     #454a66;
    --text-muted:   #8e94b0;
    --red:          #dc2626;
    --red-lt:       #fef2f2;
    --green:        #15803d;
    --green-lt:     #f0fdf4;
    --yellow:       #b45309;
    --yellow-lt:    #fffbeb;
    --shadow-sm:    0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.04);
    --shadow-md:    0 4px 12px rgba(0,0,0,.09);
    --shadow-lg:    0 10px 40px rgba(0,0,0,.15);
    --radius:       12px;
    --radius-sm:    8px;
    --radius-xs:    5px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    height: 100%;
    overflow: hidden;
}

body {
    font-family: 'Nunito', sans-serif;
    background: var(--bg);
    color: var(--text);
}

/* ════════════════ TOPBAR ════════════════ */
.topbar {
    background: var(--white);
    border-bottom: 1.5px solid var(--border);
    height: 62px;
    padding: 0 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow-sm);
    flex-shrink: 0;
}

.brand { display: flex; align-items: center; gap: 11px; }

.brand-logo {
    width: 38px; height: 38px;
    background: var(--primary);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 17px;
    box-shadow: 0 3px 8px rgba(217,92,43,.35);
}

.brand-text h1 {
    font-family: 'Lora', serif;
    font-size: 18px;
    color: var(--text);
    line-height: 1.1;
    letter-spacing: .01em;
}

.brand-text small {
    display: block;
    font-size: 10px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .12em;
    font-weight: 700;
}

.topbar-right { display: flex; align-items: center; gap: 8px; }

.cashier-pill {
    display: flex; align-items: center; gap: 8px;
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: 40px;
    padding: 5px 14px 5px 5px;
}

.avatar {
    width: 28px; height: 28px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 900; color: #fff;
    flex-shrink: 0;
}

.cashier-pill .name {
    font-size: 13px; font-weight: 800; color: var(--text-mid);
}

.role-badge {
    font-size: 10px;
    background: var(--primary-lt);
    color: var(--primary);
    border-radius: 40px;
    padding: 2px 7px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.tb-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 15px;
    border-radius: var(--radius-sm);
    font-size: 13px; font-weight: 800;
    font-family: 'Nunito', sans-serif;
    border: none; cursor: pointer;
    text-decoration: none;
    transition: all .17s;
    white-space: nowrap;
}

.btn-owner {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 2px 8px rgba(217,92,43,.3);
}
.btn-owner:hover { background: var(--primary-dk); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(217,92,43,.4); }

.btn-logout {
    background: var(--bg);
    color: var(--text-mid);
    border: 1.5px solid var(--border);
}
.btn-logout:hover { border-color: var(--red); color: var(--red); background: var(--red-lt); }

/* ════════════════ LAYOUT ════════════════ */
.pos-body {
    display: flex;
    height: calc(100vh - 62px);
    overflow: hidden;
}

/* ════════════════ LEFT PANEL ════════════════ */
.left-panel {
    flex: 1;
    overflow-y: auto;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
}

/* Manual item card */
.manual-card {
    padding: 15px 18px;
    border-left: 4px solid var(--yellow);
}

.ch { display: flex; align-items: center; gap: 9px; margin-bottom: 12px; }

.ch-icon {
    width: 30px; height: 30px;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
}
.chi-y { background: var(--yellow-lt); color: var(--yellow); }
.chi-b { background: #eff6ff; color: #2563eb; }

.ch h3 { font-size: 14px; font-weight: 900; }

.manual-form {
    display: grid;
    grid-template-columns: 2fr 1.1fr 0.65fr auto;
    gap: 8px;
    align-items: center;
}

.inp {
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 9px 11px;
    font-size: 13px;
    font-family: 'Nunito', sans-serif;
    color: var(--text);
    width: 100%;
    outline: none;
    font-weight: 600;
    transition: border-color .16s, box-shadow .16s;
}
.inp::placeholder { color: var(--text-muted); }
.inp:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(217,92,43,.1); }

.btn-manual {
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    padding: 9px 16px;
    font-size: 13px; font-weight: 900;
    font-family: 'Nunito', sans-serif;
    cursor: pointer;
    display: flex; align-items: center; gap: 6px;
    white-space: nowrap;
    transition: all .16s;
}
.btn-manual:hover { background: var(--primary-dk); transform: translateY(-1px); }

/* Filter / Menu card */
.menu-card { overflow: hidden; }

.filter-area { padding: 14px 18px 0; }

.filter-row {
    display: flex; gap: 8px;
    margin-bottom: 10px;
}

.sw { flex: 1; position: relative; }
.sw i {
    position: absolute; left: 11px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted); font-size: 12px;
}

.search-inp {
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 9px 11px 9px 32px;
    font-size: 13px;
    font-family: 'Nunito', sans-serif;
    color: var(--text);
    width: 100%; outline: none; font-weight: 600;
    transition: border-color .16s;
}
.search-inp::placeholder { color: var(--text-muted); }
.search-inp:focus { border-color: var(--primary); }

.btn-search {
    background: var(--primary); color: #fff;
    border: none; border-radius: var(--radius-sm);
    padding: 9px 16px; font-size: 13px; font-weight: 800;
    font-family: 'Nunito', sans-serif;
    display: flex; align-items: center; gap: 6px;
    cursor: pointer; transition: background .16s;
}
.btn-search:hover { background: var(--primary-dk); }

.cat-pills {
    display: flex; flex-wrap: wrap; gap: 6px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.cpill {
    text-decoration: none;
    padding: 5px 13px;
    border-radius: 40px;
    font-size: 12px; font-weight: 800;
    border: 1.5px solid var(--border);
    color: var(--text-mid);
    background: var(--bg);
    display: flex; align-items: center; gap: 5px;
    transition: all .16s;
}
.cpill:hover { border-color: var(--primary); color: var(--primary); }
.cpill.active { background: var(--primary); border-color: var(--primary); color: #fff; }

/* Products grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
    gap: 10px;
    padding: 14px 18px 18px;
}

.pcard {
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 13px 11px 11px;
    text-align: center;
    cursor: pointer;
    transition: all .17s;
    display: flex; flex-direction: column; gap: 5px;
}
.pcard:hover {
    border-color: var(--primary);
    background: var(--primary-lt);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.pcard-icon {
    width: 42px; height: 42px;
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: var(--primary);
    margin: 0 auto 4px;
    transition: all .17s;
}
.pcard:hover .pcard-icon { border-color: var(--primary); background: var(--primary-lt); }

.pcard-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.3; }
.pcard-price { font-size: 13px; font-weight: 900; color: var(--primary); }

.btn-padd {
    display: flex; align-items: center; justify-content: center; gap: 5px;
    width: 100%; padding: 7px;
    background: var(--primary); color: #fff;
    border: none; border-radius: var(--radius-xs);
    font-size: 12px; font-weight: 900;
    font-family: 'Nunito', sans-serif;
    text-decoration: none; cursor: pointer;
    margin-top: 2px; transition: background .15s;
}
.btn-padd:hover { background: var(--primary-dk); }

.no-prods {
    grid-column: 1/-1; text-align: center;
    padding: 36px 20px; color: var(--text-muted);
    font-size: 14px; font-weight: 700;
}
.no-prods i { display: block; font-size: 26px; color: var(--border-dk); margin-bottom: 8px; }

/* ════════════════ RIGHT PANEL ════════════════ */
.right-panel {
    width: 390px;
    min-width: 390px;
    background: var(--white);
    border-left: 1.5px solid var(--border);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    box-shadow: -2px 0 8px rgba(0,0,0,.04);
}

.rp-form {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
}

/* Order header */
.rp-head {
    padding: 15px 18px 12px;
    border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}

.rp-head h2 {
    font-size: 15px; font-weight: 900;
    display: flex; align-items: center; gap: 7px;
}
.rp-head h2 i { color: var(--primary); }

.count-badge {
    background: var(--primary); color: #fff;
    font-size: 11px; font-weight: 900;
    padding: 2px 9px; border-radius: 40px;
}

/* Order type */
.ot-wrap {
    padding: 11px 18px;
    border-bottom: 1.5px solid var(--border);
    flex-shrink: 0;
}

.ot-lbl {
    font-size: 10px; font-weight: 900;
    text-transform: uppercase; letter-spacing: .1em;
    color: var(--text-muted); margin-bottom: 7px;
}

.ot-row {
    display: grid; grid-template-columns: 1fr 1fr;
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    overflow: hidden;
}

.otb {
    padding: 8px;
    font-size: 13px; font-weight: 800;
    font-family: 'Nunito', sans-serif;
    border: none; background: transparent;
    color: var(--text-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: all .16s;
}
.otb.active { background: var(--primary); color: #fff; border-radius: calc(var(--radius-sm) - 2px); }

/* Cart scroll */
.cart-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 4px 18px;
    min-height: 0;
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}

.empty-cart {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    height: 130px; gap: 8px;
    color: var(--text-muted); text-align: center;
}
.empty-cart i { font-size: 30px; color: var(--border-dk); }
.empty-cart p { font-size: 13px; font-weight: 700; line-height: 1.5; }

.ci {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 0;
    border-bottom: 1px solid var(--border);
    animation: ci-in .18s ease;
}
.ci:last-child { border-bottom: none; }

@keyframes ci-in {
    from { opacity: 0; transform: translateX(6px); }
    to   { opacity: 1; transform: translateX(0); }
}

.ci-info { flex: 1; min-width: 0; }

.ci-name {
    font-size: 13px; font-weight: 800;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.ci-price { font-size: 12px; font-weight: 700; color: var(--primary); margin-top: 1px; }

.mchip {
    display: inline-block; font-size: 9px;
    background: var(--yellow-lt); color: var(--yellow);
    border: 1px solid #fde68a;
    padding: 1px 5px; border-radius: 40px;
    margin-left: 4px; font-weight: 900;
}

.qc {
    display: flex; align-items: center;
    border: 1.5px solid var(--border); border-radius: var(--radius-sm);
    overflow: hidden; flex-shrink: 0;
}

.qcb {
    width: 26px; height: 26px;
    background: var(--bg); border: none;
    color: var(--text-mid); font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; cursor: pointer;
    transition: all .13s;
}
.qcb:hover { background: var(--primary); color: #fff; }

.qn {
    font-size: 12px; font-weight: 900;
    min-width: 24px; text-align: center;
    border-left: 1px solid var(--border);
    border-right: 1px solid var(--border);
    height: 26px; line-height: 26px;
}

.rm {
    width: 26px; height: 26px;
    background: var(--red-lt);
    border: 1.5px solid #fecaca;
    border-radius: var(--radius-xs);
    color: var(--red); font-size: 11px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; cursor: pointer;
    transition: all .13s; flex-shrink: 0;
}
.rm:hover { background: var(--red); color: #fff; border-color: var(--red); }

/* Clear bar */
.clear-bar {
    padding: 7px 18px;
    border-top: 1px solid var(--border);
    display: flex; justify-content: flex-end;
    flex-shrink: 0;
}

.btn-clear {
    background: none;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    font-size: 12px; font-weight: 800;
    font-family: 'Nunito', sans-serif;
    padding: 5px 12px;
    cursor: pointer; text-decoration: none;
    display: flex; align-items: center; gap: 5px;
    transition: all .15s;
}
.btn-clear:hover { border-color: var(--red); color: var(--red); background: var(--red-lt); }

/* ════ PAYMENT SECTION ════ */
.pay-section {
    border-top: 2px solid var(--border);
    padding: 13px 18px 15px;
    background: #fafbfd;
    flex-shrink: 0;
}

.total-row {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 11px;
}

.total-lbl {
    font-size: 11px; font-weight: 900;
    text-transform: uppercase; letter-spacing: .09em;
    color: var(--text-muted);
}

.total-amt {
    font-family: 'Lora', serif;
    font-size: 22px; font-weight: 700;
    color: var(--primary);
}

.pm-lbl {
    font-size: 10px; font-weight: 900;
    text-transform: uppercase; letter-spacing: .09em;
    color: var(--text-muted); margin-bottom: 6px;
}

.pm-grid {
    display: grid; grid-template-columns: repeat(4,1fr);
    gap: 5px; margin-bottom: 9px;
}

.pmb {
    padding: 7px 3px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--white);
    color: var(--text-mid);
    font-size: 11px; font-weight: 800;
    font-family: 'Nunito', sans-serif;
    text-align: center; cursor: pointer;
    display: flex; flex-direction: column; align-items: center; gap: 3px;
    transition: all .15s;
}
.pmb i { font-size: 15px; }
.pmb:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-lt); }
.pmb.active { background: var(--primary-lt); border-color: var(--primary); color: var(--primary); }

/* Cash input */
.cash-wrap { position: relative; margin-bottom: 7px; }

.cash-pfx {
    position: absolute; left: 11px; top: 50%;
    transform: translateY(-50%);
    font-size: 12px; font-weight: 900;
    color: var(--text-muted); pointer-events: none;
}

.cash-inp {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 10px 11px 10px 42px;
    font-size: 15px; font-weight: 900;
    font-family: 'Nunito', sans-serif;
    color: var(--text); width: 100%; outline: none;
    transition: border-color .16s;
}
.cash-inp:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(217,92,43,.1); }
.cash-inp:read-only { background: var(--bg); opacity: .65; cursor: not-allowed; }

/* Balance pill */
.bal-pill {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 11px; border-radius: var(--radius-sm);
    margin-bottom: 9px; font-size: 13px; font-weight: 800;
    border: 1.5px solid;
}

.bp-zero { background: var(--bg); border-color: var(--border); color: var(--text-muted); }
.bp-pos  { background: var(--green-lt); border-color: #86efac; color: var(--green); }
.bp-neg  { background: var(--red-lt);   border-color: #fca5a5; color: var(--red); }

/* THE PAY BUTTON */
.pay-btn {
    width: 100%;
    padding: 14px 18px;
    background: var(--primary); color: #fff;
    border: none; border-radius: var(--radius);
    font-size: 16px; font-weight: 900;
    font-family: 'Nunito', sans-serif;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 9px;
    transition: all .18s;
    box-shadow: 0 4px 14px rgba(217,92,43,.38);
    letter-spacing: .02em;
}
.pay-btn:hover:not([disabled]) {
    background: var(--primary-dk);
    transform: translateY(-2px);
    box-shadow: 0 7px 20px rgba(217,92,43,.44);
}
.pay-btn:active:not([disabled]) { transform: translateY(0); }
.pay-btn[disabled] {
    background: #e4e6f0; color: var(--text-muted);
    cursor: not-allowed; box-shadow: none;
}
.pay-btn i { font-size: 17px; }

/* ════════════════ MODAL ════════════════ */
.overlay {
    position: fixed; inset: 0;
    background: rgba(28,32,56,.45);
    backdrop-filter: blur(5px);
    z-index: 999;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none;
    transition: opacity .2s;
}
.overlay.show { opacity: 1; pointer-events: all; }

.modal {
    background: var(--white);
    border-radius: 18px;
    box-shadow: var(--shadow-lg);
    padding: 30px 28px 26px;
    width: 370px; max-width: 94vw;
    position: relative;
    transform: translateY(14px) scale(.97);
    transition: transform .2s;
}
.overlay.show .modal { transform: translateY(0) scale(1); }

.mcl {
    position: absolute; top: 14px; right: 14px;
    width: 28px; height: 28px;
    background: var(--bg); border: 1.5px solid var(--border);
    border-radius: 7px; font-size: 13px; color: var(--text-muted);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all .14s;
}
.mcl:hover { background: var(--red-lt); border-color: var(--red); color: var(--red); }

.m-head { text-align: center; margin-bottom: 22px; }

.m-icon {
    width: 52px; height: 52px;
    background: var(--primary-lt); border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; color: var(--primary);
    margin: 0 auto 12px;
}

.m-head h2 { font-family: 'Lora', serif; font-size: 19px; margin-bottom: 3px; }
.m-head p  { font-size: 13px; color: var(--text-muted); font-weight: 600; }

.m-err {
    background: var(--red-lt); border: 1.5px solid #fca5a5;
    border-radius: var(--radius-sm);
    padding: 9px 12px;
    font-size: 13px; color: var(--red); font-weight: 800;
    display: flex; align-items: center; gap: 7px;
    margin-bottom: 13px;
}

.mf { margin-bottom: 12px; }

.mf label {
    display: block; font-size: 11px; font-weight: 900;
    text-transform: uppercase; letter-spacing: .09em;
    color: var(--text-mid); margin-bottom: 5px;
}

.miw { position: relative; }
.miw i {
    position: absolute; left: 12px; top: 50%;
    transform: translateY(-50%); color: var(--text-muted); font-size: 13px;
}

.minp {
    background: var(--bg); border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 11px 12px 11px 36px;
    font-size: 14px; font-weight: 700;
    font-family: 'Nunito', sans-serif;
    color: var(--text); width: 100%; outline: none;
    transition: border-color .16s;
}
.minp:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(217,92,43,.1); }

.m-sub {
    width: 100%; padding: 12px;
    background: var(--primary); color: #fff;
    border: none; border-radius: var(--radius-sm);
    font-size: 15px; font-weight: 900;
    font-family: 'Nunito', sans-serif;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 7px;
    box-shadow: 0 3px 10px rgba(217,92,43,.3);
    transition: all .16s;
}
.m-sub:hover { background: var(--primary-dk); }

.m-note {
    text-align: center; font-size: 12px;
    color: var(--text-muted); margin-top: 11px;
    font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 5px;
}

/* Scrollbar */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-thumb { background: var(--border-dk); border-radius: 4px; }

@media (max-width: 860px) {
    html, body { overflow: auto; }
    .pos-body { flex-direction: column; height: auto; overflow: visible; }
    .right-panel { width: 100%; min-width: 0; height: auto; border-left: none; border-top: 1.5px solid var(--border); }
    .cart-scroll { max-height: 220px; }
    .manual-form { grid-template-columns: 1fr 1fr; }
    .manual-form button { grid-column: 1/-1; }
}
</style>
</head>
<body>

<!-- ════ TOPBAR ════ -->
<div class="topbar">
    <div class="brand">
        <div class="brand-logo"><i class="fa-solid fa-utensils"></i></div>
        <div class="brand-text">
            <h1>The La-zogan</h1>
            <small>Point of Sale</small>
        </div>
    </div>
    <div class="topbar-right">

        <!-- Owner Dashboard — always visible, always requires login -->
        <button class="tb-btn btn-owner" type="button" onclick="openModal()">
            <i class="fa-solid fa-gauge-high"></i> Owner Dashboard
        </button>

        <div class="cashier-pill">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION["full_name"] ?? "U", 0, 1)); ?></div>
            <span class="name"><?php echo htmlspecialchars($_SESSION["full_name"] ?? "Cashier"); ?></span>
            <span class="role-badge"><?php echo ucfirst($user_role); ?></span>
        </div>

        <a href="logout.php" class="tb-btn btn-logout">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</div>

<!-- ════ BODY ════ -->
<div class="pos-body">

    <!-- ═══ LEFT PANEL ═══ -->
    <div class="left-panel">

        <!-- Custom item -->
        <div class="card manual-card">
            <div class="ch">
                <div class="ch-icon chi-y"><i class="fa-solid fa-pen-to-square"></i></div>
                <h3>Add Custom Item</h3>
            </div>
            <form method="POST" class="manual-form">
                <input type="text"   name="manual_item_name"  class="inp" placeholder="Item name" required>
                <input type="number" name="manual_item_price" class="inp" step="0.01" min="0.01" placeholder="Price (Rs.)" required>
                <input type="number" name="manual_item_qty"   class="inp" min="1" value="1" placeholder="Qty" required>
                <button type="submit" name="add_manual_item" class="btn-manual">
                    <i class="fa-solid fa-circle-plus"></i> Add
                </button>
            </form>
        </div>

        <!-- Menu -->
        <div class="card menu-card">
            <div class="filter-area">
                <div class="ch" style="margin-bottom:11px;">
                    <div class="ch-icon chi-b"><i class="fa-solid fa-bowl-food"></i></div>
                    <h3>Menu Items</h3>
                </div>
                <form method="GET" class="filter-row">
                    <div class="sw">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" class="search-inp" placeholder="Search menu…" value="<?php echo htmlspecialchars($search); ?>">
                        <?php if ($filter_category > 0): ?><input type="hidden" name="category" value="<?php echo $filter_category; ?>"><?php endif; ?>
                    </div>
                    <button type="submit" class="btn-search">
                        <i class="fa-solid fa-magnifying-glass"></i> Search
                    </button>
                </form>
                <div class="cat-pills">
                    <a href="pos.php" class="cpill <?php echo ($filter_category==0 && $search=='') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-border-all"></i> All
                    </a>
                    <?php
                    if ($categories && $categories->num_rows > 0) {
                        mysqli_data_seek($categories, 0);
                        while ($cat = $categories->fetch_assoc()) {
                            $cls = ($filter_category == $cat["category_id"]) ? "active" : "";
                            echo '<a href="pos.php?category=' . $cat["category_id"] . '" class="cpill ' . $cls . '">'
                               . '<i class="fa-solid fa-tag"></i> '
                               . htmlspecialchars($cat["category_name"]) . '</a>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="products-grid">
                <?php if ($products && $products->num_rows > 0): ?>
                    <?php while ($row = $products->fetch_assoc()): ?>
                    <div class="pcard" onclick="window.location='pos.php?add=<?php echo $row["product_id"]; ?>'">
                        <div class="pcard-icon"><i class="fa-solid fa-plate-wheat"></i></div>
                        <div class="pcard-name"><?php echo htmlspecialchars($row["product_name"]); ?></div>
                        <div class="pcard-price">Rs. <?php echo number_format($row["price"], 2); ?></div>
                        <a href="pos.php?add=<?php echo $row["product_id"]; ?>" class="btn-padd" onclick="event.stopPropagation()">
                            <i class="fa-solid fa-cart-plus"></i> Add to Order
                        </a>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-prods">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        No items found. Try a different search or category.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /left-panel -->

    <!-- ═══ RIGHT PANEL ═══ -->
    <div class="right-panel">
        <form method="POST" action="save_order.php" id="orderForm" class="rp-form">

            <!-- Header -->
            <div class="rp-head">
                <h2><i class="fa-solid fa-receipt"></i> Current Order</h2>
                <div class="count-badge"><?php echo $cart_count; ?> item<?php echo $cart_count != 1 ? 's' : ''; ?></div>
            </div>

            <!-- Order Type -->
            <div class="ot-wrap">
                <div class="ot-lbl">Order Type</div>
                <div class="ot-row">
                    <button type="button" class="otb active" data-type="dine_in" onclick="setOT('dine_in')">
                        <i class="fa-solid fa-chair"></i> Dine In
                    </button>
                    <button type="button" class="otb" data-type="takeaway" onclick="setOT('takeaway')">
                        <i class="fa-solid fa-bag-shopping"></i> Takeaway
                    </button>
                </div>
                <input type="hidden" name="order_type" id="ot_val" value="dine_in">
            </div>

            <!-- Cart Items -->
            <div class="cart-scroll">
                <?php if (!empty($_SESSION["cart"])): ?>
                    <?php foreach ($_SESSION["cart"] as $item):
                        $lt = $item["price"] * $item["quantity"];
                    ?>
                    <div class="ci">
                        <div class="ci-info">
                            <div class="ci-name">
                                <?php echo htmlspecialchars($item["product_name"]); ?>
                                <?php if (($item["item_type"] ?? "") === "manual"): ?>
                                    <span class="mchip">Custom</span>
                                <?php endif; ?>
                            </div>
                            <div class="ci-price">Rs. <?php echo number_format($lt, 2); ?></div>
                        </div>
                        <div class="qc">
                            <a class="qcb" href="pos.php?dec=<?php echo urlencode($item["cart_key"]); ?>"><i class="fa-solid fa-minus"></i></a>
                            <span class="qn"><?php echo $item["quantity"]; ?></span>
                            <a class="qcb" href="pos.php?inc=<?php echo urlencode($item["cart_key"]); ?>"><i class="fa-solid fa-plus"></i></a>
                        </div>
                        <a class="rm" href="pos.php?remove=<?php echo urlencode($item["cart_key"]); ?>" title="Remove item">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-cart">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <p>Cart is empty.<br>Tap a menu item to start!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Clear bar -->
            <?php if (!empty($_SESSION["cart"])): ?>
            <div class="clear-bar">
                <a class="btn-clear" href="pos.php?clear=1" onclick="return confirm('Clear all items from cart?')">
                    <i class="fa-solid fa-trash-can"></i> Clear Cart
                </a>
            </div>
            <?php endif; ?>

            <!-- Payment -->
            <div class="pay-section">

                <div class="total-row">
                    <span class="total-lbl">Grand Total</span>
                    <span class="total-amt">Rs. <span id="gt"><?php echo number_format($grand_total, 2, '.', ''); ?></span></span>
                </div>

                <?php if (!empty($_SESSION["cart"])): ?>

                <div class="pm-lbl">Payment Method</div>
                <div class="pm-grid">
                    <button type="button" class="pmb active" data-method="Cash" onclick="selMethod('Cash')">
                        <i class="fa-solid fa-money-bill-wave"></i> Cash
                    </button>
                    <button type="button" class="pmb" data-method="Card" onclick="selMethod('Card')">
                        <i class="fa-solid fa-credit-card"></i> Card
                    </button>
                    <button type="button" class="pmb" data-method="QR" onclick="selMethod('QR')">
                        <i class="fa-solid fa-qrcode"></i> QR Pay
                    </button>
                    <button type="button" class="pmb" data-method="Bank Transfer" onclick="selMethod('Bank Transfer')">
                        <i class="fa-solid fa-building-columns"></i> Bank
                    </button>
                </div>
                <input type="hidden" name="payment_method" id="pm_val" value="Cash">

                <div class="cash-wrap">
                    <span class="cash-pfx">Rs.</span>
                    <input type="number" name="cash_given" id="cash_given" class="cash-inp"
                           step="0.01" min="0" placeholder="Enter cash amount…" required oninput="calcBal()">
                </div>

                <div id="balPill" class="bal-pill bp-zero">
                    <span id="balLbl">Balance / Change</span>
                    <span id="balAmt">Rs. 0.00</span>
                </div>

                <input type="hidden" name="subtotal"     value="<?php echo number_format($grand_total,2,'.','')?>" >
                <input type="hidden" name="discount"     value="0.00">
                <input type="hidden" name="total_amount" value="<?php echo number_format($grand_total,2,'.','')?>" >
                <input type="hidden" name="balance"      id="bal_inp" value="0.00">

                <?php endif; ?>

                <button type="submit" class="pay-btn"
                    <?php echo empty($_SESSION["cart"]) ? "disabled" : ""; ?>>
                    <?php if (empty($_SESSION["cart"])): ?>
                        <i class="fa-solid fa-cart-shopping"></i> Cart is Empty
                    <?php else: ?>
                        <i class="fa-solid fa-circle-check"></i> Pay &amp; Save Order
                    <?php endif; ?>
                </button>

            </div><!-- /pay-section -->

        </form>
    </div><!-- /right-panel -->

</div><!-- /pos-body -->

<!-- ════ OWNER LOGIN MODAL ════ -->
<div class="overlay" id="adminOverlay">
    <div class="modal">
        <button class="mcl" type="button" onclick="closeModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="m-head">
            <div class="m-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <h2>Owner Access</h2>
            <p>Enter credentials to open the dashboard</p>
        </div>

        <?php if (!empty($admin_error)): ?>
        <div class="m-err">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?php echo htmlspecialchars($admin_error); ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mf">
                <label>Username</label>
                <div class="miw">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="admin_username" class="minp" placeholder="Owner username" required autocomplete="username">
                </div>
            </div>
            <div class="mf">
                <label>Password</label>
                <div class="miw">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="admin_password" class="minp" placeholder="Password" required autocomplete="current-password">
                </div>
            </div>
            <button type="submit" name="admin_login_submit" class="m-sub">
                <i class="fa-solid fa-right-to-bracket"></i> Login to Dashboard
            </button>
        </form>

        <p class="m-note">
            <i class="fa-solid fa-lock"></i>
            Owner / Admin access only
        </p>
    </div>
</div>

<script>
const GT = parseFloat("<?php echo number_format($grand_total, 2, '.', ''); ?>") || 0;

/* Order Type */
function setOT(t) {
    document.getElementById('ot_val').value = t;
    document.querySelectorAll('.otb').forEach(b => b.classList.toggle('active', b.dataset.type === t));
}

/* Payment Method */
function selMethod(m) {
    document.getElementById('pm_val').value = m;
    document.querySelectorAll('.pmb').forEach(b => b.classList.toggle('active', b.dataset.method === m));
    const ci = document.getElementById('cash_given');
    if (!ci) return;
    if (m === 'Cash') { ci.readOnly = false; ci.value = ''; ci.placeholder = 'Enter cash amount…'; }
    else              { ci.readOnly = true; ci.value = GT.toFixed(2); }
    calcBal();
}

/* Balance */
function calcBal() {
    const ci   = document.getElementById('cash_given');
    const pill = document.getElementById('balPill');
    const lbl  = document.getElementById('balLbl');
    const amt  = document.getElementById('balAmt');
    const inp  = document.getElementById('bal_inp');
    if (!ci || !pill) return;

    const given = parseFloat(ci.value) || 0;
    const diff  = given - GT;
    if (inp) inp.value = diff.toFixed(2);

    pill.className = 'bal-pill';
    if (given === 0) {
        pill.classList.add('bp-zero');
        lbl.textContent = 'Balance / Change';
        amt.textContent = 'Rs. 0.00';
    } else if (diff >= 0) {
        pill.classList.add('bp-pos');
        lbl.textContent = 'Change to Return';
        amt.textContent = 'Rs. ' + diff.toFixed(2);
    } else {
        pill.classList.add('bp-neg');
        lbl.textContent = 'Amount Due';
        amt.textContent = 'Rs. ' + Math.abs(diff).toFixed(2);
    }
}

/* Form validation */
const oForm = document.getElementById('orderForm');
if (oForm) {
    oForm.addEventListener('submit', function(e) {
        const m = document.getElementById('pm_val')?.value;
        const g = parseFloat(document.getElementById('cash_given')?.value) || 0;
        if (m === 'Cash' && g < GT) {
            alert('Cash given is less than the total amount. Please enter the correct amount.');
            e.preventDefault();
        }
    });
}

/* Modal */
function openModal() {
    document.getElementById('adminOverlay').classList.add('show');
    setTimeout(() => document.querySelector('.minp')?.focus(), 80);
}
function closeModal() {
    document.getElementById('adminOverlay').classList.remove('show');
}
document.getElementById('adminOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

<?php if (!empty($admin_error)): ?>
window.addEventListener('load', () => openModal());
<?php endif; ?>

calcBal();
</script>
</body>
</html>