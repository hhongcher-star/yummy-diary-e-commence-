<?php
// å•†å“ç®¡ç†é¡µï¼šæŸ¥çœ‹å•†å“åˆ—è¡¨ï¼Œå¹¶æä¾›æ–°å¢žã€ç¼–è¾‘ã€åˆ é™¤ç­‰åŽå°ç®¡ç†å…¥å£ã€‚
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
// æ–°å¢žå¤§åˆ†ç±»
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

        header("Location: products.php?msg=" . urlencode("âœ… å¤§åˆ†ç±»å·²æ·»åŠ "));
        exit;
    }
}

// ====================
// æ–°å¢žå°åˆ†ç±»
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

        header("Location: products.php?msg=" . urlencode("âœ… å°åˆ†ç±»å·²æ·»åŠ "));
        exit;
    }
}

// ====================
// åˆ é™¤å°åˆ†ç±»
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_group'])) {
    $groupId = (int)($_POST['group_id'] ?? 0);
    $label = trim((string)($_POST['group_label'] ?? ''));

    if ($groupId > 0 && $label !== '') {
        $stmt = $pdo->prepare("UPDATE category_groups SET label=? WHERE id=?");
        $stmt->execute([$label, $groupId]);
        header("Location: products.php?open_category=1&alert=" . urlencode("å¤§åˆ†ç±»åç§°å·²ä¿®æ”¹"));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_category'])) {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = trim((string)($_POST['category_name'] ?? ''));

    if ($categoryId > 0 && $name !== '') {
        $stmt = $pdo->prepare("UPDATE product_categories SET name=? WHERE id=?");
        $stmt->execute([$name, $categoryId]);
        header("Location: products.php?open_category=1&alert=" . urlencode("å°åˆ†ç±»åç§°å·²ä¿®æ”¹"));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_key = $_POST['category_key'] ?? '';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category=? AND parent_product_id IS NULL");
    $stmt->execute([$category_key]);
    $usedCount = (int)$stmt->fetchColumn();

    if ($usedCount > 0) {
        header("Location: products.php?open_category=1&alert=" . urlencode("è¿™ä¸ªå°åˆ†ç±»è¿˜æœ‰å•†å“ï¼Œä¸èƒ½åˆ é™¤ã€‚è¯·å…ˆæŠŠå•†å“ç§»åŽ»å…¶ä»–åˆ†ç±»æˆ–åˆ é™¤å•†å“ã€‚"));
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM product_categories WHERE category_key=?");
    $stmt->execute([$category_key]);

    header("Location: products.php?open_category=1&alert=" . urlencode("å°åˆ†ç±»å·²åˆ é™¤"));
    exit;
}

// ====================
// åˆ é™¤å¤§åˆ†ç±»
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_group'])) {
    $group_id = intval($_POST['group_id']);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE group_id=?");
    $stmt->execute([$group_id]);
    $catCount = (int)$stmt->fetchColumn();

    if ($catCount > 0) {
        header("Location: products.php?open_category=1&alert=" . urlencode("è¿™ä¸ªå¤§åˆ†ç±»ä¸‹é¢è¿˜æœ‰å°åˆ†ç±»ï¼Œä¸èƒ½åˆ é™¤ã€‚è¯·å…ˆåˆ é™¤é‡Œé¢çš„å°åˆ†ç±»ã€‚"));
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM category_groups WHERE id=?");
    $stmt->execute([$group_id]);

    header("Location: products.php?open_category=1&alert=" . urlencode("å¤§åˆ†ç±»å·²åˆ é™¤"));
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
// ä¸Šä¼ å›¾ç‰‡å‡½æ•°
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
        $targetDir = __DIR__ . "/../frontend/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir,0755,true);
        $target = $targetDir.$filename;
        if (move_uploaded_file($_FILES[$fileInput]['tmp_name'],$target)) {
            return "frontend/uploads/" . $filename;
        }
    }
    return null;
}

// ====================
// æ·»åŠ å•†å“ï¼ˆè‡ªåŠ¨æŽ’æœ€åŽï¼‰
// ====================
// ====================
// æ›´æ–° is_hotï¼ˆðŸ”¥æœ€é‡è¦ï¼‰
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hot'], $_POST['id'])) {

    $id = intval($_POST['id']);
    $is_hot = isset($_POST['is_hot']) ? 1 : 0;

    if ($is_hot) {

        // ðŸ”¥ Find the current maximum hot_order
        $stmt = $pdo->query("SELECT MAX(hot_order) FROM products WHERE is_hot=1 AND parent_product_id IS NULL");
        $max = $stmt->fetchColumn();
        $new_order = $max ? $max + 1 : 1;

        // ðŸ”¥ Assign hot_order
        $stmt = $pdo->prepare("UPDATE products SET is_hot=1, hot_order=? WHERE id=?");
        $stmt->execute([$new_order, $id]);

    } else {

        // âŒ Remove from hot products
        $stmt = $pdo->prepare("UPDATE products SET is_hot=0, hot_order=0 WHERE id=?");
        $stmt->execute([$id]);
    }

    // ðŸ”¥ Handle AJAX requests
    if (isset($_GET['ajax'])) {
        echo json_encode(['status' => 'ok']);
        exit;
    }

    header("Location: products.php?cat=" . urlencode($cat));
    exit;
}

// ====================
// ðŸ”¥ çƒ­é”€æŽ’åº
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
    // ðŸ”¥ é‡æ–°æ•´ç† hot_order
    // ====================
    $stmt = $pdo->query("SELECT id FROM products WHERE is_hot=1 AND parent_product_id IS NULL ORDER BY hot_order ASC");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $i => $pid) {
        $pdo->prepare("UPDATE products SET hot_order=? WHERE id=?")
            ->execute([$i+1, $pid]);
    }

    // ðŸ”¥ å›žä¼ æœ€æ–°æ•°æ®
    $stmt = $pdo->query("SELECT id,name,price,image_url,hot_order FROM products WHERE is_hot=1 AND parent_product_id IS NULL ORDER BY hot_order ASC");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ====================
// åˆ é™¤å•†å“
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
    header("Location: products.php?cat=" . urlencode($cat) . "&msg=" . urlencode("âŒ å•†å“å·²åˆ é™¤"));
    exit;
}

// ====================
// ä¸Šä¸‹ç§»åŠ¨æŽ’åº
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
// é‡æ–°æ•´ç†æŽ’åºï¼ˆä¿è¯è¿žç»­ï¼‰
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
// æŸ¥è¯¢å•†å“
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
<title>å•†å“ç®¡ç†</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">
<style>
<?php include __DIR__ . '/assets/css/products.css'; ?>
</style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>å•†å“ç®¡ç†</h2>
      <p>ç®¡ç†åº—é“ºå•†å“ã€ä»·æ ¼ã€åº“å­˜å’Œçƒ­é”€çŠ¶æ€</p>
    </div>
    <div class="page-actions">
      <a href="add_product.php" class="btn btn-edit">âž• æ–°å¢žå•†å“</a>
      <button type="button" class="btn btn-edit" onclick="openCategoryModal()">âž• åˆ†ç±»ç®¡ç†</button>
      <button type="button" class="btn btn-move" onclick="openSortModal()">â†• åˆ†ç±»æŽ’åº</button>
    </div>
  </section>

  <form class="category-filter" method="get">
    <select id="groupSelect" name="group">
      <option value="">å…¨éƒ¨å¤§åˆ†ç±»</option>
      <?php foreach ($categoryGroups as $groupKey => $group): ?>
        <option value="<?= htmlspecialchars($groupKey) ?>" <?= (isset($_GET['group']) && $_GET['group'] === $groupKey) ? 'selected' : '' ?>>
          <?= htmlspecialchars($group['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select id="catSelect" name="cat">
      <option value="">å…¨éƒ¨å°åˆ†ç±»</option>
      <?php foreach ($categoryGroups as $groupKey => $group): ?>
        <?php foreach ($group['children'] as $key => $label): ?>
          <option value="<?= htmlspecialchars($key) ?>" data-group="<?= htmlspecialchars($groupKey) ?>" <?= ($cat === $key) ? 'selected' : '' ?>>
            <?= htmlspecialchars($group['label']) ?> / <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-edit">ç­›é€‰</button>
    <a href="products.php" class="btn btn-move">é‡ç½®</a>
  </form>

  <div id="categoryModal" class="category-modal" role="dialog" aria-modal="true" aria-label="åˆ†ç±»ç®¡ç†">
    <div class="category-modal-content">
      <div class="category-modal-header">
        <h3>åˆ†ç±»ç®¡ç†</h3>
        <button type="button" class="modal-close btn" onclick="closeCategoryModal()" aria-label="å…³é—­">Ã—</button>
      </div>

      <div class="category-manage-grid">
        <form class="category-mini-form" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <h4>æ–°å¢žå¤§åˆ†ç±»</h4>
          <input type="text" name="group_key" placeholder="ä¾‹å¦‚: drinks" required>
          <input type="text" name="group_label" placeholder="ä¾‹å¦‚: é¥®æ–™" required>
          <button type="submit" name="add_group" value="1" class="btn btn-edit">âž• æ·»åŠ å¤§åˆ†ç±»</button>
        </form>

        <form class="category-mini-form" method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <h4>æ–°å¢žå°åˆ†ç±»</h4>
          <label>é€‰æ‹©å¤§åˆ†ç±»</label>
          <select name="group_id" required>
            <option value="">è¯·é€‰æ‹©å¤§åˆ†ç±»</option>
            <?php foreach ($groupsForForm as $group): ?>
              <option value="<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="category_key" placeholder="ä¾‹å¦‚: cola" required>
          <input type="text" name="category_name" placeholder="ä¾‹å¦‚: å¯ä¹" required>
          <button type="submit" name="add_category" value="1" class="btn btn-edit">âž• æ·»åŠ å°åˆ†ç±»</button>
        </form>
      </div>

      <div class="delete-category-area">
        <h4>åˆ é™¤åˆ†ç±»</h4>
        <div class="delete-split-grid">
          <div class="delete-panel">
            <h4>å¤§åˆ†ç±»</h4>
            <div class="delete-list">
              <?php foreach ($groupsForForm as $group): ?>
                <div class="delete-row">
                  <form method="post" class="category-rename-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                    <input type="text" name="group_label" value="<?= htmlspecialchars($group['label']) ?>" required>
                    <button type="submit" name="rename_group" value="1" class="btn btn-edit">ä¿®æ”¹åç§°</button>
                  </form>
                  <form method="post" data-confirm="ç¡®å®šåˆ é™¤è¿™ä¸ªå¤§åˆ†ç±»å—ï¼Ÿåˆ é™¤åŽä¸èƒ½æ¢å¤ã€‚">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                    <button type="submit" name="delete_group" value="1" class="btn btn-delete">åˆ é™¤å¤§åˆ†ç±»</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="delete-panel">
            <h4>å°åˆ†ç±»</h4>
            <div class="delete-list">
              <?php foreach ($categoryListForDelete as $item): ?>
                <div class="delete-row">
                  <form method="post" class="category-rename-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="category_id" value="<?= (int)$item['id'] ?>">
                    <small><?= htmlspecialchars($item['group_label']) ?> / <?= htmlspecialchars($item['category_key']) ?></small>
                    <input type="text" name="category_name" value="<?= htmlspecialchars($item['category_name']) ?>" required>
                    <button type="submit" name="rename_category" value="1" class="btn btn-edit">ä¿®æ”¹åç§°</button>
                  </form>
                  <form method="post" data-confirm="ç¡®å®šåˆ é™¤è¿™ä¸ªå°åˆ†ç±»å—ï¼Ÿåˆ é™¤åŽä¸èƒ½æ¢å¤ã€‚">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="category_key" value="<?= htmlspecialchars($item['category_key']) ?>">
                    <button type="submit" name="delete_category" value="1" class="btn btn-delete">åˆ é™¤å°åˆ†ç±»</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="sortModal" class="category-modal" role="dialog" aria-modal="true" aria-label="åˆ†ç±»æŽ’åº">
    <div class="category-modal-content">
      <div class="category-modal-header">
        <div>
          <h3>åˆ†ç±»æŽ’åº</h3>
          <p>è°ƒæ•´åŽä¼šç«‹å³æ”¹å˜å‰ç«¯å•†åŸŽå·¦ä¾§åˆ†ç±»çš„æ˜¾ç¤ºé¡ºåºã€‚</p>
        </div>
        <button type="button" class="modal-close btn" onclick="closeSortModal()" aria-label="å…³é—­">Ã—</button>
      </div>

      <div class="sort-section">
        <section class="sort-panel">
          <h4>å¤§åˆ†ç±»æŽ’åº</h4>
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
                    <button type="submit" name="move_group" value="1" class="btn btn-move" <?= $index === 0 ? 'disabled' : '' ?>>â†‘</button>
                  </form>
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                    <input type="hidden" name="direction" value="down">
                    <button type="submit" name="move_group" value="1" class="btn btn-move" <?= $index === count($groupsForForm) - 1 ? 'disabled' : '' ?>>â†“</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="sort-panel">
          <h4>å°åˆ†ç±»æŽ’åº</h4>
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
                        <button type="submit" name="move_category" value="1" class="btn btn-move" <?= $index === 0 ? 'disabled' : '' ?>>â†‘</button>
                      </form>
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="category_id" value="<?= (int)$item['id'] ?>">
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" name="move_category" value="1" class="btn btn-move" <?= $index === count($groupCategories) - 1 ? 'disabled' : '' ?>>â†“</button>
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
      <tr><th>ID</th><th>SKU</th><th>å›¾ç‰‡</th><th>å•†å“å</th><th>ä»·æ ¼</th><th>åº“å­˜</th><th>æŽ’åº</th><th>æ“ä½œ</th></tr>
      <?php foreach($products as $p): ?>
      <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['sku']) ?></td>
        <td><?php if($p['image_url']): ?><img src="<?= htmlspecialchars(productImageUrl($p['image_url']), ENT_QUOTES) ?>" onerror="this.remove();" class="thumb"><?php endif; ?></td>
        <td>
          <?= htmlspecialchars($p['name']) ?>
          <br><small><strong><?= ($p['product_type'] ?? 'single') === 'grouped' ? 'åˆ†ç±»å•†å“' : 'å•å•†å“' ?></strong></small>
        </td>
        <td>RM <?= number_format($p['price'],2) ?></td>
        <td><?= $p['stock'] ?></td>
        <td><?= $p['sort_order'] ?></td>
        <td>

          <!-- âœ… Edit æŒ‰é’® -->
          <a href="edit_product.php?id=<?= $p['id'] ?>" 
             class="btn btn-edit">
             âœï¸ ç¼–è¾‘
          </a>
          <?php if (($p['product_type'] ?? 'single') === 'grouped'): ?>
            <button type="button" class="btn btn-move" onclick="const row=document.getElementById('child-<?= (int)$p['id'] ?>');row.hidden=!row.hidden;">å±•å¼€åˆ†ç±»</button>
          <?php endif; ?>

          <!-- âœ… çƒ­é”€ -->
          <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="update_hot" value="1">
            
            <label>
              <input type="checkbox" name="is_hot" value="1"
                <?= $p['is_hot'] ? 'checked' : '' ?>
                onchange="this.form.submit()">
              ðŸ”¥
            </label>
          </form>

          <!-- âœ… åˆ é™¤ -->
          <form method="post" style="display:inline;" data-confirm="ç¡®å®šåˆ é™¤è¿™ä¸ªå•†å“å—ï¼Ÿåˆ é™¤åŽä¸èƒ½æ¢å¤ã€‚">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit" name="delete_product" value="<?= (int)$p['id'] ?>" class="btn btn-delete">
              ðŸ—‘ åˆ é™¤
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
              <span>åº“å­˜ <?= (int)$child['stock'] ?></span>
              <a class="btn btn-edit" href="edit_product.php?id=<?= (int)$p['id'] ?>#variant-<?= (int)$child['id'] ?>">åœ¨åˆ†ç±»å•†å“ä¸­ç¼–è¾‘</a>
            </div>
          <?php endforeach; ?>
          <?php if(empty($childrenByParent[(int)$p['id']])): ?><p>è¿˜æ²¡æœ‰å½’å…¥å•å•†å“ã€‚</p><?php endif; ?>
        </td>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
    </table>
  </div>
</main>

<div id="siteAlertDialog" class="site-dialog" role="dialog" aria-modal="true" aria-labelledby="siteAlertTitle">
  <div class="site-dialog-card">
    <div class="site-dialog-icon">âœ“</div>
    <h3 id="siteAlertTitle">æ“ä½œå®Œæˆ</h3>
    <p id="siteAlertMessage"></p>
    <div class="site-dialog-actions">
      <button type="button" class="btn btn-edit" id="siteAlertOk">ç¡®å®š</button>
    </div>
  </div>
</div>

<div id="siteConfirmDialog" class="site-dialog" role="dialog" aria-modal="true" aria-labelledby="siteConfirmTitle">
  <div class="site-dialog-card">
    <div class="site-dialog-icon">!</div>
    <h3 id="siteConfirmTitle">è¯·ç¡®è®¤æ“ä½œ</h3>
    <p id="siteConfirmMessage"></p>
    <div class="site-dialog-actions">
      <button type="button" class="btn btn-move" id="siteConfirmCancel">å–æ¶ˆ</button>
      <button type="button" class="btn btn-delete" id="siteConfirmOk">ç¡®å®šåˆ é™¤</button>
    </div>
  </div>
</div>

<script>
<?php include __DIR__ . '/assets/js/products.js.php'; ?>
</script>
</body>
</html>

