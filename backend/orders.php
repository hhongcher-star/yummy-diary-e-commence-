<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) die("❌ 未授权访问");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php?key=$secret_key");
    exit;
}

require '../config.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

// ✅ 批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_ids'])) {
    $ids = array_map('intval', $_POST['order_ids']);
    if (!empty($ids)) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        if (isset($_POST['delete_selected'])) {
            $pdo->prepare("DELETE FROM orders WHERE id IN ($in)")->execute($ids);
        }
        if (isset($_POST['mark_paid'])) {
            $pdo->prepare("UPDATE orders SET status='paid' WHERE id IN ($in)")->execute($ids);
        }
        if (isset($_POST['mark_unpaid'])) {
            $pdo->prepare("UPDATE orders SET status='pending' WHERE id IN ($in)")->execute($ids);
        }
    }
    header("Location: orders.php?key=$secret_key");
    exit;
}

// ====================
// 分页 & 搜索 & 月份筛选
// ====================
$limit = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$month  = $_GET['month'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where .= " AND order_number LIKE ?";
    $params[] = "%$search%";
}
if ($month !== '') {
    $where .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $params[] = $month;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders $where");
$stmt->execute($params);
$total_orders = $stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

$sql = "SELECT * FROM orders $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>🛒 订单管理</title>
  <style>
    body {font-family:"Segoe UI",Arial;margin:0;background:#fafafa;color:#333;}
    .sidebar{width:220px;background:#fff;border-right:2px solid #000;height:100vh;position:fixed;top:0;left:0;display:flex;flex-direction:column;}
    .sidebar h2{background:#000;color:#fff;padding:15px;text-align:center;margin:0;font-size:16px;}
    .sidebar a{display:block;padding:12px 20px;color:#000;text-decoration:none;border-bottom:1px solid #eee;}
    .sidebar a:hover{background:#eee;}
    .sidebar a.active{background:#000;color:#fff;}
    .logout{margin:15px;text-align:center;}
    .logout a{background:#000;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none;}
    .logout a:hover{background:#333;}
    main { margin-left: 240px; padding: 20px; }
    h2 { margin-top: 0; }
    form.search-form { margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 6px; }
    form.search-form input, form.search-form select { padding: 6px; }
    form.search-form button { padding: 6px 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; min-width: 600px; }
    th, td { border: 1px solid #000; padding: 10px; text-align: center; font-size: 14px; }
    th { background: #f5f5f5; font-weight: bold; }
    .paid { color: green; font-weight: bold; }
    .unpaid { color: red; font-weight: bold; }
    .btn { padding: 4px 10px; border: 1px solid #000; background: transparent; cursor: pointer; font-size: 13px; border-radius: 6px; margin: 2px; }
    .btn:hover { background: #000; color: #fff; }
    .btn-delete { border-color: red; color: red; }
    .btn-delete:hover { background: red; color: #fff; }
    .pagination { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 5px; }
    .pagination a { text-decoration: none; padding: 6px 10px; border: 1px solid #000; border-radius: 4px; }
    .pagination a.active { background: #000; color: #fff; }
    @media(max-width:768px){
      main{margin-left:0;padding:10px;margin-top:100px;}
      .sidebar{position:relative;width:100%;height:auto;border-right:none;border-bottom:2px solid #000;flex-direction:row;overflow-x:auto;}
      .sidebar h2{display:none;}
      .sidebar a{flex:1 0 auto;border-bottom:none;border-right:1px solid #eee;font-size:13px;padding:10px;text-align:center;}
      .logout{display:none;}
      table { display: block; overflow-x: auto; white-space: nowrap; }
      th, td { font-size: 12px; padding: 8px; }
      .btn { font-size: 12px; padding: 3px 8px; }
    }
  </style>
  <script>
    function toggleSelectAll(source) {
        let checkboxes = document.querySelectorAll("input[name='order_ids[]']");
        checkboxes.forEach(cb => cb.checked = source.checked);
    }
    function confirmBatchDelete() {
        return confirm("确定要批量删除选中的订单吗？此操作不可恢复！");
    }
  </script>
</head>
<body>

<!-- ✅ 左侧导航 -->
<div class="sidebar">
  <h2>🍪 Yummy Diary</h2>
  <a href="dashboard.php?key=<?= $secret_key ?>" >📊 仪表盘</a>
  <a href="products.php?key=<?= $secret_key ?>">🍪 商品管理</a>
  <a href="inventory.php?key=<?= $secret_key ?>">📦 库存管理</a>
  <a href="orders.php?key=<?= $secret_key ?>"class="active">🛒 订单管理</a>
  <a href="hot_products.php?key=<?= $secret_key ?>">💡 热销管理</a>
  <a href="promotions.php?key=<?= $secret_key ?>">💡 优惠管理</a>
  <div class="logout"><a href="logout.php?key=<?= $secret_key ?>">退出登录</a></div>
</div>

<main>
  <h2>订单管理</h2>

  <!-- 搜索 & 筛选表单 -->
  <form method="get" class="search-form">
    <input type="hidden" name="key" value="<?= $secret_key ?>">
    <input type="text" name="search" placeholder="输入订单号" value="<?= htmlspecialchars($search) ?>">
    <input type="month" name="month" value="<?= htmlspecialchars($month) ?>">
    <button type="submit" class="btn">搜索</button>
  </form>

  <!-- 批量操作表单 -->
  <form method="post" onsubmit="return confirmBatchDelete();">
    <button type="submit" name="mark_paid" class="btn">✅ 批量改已付款</button>
    <button type="submit" name="mark_unpaid" class="btn">❌ 批量改未付款</button>
    <button type="submit" name="delete_selected" value="1" class="btn btn-delete">🗑 批量删除</button>

    <table>
      <tr>
        <th><input type="checkbox" onclick="toggleSelectAll(this)"></th>
        <th>订单号</th>
        <th>下单时间</th>
        <th>总金额</th>
        <th>付款状态</th>
        <th>查看收据</th>
      </tr>

      <?php foreach ($orders as $o): ?>
      <?php $timeFormatted = date("Y年n月j日 H:i", strtotime($o['created_at'])); ?>
      <tr>
        <td><input type="checkbox" name="order_ids[]" value="<?= $o['id'] ?>"></td>
        <td><?= htmlspecialchars($o['order_number']) ?></td>
        <td><?= $timeFormatted ?></td>
        <td>RM <?= number_format($o['total'], 2) ?></td>
        <td>
          <?php if ($o['status'] === 'paid'): ?>
            <span class="paid">✅ 已付款</span>
          <?php else: ?>
            <span class="unpaid">❌ 未付款</span>
          <?php endif; ?>
        </td>
        <td><a href="../receipt.php?order_number=<?= urlencode($o['order_number']) ?>" target="_blank" class="btn">收据</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </form>

  <!-- 分页 -->
  <div class="pagination">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a href="?key=<?= $secret_key ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>&month=<?= urlencode($month) ?>" class="<?= $i == $page ? 'active' : '' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
</main>
</body>
</html>



