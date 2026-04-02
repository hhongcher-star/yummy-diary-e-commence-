<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ====================
// 访问密钥
// ====================
$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) die("❌ 未授权访问");

// ====================
// 登录检查
// ====================
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php?key=$secret_key");
    exit;
}

require '../config.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

// ====================
// 分类分组（与 shop.php 一致）
// ====================
// ====================
// 分类分组（与 shop.php 一致）
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

// ⬇ 将所有子分类展平成 1 维数组（用于表单 select）
$categories = [];
foreach ($categoryGroups as $group) {
    foreach ($group['children'] as $key => $label) {
        $categories[$key] = $label;
    }
}

$cat = $_GET['cat'] ?? array_key_first($categories);

// ====================
// 上传图片函数
// ====================
function uploadImage($fileInput) {
    if (isset($_FILES[$fileInput]) && $_FILES[$fileInput]['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif'];
        if (!in_array($_FILES[$fileInput]['type'], $allowed)) return null;
        if ($_FILES[$fileInput]['size'] > 2*1024*1024) return null;
        $ext = strtolower(pathinfo($_FILES[$fileInput]['name'], PATHINFO_EXTENSION));
        $filename = uniqid().".".$ext;
        $targetDir = __DIR__."/../uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir,0777,true);
        $target = $targetDir.$filename;
        if (move_uploaded_file($_FILES[$fileInput]['tmp_name'],$target)) return "uploads/".$filename;
    }
    return null;
}

// ====================
// 添加商品（自动排最后）
// ====================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_product'])) {
    $sku=$_POST['sku']; $name=$_POST['name']; $price=floatval($_POST['price']); $stock=intval($_POST['stock']);
    $category=$_POST['category'];
    if (!array_key_exists($category,$categories)) $category=array_key_first($categories);

    $stmt=$pdo->prepare("SELECT MAX(sort_order) FROM products WHERE category=?");
    $stmt->execute([$category]);
    $max_sort=$stmt->fetchColumn();
    $sort_order=$max_sort!==null ? $max_sort+1 : 1;

    $image_url=uploadImage('image');
    $stmt=$pdo->prepare("INSERT INTO products (sku,name,price,stock,category,image_url,sort_order,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    $stmt->execute([$sku,$name,$price,$stock,$category,$image_url,$sort_order]);

    // STEP 4: Save logic for 'is_hot'
    // $is_hot = isset($_POST['is_hot']) ? 1 : 0;

    // Update the database to save the 'is_hot' status
    // $stmt = $pdo->prepare("UPDATE products SET is_hot = ? WHERE id = ?");
    // $stmt->execute([$is_hot, $product_id]);

    header("Location: products.php?key=$secret_key&cat=$category&msg=".urlencode("✅ 商品已添加")); exit;
}

// ====================
// 更新 is_hot（🔥最重要）
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && !isset($_POST['add_product'])) {

    $id = intval($_POST['id']);
    $is_hot = isset($_POST['is_hot']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE products SET is_hot=? WHERE id=?");
    $stmt->execute([$is_hot, $id]);

    header("Location: products.php?key=$secret_key&cat=$cat");
    exit;
}

// ====================
// 删除商品
// ====================
if (isset($_GET['delete'])) {
    $id=intval($_GET['delete']);
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    header("Location: products.php?key=$secret_key&cat=$cat&msg=".urlencode("❌ 商品已删除")); exit;
}

// ====================
// 上下移动排序
// ====================
if (isset($_GET['move'],$_GET['id'])) {
    $id=intval($_GET['id']); 
    $move=$_GET['move'];

    $stmt=$pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$id]); 
    $product=$stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $current_sort=(int)$product['sort_order']; 
        $category=$product['category'];

        if ($move==='up') {
            $stmt=$pdo->prepare("SELECT * FROM products WHERE category=? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1");
            $stmt->execute([$category,$current_sort]);
        } elseif ($move==='down') {
            $stmt=$pdo->prepare("SELECT * FROM products WHERE category=? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1");
            $stmt->execute([$category,$current_sort]);
        }

        $target=$stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($target)) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?")
                    ->execute([$target['sort_order'], $product['id']]);
                $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?")
                    ->execute([$current_sort, $target['id']]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }

    header("Location: products.php?key=$secret_key&cat=$cat"); 
    exit;
}

// ====================
// 重新整理排序（保证连续）
// ====================
$stmt = $pdo->prepare("SELECT id FROM products WHERE category=? ORDER BY sort_order ASC,id ASC");
$stmt->execute([$cat]);
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($ids as $i => $pid) {
    $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?")->execute([$i+1, $pid]);
}

// ====================
// 查询商品
// ====================
$stmt=$pdo->prepare("SELECT * FROM products WHERE category=? ORDER BY sort_order ASC,id DESC");
$stmt->execute([$cat]); 
$products=$stmt->fetchAll(PDO::FETCH_ASSOC);
$msg=$_GET['msg']??'';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>商品管理</title>
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
.table-wrapper{overflow-x:auto;}
table{width:100%;border-collapse:collapse;margin-top:15px;min-width:720px;}
th,td{border:1px solid #ccc;padding:10px;text-align:center;}
tr:nth-child(even){background:#f9f9f9;}
tr:hover{background:#f1f1f1;}
.btn{padding:4px 8px;border-radius:4px;margin:2px;text-decoration:none;display:inline-block;font-size:13px;}
.btn-edit{background:#2196F3;color:#fff;border:none;}
.btn-edit:hover{background:#1976D2;}
.btn-move{background:#eee;color:#333;border:1px solid #999;}
.btn-move:hover{background:#ddd;}
.btn-delete{background:#f44336;color:#fff;border:none;}
.btn-delete:hover{background:#d32f2f;}
.thumb{width:50px;height:50px;object-fit:cover;border-radius:4px;}
.msg{padding:8px;margin:10px 0;background:#e8f5e9;color:#2e7d32;border-radius:6px;}
@media(max-width:768px){
  main{margin-left:0;padding:10px;}
  .sidebar{position:relative;width:100%;height:auto;border-right:none;border-bottom:2px solid #000;flex-direction:row;overflow-x:auto;}
  .sidebar h2{display:none;}
  .sidebar a{flex:1 0 auto;border-bottom:none;border-right:1px solid #eee;font-size:13px;padding:10px;text-align:center;}
  .logout{display:none;}
  table th,table td{font-size:12px;padding:6px;white-space:nowrap;}
  .btn{font-size:11px;padding:4px 6px;}
}
</style>
</head>
<body>

<!-- ✅ 左侧导航 -->
<div class="sidebar">
  <h2>🍪 Yummy Diary</h2>
  <a href="dashboard.php?key=<?= $secret_key ?>">📊 仪表盘</a>
  <a href="products.php?key=<?= $secret_key ?>" class="active">🍪 商品管理</a>
  <a href="inventory.php?key=<?= $secret_key ?>">📦 库存管理</a>
  <a href="orders.php?key=<?= $secret_key ?>">🛒 订单管理</a>
  <a href="promotions.php?key=<?= $secret_key ?>">💡 优惠管理</a>
  <div class="logout"><a href="logout.php?key=<?= $secret_key ?>">退出登录</a></div>
</div>

<main>
  <h2>商品列表</h2>

  <!-- ✅ 分类导航（带分组显示） -->
  <div class="category-nav">
    <?php foreach($categoryGroups as $group): ?>
      <?php foreach($group['children'] as $key=>$label): ?>
        <a href="?key=<?= $secret_key ?>&cat=<?= $key ?>" class="<?= $cat===$key?'active':'' ?>">
          <?= $group['label'] ?> / <?= $label ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- 添加商品 -->
  <form method="post" enctype="multipart/form-data" style="margin:10px 0;">
    <input type="hidden" name="add_product" value="1">
    <input type="text" name="sku" placeholder="SKU" required>
    <input type="text" name="name" placeholder="商品名" required>
    <input type="number" step="0.01" name="price" placeholder="价格" required>
    <input type="number" name="stock" placeholder="库存" required>
    <select name="category">
      <?php foreach($categories as $key=>$label): ?>
        <option value="<?= $key ?>" <?= $cat===$key?'selected':'' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <input type="file" name="image">
    <button type="submit" class="btn btn-edit">➕ 添加</button>
  </form>

  <div class="table-wrapper">
    <table>
      <tr><th>ID</th><th>SKU</th><th>图片</th><th>商品名</th><th>价格</th><th>库存</th><th>排序</th><th>操作</th></tr>
      <?php foreach($products as $p): ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['sku']) ?></td>
        <td><?php if($p['image_url']): ?><img src="../<?= $p['image_url'] ?>" class="thumb"><?php endif; ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td>RM <?= number_format($p['price'],2) ?></td>
        <td><?= $p['stock'] ?></td>
        <td><?= $p['sort_order'] ?></td>
        <td>

          <!-- ✅ Edit 按钮 -->
          <a href="edit_product.php?key=<?= $secret_key ?>&id=<?= $p['id'] ?>" 
             class="btn btn-edit">
             ✏️ 编辑
          </a>

        <a href="products.php?key=<?= $secret_key ?>&cat=<?= $cat ?>&move=up&id=<?= $p['id'] ?>" 
   class="btn btn-move">
   ⬆
</a>

          <!-- 🔽 下移 -->
          <a href="products.php?key=<?= $secret_key ?>&cat=<?= $cat ?>&move=down&id=<?= $p['id'] ?>" 
   class="btn btn-move">
   ⬇
</a>

          <!-- ✅ 热销 -->
          <form method="post" style="display:inline;">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            
            <label>
              <input type="checkbox" name="is_hot" value="1"
                <?= $p['is_hot'] ? 'checked' : '' ?>
                onchange="this.form.submit()">
              🔥
            </label>
          </form>

          <!-- ✅ 删除 -->
          <a href="products.php?key=<?= $secret_key ?>&cat=<?= $cat ?>&delete=<?= $p['id'] ?>"
             class="btn btn-delete"
             onclick="return confirm('确定删除？')">
             🗑 删除
          </a>

        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</main>
</body>
</html>
