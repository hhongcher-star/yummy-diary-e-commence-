<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<div class="sidebar">
    <h2>🍪 Yummy Diary</h2>

    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
       📊 仪表盘
    </a>

    <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">
       🍪 商品管理
    </a>

    <a href="inventory.php" class="<?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : '' ?>">
       📦 库存管理
    </a>

    <a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>">
       🛒 订单管理
    </a>

    <a href="hot_products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'hot_products.php' ? 'active' : '' ?>">
       💡 热销管理
    </a>

    <a href="promotions.php" class="<?= basename($_SERVER['PHP_SELF']) === 'promotions.php' ? 'active' : '' ?>">
       🎁 优惠管理
    </a>

    <div class="logout">
        <a href="logout.php">退出登录</a>
    </div>
</div>