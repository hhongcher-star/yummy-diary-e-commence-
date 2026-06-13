<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css?v=20260612-4">
<style>
@media (min-width:769px){
  .mobile-bottom-nav,.mobile-more-menu{display:none!important;}
}
</style>

<div class="sidebar">
    <h2>🍪 Yummy Diary</h2>

    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">📊 仪表盘</a>
    <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">🍪 商品管理</a>
    <a href="product_sort.php" class="<?= basename($_SERVER['PHP_SELF']) === 'product_sort.php' ? 'active' : '' ?>">↕ 商品排序</a>
    <a href="inventory.php" class="<?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : '' ?>">📦 库存管理</a>
    <a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>">🛒 订单管理</a>
    <a href="promotions.php" class="<?= basename($_SERVER['PHP_SELF']) === 'promotions.php' ? 'active' : '' ?>">🎁 优惠管理</a>

    <div class="logout">
        <a href="logout.php">退出登录</a>
    </div>
</div>

<?php $currentAdminPage = basename($_SERVER['PHP_SELF']); ?>
<div class="mobile-bottom-nav" aria-label="手机端后台导航">
    <a href="dashboard.php" class="<?= $currentAdminPage === 'dashboard.php' ? 'active' : '' ?>">
        <b>⌂</b><span>首页</span>
    </a>
    <a href="orders.php" class="<?= $currentAdminPage === 'orders.php' ? 'active' : '' ?>">
        <b>🛍</b><span>订单</span>
    </a>
    <a href="products.php" class="center-add <?= in_array($currentAdminPage, ['products.php', 'edit_product.php'], true) ? 'active' : '' ?>">
        <b>＋</b><span>商品管理</span>
    </a>
    <a href="inventory.php" class="<?= $currentAdminPage === 'inventory.php' ? 'active' : '' ?>">
        <b>▣</b><span>库存</span>
    </a>
    <button type="button" id="mobileMoreBtn" class="<?= in_array($currentAdminPage, ['promotions.php', 'product_sort.php'], true) ? 'active' : '' ?>" aria-expanded="false">
        <b>▦</b><span>更多</span>
    </button>
</div>

<div class="mobile-more-menu" id="mobileMoreMenu">
    <a href="promotions.php"><b>🎁</b><span>优惠管理</span></a>
    <a href="product_sort.php"><b>↕</b><span>商品排序</span></a>
    <a href="logout.php"><b>↪</b><span>退出登录</span></a>
</div>

<script>
const mobileMoreButton = document.getElementById("mobileMoreBtn");
const mobileMoreMenu = document.getElementById("mobileMoreMenu");

mobileMoreButton?.addEventListener("click", function () {
    const isOpen = mobileMoreMenu.classList.toggle("show");
    mobileMoreButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
});

document.addEventListener("click", function (event) {
    if (!mobileMoreMenu?.classList.contains("show")) return;
    if (mobileMoreMenu.contains(event.target) || mobileMoreButton.contains(event.target)) return;
    mobileMoreMenu.classList.remove("show");
    mobileMoreButton.setAttribute("aria-expanded", "false");
});
</script>
