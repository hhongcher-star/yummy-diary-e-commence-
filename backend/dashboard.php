<?php
// åŽå°ä»ªè¡¨ç›˜ï¼šæ±‡æ€»å±•ç¤ºè®¢å•ã€é”€å”®ã€è®¿å®¢æˆ–ç³»ç»Ÿè¿è¥æ¦‚è§ˆã€‚
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

$username = $_SESSION['admin_username'];
date_default_timezone_set("Asia/Kuala_Lumpur");

$username = $_SESSION['admin_username'];

$stmt = $pdo->query("
    SELECT p.id, p.name, NULL parent_name, NULL variant_name, p.stock, p.warning_level, p.category, p.image_url
    FROM products p
    WHERE p.parent_product_id IS NULL AND p.product_type='single' AND p.stock < p.warning_level
    UNION ALL
    SELECT COALESCE(v.source_product_id, -v.id), v.variant_name, parent.name, v.variant_name,
           v.stock, COALESCE(source.warning_level, parent.warning_level, 5), parent.category,
           COALESCE(v.image_url, source.image_url, parent.image_url)
    FROM product_variants v
    JOIN products parent ON parent.id=v.product_id
    LEFT JOIN products source ON source.id=v.source_product_id
    WHERE v.stock < COALESCE(source.warning_level, parent.warning_level, 5)
    ORDER BY category, id DESC LIMIT 5
");
$lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Yummy Diary åŽå°ç®¡ç†</title>
<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
<?php include __DIR__ . '/assets/css/dashboard.css'; ?>
</style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
    <section class="page-header">
        <div class="page-title">
            <h2>ä»ªè¡¨ç›˜</h2>
            <p>æ¬¢è¿Žå›žæ¥ï¼Œ<?= htmlspecialchars($username) ?>ï¼ŒæŸ¥çœ‹é”€å”®ã€è®¢å•ã€è®¿å®¢å’Œåº“å­˜æé†’</p>
        </div>
    </section>

    <div class="dashboard-stack">
    <section class="admin-card welcome-card">
        <h2 style="margin-top:0;">æ¬¢è¿Žå›žæ¥ï¼Œ<?= htmlspecialchars($username) ?> ðŸŽ‰</h2>
        <p style="margin-bottom:0;">è¿™æ˜¯ Yummy Diary çš„åŽå°é¦–é¡µã€‚</p>
    </section>

    <div class="dashboard-grid">
    <?php if ($lowStock): ?>
    <section class="admin-card warning-card">
        <div class="card-head"><h2>âš ï¸ åº“å­˜ä¸è¶³æé†’</h2><a href="inventory.php?cat=lowstock">æŸ¥çœ‹å…¨éƒ¨ â†’</a></div>
        <ul class="warning-list">
          <?php foreach($lowStock as $item): ?>
            <li><img class="product-thumb" src="<?= htmlspecialchars(productImageUrl($item['image_url'] ?? ''), ENT_QUOTES) ?>" alt=""><span><?php if(!empty($item['parent_name'])): ?><strong><?= htmlspecialchars($item['parent_name']) ?></strong><small class="product-parent">åˆ†ç±»æ¬¾å¼ï¼š<?= htmlspecialchars($item['variant_name']) ?></small><?php else: ?><?= htmlspecialchars($item['name']) ?><?php endif; ?></span><span class="stock-badge">åº“å­˜ <?= $item['stock'] ?> / é¢„è­¦ <?= $item['warning_level'] ?></span></li>
          <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- âœ… é”€å”®é¢åŒºåŸŸ -->
    <section class="admin-card sales-card">
        <div class="card-head"><h2>ðŸ“Š é”€å”®é¢æ¦‚è§ˆ</h2></div>

        <div class="filter-row">
            <select id="periodFilter">
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month" selected>This Month</option>
                <option value="year">This Year</option>
                <option value="custom_month">Custom Month</option>
            </select>

            <select id="monthFilter" style="display:none;">
                <option value="1">January</option>
                <option value="2">February</option>
                <option value="3">March</option>
                <option value="4">April</option>
                <option value="5">May</option>
                <option value="6">June</option>
                <option value="7">July</option>
                <option value="8">August</option>
                <option value="9">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
            </select>

            <select id="yearFilter">
                <option value="2026" selected>2026</option>
                <option value="2025">2025</option>
            </select>
        </div>

        <div class="stats-container">
            <div class="stat-box">
                <h3 id="salesTitle">æœ¬æœˆé”€å”®é¢</h3>
                <p id="filteredSales">åŠ è½½ä¸­...</p>
            </div>
        </div>
        <canvas id="salesChart" height="100"></canvas>
        <div class="mini-stats">
          <div class="mini-stat"><h3>ðŸ›ï¸ ä»Šæ—¥è®¢å•</h3><strong id="todayOrders">åŠ è½½ä¸­...</strong><small>å®žæ—¶è®¢å•æ•°é‡</small></div>
          <div class="mini-stat purple"><h3>ðŸ‘¤ ç´¯è®¡è®¿å®¢</h3><strong id="totalVisitors">åŠ è½½ä¸­...</strong><small>ç½‘ç«™è®¿å®¢æ€»æ•°</small><canvas id="visitorsChart" height="42"></canvas></div>
        </div>
    </section>
    </div>

    <!-- âœ… å•†å“é”€å”®åˆ†æž -->
    <section class="admin-card">
        <div class="card-head"><h2>ðŸ† å•†å“é”€å”®åˆ†æž</h2></div>

        <div class="filter-row">
            <select id="productPeriodFilter">
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month" selected>This Month</option>
                <option value="year">This Year</option>
                <option value="custom_month">Custom Month</option>
            </select>

            <select id="productMonthFilter" style="display:none;">
                <option value="1">January</option>
                <option value="2">February</option>
                <option value="3">March</option>
                <option value="4">April</option>
                <option value="5">May</option>
                <option value="6">June</option>
                <option value="7">July</option>
                <option value="8">August</option>
                <option value="9">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
            </select>

            <select id="productYearFilter">
                <option value="2026" selected>2026</option>
                <option value="2025">2025</option>
            </select>

            <select id="productCategoryFilter">
                <option value="all">å…¨éƒ¨åˆ†ç±»</option>
            </select>

            <select id="productSortFilter">
                <option value="qty" selected>æŒ‰å”®å‡ºæ•°é‡æœ€é«˜</option>
                <option value="sales">æŒ‰é”€å”®é¢æœ€é«˜</option>
                <option value="orders">æŒ‰è®¢å•æ¬¡æ•°æœ€é«˜</option>
                <option value="avg_price">æŒ‰å¹³å‡å•ä»·æœ€é«˜</option>
            </select>

            <select id="productLimitFilter">
                <option value="5" selected>Top 5</option>
                <option value="10">Top 10</option>
                <option value="20">Top 20</option>
            </select>
        </div>

        <div id="productAnalysisTable">åŠ è½½ä¸­...</div>
    </section>

    </div>
</main>

<script>
<?php include __DIR__ . '/assets/js/dashboard.js.php'; ?>
</script>

</body>
</html>

