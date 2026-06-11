<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';

date_default_timezone_set("Asia/Kuala_Lumpur");

// 🔥 只拿热销商品
$stmt = $pdo->query("
    SELECT id, sku, name, price, stock, image_url, hot_order 
    FROM products 
    WHERE is_hot = 1 
    ORDER BY hot_order ASC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalHot = count($products);
$totalStock = 0;

foreach ($products as $p) {
    $totalStock += (int)$p['stock'];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>热销管理</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<style>
  .hot-summary{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:14px;
    margin-bottom:22px;
  }

  .summary-card{
    background:#fffaf4;
    border:1px solid var(--line);
    border-radius:22px;
    padding:18px;
    box-shadow:0 10px 28px rgba(120,90,60,.08);
  }

  .summary-card span{
    display:block;
    color:var(--muted);
    font-size:13px;
    font-weight:700;
    margin-bottom:6px;
  }

  .summary-card strong{
    display:block;
    font-size:26px;
    color:var(--text);
  }

  .summary-card small{
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:12px;
  }

  .hot-note{
    background:#fff8e8;
    border:1px solid #f0dfbd;
    border-radius:22px;
    padding:16px 18px;
    margin-bottom:22px;
    color:#8a6428;
    font-weight:700;
  }

  .hot-table td{
    vertical-align:middle;
  }

  .product-cell{
    display:flex;
    align-items:center;
    gap:14px;
    text-align:left;
    min-width:320px;
  }

  .product-info strong{
    display:block;
    color:var(--text);
    font-size:15px;
    line-height:1.5;
  }

  .product-info span{
    display:block;
    color:var(--muted);
    font-size:13px;
    margin-top:3px;
  }

  .price-text{
    font-weight:900;
    white-space:nowrap;
  }

  .stock-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:7px 12px;
    border-radius:999px;
    background:#fffaf4;
    border:1px solid var(--line);
    color:var(--text);
    font-weight:800;
    white-space:nowrap;
  }

  .rank-badge{
    width:42px;
    height:42px;
    border-radius:15px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#ead6bd;
    color:#3b2a20;
    font-weight:900;
    box-shadow:0 10px 22px rgba(120,90,60,.14);
  }

  .hot-actions{
    display:flex;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
  }

  .hot-actions button{
    min-width:48px;
  }

  .empty-image{
    width:62px;
    height:62px;
    border-radius:16px;
    background:#fff7f0;
    border:1px dashed var(--line);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:var(--muted);
    font-size:12px;
    flex-shrink:0;
  }

  .empty-state{
    padding:38px;
    text-align:center;
    color:var(--muted);
    font-weight:700;
  }

  @media(max-width:768px){
    .product-cell{
      min-width:260px;
    }

    .hot-actions{
      flex-wrap:nowrap;
    }
  }
</style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>热销管理</h2>
      <p>管理首页热销商品展示顺序，支持上下移动排序</p>
    </div>
  </section>

  <section class="hot-summary">
    <div class="summary-card">
      <span>热销商品数量</span>
      <strong id="totalHot"><?= $totalHot ?></strong>
      <small>目前已设为热销</small>
    </div>

    <div class="summary-card">
      <span>热销库存合计</span>
      <strong><?= $totalStock ?></strong>
      <small>当前热销商品总库存</small>
    </div>

    <div class="summary-card">
      <span>排序方式</span>
      <strong>手动</strong>
      <small>按 hot_order 由小到大显示</small>
    </div>
  </section>

  <div class="hot-note">
    💡 提示：热销商品是在「商品管理」里面勾选 🔥 后，才会出现在这里。
  </div>

  <div class="table-wrapper">
    <table class="hot-table">
      <thead>
        <tr>
          <th>排序</th>
          <th>商品</th>
          <th>价格</th>
          <th>库存</th>
          <th>操作</th>
        </tr>
      </thead>

      <tbody id="hotTableBody">
        <?php if(empty($products)): ?>
          <tr>
            <td colspan="5">
              <div class="empty-state">暂无热销商品，请先到商品管理勾选 🔥</div>
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach($products as $p): ?>
          <tr>
            <td>
              <span class="rank-badge"><?= (int)$p['hot_order'] ?></span>
            </td>

            <td>
              <div class="product-cell">
                <?php if(!empty($p['image_url'])): ?>
                  <img src="/yummy-diary/<?= htmlspecialchars($p['image_url']) ?>"
                       onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';"
                       class="thumb">
                <?php else: ?>
                  <span class="empty-image">No Image</span>
                <?php endif; ?>

                <div class="product-info">
                  <strong><?= htmlspecialchars($p['name']) ?></strong>
                  <span>SKU：<?= htmlspecialchars($p['sku'] ?? '-') ?></span>
                </div>
              </div>
            </td>

            <td>
              <span class="price-text">RM <?= number_format($p['price'], 2) ?></span>
            </td>

            <td>
              <span class="stock-badge"><?= (int)$p['stock'] ?></span>
            </td>

            <td>
              <div class="hot-actions">
                <button type="button"
                        class="btn btn-move"
                        onclick="moveHot(<?= (int)$p['id'] ?>, 'up')">
                  ⬆
                </button>

                <button type="button"
                        class="btn btn-move"
                        onclick="moveHot(<?= (int)$p['id'] ?>, 'down')">
                  ⬇
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<script>
function escapeHtml(value) {
  if (value === null || value === undefined) return "";

  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function moveHot(id, direction) {
  fetch(`products.php?hot_move=${direction}&id=${id}`)
    .then(res => res.json())
    .then(data => {
      updateTable(data);
    })
    .catch(err => {
      console.error(err);
      alert("排序更新失败，请刷新后再试。");
    });
}

function updateTable(products) {
  const tbody = document.getElementById("hotTableBody");
  const totalHot = document.getElementById("totalHot");

  if (totalHot) {
    totalHot.innerText = products.length;
  }

  if (!products || products.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5">
          <div class="empty-state">暂无热销商品，请先到商品管理勾选 🔥</div>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = "";

  products.forEach(p => {
    const imageHtml = p.image_url
      ? `<img src="/yummy-diary/${escapeHtml(p.image_url)}"
              onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';"
              class="thumb">`
      : `<span class="empty-image">No Image</span>`;

    tbody.innerHTML += `
      <tr>
        <td>
          <span class="rank-badge">${escapeHtml(p.hot_order)}</span>
        </td>

        <td>
          <div class="product-cell">
            ${imageHtml}
            <div class="product-info">
              <strong>${escapeHtml(p.name)}</strong>
              <span>SKU：${escapeHtml(p.sku || "-")}</span>
            </div>
          </div>
        </td>

        <td>
          <span class="price-text">RM ${Number(p.price).toFixed(2)}</span>
        </td>

        <td>
          <span class="stock-badge">${escapeHtml(p.stock || 0)}</span>
        </td>

        <td>
          <div class="hot-actions">
            <button type="button" class="btn btn-move" onclick="moveHot(${p.id}, 'up')">⬆</button>
            <button type="button" class="btn btn-move" onclick="moveHot(${p.id}, 'down')">⬇</button>
          </div>
        </td>
      </tr>
    `;
  });
}
</script>

</body>
</html>