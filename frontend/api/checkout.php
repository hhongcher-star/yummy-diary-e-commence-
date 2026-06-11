<?php
session_start();

require __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Kuala_Lumpur');

if (empty($_SESSION['cart'])) {
    echo json_encode([
        'success' => false,
        'msg' => '购物车为空',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderNumber = date('YmdHis') . '-' . random_int(100, 999);
$orderTime = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    $items = [];
    $total = 0.0;
    $productStmt = $pdo->prepare(
        'SELECT id, sku, name, price, image_url, stock
         FROM products
         WHERE id = ?
         FOR UPDATE'
    );

    foreach ($_SESSION['cart'] as $cartItem) {
        $productId = (int)($cartItem['id'] ?? 0);
        $qty = (int)($cartItem['qty'] ?? 0);

        if ($productId <= 0 || $qty <= 0) {
            throw new RuntimeException('购物车商品数据无效');
        }

        $productStmt->execute([$productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException('商品不存在');
        }
        if ((int)$product['stock'] < $qty) {
            throw new RuntimeException('库存不足：' . $product['name']);
        }

        $price = (float)$product['price'];
        $total += $price * $qty;
        $items[] = [
            'id' => (int)$product['id'],
            'sku' => $product['sku'],
            'name' => $product['name'],
            'price' => $price,
            'qty' => $qty,
        ];
    }

    $orderStmt = $pdo->prepare(
        "INSERT INTO orders (order_number, created_at, total, status)
         VALUES (?, ?, ?, 'pending')"
    );
    $orderStmt->execute([$orderNumber, $orderTime, $total]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items
            (order_id, product_id, sku, product_name, quantity, price)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stockStmt = $pdo->prepare(
        'UPDATE products
         SET stock = stock - ?
         WHERE id = ? AND stock >= ?'
    );

    foreach ($items as $item) {
        $itemStmt->execute([
            $orderId,
            $item['id'],
            $item['sku'],
            $item['name'],
            $item['qty'],
            $item['price'],
        ]);

        $stockStmt->execute([$item['qty'], $item['id'], $item['qty']]);
        if ($stockStmt->rowCount() !== 1) {
            throw new RuntimeException('库存更新失败：' . $item['name']);
        }
    }

    $pdo->commit();
    unset($_SESSION['cart'], $_SESSION['pending_order'], $_SESSION['orders']);

    echo json_encode([
        'success' => true,
        'order_number' => $orderNumber,
        'redirect' => '../receipt.php?order_number=' . urlencode($orderNumber),
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
