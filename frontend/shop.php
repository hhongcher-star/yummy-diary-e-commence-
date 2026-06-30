<?php
// å•†åº—é¡µé¢ï¼šè¯»å–å•†å“ã€åˆ†ç±»å’Œåº“å­˜èµ„æ–™ï¼Œä¾›ç”¨æˆ·æµè§ˆå¹¶åŠ å…¥è´­ç‰©è½¦ã€‚
session_start(); 
require __DIR__ . '/../config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$sortAdmin = isset($_GET['sort_admin']) && $_GET['sort_admin'] === '1';
if ($sortAdmin) {
    require __DIR__ . '/../backend/auth_admin.php';
}

// ====================
// å½“å‰åˆ†ç±»ï¼ˆé»˜è®¤çƒ­é”€ï¼‰
// ====================
$cat = $_GET['cat'] ?? 'hot';

$stmt = $pdo->query("SELECT
    g.group_key,
    g.label AS group_label,
    c.category_key,
    c.name AS category_name
  FROM category_groups g
  JOIN product_categories c ON c.group_id = g.id
  WHERE g.status = 1 AND c.status = 1
  ORDER BY g.sort_order ASC, c.sort_order ASC");

$categories = [
  'hot' => [
    'label' => 'ðŸ”¥ çƒ­é”€é›¶é£Ÿ'
  ]
];

$flatCategories = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $groupKey = $row['group_key'];

    if (!isset($categories[$groupKey])) {
        $categories[$groupKey] = [
            'label' => $row['group_label'],
            'children' => []
        ];
    }

    $categories[$groupKey]['children'][$row['category_key']] = $row['category_name'];

    $flatCategories[$row['category_key']] = [
        'group' => $row['group_label'],
        'name' => $row['category_name']
    ];
}


// ====================
// æŸ¥è¯¢è¯¥åˆ†ç±»ä¸‹æ‰€æœ‰å•†å“
// ====================
if ($cat === 'hot') {
    $stmt = $pdo->query("SELECT * FROM products WHERE is_hot = 1 AND parent_product_id IS NULL ORDER BY hot_order ASC");
} else {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category = ? AND parent_product_id IS NULL ORDER BY sort_order ASC, created_at DESC");
    $stmt->execute([$cat]);
}
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$variantsByProduct = [];
if ($products) {
    $productIds = array_map('intval', array_column($products, 'id'));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $variantStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id IN ($placeholders) ORDER BY sort_order,id");
    $variantStmt->execute($productIds);
    foreach ($variantStmt->fetchAll(PDO::FETCH_ASSOC) as $variant) {
        $variantsByProduct[(int)$variant['product_id']][] = $variant;
    }
}

// ====================
// å¦‚æžœæ˜¯ AJAX è¯·æ±‚ï¼Œåªè¿”å›žå•†å“ HTML
// ====================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    ob_start();
    if ($products) {
        $renderedVariantFamilies = [];
        foreach ($products as $p): ?>
          <?php
            $variantSections = preg_split('/\s*[|ï½œ]\s*/u', (string)$p['name']);
            $variantParts = preg_split('/[Â·â€¢]/u', (string)($variantSections[0] ?? $p['name']));
            $variantFamily = trim((string)($variantParts[0] ?? $p['name']));
            $variantFamily = preg_replace('/\s+(å•åŒ…|ç›’è£…\s*\d+\s*åŒ…|æ— ç›’\s*\d+\s*åŒ…|\d+\s*åŒ…|æ•´ç›’|ç›’è£…)$/u', '', $variantFamily);
            $productType = $p['product_type'] ?? 'single';
            $productVariants = $variantsByProduct[(int)$p['id']] ?? [];
            $displayStock = $productType === 'grouped'
              ? array_sum(array_map(fn($variant) => max(0, (int)$variant['stock']), $productVariants))
              : max(0, (int)$p['stock']);
            $hideVariantCard = false;
          ?>
          <div class="product-card" data-product-id="<?= (int)$p['id'] ?>" data-variant-family="<?= htmlspecialchars($variantFamily, ENT_QUOTES) ?>" <?= $hideVariantCard ? 'hidden' : '' ?> <?= $sortAdmin ? 'draggable="true"' : '' ?>>
            <div class="product-info">
              <?php if ($displayStock <= 0): ?>
                <div class="soldout-tag">SOLD OUT</div>
              <?php endif; ?>
              <img src="/yummy-diary/<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
              <div class="product-text">
                <h4><?= htmlspecialchars($p['name'], ENT_QUOTES) ?></h4>
                <?php if (!empty($p['sku'])): ?>
                  <div class="sku">ç¼–å·ï¼š<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?></div>
                <?php endif; ?>
                <div class="price">RM <?= number_format($p['price'], 2) ?></div>
              </div>
            </div>
            <?php if ($sortAdmin): ?>
              <div class="sort-admin-control">
                <label>æŽ’åº <input type="number" class="sort-position-input" min="1" value="1"></label>
                <button type="button" class="sort-drag-handle" title="æŒ‰ä½æ‹–åŠ¨">â†•</button>
              </div>
            <?php elseif ($displayStock > 0): ?>
              <button class="add-to-cart"
                      data-id="<?= (int)$p['id'] ?>"
                      data-sku="<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?>"
                      data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                      data-price="<?= htmlspecialchars($p['price'], ENT_QUOTES) ?>"
                      data-img="<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>"
                      data-stock="<?= $displayStock ?>"
                      data-product-type="<?= htmlspecialchars($p['product_type'] ?? 'single', ENT_QUOTES) ?>"
                      data-variants="<?= htmlspecialchars(json_encode($productVariants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>">+</button>
            <?php else: ?>
              <button disabled>å”®ç½„</button>
            <?php endif; ?>
          </div>
        <?php endforeach;
    } else {
        echo "<p>è¯¥åˆ†ç±»æš‚æ— å•†å“ã€‚</p>";
    }
    echo ob_get_clean();
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop - Yummy Diary</title>
  <link rel="stylesheet" href="/yummy-diary/css/style.css">

  <style>
<?php include __DIR__ . '/assets/css/shop.css'; ?>
</style>
</head>
<body class="<?= $sortAdmin ? 'sort-admin-mode' : '' ?>">

  <div id="loader">
    <img src="/yummy-diary/images/5" alt="Loading...">
  </div>

  <div id="content">
    <?php include __DIR__ . '/hardware/header.php'; ?>
    <?php if ($sortAdmin): ?>
      <div class="sort-admin-toolbar">
        <div>
          <strong>å•†å“æ˜¾ç¤ºé¡ºåº</strong>
          <span id="sortSaveState" class="sort-save-state">æ‹–åŠ¨å•†å“åŽæŒ‰ä¿å­˜</span>
        </div>
        <div class="sort-admin-actions">
          <button type="button" id="sortResetButton" class="sort-reset-button">è¿˜åŽŸ</button>
          <button type="button" id="sortSaveButton" class="sort-save-button">ä¿å­˜é¡ºåº</button>
        </div>
      </div>
    <?php endif; ?>

    <div class="shop-wrapper">
      <div class="shop-banner">
        <img src="/yummy-diary/images/41" alt="Menu">
      </div>

      <div class="shop-layout">
        <aside class="shop-sidebar">
          <ul>
            <?php foreach ($categories as $groupKey => $group): ?>

              <?php if (!isset($group['children'])): ?>
                <!-- No children (e.g., çƒ­é”€) -->
                <li>
                  <a href="#" 
                     class="cat-link <?= $cat === $groupKey ? 'active' : '' ?>" 
                     data-cat="<?= $groupKey ?>">
                    <?= $group['label'] ?>
                  </a>
                </li>
              <?php else: ?>
                <!-- Has children (original categories) -->
                <li>
                  <span class="parent">
                    <?= $group['label'] ?>
                  </span>
                  <ul class="submenu">
                    <?php foreach ($group['children'] as $key => $label): ?>
                      <li>
                        <a href="#" 
                           class="cat-link <?= $cat === $key ? 'active' : '' ?>" 
                           data-cat="<?= $key ?>">
                          <?= $label ?>
                        </a>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </li>
              <?php endif; ?>

            <?php endforeach; ?>
          </ul>
        </aside>

        <main>
          <h2 id="category-title">
            <?php
              $currentLabel = '';

              if ($cat === 'hot') {
                  $currentLabel = 'ðŸ”¥ çƒ­é”€é›¶é£Ÿ';
              } elseif (isset($flatCategories[$cat])) {
                  $currentLabel = $flatCategories[$cat]['group'] . ' / ' . $flatCategories[$cat]['name'];
              }

              echo $currentLabel ?: 'å•†å“';
            ?>
          </h2>

          <div class="shop-content">
            <?php if ($products): ?>
              <?php $renderedVariantFamilies = []; ?>
              <?php foreach ($products as $p): ?>
                <?php
                  $variantSections = preg_split('/\s*[|ï½œ]\s*/u', (string)$p['name']);
                  $variantParts = preg_split('/[Â·â€¢]/u', (string)($variantSections[0] ?? $p['name']));
                  $variantFamily = trim((string)($variantParts[0] ?? $p['name']));
                  $variantFamily = preg_replace('/\s+(å•åŒ…|ç›’è£…\s*\d+\s*åŒ…|æ— ç›’\s*\d+\s*åŒ…|\d+\s*åŒ…|æ•´ç›’|ç›’è£…)$/u', '', $variantFamily);
                  $productType = $p['product_type'] ?? 'single';
                  $productVariants = $variantsByProduct[(int)$p['id']] ?? [];
                  $displayStock = $productType === 'grouped'
                    ? array_sum(array_map(fn($variant) => max(0, (int)$variant['stock']), $productVariants))
                    : max(0, (int)$p['stock']);
                  $hideVariantCard = false;
                ?>
                <div class="product-card" data-product-id="<?= (int)$p['id'] ?>" data-variant-family="<?= htmlspecialchars($variantFamily, ENT_QUOTES) ?>" <?= $hideVariantCard ? 'hidden' : '' ?> <?= $sortAdmin ? 'draggable="true"' : '' ?>>
                  <div class="product-info">
                    <?php if ($displayStock <= 0): ?>
                      <div class="soldout-tag">SOLD OUT</div>
                    <?php endif; ?>
                    <?php if (!empty($p['image_url'])): ?>
                      <img src="<?= htmlspecialchars(productImageUrl($p['image_url']), ENT_QUOTES) ?>" onerror="this.remove();" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
                    <?php endif; ?>
                    <div class="product-text">
                      <h4><?= htmlspecialchars($p['name'], ENT_QUOTES) ?></h4>
                      <?php if (!empty($p['sku'])): ?>
                        <div class="sku">ç¼–å·ï¼š<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?></div>
                      <?php endif; ?>
                      
                      <div class="price">RM <?= number_format($p['price'], 2) ?></div>
                    </div>
                  </div>

                  <?php if ($sortAdmin): ?>
                    <div class="sort-admin-control">
                      <label>æŽ’åº <input type="number" class="sort-position-input" min="1" value="<?= $p['hot_order'] ?? $p['sort_order'] ?? 1 ?>"></label>
                      <button type="button" class="sort-drag-handle" title="æŒ‰ä½æ‹–åŠ¨">â†•</button>
                    </div>
                  <?php elseif ($displayStock > 0): ?>
                    <button class="add-to-cart"
                            data-id="<?= (int)$p['id'] ?>"
                            data-sku="<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?>"
                            data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                            data-price="<?= htmlspecialchars($p['price'], ENT_QUOTES) ?>"
                            data-img="<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>"
                            data-stock="<?= $displayStock ?>"
                            data-product-type="<?= htmlspecialchars($p['product_type'] ?? 'single', ENT_QUOTES) ?>"
                            data-variants="<?= htmlspecialchars(json_encode($productVariants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>">+
                    </button>
                  <?php else: ?>
                    <button disabled>å”®ç½„</button>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p>è¯¥åˆ†ç±»æš‚æ— å•†å“ã€‚</p>
            <?php endif; ?>
          </div>
        </main>
      </div>
    </div>

    <?php include __DIR__ . '/hardware/footer.php'; ?>
  </div>

  <div id="productImagePreview" class="product-image-preview" role="dialog" aria-modal="true" aria-label="å•†å“å›¾ç‰‡æ”¾å¤§é¢„è§ˆ">
    <button type="button" id="closeProductImagePreview" aria-label="å…³é—­">&times;</button>
    <img id="productImagePreviewImg" src="" alt="">
  </div>

  <!-- Loader åŠ¨ç”»æŽ§åˆ¶ -->
  <script>
<?php include __DIR__ . '/assets/js/shop.js.php'; ?>
</script>
  <?php if ($sortAdmin): ?>
  <script>
<?php include __DIR__ . '/assets/js/shop-2.js.php'; ?>
</script>
  <?php endif; ?>

  <!-- è´­ç‰©è½¦é€»è¾‘å’Œåº“å­˜æ£€æµ‹ -->
  <script>
<?php include __DIR__ . '/assets/js/shop-3.js.php'; ?>
</script>
  <div id="variantModal" class="variant-modal" aria-hidden="true">
    <section class="variant-panel" role="dialog" aria-modal="true" aria-labelledby="variantTitle">
      <img class="variant-decoration" src="/yummy-diary/images/yummy.png" alt="" aria-hidden="true">
      <button type="button" class="variant-close" aria-label="å…³é—­">Ã—</button>
      <div class="variant-product">
        <img id="variantImage" src="" alt="">
        <div>
          <h3 id="variantTitle"></h3>
          <p id="variantSku"></p>
          <p id="variantStock"></p>
          <p id="variantPrice" class="variant-price"></p>
        </div>
      </div>
      <div class="variant-section">
        <h4>åˆ†ç±»é€‰æ‹©</h4>
        <div id="flavorOptions" class="variant-options"></div>
        <div id="variantPagination" class="variant-pagination" aria-label="åˆ†ç±»åˆ†é¡µ">
          <button type="button" id="variantPrevPage" class="variant-page-button" aria-label="ä¸Šä¸€é¡µ">â€¹</button>
          <span id="variantPageStatus" class="variant-page-status"></span>
          <button type="button" id="variantNextPage" class="variant-page-button" aria-label="ä¸‹ä¸€é¡µ">â€º</button>
        </div>
      </div>
      <div class="variant-section">
        <h4>æ•°é‡</h4>
        <div class="variant-quantity">
          <button type="button" id="variantMinus">âˆ’</button>
          <strong id="variantQty">1</strong>
          <button type="button" id="variantPlus">+</button>
        </div>
      </div>
      <button type="button" id="variantAdd" class="variant-add">åŠ å…¥è´­ç‰©è¢‹</button>
    </section>
  </div>
  <script>
<?php include __DIR__ . '/assets/js/shop-4.js.php'; ?>
</script>
</body>
</html>


