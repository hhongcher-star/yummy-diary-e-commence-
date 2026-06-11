<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// ====================
// 更新库存 / 预警值
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    if (isset($_POST['stock'])) {
        $stmt = $pdo->prepare("UPDATE products SET stock=? WHERE id=?");
        $stmt->execute([intval($_POST['stock']), $id]);
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
    $stmt = $pdo->prepare("SELECT id, sku, name, image_url, stock, warning_level, category FROM products WHERE category=? ORDER BY id DESC");
    $stmt->execute([$selectedCat]);

} elseif ($selectedGroup !== '') {
    $groupCats = array_keys($categoryGroups[$selectedGroup]['children']);
    $placeholders = implode(',', array_fill(0, count($groupCats), '?'));

    $stmt = $pdo->prepare("SELECT id, sku, name, image_url, stock, warning_level, category FROM products WHERE category IN ($placeholders) ORDER BY category ASC, id DESC");
    $stmt->execute($groupCats);

} else {
    $stmt = $pdo->query("SELECT id, sku, name, image_url, stock, warning_level, category FROM products ORDER BY category ASC, id DESC");
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$msg = $_GET['msg'] ?? '';
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

  @media(max-width:768px){
    .stock-form,
    .quick-actions{
      flex-wrap:nowrap;
    }

    .inventory-name{
      min-width:200px;
    }
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
  </section>

  <?php
    $totalProducts = count($products);
    $lowStockCount = 0;
    $totalStock = 0;

    foreach ($products as $item) {
        $totalStock += (int)$item['stock'];
        if ((int)$item['stock'] < (int)$item['warning_level']) {
            $lowStockCount++;
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

      <?php foreach($products as $p): ?>
        <?php $isLow = (int)$p['stock'] < (int)$p['warning_level']; ?>

        <tr>
          <td><?= $p['id'] ?></td>
          <td><?= htmlspecialchars($p['sku']) ?></td>

          <td>
            <?php if(!empty($p['image_url'])): ?>
              <img src="/yummy-diary/<?= htmlspecialchars($p['image_url']) ?>"
                   onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';"
                   class="thumb">
            <?php else: ?>
              <span class="empty-image">No Image</span>
            <?php endif; ?>
          </td>

          <td class="inventory-name">
            <?= htmlspecialchars($p['name']) ?>

            <?php if($isLow): ?>
              <br>
              <span class="low-badge">⚠️ 库存不足</span>
            <?php endif; ?>
          </td>

          <td>
            <span class="inventory-category">
              <?= isset($p['category'], $flatCategories[$p['category']])
                  ? htmlspecialchars($flatCategories[$p['category']])
                  : '未分类' ?>
            </span>
          </td>

          <td>
            <form method="post" class="stock-form">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="number"
                     name="stock"
                     value="<?= $p['stock'] ?>"
                     class="<?= $isLow ? 'stock-low' : 'stock-ok' ?>">
              <button type="submit" class="btn btn-edit">💾 更新</button>
            </form>
          </td>

          <td>
            <form method="post" class="stock-form">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="number"
                     name="warning_level"
                     value="<?= $p['warning_level'] ?>">
              <button type="submit" class="btn btn-move">⚙️ 设定</button>
            </form>
          </td>

          <td>
            <div class="quick-actions">
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
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</main>

<script>
(function () {
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