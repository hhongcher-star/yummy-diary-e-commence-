<?php
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

$csrfToken = $_SESSION['admin_csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $submittedToken)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

$stmt = $pdo->query(
    "SELECT
        g.group_key,
        g.label AS group_label,
        c.category_key,
        c.name AS category_name
     FROM category_groups g
     JOIN product_categories c ON c.group_id = g.id
     WHERE g.status = 1 AND c.status = 1
     ORDER BY g.sort_order ASC, c.sort_order ASC"
);

$categoryGroups = [];
$categories = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $groupKey = $row['group_key'];

    if (!isset($categoryGroups[$groupKey])) {
        $categoryGroups[$groupKey] = [
            'label' => $row['group_label'],
            'children' => []
        ];
    }

    $categoryGroups[$groupKey]['children'][$row['category_key']] = $row['category_name'];
    $categories[$row['category_key']] = $row['category_name'];
}

$selectedGroup = $_GET['group'] ?? '';
$selectedCat = $_GET['cat'] ?? '';

if ($selectedGroup !== '' && !isset($categoryGroups[$selectedGroup])) {
    $selectedGroup = '';
}

if ($selectedCat !== '' && !isset($categories[$selectedCat])) {
    $selectedCat = '';
}

$cat = $selectedCat;

// ====================
// 新增大分类
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_group'])) {
    $group_key = strtolower(trim($_POST['group_key']));
    $label = trim($_POST['group_label']);

    $group_key = preg_replace('/[^a-z0-9_]/', '', $group_key);

    if ($group_key !== '' && $label !== '') {
        $stmt = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM category_groups");
        $sort_order = $stmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO category_groups (group_key, label, sort_order, status) VALUES (?, ?, ?, 1)");
        $stmt->execute([$group_key, $label, $sort_order]);

        header("Location: products.php?msg=" . urlencode("✅ 大分类已添加"));
        exit;
    }
}

// ====================
// 新增小分类
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $group_id = intval($_POST['group_id']);
    $category_key = strtolower(trim($_POST['category_key']));
    $name = trim($_POST['category_name']);

    $category_key = preg_replace('/[^a-z0-9_]/', '', $category_key);

    if ($group_id > 0 && $category_key !== '' && $name !== '') {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_categories WHERE group_id=?");
        $stmt->execute([$group_id]);
        $sort_order = $stmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO product_categories (group_id, category_key, name, sort_order, status) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$group_id, $category_key, $name, $sort_order]);

        header("Location: products.php?msg=" . urlencode("✅ 小分类已添加"));
        exit;
    }
}

// ====================
// 删除小分类
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_group'])) {
    $groupId = (int)($_POST['group_id'] ?? 0);
    $label = trim((string)($_POST['group_label'] ?? ''));

    if ($groupId > 0 && $label !== '') {
        $stmt = $pdo->prepare("UPDATE category_groups SET label=? WHERE id=?");
        $stmt->execute([$label, $groupId]);
        header("Location: products.php?open_category=1&alert=" . urlencode("大分类名称已修改"));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_category'])) {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = trim((string)($_POST['category_name'] ?? ''));

    if ($categoryId > 0 && $name !== '') {
        $stmt = $pdo->prepare("UPDATE product_categories SET name=? WHERE id=?");
        $stmt->execute([$name, $categoryId]);
        header("Location: products.php?open_category=1&alert=" . urlencode("小分类名称已修改"));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_key = $_POST['category_key'] ?? '';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category=? AND parent_product_id IS NULL");
    $stmt->execute([$category_key]);
    $usedCount = (int)$stmt->fetchColumn();

    if ($usedCount > 0) {
        header("Location: products.php?open_category=1&alert=" . urlencode("这个小分类还有商品，不能删除。请先把商品移去其他分类或删除商品。"));
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM product_categories WHERE category_key=?");
    $stmt->execute([$category_key]);

    header("Location: products.php?open_category=1&alert=" . urlencode("小分类已删除"));
    exit;
}

// ====================
// 删除大分类
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_group'])) {
    $group_id = intval($_POST['group_id']);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE group_id=?");
    $stmt->execute([$group_id]);
    $catCount = (int)$stmt->fetchColumn();

    if ($catCount > 0) {
        header("Location: products.php?open_category=1&alert=" . urlencode("这个大分类下面还有小分类，不能删除。请先删除里面的小分类。"));
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM category_groups WHERE id=?");
    $stmt->execute([$group_id]);

    header("Location: products.php?open_category=1&alert=" . urlencode("大分类已删除"));
    exit;
}

function moveOrderedRow(PDO $pdo, string $table, int $id, string $direction, ?int $groupId = null): void {
    $scopeSql = $groupId === null ? '' : ' AND group_id = ?';
    $params = $groupId === null ? [] : [$groupId];

    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE status=1{$scopeSql} ORDER BY sort_order ASC, id ASC");
    $stmt->execute($params);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $index = array_search($id, $ids, true);

    if ($index === false) {
        return;
    }

    $target = $direction === 'up' ? $index - 1 : $index + 1;
    if ($target < 0 || $target >= count($ids)) {
        return;
    }

    [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
    $update = $pdo->prepare("UPDATE {$table} SET sort_order=? WHERE id=?");
    foreach ($ids as $position => $rowId) {
        $update->execute([$position + 1, $rowId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_group'])) {
    moveOrderedRow(
        $pdo,
        'category_groups',
        (int)($_POST['group_id'] ?? 0),
        $_POST['direction'] ?? 'up'
    );
    header('Location: products.php?open_sort=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_category'])) {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT group_id FROM product_categories WHERE id=?');
    $stmt->execute([$categoryId]);
    $groupId = (int)$stmt->fetchColumn();

    if ($groupId > 0) {
        moveOrderedRow(
            $pdo,
            'product_categories',
            $categoryId,
            $_POST['direction'] ?? 'up',
            $groupId
        );
    }
    header('Location: products.php?open_sort=1');
    exit;
}

$groupStmt = $pdo->query("SELECT id, group_key, label, sort_order FROM category_groups WHERE status=1 ORDER BY sort_order ASC, id ASC");
$groupsForForm = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryListStmt = $pdo->query(
    "SELECT
        c.id,
        c.category_key,
        c.name AS category_name,
        g.label AS group_label
     FROM product_categories c
     JOIN category_groups g ON c.group_id = g.id
     WHERE c.status = 1
     ORDER BY g.sort_order ASC, c.sort_order ASC"
);
$categoryListForDelete = $categoryListStmt->fetchAll(PDO::FETCH_ASSOC);

$categorySortStmt = $pdo->query(
    "SELECT
        c.id,
        c.group_id,
        c.category_key,
        c.name AS category_name,
        c.sort_order,
        g.label AS group_label
     FROM product_categories c
     JOIN category_groups g ON c.group_id = g.id
     WHERE c.status = 1 AND g.status = 1
     ORDER BY g.sort_order ASC, c.sort_order ASC, c.id ASC"
);
$categoriesForSort = $categorySortStmt->fetchAll(PDO::FETCH_ASSOC);

// ====================
// 上传图片函数
// ====================
function uploadImage($fileInput) {
    if (isset($_FILES[$fileInput]) && $_FILES[$fileInput]['error'] === UPLOAD_ERR_OK) {
        if ($_FILES[$fileInput]['size'] > 2*1024*1024) return null;
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES[$fileInput]['tmp_name']);
        if (!isset($allowed[$mime])) return null;
        $filename = bin2hex(random_bytes(16)) . "." . $allowed[$mime];
        return moveProductUpload($_FILES[$fileInput]['tmp_name'], $filename);
    }
    return null;
}

// ====================
// 添加商品（自动排最后）
// ====================
// ====================
// 更新 is_hot（🔥最重要）
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hot'], $_POST['id'])) {

    $id = intval($_POST['id']);
    $is_hot = isset($_POST['is_hot']) ? 1 : 0;

    if ($is_hot) {

        // 🔥 Find the current maximum hot_order
        $stmt = $pdo->query("SELECT MAX(hot_order) FROM products WHERE is_hot=1 AND parent_product_id IS NULL");
        $max = $stmt->fetchColumn();
        $new_order = $max ? $max + 1 : 1;

        // 🔥 Assign hot_order
        $stmt = $pdo->prepare("UPDATE products SET is_hot=1, hot_order=? WHERE id=?");
        $stmt->execute([$new_order, $id]);

    } else {

        // ❌ Remove from hot products
        $stmt = $pdo->prepare("UPDATE products SET is_hot=0, hot_order=0 WHERE id=?");
        $stmt->execute([$id]);
    }

    // 🔥 Handle AJAX requests
    if (isset($_GET['ajax'])) {
        echo json_encode(['status' => 'ok']);
        exit;
    }

    header("Location: products.php?cat=" . urlencode($cat));
    exit;
}

// ====================
// 🔥 热销排序
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hot_move'], $_POST['id'])) {
  header('Content-Type: application/json');

    $id = intval($_POST['id']);
    $move = $_POST['hot_move'];

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=? AND is_hot=1 AND parent_product_id IS NULL");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {

        $current = (int)$product['hot_order'];

        if ($move === 'up') {
            $stmt = $pdo->prepare("
                SELECT * FROM products 
                WHERE is_hot=1 AND parent_product_id IS NULL AND hot_order < ?
                ORDER BY hot_order DESC LIMIT 1
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM products 
                WHERE is_hot=1 AND parent_product_id IS NULL AND hot_order > ?
                ORDER BY hot_order ASC LIMIT 1
            ");
        }

        $stmt->execute([$current]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($target) {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE products SET hot_order=? WHERE id=?")
                ->execute([$target['hot_order'], $product['id']]);

            $pdo->prepare("UPDATE products SET hot_order=? WHERE id=?")
                ->execute([$current, $target['id']]);

            $pdo->commit();
        }
    }

    // ====================
    // 🔥 重新整理 hot_order
    // ====================
    $stmt = $pdo->query("SELECT id FROM products WHERE is_hot=1 AND parent_product_id IS NULL ORDER BY hot_order ASC");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $i => $pid) {
        $pdo->prepare("UPDATE products SET hot_order=? WHERE id=?")
            ->execute([$i+1, $pid]);
    }

    // 🔥 回传最新数据
    $stmt = $pdo->query("SELECT id,name,price,image_url,hot_order FROM products WHERE is_hot=1 AND parent_product_id IS NULL ORDER BY hot_order ASC");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ====================
// 删除商品
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $id=intval($_POST['delete_product']);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE products SET parent_product_id=NULL WHERE parent_product_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM product_variants WHERE source_product_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    header("Location: products.php?cat=" . urlencode($cat) . "&msg=" . urlencode("❌ 商品已删除"));
    exit;
}

// ====================
// 上下移动排序
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move'],$_POST['id'])) {
    $id=intval($_POST['id']);
    $move=$_POST['move'];

    $stmt=$pdo->prepare("SELECT * FROM products WHERE id=? AND parent_product_id IS NULL");
    $stmt->execute([$id]); 
    $product=$stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $current_sort=(int)$product['sort_order']; 
        $category=$product['category'];

        if ($move==='up') {
            $stmt=$pdo->prepare("SELECT * FROM products WHERE category=? AND parent_product_id IS NULL AND sort_order < ? ORDER BY sort_order DESC LIMIT 1");
            $stmt->execute([$category,$current_sort]);
        } elseif ($move==='down') {
            $stmt=$pdo->prepare("SELECT * FROM products WHERE category=? AND parent_product_id IS NULL AND sort_order > ? ORDER BY sort_order ASC LIMIT 1");
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

    header("Location: products.php?cat=" . urlencode($cat));
    exit;
}

// ====================
// 重新整理排序（保证连续）
// ====================
if ($selectedCat !== '') {
    $stmt = $pdo->prepare("SELECT id FROM products WHERE category=? AND parent_product_id IS NULL ORDER BY sort_order ASC,id ASC");
    $stmt->execute([$selectedCat]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $i => $pid) {
        $pdo->prepare("UPDATE products SET sort_order=? WHERE id=?")->execute([$i+1, $pid]);
    }
}

// ====================
// 查询商品
// ====================
if ($selectedCat !== '') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category=? AND parent_product_id IS NULL ORDER BY sort_order ASC,id DESC");
    $stmt->execute([$selectedCat]);
} elseif ($selectedGroup !== '') {
    $groupCats = array_keys($categoryGroups[$selectedGroup]['children']);
    $placeholders = implode(',', array_fill(0, count($groupCats), '?'));

    $stmt = $pdo->prepare("SELECT * FROM products WHERE category IN ($placeholders) AND parent_product_id IS NULL ORDER BY category ASC, sort_order ASC,id DESC");
    $stmt->execute($groupCats);
} else {
    $stmt = $pdo->query("SELECT * FROM products WHERE parent_product_id IS NULL ORDER BY category ASC, sort_order ASC,id DESC");
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$childrenByParent = [];
$childStmt = $pdo->query('SELECT * FROM products WHERE parent_product_id IS NOT NULL ORDER BY parent_product_id,sort_order,id');
foreach ($childStmt->fetchAll(PDO::FETCH_ASSOC) as $child) {
    $childrenByParent[(int)$child['parent_product_id']][] = $child;
}
$msg=$_GET['msg']??'';
$alert = $_GET['alert'] ?? '';
$openCategory = $_GET['open_category'] ?? '';
$openSort = $_GET['open_sort'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>商品管理</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">
<style>
  .table-wrapper{overflow-x:auto;}
  .page-header{display:flex;justify-content:space-between;align-items:center;gap:16px;}
  .page-header .page-title{flex:1;}
  .page-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;}
  .category-modal{display:none;position:fixed;inset:0;background:rgba(40,30,20,.35);z-index:999;padding:30px;overflow:auto;}
  .category-modal.show{display:block;}
  .category-modal-content{max-width:980px;margin:40px auto;background:#fff;border-radius:28px;padding:24px;box-shadow:0 25px 70px rgba(80,50,30,.22);}
  .category-modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}
  .category-modal-header h3{margin:0;}
  .modal-close{width:42px;height:42px;border-radius:50%;font-size:24px;background:#fffaf4;color:var(--text);}
  .category-manage-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;}
  .category-mini-form{background:#fffaf4;border:1px solid var(--line);border-radius:22px;padding:18px;display:grid;gap:12px;}
  .delete-category-area{margin-top:20px;}
  .delete-split-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start;}
  .delete-panel{background:#fffaf4;border:1px solid var(--line);border-radius:22px;padding:18px;}
  .delete-panel h4{margin:0 0 14px;color:var(--text);}
  .delete-list{display:grid;gap:10px;max-height:430px;overflow:auto;padding-right:4px;}
  .delete-row{display:flex;justify-content:space-between;align-items:center;gap:12px;background:#fff;border:1px solid var(--line);border-radius:18px;padding:12px;}
  .delete-row span{font-weight:700;color:var(--text);}
  .delete-row small{color:var(--muted);font-weight:600;}
  .category-rename-form{display:grid;grid-template-columns:minmax(110px,1fr) auto;align-items:center;gap:8px;flex:1;}
  .category-rename-form small{grid-column:1/-1;}
  .category-rename-form input[type="text"]{min-width:0;width:100%;}
  @media(max-width:700px){
    .delete-row{align-items:stretch;flex-direction:column;}
    .category-rename-form{grid-template-columns:1fr;}
    .category-rename-form small{grid-column:auto;}
  }
  .sort-section{display:grid;gap:18px;}
  .sort-panel{background:#fffaf4;border:1px solid var(--line);border-radius:22px;padding:18px;}
  .sort-panel h4{margin:0 0 14px;color:var(--text);}
  .sort-list{display:grid;gap:10px;}
  .sort-row{display:grid;grid-template-columns:48px 1fr auto;align-items:center;gap:12px;background:#fff;border:1px solid var(--line);border-radius:18px;padding:11px 14px;}
  .sort-position{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:var(--soft);font-weight:800;}
  .sort-name{display:grid;gap:2px;font-weight:800;color:var(--text);}
  .sort-name small{color:var(--muted);font-weight:600;}
  .sort-controls{display:flex;gap:7px;}
  .sort-controls form{display:inline;}
  .sort-controls button{min-width:44px;padding:9px 12px;}
  .sort-group{display:grid;gap:10px;}
  .sort-children{display:grid;gap:8px;padding-left:22px;border-left:3px solid var(--soft);}
  .site-dialog{display:none;position:fixed;inset:0;z-index:1200;background:rgba(40,30,20,.42);padding:20px;align-items:center;justify-content:center;}
  .site-dialog.show{display:flex;}
  .site-dialog-card{width:min(430px,100%);background:#fff;border:1px solid var(--line);border-radius:26px;padding:26px;box-shadow:0 24px 70px rgba(80,50,30,.25);}
  .site-dialog-icon{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;margin-bottom:16px;background:var(--soft);color:#7a5a2d;font-size:24px;font-weight:800;}
  .site-dialog-card h3{margin:0 0 10px;color:var(--text);}
  .site-dialog-card p{margin:0;color:var(--muted);line-height:1.65;white-space:pre-line;}
  .site-dialog-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;}
  body.dialog-open{overflow:hidden;}
  @media(max-width:900px){.delete-split-grid{grid-template-columns:1fr;}}
  @media(max-width:768px){
    .page-header{flex-wrap:wrap;}
    .page-actions{display:grid;grid-template-columns:1fr 1fr;}
    .sort-row{grid-template-columns:42px minmax(0,1fr);}
    .sort-controls{grid-column:1 / -1;justify-content:flex-end;}
  }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>商品管理</h2>
      <p>管理店铺商品、价格、库存和热销状态</p>
    </div>
    <div class="page-actions">
      <a href="add_product.php" class="btn btn-edit">➕ 新增商品</a>
      <button type="button" class="btn btn-edit" onclick="openCategoryModal()">➕ 分类管理</button>
      <button type="button" class="btn btn-move" onclick="openSortModal()">↕ 分类排序</button>
    </div>
  </section>

  <form class="category-filter" method="get">
    <select id="groupSelect" name="group">
      <option value="">全部大分类</option>
      <?php foreach ($categoryGroups as $groupKey => $group): ?>
        <option value="<?= htmlspecialchars($groupKey) ?>" <?= (isset($_GET['group']) && $_GET['group'] === $groupKey) ? 'selected' : '' ?>>
          <?= htmlspecialchars($group['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select id="catSelect" name="cat">
      <option value="">全部小分类</option>
      <?php foreach ($categoryGroups as $groupKey => $group): ?>
        <?php foreach ($group['children'] as $key => $label): ?>
          <option value="<?= htmlspecialchars($key) ?>" data-group="<?= htmlspecialchars($groupKey) ?>" <?= ($cat === $key) ? 'selected' : '' ?>>
            <?= htmlspecialchars($group['label']) ?> / <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-edit">筛选</button>
    <a href="products.php" class="btn btn-move">重置</a>
  </form>

  <div id="categoryModal" class="category-modal" role="dialog" aria-modal="true" aria-label="分类管理">
    <div class="category-modal-content">
      <div class="category-modal-header">
        <h3>分类管理</h3>
        <button type="button" class="modal-close btn" onclick="closeCategoryModal()" aria-label="关闭">×</button>
      </div>

      <div class="category-manage-grid">
        <form class="category-mini-form" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <h4>新增大分类</h4>
          <input type="text" name="group_key" placeholder="例如: drinks" required>
          <input type="text" name="group_label" placeholder="例如: 饮料" required>
          <button type="submit" name="add_group" value="1" class="btn btn-edit">➕ 添加大分类</button>
        </form>

        <form class="category-mini-form" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <h4>新增小分类</h4>
          <label>选择大分类</label>
          <select name="group_id" required>
            <option value="">请选择大分类</option>
            <?php foreach ($groupsForForm as $group): ?>
              <option value="<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="category_key" placeholder="例如: cola" required>
          <input type="text" name="category_name" placeholder="例如: 可乐" required>
          <button type="submit" name="add_category" value="1" class="btn btn-edit">➕ 添加小分类</button>
        </form>
      </div>

      <div class="delete-category-area">
        <h4>删除分类</h4>
        <div class="delete-split-grid">
          <div class="delete-panel">
            <h4>大分类</h4>
            <div class="delete-list">
              <?php foreach ($groupsForForm as $group): ?>
                <div class="delete-row">
                  <form method="post" class="category-rename-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                    <input type="text" name="group_label" value="<?= htmlspecialchars($group['label']) ?>" required>
                    <button type="submit" name="rename_group" value="1" class="btn btn-edit">修改名称</button>
                  </form>
                  <form method="post" data-confirm="确定删除这个大分类吗？删除后不能恢复。">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                    <button type="submit" name="delete_group" value="1" class="btn btn-delete">删除大分类</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="delete-panel">
            <h4>小分类</h4>
            <div class="delete-list">
              <?php foreach ($categoryListForDelete as $item): ?>
                <div class="delete-row">
                  <form method="post" class="category-rename-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="category_id" value="<?= (int)$item['id'] ?>">
                    <small><?= htmlspecialchars($item['group_label']) ?> / <?= htmlspecialchars($item['category_key']) ?></small>
                    <input type="text" name="category_name" value="<?= htmlspecialchars($item['category_name']) ?>" required>
                    <button type="submit" name="rename_category" value="1" class="btn btn-edit">修改名称</button>
                  </form>
                  <form method="post" data-confirm="确定删除这个小分类吗？删除后不能恢复。">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="category_key" value="<?= htmlspecialchars($item['category_key']) ?>">
                    <button type="submit" name="delete_category" value="1" class="btn btn-delete">删除小分类</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="sortModal" class="category-modal" role="dialog" aria-modal="true" aria-label="分类排序">
    <div class="category-modal-content">
      <div class="category-modal-header">
        <div>
          <h3>分类排序</h3>
          <p>调整后会立即改变前端商城左侧分类的显示顺序。</p>
        </div>
        <button type="button" class="modal-close btn" onclick="closeSortModal()" aria-label="关闭">×</button>
      </div>

      <div class="sort-section">
        <section class="sort-panel">
          <h4>大分类排序</h4>
          <div class="sort-list">
            <?php foreach ($groupsForForm as $index => $group): ?>
              <div class="sort-row">
                <div class="sort-position"><?= $index + 1 ?></div>
                <div class="sort-name">
                  <?= htmlspecialchars($group['label']) ?>
                  <small><?= htmlspecialchars($group['group_key']) ?></small>
                </div>
                <div class="sort-controls">
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                    <input type="hidden" name="direction" value="up">
                    <button type="submit" name="move_group" value="1" class="btn btn-move" <?= $index === 0 ? 'disabled' : '' ?>>↑</button>
                  </form>
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                    <input type="hidden" name="direction" value="down">
                    <button type="submit" name="move_group" value="1" class="btn btn-move" <?= $index === count($groupsForForm) - 1 ? 'disabled' : '' ?>>↓</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="sort-panel">
          <h4>小分类排序</h4>
          <?php foreach ($groupsForForm as $group): ?>
            <?php
              $groupCategories = array_values(array_filter(
                  $categoriesForSort,
                  fn($item) => (int)$item['group_id'] === (int)$group['id']
              ));
            ?>
            <div class="sort-group">
              <strong><?= htmlspecialchars($group['label']) ?></strong>
              <div class="sort-children">
                <?php foreach ($groupCategories as $index => $item): ?>
                  <div class="sort-row">
                    <div class="sort-position"><?= $index + 1 ?></div>
                    <div class="sort-name">
                      <?= htmlspecialchars($item['category_name']) ?>
                      <small><?= htmlspecialchars($item['category_key']) ?></small>
                    </div>
                    <div class="sort-controls">
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="category_id" value="<?= (int)$item['id'] ?>">
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" name="move_category" value="1" class="btn btn-move" <?= $index === 0 ? 'disabled' : '' ?>>↑</button>
                      </form>
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="category_id" value="<?= (int)$item['id'] ?>">
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" name="move_category" value="1" class="btn btn-move" <?= $index === count($groupCategories) - 1 ? 'disabled' : '' ?>>↓</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </section>
      </div>
    </div>
  </div>

  <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="table-wrapper">
    <table>
      <tr><th>ID</th><th>SKU</th><th>图片</th><th>商品名</th><th>价格</th><th>库存</th><th>排序</th><th>操作</th></tr>
      <?php foreach($products as $p): ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['sku']) ?></td>
        <td><?php if($p['image_url']): ?><img src="<?= htmlspecialchars(productImageUrl($p['image_url']), ENT_QUOTES) ?>" onerror="this.remove();" class="thumb"><?php endif; ?></td>
        <td>
          <?= htmlspecialchars($p['name']) ?>
          <br><small><strong><?= ($p['product_type'] ?? 'single') === 'grouped' ? '分类商品' : '单商品' ?></strong></small>
        </td>
        <td>RM <?= number_format($p['price'],2) ?></td>
        <td><?= $p['stock'] ?></td>
        <td><?= $p['sort_order'] ?></td>
        <td>

          <!-- ✅ Edit 按钮 -->
          <a href="edit_product.php?id=<?= $p['id'] ?>" 
             class="btn btn-edit">
             ✏️ 编辑
          </a>
          <?php if (($p['product_type'] ?? 'single') === 'grouped'): ?>
            <button type="button" class="btn btn-move" onclick="const row=document.getElementById('child-<?= (int)$p['id'] ?>');row.hidden=!row.hidden;">展开分类</button>
          <?php endif; ?>

          <!-- ✅ 热销 -->
          <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="update_hot" value="1">
            
            <label>
              <input type="checkbox" name="is_hot" value="1"
                <?= $p['is_hot'] ? 'checked' : '' ?>
                onchange="this.form.submit()">
              🔥
            </label>
          </form>

          <!-- ✅ 删除 -->
          <form method="post" style="display:inline;" data-confirm="确定删除这个商品吗？删除后不能恢复。">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit" name="delete_product" value="<?= (int)$p['id'] ?>" class="btn btn-delete">
              🗑 删除
            </button>
          </form>

        </td>
      </tr>
      <?php if (($p['product_type'] ?? 'single') === 'grouped'): ?>
      <tr id="child-<?= (int)$p['id'] ?>" hidden>
        <td colspan="8">
          <?php foreach($childrenByParent[(int)$p['id']] ?? [] as $child): ?>
            <div style="display:flex;align-items:center;gap:14px;padding:10px 20px;border-bottom:1px solid #eee;">
              <?php if(!empty($child['image_url'])): ?><img src="<?= htmlspecialchars(productImageUrl($child['image_url']), ENT_QUOTES) ?>" onerror="this.remove();" style="width:52px;height:52px;object-fit:cover;border-radius:10px;"><?php endif; ?>
              <strong><?= htmlspecialchars($child['name']) ?></strong>
              <span><?= htmlspecialchars($child['sku']) ?></span>
              <span>RM <?= number_format((float)$child['price'],2) ?></span>
              <span>库存 <?= (int)$child['stock'] ?></span>
              <a class="btn btn-edit" href="edit_product.php?id=<?= (int)$p['id'] ?>#variant-<?= (int)$child['id'] ?>">在分类商品中编辑</a>
            </div>
          <?php endforeach; ?>
          <?php if(empty($childrenByParent[(int)$p['id']])): ?><p>还没有归入单商品。</p><?php endif; ?>
        </td>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
    </table>
  </div>
</main>

<div id="siteAlertDialog" class="site-dialog" role="dialog" aria-modal="true" aria-labelledby="siteAlertTitle">
  <div class="site-dialog-card">
    <div class="site-dialog-icon">✓</div>
    <h3 id="siteAlertTitle">操作完成</h3>
    <p id="siteAlertMessage"></p>
    <div class="site-dialog-actions">
      <button type="button" class="btn btn-edit" id="siteAlertOk">确定</button>
    </div>
  </div>
</div>

<div id="siteConfirmDialog" class="site-dialog" role="dialog" aria-modal="true" aria-labelledby="siteConfirmTitle">
  <div class="site-dialog-card">
    <div class="site-dialog-icon">!</div>
    <h3 id="siteConfirmTitle">请确认操作</h3>
    <p id="siteConfirmMessage"></p>
    <div class="site-dialog-actions">
      <button type="button" class="btn btn-move" id="siteConfirmCancel">取消</button>
      <button type="button" class="btn btn-delete" id="siteConfirmOk">确定删除</button>
    </div>
  </div>
</div>

<script>
  const alertMsg = <?= json_encode($alert, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const alertDialog = document.getElementById('siteAlertDialog');
  const confirmDialog = document.getElementById('siteConfirmDialog');
  let pendingConfirmForm = null;

  function openSiteDialog(dialog) {
    dialog.classList.add('show');
    document.body.classList.add('dialog-open');
  }

  function closeSiteDialog(dialog) {
    dialog.classList.remove('show');
    if (!document.querySelector('.site-dialog.show')) {
      document.body.classList.remove('dialog-open');
    }
  }

  if (alertMsg) {
    document.getElementById('siteAlertMessage').textContent = alertMsg;
    openSiteDialog(alertDialog);
  }

  document.getElementById('siteAlertOk').addEventListener('click', function () {
    closeSiteDialog(alertDialog);
  });

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.confirmed === '1') {
        return;
      }
      event.preventDefault();
      pendingConfirmForm = form;
      document.getElementById('siteConfirmMessage').textContent = form.dataset.confirm;
      openSiteDialog(confirmDialog);
    });
  });

  document.getElementById('siteConfirmCancel').addEventListener('click', function () {
    pendingConfirmForm = null;
    closeSiteDialog(confirmDialog);
  });

  document.getElementById('siteConfirmOk').addEventListener('click', function () {
    if (!pendingConfirmForm) return;
    pendingConfirmForm.dataset.confirmed = '1';
    pendingConfirmForm.requestSubmit();
  });

  [alertDialog, confirmDialog].forEach(function (dialog) {
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) {
        pendingConfirmForm = null;
        closeSiteDialog(dialog);
      }
    });
  });

  window.addEventListener('DOMContentLoaded', function () {
    if (<?= $openCategory === '1' ? 'true' : 'false' ?>) {
      openCategoryModal();
    }
    if (<?= $openSort === '1' ? 'true' : 'false' ?>) {
      openSortModal();
    }
  });

  function openCategoryModal(){
    document.getElementById('categoryModal').classList.add('show');
  }

  function closeCategoryModal(){
    document.getElementById('categoryModal').classList.remove('show');
  }

  function openSortModal(){
    document.getElementById('sortModal').classList.add('show');
  }

  function closeSortModal(){
    document.getElementById('sortModal').classList.remove('show');
  }

  (function () {
    const groupSelect = document.getElementById('groupSelect');
    const catSelect = document.getElementById('catSelect');

    function syncCategoryOptions() {
      const group = groupSelect.value;
      const options = Array.from(catSelect.querySelectorAll('option'));
      const hasGroup = !!group;

      options.forEach(function (option) {
        const match = !hasGroup || option.getAttribute('data-group') === group || option.value === '';
        option.hidden = !match;
        option.disabled = !match;
      });

      if (group) {
        catSelect.value = '';
      }
    }

    if (groupSelect && catSelect) {
      groupSelect.addEventListener('change', function () {
        syncCategoryOptions();
      });
      syncCategoryOptions();
    }
  })();

</script>
</body>
</html>
