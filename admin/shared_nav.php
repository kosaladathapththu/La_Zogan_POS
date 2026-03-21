<?php
/* shared_nav.php — sidebar navigation for all admin pages */
$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar">
    <div class="sb-brand">
        <div class="sb-logo"><i class="fa-solid fa-utensils"></i></div>
        <div class="sb-brand-text">
            <h2>La-zogan</h2>
            <small>Owner Panel</small>
        </div>
    </div>
    <div class="sb-nav">
        <div class="nav-group-label">Overview</div>
        <a class="nav-item <?php echo in_array($current,['dashboard.php']) ? 'active':''; ?>" href="../dashboard.php">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>

        <div class="nav-group-label">Reports</div>
        <a class="nav-item <?php echo $current=='sales.php'?'active':''; ?>" href="sales.php">
            <i class="fa-solid fa-file-invoice-dollar"></i> Sales Report
        </a>
        <a class="nav-item <?php echo $current=='orders.php'?'active':''; ?>" href="orders.php">
            <i class="fa-solid fa-receipt"></i> All Orders
        </a>

        <div class="nav-group-label">Management</div>
        <a class="nav-item <?php echo $current=='products.php'?'active':''; ?>" href="products.php">
            <i class="fa-solid fa-bowl-food"></i> Products
        </a>
        <a class="nav-item <?php echo $current=='categories.php'?'active':''; ?>" href="categories.php">
            <i class="fa-solid fa-tags"></i> Categories
        </a>
        <a class="nav-item <?php echo $current=='users.php'?'active':''; ?>" href="users.php">
            <i class="fa-solid fa-users"></i> Staff / Users
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
        <a href="../pos.php" style="display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:8px;background:var(--primary-lt);border:1.5px solid #f9c4a6;border-radius:var(--radius-sm);color:var(--primary);font-size:12px;font-weight:800;font-family:'Nunito',sans-serif;cursor:pointer;text-decoration:none;transition:all .15s;margin-bottom:6px;">
            <i class="fa-solid fa-cash-register"></i> Go to POS
        </a>
        <a href="../logout.php" class="btn-logout-sb">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>