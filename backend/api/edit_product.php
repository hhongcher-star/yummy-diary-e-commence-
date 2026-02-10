<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require '../config.php';



// ====================
// 安全校验
// ====================
$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) die("❌ 未授权访问");
if (!isset($_SESSION['admin_id'])) { 
    header("Location: login.php?key=$secret_key"); 
    exit; 
}

// ====================
// 分类分组（与 products.php 保持一致）
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


// ====================
// 读取商品
// ====================
$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$p) die("❌ 未找到商品");

// ====================
// 更新保存
// ====================
if($_SERVER['REQUEST_METHOD']==='POST'){
    $sku = trim($_POST['sku']);
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category = $_POST['category'];
    $sort_order = $_POST['sort_order'] !== "" ? intval($_POST['sort_order']) : $p['sort_order'];

    if(!array_key_exists($category,$categories)) $category='other';

    $image_url = $p['image_url'];

    // 图片上传处理
    if(isset($_FILES['image']) && $_FILES['image']['error']===UPLOAD_ERR_OK){
        $allowed = ['image/jpeg','image/png','image/gif'];
        if(in_array($_FILES['image']['type'],$allowed) && $_FILES['image']['size']<=2*1024*1024){
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $filename = uniqid().".".$ext;
            $target = "../uploads/".$filename;
            if(move_uploaded_file($_FILES['image']['tmp_name'],$target)){
                // 删除旧图片
                if($p['image_url'] && file_exists("../".$p['image_url'])){
                    unlink("../".$p['image_url']);
                }
                $image_url = "uploads/".$filename;
            }
        }
    }

    // 更新数据库
    $stmt = $pdo->prepare("UPDATE products 
        SET sku=?, name=?, price=?, stock=?, category=?, image_url=?, sort_order=? 
        WHERE id=?");
    $stmt->execute([$sku,$name,$price,$stock,$category,$image_url,$sort_order,$id]);

    header("Location: products.php?key=$secret_key&cat=$category&msg=".urlencode("✅ 商品已更新")); 
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><title>编辑商品</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
body{font-family:"Segoe UI",Arial;margin:20px;}
form{max-width:500px;margin:auto;display:flex;flex-direction:column;gap:10px;}
input,select,button{padding:8px;font-size:14px;}
img.thumb{width:100px;height:100px;object-fit:cover;margin-top:6px;}
button{border:1px solid #000;cursor:pointer;}
button:hover{background:#000;color:#fff;}
</style>
</head>
<body>
<h2>✏️ 编辑商品</h2>
<form method="post" enctype="multipart/form-data">
  <label>SKU: 
    <input type="text" name="sku" value="<?= htmlspecialchars($p['sku']) ?>" required>
  </label>
  <label>名称: 
    <input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required>
  </label>
  <label>价格: 
    <input type="number" step="0.01" name="price" value="<?= $p['price'] ?>" required>
  </label>
  <label>库存: 
    <input type="number" name="stock" value="<?= $p['stock'] ?>" required>
  </label>
  <label>分类:
    <select name="category">
      <?php foreach($categoryGroups as $group): ?>
        <optgroup label="<?= $group['label'] ?>">
          <?php foreach($group['children'] as $key=>$label): ?>
            <option value="<?= $key ?>" <?= $p['category']===$key?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
    </select>
  </label>
  <label>排序: 
    <input type="number" name="sort_order" value="<?= $p['sort_order'] ?>">
  </label>
  <label>图片: 
    <?php if($p['image_url']): ?>
      <img src="../<?= htmlspecialchars($p['image_url']) ?>" class="thumb">
    <?php endif; ?>
    <input type="file" name="image">
  </label>
  <button type="submit">💾 保存修改</button>
</form>
<p><a href="products.php?key=<?= $secret_key ?>&cat=<?= $p['category'] ?>">⬅ 返回商品列表</a></p>
</body>
</html>
