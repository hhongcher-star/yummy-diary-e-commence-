<?php
// åº“å­˜ç®¡ç†é¡µï¼šæŸ¥çœ‹å’Œç»´æŠ¤å•†å“ SKUã€å˜ä½“åº“å­˜ä¸Žä½Žåº“å­˜çŠ¶æ€ã€‚
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';

date_default_timezone_set("Asia/Kuala_Lumpur");

// ====================
// åˆ†ç±»åˆ†ç»„ï¼ˆä»Žæ•°æ®åº“è¯»å–ï¼‰
// ====================
$stmt = $pdo->query("SELECT
    g.group_key,
    g.label AS group_label,
    c.category_key,
    c.name AS category_name
  FROM category_groups g
  JOIN product_categories c ON c.group_id = g.id
  WHERE g.status = 1 AND c.status = 1
  ORDER BY g.sort_order ASC, c.sort_order ASC");

$categoryGroups = [];
$flatCategories = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $groupKey = $row['group_key'];

    if (!isset($categoryGroups[$groupKey])) {
        $categoryGroups[$groupKey] = [
            'label' => $row['group_label'],
            'children' => []
        ];
    }

    $categoryGroups[$groupKey]['children'][$row['category_key']] = $row['category_name'];
    $flatCategories[$row['category_key']] = $row['category_name'];
}

$selectedGroup = $_GET['group'] ?? '';
$selectedCat = $_GET['cat'] ?? '';

if ($selectedGroup !== '' && !isset($categoryGroups[$selectedGroup])) {
    $selectedGroup = '';
}

if ($selectedCat !== '' && !isset($flatCategories[$selectedCat])) {
    $selectedCat = '';
}

$cat = $selectedCat;

// Keep an audit trail for every manual inventory adjustment.
$pdo->exec("CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('product','variant') NOT NULL,
    target_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) DEFAULT NULL,
    stock_before INT NOT NULL,
    stock_after INT NOT NULL,
    quantity_change INT NOT NULL,
    admin_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory_target (target_type, target_id),
    INDEX idx_inventory_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ====================
// æ›´æ–°åº“å­˜ / é¢„è­¦å€¼
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $targetType = ($_POST['target_type'] ?? 'product') === 'variant' ? 'variant' : 'product';

    if (isset($_POST['stock'])) {
        $newStock = max(0, intval($_POST['stock']));
        $pdo->beginTransaction();
        try {
            if ($targetType === 'variant') {
                $stmt = $pdo->prepare("SELECT variant_name AS name, sku, stock FROM product_variants WHERE id=? FOR UPDATE");
            } else {
                $stmt = $pdo->prepare("SELECT name, sku, stock FROM products WHERE id=? FOR UPDATE");
            }
            $stmt->execute([$id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new RuntimeException('æ‰¾ä¸åˆ°åº“å­˜é¡¹ç›®');

            $oldStock = (int)$current['stock'];
            if ($newStock !== $oldStock) {
                if ($targetType === 'variant') {
                    $pdo->prepare("UPDATE product_variants SET stock=? WHERE id=?")->execute([$newStock, $id]);
                    $pdo->prepare("UPDATE products p JOIN product_variants v ON v.source_product_id=p.id SET p.stock=? WHERE v.id=?")
                        ->execute([$newStock, $id]);
                } else {
                    $pdo->prepare("UPDATE products SET stock=? WHERE id=?")->execute([$newStock, $id]);
                }
                $pdo->prepare("INSERT INTO inventory_movements
                    (target_type,target_id,product_name,sku,stock_before,stock_after,quantity_change,admin_id)
                    VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$targetType, $id, $current['name'], $current['sku'], $oldStock, $newStock,
                        $newStock - $oldStock, $_SESSION['admin_id'] ?? null]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if (isset($_POST['warning_level'])) {
        $stmt = $pdo->prepare("UPDATE products SET warning_level=? WHERE id=?");
        $stmt->execute([intval($_POST['warning_level']), $id]);
    }

    header("Location: inventory.php?group=" . urlencode($selectedGroup) . "&cat=" . urlencode($cat) . "&msg=" . urlencode("âœ… æ›´æ–°æˆåŠŸ"));
    exit;
}

// ====================
// æŸ¥è¯¢å•†å“
// ====================
if ($selectedCat !== '') {
    $stmt = $pdo->prepare("SELECT id, sku, name, image_url, stock, warning_level, category, product_type FROM products WHERE category=? AND parent_product_id IS NULL ORDER BY id DESC");
    $stmt->execute([$selectedCat]);

} elseif ($selectedGroup !== '') {
    $groupCats = array_keys($categoryGroups[$selectedGroup]['children']);
    $placeholders = implode(',', array_fill(0, count($groupCats), '?'));

    $stmt = $pdo->prepare("SELECT id, sku, name, image_url, stock, warning_level, category, product_type FROM products WHERE category IN ($placeholders) AND parent_product_id IS NULL ORDER BY category ASC, id DESC");
    $stmt->execute($groupCats);

} else {
    $stmt = $pdo->query("SELECT id, sku, name, image_url, stock, warning_level, category, product_type FROM products WHERE parent_product_id IS NULL ORDER BY category ASC, id DESC");
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$inventoryPage = max(1, (int)($_GET['page'] ?? 1));
$inventoryPerPage = 12;
$inventoryTotalPages = max(1, (int)ceil(count($products) / $inventoryPerPage));
$inventoryPage = min($inventoryPage, $inventoryTotalPages);
$displayProducts = array_slice($products, ($inventoryPage - 1) * $inventoryPerPage, $inventoryPerPage);
$groupedIds = array_column(array_filter($products, fn($product) => ($product['product_type'] ?? 'single') === 'grouped'), 'id');
$variantsByProduct = [];
if ($groupedIds) {
    $placeholders = implode(',', array_fill(0, count($groupedIds), '?'));
    $variantStmt = $pdo->prepare("SELECT v.*,COALESCE(p.warning_level,5) warning_level
      FROM product_variants v LEFT JOIN products p ON p.id=v.source_product_id
      WHERE v.product_id IN ($placeholders) ORDER BY v.product_id,v.sort_order,v.id");
    $variantStmt->execute($groupedIds);
    foreach ($variantStmt->fetchAll(PDO::FETCH_ASSOC) as $variant) {
        $variantsByProduct[(int)$variant['product_id']][] = $variant;
    }
}
$msg = $_GET['msg'] ?? '';
$reportMonth = (string)($_GET['report_month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reportMonth)) $reportMonth = date('Y-m');
$reportStart = $reportMonth . '-01 00:00:00';
$reportEnd = date('Y-m-d H:i:s', strtotime($reportStart . ' +1 month'));
$reportStmt = $pdo->prepare("SELECT oi.product_id,oi.sku,MAX(oi.product_name) product_name,
    MAX(COALESCE(NULLIF(v.image_url,''),NULLIF(p.image_url,''))) image_url,
    SUM(oi.quantity) order_quantity,COUNT(DISTINCT o.id) order_count
  FROM order_items oi JOIN orders o ON o.id=oi.order_id
  LEFT JOIN products p ON p.id=oi.product_id
  LEFT JOIN product_variants v ON v.product_id=oi.product_id AND v.sku=oi.sku
  WHERE o.created_at>=? AND o.created_at<? AND COALESCE(o.order_status,'')<>'draft'
    AND COALESCE(o.status,'')<>'cancelled'
  GROUP BY oi.product_id,oi.sku ORDER BY order_quantity DESC,product_name");
$reportStmt->execute([$reportStart, $reportEnd]);
$orderReport = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
$detailStmt = $pdo->prepare("SELECT o.id order_id,o.order_number,o.created_at,oi.sku,oi.product_name,oi.quantity,
    COALESCE(NULLIF(v.image_url,''),NULLIF(p.image_url,'')) image_url
  FROM orders o JOIN order_items oi ON oi.order_id=o.id
  LEFT JOIN products p ON p.id=oi.product_id
  LEFT JOIN product_variants v ON v.product_id=oi.product_id AND v.sku=oi.sku
  WHERE o.created_at>=? AND o.created_at<? AND COALESCE(o.order_status,'')<>'draft'
    AND COALESCE(o.status,'')<>'cancelled'
  ORDER BY o.created_at DESC,o.id DESC,oi.id ASC");
$detailStmt->execute([$reportStart, $reportEnd]);
$ordersDetail = [];
foreach ($detailStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
    $orderId = (int)$item['order_id'];
    if (!isset($ordersDetail[$orderId])) $ordersDetail[$orderId] = [
        'order_number' => $item['order_number'], 'created_at' => $item['created_at'], 'items' => []
    ];
    $ordersDetail[$orderId]['items'][] = $item;
}
$manualStmt = $pdo->prepare("SELECT sku,SUM(quantity_change) manual_change FROM inventory_movements
  WHERE created_at>=? AND created_at<? GROUP BY sku");
$manualStmt->execute([$reportStart, $reportEnd]);
$manualBySku = [];
foreach ($manualStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $manualBySku[(string)$row['sku']] = (int)$row['manual_change'];
$movements = [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>åº“å­˜ç®¡ç†</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<style>
<?php include __DIR__ . '/assets/css/inventory.css'; ?>
</style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>åº“å­˜ç®¡ç†</h2>
      <p>æŸ¥çœ‹å•†å“å›¾ç‰‡ã€åº“å­˜æ•°é‡ã€é¢„è­¦å€¼å’Œå¿«é€Ÿè°ƒæ•´åº“å­˜</p>
    </div>
    <button type="button" class="btn btn-edit report-open" id="openReport">åº“å­˜æ‰£å‡è®°å½•</button>
  </section>

  <?php
    $totalProducts = count($products);
    $lowStockCount = 0;
    $totalStock = 0;

    foreach ($products as $item) {
        if (($item['product_type'] ?? 'single') === 'grouped') {
            foreach ($variantsByProduct[(int)$item['id']] ?? [] as $variant) {
                $totalStock += (int)$variant['stock'];
                if ((int)$variant['stock'] < (int)$variant['warning_level']) $lowStockCount++;
            }
        } else {
            $totalStock += (int)$item['stock'];
            if ((int)$item['stock'] < (int)$item['warning_level']) $lowStockCount++;
        }
    }
  ?>

  <section class="inventory-summary">
    <div class="summary-card">
      <span>å•†å“æ•°é‡</span>
      <strong><?= $totalProducts ?></strong>
    </div>

    <div class="summary-card">
      <span>æ€»åº“å­˜</span>
      <strong><?= $totalStock ?></strong>
    </div>

    <div class="summary-card">
      <span>åº“å­˜ä¸è¶³</span>
      <strong><?= $lowStockCount ?></strong>
    </div>
  </section>

  <form class="category-filter" method="get">
    <select id="groupSelect" name="group">
      <option value="">å…¨éƒ¨å¤§åˆ†ç±»</option>
      <?php foreach ($categoryGroups as $groupKey => $group): ?>
        <option value="<?= htmlspecialchars($groupKey) ?>" <?= ($selectedGroup === $groupKey) ? 'selected' : '' ?>>
          <?= htmlspecialchars($group['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select id="catSelect" name="cat">
      <option value="">å…¨éƒ¨å°åˆ†ç±»</option>
      <?php foreach ($categoryGroups as $groupKey => $group): ?>
        <?php foreach ($group['children'] as $key => $label): ?>
          <option value="<?= htmlspecialchars($key) ?>"
                  data-group="<?= htmlspecialchars($groupKey) ?>"
                  <?= ($selectedCat === $key) ? 'selected' : '' ?>>
            <?= htmlspecialchars($group['label']) ?> / <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-edit">ç­›é€‰</button>
    <a href="inventory.php" class="btn btn-move">é‡ç½®</a>
  </form>

  <?php if($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="table-wrapper">
    <table class="inventory-table">
      <tr>
        <th>ID</th>
        <th>SKU</th>
        <th>å›¾ç‰‡</th>
        <th>å•†å“å</th>
        <th>åˆ†ç±»</th>
        <th>åº“å­˜</th>
        <th>é¢„è­¦å€¼</th>
        <th>æ“ä½œ</th>
      </tr>

      <?php foreach($displayProducts as $p): ?>
        <?php
          $isGrouped = ($p['product_type'] ?? 'single') === 'grouped';
          $isLow = !$isGrouped && (int)$p['stock'] < (int)$p['warning_level'];
          $displayStock = $isGrouped ? array_sum(array_column($variantsByProduct[(int)$p['id']] ?? [], 'stock')) : (int)$p['stock'];
        ?>

        <tr class="<?= $isGrouped ? 'grouped-parent-row' : '' ?>">
          <td data-label="ID"><?= $p['id'] ?></td>
          <td data-label="SKU"><?= htmlspecialchars($p['sku']) ?></td>

          <td data-label="å›¾ç‰‡">
            <?php if(!empty($p['image_url'])): ?>
              <img src="<?= htmlspecialchars(productImageUrl($p['image_url']), ENT_QUOTES) ?>"
                   onerror="this.remove();"
                   class="thumb">
            <?php else: ?>
              <span class="empty-image">No Image</span>
            <?php endif; ?>
          </td>

          <td class="inventory-name" data-label="å•†å“å">
            <?= htmlspecialchars($p['name']) ?>
            <br><small><strong><?= $isGrouped ? 'åˆ†ç±»å•†å“' : 'å•å•†å“' ?></strong></small>

            <?php if($isLow): ?>
              <br>
              <span class="low-badge">âš ï¸ åº“å­˜ä¸è¶³</span>
            <?php endif; ?>
          </td>

          <td data-label="åˆ†ç±»">
            <span class="inventory-category">
              <?= isset($p['category'], $flatCategories[$p['category']])
                  ? htmlspecialchars($flatCategories[$p['category']])
                  : 'æœªåˆ†ç±»' ?>
            </span>
          </td>

          <td data-label="åº“å­˜">
            <?php if(!$isGrouped): ?><form method="post" class="stock-form">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="number"
                     name="stock"
                     value="<?= $displayStock ?>"
                     <?= $isGrouped ? 'disabled' : '' ?>
                     class="<?= $isLow ? 'stock-low' : 'stock-ok' ?>">
              <button type="submit" class="btn btn-edit" <?= $isGrouped ? 'disabled' : '' ?>>ðŸ’¾ æ›´æ–°</button>
            </form><?php else: ?><span class="inventory-category">ç”±åˆ†ç±»é¡¹ç›®ç®¡ç†</span><?php endif; ?>
          </td>

          <td data-label="é¢„è­¦å€¼">
            <form method="post" class="stock-form">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="number"
                     name="warning_level"
                     value="<?= $p['warning_level'] ?>">
              <button type="submit" class="btn btn-move">âš™ï¸ è®¾å®š</button>
            </form>
          </td>

          <td data-label="æ“ä½œ">
            <?php if(!$isGrouped): ?><div class="quick-actions">
              <form method="post">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <input type="hidden" name="stock" value="<?= $p['stock'] + 1 ?>">
                <button type="submit" class="btn btn-move">âž• å¢žåŠ  1</button>
              </form>

              <form method="post">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <input type="hidden" name="stock" value="<?= max(0, $p['stock'] - 1) ?>">
                <button type="submit" class="btn btn-delete">âž– å‡å°‘ 1</button>
              </form>
            </div><?php else: ?><button type="button" class="btn btn-move group-toggle" data-group-id="<?= (int)$p['id'] ?>" aria-expanded="false">æŸ¥çœ‹åˆ†ç±»</button><?php endif; ?>
          </td>
        </tr>
        <?php if($isGrouped): foreach($variantsByProduct[(int)$p['id']] ?? [] as $variant): $variantLow=(int)$variant['stock'] < (int)$variant['warning_level']; ?>
          <tr class="variant-inventory-row is-collapsed" data-parent-id="<?= (int)$p['id'] ?>">
            <td data-label="ID">â†³ <?= (int)$variant['id'] ?></td>
            <td data-label="SKU"><?= htmlspecialchars($variant['sku']) ?></td>
            <td data-label="å›¾ç‰‡"><?php if(!empty($variant['image_url'])): ?><img src="<?= htmlspecialchars(productImageUrl($variant['image_url']), ENT_QUOTES) ?>" class="thumb" onerror="this.remove();"><?php endif; ?></td>
            <td class="inventory-name" data-label="å•†å“å"><?= htmlspecialchars($variant['variant_name']) ?><br><small>åˆ†ç±»é¡¹ç›®</small><?php if($variantLow): ?><br><span class="low-badge">åº“å­˜ä¸è¶³</span><?php endif; ?></td>
            <td data-label="åˆ†ç±»"><span class="inventory-category">å±žäºŽï¼š<?= htmlspecialchars($p['name']) ?></span></td>
            <td data-label="åº“å­˜"><form method="post" class="stock-form"><input type="hidden" name="id" value="<?= (int)$variant['id'] ?>"><input type="hidden" name="target_type" value="variant"><input type="number" name="stock" value="<?= (int)$variant['stock'] ?>" class="<?= $variantLow?'stock-low':'stock-ok' ?>"><button class="btn btn-edit">æ›´æ–°</button></form></td>
            <td data-label="é¢„è­¦å€¼"><?= (int)$variant['warning_level'] ?></td>
            <td data-label="æ“ä½œ"><div class="quick-actions"><form method="post"><input type="hidden" name="id" value="<?= (int)$variant['id'] ?>"><input type="hidden" name="target_type" value="variant"><input type="hidden" name="stock" value="<?= (int)$variant['stock']+1 ?>"><button class="btn btn-move">ï¼‹1</button></form><form method="post"><input type="hidden" name="id" value="<?= (int)$variant['id'] ?>"><input type="hidden" name="target_type" value="variant"><input type="hidden" name="stock" value="<?= max(0,(int)$variant['stock']-1) ?>"><button class="btn btn-delete">ï¼1</button></form></div></td>
          </tr>
        <?php endforeach; endif; ?>
      <?php endforeach; ?>
    </table>
  </div>

  <section class="movement-section">
    <h3>åº“å­˜å˜åŠ¨è®°å½•</h3>
    <p>æ˜¾ç¤ºæœ€è¿‘ 50 æ¬¡æ‰‹åŠ¨åº“å­˜è°ƒæ•´</p>
    <div class="table-wrapper">
      <table class="inventory-table movement-table">
        <tr><th>æ—¶é—´</th><th>å•†å“</th><th>SKU</th><th>å˜åŠ¨</th><th>åº“å­˜å˜åŒ–</th></tr>
        <?php if (!$movements): ?><tr><td colspan="5">æš‚æ— åº“å­˜å˜åŠ¨è®°å½•</td></tr><?php endif; ?>
        <?php foreach ($movements as $movement): $change=(int)$movement['quantity_change']; ?>
          <tr>
            <td data-label="æ—¶é—´"><?= htmlspecialchars($movement['created_at']) ?></td>
            <td data-label="å•†å“"><?= htmlspecialchars($movement['product_name']) ?><?= $movement['target_type']==='variant' ? 'ï¼ˆè§„æ ¼ï¼‰' : '' ?></td>
            <td data-label="SKU"><?= htmlspecialchars((string)$movement['sku']) ?></td>
            <td data-label="å˜åŠ¨"><span class="movement-change <?= $change >= 0 ? 'positive' : 'negative' ?>"><?= $change > 0 ? '+' : '' ?><?= $change ?></span></td>
            <td data-label="åº“å­˜å˜åŒ–"><?= (int)$movement['stock_before'] ?> â†’ <?= (int)$movement['stock_after'] ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </section>
  <?php if ($inventoryTotalPages > 1): ?><nav class="pagination">
    <?php if ($inventoryPage > 1): ?><a class="btn btn-move" href="?<?= http_build_query(['group'=>$selectedGroup,'cat'=>$selectedCat,'page'=>$inventoryPage-1]) ?>">ä¸Šä¸€é¡µ</a><?php endif; ?>
    <span>ç¬¬ <?= $inventoryPage ?> / <?= $inventoryTotalPages ?> é¡µ</span>
    <?php if ($inventoryPage < $inventoryTotalPages): ?><a class="btn btn-move" href="?<?= http_build_query(['group'=>$selectedGroup,'cat'=>$selectedCat,'page'=>$inventoryPage+1]) ?>">ä¸‹ä¸€é¡µ</a><?php endif; ?>
  </nav><?php endif; ?>
</main>

<div class="report-modal <?= isset($_GET['report_month']) ? 'show' : '' ?>" id="reportModal">
 <section class="report-panel" role="dialog" aria-modal="true"><div class="report-head"><div><h3>åº“å­˜æ‰£å‡ä¸Žè®¢å•æ¯”å¯¹</h3><p>ç»Ÿè®¡å·²ç¡®è®¤ä¸”æœªå–æ¶ˆçš„è®¢å•ï¼Œæ‰‹åŠ¨åº“å­˜è°ƒæ•´å¦å¤–åˆ—å‡ºã€‚</p></div><button type="button" class="btn btn-move" id="closeReport">å…³é—­</button></div>
 <form method="get" class="report-filter"><input type="hidden" name="group" value="<?= htmlspecialchars($selectedGroup) ?>"><input type="hidden" name="cat" value="<?= htmlspecialchars($selectedCat) ?>"><input type="month" name="report_month" value="<?= htmlspecialchars($reportMonth) ?>" max="<?= date('Y-m') ?>"><button class="btn btn-edit">æŸ¥çœ‹æœˆä»½</button></form>
 <div class="report-tabs"><button type="button" class="btn btn-move report-tab" data-report-view="summary" aria-selected="true">å•†å“æ±‡æ€»</button><button type="button" class="btn btn-move report-tab" data-report-view="orders" aria-selected="false">é€å•æ˜Žç»†</button></div>
 <div class="report-view" data-view="summary"><div class="table-wrapper"><table class="inventory-table"><tr><th>å•†å“</th><th>SKU</th><th>è®¢å•æ•°</th><th>è®¢å•åº”æ‰£</th><th>æ‰‹åŠ¨è°ƒæ•´</th><th>åˆè®¡å˜åŒ–</th></tr>
 <?php if (!$orderReport): ?><tr><td colspan="6">è¿™ä¸ªæœˆä»½æ²¡æœ‰å·²ç¡®è®¤è®¢å•è®°å½•</td></tr><?php endif; ?>
 <?php foreach ($orderReport as $row): $manual=$manualBySku[(string)$row['sku']] ?? 0; $ordered=(int)$row['order_quantity']; $net=$manual-$ordered; ?><tr><td data-label="å•†å“"><div class="report-product"><?php if(!empty($row['image_url'])): ?><img src="<?= htmlspecialchars(productImageUrl($row['image_url']),ENT_QUOTES) ?>" alt="" onerror="this.style.display='none'"> <?php else: ?><span class="report-image-empty"></span><?php endif; ?><strong><?= htmlspecialchars($row['product_name']) ?></strong></div></td><td data-label="SKU"><?= htmlspecialchars($row['sku']) ?></td><td data-label="è®¢å•æ•°"><?= (int)$row['order_count'] ?></td><td data-label="è®¢å•åº”æ‰£" class="movement-change negative">ï¼<?= $ordered ?></td><td data-label="æ‰‹åŠ¨è°ƒæ•´" class="movement-change <?= $manual>=0?'positive':'negative' ?>"><?= $manual>0?'+':'' ?><?= $manual ?></td><td data-label="åˆè®¡å˜åŒ–" class="movement-change <?= $net>=0?'positive':'negative' ?>"><?= $net>0?'+':'' ?><?= $net ?></td></tr><?php endforeach; ?>
 </table></div></div>
 <div class="report-view" data-view="orders" hidden>
  <?php if (!$ordersDetail): ?><div class="order-detail-card"><div class="order-detail-head">è¿™ä¸ªæœˆä»½æ²¡æœ‰å·²ç¡®è®¤è®¢å•</div></div><?php endif; ?>
  <?php foreach ($ordersDetail as $order): ?>
   <article class="order-detail-card"><header class="order-detail-head"><button type="button" class="order-detail-toggle" aria-expanded="false"><span><strong>è®¢å• <?= htmlspecialchars($order['order_number']) ?></strong><small><?= count($order['items']) ?> é¡¹å•†å“</small></span><span><?= htmlspecialchars(date('Y-m-d H:i',strtotime($order['created_at']))) ?></span></button></header>
   <div class="order-detail-items" hidden><?php foreach ($order['items'] as $item): ?><div class="order-detail-item"><div class="report-product"><?php if(!empty($item['image_url'])): ?><img src="<?= htmlspecialchars(productImageUrl($item['image_url']),ENT_QUOTES) ?>" alt="" onerror="this.style.display='none'"><?php else: ?><span class="report-image-empty"></span><?php endif; ?><strong><?= htmlspecialchars($item['product_name']) ?></strong></div><span>SKUï¼š<?= htmlspecialchars($item['sku']) ?></span><span class="deduct-qty">æ‰£å‡ ï¼<?= (int)$item['quantity'] ?></span></div><?php endforeach; ?></div>
   </article>
  <?php endforeach; ?>
 </div></section>
</div>

<script>
<?php include __DIR__ . '/assets/js/inventory.js.php'; ?>
</script>

</body>
</html>

