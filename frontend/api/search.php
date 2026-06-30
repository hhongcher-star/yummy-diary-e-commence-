<?php
// æœç´¢é¡µé¢/APIï¼šæ ¹æ®å…³é”®è¯æŸ¥è¯¢å•†å“å¹¶è¾“å‡ºæœç´¢ç»“æžœã€‚
session_start();
require __DIR__ . '/../../config.php';

$q = trim($_GET['q'] ?? '');
$products = [];

if ($q !== '') {
    // æ”¯æŒå¤šå…³é”®è¯æ¨¡ç³ŠæŸ¥è¯¢ (ç©ºæ ¼åˆ†éš”)
    $keywords = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);

    $conditions = [];
    $params = [];
    foreach ($keywords as $kw) {
        $conditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.pinyin LIKE ?
            OR EXISTS (
                SELECT 1 FROM product_variants v
                WHERE v.product_id=p.id AND (v.variant_name LIKE ? OR v.sku LIKE ?)
            ))";
        $params[] = "%{$kw}%";
        $params[] = "%{$kw}%";
        $params[] = "%{$kw}%";
        $params[] = "%{$kw}%";
        $params[] = "%{$kw}%";
    }

    $sql = "SELECT p.*,
              CASE WHEN p.product_type='grouped'
                THEN COALESCE((SELECT SUM(v.stock) FROM product_variants v WHERE v.product_id=p.id),0)
                ELSE p.stock
              END AS display_stock,
              CASE WHEN p.product_type='grouped'
                THEN COALESCE((SELECT MIN(v.price) FROM product_variants v WHERE v.product_id=p.id),p.price)
                ELSE p.price
              END AS display_price
            FROM products p
            WHERE p.parent_product_id IS NULL";
    if ($conditions) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>æœç´¢ç»“æžœ - <?= htmlspecialchars($q, ENT_QUOTES) ?> | Yummy Diary</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/yummy-diary/css/style.css">
<style>
<?php include __DIR__ . '/../assets/css/api-search.css'; ?>
</style>
</head>
<body>

<?php include __DIR__ . '/../hardware/header.php'; ?>

<div class="search-wrapper">
  <h2>ðŸ” æœç´¢ç»“æžœï¼š<?= htmlspecialchars($q, ENT_QUOTES) ?></h2>
  <div class="search-results">
    <?php if ($products): ?>
      <?php foreach ($products as $p): ?>
        <div class="product-card">
          <div class="product-info">
            <?php if ((int)$p['display_stock'] <= 0): ?>
              <div class="soldout-tag">SOLD OUT</div>
            <?php endif; ?>
            <img src="/yummy-diary/<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
            <div class="product-text">
              <h4>[<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?>] <?= htmlspecialchars($p['name'], ENT_QUOTES) ?></h4>
              <p>åº“å­˜ï¼š<?= (int)$p['display_stock'] ?></p>
              <div class="price">RM <?= number_format((float)$p['display_price'],2) ?></div>
            </div>
          </div>

          <?php if ((int)$p['display_stock'] > 0 && ($p['product_type'] ?? 'single') === 'single'): ?>
            <button class="add-to-cart"
                    data-id="<?= (int)$p['id'] ?>"
                    data-sku="<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?>"
                    data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                    data-price="<?= htmlspecialchars($p['price'], ENT_QUOTES) ?>"
                    data-img="<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>"
                    data-stock="<?= (int)$p['display_stock'] ?>">
              +
            </button>
          <?php elseif ((int)$p['display_stock'] > 0): ?>
            <a href="<?= htmlspecialchars(appUrl('shop') . '?cat=' . rawurlencode($p['category']), ENT_QUOTES) ?>"
               style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:32px;border:1px solid #000;border-radius:6px;color:#000;text-decoration:none;">
              æŸ¥çœ‹
            </a>
          <?php else: ?>
            <button disabled>å”®ç½„</button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center; padding:20px;">
        <p>âŒ æ²¡æœ‰æ‰¾åˆ°ç›¸å…³å•†å“ã€‚</p>
        <a href="<?= htmlspecialchars(appUrl('shop'), ENT_QUOTES) ?>"
           style="display:inline-block; margin-top:10px; padding:8px 14px; background:#000; color:#fff; border-radius:6px; text-decoration:none;">
          è¿”å›žå•†åº— ðŸ›’
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../hardware/footer.php'; ?>

<script>
<?php include __DIR__ . '/../assets/js/api-search.js.php'; ?>
</script>
</body>
</html>


