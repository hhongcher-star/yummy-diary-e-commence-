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

// ====================
// 读取商品
// ====================
$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die("❌ 未找到商品");
}

// ====================
// 更新保存
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku']);
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category = $_POST['category'];
    $sort_order = $_POST['sort_order'] !== "" ? intval($_POST['sort_order']) : $p['sort_order'];
    $is_hot = isset($_POST['is_hot']) ? 1 : 0;

    if (!array_key_exists($category, $categories)) {
        $category = array_key_first($categories);
    }

    $image_url = $p['image_url'];

    // 图片上传处理
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif'];

        if (in_array($_FILES['image']['type'], $allowed) && $_FILES['image']['size'] <= 2 * 1024 * 1024) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $filename = uniqid() . "." . $ext;

            $targetDir = __DIR__ . "/../frontend/uploads/";

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $target = $targetDir . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                // 删除旧图片
                $oldImage = __DIR__ . "/../" . ltrim($p['image_url'], "/");

                if ($p['image_url'] && file_exists($oldImage)) {
                    unlink($oldImage);
                }

                $image_url = "frontend/uploads/" . $filename;
            }
        }
    }

    // 更新数据库
    $stmt = $pdo->prepare("UPDATE products 
        SET sku=?, name=?, price=?, stock=?, category=?, image_url=?, sort_order=?, is_hot=? 
        WHERE id=?");

    $stmt->execute([
        $sku,
        $name,
        $price,
        $stock,
        $category,
        $image_url,
        $sort_order,
        $is_hot,
        $id
    ]);

    header("Location: products.php?cat=" . urlencode($category) . "&msg=" . urlencode("✅ 商品已更新"));
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>编辑商品</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<style>
  .edit-layout{
    display:grid;
    grid-template-columns:360px 1fr;
    gap:22px;
    align-items:start;
  }

  .preview-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:26px;
    padding:22px;
    box-shadow:var(--shadow);
    position:sticky;
    top:24px;
  }

  .preview-image{
    width:100%;
    aspect-ratio:1 / 1;
    border-radius:24px;
    background:#fff7f0;
    border:1px solid var(--line);
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-bottom:16px;
  }

  .preview-image img{
    width:100%;
    height:100%;
    object-fit:cover;
  }

  .no-image{
    color:var(--muted);
    font-weight:800;
  }

  .preview-card h3{
    margin:0 0 8px;
    color:var(--text);
    font-size:22px;
  }

  .preview-card p{
    margin:0;
    color:var(--muted);
    line-height:1.6;
  }

  .preview-price{
    margin-top:14px;
    display:inline-flex;
    padding:9px 14px;
    border-radius:999px;
    background:#fffaf4;
    border:1px solid var(--line);
    font-weight:900;
    color:var(--text);
  }

  .form-section{
    margin-bottom:18px;
  }

  .section-title{
    margin:0 0 14px;
    font-size:17px;
    color:var(--text);
  }

  .edit-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
  }

  .form-field{
    display:flex;
    flex-direction:column;
    gap:7px;
  }

  .form-field.full{
    grid-column:1 / -1;
  }

  .form-field label{
    color:var(--muted);
    font-size:13px;
    font-weight:800;
  }

  .file-upload{
    padding:16px;
    border:1px dashed #d8bfa4;
    border-radius:20px;
    background:#fffaf4;
  }

  .hot-toggle{
    display:flex;
    align-items:center;
    gap:10px;
    padding:16px;
    border-radius:20px;
    background:#fffaf4;
    border:1px solid var(--line);
    font-weight:800;
  }

  .hot-toggle input{
    width:18px;
    height:18px;
    accent-color:#c9a984;
  }

  .form-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:20px;
  }

  @media(max-width:900px){
    .edit-layout{
      grid-template-columns:1fr;
    }

    .preview-card{
      position:relative;
      top:0;
    }
  }

  @media(max-width:600px){
    .edit-form-grid{
      grid-template-columns:1fr;
    }

    .form-actions .btn{
      width:100%;
    }
  }
</style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>编辑商品</h2>
      <p>修改商品资料、价格、库存、分类、图片和热销状态</p>
    </div>
  </section>

  <div class="edit-layout">
    <aside class="preview-card">
      <div class="preview-image">
        <?php if(!empty($p['image_url'])): ?>
          <img src="/yummy-diary/<?= htmlspecialchars($p['image_url']) ?>"
               onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';">
        <?php else: ?>
          <span class="no-image">No Image</span>
        <?php endif; ?>
      </div>

      <h3><?= htmlspecialchars($p['name']) ?></h3>
      <p>SKU：<?= htmlspecialchars($p['sku']) ?></p>
      <p>库存：<?= (int)$p['stock'] ?></p>

      <div class="preview-price">
        RM <?= number_format((float)$p['price'], 2) ?>
      </div>
    </aside>

    <form class="admin-card" method="post" enctype="multipart/form-data">
      <div class="form-section">
        <h3 class="section-title">基本资料</h3>

        <div class="edit-form-grid">
          <div class="form-field">
            <label>SKU</label>
            <input type="text"
                   name="sku"
                   value="<?= htmlspecialchars($p['sku']) ?>"
                   required>
          </div>

          <div class="form-field">
            <label>商品名称</label>
            <input type="text"
                   name="name"
                   value="<?= htmlspecialchars($p['name']) ?>"
                   required>
          </div>

          <div class="form-field">
            <label>价格</label>
            <input type="number"
                   step="0.01"
                   name="price"
                   value="<?= htmlspecialchars($p['price']) ?>"
                   required>
          </div>

          <div class="form-field">
            <label>库存</label>
            <input type="number"
                   name="stock"
                   value="<?= (int)$p['stock'] ?>"
                   required>
          </div>

          <div class="form-field">
            <label>分类</label>
            <select name="category">
              <?php foreach($categoryGroups as $group): ?>
                <optgroup label="<?= htmlspecialchars($group['label']) ?>">
                  <?php foreach($group['children'] as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= $p['category'] === $key ? 'selected' : '' ?>>
                      <?= htmlspecialchars($label) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-field">
            <label>排序</label>
            <input type="number"
                   name="sort_order"
                   value="<?= (int)$p['sort_order'] ?>">
          </div>
        </div>
      </div>

      <div class="form-section">
        <h3 class="section-title">商品图片</h3>

        <div class="file-upload">
          <input type="file" name="image" accept="image/jpeg,image/png,image/gif">
          <p style="margin:10px 0 0;color:var(--muted);font-size:13px;">
            支持 JPG / PNG / GIF，最大 2MB。上传新图片后会替换旧图片。
          </p>
        </div>
      </div>

      <div class="form-section">
        <h3 class="section-title">展示设置</h3>

        <label class="hot-toggle">
          <input type="checkbox"
                 name="is_hot"
                 value="1"
                 <?= !empty($p['is_hot']) ? 'checked' : '' ?>>
          🔥 设为热销商品
        </label>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-edit">💾 保存修改</button>

        <a href="products.php?cat=<?= urlencode($p['category']) ?>" class="btn btn-move">
          ⬅ 返回商品列表
        </a>
      </div>
    </form>
  </div>
</main>

</body>
</html>