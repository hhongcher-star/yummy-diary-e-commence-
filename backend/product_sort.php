<?php
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order'])) {
    header('Content-Type: application/json; charset=utf-8');
    $category = $_POST['category'] ?? '';
    $orderedIds = json_decode($_POST['ordered_ids'] ?? '[]', true);

    $isHot = $category === 'hot';
    $categoryExists = $isHot;
    if (!$isHot) {
        $categoryCheck = $pdo->prepare('SELECT COUNT(*) FROM product_categories WHERE category_key=? AND status=1');
        $categoryCheck->execute([$category]);
        $categoryExists = (bool)$categoryCheck->fetchColumn();
    }

    if (!$categoryExists || !is_array($orderedIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '排序资料无效']);
        exit;
    }

    $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));
    if ($isHot) {
        $check = $pdo->query('SELECT id FROM products WHERE is_hot=1 ORDER BY hot_order ASC, id ASC');
    } else {
        $check = $pdo->prepare('SELECT id FROM products WHERE category=? ORDER BY sort_order ASC, id ASC');
        $check->execute([$category]);
    }
    $databaseIds = array_map('intval', $check->fetchAll(PDO::FETCH_COLUMN));
    $submitted = $orderedIds;
    $expected = $databaseIds;
    sort($submitted);
    sort($expected);

    if ($submitted !== $expected) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => '商品资料已改变，请刷新页面']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        if ($isHot) {
            $update = $pdo->prepare('UPDATE products SET hot_order=? WHERE id=? AND is_hot=1');
            foreach ($orderedIds as $index => $productId) {
                $update->execute([$index + 1, $productId]);
            }
        } else {
            $update = $pdo->prepare('UPDATE products SET sort_order=? WHERE id=? AND category=?');
            foreach ($orderedIds as $index => $productId) {
                $update->execute([$index + 1, $productId, $category]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '保存失败']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>商品排序</title>
<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css?v=20260612-4">
<style>
  body{background:#f5f5f5;}
  main{background:#f5f5f5;}
  .page-title p{color:#666;}
  .sort-mobile-only{display:none;}
  .live-preview{width:100%;height:calc(100vh - 175px);min-height:650px;border:1px solid var(--line);border-radius:24px;background:#fff;box-shadow:var(--shadow);}
  @media(max-width:768px){
    .live-preview{display:none;}
    .sort-mobile-only{display:flex;align-items:center;justify-content:center;min-height:150px;border:1px solid #f5bfd4;border-radius:18px;background:#fff;color:#6f6070;font-size:18px;font-weight:800;text-align:center;padding:24px;margin-top:30px;}
    .sort-mobile-only .icon{display:block;font-size:28px;margin-bottom:14px;}
  }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main>
  <section class="page-header">
    <div class="page-title">
      <h2>商品排序</h2>
      <p>选择分类后，直接拖动前台商品卡片调整显示顺序</p>
    </div>
  </section>
  <iframe class="live-preview" src="/yummy-diary/frontend/shop.php?sort_admin=1" title="实时前台商品排序"></iframe>
  <div class="sort-mobile-only">
    <div>
      <span class="icon">🖥️</span>
      <div>请使用电脑打开商品排序功能。</div>
    </div>
  </div>
</main>
</body>
</html>
