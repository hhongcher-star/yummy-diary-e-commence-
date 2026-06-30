<?php // åŽå°å…±äº«å¯¼èˆªï¼šæä¾›æ¡Œé¢ä¾§è¾¹æ å’Œç§»åŠ¨ç«¯åº•éƒ¨å¯¼èˆªï¼Œå¹¶æ ¹æ®å½“å‰é¡µé¢æ ‡è®° activeã€‚ ?>
<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css?v=20260612-4">
<style>
<?php include __DIR__ . '/../assets/css/includes-sidebar.css'; ?>
</style>

<div class="sidebar">
    <h2>ðŸª Yummy Diary</h2>

    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">ðŸ“Š ä»ªè¡¨ç›˜</a>
    <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">ðŸª å•†å“ç®¡ç†</a>
    <a href="product_sort.php" class="<?= basename($_SERVER['PHP_SELF']) === 'product_sort.php' ? 'active' : '' ?>">â†• å•†å“æŽ’åº</a>
    <a href="inventory.php" class="<?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : '' ?>">ðŸ“¦ åº“å­˜ç®¡ç†</a>
    <a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>">ðŸ›’ è®¢å•ç®¡ç†</a>
    <a href="promotions.php" class="<?= basename($_SERVER['PHP_SELF']) === 'promotions.php' ? 'active' : '' ?>">ðŸŽ ä¼˜æƒ ç®¡ç†</a>

    <div class="logout">
        <a href="logout.php">é€€å‡ºç™»å½•</a>
    </div>
</div>

<?php $currentAdminPage = basename($_SERVER['PHP_SELF']); ?>
<div class="mobile-bottom-nav" aria-label="æ‰‹æœºç«¯åŽå°å¯¼èˆª">
    <a href="dashboard.php" class="<?= $currentAdminPage === 'dashboard.php' ? 'active' : '' ?>">
        <b>âŒ‚</b><span>é¦–é¡µ</span>
    </a>
    <a href="orders.php" class="<?= $currentAdminPage === 'orders.php' ? 'active' : '' ?>">
        <b>ðŸ›</b><span>è®¢å•</span>
    </a>
    <a href="products.php" class="center-add <?= in_array($currentAdminPage, ['products.php', 'edit_product.php'], true) ? 'active' : '' ?>">
        <b>ï¼‹</b><span>å•†å“ç®¡ç†</span>
    </a>
    <a href="inventory.php" class="<?= $currentAdminPage === 'inventory.php' ? 'active' : '' ?>">
        <b>â–£</b><span>åº“å­˜</span>
    </a>
    <button type="button" id="mobileMoreBtn" class="<?= in_array($currentAdminPage, ['promotions.php', 'product_sort.php'], true) ? 'active' : '' ?>" aria-expanded="false">
        <b>â–¦</b><span>æ›´å¤š</span>
    </button>
</div>

<div class="mobile-more-menu" id="mobileMoreMenu">
    <a href="promotions.php"><b>ðŸŽ</b><span>ä¼˜æƒ ç®¡ç†</span></a>
    <a href="product_sort.php"><b>â†•</b><span>å•†å“æŽ’åº</span></a>
    <a href="logout.php"><b>â†ª</b><span>é€€å‡ºç™»å½•</span></a>
</div>

<script>
<?php include __DIR__ . '/../assets/js/includes-sidebar.js.php'; ?>
</script>

