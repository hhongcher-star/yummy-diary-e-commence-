<?php
// å•†å“æŽ’åºé¡µï¼šè°ƒæ•´å•†å“æˆ–åˆ†ç±»åœ¨å‰å°å•†åº—ä¸­çš„å±•ç¤ºé¡ºåºã€‚
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
        echo json_encode(['success' => false, 'message' => 'æŽ’åºèµ„æ–™æ— æ•ˆ']);
        exit;
    }

    $orderedIds = array_values(array_unique(array_map('intval', $orderedIds)));
    if ($isHot) {
        $check = $pdo->query(
            'SELECT id FROM products
             WHERE is_hot=1 AND parent_product_id IS NULL
             ORDER BY hot_order ASC, id ASC'
        );
    } else {
        $check = $pdo->prepare(
            'SELECT id FROM products
             WHERE category=? AND parent_product_id IS NULL
             ORDER BY sort_order ASC, id ASC'
        );
        $check->execute([$category]);
    }
    $databaseIds = array_map('intval', $check->fetchAll(PDO::FETCH_COLUMN));
    $submitted = $orderedIds;
    $expected = $databaseIds;
    sort($submitted);
    sort($expected);

    if ($submitted !== $expected) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'å•†å“èµ„æ–™å·²æ”¹å˜ï¼Œè¯·åˆ·æ–°é¡µé¢']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        if ($isHot) {
            $update = $pdo->prepare(
                'UPDATE products SET hot_order=?
                 WHERE id=? AND is_hot=1 AND parent_product_id IS NULL'
            );
            foreach ($orderedIds as $index => $productId) {
                $update->execute([$index + 1, $productId]);
            }
        } else {
            $update = $pdo->prepare(
                'UPDATE products SET sort_order=?
                 WHERE id=? AND category=? AND parent_product_id IS NULL'
            );
            foreach ($orderedIds as $index => $productId) {
                $update->execute([$index + 1, $productId, $category]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'ä¿å­˜å¤±è´¥']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>å•†å“æŽ’åº</title>
<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css?v=20260612-4">
<style>
<?php include __DIR__ . '/assets/css/product_sort.css'; ?>
</style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main>
  <section class="page-header">
    <div class="page-title">
      <h2>å•†å“æŽ’åº</h2>
      <p>é€‰æ‹©åˆ†ç±»åŽï¼Œç›´æŽ¥æ‹–åŠ¨å‰å°å•†å“å¡ç‰‡è°ƒæ•´æ˜¾ç¤ºé¡ºåº</p>
    </div>
  </section>
  <iframe class="live-preview" src="<?= htmlspecialchars(appUrl('shop?sort_admin=1'), ENT_QUOTES) ?>" title="å®žæ—¶å‰å°å•†å“æŽ’åº"></iframe>
  <div class="sort-mobile-only">
    <div>
      <span class="icon">ðŸ–¥ï¸</span>
      <div>è¯·ä½¿ç”¨ç”µè„‘æ‰“å¼€å•†å“æŽ’åºåŠŸèƒ½ã€‚</div>
    </div>
  </div>
</main>
</body>
</html>

