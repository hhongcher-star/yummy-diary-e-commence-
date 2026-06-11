<?php
session_start();
require __DIR__ . '/../../config.php';

$q = trim($_GET['q'] ?? '');
$products = [];

if ($q !== '') {
    // 支持多关键词模糊查询 (空格分隔)
    $keywords = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);

    $conditions = [];
    $params = [];
    foreach ($keywords as $kw) {
        $conditions[] = "(name LIKE ? OR sku LIKE ? OR pinyin LIKE ?)";
        $params[] = "%{$kw}%";
        $params[] = "%{$kw}%";
        $params[] = "%{$kw}%";
    }

    $sql = "SELECT * FROM products";
    if ($conditions) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>搜索结果 - <?= htmlspecialchars($q, ENT_QUOTES) ?> | Yummy Diary</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/yummy-diary/css/style.css">
<style>
.search-wrapper { max-width: 1000px; margin: 20px auto; padding: 15px; }
.search-wrapper h2 { margin-bottom: 15px; font-size: 1.2rem; }
.search-results { display: flex; flex-direction: column; gap: 12px; }
.product-card { display: flex; justify-content: space-between; align-items: center; border:1px solid #eee; padding:10px; border-radius:6px; background:#fff; }
.product-info { display:flex; align-items:center; gap:10px; position:relative; }
.product-info img { width:60px; height:60px; object-fit:cover; border-radius:6px; border:1px solid #eee; }

/* 红色 Sold Out 标签 */
.soldout-tag {
  position: absolute;
  top: -6px; left: -6px;
  background: #e60000;
  color: #fff;
  font-size: 11px;
  font-weight: bold;
  padding: 3px 6px;
  border-radius: 4px;
  transform: rotate(-10deg);
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

.product-text h4 { margin:0; font-size:0.95rem; color:#333; }
.product-text p { margin:3px 0 0; font-size:0.8rem; color:#888; }
.product-text .price { margin-top:4px; font-weight:bold; }
.product-card button {
  border:1px solid #000; background:#fff;
  width:50px; height:30px; border-radius:6px;
  cursor:pointer; font-size:14px; transition:0.2s;
}
.product-card button:disabled { border-color:#aaa; color:#aaa; cursor:not-allowed; background:#f1f1f1; }
.product-card button:hover:not(:disabled) { background:#000; color:#fff; }
</style>
</head>
<body>

<?php include __DIR__ . '/../hardware/header.php'; ?>

<div class="search-wrapper">
  <h2>🔍 搜索结果：<?= htmlspecialchars($q, ENT_QUOTES) ?></h2>
  <div class="search-results">
    <?php if ($products): ?>
      <?php foreach ($products as $p): ?>
        <div class="product-card">
          <div class="product-info">
            <?php if ($p['stock'] <= 0): ?>
              <div class="soldout-tag">SOLD OUT</div>
            <?php endif; ?>
            <img src="/yummy-diary/<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
            <div class="product-text">
              <h4>[<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?>] <?= htmlspecialchars($p['name'], ENT_QUOTES) ?></h4>
              <p>库存：<?= (int)$p['stock'] ?></p>
              <div class="price">RM <?= number_format($p['price'],2) ?></div>
            </div>
          </div>

          <?php if ($p['stock'] > 0): ?>
            <button class="add-to-cart"
                    data-id="<?= (int)$p['id'] ?>"
                    data-sku="<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?>"
                    data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                    data-price="<?= htmlspecialchars($p['price'], ENT_QUOTES) ?>"
                    data-img="<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>"
                    data-stock="<?= (int)$p['stock'] ?>">
              +
            </button>
          <?php else: ?>
            <button disabled>售罄</button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center; padding:20px;">
        <p>❌ 没有找到相关商品。</p>
        <a href="shop.php" 
           style="display:inline-block; margin-top:10px; padding:8px 14px; background:#000; color:#fff; border-radius:6px; text-decoration:none;">
          返回商店 🛒
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../hardware/footer.php'; ?>

<script>
// ✅ 复用购物车逻辑（footer 里的 updateCartUI）+ 加库存检测
document.addEventListener("DOMContentLoaded", () => {
  document.body.addEventListener("click", function(e) {
    if (e.target.classList.contains("add-to-cart")) {
      e.preventDefault();
      const btn = e.target;

      const stock = parseInt(btn.dataset.stock, 10);
      const sku   = btn.dataset.sku;

      let currentQty = 0;
      if (window.cartData && window.cartData.cart) {
        const found = window.cartData.cart.find(item => item.sku === sku);
        if (found) currentQty = found.qty;
      }
      if (currentQty >= stock) {
        alert("⚠️ 已达到库存上限，不能再添加了！");
        btn.disabled = true;
        btn.textContent = "售罄";
        return;
      }

      const formData = new FormData();
      formData.append("id", btn.dataset.id);
      formData.append("sku", btn.dataset.sku);
      formData.append("name", btn.dataset.name);
      formData.append("price", btn.dataset.price);
      formData.append("img", btn.dataset.img);

      fetch("add_to_cart.php", { method:"POST", body:formData })
        .then(res=>res.json())
        .then(data => {
          if(data.success && typeof updateCartUI === "function") {
            window.cartData = data;
            updateCartUI(data);
            if (data.cart.some(item => item.sku === sku && item.qty >= stock)) {
              btn.disabled = true;
              btn.textContent = "售罄";
            }
          }
        });
    }
  });
});
</script>
</body>
</html>

