<?php
// æ–°å¢žå•†å“é¡µï¼šå½•å…¥å•†å“åŸºæœ¬èµ„æ–™ã€ä»·æ ¼ã€å›¾ç‰‡å’Œåº“å­˜/å˜ä½“ä¿¡æ¯ã€‚
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

$stmt = $pdo->query("SELECT g.label group_label,c.category_key,c.name category_name
  FROM category_groups g JOIN product_categories c ON c.group_id=g.id
  WHERE g.status=1 AND c.status=1 ORDER BY g.sort_order,c.sort_order");
$categoryGroups = [];
foreach ($stmt->fetchAll() as $row) {
    $categoryGroups[$row['group_label']][$row['category_key']] = $row['category_name'];
}
$availableSingles = $pdo->query("SELECT p.id,p.sku,p.name,p.price,p.stock,p.image_url,p.parent_product_id,parent.name parent_name
  FROM products p LEFT JOIN products parent ON parent.id=p.parent_product_id
  WHERE p.product_type='single' ORDER BY p.name,p.sku")->fetchAll(PDO::FETCH_ASSOC);

function uploadProductImage(string $field, ?int $index = null): ?string {
    if ($index === null) {
        $error = $_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE;
        $tmp = $_FILES[$field]['tmp_name'] ?? '';
        $size = $_FILES[$field]['size'] ?? 0;
    } else {
        $error = $_FILES[$field]['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        $tmp = $_FILES[$field]['tmp_name'][$index] ?? '';
        $size = $_FILES[$field]['size'][$index] ?? 0;
    }
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('å›¾ç‰‡ä¸Šä¼ å¤±è´¥ï¼Œè¯·é‡æ–°é€‰æ‹©æ–‡ä»¶ã€‚');
    }
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $type = mime_content_type($tmp);
    if (!isset($allowed[$type])) {
        throw new RuntimeException('å›¾ç‰‡æ ¼å¼åªæ”¯æŒ JPGã€PNGã€GIF æˆ– WEBPã€‚');
    }
    if ($size > 2 * 1024 * 1024) {
        throw new RuntimeException('å›¾ç‰‡å¤§å°ä¸èƒ½è¶…è¿‡ 2MBã€‚');
    }
    $dir = __DIR__ . '/../frontend/uploads/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create product upload directory.');
    }
    $name = uniqid('', true) . '.' . $allowed[$type];
    if (!move_uploaded_file($tmp, $dir . $name)) {
        throw new RuntimeException('Unable to save uploaded product image.');
    }
    return 'frontend/uploads/' . $name;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $type = ($_POST['product_type'] ?? 'single') === 'grouped' ? 'grouped' : 'single';
    $price = (float)($_POST['price'] ?? 0);
    $stock = max(0, (int)($_POST['stock'] ?? 0));
    $category = trim($_POST['category'] ?? '');
    if ($type === 'grouped') {
        $firstSourceId = (int)($_POST['source_product_id'][0] ?? 0);
        if ($firstSourceId > 0) {
            $firstSourceStmt = $pdo->prepare("SELECT sku,price FROM products WHERE id=?");
            $firstSourceStmt->execute([$firstSourceId]);
            $firstSource = $firstSourceStmt->fetch();
            $sku = $firstSource['sku'] ?? '';
            $price = (float)($firstSource['price'] ?? 0);
        } else {
            $sku = trim($_POST['variant_sku'][0] ?? '');
            $price = (float)($_POST['variant_price'][0] ?? 0);
        }
        $stock = 0;
    }
    if ($sku === '' || $name === '' || $category === '' || $price < 0) {
        $error = 'è¯·å¡«å†™å®Œæ•´å•†å“èµ„æ–™ã€‚';
    } else {
        try {
            $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM products WHERE category=?');
            $sortStmt->execute([$category]);
            $pdo->beginTransaction();
            $insert = $pdo->prepare("INSERT INTO products
              (sku,name,product_type,variant_flavors,variant_sizes,price,stock,category,image_url,sort_order,is_hot,created_at)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $insert->execute([$sku,$name,$type,null,null,$price,$stock,$category,uploadProductImage('image'),$sortStmt->fetchColumn(),isset($_POST['is_hot'])?1:0]);
            $productId = (int)$pdo->lastInsertId();
            if ($type === 'grouped') {
                $variantNames = $_POST['variant_name'] ?? [];
                if (count($variantNames) === 0) {
                    throw new RuntimeException('åˆ†ç±»å•†å“è‡³å°‘éœ€è¦ä¸€ä¸ªåˆ†ç±»é¡¹ç›®ã€‚');
                }
                $variantStmt = $pdo->prepare('INSERT INTO product_variants
                  (product_id,source_product_id,variant_name,sku,price,stock,image_url,sort_order)
                  VALUES (?,?,?,?,?,?,?,?)');
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
                    $variantSku = trim($_POST['variant_sku'][$index] ?? '') ?: ($source['sku'] ?? '');
                    $variantPrice = isset($_POST['variant_price'][$index]) ? (float)$_POST['variant_price'][$index] : (float)($source['price'] ?? 0);
                    $variantStock = isset($_POST['variant_stock'][$index]) ? max(0, (int)$_POST['variant_stock'][$index]) : (int)($source['stock'] ?? 0);
                    $variantImage = uploadProductImage('variant_image', $index) ?: ($source['image_url'] ?? null);
                    if ($variantSku === '') {
                        throw new RuntimeException('åˆ†ç±»é¡¹ç›® SKU ä¸èƒ½ä¸ºç©ºã€‚');
                    }
                    $variantStmt->execute([
                        $productId,
                        $sourceId ?: null,
                        $variantName,
                        $variantSku,
                        $variantPrice,
                        $variantStock,
                        $variantImage,
                        $index + 1
                    ]);
                    if ($sourceId > 0) {
                        $pdo->prepare('UPDATE products SET name=?,sku=?,price=?,stock=?,image_url=?,parent_product_id=? WHERE id=?')
                            ->execute([$variantName,$variantSku,$variantPrice,$variantStock,$variantImage ?: null,$productId,$sourceId]);
                    }
                }
            }
            $pdo->commit();
            header('Location: products.php?cat=' . urlencode($category) . '&msg=' . urlencode('å•†å“å·²æ·»åŠ '));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'æ–°å¢žå¤±è´¥ï¼š' . $e->getMessage();
        }
    }
}
?>
<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>æ–°å¢žå•†å“</title><link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">
<style>
<?php include __DIR__ . '/assets/css/add_product.css'; ?>
</style></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main>
<section class="page-header"><div class="page-title"><h2>æ–°å¢žå•†å“</h2><p>å»ºç«‹å•å•†å“ï¼Œæˆ–å»ºç«‹æ‹¥æœ‰å¤šä¸ªç‹¬ç«‹åˆ†ç±»é¡¹ç›®çš„åˆ†ç±»å•†å“</p></div></section>
<?php if($error): ?><div class="msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="edit-layout"><aside class="preview-card"><div class="preview-image"><img id="previewImage" src="/yummy-diary/images/soldout.png"></div><h3 id="previewName">å•†å“åç§°</h3><p id="previewSku">SKUï¼š-</p><p id="previewStock">åº“å­˜ï¼š0</p><span id="previewPrice" class="preview-price">RM 0.00</span></aside>
<form class="admin-card" method="post" enctype="multipart/form-data">
<div class="form-section"><h3>å•†å“ç±»åž‹</h3><div class="type-choice"><label><input type="radio" name="product_type" value="single" checked> å•å•†å“</label><label><input type="radio" name="product_type" value="grouped"> åˆ†ç±»å•†å“</label></div></div>
<div class="form-section"><h3>åŸºæœ¬èµ„æ–™</h3><div class="edit-form-grid">
<div class="form-field single-only"><label>SKU</label><input id="sku" name="sku" required></div><div class="form-field"><label>å•†å“åç§°</label><input id="name" name="name" required></div>
<div class="form-field single-only"><label>ä»·æ ¼</label><input id="price" type="number" step=".01" min="0" name="price" required></div><div class="form-field single-only"><label>åº“å­˜</label><input id="stock" type="number" min="0" name="stock" required></div>
<div class="form-field full"><label>å•†åŸŽåˆ†ç±»</label><select name="category" required><?php foreach($categoryGroups as $group=>$items): ?><optgroup label="<?= htmlspecialchars($group) ?>"><?php foreach($items as $key=>$label): ?><option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
</div></div>
<div id="variantFields" class="form-section variant-fields"><div class="variant-head"><div><h3>åˆ†ç±»å•†å“é¡¹ç›®</h3><p>å»ºç«‹æ–°åˆ†ç±»åç§°ï¼Œæˆ–é€‰æ‹©çŽ°æœ‰å•†å“å½’å…¥æ­¤åˆ†ç±»å•†å“ã€‚</p></div><div class="variant-actions"><button type="button" id="addVariant" class="btn btn-edit">ï¼‹ æ·»åŠ æ–°åˆ†ç±»</button><button type="button" id="useExisting" class="btn btn-move">ä½¿ç”¨çŽ°æœ‰å•†å“</button></div></div><div id="variantList"></div></div>
<div class="form-section"><h3>å•†å“å›¾ç‰‡</h3><div class="file-upload"><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></div></div>
<label><input type="checkbox" name="is_hot" value="1"> è®¾ä¸ºçƒ­é”€å•†å“</label>
<div class="form-actions"><button class="btn btn-edit" type="submit">ä¿å­˜å•†å“</button><a class="btn btn-move" href="products.php">è¿”å›žå•†å“åˆ—è¡¨</a></div>
</form></div>
<div id="productPicker" class="product-picker"><div class="picker-card"><div class="picker-head"><div><h3>é€‰æ‹©çŽ°æœ‰å•†å“</h3><p>ç‚¹å‡»å•†å“å¡ç‰‡å³å¯åŠ å…¥ï¼Œå•†å“ä¼šä»ŽåŽŸæ¥çš„åˆ†ç±»å•†å“ç§»åŠ¨åˆ°è¿™é‡Œã€‚</p></div><button type="button" class="picker-close">Ã—</button></div><input id="pickerSearch" type="search" placeholder="æœç´¢åç§°æˆ– SKU"><div class="picker-grid"><?php foreach($availableSingles as $single): ?><button type="button" class="picker-product" data-search="<?= htmlspecialchars(strtolower($single['name'].' '.$single['sku'])) ?>" data-id="<?= (int)$single['id'] ?>" data-name="<?= htmlspecialchars($single['name'],ENT_QUOTES) ?>" data-sku="<?= htmlspecialchars($single['sku'],ENT_QUOTES) ?>" data-price="<?= htmlspecialchars($single['price'],ENT_QUOTES) ?>" data-stock="<?= (int)$single['stock'] ?>"><img src="/yummy-diary/<?= htmlspecialchars($single['image_url'] ?: 'images/soldout.png') ?>"><span><strong><?= htmlspecialchars($single['name']) ?></strong><small><?= htmlspecialchars($single['sku']) ?> Â· RM <?= number_format((float)$single['price'],2) ?> Â· åº“å­˜ <?= (int)$single['stock'] ?></small><?php if($single['parent_name']): ?><small>ç›®å‰å±žäºŽï¼š<?= htmlspecialchars($single['parent_name']) ?></small><?php endif; ?></span></button><?php endforeach; ?></div></div></div>
</main><script>
<?php include __DIR__ . '/assets/js/add_product.js.php'; ?>
</script></body></html>

