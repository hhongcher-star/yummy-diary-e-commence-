<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

$stmt = $pdo->query(
    "SELECT
        g.group_key,
        g.label AS group_label,
        c.category_key,
        c.name AS category_name
     FROM category_groups g
     JOIN product_categories c ON c.group_id = g.id
     WHERE g.status = 1 AND c.status = 1
     ORDER BY g.sort_order ASC, c.sort_order ASC"
);

$categoryGroups = [];
$categories = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $groupKey = $row['group_key'];

    if (!isset($categoryGroups[$groupKey])) {
        $categoryGroups[$groupKey] = [
            'label' => $row['group_label'],
            'children' => []
        ];
    }

    $categoryGroups[$groupKey]['children'][$row['category_key']] = $row['category_name'];
    $categories[$row['category_key']] = $row['category_name'];
}

$selectedGroup = $_GET['group'] ?? '';
$selectedCat = $_GET['cat'] ?? '';

if ($selectedGroup !== '' && !isset($categoryGroups[$selectedGroup])) {
    $selectedGroup = '';
}

if ($selectedCat !== '' && !isset($categories[$selectedCat])) {
    $selectedCat = '';
}

$cat = $selectedCat;

// ====================
// 上传图片函数
// ====================
function uploadImage($fileInput) {
    if (isset($_FILES[$fileInput]) && $_FILES[$fileInput]['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif'];
        if (!in_array($_FILES[$fileInput]['type'], $allowed)) return null;
        if ($_FILES[$fileInput]['size'] > 2*1024*1024) return null;
        $ext = strtolower(pathinfo($_FILES[$fileInput]['name'], PATHINFO_EXTENSION));
        $filename = uniqid().".".$ext;
        $targetDir = __DIR__ . "/../frontend/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir,0777,true);
        $target = $targetDir.$filename;
        if (move_uploaded_file($_FILES[$fileInput]['tmp_name'],$target)) {
            return "frontend/uploads/" . $filename;
        }
    }
    return null;
}

// ====================
// 添加商品（自动排最后）
// ====================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_product'])) {
    $sku=$_POST['sku']; $name=$_POST['name']; $price=floatval($_POST['price']); $stock=intval($_POST['stock']);
    $category=$_POST['category'];
    if (!array_key_exists($category,$categories)) $category=array_key_first($categories);

    $stmt=$pdo->prepare("SELECT MAX(sort_order) FROM products WHERE category=?");
    $stmt->execute([$category]);
    $max_sort=$stmt->fetchColumn();
    $sort_order=$max_sort!==null ? $max_sort+1 : 1;

    $image_url=uploadImage('image');
    $stmt=$pdo->prepare("INSERT INTO products (sku,name,price,stock,category,image_url,sort_order,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    $stmt->execute([$sku,$name,$price,$stock,$category,$image_url,$sort_order]);

    // STEP 4: Save logic for 'is_hot'
    // $is_hot = isset($_POST['is_hot']) ? 1 : 0;

    // Update the database to save the 'is_hot' status
    // $stmt = $pdo->prepare("UPDATE products SET is_hot = ? WHERE id = ?");
    // $stmt->execute([$is_hot, $product_id]);

    header("Location: products.php?cat=" . urlencode($category) . "&msg=" . urlencode("✅ 商品已添加"));
    exit;
}

// ====================
// 更新 is_hot（🔥最重要）
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && !isset($_POST['add_product'])) {

    $id = intval($_POST['id']);
    $is_hot = isset($_POST['is_hot']) ? 1 : 0;

    if ($is_hot) {

        // 🔥 Find the current maximum hot_order
        $stmt = $pdo->query("SELECT MAX(hot_order) FROM products WHERE is_hot=1");
        $max = $stmt->fetchColumn();
        $new_order = $max ? $max + 1 : 1;

        // 🔥 Assign hot_order
        $stmt = $pdo->prepare("UPDATE products SET is_hot=1, hot_order=? WHERE id=?");
        $stmt->execute([$new_order, $id]);

    } else {

        // ❌ Remove from hot products
        $stmt = $pdo->prepare("UPDATE products SET is_hot=0, hot_order=0 WHERE id=?");
        $stmt->execute([$id]);
    }

    // 🔥 Handle AJAX requests
    if (isset($_GET['ajax'])) {
        echo json_encode(['status' => 'ok']);
        exit;
    }

    header("Location: products.php?cat=" . urlencode($cat));
    exit;
}

// ====================
// 🔥 热销排序
// ====================
if (isset($_GET['hot_move'], $_GET['id'])) {
  header('Content-Type: application/json');

    $id = intval($_GET['id']);
    $move = $_GET['hot_move'];

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND is_hot=1");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {

        $current = (int)$product['hot_order'];

        if ($move === 'up') {
            $stmt = $pdo->prepare("
                SELECT * FROM products 
                WHERE is_hot=1 AND hot_order < ? 
                ORDER BY hot_order DESC LIMIT 1
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM products 
                WHERE is_hot=1 AND hot_order > ? 
                ORDER BY hot_order ASC LIMIT 1
            ");
        }

        $stmt->execute([$current]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($target) {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE products SET hot_order=? WHERE id=?")
                ->execute([$target['hot_order'], $product['id']]);

            $pdo->prepare("UPDATE products SET hot_order=? WHERE id=?")
                ->execute([$current, $target['id']]);

            $pdo->commit();
        }
    }

    // ====================
    // 🔥 重新整理 hot_order
    // ====================
    $stmt = $pdo->query("SELECT id FROM products WHERE is_hot=1 ORDER BY hot_order ASC");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $i => $pid) {
        $pdo->prepare("UPDATE products SET hot_order=? WHERE id=?")
            ->execute([$i+1, $pid]);
    }

    // 🔥 回传最新数据
    $stmt = $pdo->query("SELECT id,name,price,image_url,hot_order FROM products WHERE is_hot=1 ORDER BY hot_order ASC");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ====================
// 删除商品
// ====================
if (isset($_GET['delete'])) {
    $id=intval($_GET['delete']);
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    header("Location: products.php?cat=" . urlencode($cat) . "&msg=" . urlencode("❌ 商品已删除"));
    exit;
}

// ====================
// 上下移动排序
// ====================
if (isset($_GET['move'],$_GET['id'])) {
    $id=intval($_GET['id']); 
    $move=$_GET['move'];

    $stmt=$pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$id]); 
    $product=$stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $current_sort=(int)$product['sort_order']; 
        $category=$product['category'];

        if ($move==='up') {
            $stmt=$pdo->prepare("SELECT * FROM products WHERE category=? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1");
            $stmt->execute([$category,$current_sort]);
        } elseif ($move==='down') {
            $stmt=$pdo->prepare("SELECT * FROM products WHERE category=? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1");
            $stmt->execute([$category,$current_sort]);
        }

        $target=$stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($target)) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?")
                    ->execute([$target['sort_order'], $product['id']]);
                $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?")
                    ->execute([$current_sort, $target['id']]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }

    header("Location: products.php?cat=" . urlencode($cat));
    exit;
}

// ====================
// 重新整理排序（保证连续）
// ====================
if ($selectedCat !== '') {
    $stmt = $pdo->prepare("SELECT id FROM products WHERE category=? ORDER BY sort_order ASC,id ASC");
    $stmt->execute([$selectedCat]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $i => $pid) {
        $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?")->execute([$i+1, $pid]);
    }
}

// ====================
// 查询商品
// ====================
if ($selectedCat !== '') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category=? ORDER BY sort_order ASC,id DESC");
    $stmt->execute([$selectedCat]);
} elseif ($selectedGroup !== '') {
    $groupCats = array_keys($categoryGroups[$selectedGroup]['children']);
    $placeholders = implode(',', array_fill(0, count($groupCats), '?'));

    $stmt = $pdo->prepare("SELECT * FROM products WHERE category IN ($placeholders) ORDER BY category ASC, sort_order ASC,id DESC");
    $stmt->execute($groupCats);
} else {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY category ASC, sort_order ASC,id DESC");
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$msg=$_GET['msg']??'';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>商品管理</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">
<style>
  .table-wrapper{overflow-x:auto;}
  .product-form-card{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;}
  .product-form-card .full{grid-column:1 / -1;}
</style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>商品管理</h2>
      <p>管理店铺商品、价格、库存和热销状态</p>
    </div>
  </section>

  <form class="category-filter" method="get">
    <select id="groupSelect" name="group">
      <option value="">全部大分类</option>
      <?php foreach ($categoryGroups as $groupKey => $group): ?>
        <option value="<?= htmlspecialchars($groupKey) ?>" <?= (isset($_GET['group']) && $_GET['group'] === $groupKey) ? 'selected' : '' ?>>
          <?= htmlspecialchars($group['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select id="catSelect" name="cat">
      <option value="">全部小分类</option>
      <?php foreach ($categoryGroups as $groupKey => $group): ?>
        <?php foreach ($group['children'] as $key => $label): ?>
          <option value="<?= htmlspecialchars($key) ?>" data-group="<?= htmlspecialchars($groupKey) ?>" <?= ($cat === $key) ? 'selected' : '' ?>>
            <?= htmlspecialchars($group['label']) ?> / <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-edit">筛选</button>
    <a href="products.php" class="btn btn-move">重置</a>
  </form>

  <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- 添加商品 -->
  <form class="admin-card product-form-card" method="post" enctype="multipart/form-data">
    <input type="hidden" name="add_product" value="1">
    <input type="text" name="sku" placeholder="SKU" required>
    <input type="text" name="name" placeholder="商品名" required>
    <input type="number" step="0.01" name="price" placeholder="价格" required>
    <input type="number" name="stock" placeholder="库存" required>
    <select name="category">
      <?php foreach($categories as $key=>$label): ?>
        <option value="<?= $key ?>" <?= $cat===$key?'selected':'' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <input type="file" name="image">
    <button type="submit" class="btn btn-edit">➕ 添加</button>
  </form>

  <div class="table-wrapper">
    <table>
      <tr><th>ID</th><th>SKU</th><th>图片</th><th>商品名</th><th>价格</th><th>库存</th><th>排序</th><th>操作</th></tr>
      <?php foreach($products as $p): ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['sku']) ?></td>
        <td><?php if($p['image_url']): ?><img src="/yummy-diary/<?= htmlspecialchars($p['image_url']) ?>" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';" class="thumb"><?php endif; ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td>RM <?= number_format($p['price'],2) ?></td>
        <td><?= $p['stock'] ?></td>
        <td><?= $p['sort_order'] ?></td>
        <td>

          <!-- ✅ Edit 按钮 -->
          <a href="edit_product.php?id=<?= $p['id'] ?>" 
             class="btn btn-edit">
             ✏️ 编辑
          </a>

        <a href="products.php?cat=<?= urlencode($cat) ?>&move=up&id=<?= $p['id'] ?>" 
   class="btn btn-move">
   ⬆
</a>

          <!-- 🔽 下移 -->
          <a href="products.php?cat=<?= urlencode($cat) ?>&move=down&id=<?= $p['id'] ?>" 
   class="btn btn-move">
   ⬇
</a>

          <!-- ✅ 热销 -->
          <form method="post" style="display:inline;">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            
            <label>
              <input type="checkbox" name="is_hot" value="1"
                <?= $p['is_hot'] ? 'checked' : '' ?>
                onchange="this.form.submit()">
              🔥
            </label>
          </form>

          <!-- ✅ 删除 -->
          <a href="products.php?cat=<?= urlencode($cat) ?>&delete=<?= $p['id'] ?>"
             class="btn btn-delete"
             onclick="return confirm('确定删除？')">
             🗑 删除
          </a>

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
      const hasGroup = !!group;

      options.forEach(function (option) {
        const match = !hasGroup || option.getAttribute('data-group') === group || option.value === '';
        option.hidden = !match;
        option.disabled = !match;
      });

      if (group) {
        catSelect.value = '';
      }
    }

    if (groupSelect && catSelect) {
      groupSelect.addEventListener('change', function () {
        syncCategoryOptions();
      });
      syncCategoryOptions();
    }
  })();
</script>
</body>
</html>
