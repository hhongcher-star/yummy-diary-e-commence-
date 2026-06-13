<?php
session_start(); 
require __DIR__ . '/../config.php';
$sortAdmin = isset($_GET['sort_admin']) && $_GET['sort_admin'] === '1';
if ($sortAdmin) {
    require __DIR__ . '/../backend/auth_admin.php';
}

// ====================
// 当前分类（默认热销）
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
    'label' => '🔥 热销零食'
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
// 查询该分类下所有商品
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
// 如果是 AJAX 请求，只返回商品 HTML
// ====================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    ob_start();
    if ($products) {
        $renderedVariantFamilies = [];
        foreach ($products as $p): ?>
          <?php
            $variantSections = preg_split('/\s*[|｜]\s*/u', (string)$p['name']);
            $variantParts = preg_split('/[·•]/u', (string)($variantSections[0] ?? $p['name']));
            $variantFamily = trim((string)($variantParts[0] ?? $p['name']));
            $variantFamily = preg_replace('/\s+(单包|盒装\s*\d+\s*包|无盒\s*\d+\s*包|\d+\s*包|整盒|盒装)$/u', '', $variantFamily);
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
                  <div class="sku">编号：<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?></div>
                <?php endif; ?>
                <div class="price">RM <?= number_format($p['price'], 2) ?></div>
              </div>
            </div>
            <?php if ($sortAdmin): ?>
              <div class="sort-admin-control">
                <label>排序 <input type="number" class="sort-position-input" min="1" value="1"></label>
                <button type="button" class="sort-drag-handle" title="按住拖动">↕</button>
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
              <button disabled>售罄</button>
            <?php endif; ?>
          </div>
        <?php endforeach;
    } else {
        echo "<p>该分类暂无商品。</p>";
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
    /* ========= Loader 动画 ========= */
    #loader {
      position: fixed;top: 0;left: 0;width: 100vw;height: 100vh;
      background: #fff;display: flex;justify-content: center;align-items: center;
      z-index: 2000;transition: opacity 0.6s ease, visibility 0.6s ease;
    }
    #loader.fade-out {opacity: 0;visibility: hidden;}
    #loader img {width: 150px;animation: wag 1.4s infinite ease-in-out;}
    @keyframes wag {
      0%,100%{transform:rotate(0deg);}
      25%{transform:rotate(2deg);}
      75%{transform:rotate(-2deg);}
    }
    #content {opacity: 0;transition: opacity 1s ease;}
    #content.show {opacity: 1;}

    /* ========= Shop 样式 ========= */
    .shop-wrapper { max-width: 1200px; margin: 0 auto; padding: 15px; background: #fff; }
    .shop-banner { display: flex; justify-content: center; margin-bottom: 15px; }
    .shop-banner img { max-width: 200px; height: auto; }

    .shop-layout {
      display: grid;
      grid-template-columns: 180px 1fr;
      gap: 14px;
      height: calc(100vh - 180px);
      overflow: hidden;
    }
    .shop-sidebar {
      position: sticky;
      top: 180px;
      align-self: flex-start;
      max-height: calc(100vh - 180px);
      overflow-y: auto;
      overflow-x: hidden;
      padding-right: 6px;
      -webkit-overflow-scrolling: touch;
    }
    .shop-sidebar ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
    .shop-sidebar a { display: block; padding: 6px 8px; font-size: 0.85rem; text-decoration: none; color: #333; border-left: 3px solid transparent; transition: 0.2s; }
    .shop-sidebar a.active { border-left: 3px solid #000; font-weight: bold; background: #f7f7f7; }
    .shop-sidebar { position: sticky;top: 180px;align-self:flex-start;}

    /* ✅ 子菜单（常驻显示） */
    .shop-sidebar .submenu {
      display: block;
      margin-left: 10px;
      padding-left: 5px;
      border-left: 2px solid #eee;
    }
    .submenu a { font-size: 0.8rem; }


    .shop-sidebar .parent { 
      font-weight: bold; padding: 6px 8px;
      background: #fafafa; display: flex; justify-content: space-between; 
      align-items: center; user-select: none;
    }
    .shop-sidebar .arrow { display: none; }
    .shop-sidebar > ul::after {
  content: "";
  display: block;
  height: 120px;
  flex: 0 0 120px;
}

    .shop-content {
      display: flex;
      flex-direction: column;
      gap: 16px;
      max-height: calc(100vh - 180px);
      overflow-y: auto;
      padding-right: 8px;
    }
    .product-card { display: flex; align-items: flex-start; justify-content: space-between; padding: 18px; border: 1px solid #eee; border-radius: 10px; background: #fff; }
    .product-info { display: flex; align-items: center; gap: 14px; position: relative; }
    .product-info img { width: 90px; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid #eee; }

    .soldout-tag {
      position: absolute; top: -6px; left: -6px;
      background: #e60000; color: #fff;
      font-size: 12px; font-weight: bold;
      padding: 4px 7px; border-radius: 4px;
      transform: rotate(-10deg);
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .product-text h4 { font-size: 1.1rem; margin: 0; color: #333; }
    .product-text .sku { font-size: 0.9rem; color: #666; margin-top: 2px; }
    .product-text .price { font-weight: bold; color: #000; margin-top: 4px; font-size: 1.05rem; }
    .product-text p { margin: 0; font-size: 0.85rem; color: #888; }

    .product-card button {
      border: 1px solid #000;background: #fff;color: #000;
      width: 65px; height: 38px;border-radius: 8px;
      font-size: 16px;cursor: pointer;transition: 0.2s;
      align-self: flex-end; margin-top: 10px;
    }
    .product-card button:hover:not(:disabled) { background: #000; color: #fff; }
    .product-card button:disabled { border-color:#aaa; color:#aaa; cursor:not-allowed; background:#f1f1f1; }
    .variant-modal{position:fixed;inset:0;z-index:2600;display:none;background:rgba(28,24,22,.38);backdrop-filter:blur(3px);}
    .variant-modal.show{display:block;}
    .variant-panel{position:absolute;right:0;top:0;width:min(430px,92vw);height:100%;background:#fff;padding:72px 24px 24px;box-sizing:border-box;overflow-y:auto;box-shadow:-18px 0 55px rgba(45,35,30,.18);animation:variantSlide .24s ease;}
    @keyframes variantSlide{from{transform:translateX(100%)}to{transform:translateX(0)}}
    .variant-decoration{position:absolute;top:0;left:50%;width:120px;height:120px;object-fit:contain;pointer-events:none;transform-origin:center bottom;animation:variantBreathe 2.2s ease-in-out infinite;}
    @keyframes variantBreathe{
      0%,100%{transform:translateX(-50%) translateY(0) scale(1);}
      50%{transform:translateX(-50%) translateY(-7px) scale(1.06);}
    }
    .variant-close{position:absolute;right:18px;top:15px;border:0;width:38px;height:38px;border-radius:50%;background:#f7f3ef;font-size:22px;cursor:pointer;}
    .variant-product{display:grid;grid-template-columns:86px 1fr;gap:15px;align-items:center;padding:22px 0 18px;border-bottom:1px solid #eee;}
    .variant-product img{width:86px;height:86px;object-fit:cover;border-radius:15px;border:1px solid #eee;}
    .variant-product h3{margin:0 35px 6px 0;font-size:18px;line-height:1.35;}
    .variant-product p{margin:3px 0;color:#777;font-size:13px;}
    .variant-price{font-weight:900;color:#111!important;font-size:18px!important;}
    .variant-section{padding:18px 0;border-bottom:1px solid #eee;}
    .variant-section h4{margin:0 0 11px;font-size:14px;}
    .variant-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;}
    .variant-option{min-height:44px;padding:10px 12px;border:1px solid #ddd;border-radius:12px;background:#fff;color:#333;cursor:pointer;}
    .variant-option.selected{border-color:#111;box-shadow:inset 0 0 0 1px #111;font-weight:800;}
    .variant-quantity{display:flex;align-items:center;justify-content:space-between;border:1px solid #ddd;border-radius:14px;padding:5px 8px;}
    .variant-quantity button{width:42px;height:36px;border:0;background:#fff;font-size:20px;cursor:pointer;}
    .variant-add{position:sticky;bottom:0;width:100%;height:52px;margin-top:22px;border:0;border-radius:15px;background:#111;color:#fff;font-weight:900;font-size:15px;cursor:pointer;box-shadow:0 -10px 30px #fff;}
    .variant-add:disabled{background:#aaa;}
    body.sort-admin-mode .product-card{cursor:grab;}
    body.sort-admin-mode .product-card.dragging{opacity:.4;}
    body.sort-admin-mode .product-card.drag-target{outline:3px solid #333;}
    body.sort-admin-mode .sort-drag-handle{font-size:22px;font-weight:900;cursor:grab;}
    .sort-admin-control{display:flex;align-items:center;gap:9px;flex:0 0 auto;}
    .sort-admin-control label{display:flex;align-items:center;gap:6px;color:#555;font-size:13px;font-weight:700;}
    .sort-position-input{width:64px!important;height:40px!important;padding:6px!important;border:1px solid #aaa!important;border-radius:8px!important;text-align:center;font-weight:800;}
    .sort-admin-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 18px;background:#fff;border-bottom:1px solid #ddd;position:sticky;top:0;z-index:1500;}
    .sort-admin-toolbar strong{color:#222;}
    .sort-admin-actions{display:flex;gap:9px;align-items:center;}
    .sort-admin-actions button{width:auto;height:auto;margin:0;padding:10px 16px;border-radius:10px;font-weight:800;}
    .sort-save-button{background:#222!important;color:#fff!important;}
    .sort-reset-button{background:#fff!important;color:#222!important;}
    .sort-save-state{font-size:13px;color:#666;font-weight:700;}
    .sort-save-state.dirty{color:#b36b00;}
    .sort-save-state.success{color:#267a43;}
@media (max-width: 768px) {
  .variant-panel{top:auto;bottom:0;width:100%;height:auto;max-height:88vh;padding-top:58px;border-radius:24px 24px 0 0;overflow:visible;animation:variantRise .24s ease;}
  .variant-decoration{top:-78px;width:150px;height:150px;}
  @keyframes variantRise{from{transform:translateY(100%)}to{transform:translateY(0)}}
  .shop-layout {
    grid-template-columns: 120px 1fr;
    gap: 10px;

    height: auto;
    min-height: calc(100vh - 220px);
    overflow: visible;
  }

  .shop-sidebar {
    font-size: 0.8rem;
  }

  .shop-sidebar a {
    font-size: 0.75rem;
    padding: 4px 6px;
  }

  .shop-content {
    max-height: none;
    overflow-y: visible;
    padding-bottom: 180px;
  }

  main {
    min-width: 0;
  }

  .product-card {
    flex-direction: column;
    align-items: flex-start;
  }

  .product-info {
    flex-direction: row;
    gap: 10px;
  }

  .product-info img {
    width: 70px;
    height: 70px;
  }

  .product-text h4 {
    font-size: 0.9rem;
  }

  .product-text .price {
    font-size: 0.9rem;
  }

  .product-card button {
    align-self: flex-end;
    margin-top: 8px;
    width: 55px;
    height: 32px;
    font-size: 14px;
    margin-right: 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .variant-decoration{animation:none;transform:translateX(-50%);}
}

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
          <strong>商品显示顺序</strong>
          <span id="sortSaveState" class="sort-save-state">拖动商品后按保存</span>
        </div>
        <div class="sort-admin-actions">
          <button type="button" id="sortResetButton" class="sort-reset-button">还原</button>
          <button type="button" id="sortSaveButton" class="sort-save-button">保存顺序</button>
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
                <!-- No children (e.g., 热销) -->
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
                  $currentLabel = '🔥 热销零食';
              } elseif (isset($flatCategories[$cat])) {
                  $currentLabel = $flatCategories[$cat]['group'] . ' / ' . $flatCategories[$cat]['name'];
              }

              echo $currentLabel ?: '商品';
            ?>
          </h2>

          <div class="shop-content">
            <?php if ($products): ?>
              <?php $renderedVariantFamilies = []; ?>
              <?php foreach ($products as $p): ?>
                <?php
                  $variantSections = preg_split('/\s*[|｜]\s*/u', (string)$p['name']);
                  $variantParts = preg_split('/[·•]/u', (string)($variantSections[0] ?? $p['name']));
                  $variantFamily = trim((string)($variantParts[0] ?? $p['name']));
                  $variantFamily = preg_replace('/\s+(单包|盒装\s*\d+\s*包|无盒\s*\d+\s*包|\d+\s*包|整盒|盒装)$/u', '', $variantFamily);
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
                        <div class="sku">编号：<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?></div>
                      <?php endif; ?>
                      
                      <div class="price">RM <?= number_format($p['price'], 2) ?></div>
                    </div>
                  </div>

                  <?php if ($sortAdmin): ?>
                    <div class="sort-admin-control">
                      <label>排序 <input type="number" class="sort-position-input" min="1" value="<?= $p['hot_order'] ?? $p['sort_order'] ?? 1 ?>"></label>
                      <button type="button" class="sort-drag-handle" title="按住拖动">↕</button>
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
                    <button disabled>售罄</button>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p>该分类暂无商品。</p>
            <?php endif; ?>
          </div>
        </main>
      </div>
    </div>

    <?php include __DIR__ . '/hardware/footer.php'; ?>
  </div>

  <!-- Loader 动画控制 -->
  <script>
    window.addEventListener("load", () => {
      const loader = document.getElementById("loader");
      const content = document.getElementById("content");
      setTimeout(() => {
        loader.classList.add("fade-out");
        setTimeout(() => {
          loader.style.display = "none";
          content.classList.add("show");
        }, 600);
      }, 2000); 
    });

    document.addEventListener("DOMContentLoaded", () => {
      // ✅ AJAX 分类切换
      document.querySelectorAll(".cat-link").forEach(link => {
        link.addEventListener("click", e => {
          e.preventDefault();
          const cat = link.dataset.cat;
          if (window.sortOrderDirty && !confirm('当前排序还没有保存，确定放弃修改并切换分类吗？')) {
            return;
          }

          // 更新 active
          document.querySelectorAll(".cat-link").forEach(a => a.classList.remove("active"));
          link.classList.add("active");

          // 更新标题
          document.getElementById("category-title").textContent = link.textContent;

          // AJAX 拉商品
          fetch(`/shop?cat=${encodeURIComponent(cat)}&ajax=1<?= $sortAdmin ? '&sort_admin=1' : '' ?>`)
            .then(res => res.text())
            .then(html => {
              document.querySelector(".shop-content").innerHTML = html;
              window.sortOrderDirty = false;
              window.captureSortOrder?.();
              window.refreshSortNumbers?.();
              window.updateSortSaveState?.('拖动商品后按保存', '');
              if (window.enableAdminSorting) window.enableAdminSorting();
            });
        });
      });
    });
  </script>
  <?php if ($sortAdmin): ?>
  <script>
    window.sortOrderDirty = false;
    window.originalSortOrder = [];

    window.updateSortSaveState = function (text, className) {
      const state = document.getElementById('sortSaveState');
      if (!state) return;
      state.textContent = text;
      state.className = `sort-save-state ${className || ''}`;
    };

    window.captureSortOrder = function () {
      const container = document.querySelector('.shop-content');
      window.originalSortOrder = Array.from(container.querySelectorAll('.product-card')).map(card => card.dataset.productId);
    };

    window.refreshSortNumbers = function () {
      const cards = Array.from(document.querySelectorAll('.shop-content .product-card'));
      cards.forEach((card, index) => {
        const input = card.querySelector('.sort-position-input');
        if (input) {
          input.value = index + 1;
          input.max = cards.length;
        }
      });
    };

    window.enableAdminSorting = function () {
      const container = document.querySelector('.shop-content');
      if (!container || container.dataset.sortReady === '1') return;
      container.dataset.sortReady = '1';
      let dragged = null;

      container.addEventListener('dragstart', event => {
        const card = event.target.closest('.product-card');
        if (!card) return;
        dragged = card;
        card.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
      });

      container.addEventListener('dragover', event => {
        event.preventDefault();
        const card = event.target.closest('.product-card');
        if (!card || card === dragged) return;
        container.querySelectorAll('.drag-target').forEach(item => item.classList.remove('drag-target'));
        card.classList.add('drag-target');
        const box = card.getBoundingClientRect();
        container.insertBefore(dragged, event.clientY > box.top + box.height / 2 ? card.nextSibling : card);
      });

      container.addEventListener('dragend', () => {
        if (!dragged) return;
        dragged.classList.remove('dragging');
        container.querySelectorAll('.drag-target').forEach(item => item.classList.remove('drag-target'));
        dragged = null;
        window.sortOrderDirty = true;
        window.refreshSortNumbers();
        window.updateSortSaveState('有未保存的排序修改', 'dirty');
      });

      container.addEventListener('change', event => {
        const input = event.target.closest('.sort-position-input');
        if (!input) return;
        const card = input.closest('.product-card');
        const cards = Array.from(container.querySelectorAll('.product-card'));
        const targetPosition = Math.max(1, Math.min(cards.length, Number(input.value) || 1));
        const remainingCards = cards.filter(item => item !== card);
        const referenceCard = remainingCards[targetPosition - 1] || null;

        if (referenceCard) {
          container.insertBefore(card, referenceCard);
        } else {
          container.appendChild(card);
        }

        window.sortOrderDirty = true;
        window.refreshSortNumbers();
        window.updateSortSaveState('有未保存的排序修改', 'dirty');
      });
    };

    document.getElementById('sortSaveButton').addEventListener('click', async () => {
      const container = document.querySelector('.shop-content');
      const activeCategory = document.querySelector('.cat-link.active')?.dataset.cat || '';
      const ids = Array.from(container.querySelectorAll('.product-card')).map(card => Number(card.dataset.productId));
      if (!activeCategory) return;

      window.updateSortSaveState('保存中...', '');
      const data = new FormData();
      data.append('save_order', '1');
      data.append('category', activeCategory);
      data.append('ordered_ids', JSON.stringify(ids));

      try {
        const response = await fetch('/yummy-diary/backend/product_sort.php', {method:'POST', body:data});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || '保存失败');
        window.sortOrderDirty = false;
        window.captureSortOrder();
        window.updateSortSaveState('顺序已保存', 'success');
      } catch (error) {
        window.updateSortSaveState(error.message || '保存失败', 'dirty');
      }
    });

    document.getElementById('sortResetButton').addEventListener('click', () => {
      const container = document.querySelector('.shop-content');
      window.originalSortOrder.forEach(id => {
        const card = container.querySelector(`.product-card[data-product-id="${id}"]`);
        if (card) container.appendChild(card);
      });
      window.sortOrderDirty = false;
      window.refreshSortNumbers();
      window.updateSortSaveState('已还原到上次保存顺序', '');
    });

    window.addEventListener('beforeunload', event => {
      if (!window.sortOrderDirty) return;
      event.preventDefault();
      event.returnValue = '';
    });

    window.captureSortOrder();
    window.refreshSortNumbers();
    window.enableAdminSorting();
  </script>
  <?php endif; ?>

  <!-- 购物车逻辑和库存检测 -->
  <script>
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

        fetch("api/add_to_cart.php", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
          if (data.success && typeof updateCartUI === "function") {
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
  <div id="variantModal" class="variant-modal" aria-hidden="true">
    <section class="variant-panel" role="dialog" aria-modal="true" aria-labelledby="variantTitle">
      <img class="variant-decoration" src="/yummy-diary/images/yummy.png" alt="" aria-hidden="true">
      <button type="button" class="variant-close" aria-label="关闭">×</button>
      <div class="variant-product">
        <img id="variantImage" src="" alt="">
        <div>
          <h3 id="variantTitle"></h3>
          <p id="variantSku"></p>
          <p id="variantStock"></p>
          <p id="variantPrice" class="variant-price"></p>
        </div>
      </div>
      <div class="variant-section"><h4>分类选择</h4><div id="flavorOptions" class="variant-options"></div></div>
      <div class="variant-section">
        <h4>数量</h4>
        <div class="variant-quantity">
          <button type="button" id="variantMinus">−</button>
          <strong id="variantQty">1</strong>
          <button type="button" id="variantPlus">+</button>
        </div>
      </div>
      <button type="button" id="variantAdd" class="variant-add">加入购物袋</button>
    </section>
  </div>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("variantModal");
    const flavorBox = document.getElementById("flavorOptions");
    const qtyText = document.getElementById("variantQty");
    const addButton = document.getElementById("variantAdd");
    let products = [], selected = null, flavor = "", qty = 1;

    function parse(button) {
      if (button.dataset.productType === "grouped") {
        let variants = [];
        try { variants = JSON.parse(button.dataset.variants || "[]"); } catch (error) {}
        return {
          button,
          family: button.dataset.sku,
          flavor: variants[0]?.variant_name || "默认",
          variants
        };
      }
      const sections = button.dataset.name.trim().split(/\s*[|｜]\s*/);
      const parts = sections[0].split(/[·•]/);
      let family = (parts[0] || sections[0]).trim();
      let detectedSize = "";
      const sizeMatch = family.match(/\s+(单包|盒装\s*\d+\s*包|无盒\s*\d+\s*包|\d+\s*包|整盒|盒装)$/u);
      if (sizeMatch) {
        detectedSize = sizeMatch[1].replace(/\s+/g, "");
        family = family.slice(0, sizeMatch.index).trim();
      }
      return {button, family, flavor:(parts.slice(1).join("·") || "原味").trim(), size:(sections.slice(1).join("｜") || detectedSize || "默认规格").trim()};
    }
    function details() {
      if (!selected) return;
      const button = selected.button;
      const variant = selected.variant;
      const sku = variant?.sku || button.dataset.sku;
      const stock = Number(variant?.stock ?? button.dataset.stock);
      const price = Number(variant?.price ?? button.dataset.price);
      const imageUrl = variant?.image_url || button.dataset.img;
      const inCart = window.cartData?.cart?.find(item => item.sku === sku)?.qty || 0;
      const available = Math.max(0, stock - inCart);
      qty = Math.max(1, Math.min(qty, available || 1));
      qtyText.textContent = qty;
      document.getElementById("variantTitle").textContent = variant ? `${button.dataset.name} · ${variant.variant_name}` : button.dataset.name;
      document.getElementById("variantSku").textContent = "编号：" + sku;
      document.getElementById("variantStock").textContent = "库存：" + stock;
      document.getElementById("variantPrice").textContent = "RM " + price.toFixed(2);
      document.getElementById("variantImage").src = "/yummy-diary/" + imageUrl;
      addButton.disabled = available <= 0;
      addButton.textContent = available > 0 ? `加入购物袋 · RM ${(price * qty).toFixed(2)}` : "已达到库存上限";
    }
    function render() {
      const configured = products[0]?.button.dataset.productType === "grouped";
      const flavors = configured ? products[0].variants.map(item => item.variant_name) : [products[0].flavor];
      flavorBox.innerHTML = flavors.map(value => `<button type="button" class="variant-option${value === flavor ? " selected" : ""}" data-flavor="${escapeHtml(value)}">${escapeHtml(value)}</button>`).join("");
      selected = products[0];
      selected.variant = configured ? selected.variants.find(item => item.variant_name === flavor) : null;
      details();
    }
    function open(button) {
      const current = parse(button);
      products = button.dataset.productType === "grouped"
        ? [current]
        : [current];
      flavor = current.flavor; qty = 1;
      render();
      document.getElementById("flavorOptions").closest(".variant-section").style.display = button.dataset.productType === "grouped" ? "" : "none";
      modal.classList.add("show");
      document.body.style.overflow = "hidden";
    }
    function close() {
      modal.classList.remove("show");
      document.body.style.overflow = "";
    }

    document.addEventListener("click", event => {
      const button = event.target.closest(".add-to-cart");
      if (!button) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      open(button);
    }, true);
    flavorBox.addEventListener("click", event => {
      const option = event.target.closest("[data-flavor]");
      if (!option) return;
      flavor = option.dataset.flavor;
      render();
    });
    document.getElementById("variantMinus").onclick = () => { qty = Math.max(1, qty - 1); details(); };
    document.getElementById("variantPlus").onclick = () => {
      const sku = selected.variant?.sku || selected.button.dataset.sku;
      const stock = Number(selected.variant?.stock ?? selected.button.dataset.stock);
      const inCart = window.cartData?.cart?.find(item => item.sku === sku)?.qty || 0;
      qty = Math.min(qty + 1, Math.max(1, stock - inCart));
      details();
    };
    addButton.onclick = async () => {
      if (!selected) return;
      addButton.disabled = true;
      try {
        for (let index = 0; index < qty; index++) {
          const data = new FormData();
          data.append("sku", selected.button.dataset.sku);
          if (selected.button.dataset.productType === "grouped") {
            data.append("variant_id", selected.variant?.id || "");
          }
          const response = await fetch("api/add_to_cart.php", {method:"POST", body:data});
          const result = await response.json();
          if (!result.success) throw new Error(result.message || "加入购物袋失败");
          window.cartData = result;
        }
        updateCartUI(window.cartData);
        qty = 1;
        details();
        addButton.textContent = "已加入购物袋 ✓";
        window.setTimeout(details, 700);
      } catch (error) {
        alert(error.message);
        details();
      }
    };
    modal.querySelector(".variant-close").onclick = close;
    modal.addEventListener("click", event => { if (event.target === modal) close(); });
  });
  </script>
</body>
</html>

