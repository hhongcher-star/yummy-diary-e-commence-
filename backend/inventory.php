<?php
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';

date_default_timezone_set("Asia/Kuala_Lumpur");

// ====================
// 分类分组（从数据库读取）
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
// 更新库存 / 预警值
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
            if (!$current) throw new RuntimeException('找不到库存项目');

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

    header("Location: inventory.php?group=" . urlencode($selectedGroup) . "&cat=" . urlencode($cat) . "&msg=" . urlencode("✅ 更新成功"));
    exit;
}

// ====================
// 查询商品
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
<title>库存管理</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<style>
  .inventory-summary{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:14px;
    margin-bottom:22px;
  }

  .summary-card{
    background:#fffaf4;
    border:1px solid var(--line);
    border-radius:22px;
    padding:18px;
    box-shadow:0 10px 28px rgba(120,90,60,.08);
  }

  .summary-card span{
    display:block;
    color:var(--muted);
    font-size:13px;
    font-weight:700;
    margin-bottom:6px;
  }

  .summary-card strong{
    font-size:26px;
    color:var(--text);
  }

  .inventory-table td{
    vertical-align:middle;
  }

  .inventory-name{
    text-align:left;
    font-weight:700;
    line-height:1.5;
    min-width:260px;
  }

  .inventory-category{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:7px 12px;
    border-radius:999px;
    background:#fffaf4;
    border:1px solid var(--line);
    color:var(--muted);
    font-weight:700;
    font-size:13px;
    white-space:nowrap;
  }

  .stock-form{
    display:flex;
    justify-content:center;
    gap:8px;
    align-items:center;
  }

  .stock-form input{
    width:82px;
    text-align:center;
    padding:10px 8px;
  }

  .quick-actions{
    display:flex;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
  }

  .quick-actions form{
    margin:0;
  }

  .stock-low{
    color:#d97706;
    font-weight:800;
    background:#fff7ed;
    border-color:#fed7aa;
  }

  .stock-ok{
    color:#3b2a20;
    font-weight:800;
  }

  .low-badge{
    display:inline-flex;
    margin-top:6px;
    padding:4px 8px;
    border-radius:999px;
    background:#fff7ed;
    color:#d97706;
    font-size:12px;
    font-weight:800;
  }

  .empty-image{
    width:62px;
    height:62px;
    border-radius:16px;
    background:#fff7f0;
    border:1px dashed var(--line);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:var(--muted);
    font-size:12px;
  }

  .variant-inventory-row{
    background:#fffaf4;
  }

  .grouped-parent-row{
    background:#fff;
  }

  .grouped-parent-row td{
    border-bottom:0;
  }

  .variant-inventory-row td{
    border-top:1px dashed #ead8c8;
  }

  .page-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;}
  .report-open{white-space:nowrap;}
  .pagination{display:flex;justify-content:center;align-items:center;gap:8px;margin:20px 0;}
  .pagination span{color:var(--muted);font-size:13px;}
  .report-modal{display:none;position:fixed;inset:0;z-index:99999;background:rgba(35,25,20,.48);padding:28px;}
  .report-modal.show{display:flex;align-items:center;justify-content:center;}
  .report-panel{width:min(1050px,100%);max-height:88vh;overflow:auto;background:#fff;border-radius:24px;padding:22px;box-shadow:0 24px 70px rgba(0,0,0,.22);}
  .report-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;}
  .report-head h3{margin:0 0 4px;font-size:22px}.report-head p{margin:0;color:var(--muted);font-size:13px;}
  .report-filter{display:flex;gap:8px;align-items:center;margin-bottom:16px;}
  .report-filter input{padding:10px 12px;border:1px solid var(--line);border-radius:12px;}
  .group-toggle{cursor:pointer;}
  .group-toggle[aria-expanded="true"]::after{content:' ▲';}
  .group-toggle[aria-expanded="false"]::after{content:' ▼';}
  .variant-inventory-row.is-collapsed{display:none;}
  .report-product{display:flex;align-items:center;gap:10px;min-width:230px;text-align:left;}
  .report-product img,.report-image-empty{width:48px;height:48px;flex:0 0 48px;border-radius:10px;object-fit:contain;background:#fffaf4;border:1px solid var(--line);}
  .report-tabs{display:flex;gap:8px;margin:0 0 16px;}
  .report-tab[aria-selected="true"]{background:#ead2b5;color:var(--text);}
  .report-view[hidden]{display:none!important;}
  .order-detail-card{border:1px solid var(--line);border-radius:18px;margin-bottom:12px;overflow:hidden;}
  .order-detail-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 16px;background:#fffaf4;}
  .order-detail-head span{color:var(--muted);font-size:13px;}
  .order-detail-toggle{display:flex;justify-content:space-between;align-items:center;gap:12px;width:100%;padding:0;border:0;background:transparent;color:var(--text);font:inherit;text-align:left;cursor:pointer;}
  .order-detail-toggle::after{content:'▼';font-size:12px;color:var(--muted);}
  .order-detail-toggle[aria-expanded="true"]::after{content:'▲';}
  .order-detail-items[hidden]{display:none;}
  .order-detail-item{display:grid;grid-template-columns:minmax(260px,1fr) 130px 110px;align-items:center;gap:12px;padding:12px 16px;border-top:1px solid var(--line);}
  .deduct-qty{font-weight:800;color:#dc2626;text-align:center;}
  .movement-change{font-weight:800;white-space:nowrap;}
  .movement-change.positive{color:#15803d}.movement-change.negative{color:#dc2626}
  .movement-section{display:none;}

  @media(max-width:768px){
    main{padding-left:12px!important;padding-right:12px!important;}
    .page-header{margin-bottom:14px;}
    .inventory-summary{grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;}
    .summary-card{padding:12px 10px;border-radius:16px;}
    .summary-card span{font-size:11px}.summary-card strong{font-size:20px;}
    .category-filter{display:grid!important;grid-template-columns:1fr 1fr;gap:8px;padding:12px!important;}
    .category-filter select,.category-filter .btn{width:100%;min-width:0;margin:0;}
    .table-wrapper{
      overflow:visible;
    }

    .inventory-table,
    .inventory-table tbody,
    .inventory-table tr,
    .inventory-table td{
      display:block;
      width:100%;
    }

    .inventory-table tr{
      margin-bottom:14px;
      padding:12px;
      border:1px solid var(--line);
      border-radius:20px;
      background:#fff;
    }

    .inventory-table th{
      display:none;
    }

    .inventory-table td{
      border:0;
      padding:8px 0;
      text-align:left;
    }

    .stock-form,
    .quick-actions{
      justify-content:flex-start;
      flex-wrap:wrap;
    }

    .stock-form input{
      width:100px;
    }
    .inventory-table td[data-label]{display:grid;grid-template-columns:74px minmax(0,1fr);align-items:center;gap:8px;}
    .inventory-table td[data-label]::before{content:attr(data-label);color:var(--muted);font-size:12px;font-weight:700;}
    .inventory-table td.inventory-name{min-width:0;font-size:14px;}
    .inventory-table .thumb,.empty-image{width:52px;height:52px;}
    .inventory-category{white-space:normal;text-align:left;overflow-wrap:anywhere;}
    .stock-form{display:grid;grid-template-columns:minmax(0,1fr) auto;width:100%;}
    .stock-form input{width:100%!important;min-width:0;}
    .quick-actions{display:grid;grid-template-columns:1fr 1fr;width:100%;}
    .quick-actions .btn{width:100%;}
    .page-header{align-items:center}.page-title p{display:none}.report-open{padding:10px!important;font-size:13px;}
    .report-modal{padding:0;align-items:flex-end!important}.report-panel{max-height:90vh;border-radius:22px 22px 0 0;padding:14px;}
    .report-filter{display:grid;grid-template-columns:1fr auto}.report-filter input{width:100%;}
    .report-panel .table-wrapper{overflow:visible;}
    .report-panel .inventory-table tr{padding:12px;margin-bottom:10px;}
    .report-panel .inventory-table td[data-label]{grid-template-columns:82px minmax(0,1fr);padding:6px 0;}
    .report-panel .inventory-table td:first-child{display:block;}
    .report-product{min-width:0;font-size:14px;padding-bottom:8px;border-bottom:1px solid var(--line);}
    .report-product img,.report-image-empty{width:58px;height:58px;flex-basis:58px;}
    .report-panel{padding-bottom:100px;}
    .report-tabs{display:grid;grid-template-columns:1fr 1fr;position:sticky;top:-14px;background:#fff;padding:8px 0;z-index:2;}
    .order-detail-head{display:block}.order-detail-toggle span{display:block;margin-top:3px;}
    .order-detail-item{grid-template-columns:1fr auto;padding:10px;}
    .order-detail-item>span:nth-child(2){grid-column:1/2;color:var(--muted);font-size:12px;padding-left:68px;}
    .order-detail-item .deduct-qty{grid-column:2;grid-row:1/3;}
  }
</style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>库存管理</h2>
      <p>查看商品图片、库存数量、预警值和快速调整库存</p>
    </div>
    <button type="button" class="btn btn-edit report-open" id="openReport">库存扣减记录</button>
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
      <span>商品数量</span>
      <strong><?= $totalProducts ?></strong>
    </div>

    <div class="summary-card">
      <span>总库存</span>
      <strong><?= $totalStock ?></strong>
    </div>

    <div class="summary-card">
      <span>库存不足</span>
      <strong><?= $lowStockCount ?></strong>
    </div>
  </section>

  <form class="category-filter" method="get">
    <select id="groupSelect" name="group">
      <option value="">全部大分类</option>
      <?php foreach ($categoryGroups as $groupKey => $group): ?>
        <option value="<?= htmlspecialchars($groupKey) ?>" <?= ($selectedGroup === $groupKey) ? 'selected' : '' ?>>
          <?= htmlspecialchars($group['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select id="catSelect" name="cat">
      <option value="">全部小分类</option>
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

    <button type="submit" class="btn btn-edit">筛选</button>
    <a href="inventory.php" class="btn btn-move">重置</a>
  </form>

  <?php if($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="table-wrapper">
    <table class="inventory-table">
      <tr>
        <th>ID</th>
        <th>SKU</th>
        <th>图片</th>
        <th>商品名</th>
        <th>分类</th>
        <th>库存</th>
        <th>预警值</th>
        <th>操作</th>
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

          <td data-label="图片">
            <?php if(!empty($p['image_url'])): ?>
              <img src="<?= htmlspecialchars(productImageUrl($p['image_url']), ENT_QUOTES) ?>"
                   onerror="this.remove();"
                   class="thumb">
            <?php else: ?>
              <span class="empty-image">No Image</span>
            <?php endif; ?>
          </td>

          <td class="inventory-name" data-label="商品名">
            <?= htmlspecialchars($p['name']) ?>
            <br><small><strong><?= $isGrouped ? '分类商品' : '单商品' ?></strong></small>

            <?php if($isLow): ?>
              <br>
              <span class="low-badge">⚠️ 库存不足</span>
            <?php endif; ?>
          </td>

          <td data-label="分类">
            <span class="inventory-category">
              <?= isset($p['category'], $flatCategories[$p['category']])
                  ? htmlspecialchars($flatCategories[$p['category']])
                  : '未分类' ?>
            </span>
          </td>

          <td data-label="库存">
            <?php if(!$isGrouped): ?><form method="post" class="stock-form">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="number"
                     name="stock"
                     value="<?= $displayStock ?>"
                     <?= $isGrouped ? 'disabled' : '' ?>
                     class="<?= $isLow ? 'stock-low' : 'stock-ok' ?>">
              <button type="submit" class="btn btn-edit" <?= $isGrouped ? 'disabled' : '' ?>>💾 更新</button>
            </form><?php else: ?><span class="inventory-category">由分类项目管理</span><?php endif; ?>
          </td>

          <td data-label="预警值">
            <form method="post" class="stock-form">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="number"
                     name="warning_level"
                     value="<?= $p['warning_level'] ?>">
              <button type="submit" class="btn btn-move">⚙️ 设定</button>
            </form>
          </td>

          <td data-label="操作">
            <?php if(!$isGrouped): ?><div class="quick-actions">
              <form method="post">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <input type="hidden" name="stock" value="<?= $p['stock'] + 1 ?>">
                <button type="submit" class="btn btn-move">➕ 增加 1</button>
              </form>

              <form method="post">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <input type="hidden" name="stock" value="<?= max(0, $p['stock'] - 1) ?>">
                <button type="submit" class="btn btn-delete">➖ 减少 1</button>
              </form>
            </div><?php else: ?><button type="button" class="btn btn-move group-toggle" data-group-id="<?= (int)$p['id'] ?>" aria-expanded="false">查看分类</button><?php endif; ?>
          </td>
        </tr>
        <?php if($isGrouped): foreach($variantsByProduct[(int)$p['id']] ?? [] as $variant): $variantLow=(int)$variant['stock'] < (int)$variant['warning_level']; ?>
          <tr class="variant-inventory-row is-collapsed" data-parent-id="<?= (int)$p['id'] ?>">
            <td data-label="ID">↳ <?= (int)$variant['id'] ?></td>
            <td data-label="SKU"><?= htmlspecialchars($variant['sku']) ?></td>
            <td data-label="图片"><?php if(!empty($variant['image_url'])): ?><img src="<?= htmlspecialchars(productImageUrl($variant['image_url']), ENT_QUOTES) ?>" class="thumb" onerror="this.remove();"><?php endif; ?></td>
            <td class="inventory-name" data-label="商品名"><?= htmlspecialchars($variant['variant_name']) ?><br><small>分类项目</small><?php if($variantLow): ?><br><span class="low-badge">库存不足</span><?php endif; ?></td>
            <td data-label="分类"><span class="inventory-category">属于：<?= htmlspecialchars($p['name']) ?></span></td>
            <td data-label="库存"><form method="post" class="stock-form"><input type="hidden" name="id" value="<?= (int)$variant['id'] ?>"><input type="hidden" name="target_type" value="variant"><input type="number" name="stock" value="<?= (int)$variant['stock'] ?>" class="<?= $variantLow?'stock-low':'stock-ok' ?>"><button class="btn btn-edit">更新</button></form></td>
            <td data-label="预警值"><?= (int)$variant['warning_level'] ?></td>
            <td data-label="操作"><div class="quick-actions"><form method="post"><input type="hidden" name="id" value="<?= (int)$variant['id'] ?>"><input type="hidden" name="target_type" value="variant"><input type="hidden" name="stock" value="<?= (int)$variant['stock']+1 ?>"><button class="btn btn-move">＋1</button></form><form method="post"><input type="hidden" name="id" value="<?= (int)$variant['id'] ?>"><input type="hidden" name="target_type" value="variant"><input type="hidden" name="stock" value="<?= max(0,(int)$variant['stock']-1) ?>"><button class="btn btn-delete">－1</button></form></div></td>
          </tr>
        <?php endforeach; endif; ?>
      <?php endforeach; ?>
    </table>
  </div>

  <section class="movement-section">
    <h3>库存变动记录</h3>
    <p>显示最近 50 次手动库存调整</p>
    <div class="table-wrapper">
      <table class="inventory-table movement-table">
        <tr><th>时间</th><th>商品</th><th>SKU</th><th>变动</th><th>库存变化</th></tr>
        <?php if (!$movements): ?><tr><td colspan="5">暂无库存变动记录</td></tr><?php endif; ?>
        <?php foreach ($movements as $movement): $change=(int)$movement['quantity_change']; ?>
          <tr>
            <td data-label="时间"><?= htmlspecialchars($movement['created_at']) ?></td>
            <td data-label="商品"><?= htmlspecialchars($movement['product_name']) ?><?= $movement['target_type']==='variant' ? '（规格）' : '' ?></td>
            <td data-label="SKU"><?= htmlspecialchars((string)$movement['sku']) ?></td>
            <td data-label="变动"><span class="movement-change <?= $change >= 0 ? 'positive' : 'negative' ?>"><?= $change > 0 ? '+' : '' ?><?= $change ?></span></td>
            <td data-label="库存变化"><?= (int)$movement['stock_before'] ?> → <?= (int)$movement['stock_after'] ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </section>
  <?php if ($inventoryTotalPages > 1): ?><nav class="pagination">
    <?php if ($inventoryPage > 1): ?><a class="btn btn-move" href="?<?= http_build_query(['group'=>$selectedGroup,'cat'=>$selectedCat,'page'=>$inventoryPage-1]) ?>">上一页</a><?php endif; ?>
    <span>第 <?= $inventoryPage ?> / <?= $inventoryTotalPages ?> 页</span>
    <?php if ($inventoryPage < $inventoryTotalPages): ?><a class="btn btn-move" href="?<?= http_build_query(['group'=>$selectedGroup,'cat'=>$selectedCat,'page'=>$inventoryPage+1]) ?>">下一页</a><?php endif; ?>
  </nav><?php endif; ?>
</main>

<div class="report-modal <?= isset($_GET['report_month']) ? 'show' : '' ?>" id="reportModal">
 <section class="report-panel" role="dialog" aria-modal="true"><div class="report-head"><div><h3>库存扣减与订单比对</h3><p>统计已确认且未取消的订单，手动库存调整另外列出。</p></div><button type="button" class="btn btn-move" id="closeReport">关闭</button></div>
 <form method="get" class="report-filter"><input type="hidden" name="group" value="<?= htmlspecialchars($selectedGroup) ?>"><input type="hidden" name="cat" value="<?= htmlspecialchars($selectedCat) ?>"><input type="month" name="report_month" value="<?= htmlspecialchars($reportMonth) ?>" max="<?= date('Y-m') ?>"><button class="btn btn-edit">查看月份</button></form>
 <div class="report-tabs"><button type="button" class="btn btn-move report-tab" data-report-view="summary" aria-selected="true">商品汇总</button><button type="button" class="btn btn-move report-tab" data-report-view="orders" aria-selected="false">逐单明细</button></div>
 <div class="report-view" data-view="summary"><div class="table-wrapper"><table class="inventory-table"><tr><th>商品</th><th>SKU</th><th>订单数</th><th>订单应扣</th><th>手动调整</th><th>合计变化</th></tr>
 <?php if (!$orderReport): ?><tr><td colspan="6">这个月份没有已确认订单记录</td></tr><?php endif; ?>
 <?php foreach ($orderReport as $row): $manual=$manualBySku[(string)$row['sku']] ?? 0; $ordered=(int)$row['order_quantity']; $net=$manual-$ordered; ?><tr><td data-label="商品"><div class="report-product"><?php if(!empty($row['image_url'])): ?><img src="<?= htmlspecialchars(productImageUrl($row['image_url']),ENT_QUOTES) ?>" alt="" onerror="this.style.display='none'"> <?php else: ?><span class="report-image-empty"></span><?php endif; ?><strong><?= htmlspecialchars($row['product_name']) ?></strong></div></td><td data-label="SKU"><?= htmlspecialchars($row['sku']) ?></td><td data-label="订单数"><?= (int)$row['order_count'] ?></td><td data-label="订单应扣" class="movement-change negative">－<?= $ordered ?></td><td data-label="手动调整" class="movement-change <?= $manual>=0?'positive':'negative' ?>"><?= $manual>0?'+':'' ?><?= $manual ?></td><td data-label="合计变化" class="movement-change <?= $net>=0?'positive':'negative' ?>"><?= $net>0?'+':'' ?><?= $net ?></td></tr><?php endforeach; ?>
 </table></div></div>
 <div class="report-view" data-view="orders" hidden>
  <?php if (!$ordersDetail): ?><div class="order-detail-card"><div class="order-detail-head">这个月份没有已确认订单</div></div><?php endif; ?>
  <?php foreach ($ordersDetail as $order): ?>
   <article class="order-detail-card"><header class="order-detail-head"><button type="button" class="order-detail-toggle" aria-expanded="false"><span><strong>订单 <?= htmlspecialchars($order['order_number']) ?></strong><small><?= count($order['items']) ?> 项商品</small></span><span><?= htmlspecialchars(date('Y-m-d H:i',strtotime($order['created_at']))) ?></span></button></header>
   <div class="order-detail-items" hidden><?php foreach ($order['items'] as $item): ?><div class="order-detail-item"><div class="report-product"><?php if(!empty($item['image_url'])): ?><img src="<?= htmlspecialchars(productImageUrl($item['image_url']),ENT_QUOTES) ?>" alt="" onerror="this.style.display='none'"><?php else: ?><span class="report-image-empty"></span><?php endif; ?><strong><?= htmlspecialchars($item['product_name']) ?></strong></div><span>SKU：<?= htmlspecialchars($item['sku']) ?></span><span class="deduct-qty">扣减 －<?= (int)$item['quantity'] ?></span></div><?php endforeach; ?></div>
   </article>
  <?php endforeach; ?>
 </div></section>
</div>

<script>
(function () {
  const reportModal = document.getElementById('reportModal');
  const setReportOpen = open => { reportModal.classList.toggle('show', open); document.body.style.overflow = open ? 'hidden' : ''; };
  document.getElementById('openReport')?.addEventListener('click', () => setReportOpen(true));
  document.getElementById('closeReport')?.addEventListener('click', () => setReportOpen(false));
  reportModal?.addEventListener('click', event => { if (event.target === reportModal) setReportOpen(false); });
  document.querySelectorAll('.report-tab').forEach(tab => tab.addEventListener('click', () => {
    const selected = tab.dataset.reportView;
    document.querySelectorAll('.report-tab').forEach(item => item.setAttribute('aria-selected', String(item === tab)));
    document.querySelectorAll('.report-view').forEach(view => { view.hidden = view.dataset.view !== selected; });
  }));
  document.querySelectorAll('.order-detail-toggle').forEach(button => button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    button.closest('.order-detail-card').querySelector('.order-detail-items').hidden = !open;
  }));
  document.querySelectorAll('.group-toggle').forEach(button => button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    button.textContent = open ? '收起分类' : '查看分类';
    document.querySelectorAll('.variant-inventory-row[data-parent-id="' + button.dataset.groupId + '"]').forEach(row => row.classList.toggle('is-collapsed', !open));
  }));
  const groupSelect = document.getElementById('groupSelect');
  const catSelect = document.getElementById('catSelect');

  function syncCategoryOptions() {
    const group = groupSelect.value;
    const options = Array.from(catSelect.querySelectorAll('option'));

    options.forEach(function (option) {
      const match = !group || option.getAttribute('data-group') === group || option.value === '';
      option.hidden = !match;
      option.disabled = !match;
    });
  }

  if (groupSelect && catSelect) {
    groupSelect.addEventListener('change', function () {
      catSelect.value = '';
      syncCategoryOptions();
    });

    syncCategoryOptions();
  }
})();
</script>

</body>
</html>
