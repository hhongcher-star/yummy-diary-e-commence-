<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) die("❌ 未授权访问");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php?key=$secret_key");
    exit;
}

require '../config.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

// ====================
// 分类分组（与 shop.php / products.php 一致）
// ====================
$categoryGroups  = [
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
// 展平分类（生成 key → label）
$flatCategories = [];
foreach ($categoryGroups as $group) {
    foreach ($group['children'] as $key => $label) {
        $flatCategories[$key] = $label;
    }
}

// 默认分类
$cat = $_GET['cat'] ?? array_key_first($flatCategories);

// ====================
// 更新库存 / 预警值
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    if (isset($_POST['stock'])) {
        $stmt = $pdo->prepare("UPDATE products SET stock=? WHERE id=?");
        $stmt->execute([intval($_POST['stock']), $id]);
    }

    if (isset($_POST['warning_level'])) {
        $stmt = $pdo->prepare("UPDATE products SET warning_level=? WHERE id=?");
        $stmt->execute([intval($_POST['warning_level']), $id]);
    }

    header("Location: inventory.php?key=$secret_key&cat=$cat&msg=" . urlencode("✅ 更新成功"));
    exit;
}

// ====================
// 查询商品
// ====================
if ($cat === 'lowstock') {
    $validCats = array_keys($flatCategories);
    $placeholders = rtrim(str_repeat('?,', count($validCats)), ',');

    $sql = "SELECT id, sku, name, stock, warning_level, category 
            FROM products 
            WHERE stock < warning_level 
              AND category IN ($placeholders)
            ORDER BY category,id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($validCats);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    $stmt = $pdo->prepare("SELECT id, sku, name, stock, warning_level 
                           FROM products 
                           WHERE category=? 
                           ORDER BY id DESC");
    $stmt->execute([$cat]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$msg = $_GET['msg'] ?? '';
$username = $_SESSION['admin_username'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>库存管理</title>
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
.category-nav{margin:10px 0;display:flex;flex-wrap:wrap;gap:6px;}
.category-nav a{padding:6px 12px;border:1px solid #000;border-radius:4px;text-decoration:none;font-size:14px;}
.category-nav a.active{background:#000;color:#fff;}
table{width:100%;border-collapse:collapse;margin-top:15px;min-width:600px;background:#fff;}
th,td{border:1px solid #ccc;padding:10px;text-align:center;}
tr:nth-child(even){background:#f9f9f9;}
.btn-update{background:#2196F3;color:#fff;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;}
.btn-update:hover{background:#1976D2;}
.msg{padding:8px;margin:10px 0;background:#e8f5e9;color:#2e7d32;border-radius:6px;}
@media(max-width:768px){
  main{margin-left:0;padding:10px;}
  .sidebar{position:relative;width:100%;height:auto;border-right:none;border-bottom:2px solid #000;flex-direction:row;overflow-x:auto;}
  .sidebar h2{display:none;}
  .sidebar a{flex:1 0 auto;border-bottom:none;border-right:1px solid #eee;font-size:13px;padding:10px;text-align:center;}
  .logout{display:none;}
  table{min-width:auto;}
  table th,table td{font-size:12px;padding:6px;white-space:nowrap;}
}
</style>
</head>
<body>

<!-- 左侧导航 -->
<div class="sidebar">
  <h2>🍪 Yummy Diary</h2>
  <a href="dashboard.php?key=<?= $secret_key ?>" >📊 仪表盘</a>
  <a href="products.php?key=<?= $secret_key ?>">🍪 商品管理</a>
  <a href="inventory.php?key=<?= $secret_key ?>" class="active">📦 库存管理</a>
  <a href="orders.php?key=<?= $secret_key ?>">🛒 订单管理</a>
  <a href="hot_products.php?key=<?= $secret_key ?>">💡 热销管理</a>
  <a href="promotions.php?key=<?= $secret_key ?>">💡 优惠管理</a>
  <div class="logout"><a href="logout.php?key=<?= $secret_key ?>">退出登录</a></div>
</div>

<main>
  <h2>库存列表</h2>

  <div class="category-nav">
    <?php foreach($categoryGroups as $group): ?>
      <?php foreach($group['children'] as $key=>$label): ?>
        <a href="inventory.php?key=<?= $secret_key ?>&cat=<?= $key ?>" class="<?= $cat===$key?'active':'' ?>">
          <?= $group['label'] ?> / <?= $label ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <table>
    <tr>
      <th>ID</th><th>SKU</th><th>商品名</th><th>分类</th><th>库存</th><th>预警值</th><th>操作</th>
    </tr>

    <?php foreach($products as $p): ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['sku']) ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>

        <!-- FIXED: 分类转换 -->
       <td>
    <?= isset($p['category'], $flatCategories[$p['category']]) 
        ? $flatCategories[$p['category']] 
        : '<span style="color:red;">未分类</span>' ?>
</td>


        <td>
          <form method="post" style="display:flex;justify-content:center;gap:6px;">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="number" name="stock" value="<?= $p['stock'] ?>" style="width:80px;">
            <button type="submit" class="btn-update">💾 更新</button>
          </form>
        </td>

        <td>
          <form method="post" style="display:flex;justify-content:center;gap:6px;">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="number" name="warning_level" value="<?= $p['warning_level'] ?>" style="width:80px;">
            <button type="submit" class="btn-update">⚙️ 设定</button>
          </form>
        </td>

        <td>
          <form method="post" style="display:inline;">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="stock" value="<?= $p['stock']+1 ?>">
            <button type="submit" class="btn-update">➕ 增加 1</button>
          </form>

          <form method="post" style="display:inline;">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="stock" value="<?= max(0, $p['stock']-1) ?>">
            <button type="submit" class="btn-update">➖ 减少 1</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>

  </table>
</main>

</body>
</html>

