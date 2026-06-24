<?php
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

$username = $_SESSION['admin_username'];
date_default_timezone_set("Asia/Kuala_Lumpur");

$username = $_SESSION['admin_username'];

$stmt = $pdo->query("
    SELECT p.id, p.name, NULL parent_name, NULL variant_name, p.stock, p.warning_level, p.category, p.image_url
    FROM products p
    WHERE p.parent_product_id IS NULL AND p.product_type='single' AND p.stock < p.warning_level
    UNION ALL
    SELECT COALESCE(v.source_product_id, -v.id), v.variant_name, parent.name, v.variant_name,
           v.stock, COALESCE(source.warning_level, parent.warning_level, 5), parent.category,
           COALESCE(v.image_url, source.image_url, parent.image_url)
    FROM product_variants v
    JOIN products parent ON parent.id=v.product_id
    LEFT JOIN products source ON source.id=v.source_product_id
    WHERE v.stock < COALESCE(source.warning_level, parent.warning_level, 5)
    ORDER BY category, id DESC LIMIT 5
");
$lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Yummy Diary 后台管理</title>
<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
  .dashboard-stack{display:grid;gap:20px}.dashboard-grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(320px,1fr);gap:20px;align-items:stretch}.dashboard-stack>.admin-card,.dashboard-grid>.admin-card{margin:0}
  .welcome-card{position:relative;overflow:hidden;min-height:150px;display:flex;align-items:center;padding:30px;background:linear-gradient(120deg,#fff9f2,#fff2e7)}.welcome-card:after{content:'🍪';position:absolute;right:42px;font-size:76px;opacity:.22;transform:rotate(-12deg)}.welcome-card h2{margin:0 0 10px;font-size:25px}.welcome-card p{margin:0;color:var(--muted)}
  .card-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px}.card-head h2{margin:0;font-size:20px}.card-head a{color:#9a6535;text-decoration:none;font-weight:700;font-size:13px}
  .stat-box{background:linear-gradient(135deg,#fffaf4,#fff);border:1px solid var(--line);border-radius:18px;padding:20px;text-align:left}.stat-box h3{margin:0 0 10px;color:var(--muted);font-size:13px}.stat-box p{margin:0;font-size:32px;font-weight:800;color:var(--text)}
  .filter-row{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 18px}.filter-row select{min-height:42px;background:#fff}
  .warning-card{background:#fff;border-color:#efcfaa}.warning-list{list-style:none;margin:0;padding:0;border:1px solid #f4e6da;border-radius:15px;overflow:hidden}.warning-list li{position:relative;padding:10px 120px 10px 62px;border-bottom:1px solid #f2e7df;color:var(--text);font-size:14px;min-height:58px;display:flex;align-items:center}.warning-list li:last-child{border-bottom:0}.product-thumb{width:38px;height:38px;object-fit:cover;border:1px solid var(--line);border-radius:10px;background:#fffaf4}.warning-list .product-thumb{position:absolute;left:12px}.stock-badge{position:absolute;right:12px;padding:6px 10px;border-radius:10px;background:#fff0ee;color:#f05c4f;font-size:12px;white-space:nowrap}.product-cell{display:flex;align-items:center;gap:10px;min-width:210px}.product-cell .product-thumb{flex:0 0 38px}.product-cell span{line-height:1.35}
  .table-scroll{overflow-x:auto;border:1px solid var(--line);border-radius:16px}.dashboard-table{width:100%;border-collapse:collapse;min-width:760px;font-size:13px}.dashboard-table th,.dashboard-table td{padding:13px 14px;text-align:center;border-bottom:1px solid var(--line)}.dashboard-table th{background:#fffaf5;color:var(--muted);font-size:12px}.dashboard-table td:nth-child(2){text-align:left;font-weight:650}.dashboard-table tbody tr:hover{background:#fffaf7}.dashboard-table tbody tr:last-child td{border-bottom:0}
  .sales-card{display:flex;flex-direction:column}.sales-card .stat-box{border:0;padding:8px 4px;background:transparent}.sales-card #salesChart{height:125px!important;max-height:125px;margin:4px 0 14px}.mini-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:auto}.mini-stat{position:relative;min-height:105px;padding:14px;border:1px solid var(--line);border-radius:16px;background:linear-gradient(135deg,#fffaf4,#fff)}.mini-stat.purple{background:linear-gradient(135deg,#faf7ff,#fff)}.mini-stat h3{margin:0 0 8px;color:var(--muted);font-size:12px}.mini-stat strong{font-size:24px}.mini-stat small{display:block;margin-top:4px;color:var(--muted)}.mini-stat canvas{position:absolute;right:8px;bottom:10px;width:42%!important;height:42px!important}
  .empty-state{padding:36px;text-align:center;color:var(--muted)}
  @media(max-width:1050px){.dashboard-grid{grid-template-columns:1fr}}@media(max-width:650px){html,body{max-width:100%;overflow-x:hidden}main{padding:18px 10px 136px;overflow:visible}.page-header,.dashboard-stack,.dashboard-grid,.admin-card{width:100%;max-width:100%;min-width:0}.page-title{min-width:0;overflow:hidden}.page-title p{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.dashboard-stack{gap:16px}.admin-card{padding:16px;border-radius:18px}.welcome-card{min-height:0;padding:20px;display:block}.welcome-card:after{display:none}.welcome-card h2{font-size:23px;line-height:1.25;overflow-wrap:anywhere}.welcome-card p{max-width:100%;line-height:1.5}.card-head{align-items:flex-start}.card-head h2{font-size:18px;line-height:1.35;min-width:0}.card-head a{flex:0 0 auto;white-space:nowrap;padding-top:3px}.filter-row{display:grid;grid-template-columns:1fr 1fr}.filter-row select{width:100%;min-width:0}.stat-box p{font-size:27px}.warning-list li{padding:12px 12px 12px 60px;display:block}.warning-list li>span:first-of-type{display:block;min-width:0;max-width:100%;line-height:1.35}.warning-list li strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.warning-list .product-parent{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.warning-list .product-thumb{top:12px}.stock-badge{position:static;display:inline-flex;margin-top:8px}.mini-stats{grid-template-columns:1fr 1fr}.table-scroll{border:0;overflow:visible}.dashboard-table{min-width:0;display:block}.dashboard-table thead{display:none}.dashboard-table,.dashboard-table tbody,.dashboard-table tr{display:block}.dashboard-table tr{margin-bottom:12px;padding:14px;border:1px solid var(--line);border-radius:16px;background:#fff}.dashboard-table td{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:7px 0;border:0;text-align:right!important}.dashboard-table td:before{content:attr(data-label);color:var(--muted);font-size:12px;font-weight:600}.dashboard-table td.product-data{display:block;text-align:left!important;padding-bottom:10px;border-bottom:1px solid var(--line)}.dashboard-table td.product-data:before{display:none}.product-cell{min-width:0}.product-cell span{min-width:0;overflow-wrap:anywhere}.dashboard-table td.rank-data{position:absolute;margin:7px 0 0 calc(100% - 78px);width:28px;height:28px;display:grid;place-items:center;padding:0;border-radius:50%;background:#f4dfc7}.dashboard-table td.rank-data:before{display:none}}
  .product-parent{display:block;margin-top:3px;color:var(--muted);font-size:12px;font-weight:500}
  @media(max-width:1050px){.dashboard-grid{display:flex!important;flex-direction:column!important}.dashboard-grid>.admin-card{width:100%}.mini-stats{grid-template-columns:1fr}.sales-card #salesChart{height:170px!important;max-height:170px}}
  @media(max-width:650px){.dashboard-grid{gap:16px}.dashboard-table tr{position:relative}.dashboard-table td.rank-data{position:absolute!important;right:12px;top:12px;margin:0!important}.dashboard-table td.product-data{padding-right:42px}.sales-card #salesChart{height:145px!important;max-height:145px}}
  @media(max-width:380px){main{padding-left:8px;padding-right:8px}.card-head{gap:8px}.card-head h2{font-size:17px}.card-head a{font-size:12px}.filter-row{grid-template-columns:1fr}.mini-stats{grid-template-columns:1fr}}
</style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
    <section class="page-header">
        <div class="page-title">
            <h2>仪表盘</h2>
            <p>欢迎回来，<?= htmlspecialchars($username) ?>，查看销售、订单、访客和库存提醒</p>
        </div>
    </section>

    <div class="dashboard-stack">
    <section class="admin-card welcome-card">
        <h2 style="margin-top:0;">欢迎回来，<?= htmlspecialchars($username) ?> 🎉</h2>
        <p style="margin-bottom:0;">这是 Yummy Diary 的后台首页。</p>
    </section>

    <div class="dashboard-grid">
    <?php if ($lowStock): ?>
    <section class="admin-card warning-card">
        <div class="card-head"><h2>⚠️ 库存不足提醒</h2><a href="inventory.php?cat=lowstock">查看全部 →</a></div>
        <ul class="warning-list">
          <?php foreach($lowStock as $item): ?>
            <li><img class="product-thumb" src="<?= htmlspecialchars(productImageUrl($item['image_url'] ?? ''), ENT_QUOTES) ?>" alt=""><span><?php if(!empty($item['parent_name'])): ?><strong><?= htmlspecialchars($item['parent_name']) ?></strong><small class="product-parent">分类款式：<?= htmlspecialchars($item['variant_name']) ?></small><?php else: ?><?= htmlspecialchars($item['name']) ?><?php endif; ?></span><span class="stock-badge">库存 <?= $item['stock'] ?> / 预警 <?= $item['warning_level'] ?></span></li>
          <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- ✅ 销售额区域 -->
    <section class="admin-card sales-card">
        <div class="card-head"><h2>📊 销售额概览</h2></div>

        <div class="filter-row">
            <select id="periodFilter">
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month" selected>This Month</option>
                <option value="year">This Year</option>
                <option value="custom_month">Custom Month</option>
            </select>

            <select id="monthFilter" style="display:none;">
                <option value="1">January</option>
                <option value="2">February</option>
                <option value="3">March</option>
                <option value="4">April</option>
                <option value="5">May</option>
                <option value="6">June</option>
                <option value="7">July</option>
                <option value="8">August</option>
                <option value="9">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
            </select>

            <select id="yearFilter">
                <option value="2026" selected>2026</option>
                <option value="2025">2025</option>
            </select>
        </div>

        <div class="stats-container">
            <div class="stat-box">
                <h3 id="salesTitle">本月销售额</h3>
                <p id="filteredSales">加载中...</p>
            </div>
        </div>
        <canvas id="salesChart" height="100"></canvas>
        <div class="mini-stats">
          <div class="mini-stat"><h3>🛍️ 今日订单</h3><strong id="todayOrders">加载中...</strong><small>实时订单数量</small></div>
          <div class="mini-stat purple"><h3>👤 累计访客</h3><strong id="totalVisitors">加载中...</strong><small>网站访客总数</small><canvas id="visitorsChart" height="42"></canvas></div>
        </div>
    </section>
    </div>

    <!-- ✅ 商品销售分析 -->
    <section class="admin-card">
        <div class="card-head"><h2>🏆 商品销售分析</h2></div>

        <div class="filter-row">
            <select id="productPeriodFilter">
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month" selected>This Month</option>
                <option value="year">This Year</option>
                <option value="custom_month">Custom Month</option>
            </select>

            <select id="productMonthFilter" style="display:none;">
                <option value="1">January</option>
                <option value="2">February</option>
                <option value="3">March</option>
                <option value="4">April</option>
                <option value="5">May</option>
                <option value="6">June</option>
                <option value="7">July</option>
                <option value="8">August</option>
                <option value="9">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
            </select>

            <select id="productYearFilter">
                <option value="2026" selected>2026</option>
                <option value="2025">2025</option>
            </select>

            <select id="productCategoryFilter">
                <option value="all">全部分类</option>
            </select>

            <select id="productSortFilter">
                <option value="qty" selected>按售出数量最高</option>
                <option value="sales">按销售额最高</option>
                <option value="orders">按订单次数最高</option>
                <option value="avg_price">按平均单价最高</option>
            </select>

            <select id="productLimitFilter">
                <option value="5" selected>Top 5</option>
                <option value="10">Top 10</option>
                <option value="20">Top 20</option>
            </select>
        </div>

        <div id="productAnalysisTable">加载中...</div>
    </section>

    </div>
</main>

<script>
// ✅ 销售额 filter
function loadSalesSummary() {
    const period = document.getElementById("periodFilter").value;
    const month = document.getElementById("monthFilter").value;
    const year = document.getElementById("yearFilter").value;

    document.getElementById("monthFilter").style.display =
        period === "custom_month" ? "inline-block" : "none";

    fetch(`api/orders_api.php?type=sales_summary&period=${period}&month=${month}&year=${year}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById("salesTitle").innerText = data.title;
            document.getElementById("filteredSales").innerText =
                "RM " + Number(data.sales).toFixed(2);
        })
        .catch(() => {
            document.getElementById("filteredSales").innerText = "❌ 错误";
        });
}

document.getElementById("periodFilter").addEventListener("change", loadSalesSummary);
document.getElementById("monthFilter").addEventListener("change", loadSalesSummary);
document.getElementById("yearFilter").addEventListener("change", loadSalesSummary);

loadSalesSummary();

// ✅ 加载商品分类
function loadProductCategories() {
    fetch(`api/product_api.php?type=categories`)
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById("productCategoryFilter");
            select.innerHTML = "";

            if (!data.categories) return;

            data.categories.forEach(cat => {
                const option = document.createElement("option");
                option.value = cat.value;
                option.textContent = cat.label;
                select.appendChild(option);
            });
        });
}

// ✅ 商品销售分析
function loadProductAnalysis() {
    const period = document.getElementById("productPeriodFilter").value;
    const month = document.getElementById("productMonthFilter").value;
    const year = document.getElementById("productYearFilter").value;
    const category = document.getElementById("productCategoryFilter").value;
    const sort = document.getElementById("productSortFilter").value;
    const limit = document.getElementById("productLimitFilter").value;

    document.getElementById("productMonthFilter").style.display =
        period === "custom_month" ? "inline-block" : "none";

    Promise.all([
        fetch(`api/orders_api.php?type=product_analysis&period=${period}&month=${month}&year=${year}&sort=${sort}&limit=50`).then(res => res.json()),
        fetch(`api/product_api.php?type=product_map`).then(res => res.json())
    ])
    .then(([orderData, productData]) => {
        const box = document.getElementById("productAnalysisTable");

        if (!orderData.products || orderData.products.length === 0) {
            box.innerHTML = "<p>暂无商品销售记录</p>";
            return;
        }

        const productMap = productData.product_map || {};
        const productNameMap = productData.product_name_map || {};

        let merged = orderData.products.map(item => {
            const product =
                productMap[item.sku] ||
                productNameMap[item.product_name] ||
                null;

            if (!product) return null;

            return {
                ...item,
                product_name: product.name || item.product_name,
                parent_name: product.parent_name || "",
                variant_name: product.variant_name || "",
                category_key: product.category,
                category_label: product.category_label,
                stock: product.stock,
                warning_level: product.warning_level,
                image_url: product.image_url
            };
        }).filter(Boolean);

        if (category !== "all") {
            merged = merged.filter(item => item.category_key === category);
        }

        merged = merged.slice(0, Number(limit));

        if (merged.length === 0) {
            box.innerHTML = "<p>这个分类暂时没有销售记录</p>";
            return;
        }

        let html = `
            <div class="table-scroll"><table class="dashboard-table">
                <thead>
                    <tr>
                        <th>排名</th><th>商品名称</th><th>分类</th><th>售出数量</th>
                        <th>订单次数</th><th>销售额</th><th>平均单价</th><th>库存</th>
                    </tr>
                </thead>
                <tbody>
        `;

        merged.forEach((item, index) => {
            html += `
                <tr>
                    <td class="rank-data" data-label="排名">${index + 1}</td>
                    <td class="product-data" data-label="商品名称"><div class="product-cell"><img class="product-thumb" src="${item.image_url}" alt="" onerror="this.src='<?= htmlspecialchars(productImageUrl(''), ENT_QUOTES) ?>'"><span>${item.parent_name ? `<strong>${item.parent_name}</strong><small class="product-parent">分类款式：${item.variant_name}</small>` : item.product_name}</span></div></td>
                    <td data-label="分类">${item.category_label}</td>
                    <td data-label="售出数量">${item.qty_sold}</td><td data-label="订单次数">${item.order_count}</td>
                    <td data-label="销售额">RM ${Number(item.sales).toFixed(2)}</td><td data-label="平均单价">RM ${Number(item.avg_price).toFixed(2)}</td>
                    <td data-label="库存">${item.stock}</td>
                </tr>
            `;
        });

        html += `</tbody></table></div>`;
        box.innerHTML = html;
    })
    .catch(() => {
        document.getElementById("productAnalysisTable").innerHTML = "<p>❌ 加载错误</p>";
    });
}

["productPeriodFilter", "productMonthFilter", "productYearFilter", "productCategoryFilter", "productSortFilter", "productLimitFilter"]
.forEach(id => {
    document.getElementById(id).addEventListener("change", loadProductAnalysis);
});

loadProductCategories();
loadProductAnalysis();


// ✅ 今日订单
fetch("api/orders_api.php")
  .then(res => res.json())
  .then(data => {
    document.getElementById("todayOrders").innerText = data.today_orders;
  })
  .catch(() => {
    document.getElementById("todayOrders").innerText = "❌ 错误";
  });


// ✅ 订单 & 销售额趋势
fetch("api/orders_api.php?type=trend")
  .then(res => res.json())
  .then(data => {
    const ctx = document.getElementById("salesChart").getContext("2d");
    new Chart(ctx, {
      type: "line",
      data: {
        labels: data.labels,
        datasets: [
          {
            label: "销售额 (RM)",
            data: data.sales,
            borderColor: "#ff8a34",
            backgroundColor: "rgba(255,138,52,.12)",
            fill: true,
            tension: 0.3,
            yAxisID: 'y'
          },
          {
            label: "订单数",
            data: data.orders,
            borderColor: "#a66a3f",
            backgroundColor: "transparent",
            fill: false,
            tension: 0.3,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        stacked: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            type: 'linear',
            display: false,
            position: 'left',
            title: { display: true, text: '销售额 (RM)' }
          },
          y1: {
            type: 'linear',
            display: false,
            position: 'right',
            grid: { drawOnChartArea: false },
            title: { display: true, text: '订单数' }
          }
        }
      }
    });
  });


// ✅ 访客趋势
fetch("api/visitors_api.php?type=trend")
  .then(res => res.json())
  .then(data => {
    document.getElementById("totalVisitors").innerText = data.total_visitors;

    const ctx = document.getElementById("visitorsChart").getContext("2d");
    new Chart(ctx, {
      type: "line",
      data: {
        labels: data.labels,
        datasets: [{
          label: "访客数",
          data: data.visitors,
          borderColor: "#7867ff",
          backgroundColor: "rgba(120,103,255,.12)",
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        elements: { point: { radius: 0 } },
        scales: { x: { display: false }, y: { display: false } },
        plugins: { legend: { display: false }, tooltip: { enabled: false } }
      }
    });
  });
</script>

</body>
</html>
