<?php
session_start();

// ====================
// 访问密钥
// ====================
$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    die("❌ 未授权访问");
}

// ====================
// 登录检查
// ====================
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php?key=$secret_key");
    exit;
}

require '../config.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

$username = $_SESSION['admin_username'];

// ✅ 查询库存不足
$stmt = $pdo->query("SELECT id, name, stock, warning_level, category FROM products WHERE stock < warning_level ORDER BY category,id DESC LIMIT 5");
$lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yummy Diary 后台管理</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { margin: 0; font-family: "Segoe UI", Arial, sans-serif; background: #fafafa; color: #000; }
        .sidebar { width:220px; background:#fff; border-right:2px solid #000; height:100vh; position:fixed; top:0; left:0; display:flex; flex-direction:column; }
        .sidebar h2 { background:#000; color:#fff; padding:15px; text-align:center; margin:0; font-size:16px; }
        .sidebar a { display:block; padding:12px 20px; color:#000; text-decoration:none; border-bottom:1px solid #eee; }
        .sidebar a:hover { background:#eee; }
        .sidebar a.active { background:#000; color:#fff; }
        .logout { margin:15px; text-align:center; }
        .logout a { background:#000; color:#fff; padding:8px 12px; border-radius:6px; text-decoration:none; display:inline-block; }
        .logout a:hover { background:#333; }
        main { margin-left:240px; padding:20px; }
        .card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #000; margin-bottom: 20px; box-shadow: 2px 2px 8px rgba(0,0,0,0.1); }
        .card h2 { margin-top: 0; color: #000; font-size: 18px; }
        .stats-container { display: flex; gap: 20px; flex-wrap: wrap; }
        .stat-box { flex: 1; min-width: 140px; background:#f5f5f5; padding:20px; border-radius:10px; text-align:center; border:1px solid #000; }
        .stat-box h3 { margin: 0 0 8px; font-size: 16px; }
        .stat-box p { font-size: 28px; font-weight: bold; margin: 5px 0; }
        .warning-list { margin:0; padding-left:18px; color:#856404; }
        .warning-list li { margin-bottom:6px; }
        @media (max-width: 768px) {
            main{margin-left:0;padding:15px;margin-top:120px;}
            .sidebar{position:relative;width:100%;height:auto;border-right:none;border-bottom:2px solid #000;flex-direction:row;overflow-x:auto;}
            .sidebar h2{display:none;}
            .sidebar a{flex:1 0 auto;border-bottom:none;border-right:1px solid #eee;font-size:13px;padding:10px;text-align:center;}
            .logout{display:none;}
            .stats-container{flex-direction:column;}
            .stat-box{font-size:14px;padding:15px;}
            .stat-box p{font-size:22px;}
            canvas{max-width:100%; height:auto !important;}
        }
    </style>
</head>
<body>

<!-- ✅ 左侧导航 -->
<div class="sidebar">
  <h2>🍪 Yummy Diary</h2>
  <a href="dashboard.php?key=<?= $secret_key ?>" class="active">📊 仪表盘</a>
  <a href="products.php?key=<?= $secret_key ?>">🍪 商品管理</a>
  <a href="inventory.php?key=<?= $secret_key ?>">📦 库存管理</a>
  <a href="orders.php?key=<?= $secret_key ?>">🛒 订单管理</a>
  <a href="hot_products.php?key=<?= $secret_key ?>">💡 热销管理</a>
  <a href="promotions.php?key=<?= $secret_key ?>">💡 优惠管理</a>
  <div class="logout"><a href="logout.php?key=<?= $secret_key ?>">退出登录</a></div>
</div>

<main>
    <div class="card">
        <h2>欢迎回来，<?= htmlspecialchars($username) ?> 🎉</h2>
        <p>这是 Yummy Diary 的后台首页。</p>
    </div>

    <!-- ⚠️ 库存不足卡片 -->
    <?php if ($lowStock): ?>
    <div class="card" style="background:#fff3cd; border-color:#ffeeba;">
        <h2>⚠️ 库存不足提醒</h2>
        <ul class="warning-list">
          <?php foreach($lowStock as $item): ?>
            <li><?= htmlspecialchars($item['name']) ?> 
              （库存 <?= $item['stock'] ?>/预警 <?= $item['warning_level'] ?>）
            </li>
          <?php endforeach; ?>
        </ul>
        <p><a href="inventory.php?key=<?= $secret_key ?>&cat=lowstock">👉 查看全部库存不足</a></p>
    </div>
    <?php endif; ?>

    <!-- 📊 数据分析 -->
    <div class="card">
        <h2>📊 数据概览</h2>
        <div class="stats-container">
            <div class="stat-box"><h3>今日订单</h3><p id="todayOrders">加载中...</p></div>
            <div class="stat-box"><h3>本月销售额</h3><p id="monthSales">加载中...</p></div>
            <div class="stat-box"><h3>累计访客</h3><p id="totalVisitors">加载中...</p></div>
        </div>
    </div>

    <!-- 📈 订单 & 销售额趋势 -->
    <div class="card">
        <h2>📈 最近 7 天订单 & 销售额趋势</h2>
        <canvas id="salesChart" height="120"></canvas>
    </div>

    <!-- 👥 访客趋势 -->
    <div class="card">
        <h2>👥 最近 7 天访客趋势</h2>
        <canvas id="visitorsChart" height="120"></canvas>
    </div>
</main>

<script>
// 📊 今日订单 & 本月销售额
fetch("orders_api.php?key=<?= $secret_key ?>")
  .then(res => res.json())
  .then(data => {
    document.getElementById("todayOrders").innerText = data.today_orders;
    document.getElementById("monthSales").innerText = "RM " + Number(data.month_sales).toFixed(2);
  })
  .catch(() => {
    document.getElementById("todayOrders").innerText = "❌ 错误";
    document.getElementById("monthSales").innerText = "❌ 错误";
  });

// 📈 订单趋势
fetch("orders_api.php?key=<?= $secret_key ?>&type=trend")
  .then(res => res.json())
  .then(data => {
    const ctx = document.getElementById("salesChart").getContext("2d");
    new Chart(ctx, {
      type: "line",
      data: {
        labels: data.labels,
        datasets: [
          { label: "销售额 (RM)", data: data.sales, borderColor: "blue", backgroundColor: "rgba(0,0,255,0.2)", fill: true, tension: 0.3, yAxisID: 'y' },
          { label: "订单数", data: data.orders, borderColor: "green", backgroundColor: "rgba(0,255,0,0.2)", fill: true, tension: 0.3, yAxisID: 'y1' }
        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        stacked: false,
        scales: {
          y: { type: 'linear', display: true, position: 'left', title: { display: true, text: '销售额 (RM)' } },
          y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '订单数' } }
        }
      }
    });
  });

// 👥 访客趋势
fetch("visitors_api.php?key=<?= $secret_key ?>&type=trend")
  .then(res => res.json())
  .then(data => {
    document.getElementById("totalVisitors").innerText = data.total_visitors;
    const ctx = document.getElementById("visitorsChart").getContext("2d");
    new Chart(ctx, {
      type: "line",
      data: {
        labels: data.labels,
        datasets: [{ label: "访客数", data: data.visitors, borderColor: "orange", backgroundColor: "rgba(255,165,0,0.2)", fill: true, tension: 0.3 }]
      },
      options: { responsive: true, plugins: { legend: { position: 'top' } } }
    });
  });
</script>
</body>
</html>


