<?php
// ç¼–è¾‘å•†å“é¡µï¼šä¿®æ”¹çŽ°æœ‰å•†å“èµ„æ–™ã€å›¾ç‰‡ã€çŠ¶æ€å’Œå˜ä½“ä¿¡æ¯ã€‚
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
// è¯»å–å•†å“
// ====================
$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die("âŒ æœªæ‰¾åˆ°å•†å“");
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
// æ›´æ–°ä¿å­˜
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

    // å›¾ç‰‡ä¸Šä¼ å¤„ç†
    $mainImageError = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($mainImageError !== UPLOAD_ERR_NO_FILE) {
        if ($mainImageError !== UPLOAD_ERR_OK) {
            throw new RuntimeException('å›¾ç‰‡ä¸Šä¼ å¤±è´¥ï¼Œè¯·é‡æ–°é€‰æ‹©æ–‡ä»¶ã€‚');
        }
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        $mime = mime_content_type($_FILES['image']['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('å›¾ç‰‡æ ¼å¼åªæ”¯æŒ JPGã€PNGã€GIF æˆ– WEBPã€‚');
        }
        if ((int)$_FILES['image']['size'] > 2 * 1024 * 1024) {
            throw new RuntimeException('å›¾ç‰‡å¤§å°ä¸èƒ½è¶…è¿‡ 2MBã€‚');
        }
        $filename = uniqid('', true) . "." . $allowed[$mime];
        $targetDir = __DIR__ . "/../frontend/uploads/";
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to create product upload directory.');
        }
        $target = $targetDir . $filename;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            throw new RuntimeException('Unable to save uploaded product image.');
        }
        $image_url = "frontend/uploads/" . $filename;
    }

    // æ›´æ–°æ•°æ®åº“
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
            throw new RuntimeException('åˆ†ç±»å•†å“è‡³å°‘éœ€è¦ä¸€ä¸ªåˆ†ç±»é¡¹ç›®ã€‚');
        }
        $insertVariant = $pdo->prepare('INSERT INTO product_variants
          (product_id,variant_name,sku,price,stock,image_url,sort_order) VALUES (?,?,?,?,?,?,?)');
        foreach ($variantNames as $index => $variantName) {
            $variantName = trim($variantName);
            $sourceId = (int)($_POST['source_product_id'][$index] ?? 0);
            if ($variantName === '') {
                throw new RuntimeException('åˆ†ç±»é¡¹ç›®åç§°ä¸èƒ½ä¸ºç©ºã€‚');
            }
            $source = null;
            if ($sourceId > 0) {
                $sourceStmt = $pdo->prepare("SELECT sku,price,stock,image_url FROM products WHERE id=? AND product_type='single'");
                $sourceStmt->execute([$sourceId]);
                $source = $sourceStmt->fetch();
                if (!$source) {
                    throw new RuntimeException('åˆ†ç±»é¡¹ç›®æ¥æºå•†å“ä¸å­˜åœ¨ï¼Œè¯·åˆ·æ–°é¡µé¢åŽé‡è¯•ã€‚');
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
                    throw new RuntimeException('åˆ†ç±»é¡¹ç›®å›¾ç‰‡ä¸Šä¼ å¤±è´¥ï¼Œè¯·é‡æ–°é€‰æ‹©æ–‡ä»¶ã€‚');
                }
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
                $tmp = $_FILES['variant_image']['tmp_name'][$index];
                $mime = mime_content_type($tmp);
                if (!isset($allowed[$mime])) {
                    throw new RuntimeException('åˆ†ç±»é¡¹ç›®å›¾ç‰‡æ ¼å¼åªæ”¯æŒ JPGã€PNGã€GIF æˆ– WEBPã€‚');
                }
                if ((int)$_FILES['variant_image']['size'][$index] > 2 * 1024 * 1024) {
                    throw new RuntimeException('åˆ†ç±»é¡¹ç›®å›¾ç‰‡å¤§å°ä¸èƒ½è¶…è¿‡ 2MBã€‚');
                }
                $filename = uniqid('', true) . '.' . $allowed[$mime];
                if (!move_uploaded_file($tmp, __DIR__ . '/../frontend/uploads/' . $filename)) {
                    throw new RuntimeException('Unable to save uploaded variant image.');
                }
                $variantImage = 'frontend/uploads/' . $filename;
            }
            $insertVariant = $pdo->prepare('INSERT INTO product_variants
              (product_id,source_product_id,variant_name,sku,price,stock,image_url,sort_order) VALUES (?,?,?,?,?,?,?,?)');
            if ($variantSku === '') {
                throw new RuntimeException('åˆ†ç±»é¡¹ç›® SKU ä¸èƒ½ä¸ºç©ºã€‚');
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
        header("Location: products.php?cat=" . urlencode($category) . "&msg=" . urlencode("âœ… å•†å“å·²æ›´æ–°"));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'æ›´æ–°å¤±è´¥ï¼š' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>ç¼–è¾‘å•†å“</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<style>
<?php include __DIR__ . '/assets/css/edit_product.css'; ?>
</style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>ç¼–è¾‘å•†å“</h2>
      <p>ä¿®æ”¹å•†å“èµ„æ–™ã€ä»·æ ¼ã€åº“å­˜ã€åˆ†ç±»ã€å›¾ç‰‡å’Œçƒ­é”€çŠ¶æ€</p>
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
      <p>SKUï¼š<?= htmlspecialchars($p['sku']) ?></p>
      <p>åº“å­˜ï¼š<?= (int)$p['stock'] ?></p>

      <div class="preview-price">
        RM <?= number_format((float)$p['price'], 2) ?>
      </div>
    </aside>

    <form class="admin-card" method="post" enctype="multipart/form-data">
      <div class="form-section">
        <h3 class="section-title">å•†å“ç±»åž‹</h3>
        <div class="type-choice">
          <label><input type="radio" name="product_type" value="single" <?= ($p['product_type'] ?? 'single') === 'single' ? 'checked' : '' ?>> å•å•†å“</label>
          <label><input type="radio" name="product_type" value="grouped" <?= ($p['product_type'] ?? 'single') === 'grouped' ? 'checked' : '' ?>> åˆ†ç±»å•†å“</label>
        </div>
      </div>
      <div class="form-section">
        <h3 class="section-title">åŸºæœ¬èµ„æ–™</h3>

        <div class="edit-form-grid">
          <div class="form-field single-only">
            <label>SKU</label>
            <input type="text"
                   name="sku"
                   value="<?= htmlspecialchars($p['sku']) ?>"
                   required>
          </div>

          <div class="form-field">
            <label>å•†å“åç§°</label>
            <input type="text"
                   name="name"
                   value="<?= htmlspecialchars($p['name']) ?>"
                   required>
          </div>

          <div class="form-field single-only">
            <label>ä»·æ ¼</label>
            <input type="number"
                   step="0.01"
                   name="price"
                   value="<?= htmlspecialchars($p['price']) ?>"
                   required>
          </div>

          <div class="form-field single-only">
            <label>åº“å­˜</label>
            <input type="number"
                   name="stock"
                   value="<?= (int)$p['stock'] ?>"
                   required>
          </div>

          <div class="form-field">
            <label>åˆ†ç±»</label>
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
            <label>æŽ’åº</label>
            <input type="number"
                   name="sort_order"
                   value="<?= (int)$p['sort_order'] ?>">
          </div>
        </div>
      </div>

      <div id="variantFields" class="form-section variant-fields <?= ($p['product_type'] ?? 'single') === 'grouped' ? 'show' : '' ?>">
        <div class="variant-head">
          <div><h3 class="section-title">åˆ†ç±»å•†å“é¡¹ç›®</h3><p>æ¯é¡¹æ‹¥æœ‰ç‹¬ç«‹åç§°ã€SKUã€ä»·æ ¼ã€åº“å­˜å’Œå›¾ç‰‡ã€‚</p></div>
          <div class="variant-actions">
            <button type="button" id="addVariant" class="btn btn-edit">ï¼‹ æ·»åŠ æ–°åˆ†ç±»</button>
            <button type="button" id="useExisting" class="btn btn-move">ä½¿ç”¨çŽ°æœ‰å•†å“</button>
          </div>
        </div>
        <div id="variantList">
          <?php foreach($productVariants as $variant): ?>
            <div id="variant-<?= (int)($variant['source_product_id'] ?? 0) ?>" class="variant-row <?= !empty($variant['source_product_id']) ? 'variant-existing' : '' ?>">
              <input name="variant_name[]" value="<?= htmlspecialchars($variant['variant_name']) ?>" placeholder="åˆ†ç±»åç§°" required>
              <input type="hidden" name="source_product_id[]" value="<?= (int)($variant['source_product_id'] ?? 0) ?>">
              <?php if(!empty($variant['source_product_id'])): ?>
                <input name="variant_sku[]" value="<?= htmlspecialchars($variant['sku']) ?>" placeholder="SKU" required>
                <input type="number" step=".01" min="0" name="variant_price[]" value="<?= htmlspecialchars($variant['price']) ?>" placeholder="ä»·æ ¼ RM" required>
                <input type="number" min="0" name="variant_stock[]" value="<?= (int)$variant['stock'] ?>" placeholder="åº“å­˜" required>
                <input type="hidden" name="existing_variant_image[]" value="<?= htmlspecialchars($variant['image_url'] ?? '') ?>">
                <img class="variant-photo" src="/yummy-diary/<?= htmlspecialchars($variant['image_url'] ?: 'images/soldout.png') ?>" alt="åˆ†ç±»å›¾ç‰‡é¢„è§ˆ" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';">
                <input type="file" name="variant_image[]" accept="image/jpeg,image/png,image/gif,image/webp">
              <?php else: ?>
                <input name="variant_sku[]" value="<?= htmlspecialchars($variant['sku']) ?>" placeholder="SKU" required>
                <input type="number" step=".01" min="0" name="variant_price[]" value="<?= htmlspecialchars($variant['price']) ?>" placeholder="ä»·æ ¼ RM" required>
                <input type="number" min="0" name="variant_stock[]" value="<?= (int)$variant['stock'] ?>" placeholder="åº“å­˜" required>
                <input type="hidden" name="existing_variant_image[]" value="<?= htmlspecialchars($variant['image_url'] ?? '') ?>">
                <img class="variant-photo" src="/yummy-diary/<?= htmlspecialchars($variant['image_url'] ?: 'images/soldout.png') ?>" alt="åˆ†ç±»å›¾ç‰‡é¢„è§ˆ" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';">
                <input type="file" name="variant_image[]" accept="image/jpeg,image/png,image/gif,image/webp">
              <?php endif; ?>
              <button type="button" class="remove-variant">åˆ é™¤</button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div id="productPicker" class="product-picker">
        <div class="picker-card">
          <div class="picker-head"><div><h3>é€‰æ‹©çŽ°æœ‰å•†å“</h3><p>å¯é€‰æ‹©ç‹¬ç«‹å•å•†å“ï¼Œä¹Ÿå¯æŠŠå…¶ä»–åˆ†ç±»å•†å“ä¸‹çš„å­å•†å“ç§»åŠ¨åˆ°è¿™é‡Œã€‚</p></div><button type="button" class="picker-close">Ã—</button></div>
          <input id="pickerSearch" type="search" placeholder="æœç´¢å•†å“åç§°æˆ– SKU">
          <div class="picker-grid">
            <?php foreach($availableSingles as $single): ?>
              <button type="button" class="picker-product" data-search="<?= htmlspecialchars(strtolower($single['name'].' '.$single['sku'])) ?>" data-id="<?= (int)$single['id'] ?>" data-name="<?= htmlspecialchars($single['name'],ENT_QUOTES) ?>" data-sku="<?= htmlspecialchars($single['sku'],ENT_QUOTES) ?>" data-price="<?= htmlspecialchars($single['price'],ENT_QUOTES) ?>" data-stock="<?= (int)$single['stock'] ?>" data-image="<?= htmlspecialchars($single['image_url'] ?: 'images/soldout.png',ENT_QUOTES) ?>">
                <img src="/yummy-diary/<?= htmlspecialchars($single['image_url'] ?: 'images/soldout.png') ?>">
                <span><strong><?= htmlspecialchars($single['name']) ?></strong><small><?= htmlspecialchars($single['sku']) ?> Â· RM <?= number_format((float)$single['price'],2) ?> Â· åº“å­˜ <?= (int)$single['stock'] ?></small><?php if($single['parent_name']): ?><small>ç›®å‰å±žäºŽï¼š<?= htmlspecialchars($single['parent_name']) ?></small><?php endif; ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="form-section">
        <h3 class="section-title">å•†å“å›¾ç‰‡</h3>

        <div class="file-upload">
          <input type="file" name="image" accept="image/jpeg,image/png,image/gif">
          <p style="margin:10px 0 0;color:var(--muted);font-size:13px;">
            æ”¯æŒ JPG / PNG / GIFï¼Œæœ€å¤§ 2MBã€‚ä¸Šä¼ æ–°å›¾ç‰‡åŽä¼šæ›¿æ¢æ—§å›¾ç‰‡ã€‚
          </p>
        </div>
      </div>

      <div class="form-section">
        <h3 class="section-title">å±•ç¤ºè®¾ç½®</h3>

        <label class="hot-toggle">
          <input type="checkbox"
                 name="is_hot"
                 value="1"
                 <?= !empty($p['is_hot']) ? 'checked' : '' ?>>
          ðŸ”¥ è®¾ä¸ºçƒ­é”€å•†å“
        </label>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-edit">ðŸ’¾ ä¿å­˜ä¿®æ”¹</button>

        <a href="products.php?cat=<?= urlencode($p['category']) ?>" class="btn btn-move">
          â¬… è¿”å›žå•†å“åˆ—è¡¨
        </a>
      </div>
    </form>
  </div>
</main>
<script>
<?php include __DIR__ . '/assets/js/edit_product.js.php'; ?>
</script>
</body>
</html>

