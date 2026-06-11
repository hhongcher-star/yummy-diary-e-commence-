<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$mode = $_GET['mode'] ?? 'add';

$sku = trim($_POST['sku'] ?? '');

switch ($mode) {

    // ====================
    // 获取购物车
    // ====================
    case 'getCart':
        break;

    // ====================
    // 删除一个商品
    // ====================
    case 'removeOne':

        foreach ($_SESSION['cart'] as $k => &$item) {

            if ((string)$item['sku'] === (string)$sku) {

                $item['qty']--;

                if ($item['qty'] <= 0) {
                    unset($_SESSION['cart'][$k]);
                }

                break;
            }
        }

        unset($item);

        $_SESSION['cart'] = array_values($_SESSION['cart']);

        break;

    // ====================
    // 清空购物车
    // ====================
    case 'clear':

        $_SESSION['cart'] = [];

        break;

    // ====================
    // 添加商品
    // ====================
    case 'add':
    default:

        if ($sku === '') {

            echo json_encode([
                'success' => false,
                'message' => 'Missing SKU'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        // 只从数据库取商品资料
        $stmt = $pdo->prepare("
            SELECT
                id,
                sku,
                name,
                price,
                image_url,
                stock
            FROM products
            WHERE sku = ?
            LIMIT 1
        ");

        $stmt->execute([$sku]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {

            echo json_encode([
                'success' => false,
                'message' => '商品不存在'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $stock = (int)$product['stock'];

        if ($stock <= 0) {

            echo json_encode([
                'success' => false,
                'message' => '❌ 库存不足'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $found = false;

        foreach ($_SESSION['cart'] as &$item) {

            if ((string)$item['sku'] === (string)$sku) {

                if ($item['qty'] < $stock) {

                    $item['qty']++;

                } else {

                    echo json_encode([
                        'success' => false,
                        'message' => '⚠️ 已达到库存上限'
                    ], JSON_UNESCAPED_UNICODE);

                    exit;
                }

                $found = true;

                break;
            }
        }

        unset($item);

        if (!$found) {

            $_SESSION['cart'][] = [
                'id'    => (int)$product['id'],
                'sku'   => $product['sku'],
                'name'  => $product['name'],
                'price' => (float)$product['price'],
                'img'   => $product['image_url'],
                'qty'   => 1
            ];
        }

        break;
}

// ====================
// 计算购物车数量
// ====================

$itemCount = 0;

foreach ($_SESSION['cart'] as $item) {
    $itemCount += $item['qty'];
}

echo json_encode([
    'success' => true,
    'count'   => $itemCount,
    'cart'    => array_values($_SESSION['cart'])
], JSON_UNESCAPED_UNICODE);

exit;
