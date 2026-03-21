<?php
session_start();
include 'db.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: login.php");
    exit;
}

/* ════════════════════════════════════════
   DATA QUERIES
════════════════════════════════════════ */

/* --- KPI cards --- */
$total_sales    = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS v FROM orders")->fetch_assoc()["v"];
$total_orders   = $conn->query("SELECT COUNT(*) AS v FROM orders")->fetch_assoc()["v"];
$today_sales    = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS v FROM orders WHERE DATE(created_at)=CURDATE()")->fetch_assoc()["v"];
$today_orders   = $conn->query("SELECT COUNT(*) AS v FROM orders WHERE DATE(created_at)=CURDATE()")->fetch_assoc()["v"];
$month_sales    = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS v FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetch_assoc()["v"];
$month_orders   = $conn->query("SELECT COUNT(*) AS v FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetch_assoc()["v"];
$avg_order_val  = $total_orders > 0 ? $total_sales / $total_orders : 0;

/* --- Yesterday comparison for today --- */
$yesterday_sales = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS v FROM orders WHERE DATE(created_at)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetch_assoc()["v"];
$today_vs_yesterday = $yesterday_sales > 0 ? (($today_sales - $yesterday_sales) / $yesterday_sales) * 100 : 0;

/* --- Last 7 days daily sales (for chart) --- */
$daily_q = $conn->query("
    SELECT DATE(created_at) AS day, COALESCE(SUM(total_amount),0) AS total, COUNT(*) AS cnt
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$daily_labels = []; $daily_amounts = []; $daily_counts = [];
$days_map = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days_map[$d] = ['total'=>0,'cnt'=>0];
}
while ($row = $daily_q->fetch_assoc()) {
    $days_map[$row['day']] = ['total'=> (float)$row['total'], 'cnt'=> (int)$row['cnt']];
}
foreach ($days_map as $d => $v) {
    $daily_labels[]  = date('D d', strtotime($d));
    $daily_amounts[] = round($v['total'], 2);
    $daily_counts[]  = $v['cnt'];
}

/* --- Payment method breakdown --- */
$pm_q = $conn->query("SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM orders GROUP BY payment_method ORDER BY total DESC");
$pm_labels = []; $pm_totals = []; $pm_counts = [];
while ($row = $pm_q->fetch_assoc()) {
    $pm_labels[] = $row['payment_method'];
    $pm_totals[] = round((float)$row['total'], 2);
    $pm_counts[] = (int)$row['cnt'];
}

/* --- Order type breakdown --- */
$ot_q = $conn->query("SELECT order_type, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM orders GROUP BY order_type");
$ot_data = [];
while ($r = $ot_q->fetch_assoc()) $ot_data[$r['order_type']] = $r;

/* --- Top selling products --- */
$top_q = $conn->query("
    SELECT p.product_name,
           SUM(oi.quantity) AS qty_sold,
           SUM(oi.quantity * oi.unit_price) AS revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.product_id IS NOT NULL
    GROUP BY oi.product_id
    ORDER BY qty_sold DESC
    LIMIT 8
");
$top_products = [];
while ($r = $top_q->fetch_assoc()) $top_products[] = $r;

/* --- Recent orders --- */
$recent_q = $conn->query("
    SELECT o.order_id, o.order_type, o.payment_method, o.total_amount,
           o.created_at, u.full_name AS cashier
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$recent_orders = [];
while ($r = $recent_q->fetch_assoc()) $recent_orders[] = $r;

/* --- Hourly heatmap today --- */
$hourly_q = $conn->query("
    SELECT HOUR(created_at) AS hr, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total
    FROM orders
    WHERE DATE(created_at)=CURDATE()
    GROUP BY HOUR(created_at)
");
$hourly = array_fill(0, 24, ['cnt'=>0,'total'=>0]);
while ($r = $hourly_q->fetch_assoc()) {
    $hourly[(int)$r['hr']] = ['cnt'=>(int)$r['cnt'],'total'=>(float)$r['total']];
}

/* --- Monthly trend last 6 months --- */
$monthly_q = $conn->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS mon,
           COALESCE(SUM(total_amount),0) AS total,
           COUNT(*) AS cnt
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
");
$mon_labels = []; $mon_totals = [];
while ($r = $monthly_q->fetch_assoc()) {
    $mon_labels[] = $r['mon'];
    $mon_totals[] = round((float)$r['total'], 2);
}

/* --- Total products & categories --- */
$total_products   = $conn->query("SELECT COUNT(*) AS v FROM products WHERE status=1")->fetch_assoc()["v"];
$total_categories = $conn->query("SELECT COUNT(*) AS v FROM categories WHERE status=1")->fetch_assoc()["v"];
$total_users      = $conn->query("SELECT COUNT(*) AS v FROM users WHERE status=1")->fetch_assoc()["v"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>The La-zogan — Owner Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Lora:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<style>
:root {
    --primary:    #d95c2b;
    --primary-dk: #b84a1f;
    --primary-lt: #fef3ed;
    --primary-mid:#f4956a;
    --indigo:     #4f46e5;
    --indigo-lt:  #eef2ff;
    --green:      #16a34a;
    --green-lt:   #f0fdf4;
    --amber:      #d97706;
    --amber-lt:   #fffbeb;
    --sky:        #0284c7;
    --sky-lt:     #f0f9ff;
    --red:        #dc2626;
    --red-lt:     #fef2f2;
    --bg:         #f1f3f8;
    --white:      #ffffff;
    --border:     #e0e3ef;
    --border-dk:  #c8ccd8;
    --text:       #1c2038;
    --text-mid:   #454a66;
    --text-muted: #8e94b0;
    --sidebar-w:  240px;
    --topbar-h:   62px;
    --shadow-sm:  0 1px 3px rgba(0,0,0,.07);
    --shadow-md:  0 4px 16px rgba(0,0,0,.09);
    --shadow-lg:  0 10px 36px rgba(0,0,0,.13);
    --radius:     12px;
    --radius-sm:  8px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Nunito', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
}

/* ════════════════════════
   SIDEBAR
════════════════════════ */
.sidebar {
    width: var(--sidebar-w);
    background: var(--white);
    border-right: 1.5px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0;
    height: 100vh;
    z-index: 200;
    box-shadow: 2px 0 12px rgba(0,0,0,.05);
    transition: transform .25s;
}

.sb-brand {
    padding: 18px 20px 16px;
    border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center; gap: 11px;
}

.sb-logo {
    width: 38px; height: 38px;
    background: var(--primary);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 17px;
    box-shadow: 0 3px 8px rgba(217,92,43,.32);
    flex-shrink: 0;
}

.sb-brand-text h2 {
    font-family: 'Lora', serif;
    font-size: 15px; color: var(--text);
    line-height: 1.1;
}

.sb-brand-text small {
    font-size: 10px; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .1em; font-weight: 700;
}

/* Nav */
.sb-nav { flex: 1; overflow-y: auto; padding: 12px 0; }

.nav-group-label {
    font-size: 10px; font-weight: 900;
    text-transform: uppercase; letter-spacing: .12em;
    color: var(--text-muted);
    padding: 10px 20px 5px;
}

.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 20px;
    font-size: 13px; font-weight: 800;
    color: var(--text-mid);
    text-decoration: none;
    border-radius: 0;
    cursor: pointer;
    transition: all .15s;
    border: none; background: none;
    width: 100%; text-align: left;
    font-family: 'Nunito', sans-serif;
    position: relative;
}

.nav-item i {
    width: 18px; text-align: center;
    font-size: 14px; color: var(--text-muted);
    transition: color .15s;
}

.nav-item:hover { background: var(--bg); color: var(--primary); }
.nav-item:hover i { color: var(--primary); }

.nav-item.active {
    background: var(--primary-lt);
    color: var(--primary);
}
.nav-item.active i { color: var(--primary); }
.nav-item.active::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; background: var(--primary);
    border-radius: 0 3px 3px 0;
}

.sb-footer {
    padding: 14px 16px;
    border-top: 1.5px solid var(--border);
}

.sb-user {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px;
    background: var(--bg);
    border-radius: var(--radius-sm);
    margin-bottom: 8px;
}

.sb-avatar {
    width: 32px; height: 32px;
    background: linear-gradient(135deg,#6366f1,#8b5cf6);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 900; color: #fff;
    flex-shrink: 0;
}

.sb-user-info .name { font-size: 13px; font-weight: 800; color: var(--text); }
.sb-user-info .role {
    font-size: 10px; font-weight: 900; color: var(--primary);
    text-transform: uppercase; letter-spacing: .06em;
}

.btn-logout-sb {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    width: 100%; padding: 9px;
    background: var(--red-lt); border: 1.5px solid #fca5a5;
    border-radius: var(--radius-sm); color: var(--red);
    font-size: 13px; font-weight: 800;
    font-family: 'Nunito', sans-serif;
    cursor: pointer; text-decoration: none;
    transition: all .15s;
}
.btn-logout-sb:hover { background: var(--red); color: #fff; border-color: var(--red); }

/* ════════════════════════
   MAIN CONTENT
════════════════════════ */
.main {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Topbar */
.topbar {
    background: var(--white);
    border-bottom: 1.5px solid var(--border);
    height: var(--topbar-h);
    padding: 0 24px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
    box-shadow: var(--shadow-sm);
}

.topbar-left {
    display: flex; align-items: center; gap: 10px;
}

.page-title {
    font-family: 'Lora', serif;
    font-size: 18px; color: var(--text);
}

.breadcrumb {
    font-size: 12px; color: var(--text-muted); font-weight: 700;
    display: flex; align-items: center; gap: 5px;
}
.breadcrumb i { font-size: 10px; }

.topbar-right { display: flex; align-items: center; gap: 10px; }

.btn-pos {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 16px;
    background: var(--primary); color: #fff;
    border: none; border-radius: var(--radius-sm);
    font-size: 13px; font-weight: 800;
    font-family: 'Nunito', sans-serif;
    cursor: pointer; text-decoration: none;
    box-shadow: 0 2px 8px rgba(217,92,43,.3);
    transition: all .17s;
}
.btn-pos:hover { background: var(--primary-dk); transform: translateY(-1px); }

.date-badge {
    background: var(--bg); border: 1.5px solid var(--border);
    border-radius: var(--radius-sm); padding: 7px 13px;
    font-size: 12px; font-weight: 800; color: var(--text-mid);
    display: flex; align-items: center; gap: 6px;
}

/* ════════════════════════
   PAGE CONTENT
════════════════════════ */
.content {
    padding: 22px 24px 32px;
    flex: 1;
}

/* ── SECTION ── */
.section { margin-bottom: 28px; }

.section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
}

.section-title {
    font-size: 15px; font-weight: 900;
    display: flex; align-items: center; gap: 8px;
}
.section-title i { color: var(--primary); font-size: 14px; }

.section-action {
    font-size: 12px; font-weight: 800; color: var(--primary);
    text-decoration: none; display: flex; align-items: center; gap: 5px;
    transition: gap .15s;
}
.section-action:hover { gap: 8px; }

/* ════════════════════════
   KPI CARDS
════════════════════════ */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.kpi-card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 18px 16px;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    transition: transform .17s, box-shadow .17s;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

.kpi-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 3px;
}
.kpi-orange::before  { background: var(--primary); }
.kpi-indigo::before  { background: var(--indigo); }
.kpi-green::before   { background: var(--green); }
.kpi-amber::before   { background: var(--amber); }
.kpi-sky::before     { background: var(--sky); }

.kpi-top {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 12px;
}

.kpi-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px;
}
.ki-orange { background: var(--primary-lt); color: var(--primary); }
.ki-indigo { background: var(--indigo-lt); color: var(--indigo); }
.ki-green  { background: var(--green-lt);  color: var(--green); }
.ki-amber  { background: var(--amber-lt);  color: var(--amber); }
.ki-sky    { background: var(--sky-lt);    color: var(--sky); }

.kpi-badge {
    font-size: 11px; font-weight: 800;
    padding: 3px 8px; border-radius: 40px;
    display: flex; align-items: center; gap: 4px;
}
.badge-up   { background: var(--green-lt); color: var(--green); }
.badge-down { background: var(--red-lt);   color: var(--red); }
.badge-neu  { background: var(--bg);       color: var(--text-muted); }

.kpi-value {
    font-family: 'Lora', serif;
    font-size: 24px; font-weight: 700;
    color: var(--text); margin-bottom: 2px;
    line-height: 1.1;
}

.kpi-label {
    font-size: 12px; font-weight: 700; color: var(--text-muted);
}

/* ════════════════════════
   CHARTS ROW
════════════════════════ */
.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 14px;
}

.chart-card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow-sm);
}

.chart-card h4 {
    font-size: 14px; font-weight: 900;
    margin-bottom: 14px;
    display: flex; align-items: center; gap: 7px;
}
.chart-card h4 i { color: var(--primary); font-size: 13px; }

/* ════════════════════════
   SECONDARY METRICS
════════════════════════ */
.metrics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 14px;
}

/* ════════════════════════
   TABLE CARD
════════════════════════ */
.table-card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.table-card-header {
    padding: 15px 20px;
    border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}

.table-card-header h4 {
    font-size: 14px; font-weight: 900;
    display: flex; align-items: center; gap: 7px;
}
.table-card-header h4 i { color: var(--primary); font-size: 13px; }

table {
    width: 100%; border-collapse: collapse;
}

th {
    padding: 10px 16px;
    font-size: 11px; font-weight: 900;
    text-transform: uppercase; letter-spacing: .08em;
    color: var(--text-muted);
    background: var(--bg);
    text-align: left;
    border-bottom: 1.5px solid var(--border);
}

td {
    padding: 11px 16px;
    font-size: 13px; font-weight: 700;
    border-bottom: 1px solid var(--border);
    color: var(--text-mid);
}

tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafbfd; }

.badge-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 40px;
    font-size: 11px; font-weight: 900;
}
.bp-cash   { background: var(--green-lt); color: var(--green); }
.bp-card   { background: var(--indigo-lt); color: var(--indigo); }
.bp-qr     { background: var(--amber-lt); color: var(--amber); }
.bp-bank   { background: var(--sky-lt);   color: var(--sky); }
.bp-dine   { background: var(--primary-lt); color: var(--primary); }
.bp-take   { background: var(--amber-lt); color: var(--amber); }

/* ════════════════════════
   TOP PRODUCTS
════════════════════════ */
.prod-row {
    display: flex; align-items: center; gap: 12px;
    padding: 9px 16px;
    border-bottom: 1px solid var(--border);
    transition: background .13s;
}
.prod-row:last-child { border-bottom: none; }
.prod-row:hover { background: #fafbfd; }

.prod-rank {
    width: 24px; height: 24px;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 900;
    flex-shrink: 0;
}
.rank-1 { background: #fef3c7; color: #d97706; }
.rank-2 { background: #f1f5f9; color: #64748b; }
.rank-3 { background: #fff7ed; color: #ea580c; }
.rank-n { background: var(--bg); color: var(--text-muted); }

.prod-name { flex: 1; font-size: 13px; font-weight: 800; color: var(--text); }

.prod-bar-wrap { width: 90px; height: 6px; background: var(--bg); border-radius: 3px; overflow: hidden; }
.prod-bar { height: 100%; background: var(--primary); border-radius: 3px; }

.prod-qty { font-size: 12px; font-weight: 800; color: var(--text-mid); min-width: 52px; text-align: right; }
.prod-rev { font-size: 12px; font-weight: 800; color: var(--green); min-width: 80px; text-align: right; }

/* ════════════════════════
   HOURLY HEATMAP
════════════════════════ */
.heatmap-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 4px;
}

.hm-cell {
    aspect-ratio: 1;
    border-radius: 5px;
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; font-weight: 800;
    color: var(--text-muted);
    background: var(--bg);
    cursor: default;
    transition: all .15s;
    position: relative;
}
.hm-cell:hover::after {
    content: attr(data-tip);
    position: absolute; bottom: calc(100% + 5px); left: 50%;
    transform: translateX(-50%);
    background: var(--text); color: #fff;
    padding: 4px 8px; border-radius: 5px;
    font-size: 11px; white-space: nowrap;
    z-index: 10; pointer-events: none;
}

/* ════════════════════════
   QUICK LINKS
════════════════════════ */
.quick-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
}

.ql-card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 12px;
    text-align: center;
    text-decoration: none;
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    transition: all .17s;
    box-shadow: var(--shadow-sm);
}
.ql-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.ql-icon {
    width: 42px; height: 42px;
    border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    transition: all .17s;
}
.ql-card:hover .ql-icon { transform: scale(1.08); }

.ql-label { font-size: 12px; font-weight: 900; color: var(--text-mid); }

/* ════════════════════════
   RESPONSIVE
════════════════════════ */
@media (max-width: 1100px) {
    .kpi-grid      { grid-template-columns: repeat(2, 1fr); }
    .charts-grid   { grid-template-columns: 1fr; }
    .metrics-grid  { grid-template-columns: 1fr 1fr; }
    .quick-grid    { grid-template-columns: repeat(3,1fr); }
}

@media (max-width: 768px) {
    :root { --sidebar-w: 0px; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); --sidebar-w: 240px; }
    .main { margin-left: 0; }
    .kpi-grid { grid-template-columns: 1fr 1fr; }
    .metrics-grid { grid-template-columns: 1fr; }
    .quick-grid { grid-template-columns: repeat(2,1fr); }
}

/* Scrollbar */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-thumb { background: var(--border-dk); border-radius: 5px; }
</style>
</head>
<body>

<!-- ════════════════════════
   SIDEBAR
════════════════════════ -->
<nav class="sidebar" id="sidebar">
    <div class="sb-brand">
        <div class="sb-logo"><i class="fa-solid fa-utensils"></i></div>
        <div class="sb-brand-text">
            <h2>La-zogan</h2>
            <small>Owner Panel</small>
        </div>
    </div>

    <div class="sb-nav">
        <div class="nav-group-label">Overview</div>
        <button class="nav-item active" onclick="scrollTo('overview')">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </button>

        <div class="nav-group-label">Reports</div>
        <button class="nav-item" onclick="scrollTo('sales-report')">
            <i class="fa-solid fa-chart-line"></i> Sales Report
        </button>
        <button class="nav-item" onclick="scrollTo('products-report')">
            <i class="fa-solid fa-trophy"></i> Top Products
        </button>
        <button class="nav-item" onclick="scrollTo('payment-report')">
            <i class="fa-solid fa-credit-card"></i> Payments
        </button>
        <button class="nav-item" onclick="scrollTo('orders-report')">
            <i class="fa-solid fa-receipt"></i> Recent Orders
        </button>

        <div class="nav-group-label">Management</div>
        <a class="nav-item" href="admin/products.php">
            <i class="fa-solid fa-bowl-food"></i> Products
        </a>
        <a class="nav-item" href="admin/categories.php">
            <i class="fa-solid fa-tags"></i> Categories
        </a>
        <a class="nav-item" href="admin/users.php">
            <i class="fa-solid fa-users"></i> Staff / Users
        </a>
        <a class="nav-item" href="admin/orders.php">
            <i class="fa-solid fa-list-check"></i> All Orders
        </a>
        <a class="nav-item" href="admin/sales.php">
            <i class="fa-solid fa-file-invoice-dollar"></i> Sales History
        </a>
    </div>

    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar"><?php echo strtoupper(substr($_SESSION["full_name"] ?? "A", 0, 1)); ?></div>
            <div class="sb-user-info">
                <div class="name"><?php echo htmlspecialchars($_SESSION["full_name"] ?? "Admin"); ?></div>
                <div class="role">Owner</div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout-sb">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<!-- ════════════════════════
   MAIN
════════════════════════ -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <div>
                <div class="page-title">Owner Dashboard</div>
                <div class="breadcrumb">
                    <i class="fa-solid fa-house"></i> Home
                    <i class="fa-solid fa-chevron-right"></i> Dashboard
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="date-badge">
                <i class="fa-regular fa-calendar"></i>
                <?php echo date('l, d M Y'); ?>
            </div>
            <a href="pos.php" class="btn-pos">
                <i class="fa-solid fa-cash-register"></i> Go to POS
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- ══ KPI CARDS ══ -->
        <div class="section" id="overview">
            <div class="section-header">
                <div class="section-title"><i class="fa-solid fa-gauge-high"></i> Key Performance</div>
                <span style="font-size:12px;color:var(--text-muted);font-weight:700;">
                    <i class="fa-regular fa-clock"></i> Live data
                </span>
            </div>
            <div class="kpi-grid">
                <!-- Today Sales -->
                <div class="kpi-card kpi-orange">
                    <div class="kpi-top">
                        <div class="kpi-icon ki-orange"><i class="fa-solid fa-coins"></i></div>
                        <?php
                        $dir = $today_vs_yesterday >= 0 ? 'up' : 'down';
                        $icon = $dir === 'up' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down';
                        ?>
                        <span class="kpi-badge badge-<?php echo $dir; ?>">
                            <i class="fa-solid <?php echo $icon; ?>"></i>
                            <?php echo abs(round($today_vs_yesterday, 1)); ?>%
                        </span>
                    </div>
                    <div class="kpi-value">Rs. <?php echo number_format($today_sales, 0); ?></div>
                    <div class="kpi-label">Today's Sales &bull; <?php echo $today_orders; ?> orders</div>
                </div>

                <!-- Month Sales -->
                <div class="kpi-card kpi-indigo">
                    <div class="kpi-top">
                        <div class="kpi-icon ki-indigo"><i class="fa-solid fa-calendar-check"></i></div>
                        <span class="kpi-badge badge-neu"><?php echo date('M'); ?></span>
                    </div>
                    <div class="kpi-value">Rs. <?php echo number_format($month_sales, 0); ?></div>
                    <div class="kpi-label">This Month &bull; <?php echo $month_orders; ?> orders</div>
                </div>

                <!-- Total Sales -->
                <div class="kpi-card kpi-green">
                    <div class="kpi-top">
                        <div class="kpi-icon ki-green"><i class="fa-solid fa-sack-dollar"></i></div>
                        <span class="kpi-badge badge-up"><i class="fa-solid fa-check"></i> All time</span>
                    </div>
                    <div class="kpi-value">Rs. <?php echo number_format($total_sales, 0); ?></div>
                    <div class="kpi-label">Total Revenue &bull; <?php echo number_format($total_orders); ?> orders</div>
                </div>

                <!-- Avg Order Value -->
                <div class="kpi-card kpi-amber">
                    <div class="kpi-top">
                        <div class="kpi-icon ki-amber"><i class="fa-solid fa-chart-simple"></i></div>
                        <span class="kpi-badge badge-neu">avg</span>
                    </div>
                    <div class="kpi-value">Rs. <?php echo number_format($avg_order_val, 0); ?></div>
                    <div class="kpi-label">Avg Order Value</div>
                </div>
            </div>
        </div>

        <!-- ══ QUICK LINKS ══ -->
        <div class="section">
            <div class="section-header">
                <div class="section-title"><i class="fa-solid fa-bolt"></i> Quick Access</div>
            </div>
            <div class="quick-grid">
                <a href="admin/products.php" class="ql-card">
                    <div class="ql-icon" style="background:var(--primary-lt);color:var(--primary);">
                        <i class="fa-solid fa-bowl-food"></i>
                    </div>
                    <div class="ql-label">Products</div>
                    <div style="font-size:11px;color:var(--text-muted);font-weight:700;"><?php echo $total_products; ?> active</div>
                </a>
                <a href="admin/categories.php" class="ql-card">
                    <div class="ql-icon" style="background:var(--indigo-lt);color:var(--indigo);">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                    <div class="ql-label">Categories</div>
                    <div style="font-size:11px;color:var(--text-muted);font-weight:700;"><?php echo $total_categories; ?> active</div>
                </a>
                <a href="admin/users.php" class="ql-card">
                    <div class="ql-icon" style="background:var(--green-lt);color:var(--green);">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="ql-label">Staff</div>
                    <div style="font-size:11px;color:var(--text-muted);font-weight:700;"><?php echo $total_users; ?> users</div>
                </a>
                <a href="admin/orders.php" class="ql-card">
                    <div class="ql-icon" style="background:var(--amber-lt);color:var(--amber);">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div class="ql-label">All Orders</div>
                    <div style="font-size:11px;color:var(--text-muted);font-weight:700;"><?php echo number_format($total_orders); ?> total</div>
                </a>
                <a href="admin/sales.php" class="ql-card">
                    <div class="ql-icon" style="background:var(--sky-lt);color:var(--sky);">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                    <div class="ql-label">Sales History</div>
                    <div style="font-size:11px;color:var(--text-muted);font-weight:700;">Full report</div>
                </a>
            </div>
        </div>

        <!-- ══ SALES CHART + PAYMENT PIE ══ -->
        <div class="section" id="sales-report">
            <div class="section-header">
                <div class="section-title"><i class="fa-solid fa-chart-line"></i> Sales Report — Last 7 Days</div>
            </div>
            <div class="charts-grid">
                <div class="chart-card">
                    <h4><i class="fa-solid fa-chart-bar"></i> Daily Sales (Rs.)</h4>
                    <canvas id="dailyChart" height="90"></canvas>
                </div>
                <div class="chart-card" id="payment-report">
                    <h4><i class="fa-solid fa-credit-card"></i> Payment Methods</h4>
                    <canvas id="pmChart" height="150"></canvas>
                    <div id="pmLegend" style="margin-top:12px;"></div>
                </div>
            </div>
        </div>

        <!-- ══ MONTHLY TREND ══ -->
        <div class="section">
            <div class="section-header">
                <div class="section-title"><i class="fa-solid fa-chart-area"></i> Monthly Revenue Trend</div>
            </div>
            <div class="chart-card">
                <h4><i class="fa-solid fa-calendar"></i> Last 6 Months</h4>
                <canvas id="monthlyChart" height="70"></canvas>
            </div>
        </div>

        <!-- ══ 2-COL: ORDER TYPE + HOURLY ══ -->
        <div class="metrics-grid section">
            <!-- Order type card -->
            <div class="chart-card">
                <h4><i class="fa-solid fa-chair"></i> Order Types</h4>
                <?php
                $dine_cnt  = $ot_data['dine_in']['cnt']  ?? 0;
                $take_cnt  = $ot_data['takeaway']['cnt'] ?? 0;
                $dine_rev  = $ot_data['dine_in']['total']  ?? 0;
                $take_rev  = $ot_data['takeaway']['total'] ?? 0;
                $total_ot  = $dine_cnt + $take_cnt;
                $dine_pct  = $total_ot > 0 ? round($dine_cnt/$total_ot*100) : 0;
                $take_pct  = 100 - $dine_pct;
                ?>
                <canvas id="otChart" height="140"></canvas>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px;">
                    <div style="background:var(--primary-lt);border-radius:8px;padding:10px 12px;">
                        <div style="font-size:11px;color:var(--primary);font-weight:900;text-transform:uppercase;letter-spacing:.07em;">Dine In</div>
                        <div style="font-size:18px;font-weight:900;color:var(--text);"><?php echo $dine_cnt; ?></div>
                        <div style="font-size:11px;color:var(--text-muted);font-weight:700;">Rs. <?php echo number_format($dine_rev,0); ?></div>
                    </div>
                    <div style="background:var(--amber-lt);border-radius:8px;padding:10px 12px;">
                        <div style="font-size:11px;color:var(--amber);font-weight:900;text-transform:uppercase;letter-spacing:.07em;">Takeaway</div>
                        <div style="font-size:18px;font-weight:900;color:var(--text);"><?php echo $take_cnt; ?></div>
                        <div style="font-size:11px;color:var(--text-muted);font-weight:700;">Rs. <?php echo number_format($take_rev,0); ?></div>
                    </div>
                </div>
            </div>

            <!-- Hourly heatmap -->
            <div class="chart-card" style="grid-column:span 2;">
                <h4><i class="fa-solid fa-clock"></i> Today's Hourly Activity</h4>
                <div class="heatmap-grid" id="heatmapGrid"></div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:11px;color:var(--text-muted);font-weight:700;">
                    <span>Low</span>
                    <?php for($i=0;$i<=5;$i++): ?>
                    <div style="width:18px;height:18px;border-radius:4px;background:<?php
                        $bg = ['#f1f3f8','#fde8dc','#f9c4a6','#f4a07a','#ea7044','#d95c2b'];
                        echo $bg[$i];
                    ?>;"></div>
                    <?php endfor; ?>
                    <span>High</span>
                </div>
            </div>
        </div>

        <!-- ══ TOP PRODUCTS ══ -->
        <div class="section" id="products-report">
            <div class="section-header">
                <div class="section-title"><i class="fa-solid fa-trophy"></i> Top Selling Products</div>
                <a href="admin/products.php" class="section-action">
                    Manage Products <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-card">
                <div class="table-card-header">
                    <h4><i class="fa-solid fa-ranking-star"></i> By Quantity Sold</h4>
                </div>
                <?php if (!empty($top_products)):
                    $max_qty = max(array_column($top_products, 'qty_sold'));
                    foreach ($top_products as $i => $p):
                        $rank = $i + 1;
                        $pct  = $max_qty > 0 ? round($p['qty_sold']/$max_qty*100) : 0;
                        $rank_cls = $rank==1?'rank-1':($rank==2?'rank-2':($rank==3?'rank-3':'rank-n'));
                ?>
                <div class="prod-row">
                    <div class="prod-rank <?php echo $rank_cls; ?>"><?php echo $rank; ?></div>
                    <div class="prod-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
                    <div class="prod-bar-wrap"><div class="prod-bar" style="width:<?php echo $pct; ?>%"></div></div>
                    <div class="prod-qty"><i class="fa-solid fa-box" style="font-size:10px;margin-right:3px;"></i><?php echo number_format($p['qty_sold']); ?> sold</div>
                    <div class="prod-rev">Rs. <?php echo number_format($p['revenue'], 0); ?></div>
                </div>
                <?php endforeach; else: ?>
                <div style="padding:24px;text-align:center;color:var(--text-muted);font-weight:700;font-size:13px;">
                    No sales data yet.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══ RECENT ORDERS ══ -->
        <div class="section" id="orders-report">
            <div class="section-header">
                <div class="section-title"><i class="fa-solid fa-receipt"></i> Recent Orders</div>
                <a href="admin/orders.php" class="section-action">
                    View All <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date &amp; Time</th>
                            <th>Cashier</th>
                            <th>Type</th>
                            <th>Payment</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_orders)): foreach ($recent_orders as $o): ?>
                        <tr>
                            <td><strong>#<?php echo $o['order_id']; ?></strong></td>
                            <td><?php echo date('d M, h:i A', strtotime($o['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($o['cashier'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($o['order_type'] == 'dine_in'): ?>
                                    <span class="badge-pill bp-dine"><i class="fa-solid fa-chair"></i> Dine In</span>
                                <?php else: ?>
                                    <span class="badge-pill bp-take"><i class="fa-solid fa-bag-shopping"></i> Takeaway</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $pm  = $o['payment_method'];
                                $cls = strtolower(str_replace(' ','_',$pm));
                                $cls_map = ['Cash'=>'bp-cash','Card'=>'bp-card','QR'=>'bp-qr','Bank Transfer'=>'bp-bank'];
                                $icon_map= ['Cash'=>'fa-money-bill-wave','Card'=>'fa-credit-card','QR'=>'fa-qrcode','Bank Transfer'=>'fa-building-columns'];
                                $pill_cls = $cls_map[$pm] ?? 'bp-cash';
                                $pill_ico = $icon_map[$pm] ?? 'fa-money-bill';
                                ?>
                                <span class="badge-pill <?php echo $pill_cls; ?>">
                                    <i class="fa-solid <?php echo $pill_ico; ?>"></i> <?php echo htmlspecialchars($pm); ?>
                                </span>
                            </td>
                            <td><strong style="color:var(--primary);">Rs. <?php echo number_format($o['total_amount'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<script>
/* ════ CHART.JS DEFAULTS ════ */
Chart.defaults.font.family = "'Nunito', sans-serif";
Chart.defaults.font.weight = '700';
Chart.defaults.color = '#8e94b0';

const prim    = '#d95c2b';
const indigo  = '#4f46e5';
const green   = '#16a34a';
const amber   = '#d97706';
const sky     = '#0284c7';

/* ── Daily Bar Chart ── */
const dailyCtx = document.getElementById('dailyChart');
if (dailyCtx) {
    new Chart(dailyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($daily_labels); ?>,
            datasets: [
                {
                    label: 'Sales (Rs.)',
                    data: <?php echo json_encode($daily_amounts); ?>,
                    backgroundColor: function(ctx) {
                        const chart = ctx.chart;
                        const {ctx: c, chartArea} = chart;
                        if (!chartArea) return prim;
                        const grad = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        grad.addColorStop(0, 'rgba(217,92,43,.85)');
                        grad.addColorStop(1, 'rgba(217,92,43,.2)');
                        return grad;
                    },
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'Orders',
                    data: <?php echo json_encode($daily_counts); ?>,
                    type: 'line',
                    borderColor: indigo,
                    backgroundColor: 'rgba(79,70,229,.08)',
                    borderWidth: 2,
                    pointBackgroundColor: indigo,
                    pointRadius: 4,
                    tension: .4,
                    yAxisID: 'y2',
                    fill: true,
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, padding: 16, font: { size: 12 } } },
                tooltip: { callbacks: {
                    label: function(c) {
                        if (c.datasetIndex === 0) return ' Rs. ' + c.raw.toLocaleString();
                        return ' ' + c.raw + ' orders';
                    }
                }}
            },
            scales: {
                y:  { grid: { color: '#f0f2f8' }, ticks: { callback: v => 'Rs.' + (v>=1000?Math.round(v/1000)+'k':v) } },
                y2: { position: 'right', grid: { display: false }, ticks: { callback: v => v + ' ord' } }
            }
        }
    });
}

/* ── Payment Method Doughnut ── */
const pmCtx = document.getElementById('pmChart');
if (pmCtx) {
    const colors = [prim, indigo, amber, sky, green, '#ec4899'];
    const pmLabels = <?php echo json_encode($pm_labels); ?>;
    const pmTotals = <?php echo json_encode($pm_totals); ?>;

    new Chart(pmCtx, {
        type: 'doughnut',
        data: {
            labels: pmLabels,
            datasets: [{ data: pmTotals, backgroundColor: colors, borderWidth: 3, borderColor: '#fff', hoverOffset: 6 }]
        },
        options: {
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ' Rs. ' + c.raw.toLocaleString() + ' — ' + c.label } }
            }
        }
    });

    /* Custom legend */
    const leg = document.getElementById('pmLegend');
    pmLabels.forEach((l, i) => {
        const t = pmTotals[i];
        const div = document.createElement('div');
        div.style = 'display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:12px;font-weight:800;color:#454a66;';
        div.innerHTML = `<span style="width:10px;height:10px;border-radius:3px;background:${colors[i]};flex-shrink:0;"></span>
                         <span style="flex:1;">${l}</span>
                         <span style="color:#1c2038;">Rs. ${t.toLocaleString()}</span>`;
        leg.appendChild(div);
    });
}

/* ── Monthly Trend ── */
const monthlyCtx = document.getElementById('monthlyChart');
if (monthlyCtx) {
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($mon_labels); ?>,
            datasets: [{
                label: 'Monthly Revenue',
                data: <?php echo json_encode($mon_totals); ?>,
                borderColor: prim, borderWidth: 3,
                backgroundColor: function(ctx) {
                    const chart = ctx.chart;
                    const {ctx: c, chartArea} = chart;
                    if (!chartArea) return 'transparent';
                    const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    g.addColorStop(0, 'rgba(217,92,43,.18)');
                    g.addColorStop(1, 'rgba(217,92,43,.01)');
                    return g;
                },
                fill: true, tension: .4,
                pointBackgroundColor: prim, pointRadius: 5, pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ' Rs. ' + c.raw.toLocaleString() } }
            },
            scales: {
                y: { grid: { color: '#f0f2f8' }, ticks: { callback: v => 'Rs.' + (v>=1000?Math.round(v/1000)+'k':v) } }
            }
        }
    });
}

/* ── Order Type Doughnut ── */
const otCtx = document.getElementById('otChart');
if (otCtx) {
    new Chart(otCtx, {
        type: 'doughnut',
        data: {
            labels: ['Dine In', 'Takeaway'],
            datasets: [{ data: [<?php echo $dine_cnt; ?>, <?php echo $take_cnt; ?>], backgroundColor: [prim, amber], borderWidth: 3, borderColor: '#fff' }]
        },
        options: {
            cutout: '65%',
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14 } } }
        }
    });
}

/* ── Hourly Heatmap ── */
(function buildHeatmap() {
    const hourly = <?php echo json_encode($hourly); ?>;
    const grid   = document.getElementById('heatmapGrid');
    if (!grid) return;

    const maxCnt = Math.max(...hourly.map(h => h.cnt), 1);
    const colors = ['#f1f3f8','#fde8dc','#f9c4a6','#f4a07a','#ea7044','#d95c2b'];

    hourly.forEach((h, i) => {
        const lvl   = Math.round((h.cnt / maxCnt) * 5);
        const bg    = colors[lvl];
        const label = i === 0 ? '12am' : (i < 12 ? i+'am' : (i===12?'12pm':(i-12)+'pm'));
        const cell  = document.createElement('div');
        cell.className = 'hm-cell';
        cell.style.background = bg;
        cell.style.color = lvl >= 3 ? '#fff' : '#8e94b0';
        cell.textContent = label;
        cell.setAttribute('data-tip', `${label}: ${h.cnt} orders • Rs.${Math.round(h.total).toLocaleString()}`);
        grid.appendChild(cell);
    });
})();

/* ── Sidebar scroll nav ── */
function scrollTo(id) {
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });

    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    event.currentTarget.classList.add('active');
}
</script>
</body>
</html>