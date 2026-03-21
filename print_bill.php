<?php
include 'db.php';

$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;
if ($order_id <= 0) die("Invalid order ID.");

$order_stmt = $conn->prepare("
    SELECT o.*, t.table_name, u.full_name
    FROM orders o
    LEFT JOIN restaurant_tables t ON o.table_id = t.table_id
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();
$order_stmt->close();
if (!$order) die("Order not found.");

$item_stmt = $conn->prepare("
    SELECT oi.*,
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
while ($row = $items_result->fetch_assoc()) $all_items[] = $row;
$item_stmt->close();

$order_type_label = ucwords(str_replace('_', ' ', $order['order_type']));
$has_table  = !empty($order['table_name']) && $order['table_name'] !== 'N/A';
$cash_given = (float)($order['cash_given'] ?? 0);
$balance    = (float)($order['balance']    ?? 0);
$discount   = (float)($order['discount']   ?? 0);
$subtotal   = (float)($order['subtotal']   ?? 0);
$total      = (float)($order['total_amount'] ?? 0);
$pm         = $order['payment_method'];
$total_qty  = array_sum(array_column($all_items, 'quantity'));
$is_cash    = ($pm === 'Cash');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bill #<?php printf('%05d', $order_id); ?> — The La-zogan</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans+Condensed:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: #c8cad0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 30px 12px 80px;
    font-family: 'IBM Plex Mono', monospace;
    -webkit-font-smoothing: antialiased;
}

/* ── RECEIPT ── */
.receipt {
    width: 302px;
    background: #fff;
    color: #000;
    padding: 18px 14px 22px;
    box-shadow: 0 8px 32px rgba(0,0,0,.28);
    border-radius: 2px;
    font-size: 11px;
    line-height: 1.5;
    letter-spacing: .01em;
}

.hdr { text-align: center; padding-bottom: 11px; }
.shop-name {
    font-family: 'IBM Plex Sans Condensed', sans-serif;
    font-size: 24px; font-weight: 800;
    letter-spacing: .02em; color: #000;
    line-height: 1; margin-bottom: 3px;
}
.shop-type {
    font-size: 8.5px; letter-spacing: .22em;
    text-transform: uppercase; color: #444; margin-bottom: 8px;
}
.shop-addr { font-size: 10.5px; color: #333; line-height: 1.7; }

.line-d  { border: none; border-top: 1px dashed #888; margin: 8px 0; }
.line-s  { border: none; border-top: 2px solid #000;  margin: 8px 0; }
.line-eq { font-size: 10px; text-align: center; color: #333; letter-spacing: .4px; margin: 7px 0; line-height: 1; overflow: hidden; }

.meta { width: 100%; border-collapse: collapse; font-size: 11px; }
.meta td { padding: 1.5px 0; vertical-align: top; }
.meta .lbl { width: 86px; color: #555; }
.meta .sep { width: 10px; color: #555; }
.meta .val { font-weight: 600; }

.type-tag {
    display: inline-block; border: 1px solid #000;
    padding: 0 4px; font-family: 'IBM Plex Sans Condensed', sans-serif;
    font-size: 8.5px; font-weight: 700;
    letter-spacing: .09em; text-transform: uppercase;
    border-radius: 1px; line-height: 1.6;
}

.itbl { width: 100%; border-collapse: collapse; font-size: 11px; }
.itbl thead tr { border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; }
.itbl th {
    padding: 3.5px 2px;
    font-family: 'IBM Plex Sans Condensed', sans-serif;
    font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .08em; color: #000;
}
.itbl .h1 { text-align: left; }
.itbl .h2 { text-align: center; width: 24px; }
.itbl .h3 { text-align: right;  width: 50px; }
.itbl .h4 { text-align: right;  width: 54px; }
.itbl tbody tr { border-bottom: 1px dashed #ccc; }
.itbl tbody tr:last-child { border-bottom: none; }
.itbl td { padding: 3.5px 2px; vertical-align: top; }
.itbl .d1 { text-align: left; }
.itbl .d2 { text-align: center; }
.itbl .d3 { text-align: right; color: #444; }
.itbl .d4 { text-align: right; font-weight: 600; }
.iname {
    font-family: 'IBM Plex Sans Condensed', sans-serif;
    font-weight: 700; font-size: 11.5px; color: #000; line-height: 1.3;
}
.cust-tag { font-size: 8.5px; font-weight: 400; color: #777; letter-spacing: .04em; }

.summ { width: 100%; border-collapse: collapse; font-size: 11px; }
.summ td { padding: 2px 0; }
.summ .sl { color: #444; }
.summ .sr { text-align: right; }

.g-row td {
    font-family: 'IBM Plex Sans Condensed', sans-serif;
    font-size: 17px; font-weight: 800; color: #000; letter-spacing: .02em;
    border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; padding: 5px 0;
}
.g-row .gr { text-align: right; }
.c-row td { font-weight: 600; }
.b-row td { font-weight: 700; border-top: 1px dashed #888; padding-top: 4px; padding-bottom: 3px; }

.ftr { text-align: center; line-height: 1.7; }
.ftr .ft1 {
    font-family: 'IBM Plex Sans Condensed', sans-serif;
    font-size: 13.5px; font-weight: 800; letter-spacing: .03em; color: #000;
}
.ftr .ft2 { font-size: 10px; color: #444; }
.ftr .ft3 { font-size: 9.5px; color: #555; margin-top: 3px; }
.ref-line {
    text-align: center; font-size: 8.5px; color: #aaa;
    letter-spacing: .06em; margin-top: 4px;
}

/* ═══════════════════════════════════════
   ACTION BUTTONS (screen only)
═══════════════════════════════════════ */
.actions {
    display: flex; gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
    justify-content: center;
}

.a-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 11px 22px; border-radius: 7px;
    font-size: 14px; font-weight: 700;
    font-family: 'IBM Plex Sans Condensed', sans-serif;
    letter-spacing: .03em;
    border: 1.5px solid transparent; cursor: pointer;
    text-decoration: none; transition: all .15s;
}

.a-print { background: #111; color: #fff; border-color: #111; }
.a-print:hover { background: #333; border-color: #333; transform: translateY(-1px); }

.a-drawer {
    background: #1a6b47; color: #fff; border-color: #1a6b47;
    display: <?php echo $is_cash ? 'inline-flex' : 'none'; ?>;
}
.a-drawer:hover { background: #145538; border-color: #145538; transform: translateY(-1px); }
.a-drawer:disabled { background: #888; border-color: #888; cursor: not-allowed; transform: none; }

.a-back { background: #fff; color: #333; border-color: #bbb; }
.a-back:hover { border-color: #555; color: #000; }

/* ── QZ STATUS INDICATOR ── */
.qz-status {
    display: <?php echo $is_cash ? 'flex' : 'none'; ?>;
    align-items: center; gap: 8px;
    margin-top: 12px;
    padding: 9px 16px;
    border-radius: 7px;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 12px; font-weight: 600;
    background: #f1f3f8;
    border: 1.5px solid #d0d3de;
    color: #454a66;
    width: 302px;
    transition: all .2s;
}
.qz-dot {
    width: 10px; height: 10px; border-radius: 50%;
    background: #bbb; flex-shrink: 0;
    transition: background .3s;
}
.qz-status.connecting .qz-dot { background: #d97706; animation: pulse .9s infinite; }
.qz-status.connected   .qz-dot { background: #16a34a; }
.qz-status.error       .qz-dot { background: #dc2626; }

@keyframes pulse {
    0%,100% { opacity:1; }
    50%      { opacity:.3; }
}

/* ═══════════════════════════════════════
   PRINT — 80mm thermal
═══════════════════════════════════════ */
@media print {
    @page { size: 80mm auto; margin: 2mm 0 5mm; }
    body { background: none; padding: 0; margin: 0; width: 80mm; }
    .receipt {
        width: 80mm; padding: 2mm 3.5mm 6mm;
        box-shadow: none; border-radius: 0; font-size: 11pt;
    }
    * { color: #000 !important; background: transparent !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .summ .sl, .meta .lbl, .meta .sep, .shop-type,
    .shop-addr, .cust-tag, .ftr .ft2, .ftr .ft3,
    .ref-line { color: #555 !important; }
    .actions, .qz-status { display: none !important; }
}
</style>
</head>
<body>

<!-- ═══════════ RECEIPT ═══════════ -->
<div class="receipt">

    <div class="hdr">
        <div class="shop-name">The La-zogan</div>
        <div class="shop-type">Restaurant &amp; Café</div>
        <div class="shop-addr">
            Anuradapura Road, Magulagama<br>
            Padeniya &nbsp;|&nbsp; Tel: 070 070 550
        </div>
    </div>

    <div class="line-eq">================================</div>

    <table class="meta">
        <tr><td class="lbl">Bill No.</td><td class="sep">:</td><td class="val">#<?php printf('%05d', $order['order_id']); ?></td></tr>
        <tr><td class="lbl">Date</td><td class="sep">:</td><td class="val"><?php echo date('d-M-Y', strtotime($order['created_at'])); ?></td></tr>
        <tr><td class="lbl">Time</td><td class="sep">:</td><td class="val"><?php echo date('h:i A', strtotime($order['created_at'])); ?></td></tr>
        <tr><td class="lbl">Order Type</td><td class="sep">:</td><td class="val"><span class="type-tag"><?php echo $order_type_label; ?></span></td></tr>
        <?php if ($has_table): ?>
        <tr><td class="lbl">Table</td><td class="sep">:</td><td class="val"><?php echo htmlspecialchars($order['table_name']); ?></td></tr>
        <?php endif; ?>
        <tr><td class="lbl">Cashier</td><td class="sep">:</td><td class="val"><?php echo htmlspecialchars($order['full_name']); ?></td></tr>
        <tr><td class="lbl">Payment</td><td class="sep">:</td><td class="val"><?php echo htmlspecialchars($pm); ?></td></tr>
    </table>

    <div class="line-eq">--------------------------------</div>

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
                    <?php if (!$item['product_id']): ?><div class="cust-tag">[custom]</div><?php endif; ?>
                </td>
                <td class="d2"><?php echo (int)$item['quantity']; ?></td>
                <td class="d3"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="d4"><?php echo number_format($item['line_total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="line-eq">--------------------------------</div>

    <table class="summ">
        <tr><td class="sl">Items</td><td class="sr"><?php echo count($all_items); ?> lines / <?php echo $total_qty; ?> pcs</td></tr>
        <tr><td class="sl">Subtotal</td><td class="sr">Rs. <?php echo number_format($subtotal, 2); ?></td></tr>
        <?php if ($discount > 0): ?>
        <tr><td class="sl">Discount</td><td class="sr">- Rs. <?php echo number_format($discount, 2); ?></td></tr>
        <?php endif; ?>
    </table>

    <table class="summ" style="margin:5px 0;">
        <tr class="g-row">
            <td>TOTAL</td>
            <td class="gr">Rs. <?php echo number_format($total, 2); ?></td>
        </tr>
    </table>

    <table class="summ">
        <?php if ($is_cash): ?>
        <tr class="c-row"><td class="sl">Cash Received</td><td class="sr">Rs. <?php echo number_format($cash_given, 2); ?></td></tr>
        <tr class="b-row">
            <td><?php echo $balance >= 0 ? 'Change Returned' : 'Balance Due'; ?></td>
            <td class="sr">Rs. <?php echo number_format(abs($balance), 2); ?></td>
        </tr>
        <?php else: ?>
        <tr class="c-row"><td class="sl">Amount Paid</td><td class="sr">Rs. <?php echo number_format($total, 2); ?></td></tr>
        <?php endif; ?>
    </table>

    <table class="summ" style="margin-top:4px;">
        <tr>
            <td class="sl" style="font-size:10px;">Payment Status</td>
            <td class="sr" style="font-size:10px;font-weight:700;">*** <?php echo strtoupper($order['payment_status'] ?? 'PAID'); ?> ***</td>
        </tr>
    </table>

    <div class="line-eq">================================</div>

    <div class="ftr">
        <div class="ft1">Thank You For Your Visit!</div>
        <div class="ft2">Please Come Again</div>
        <div class="ft3">www.lazogan.lk &nbsp;&bull;&nbsp; 070 070 550</div>
    </div>

    <div class="line-eq">--------------------------------</div>

    <div class="ref-line">
        REF: LZ-<?php printf('%05d', $order['order_id']); ?>
        &nbsp;&nbsp;
        <?php echo date('YmdHis', strtotime($order['created_at'])); ?>
    </div>

</div><!-- /receipt -->

<!-- ═══════════ QZ STATUS (cash only) ═══════════ -->
<div class="qz-status connecting" id="qzStatus">
    <div class="qz-dot" id="qzDot"></div>
    <span id="qzMsg">Connecting to QZ Tray…</span>
</div>

<!-- ═══════════ ACTION BUTTONS ═══════════ -->
<div class="actions">

    <button class="a-btn a-print" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print Bill
    </button>

    <!-- Cash Drawer button — only shown for Cash payments -->
    <button class="a-btn a-drawer" id="drawerBtn" onclick="openDrawer()" disabled>
        <i class="fa-solid fa-cash-register"></i> Open Drawer
    </button>

    <a class="a-btn a-back" href="pos.php">
        <i class="fa-solid fa-arrow-left"></i> Back to POS
    </a>

</div>

<!-- ═══════════════════════════════════════════════
     QZ TRAY DEPENDENCIES
     sha-256 is required for secure message signing.
     rsvp is the Promise library QZ uses internally.
═══════════════════════════════════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qz-tray/2.2.2/qz-tray.js"></script>

<script>
/* ═══════════════════════════════════════════════
   !!  CONFIGURE THIS  !!
   Set PRINTER_NAME to the exact Windows/Mac/Linux
   printer name of your receipt printer.
   e.g.  "EPSON TM-T88VI"  or  "POS-80C"
   Check: Control Panel → Devices and Printers
═══════════════════════════════════════════════ */
const PRINTER_NAME = "POS-80";          // ← CHANGE THIS to your printer name
const IS_CASH      = <?php echo $is_cash ? 'true' : 'false'; ?>;

/* ─── UI helpers ─── */
const statusBox = document.getElementById('qzStatus');
const statusMsg = document.getElementById('qzMsg');
const drawerBtn = document.getElementById('drawerBtn');

function setStatus(state, msg) {
    statusBox.className = 'qz-status ' + state;
    statusMsg.textContent = msg;
}

/* ─── QZ Tray: no-op signing (unsigned — works locally) ─── */
qz.security.setCertificatePromise(function(resolve) {
    resolve();   // No certificate — OK for local/intranet use
});

qz.security.setSignatureAlgorithm("SHA512");
qz.security.setSignaturePromise(function(toSign) {
    return function(resolve) {
        resolve();   // No private key — works without signing on local network
    };
});

/* ─── Connect to QZ Tray ─── */
async function connectQZ() {
    if (!IS_CASH) return;   // Only needed for cash payments

    try {
        if (qz.websocket.isActive()) return;

        setStatus('connecting', 'Connecting to QZ Tray…');

        await qz.websocket.connect({ retries: 3, delay: 1 });

        setStatus('connected', 'QZ Tray connected — drawer ready');
        drawerBtn.disabled = false;

    } catch (err) {
        setStatus('error', 'QZ Tray not found. Is it running?');
        drawerBtn.disabled = true;
        console.warn('QZ Tray connect error:', err);
    }
}

/* ─── Open Cash Drawer ─── */
async function openDrawer() {
    drawerBtn.disabled = true;
    drawerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Opening…';

    try {
        if (!qz.websocket.isActive()) {
            await connectQZ();
        }

        const config = qz.configs.create(PRINTER_NAME, {
            raw: true   // Send raw ESC/POS bytes directly to printer
        });

        /*
         *  ESC/POS Cash Drawer Pulse Command
         *  \x1B \x70  = ESC p  (cash drawer kick)
         *  \x00       = Pin 2  (most drawers use pin 2; try \x01 for pin 5)
         *  \x19       = ON pulse time  = 25 × 2ms = 50ms
         *  \xFA       = OFF pulse time = 250 × 2ms = 500ms
         *
         *  If your drawer doesn't open, try:
         *    \x1B\x70\x00\x30\xFF   (longer pulse)
         *    \x1B\x70\x01\x19\xFA   (pin 5 instead of pin 2)
         */
        const drawerCmd = [
            { type: 'raw', format: 'command', data: '\x1B\x40' },            // Init printer
            { type: 'raw', format: 'command', data: '\x1B\x70\x00\x19\xFA'} // Open drawer
        ];

        await qz.print(config, drawerCmd);

        drawerBtn.innerHTML = '<i class="fa-solid fa-check"></i> Drawer Opened';
        drawerBtn.style.background = '#145538';
        setStatus('connected', 'Cash drawer opened successfully');

        // Re-enable after 3 seconds
        setTimeout(() => {
            drawerBtn.disabled = false;
            drawerBtn.innerHTML = '<i class="fa-solid fa-cash-register"></i> Open Drawer';
        }, 3000);

    } catch (err) {
        console.error('Drawer error:', err);
        setStatus('error', 'Failed to open drawer: ' + err.message);
        drawerBtn.disabled = false;
        drawerBtn.innerHTML = '<i class="fa-solid fa-cash-register"></i> Open Drawer';

        /* Helpful error message */
        if (err.message && err.message.includes('Unable to find')) {
            alert(
                'Printer "' + PRINTER_NAME + '" not found.\n\n' +
                'Steps to fix:\n' +
                '1. Open Control Panel → Devices & Printers\n' +
                '2. Copy the exact printer name\n' +
                '3. Update PRINTER_NAME in bill.php'
            );
        }
    }
}

/* ─── Auto-connect and open drawer on Cash orders ─── */
window.addEventListener('load', async function () {
    if (!IS_CASH) return;

    await connectQZ();

    // Auto-open drawer on page load for cash payments
    if (!drawerBtn.disabled) {
        await openDrawer();
    }
});

/* ─── Handle QZ Tray disconnect ─── */
qz.websocket.setClosedCallbacks(function () {
    if (IS_CASH) {
        setStatus('error', 'QZ Tray disconnected');
        drawerBtn.disabled = true;
    }
});

qz.websocket.setErrorCallbacks(function (err) {
    console.error('QZ Tray error:', err);
    if (IS_CASH) setStatus('error', 'QZ Tray error — check console');
});
</script>

</body>
</html>