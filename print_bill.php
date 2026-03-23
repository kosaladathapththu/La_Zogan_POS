<?php
include 'db.php';

$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;
if ($order_id <= 0) {
    die("Invalid order ID.");
}

$order_stmt = $conn->prepare("
    SELECT o.*, t.table_name, u.full_name
    FROM orders o
    LEFT JOIN restaurant_tables t ON o.table_id = t.table_id
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
    LIMIT 1
");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();
$order_stmt->close();

if (!$order) {
    die("Order not found.");
}

$item_stmt = $conn->prepare("
    SELECT 
        oi.*,
        COALESCE(p.product_name, oi.custom_item_name, 'Item') AS item_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.order_item_id ASC
");
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$items_result = $item_stmt->get_result();

$all_items = [];
while ($row = $items_result->fetch_assoc()) {
    $all_items[] = $row;
}
$item_stmt->close();

$order_number = !empty($order['order_number']) ? $order['order_number'] : ('ORD-' . str_pad($order['order_id'], 5, '0', STR_PAD_LEFT));
$order_type_label = ucwords(str_replace('_', ' ', $order['order_type'] ?? 'dine_in'));
$has_table = !empty($order['table_name']) && $order['table_name'] !== 'N/A';
$has_customer = !empty($order['customer_name']);
$cash_given = (float)($order['cash_given'] ?? 0);
$balance = (float)($order['balance'] ?? 0);
$discount = (float)($order['discount'] ?? 0);
$subtotal = (float)($order['subtotal'] ?? 0);
$total = (float)($order['total_amount'] ?? 0);
$pm = $order['payment_method'] ?? 'Cash';
$total_qty = 0;
foreach ($all_items as $it) {
    $total_qty += (int)$it['quantity'];
}
$is_cash = ($pm === 'Cash');

$bill_datetime = !empty($order['paid_at']) ? $order['paid_at'] : $order['created_at'];

function fmt($v) {
    $s = number_format((float)$v, 2);
    return str_ends_with($s, '.00') ? rtrim(rtrim($s, '0'), '.') : $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bill <?php echo htmlspecialchars($order_number); ?> — The La-Zogan</title>

<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: #d4d6dc;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 28px 12px 72px;
    font-family: 'Nunito', sans-serif;
    -webkit-font-smoothing: antialiased;
}

.receipt {
    width: 302px;
    background: #fff;
    color: #111;
    padding: 18px 15px 22px;
    box-shadow: 0 8px 32px rgba(0,0,0,.26);
    border-radius: 3px;
    font-family: 'Nunito', sans-serif;
    font-size: 11.5px;
    line-height: 1.5;
}

.hdr { text-align: center; padding-bottom: 10px; }

.shop-name {
    font-size: 26px;
    font-weight: 900;
    letter-spacing: .01em;
    color: #111;
    line-height: 1;
    margin-bottom: 3px;
}

.shop-sub {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .2em;
    text-transform: uppercase;
    color: #555;
    margin-bottom: 7px;
}

.shop-addr {
    font-size: 11px;
    color: #444;
    line-height: 1.65;
    font-weight: 600;
}

.sep-d { border: none; border-top: 1px dashed #999; margin: 8px 0; }
.sep-s { border: none; border-top: 2px solid #111; margin: 8px 0; }
.sep-eq  {
    font-size: 10px;
    font-weight: 600;
    text-align: center;
    color: #555;
    letter-spacing: .3px;
    margin: 7px 0;
    line-height: 1;
    overflow: hidden;
}

.meta { width: 100%; border-collapse: collapse; font-size: 11.5px; }
.meta td { padding: 1.5px 0; vertical-align: top; }
.meta .lbl { width: 90px; color: #666; font-weight: 600; }
.meta .sep-col { width: 12px; color: #666; }
.meta .val { font-weight: 800; color: #111; }

.type-tag {
    display: inline-block;
    border: 1.5px solid #111;
    padding: 0 5px;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    border-radius: 3px;
    line-height: 1.7;
}

.itbl { width: 100%; border-collapse: collapse; font-size: 11.5px; }

.itbl thead tr {
    border-top: 2px solid #111;
    border-bottom: 2px solid #111;
}

.itbl th {
    padding: 4px 2px;
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #111;
}
.itbl .h1 { text-align: left; }
.itbl .h2 { text-align: center; width: 26px; }
.itbl .h3 { text-align: right; width: 52px; }
.itbl .h4 { text-align: right; width: 56px; }

.itbl tbody tr { border-bottom: 1px dashed #ccc; }
.itbl tbody tr:last-child { border-bottom: none; }

.itbl td { padding: 4px 2px; vertical-align: top; }
.itbl .d1 { text-align: left; }
.itbl .d2 { text-align: center; font-weight: 800; }
.itbl .d3 { text-align: right; color: #555; font-weight: 600; }
.itbl .d4 { text-align: right; font-weight: 800; }

.iname {
    font-size: 12px;
    font-weight: 800;
    color: #111;
    line-height: 1.3;
}
.ctag {
    font-size: 9px;
    font-weight: 600;
    color: #888;
}

.summ { width: 100%; border-collapse: collapse; font-size: 11.5px; }
.summ td { padding: 2.5px 0; }
.summ .sl { color: #555; font-weight: 600; }
.summ .sr { text-align: right; font-weight: 700; }

.g-row td {
    font-size: 18px;
    font-weight: 900;
    color: #111;
    letter-spacing: .01em;
    border-top: 2px solid #111;
    border-bottom: 2px solid #111;
    padding: 6px 0;
}
.g-row .gr { text-align: right; }

.c-row td { font-weight: 700; }
.b-row td { font-weight: 800; border-top: 1px dashed #999; padding-top: 4px; }

.ftr {
    text-align: center;
    font-size: 11px;
    color: #444;
    line-height: 1.7;
}
.ftr .ft1 { font-size: 14px; font-weight: 900; color: #111; letter-spacing: .01em; }
.ftr .ft2 { font-weight: 600; color: #555; }
.ftr .ft3 { font-size: 10px; color: #666; font-weight: 600; margin-top: 2px; }

.dev-credit {
    text-align: center;
    font-size: 8.5px;
    color: #bbb;
    font-weight: 600;
    margin-top: 6px;
    letter-spacing: .03em;
    line-height: 1.6;
    border-top: 1px dashed #ddd;
    padding-top: 6px;
}
.dev-credit strong { color: #aaa; font-weight: 800; }

.ref-line {
    text-align: center;
    font-size: 9px;
    font-weight: 600;
    color: #bbb;
    letter-spacing: .04em;
    margin-top: 5px;
}

.actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
    justify-content: center;
}

.a-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 22px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 800;
    font-family: 'Nunito', sans-serif;
    border: 1.5px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: all .16s;
}

.a-print { background: #1c2038; color: #fff; border-color: #1c2038; }
.a-print:hover { background: #2d3252; transform: translateY(-1px); }

.a-drawer {
    background: #15803d;
    color: #fff;
    border-color: #15803d;
    display: <?php echo $is_cash ? 'inline-flex' : 'none'; ?>;
}
.a-drawer:hover { background: #166534; transform: translateY(-1px); }
.a-drawer:disabled { background: #888; border-color: #888; cursor: not-allowed; transform: none; }

.a-back { background: #fff; color: #454a66; border-color: #c8ccd8; }
.a-back:hover { border-color: #777; color: #111; }

.qz-status {
    display: <?php echo $is_cash ? 'flex' : 'none'; ?>;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    font-family: 'Nunito', sans-serif;
    background: #f1f3f8;
    border: 1.5px solid #d0d3de;
    color: #454a66;
    width: 302px;
    transition: all .2s;
}
.qz-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #bbb;
    flex-shrink: 0;
    transition: background .3s;
}
.qz-status.connecting .qz-dot { background: #d97706; animation: pulse .9s infinite; }
.qz-status.connected .qz-dot { background: #16a34a; }
.qz-status.error .qz-dot { background: #dc2626; }

@keyframes pulse {
    0%,100% { opacity: 1; }
    50% { opacity: .3; }
}

@media print {
    @page { size: 80mm auto; margin: 2mm 0 4mm; }
    body { background: none; padding: 0; margin: 0; width: 80mm; }
    .receipt {
        width: 80mm;
        padding: 2mm 3.5mm 5mm;
        box-shadow: none;
        border-radius: 0;
        font-size: 11pt;
    }
    * {
        color: #000 !important;
        background: transparent !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .summ .sl, .meta .lbl, .meta .sep-col,
    .shop-sub, .shop-addr, .ctag,
    .ftr .ft2, .ftr .ft3, .ref-line, .dev-credit {
        color: #555 !important;
    }
    .actions, .qz-status { display: none !important; }
}
</style>
</head>
<body>

<div class="receipt">

    <div class="hdr">
        <div class="shop-name">The La-Zogan</div>
        <div class="shop-sub">Restaurant &amp; Café</div>
        <div class="shop-addr">
            Anuradapura Road, Magulagama, Padeniya<br>
            Tel: 070 550 8859
        </div>
    </div>

    <div class="sep-eq">================================</div>

    <table class="meta">
        <tr>
            <td class="lbl">Bill No</td>
            <td class="sep-col">:</td>
            <td class="val"><?php echo htmlspecialchars($order_number); ?></td>
        </tr>
        <tr>
            <td class="lbl">Date</td>
            <td class="sep-col">:</td>
            <td class="val"><?php echo date('d M Y', strtotime($bill_datetime)); ?></td>
        </tr>
        <tr>
            <td class="lbl">Time</td>
            <td class="sep-col">:</td>
            <td class="val"><?php echo date('h:i A', strtotime($bill_datetime)); ?></td>
        </tr>
        <tr>
            <td class="lbl">Order Type</td>
            <td class="sep-col">:</td>
            <td class="val"><span class="type-tag"><?php echo htmlspecialchars($order_type_label); ?></span></td>
        </tr>
        <?php if ($has_table): ?>
        <tr>
            <td class="lbl">Table</td>
            <td class="sep-col">:</td>
            <td class="val"><?php echo htmlspecialchars($order['table_name']); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($has_customer): ?>
        <tr>
            <td class="lbl">Customer</td>
            <td class="sep-col">:</td>
            <td class="val"><?php echo htmlspecialchars($order['customer_name']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td class="lbl">Cashier</td>
            <td class="sep-col">:</td>
            <td class="val"><?php echo htmlspecialchars($order['full_name']); ?></td>
        </tr>
        <tr>
            <td class="lbl">Payment</td>
            <td class="sep-col">:</td>
            <td class="val"><?php echo htmlspecialchars($pm); ?></td>
        </tr>
    </table>

    <div class="sep-eq">--------------------------------</div>

    <table class="itbl">
        <thead>
            <tr>
                <th class="h1">Item</th>
                <th class="h2">Qty</th>
                <th class="h3">Rate</th>
                <th class="h4">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_items as $item): ?>
            <tr>
                <td class="d1">
                    <div class="iname"><?php echo htmlspecialchars($item['item_name']); ?></div>
                    <?php if (empty($item['product_id'])): ?>
                        <div class="ctag">[custom]</div>
                    <?php endif; ?>
                </td>
                <td class="d2"><?php echo (int)$item['quantity']; ?></td>
                <td class="d3"><?php echo fmt($item['price']); ?></td>
                <td class="d4"><?php echo fmt($item['line_total']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="sep-eq">--------------------------------</div>

    <table class="summ">
        <tr>
            <td class="sl">Items</td>
            <td class="sr"><?php echo count($all_items); ?> lines / <?php echo $total_qty; ?> pcs</td>
        </tr>
        <tr>
            <td class="sl">Subtotal</td>
            <td class="sr">Rs <?php echo fmt($subtotal); ?></td>
        </tr>
        <?php if ($discount > 0): ?>
        <tr>
            <td class="sl">Discount</td>
            <td class="sr">- Rs <?php echo fmt($discount); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <table class="summ" style="margin:6px 0;">
        <tr class="g-row">
            <td>TOTAL</td>
            <td class="gr">Rs <?php echo fmt($total); ?></td>
        </tr>
    </table>

    <table class="summ">
        <?php if ($is_cash): ?>
        <tr class="c-row">
            <td class="sl">Cash Received</td>
            <td class="sr">Rs <?php echo fmt($cash_given); ?></td>
        </tr>
        <tr class="b-row">
            <td><?php echo $balance >= 0 ? 'Change Returned' : 'Balance Due'; ?></td>
            <td class="sr">Rs <?php echo fmt(abs($balance)); ?></td>
        </tr>
        <?php else: ?>
        <tr class="c-row">
            <td class="sl">Amount Paid</td>
            <td class="sr">Rs <?php echo fmt($total); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <table class="summ" style="margin-top:5px;">
        <tr>
            <td class="sl" style="font-size:10px;">Status</td>
            <td class="sr" style="font-size:10px;font-weight:900;">
                *** <?php echo strtoupper($order['payment_status'] ?? 'PAID'); ?> ***
            </td>
        </tr>
    </table>

    <div class="sep-eq">================================</div>

    <div class="ftr">
        <div class="ft1">Thank You For Your Visit!</div>
        <div class="ft2">Please Come Again</div>
        <div class="ft3">www.lazogan.lk &nbsp;&bull;&nbsp; 070 550 8859</div>
    </div>

    <div class="sep-eq">--------------------------------</div>

    <div class="ref-line">
        <?php echo htmlspecialchars($order_number); ?>
        &nbsp;&nbsp;
        <?php echo date('d/m/Y H:i', strtotime($bill_datetime)); ?>
    </div>

    <div class="dev-credit">
        <strong>Software by A.M.K.D. Athapaththu</strong><br>
        0719148762 &nbsp;&bull;&nbsp; kosalaathapaththu1234@gmail.com
    </div>

</div>

<div class="qz-status connecting" id="qzStatus">
    <div class="qz-dot" id="qzDot"></div>
    <span id="qzMsg">Connecting to QZ Tray…</span>
</div>

<div class="actions">
    <button class="a-btn a-print" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print Bill
    </button>
    <button class="a-btn a-drawer" id="drawerBtn" onclick="openDrawer()" disabled>
        <i class="fa-solid fa-cash-register"></i> Open Drawer
    </button>
    <a class="a-btn a-back" href="pos.php">
        <i class="fa-solid fa-arrow-left"></i> Back to POS
    </a>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qz-tray/2.2.2/qz-tray.js"></script>
<script>
const PRINTER_NAME = "POS-80";
const IS_CASH = <?php echo $is_cash ? 'true' : 'false'; ?>;

const statusBox = document.getElementById('qzStatus');
const statusMsg = document.getElementById('qzMsg');
const drawerBtn = document.getElementById('drawerBtn');

function setStatus(state, msg) {
    statusBox.className = 'qz-status ' + state;
    statusMsg.textContent = msg;
}

qz.security.setCertificatePromise(resolve => resolve());
qz.security.setSignatureAlgorithm("SHA512");
qz.security.setSignaturePromise(toSign => resolve => resolve());

async function connectQZ() {
    if (!IS_CASH || qz.websocket.isActive()) return;

    setStatus('connecting', 'Connecting to QZ Tray…');

    try {
        await qz.websocket.connect({ retries: 3, delay: 1 });
        setStatus('connected', 'QZ Tray connected — drawer ready');
        drawerBtn.disabled = false;
    } catch (err) {
        setStatus('error', 'QZ Tray not found. Is it running?');
        drawerBtn.disabled = true;
    }
}

async function openDrawer() {
    drawerBtn.disabled = true;
    drawerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Opening…';

    try {
        if (!qz.websocket.isActive()) {
            await connectQZ();
        }

        const config = qz.configs.create(PRINTER_NAME, { raw: true });

        await qz.print(config, [
            { type: 'raw', format: 'command', data: '\x1B\x40' },
            { type: 'raw', format: 'command', data: '\x1B\x70\x00\x19\xFA' }
        ]);

        drawerBtn.innerHTML = '<i class="fa-solid fa-check"></i> Drawer Opened';
        setStatus('connected', 'Cash drawer opened successfully');

        setTimeout(() => {
            drawerBtn.disabled = false;
            drawerBtn.innerHTML = '<i class="fa-solid fa-cash-register"></i> Open Drawer';
        }, 3000);

    } catch (err) {
        setStatus('error', 'Failed: ' + err.message);
        drawerBtn.disabled = false;
        drawerBtn.innerHTML = '<i class="fa-solid fa-cash-register"></i> Open Drawer';
    }
}

window.addEventListener('load', async function() {
    if (!IS_CASH) return;

    await connectQZ();

    if (!drawerBtn.disabled) {
        await openDrawer();
    }
});

qz.websocket.setClosedCallbacks(() => {
    if (IS_CASH) {
        setStatus('error', 'QZ Tray disconnected');
        drawerBtn.disabled = true;
    }
});

qz.websocket.setErrorCallbacks(() => {
    if (IS_CASH) {
        setStatus('error', 'QZ Tray error');
    }
});
</script>

</body>
</html>