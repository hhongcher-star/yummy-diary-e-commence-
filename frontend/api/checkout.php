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

/**
 * region:
 * west = 西马
 * east = 东马
 *
 * 前端可以 POST region=west / east
 * 如果没有传，默认西马
 */
$region = $_POST['region'] ?? $_SESSION['region'] ?? 'west';
$region = strtolower(trim((string)$region));

if (!in_array($region, ['west', 'east'], true)) {
    $region = 'west';
}

$orderNumber = date('YmdHis') . '-' . random_int(100, 999);
$accessToken = bin2hex(random_bytes(32));
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

    $variantStmt = $pdo->prepare(
        'SELECT id, sku, variant_name, price, stock
         FROM product_variants
         WHERE id = ? AND product_id = ?
         FOR UPDATE'
    );

    foreach ($_SESSION['cart'] as $cartItem) {
        $productId = (int)($cartItem['id'] ?? 0);
        $variantId = (int)($cartItem['variant_id'] ?? 0);
        $qty = (int)($cartItem['qty'] ?? 0);

        if ($productId <= 0 || $qty <= 0) {
            throw new RuntimeException('购物车商品数据无效');
        }

        $productStmt->execute([$productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException('商品不存在');
        }

        $variant = null;

        if ($variantId > 0) {
            $variantStmt->execute([$variantId, $productId]);
            $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);

            if (!$variant) {
                throw new RuntimeException('分类商品不存在');
            }
        }

        $availableStock = (int)($variant['stock'] ?? $product['stock']);

        if ($availableStock < $qty) {
            throw new RuntimeException('库存不足：' . $product['name']);
        }

        $price = (float)($variant['price'] ?? $product['price']);
        $total += $price * $qty;

        $items[] = [
            'id' => (int)$product['id'],
            'variant_id' => $variantId,
            'sku' => $variant['sku'] ?? $product['sku'],
            'name' => trim((string)($cartItem['name'] ?? $product['name'])),
            'price' => $price,
            'qty' => $qty,
        ];
    }

    /**
     * 运费规则
     */
    if ($region === 'east') {
        // 东马
        $shipping = 15.90;

        if ($total >= 49.90) {
            $shipping = 9.90;
        } elseif ($total >= 39.90) {
            $shipping = 11.90;
        } elseif ($total >= 29.90) {
            $shipping = 12.90;
        } elseif ($total >= 19.90) {
            $shipping = 13.90;
        }
    } else {
        // 西马
        $shipping = 7.50;

        if ($total >= 49.90) {
            $shipping = 0.00;
        } elseif ($total >= 39.90) {
            $shipping = 1.90;
        } elseif ($total >= 29.90) {
            $shipping = 3.50;
        } elseif ($total >= 19.90) {
            $shipping = 5.90;
        }
    }

    $grandTotal = $total + $shipping;

    $orderStmt = $pdo->prepare(
        "INSERT INTO orders
            (order_number, access_token, created_at, total, shipping, grand_total, region, status, currency)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, 'pending', 'MYR')"
    );

    $orderStmt->execute([
        $orderNumber,
        $accessToken,
        $orderTime,
        $total,
        $shipping,
        $grandTotal,
        $region,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items
            (order_id, product_id, sku, product_name, quantity, price)
         VALUES
            (?, ?, ?, ?, ?, ?)'
    );

    $stockStmt = $pdo->prepare(
        'UPDATE products
         SET stock = stock - ?
         WHERE id = ? AND stock >= ?'
    );

    $variantStockStmt = $pdo->prepare(
        'UPDATE product_variants
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

        if ($item['variant_id'] > 0) {
            $variantStockStmt->execute([
                $item['qty'],
                $item['variant_id'],
                $item['qty'],
            ]);

            $stockUpdated = $variantStockStmt->rowCount() === 1;
        } else {
            $stockStmt->execute([
                $item['qty'],
                $item['id'],
                $item['qty'],
            ]);

            $stockUpdated = $stockStmt->rowCount() === 1;
        }

        if (!$stockUpdated) {
            throw new RuntimeException('库存更新失败：' . $item['name']);
        }
    }

    $pdo->commit();

    $_SESSION['order_access_tokens'][$orderNumber] = $accessToken;

    unset(
        $_SESSION['cart'],
        $_SESSION['pending_order'],
        $_SESSION['orders']
    );

    echo json_encode([
        'success' => true,
        'order_number' => $orderNumber,
        'access_token' => $accessToken,
        'region' => $region,
        'shipping' => $shipping,
        'grand_total' => $grandTotal,
        'receipt_url' => 'receipt?order_number=' . rawurlencode($orderNumber)
            . '&token=' . rawurlencode($accessToken),
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
