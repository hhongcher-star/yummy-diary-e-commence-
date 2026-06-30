<?php
// 结账 API：根据购物车内容创建订单，并返回收据页面地址。
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
        'SELECT id, sku, name, product_type, parent_product_id, price, image_url, stock
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

        if ($variantId <= 0 && (
            ($product['product_type'] ?? 'single') !== 'single'
            || !empty($product['parent_product_id'])
        )) {
            throw new RuntimeException('购物车商品资料已更新，请清除后重新加入');
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

    if ($region === 'east') {
        $shipping = 12.90;

        if ($total >= 49.90) {
            $shipping = 4.90;
        } elseif ($total >= 39.90) {
            $shipping = 6.90;
        } elseif ($total >= 29.90) {
            $shipping = 8.90;
        } elseif ($total >= 19.90) {
            $shipping = 10.90;
        }
    } else {
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
            (order_number, access_token, created_at, total, shipping, grand_total, region, status, currency, order_status)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, 'pending', 'MYR', 'draft')"
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

    foreach ($items as $item) {
        $itemStmt->execute([
            $orderId,
            $item['id'],
            $item['sku'],
            $item['name'],
            $item['qty'],
            $item['price'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_number' => $orderNumber,
        'access_token' => $accessToken,
        'region' => $region,
        'shipping' => $shipping,
        'grand_total' => $grandTotal,
        'receipt_url' => appUrl('receipt') . '?order_number=' . rawurlencode($orderNumber)
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
