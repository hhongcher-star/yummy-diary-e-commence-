<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";

if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    die("❌ 未授权访问");
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php?key=$secret_key");
    exit;
}

require '../config.php';

// 🔥 只拿热销商品
$stmt = $pdo->query("
    SELECT * FROM products 
    WHERE is_hot = 1 
    ORDER BY hot_order ASC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>热销管理</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

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

.table-wrapper{overflow-x:auto;}
table{width:100%;border-collapse:collapse;margin-top:15px;min-width:720px;}
th,td{border:1px solid #ccc;padding:10px;text-align:center;}
tbody tr:nth-child(even){background:#f9f9f9;}
tbody tr:hover{background:#f1f1f1;}

.btn{padding:4px 8px;border-radius:4px;margin:2px;text-decoration:none;display:inline-block;font-size:13px;cursor:pointer;}
.btn-move{background:#eee;color:#333;border:1px solid #999;}
.btn-move:hover{background:#ddd;}
.thumb{width:50px;height:50px;object-fit:cover;border-radius:4px;}
</style>
</head>

<body>

<div class="sidebar">
  <h2>🍪 Yummy Diary</h2>
  <a href="dashboard.php?key=<?= $secret_key ?>">📊 仪表盘</a>
  <a href="products.php?key=<?= $secret_key ?>">🍪 商品管理</a>
  <a href="inventory.php?key=<?= $secret_key ?>">📦 库存管理</a>
  <a href="orders.php?key=<?= $secret_key ?>">🛒 订单管理</a>
  <a href="hot_products.php?key=<?= $secret_key ?>" class="active">🔥 热销管理</a>
  <a href="promotions.php?key=<?= $secret_key ?>">💡 优惠管理</a>
  <div class="logout">
    <a href="logout.php?key=<?= $secret_key ?>">退出登录</a>
  </div>
</div>

<main>
  <h2>🔥 热销商品列表</h2>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>图片</th>
          <th>商品名</th>
          <th>价格</th>
          <th>🔥 排序</th>
          <th>操作</th>
        </tr>
      </thead>

      <tbody id="hotTableBody">
        <?php foreach($products as $p): ?>
        <tr>
          <td><?= $p['id'] ?></td>

          <td>
            <?php if(!empty($p['image_url'])): ?>
              <img src="../<?= htmlspecialchars($p['image_url']) ?>" class="thumb">
            <?php endif; ?>
          </td>

          <td><?= htmlspecialchars($p['name']) ?></td>

          <td>RM <?= number_format($p['price'], 2) ?></td>

          <td><?= $p['hot_order'] ?></td>

          <td>
            <button type="button" class="btn btn-move" onclick="moveHot(<?= $p['id'] ?>, 'up')">⬆</button>
            <button type="button" class="btn btn-move" onclick="moveHot(<?= $p['id'] ?>, 'down')">⬇</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<script>
function moveHot(id, direction) {
    fetch(`products.php?key=<?= $secret_key ?>&hot_move=${direction}&id=${id}`)
        .then(res => res.json())
        .then(data => {
            updateTable(data);
        })
        .catch(err => console.error(err));
}

function updateTable(products) {
    const tbody = document.getElementById("hotTableBody");
    tbody.innerHTML = "";

    products.forEach(p => {
        tbody.innerHTML += `
        <tr>
            <td>${p.id}</td>
            <td>${p.image_url ? `<img src="../${p.image_url}" class="thumb">` : ""}</td>
            <td>${p.name}</td>
            <td>RM ${parseFloat(p.price).toFixed(2)}</td>
            <td>${p.hot_order}</td>
            <td>
                <button type="button" class="btn btn-move" onclick="moveHot(${p.id}, 'up')">⬆</button>
                <button type="button" class="btn btn-move" onclick="moveHot(${p.id}, 'down')">⬇</button>
            </td>
        </tr>
        `;
    });
}
</script>

</body>
</html>