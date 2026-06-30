<?php
// è®¢å•ç®¡ç†é¡µï¼šæŸ¥çœ‹è®¢å•åˆ—è¡¨ã€è®¢å•è¯¦æƒ…ã€ä»˜æ¬¾çŠ¶æ€å’Œå±¥çº¦å¤„ç†ã€‚
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

// æ‰¹é‡å½’æ¡£
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_payment_id'])) {
    $orderId = (int)$_POST['toggle_payment_id'];

    if ($orderId > 0) {
        $stmt = $pdo->prepare(
            "UPDATE orders
             SET status = CASE WHEN status = 'paid' THEN 'pending' ELSE 'paid' END
             WHERE id = ? AND archived_at IS NULL"
        );
        $stmt->execute([$orderId]);
    }

    $query = http_build_query([
        'page' => max(1, (int)($_POST['current_page'] ?? 1)),
        'search' => (string)($_POST['current_search'] ?? ''),
        'month' => (string)($_POST['current_month'] ?? ''),
    ]);
    header('Location: orders.php?' . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['mark_paid_id', 'mark_unpaid_id', 'archive_order_id', 'delete_order_id'] as $singleAction) {
        if (!isset($_POST[$singleAction])) {
            continue;
        }

        $singleOrderId = (int)$_POST[$singleAction];
        if ($singleOrderId <= 0) {
            break;
        }

        $_POST['order_ids'] = [$singleOrderId];
        if ($singleAction === 'mark_paid_id') {
            $_POST['mark_paid'] = '1';
        } elseif ($singleAction === 'mark_unpaid_id') {
            $_POST['mark_unpaid'] = '1';
        } elseif ($singleAction === 'archive_order_id') {
            $_POST['archive_selected'] = '1';
        } elseif ($singleAction === 'delete_order_id') {
            $_POST['delete_selected'] = '1';
        }
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_ids'])) {
    $ids = array_values(array_filter(array_map('intval', $_POST['order_ids'])));

    if (!empty($ids) && (isset($_POST['mark_paid']) || isset($_POST['mark_unpaid']))) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $status = isset($_POST['mark_paid']) ? 'paid' : 'pending';
        $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$status], $ids));
    }

    if (!empty($ids) && isset($_POST['archive_selected'])) {
        $pdo->beginTransaction();
        try {
            $orderStmt = $pdo->prepare('SELECT id, status, stock_released_at FROM orders WHERE id=? FOR UPDATE');
            $itemStmt = $pdo->prepare('SELECT product_id, sku, quantity FROM order_items WHERE order_id=?');
            $stockStmt = $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?');
            $variantStockStmt = $pdo->prepare(
                'UPDATE product_variants SET stock=stock+? WHERE product_id=? AND sku=?'
            );
            $syncSourceStockStmt = $pdo->prepare(
                'UPDATE products p
                 JOIN product_variants v ON v.source_product_id=p.id
                 SET p.stock=v.stock
                 WHERE v.product_id=? AND v.sku=?'
            );
            $cancelStmt = $pdo->prepare(
                "UPDATE orders
                 SET status='cancelled', stock_released_at=COALESCE(stock_released_at, NOW()), archived_at=NOW()
                 WHERE id=?"
            );
            $archiveStmt = $pdo->prepare('UPDATE orders SET archived_at=NOW() WHERE id=?');

            foreach ($ids as $id) {
                $orderStmt->execute([$id]);
                $order = $orderStmt->fetch();
                if (!$order) {
                    continue;
                }

                if ($order['status'] === 'pending' && $order['stock_released_at'] === null) {
                    $itemStmt->execute([$id]);
                    foreach ($itemStmt->fetchAll() as $item) {
                        $variantStockStmt->execute([
                            (int)$item['quantity'],
                            (int)$item['product_id'],
                            (string)$item['sku'],
                        ]);
                        if ($variantStockStmt->rowCount() === 0) {
                            $stockStmt->execute([(int)$item['quantity'], (int)$item['product_id']]);
                        } else {
                            $syncSourceStockStmt->execute([
                                (int)$item['product_id'],
                                (string)$item['sku'],
                            ]);
                        }
                    }
                    $cancelStmt->execute([$id]);
                } else {
                    $archiveStmt->execute([$id]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Archive orders failed: ' . $e->getMessage());
        }
    }

    if (!empty($ids) && isset($_POST['delete_selected'])) {
        $pdo->beginTransaction();
        try {
            $orderStmt = $pdo->prepare('SELECT id, status, stock_released_at FROM orders WHERE id=? FOR UPDATE');
            $itemStmt = $pdo->prepare('SELECT product_id, sku, quantity FROM order_items WHERE order_id=?');
            $stockStmt = $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?');
            $variantStockStmt = $pdo->prepare(
                'UPDATE product_variants SET stock=stock+? WHERE product_id=? AND sku=?'
            );
            $syncSourceStockStmt = $pdo->prepare(
                'UPDATE products p
                 JOIN product_variants v ON v.source_product_id=p.id
                 SET p.stock=v.stock
                 WHERE v.product_id=? AND v.sku=?'
            );
            $deleteItemsStmt = $pdo->prepare('DELETE FROM order_items WHERE order_id=?');
            $deleteOrderStmt = $pdo->prepare('DELETE FROM orders WHERE id=?');

            foreach ($ids as $id) {
                $orderStmt->execute([$id]);
                $order = $orderStmt->fetch();
                if (!$order) {
                    continue;
                }

                if ($order['status'] === 'pending' && $order['stock_released_at'] === null) {
                    $itemStmt->execute([$id]);
                    foreach ($itemStmt->fetchAll() as $item) {
                        $variantStockStmt->execute([
                            (int)$item['quantity'],
                            (int)$item['product_id'],
                            (string)$item['sku'],
                        ]);
                        if ($variantStockStmt->rowCount() === 0) {
                            $stockStmt->execute([(int)$item['quantity'], (int)$item['product_id']]);
                        } else {
                            $syncSourceStockStmt->execute([
                                (int)$item['product_id'],
                                (string)$item['sku'],
                            ]);
                        }
                    }
                }

                $deleteItemsStmt->execute([$id]);
                $deleteOrderStmt->execute([$id]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Delete orders failed: ' . $e->getMessage());
        }
    }

    $query = http_build_query([
        'page' => max(1, (int)($_POST['current_page'] ?? 1)),
        'search' => (string)($_POST['current_search'] ?? ''),
        'month' => (string)($_POST['current_month'] ?? ''),
    ]);
    header('Location: orders.php?' . $query);
    exit;
}

// ====================
// åˆ†é¡µ & æœç´¢ & æœˆä»½ç­›é€‰
// ====================
$limit = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$month  = $_GET['month'] ?? '';

$where = "WHERE archived_at IS NULL AND COALESCE(order_status, 'pending') <> 'draft'";
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
$total_orders = (int)$stmt->fetchColumn();
$total_pages = max(1, ceil($total_orders / $limit));

$sql = "SELECT * FROM orders $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary
$paidCount = 0;
$pendingCount = 0;
$pageTotal = 0;

foreach ($orders as $order) {
    $pageTotal += (float)$order['total'];

    if ($order['status'] === 'paid') {
        $paidCount++;
    } else {
        $pendingCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>è®¢å•ç®¡ç†</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<style>
<?php include __DIR__ . '/assets/css/orders.css'; ?>
</style>

<script>
<?php include __DIR__ . '/assets/js/orders.js.php'; ?>
</script>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>è®¢å•ç®¡ç†</h2>
      <p>æŸ¥çœ‹è®¢å•è®°å½•ã€ä»˜æ¬¾çŠ¶æ€ã€æ”¶æ®å’Œæ‰¹é‡å¤„ç†è®¢å•</p>
    </div>
  </section>

  <section class="orders-summary">
    <div class="summary-card">
      <span>è®¢å•æ•°é‡</span>
      <strong><?= $total_orders ?></strong>
      <small>ç¬¦åˆå½“å‰ç­›é€‰æ¡ä»¶</small>
    </div>

    <div class="summary-card">
      <span>æœ¬é¡µé”€å”®é¢</span>
      <strong>RM <?= number_format($pageTotal, 2) ?></strong>
      <small>å½“å‰é¡µé¢è®¢å•åˆè®¡</small>
    </div>

    <div class="summary-card">
      <span>æœ¬é¡µå·²ä»˜æ¬¾</span>
      <strong><?= $paidCount ?></strong>
      <small>Paid orders</small>
    </div>

    <div class="summary-card">
      <span>æœ¬é¡µæœªä»˜æ¬¾</span>
      <strong><?= $pendingCount ?></strong>
      <small>Pending orders</small>
    </div>
  </section>

  <form method="get" class="search-form">
    <input type="text"
           name="search"
           placeholder="è¾“å…¥è®¢å•å·"
           value="<?= htmlspecialchars($search) ?>">

    <input type="month"
           name="month"
           value="<?= htmlspecialchars($month) ?>">

    <button type="submit" class="btn btn-edit">ðŸ” æœç´¢</button>
    <a href="orders.php" class="btn btn-move">é‡ç½®</a>
  </form>

  <form method="post" onsubmit="return confirmBatchAction(event);">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="current_page" value="<?= $page ?>">
    <input type="hidden" name="current_search" value="<?= htmlspecialchars($search) ?>">
    <input type="hidden" name="current_month" value="<?= htmlspecialchars($month) ?>">
    <div class="table-wrapper">
      <table class="orders-table">
        <tr>
          <th>
            <input type="checkbox" class="select-check" onclick="toggleSelectAll(this)">
          </th>
          <th>è®¢å•å·</th>
          <th>ä¸‹å•æ—¶é—´</th>
          <th>æ€»é‡‘é¢</th>
          <th>ä»˜æ¬¾çŠ¶æ€</th>
          <th>æ“ä½œ</th>
        </tr>

        <?php if(empty($orders)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">æš‚æ— è®¢å•è®°å½•</div>
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($orders as $o): ?>
          <?php
            $timeFormatted = date("Yå¹´næœˆjæ—¥ H:i", strtotime($o['created_at']));
            $orderRegion = strtolower(trim((string)($o['region'] ?? '')));
            $isEastMalaysia = $orderRegion === 'east';
            $regionLabel = $isEastMalaysia ? 'ä¸œé©¬è®¢å•' : 'è¥¿é©¬è®¢å•';
            $hasGift = (float)$o['total'] >= 29.90;
          ?>

          <tr>
            <td>
              <input type="checkbox"
                     class="select-check"
                     name="order_ids[]"
                     value="<?= $o['id'] ?>">
            </td>

            <td>
              <span class="order-number">
                <?= htmlspecialchars($o['order_number']) ?>
              </span>
            </td>

            <td>
              <span class="order-time">
                <?= $timeFormatted ?>
              </span>
            </td>

            <td>
              <span class="amount">
                RM <?= number_format($o['total'], 2) ?>
              </span>
              <div class="amount-details">
                <div><?= $regionLabel ?></div>
                <div>è¿è´¹ RM <?= number_format((float)($o['shipping'] ?? 0), 2) ?></div>
                <?php if ($hasGift): ?>
                  <div class="amount-gift">èµ ï¼š1åŒ…é­”èŠ‹çˆ½ + å°æŒ‚ä»¶</div>
                <?php else: ?>
                  <div>èµ ï¼šæ— </div>
                <?php endif; ?>
              </div>
            </td>

            <td>
              <?php if ($o['status'] === 'paid'): ?>
                <span class="status-badge status-paid">
                  âœ… å·²ä»˜æ¬¾
                </span>
              <?php else: ?>
                <span class="status-badge status-pending">
                  âŒ æœªä»˜æ¬¾
                </span>
              <?php endif; ?>
            </td>

            <td>
              <div class="row-actions">
                <?php if ($o['status'] === 'paid'): ?>
                  <button type="submit"
                          name="mark_unpaid_id"
                          value="<?= (int)$o['id'] ?>"
                          class="btn btn-move"
                          onclick="return confirm('ç¡®å®šæ”¹ä¸ºæœªä»˜æ¬¾å—ï¼Ÿ')">
                    æœªä»˜æ¬¾
                  </button>
                <?php else: ?>
                  <button type="submit"
                          name="mark_paid_id"
                          value="<?= (int)$o['id'] ?>"
                          class="btn btn-edit"
                          onclick="return confirm('ç¡®å®šæ”¹ä¸ºå·²ä»˜æ¬¾å—ï¼Ÿ')">
                    å·²ä»˜æ¬¾
                  </button>
                <?php endif; ?>
                <button type="submit"
                        name="archive_order_id"
                        value="<?= (int)$o['id'] ?>"
                        class="btn btn-delete">
                  å½’æ¡£
                </button>
                <button type="submit"
                        name="delete_order_id"
                        value="<?= (int)$o['id'] ?>"
                        class="btn btn-delete"
                        onclick="return confirm('ç¡®å®šè¦æ°¸ä¹…åˆ é™¤è¿™ç¬”è®¢å•å—ï¼Ÿæ­¤æ“ä½œä¸å¯æ¢å¤ï¼')">
                  åˆ é™¤
                </button>
                <a href="../frontend/receipt.php?order_number=<?= urlencode($o['order_number']) ?>&token=admin"
                   target="_blank"
                   class="btn btn-move receipt-btn">
                   ðŸ§¾ æ”¶æ®
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </form>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&month=<?= urlencode($month) ?>"
           class="<?= $i == $page ? 'active' : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</main>

</body>
</html>

