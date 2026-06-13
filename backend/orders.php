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

// 批量归档
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
            $itemStmt = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id=?');
            $stockStmt = $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?');
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
                        $stockStmt->execute([(int)$item['quantity'], (int)$item['product_id']]);
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

    header("Location: orders.php");
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

$where = "WHERE archived_at IS NULL";
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
<title>订单管理</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<style>
  .orders-summary{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:14px;
    margin-bottom:22px;
  }

  .summary-card{
    background:#fffaf4;
    border:1px solid var(--line);
    border-radius:22px;
    padding:18px;
    box-shadow:0 10px 28px rgba(120,90,60,.08);
  }

  .summary-card span{
    display:block;
    color:var(--muted);
    font-size:13px;
    font-weight:700;
    margin-bottom:6px;
  }

  .summary-card strong{
    display:block;
    font-size:26px;
    color:var(--text);
  }

  .summary-card small{
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:12px;
  }

  .search-form{
    background:rgba(255,255,255,.9);
    border:1px solid var(--line);
    border-radius:24px;
    padding:18px;
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    align-items:center;
    box-shadow:var(--shadow);
    margin-bottom:22px;
  }

  .search-form input{
    min-width:220px;
  }

  .batch-actions{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:center;
    margin-bottom:14px;
  }

  .orders-table td{
    vertical-align:middle;
  }

  .order-number{
    font-weight:800;
    color:var(--text);
    white-space:nowrap;
  }

  .order-time{
    color:var(--muted);
    white-space:nowrap;
    font-size:13px;
  }

  .amount{
    font-weight:900;
    white-space:nowrap;
  }

  .status-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:7px 12px;
    border-radius:999px;
    font-weight:800;
    font-size:13px;
    white-space:nowrap;
    cursor:pointer;
  }

  .status-paid{
    background:#eefaf0;
    color:#2f8f46;
    border:1px solid #cdebd3;
  }

  .status-pending{
    background:#fff4f4;
    color:#d64545;
    border:1px solid #ffd5d5;
  }

  .select-check{
    width:18px;
    height:18px;
    accent-color:#c9a984;
  }

  .receipt-btn{
    min-width:92px;
  }

  .pagination{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:20px;
  }

  .pagination a{
    min-width:38px;
    height:38px;
    padding:0 12px;
    border-radius:14px;
    border:1px solid var(--line);
    background:#fff;
    color:var(--text);
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
  }

  .pagination a.active{
    background:#ead6bd;
    color:#3b2a20;
    border-color:#ead6bd;
    box-shadow:0 10px 24px rgba(120,90,60,.16);
  }

  .empty-state{
    padding:34px;
    text-align:center;
    color:var(--muted);
    font-weight:700;
  }

  @media(max-width:768px){
    .search-form input{
      min-width:100%;
    }

    .batch-actions{
      flex-direction:column;
      align-items:stretch;
    }

    .batch-actions .btn{
      width:100%;
    }
  }
</style>

<script>
  function toggleSelectAll(source) {
    let checkboxes = document.querySelectorAll("input[name='order_ids[]']");
    checkboxes.forEach(cb => cb.checked = source.checked);
  }

  function confirmBatchAction(event) {
    const submitter = event.submitter;

    if (submitter && submitter.name === "toggle_payment_id") {
      return true;
    }

    const selected = document.querySelectorAll("input[name='order_ids[]']:checked");

    if (selected.length === 0) {
      alert("请先选择订单");
      event.preventDefault();
      return false;
    }

    if (submitter && submitter.name === "delete_selected") {
      return confirm("确定要批量删除选中的订单吗？此操作不可恢复！");
    }

    if (submitter && submitter.name === "mark_paid") {
      return confirm("确定将选中的订单标记为已付款吗？");
    }

    if (submitter && submitter.name === "mark_unpaid") {
      return confirm("确定将选中的订单标记为未付款吗？");
    }

    return true;
  }
</script>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>订单管理</h2>
      <p>查看订单记录、付款状态、收据和批量处理订单</p>
    </div>
  </section>

  <section class="orders-summary">
    <div class="summary-card">
      <span>订单数量</span>
      <strong><?= $total_orders ?></strong>
      <small>符合当前筛选条件</small>
    </div>

    <div class="summary-card">
      <span>本页销售额</span>
      <strong>RM <?= number_format($pageTotal, 2) ?></strong>
      <small>当前页面订单合计</small>
    </div>

    <div class="summary-card">
      <span>本页已付款</span>
      <strong><?= $paidCount ?></strong>
      <small>Paid orders</small>
    </div>

    <div class="summary-card">
      <span>本页未付款</span>
      <strong><?= $pendingCount ?></strong>
      <small>Pending orders</small>
    </div>
  </section>

  <form method="get" class="search-form">
    <input type="text"
           name="search"
           placeholder="输入订单号"
           value="<?= htmlspecialchars($search) ?>">

    <input type="month"
           name="month"
           value="<?= htmlspecialchars($month) ?>">

    <button type="submit" class="btn btn-edit">🔍 搜索</button>
    <a href="orders.php" class="btn btn-move">重置</a>
  </form>

  <form method="post" onsubmit="return confirmBatchAction(event);">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="current_page" value="<?= $page ?>">
    <input type="hidden" name="current_search" value="<?= htmlspecialchars($search) ?>">
    <input type="hidden" name="current_month" value="<?= htmlspecialchars($month) ?>">
    <div class="batch-actions">
      <button type="submit" name="mark_paid" value="1" class="btn btn-edit">标记已付款</button>
      <button type="submit" name="mark_unpaid" value="1" class="btn btn-move">标记未付款</button>
      <button type="submit" name="archive_selected" value="1" class="btn btn-delete">归档选中订单</button>
    </div>

    <div class="table-wrapper">
      <table class="orders-table">
        <tr>
          <th>
            <input type="checkbox" class="select-check" onclick="toggleSelectAll(this)">
          </th>
          <th>订单号</th>
          <th>下单时间</th>
          <th>总金额</th>
          <th>付款状态</th>
          <th>查看收据</th>
        </tr>

        <?php if(empty($orders)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">暂无订单记录</div>
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($orders as $o): ?>
          <?php $timeFormatted = date("Y年n月j日 H:i", strtotime($o['created_at'])); ?>

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
            </td>

            <td>
              <?php if ($o['status'] === 'paid'): ?>
                <button type="submit"
                        name="toggle_payment_id"
                        value="<?= (int)$o['id'] ?>"
                        class="status-badge status-paid"
                        onclick="return confirm('确定改为未付款吗？')">
                  ✅ 已付款
                </button>
              <?php else: ?>
                <button type="submit"
                        name="toggle_payment_id"
                        value="<?= (int)$o['id'] ?>"
                        class="status-badge status-pending"
                        onclick="return confirm('确定改为已付款吗？')">
                  ❌ 未付款
                </button>
              <?php endif; ?>
            </td>

            <td>
              <a href="../frontend/receipt.php?order_number=<?= urlencode($o['order_number']) ?>&token=admin"
                 target="_blank"
                 class="btn btn-move receipt-btn">
                 🧾 收据
              </a>
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
