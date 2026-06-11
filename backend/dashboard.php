<?php
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

$username = $_SESSION['admin_username'];
date_default_timezone_set("Asia/Kuala_Lumpur");

$username = $_SESSION['admin_username'];

$stmt = $pdo->query("SELECT id, name, stock, warning_level, category FROM products WHERE stock < warning_level ORDER BY category,id DESC LIMIT 5");
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
  .dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;}
  .stat-box{background:#fffaf4;border:1px solid var(--line);border-radius:20px;padding:22px;text-align:center;}
  .stat-box h3{margin:0 0 10px;color:var(--muted);font-size:15px;}
  .stat-box p{margin:0;font-size:30px;font-weight:800;color:var(--text);} 
  .filter-row{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0 18px;}
  .warning-card{background:#fff8e8;border-color:#f0dfbd;}
  .warning-list{margin:0;padding-left:20px;color:#8a6428;line-height:1.9;}
  .dashboard-table{width:100%;border-collapse:separate;border-spacing:0 10px;}
  .dashboard-table th,.dashboard-table td{padding:13px 14px;text-align:center;}
  canvas{margin-top:14px;}
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

    <section class="admin-card">
        <h2 style="margin-top:0;">欢迎回来，<?= htmlspecialchars($username) ?> 🎉</h2>
        <p style="margin-bottom:0;">这是 Yummy Diary 的后台首页。</p>
    </section>

    <?php if ($lowStock): ?>
    <section class="admin-card warning-card">
        <h2 style="margin-top:0;">⚠️ 库存不足提醒</h2>
        <ul class="warning-list">
          <?php foreach($lowStock as $item): ?>
            <li><?= htmlspecialchars($item['name']) ?> （库存 <?= $item['stock'] ?>/预警 <?= $item['warning_level'] ?>）</li>
          <?php endforeach; ?>
        </ul>
        <p style="margin-bottom:0;"><a href="inventory.php?cat=lowstock">👉 查看全部库存不足</a></p>
    </section>
    <?php endif; ?>

    <!-- ✅ 销售额区域 -->
    <section class="admin-card">
        <h2 style="margin-top:0;">📊 销售额概览</h2>

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
    </div>

    <!-- ✅ 商品销售分析 -->
    <section class="admin-card">
        <h2 style="margin-top:0;">🏆 商品销售分析</h2>

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
    </div>

    <!-- ✅ 今日订单移到这里 -->
    <section class="admin-card">
        <h2 style="margin-top:0;">📈 最近 7 天订单 & 销售额趋势</h2>

        <div class="stats-container">
            <div class="stat-box">
                <h3>今日订单</h3>
                <p id="todayOrders">加载中...</p>
            </div>
        </div>

        <canvas id="salesChart" height="120"></canvas>
    </div>

    <!-- ✅ 累计访客移到这里 -->
    <section class="admin-card">
        <h2 style="margin-top:0;">👥 最近 7 天访客趋势</h2>

        <div class="stats-container">
            <div class="stat-box">
                <h3>累计访客</h3>
                <p id="totalVisitors">加载中...</p>
            </div>
        </div>

        <canvas id="visitorsChart" height="120"></canvas>
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
                {};

            return {
                ...item,
                category_key: product.category || "unknown",
                category_label: product.category_label || "未分类",
                stock: product.stock ?? 0,
                warning_level: product.warning_level ?? 5
            };
        });

        if (category !== "all") {
            merged = merged.filter(item => item.category_key === category);
        }

        merged = merged.slice(0, Number(limit));

        if (merged.length === 0) {
            box.innerHTML = "<p>这个分类暂时没有销售记录</p>";
            return;
        }

        let html = `
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr>
                        <th style="border:1px solid #000; padding:8px;">排名</th>
                        <th style="border:1px solid #000; padding:8px;">商品名称</th>
                        <th style="border:1px solid #000; padding:8px;">分类</th>
                        <th style="border:1px solid #000; padding:8px;">售出数量</th>
                        <th style="border:1px solid #000; padding:8px;">订单次数</th>
                        <th style="border:1px solid #000; padding:8px;">销售额</th>
                        <th style="border:1px solid #000; padding:8px;">平均单价</th>
                        <th style="border:1px solid #000; padding:8px;">库存</th>
                    </tr>
                </thead>
                <tbody>
        `;

        merged.forEach((item, index) => {
            html += `
                <tr>
                    <td style="border:1px solid #000; padding:8px; text-align:center;">${index + 1}</td>
                    <td style="border:1px solid #000; padding:8px;">${item.product_name}</td>
                    <td style="border:1px solid #000; padding:8px;">${item.category_label}</td>
                    <td style="border:1px solid #000; padding:8px; text-align:center;">${item.qty_sold}</td>
                    <td style="border:1px solid #000; padding:8px; text-align:center;">${item.order_count}</td>
                    <td style="border:1px solid #000; padding:8px; text-align:right;">RM ${Number(item.sales).toFixed(2)}</td>
                    <td style="border:1px solid #000; padding:8px; text-align:right;">RM ${Number(item.avg_price).toFixed(2)}</td>
                    <td style="border:1px solid #000; padding:8px; text-align:center;">${item.stock}</td>
                </tr>
            `;
        });

        html += `</tbody></table>`;
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
            borderColor: "blue",
            backgroundColor: "rgba(0,0,255,0.2)",
            fill: true,
            tension: 0.3,
            yAxisID: 'y'
          },
          {
            label: "订单数",
            data: data.orders,
            borderColor: "green",
            backgroundColor: "rgba(0,255,0,0.2)",
            fill: true,
            tension: 0.3,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        stacked: false,
        scales: {
          y: {
            type: 'linear',
            display: true,
            position: 'left',
            title: { display: true, text: '销售额 (RM)' }
          },
          y1: {
            type: 'linear',
            display: true,
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
          borderColor: "orange",
          backgroundColor: "rgba(255,165,0,0.2)",
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'top' } }
      }
    });
  });
</script>

</body>
</html>
