<?php
session_start(); 
require 'config.php';

// ====================
// 当前分类（默认第一个：魔芋爽）
// ====================
$cat = $_GET['cat'] ?? 'hot';

// ====================
// 分类映射（分组）
// ====================
$categories = [
  'hot' => [
    'label' => '🔥 热销零食'
  ],
  'snacks' => [
    'label' => '速食小吃',
    'children' => [
      'moyu'     => '魔芋爽',
      'xieliu'   => '蟹柳',
      'egg'      => '鹌鹑蛋',
      'tofu'     => '鱼豆腐',
      'latiao'   => '辣条',
      'jinzhen'  => '金针菇',
      'tudoupian'=> '土豆片',
      'lianou'   => '莲藕片',
      'moyu2'     => '魔芋',
      'haidai'   => '海带',
      'other'    => '其他'
    ]
  ],

  'meals' => [
    'label' => '粉类/速食主食',
    'children' => [
      'noodle'   => '酸辣粉',
      'luosifen' => '螺蛳粉',
      'hotpot'   => '自热火锅'

    ]
  ],

  'candy' => [
    'label' => '糖果',
    'children' => [
      'qqcandy'  => 'QQ糖果',
      'coffee'   => '咖啡糖',
      'other1'    => '其他'
    ]
  ],

  'chips' => [
    'label' => '脆片坚果类',
    'children' => [
      'lays'  => 'Lays 薯片',
      'other2' => '其他'   
    ]
  ],

  'creative' => [
    'label' => '文创小物',
    'children' => [
      'creative' => '文创小物'
    ]
  ]
];


// ====================
// 查询该分类下所有商品
// ====================
if ($cat === 'hot') {
    $stmt = $pdo->query("SELECT * FROM products WHERE is_hot = 1 ORDER BY created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category = ? ORDER BY sort_order ASC, created_at DESC");
    $stmt->execute([$cat]);
}
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====================
// 如果是 AJAX 请求，只返回商品 HTML
// ====================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    ob_start();
    if ($products) {
        foreach ($products as $p): ?>
          <div class="product-card">
            <div class="product-info">
              <?php if ($p['stock'] <= 0): ?>
                <div class="soldout-tag">SOLD OUT</div>
              <?php endif; ?>
              <img src="<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
              <div class="product-text">
                <h4><?= htmlspecialchars($p['name'], ENT_QUOTES) ?></h4>
                <?php if (!empty($p['sku'])): ?>
                  <div class="sku">编号：<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?></div>
                <?php endif; ?>
                <p>库存：<?= (int)$p['stock'] ?></p>
                <div class="price">RM <?= number_format($p['price'], 2) ?></div>
              </div>
            </div>
            <?php if ($p['stock'] > 0): ?>
              <button class="add-to-cart"
                      data-id="<?= (int)$p['id'] ?>"
                      data-sku="<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?>"
                      data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                      data-price="<?= htmlspecialchars($p['price'], ENT_QUOTES) ?>"
                      data-img="<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>"
                      data-stock="<?= (int)$p['stock'] ?>">+</button>
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
  <link rel="stylesheet" href="css/style.css">

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

    .shop-layout { display: grid; grid-template-columns: 180px 1fr; gap: 14px; }

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

    .shop-content { display: flex; flex-direction: column; gap: 16px; }
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
    @media (max-width: 768px) {
  .shop-layout {
    grid-template-columns: 120px 1fr; /* 左边缩小，右边扩大 */
    gap: 10px;
  }

  .shop-sidebar {
    font-size: 0.8rem;
  }
  .shop-sidebar a {
    font-size: 0.75rem;
    padding: 4px 6px;
  }

  .product-card {
    flex-direction: column; /* 卡片上下堆叠 */
    align-items: flex-start;
  }
  .product-info {
    flex-direction: row; /* 图片+文字并排 */
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
  }
}
  </style>
</head>
<body>

  <div id="loader">
    <img src="images/5" alt="Loading...">
  </div>

  <div id="content">
    <?php include 'header.php'; ?>

    <div class="shop-wrapper">
      <div class="shop-banner">
        <img src="images/41" alt="Menu">
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

              foreach ($categories as $groupKey => $group) {

                // ✅ 如果是没有 children（例如 hot）
                if ($groupKey === $cat && !isset($group['children'])) {
                  $currentLabel = $group['label'];
                }

                // ✅ 有 children 的正常逻辑
                if (isset($group['children'][$cat])) {
                  $currentLabel = $group['children'][$cat];
                }
              }

              echo $currentLabel ?: '商品';
            ?>
          </h2>

          <div class="shop-content">
            <?php if ($products): ?>
              <?php foreach ($products as $p): ?>
                <div class="product-card">
                  <div class="product-info">
                    <?php if ($p['stock'] <= 0): ?>
                      <div class="soldout-tag">SOLD OUT</div>
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
                    <div class="product-text">
                      <h4><?= htmlspecialchars($p['name'], ENT_QUOTES) ?></h4>
                      <?php if (!empty($p['sku'])): ?>
                        <div class="sku">编号：<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?></div>
                      <?php endif; ?>
                      <p>库存：<?= (int)$p['stock'] ?></p>
                      <div class="price">RM <?= number_format($p['price'], 2) ?></div>
                    </div>
                  </div>

                  <?php if ($p['stock'] > 0): ?>
                    <button class="add-to-cart"
                            data-id="<?= (int)$p['id'] ?>"
                            data-sku="<?= htmlspecialchars($p['sku'], ENT_QUOTES) ?>"
                            data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                            data-price="<?= htmlspecialchars($p['price'], ENT_QUOTES) ?>"
                            data-img="<?= htmlspecialchars($p['image_url'], ENT_QUOTES) ?>"
                            data-stock="<?= (int)$p['stock'] ?>">+
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

    <?php include 'footer.php'; ?>
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

          // 更新 active
          document.querySelectorAll(".cat-link").forEach(a => a.classList.remove("active"));
          link.classList.add("active");

          // 更新标题
          document.getElementById("category-title").textContent = link.textContent;

          // AJAX 拉商品
          fetch(`shop.php?cat=${cat}&ajax=1`)
            .then(res => res.text())
            .then(html => {
              document.querySelector(".shop-content").innerHTML = html;
            });
        });
      });
    });
  </script>

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

        fetch("add_to_cart.php", { method: "POST", body: formData })
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
</body>
</html>

