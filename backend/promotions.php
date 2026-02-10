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

$username = $_SESSION['admin_username'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>优惠管理</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
body{font-family:"Segoe UI",Arial;margin:0;background:#fafafa;color:#333;}
.sidebar{width:220px;background:#fff;border-right:2px solid #000;height:100vh;position:fixed;top:0;left:0;display:flex;flex-direction:column;}
.sidebar h2{background:#000;color:#fff;padding:15px;text-align:center;margin:0;font-size:16px;}
.sidebar a{display:block;padding:12px 20px;color:#000;text-decoration:none;border-bottom:1px solid #eee;}
.sidebar a:hover{background:#eee;}
.sidebar a.active{background:#000;color:#fff;}
.logout{margin:15px;text-align:center;}
.logout a{background:#000;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none;}
.logout a:hover{background:#333;}
main{margin-left:240px;padding:20px;}
h2{margin-top:0;}
.placeholder{padding:40px;text-align:center;border:2px dashed #999;border-radius:12px;background:#fafafa;color:#666;font-size:16px;}
@media(max-width:768px){
  main{margin-left:0;padding:10px;}
  .sidebar{position:relative;width:100%;height:auto;border-right:none;border-bottom:2px solid #000;flex-direction:row;overflow-x:auto;}
  .sidebar h2{display:none;}
  .sidebar a{flex:1 0 auto;border-bottom:none;border-right:1px solid #eee;font-size:13px;padding:10px;text-align:center;}
  .logout{display:none;}
}
</style>
</head>
<body>

<!-- ✅ 左侧导航 -->
<div class="sidebar">
  <h2>💡 Yummy Diary</h2>
  <a href="dashboard.php?key=<?= $secret_key ?>">📊 仪表盘</a>
  <a href="products.php?key=<?= $secret_key ?>">🍪 商品管理</a>
  <a href="inventory.php?key=<?= $secret_key ?>">📦 库存管理</a>
  <a href="orders.php?key=<?= $secret_key ?>">🛒 订单管理</a>
  <a href="promotions.php?key=<?= $secret_key ?>" class="active">💡 优惠管理</a>
  <div class="logout"><a href="logout.php?key=<?= $secret_key ?>">退出登录</a></div>
</div>

<main>
  <h2>优惠活动</h2>
  <div class="placeholder">📌 暂时没有优惠活动</div>
</main>

</body>
</html>

