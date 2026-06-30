<?php
// 付款确认 API：确认订单付款状态，并更新订单相关会话或数据库状态。
session_start();

require __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Kuala_Lumpur');

$orderNumber = trim((string)($_POST['order_number'] ?? ''));
$accessToken = trim((string)($_POST['token'] ?? ''));

if ($orderNumber === '' || $accessToken === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'msg' => '订单访问资料缺失，请重新结算。',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->beginTransaction();

    $orderStmt = $pdo->prepare(
        "SELECT *
         FROM orders
         WHERE order_number = ? AND access_token = ?
         FOR UPDATE"
    );
    $orderStmt->execute([$orderNumber, $accessToken]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException('找不到待付款订单，请重新结算。');
    }

    if (($order['order_status'] ?? 'pending') !== 'draft') {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'order_number' => $order['order_number'],
            'access_token' => $order['access_token'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $itemStmt = $pdo->prepare('SELECT product_id, sku, quantity, product_name FROM order_items WHERE order_id = ?');
    $itemStmt->execute([(int)$order['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        throw new RuntimeException('订单商品资料缺失，请重新结算。');
    }

    $stockStmt = $pdo->prepare(
        'UPDATE products
         SET stock = stock - ?
         WHERE id = ? AND stock >= ?'
    );

    $variantStockStmt = $pdo->prepare(
        'UPDATE product_variants
         SET stock = stock - ?
         WHERE product_id = ? AND sku = ? AND stock >= ?'
    );

    $syncSourceStockStmt = $pdo->prepare(
        'UPDATE products p
         JOIN product_variants v ON v.source_product_id = p.id
         SET p.stock = v.stock
         WHERE v.product_id = ? AND v.sku = ?'
    );

    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        $productId = (int)$item['product_id'];
        $sku = (string)$item['sku'];

        $variantStockStmt->execute([$qty, $productId, $sku, $qty]);
        $stockUpdated = $variantStockStmt->rowCount() === 1;

        if ($stockUpdated) {
            $syncSourceStockStmt->execute([$productId, $sku]);
        } else {
            $stockStmt->execute([$qty, $productId, $qty]);
            $stockUpdated = $stockStmt->rowCount() === 1;
        }

        if (!$stockUpdated) {
            throw new RuntimeException('库存不足或更新失败：' . $item['product_name']);
        }
    }

    $paidStmt = $pdo->prepare("UPDATE orders SET order_status = 'pending' WHERE id = ? AND order_status = 'draft'");
    $paidStmt->execute([(int)$order['id']]);

    $pdo->commit();

    $_SESSION['order_access_tokens'][$order['order_number']] = $order['access_token'];
    unset($_SESSION['cart'], $_SESSION['orders'], $_SESSION['pending_order'], $_SESSION['pending_orders']);

    echo json_encode([
        'success' => true,
        'order_number' => $order['order_number'],
        'access_token' => $order['access_token'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'msg' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
