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
// Child products are managed from their grouped parent so product and variant
// data cannot be edited through two competing forms.
if (!empty($p['parent_product_id'])) {
    header('Location: edit_product.php?id=' . (int)$p['parent_product_id'] . '#variant-' . $id);
    exit;
}

$variantStmt = $pdo->prepare('SELECT * FROM product_variants WHERE product_id=? ORDER BY sort_order,id');
$variantStmt->execute([$id]);
$productVariants = $variantStmt->fetchAll(PDO::FETCH_ASSOC);
$availableStmt = $pdo->prepare("SELECT p.id,p.sku,p.name,p.price,p.stock,p.image_url,p.parent_product_id,parent.name parent_name
  FROM products p LEFT JOIN products parent ON parent.id=p.parent_product_id
  WHERE p.product_type='single' AND p.id<>? ORDER BY p.name,p.sku");
$availableStmt->execute([$id]);
$availableSingles = $availableStmt->fetchAll(PDO::FETCH_ASSOC);
$error = '';

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
    $productType = ($_POST['product_type'] ?? 'single') === 'grouped' ? 'grouped' : 'single';
    $variantFlavors = null;
    $variantSizes = null;
    if ($productType === 'grouped') {
        $firstSourceId = (int)($_POST['source_product_id'][0] ?? 0);
        if ($firstSourceId > 0) {
            $firstSourceStmt = $pdo->prepare("SELECT sku,price FROM products WHERE id=?");
            $firstSourceStmt->execute([$firstSourceId]);
            $firstSource = $firstSourceStmt->fetch();
            $sku = $firstSource['sku'] ?? $sku;
            $price = (float)($firstSource['price'] ?? $price);
        } else {
            $sku = trim($_POST['variant_sku'][0] ?? $sku);
            $price = (float)($_POST['variant_price'][0] ?? $price);
        }
        $stock = 0;
    }

    if (!array_key_exists($category, $categories)) {
        $category = array_key_first($categories);
    }

    try {
    $image_url = $p['image_url'];

    // 图片上传处理
    $mainImageError = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($mainImageError !== UPLOAD_ERR_NO_FILE) {
        if ($mainImageError !== UPLOAD_ERR_OK) {
            throw new RuntimeException('图片上传失败，请重新选择文件。');
        }
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        $mime = mime_content_type($_FILES['image']['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('图片格式只支持 JPG、PNG、GIF 或 WEBP。');
        }
        if ((int)$_FILES['image']['size'] > 2 * 1024 * 1024) {
            throw new RuntimeException('图片大小不能超过 2MB。');
        }
        $filename = uniqid('', true) . "." . $allowed[$mime];
        $targetDir = __DIR__ . "/../frontend/uploads/";
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('无法建立图片上传目录。');
        }
        $target = $targetDir . $filename;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            throw new RuntimeException('图片保存失败，请稍后重试。');
        }
        $image_url = "frontend/uploads/" . $filename;
    }

    // 更新数据库
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE products
        SET sku=?, name=?, product_type=?, variant_flavors=?, variant_sizes=?, price=?, stock=?, category=?, image_url=?, sort_order=?, is_hot=?
        WHERE id=?");

        $stmt->execute([
        $sku,
        $name,
        $productType,
        $variantFlavors,
        $variantSizes,
        $price,
        $stock,
        $category,
        $image_url,
        $sort_order,
        $is_hot,
        $id
        ]);
        $pdo->prepare('UPDATE products SET parent_product_id=NULL WHERE parent_product_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM product_variants WHERE product_id=?')->execute([$id]);
        if ($productType === 'grouped') {
        $variantNames = $_POST['variant_name'] ?? [];
        if (count($variantNames) === 0) {
            throw new RuntimeException('分类商品至少需要一个分类项目。');
        }
        $insertVariant = $pdo->prepare('INSERT INTO product_variants
          (product_id,variant_name,sku,price,stock,image_url,sort_order) VALUES (?,?,?,?,?,?,?)');
        foreach ($variantNames as $index => $variantName) {
            $variantName = trim($variantName);
            $sourceId = (int)($_POST['source_product_id'][$index] ?? 0);
            if ($variantName === '') {
                throw new RuntimeException('分类项目名称不能为空。');
            }
            $source = null;
            if ($sourceId > 0) {
                $sourceStmt = $pdo->prepare("SELECT sku,price,stock,image_url FROM products WHERE id=? AND product_type='single'");
                $sourceStmt->execute([$sourceId]);
                $source = $sourceStmt->fetch();
                if (!$source) {
                    throw new RuntimeException('分类项目来源商品不存在，请刷新页面后重试。');
                }
                $pdo->prepare('DELETE FROM product_variants WHERE source_product_id=?')->execute([$sourceId]);
            }
            $variantSku = trim($_POST['variant_sku'][$index] ?? '') ?: (string)($source['sku'] ?? '');
            $variantPrice = isset($_POST['variant_price'][$index]) && $_POST['variant_price'][$index] !== ''
                ? (float)$_POST['variant_price'][$index]
                : (float)($source['price'] ?? 0);
            $variantStock = isset($_POST['variant_stock'][$index]) && $_POST['variant_stock'][$index] !== ''
                ? max(0, (int)$_POST['variant_stock'][$index])
                : max(0, (int)($source['stock'] ?? 0));
            $variantImage = trim($_POST['existing_variant_image'][$index] ?? '') ?: (string)($source['image_url'] ?? '');
            $variantUploadError = $_FILES['variant_image']['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($variantUploadError !== UPLOAD_ERR_NO_FILE) {
                if ($variantUploadError !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('分类项目图片上传失败，请重新选择文件。');
                }
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
                $tmp = $_FILES['variant_image']['tmp_name'][$index];
                $mime = mime_content_type($tmp);
                if (!isset($allowed[$mime])) {
                    throw new RuntimeException('分类项目图片格式只支持 JPG、PNG、GIF 或 WEBP。');
                }
                if ((int)$_FILES['variant_image']['size'][$index] > 2 * 1024 * 1024) {
                    throw new RuntimeException('分类项目图片大小不能超过 2MB。');
                }
                $filename = uniqid('', true) . '.' . $allowed[$mime];
                if (!move_uploaded_file($tmp, __DIR__ . '/../frontend/uploads/' . $filename)) {
                    throw new RuntimeException('分类项目图片保存失败，请稍后重试。');
                }
                $variantImage = 'frontend/uploads/' . $filename;
            }
            $insertVariant = $pdo->prepare('INSERT INTO product_variants
              (product_id,source_product_id,variant_name,sku,price,stock,image_url,sort_order) VALUES (?,?,?,?,?,?,?,?)');
            if ($variantSku === '') {
                throw new RuntimeException('分类项目 SKU 不能为空。');
            }
            $insertVariant->execute([$id,$sourceId ?: null,$variantName,$variantSku,$variantPrice,$variantStock,$variantImage ?: null,$index+1]);
            if ($sourceId > 0) {
                $pdo->prepare('UPDATE products SET name=?,sku=?,price=?,stock=?,image_url=?,parent_product_id=? WHERE id=?')
                    ->execute([$variantName,$variantSku,$variantPrice,$variantStock,$variantImage ?: null,$id,$sourceId]);
            }
        }
        }

        if (!empty($p['parent_product_id']) && $productType === 'single') {
            $pdo->prepare(
                'UPDATE product_variants
                 SET variant_name=?,sku=?,price=?,stock=?,image_url=?
                 WHERE source_product_id=?'
            )->execute([$name,$sku,$price,$stock,$image_url ?: null,$id]);
        }

        $pdo->commit();
        header("Location: products.php?cat=" . urlencode($category) . "&msg=" . urlencode("✅ 商品已更新"));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = '更新失败：' . $e->getMessage();
    }
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
  .type-choice{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
  .type-choice label{padding:16px;border:1px solid var(--line);border-radius:18px;background:#fffaf4;font-weight:800;}
  .variant-fields{display:none;padding:18px;border:1px dashed #d8bfa4;border-radius:20px;background:#fffaf4;}
  .variant-fields.show{display:block;}
  .variant-head{display:flex;justify-content:space-between;align-items:center;gap:12px;}
.variant-row{
  display:grid;
  grid-template-columns:
    minmax(140px,1fr)
    minmax(120px,1fr)
    minmax(120px,1fr)
    minmax(100px,1fr)
    64px
    minmax(170px,1fr)
    auto;
  gap:14px;
  padding:16px;
  margin-top:12px;
  border:1px solid #f2bfd5;
  border-radius:18px;
  background:#fff;
  align-items:center;
}  
  .variant-row{
  display:grid;
  grid-template-columns:
    minmax(170px,1.2fr)
    minmax(120px,1fr)
    minmax(120px,1fr)
    minmax(100px,.8fr)
    64px
    minmax(170px,1fr);
  gap:14px;
  padding:16px;
  margin-top:12px;
  border:1px solid #f2bfd5;
  border-radius:18px;
  background:#fff;
  align-items:center;
}

.remove-variant{
  grid-column:1 / -1;
  width:180px;
  border:1px solid #ffb4c8;
  background:#fff;
  color:#d33;
  border-radius:12px;
  padding:10px 12px;
}
  .variant-photo{width:58px;height:58px;border-radius:14px;border:1px solid #ead8c8;background:#fffaf4;object-fit:cover;}
  .remove-variant{
  grid-column:1 / -1;
  width:180px;
  border:1px solid #ffb4c8;
  background:#fff;
  color:#d33;
  border-radius:12px;
  padding:10px 12px;
}
  .variant-actions{display:flex;gap:10px;flex-wrap:wrap;}
  .product-picker{display:none;position:fixed;inset:0;z-index:3000;background:rgba(30,24,22,.42);padding:34px;}
  .product-picker.show{display:block;}
  .picker-card{max-width:1180px;height:calc(100vh - 68px);margin:auto;background:#fff;border-radius:28px;padding:24px;box-sizing:border-box;display:flex;flex-direction:column;box-shadow:0 24px 70px rgba(50,35,25,.25);}
  .picker-head{display:flex;justify-content:space-between;align-items:center;gap:14px;}
  .picker-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px;overflow:auto;padding:18px 4px;}
  .picker-product{display:grid;grid-template-columns:74px 1fr;gap:12px;text-align:left;border:1px solid var(--line);border-radius:18px;padding:12px;background:#fff;cursor:pointer;}
  .picker-product:hover{border-color:#c8a987;background:#fffaf4;}
  .picker-product img{width:74px;height:74px;object-fit:cover;border-radius:12px;}
  .picker-product strong,.picker-product span,.picker-product small{display:block;}
  .picker-product small{color:var(--muted);margin-top:3px;}
  .picker-close{width:42px;height:42px;border-radius:50%;border:1px solid var(--line);background:#fff;font-size:22px;}

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
@media(max-width:1100px){
  .variant-row{
    grid-template-columns:repeat(2,minmax(0,1fr));
  }

  .variant-photo{
    width:74px;
    height:74px;
  }
}  @media(max-width:650px){.variant-row,.variant-row.variant-existing{grid-template-columns:1fr;}}
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
  <?php if($error): ?><div class="msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

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
        <h3 class="section-title">商品类型</h3>
        <div class="type-choice">
          <label><input type="radio" name="product_type" value="single" <?= ($p['product_type'] ?? 'single') === 'single' ? 'checked' : '' ?>> 单商品</label>
          <label><input type="radio" name="product_type" value="grouped" <?= ($p['product_type'] ?? 'single') === 'grouped' ? 'checked' : '' ?>> 分类商品</label>
        </div>
      </div>
      <div class="form-section">
        <h3 class="section-title">基本资料</h3>

        <div class="edit-form-grid">
          <div class="form-field single-only">
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

          <div class="form-field single-only">
            <label>价格</label>
            <input type="number"
                   step="0.01"
                   name="price"
                   value="<?= htmlspecialchars($p['price']) ?>"
                   required>
          </div>

          <div class="form-field single-only">
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

      <div id="variantFields" class="form-section variant-fields <?= ($p['product_type'] ?? 'single') === 'grouped' ? 'show' : '' ?>">
        <div class="variant-head">
          <div><h3 class="section-title">分类商品项目</h3><p>每项拥有独立名称、SKU、价格、库存和图片。</p></div>
          <div class="variant-actions">
            <button type="button" id="addVariant" class="btn btn-edit">＋ 添加新分类</button>
            <button type="button" id="useExisting" class="btn btn-move">使用现有商品</button>
          </div>
        </div>
        <div id="variantList">
          <?php foreach($productVariants as $variant): ?>
            <div id="variant-<?= (int)($variant['source_product_id'] ?? 0) ?>" class="variant-row <?= !empty($variant['source_product_id']) ? 'variant-existing' : '' ?>">
              <input name="variant_name[]" value="<?= htmlspecialchars($variant['variant_name']) ?>" placeholder="分类名称" required>
              <input type="hidden" name="source_product_id[]" value="<?= (int)($variant['source_product_id'] ?? 0) ?>">
              <?php if(!empty($variant['source_product_id'])): ?>
                <input name="variant_sku[]" value="<?= htmlspecialchars($variant['sku']) ?>" placeholder="SKU" required>
                <input type="number" step=".01" min="0" name="variant_price[]" value="<?= htmlspecialchars($variant['price']) ?>" placeholder="价格 RM" required>
                <input type="number" min="0" name="variant_stock[]" value="<?= (int)$variant['stock'] ?>" placeholder="库存" required>
                <input type="hidden" name="existing_variant_image[]" value="<?= htmlspecialchars($variant['image_url'] ?? '') ?>">
                <img class="variant-photo" src="/yummy-diary/<?= htmlspecialchars($variant['image_url'] ?: 'images/soldout.png') ?>" alt="分类图片预览" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';">
                <input type="file" name="variant_image[]" accept="image/jpeg,image/png,image/gif,image/webp">
              <?php else: ?>
                <input name="variant_sku[]" value="<?= htmlspecialchars($variant['sku']) ?>" placeholder="SKU" required>
                <input type="number" step=".01" min="0" name="variant_price[]" value="<?= htmlspecialchars($variant['price']) ?>" placeholder="价格 RM" required>
                <input type="number" min="0" name="variant_stock[]" value="<?= (int)$variant['stock'] ?>" placeholder="库存" required>
                <input type="hidden" name="existing_variant_image[]" value="<?= htmlspecialchars($variant['image_url'] ?? '') ?>">
                <img class="variant-photo" src="/yummy-diary/<?= htmlspecialchars($variant['image_url'] ?: 'images/soldout.png') ?>" alt="分类图片预览" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';">
                <input type="file" name="variant_image[]" accept="image/jpeg,image/png,image/gif,image/webp">
              <?php endif; ?>
              <button type="button" class="remove-variant">删除</button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div id="productPicker" class="product-picker">
        <div class="picker-card">
          <div class="picker-head"><div><h3>选择现有商品</h3><p>可选择独立单商品，也可把其他分类商品下的子商品移动到这里。</p></div><button type="button" class="picker-close">×</button></div>
          <input id="pickerSearch" type="search" placeholder="搜索商品名称或 SKU">
          <div class="picker-grid">
            <?php foreach($availableSingles as $single): ?>
              <button type="button" class="picker-product" data-search="<?= htmlspecialchars(strtolower($single['name'].' '.$single['sku'])) ?>" data-id="<?= (int)$single['id'] ?>" data-name="<?= htmlspecialchars($single['name'],ENT_QUOTES) ?>" data-sku="<?= htmlspecialchars($single['sku'],ENT_QUOTES) ?>" data-price="<?= htmlspecialchars($single['price'],ENT_QUOTES) ?>" data-stock="<?= (int)$single['stock'] ?>" data-image="<?= htmlspecialchars($single['image_url'] ?: 'images/soldout.png',ENT_QUOTES) ?>">
                <img src="/yummy-diary/<?= htmlspecialchars($single['image_url'] ?: 'images/soldout.png') ?>">
                <span><strong><?= htmlspecialchars($single['name']) ?></strong><small><?= htmlspecialchars($single['sku']) ?> · RM <?= number_format((float)$single['price'],2) ?> · 库存 <?= (int)$single['stock'] ?></small><?php if($single['parent_name']): ?><small>目前属于：<?= htmlspecialchars($single['parent_name']) ?></small><?php endif; ?></span>
              </button>
            <?php endforeach; ?>
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
<script>
const variantFields=document.getElementById('variantFields');
const variantList=document.getElementById('variantList');
const editForm=document.querySelector('form.admin-card');
editForm.addEventListener('submit',()=>{
  variantList.querySelectorAll('.variant-row').forEach((row,index)=>{
    const file=row.querySelector('input[type="file"][name^="variant_image"]');
    if(file)file.name=`variant_image[${index}]`;
  });
});
function bindRemove(row){row.querySelector('.remove-variant').onclick=()=>row.remove();}
function bindImagePreview(row){
  const fileInput=row.querySelector('input[type="file"][name="variant_image[]"]');
  const img=row.querySelector('.variant-photo');
  if(!fileInput||!img)return;
  fileInput.addEventListener('change',()=>{
    const file=fileInput.files[0];
    if(!file)return;
    img.src=URL.createObjectURL(file);
  });
}
const singleOptions=<?= json_encode($availableSingles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function addVariant(product=null){const row=document.createElement('div');row.className='variant-row'+(product?' variant-existing':'');row.innerHTML=product?`<input name="variant_name[]" placeholder="分类名称" required value="${product.name}"><input type="hidden" name="source_product_id[]" value="${product.id}"><input type="hidden" name="variant_sku[]" value=""><input type="hidden" name="variant_price[]" value=""><input type="hidden" name="variant_stock[]" value=""><input type="hidden" name="existing_variant_image[]" value=""><input type="file" name="variant_image[]" hidden><img class="variant-photo" src="/yummy-diary/images/soldout.png" alt="分类图片预览" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';"><div><strong>${product.sku}</strong><br><span>RM ${product.price} · 库存 ${product.stock}</span></div><button type="button" class="remove-variant">删除</button>`:`<input name="variant_name[]" placeholder="分类名称" required><input type="hidden" name="source_product_id[]" value="0"><input name="variant_sku[]" placeholder="SKU" required><input type="number" step=".01" min="0" name="variant_price[]" placeholder="价格 RM" required><input type="number" min="0" name="variant_stock[]" placeholder="库存" required><input type="hidden" name="existing_variant_image[]" value=""><img class="variant-photo" src="/yummy-diary/images/soldout.png" alt="分类图片预览" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';"><input type="file" name="variant_image[]" accept="image/jpeg,image/png,image/gif,image/webp"><button type="button" class="remove-variant">删除</button>`;bindRemove(row);bindImagePreview(row);variantList.appendChild(row);}
document.querySelectorAll('.variant-row').forEach(row=>{bindRemove(row);bindImagePreview(row);});
document.getElementById('addVariant').onclick=()=>addVariant();
const picker=document.getElementById('productPicker');
document.getElementById('useExisting').onclick=()=>picker.classList.add('show');
picker.querySelector('.picker-close').onclick=()=>picker.classList.remove('show');
picker.addEventListener('click',e=>{if(e.target===picker)picker.classList.remove('show')});
picker.querySelectorAll('.picker-product').forEach(card=>card.onclick=()=>{addVariant({id:card.dataset.id,name:card.dataset.name,sku:card.dataset.sku,price:card.dataset.price,stock:card.dataset.stock});const row=variantList.lastElementChild;row.classList.remove('variant-existing');row.querySelector('[name="variant_sku[]"]').type='text';row.querySelector('[name="variant_sku[]"]').value=card.dataset.sku;const priceInput=row.querySelector('[name="variant_price[]"]');priceInput.type='number';priceInput.step='0.01';priceInput.min='0';priceInput.value=card.dataset.price;row.querySelector('[name="variant_stock[]"]').type='number';row.querySelector('[name="variant_stock[]"]').value=card.dataset.stock;row.querySelector('[name="existing_variant_image[]"]').value=card.dataset.image;const image=row.querySelector('.variant-photo');image.src='/yummy-diary/'+card.dataset.image;const file=row.querySelector('[name="variant_image[]"]');file.hidden=false;file.accept='image/jpeg,image/png,image/gif,image/webp';bindImagePreview(row);picker.classList.remove('show')});
document.getElementById('pickerSearch').oninput=e=>{const q=e.target.value.toLowerCase();picker.querySelectorAll('.picker-product').forEach(card=>card.hidden=!card.dataset.search.includes(q))};
document.querySelectorAll('[name=product_type]').forEach(radio=>{
  radio.addEventListener('change',()=>{const grouped=radio.value==='grouped'&&radio.checked;variantFields.classList.toggle('show',grouped);document.querySelectorAll('.single-only').forEach(el=>el.style.display=grouped?'none':'flex');document.querySelectorAll('.single-only input').forEach(el=>el.required=!grouped);if(grouped&&!variantList.children.length)addVariant();});
});
if(document.querySelector('[name=product_type]:checked').value==='grouped'){document.querySelectorAll('.single-only').forEach(el=>el.style.display='none');document.querySelectorAll('.single-only input').forEach(el=>el.required=false);}
</script>
</body>
</html>
