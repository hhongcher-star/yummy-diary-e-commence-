<?php
// 购物车 API：处理加入购物车、获取购物车、数量调整、删除和清空操作。
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
$flavor = trim($_POST['flavor'] ?? '');
$size = trim($_POST['size'] ?? '');
$cartKey = trim($_POST['cart_key'] ?? '');
$variantId = max(0, (int)($_POST['variant_id'] ?? 0));

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

            if ((string)($item['cart_key'] ?? $item['sku']) === (string)($cartKey !== '' ? $cartKey : $sku)) {

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

        if ($sku === '' && $variantId <= 0) {

            echo json_encode([
                'success' => false,
                'message' => 'Missing SKU'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        // 只从数据库取商品资料
        if ($variantId > 0) {
            $stmt = $pdo->prepare('SELECT p.id,p.sku,p.name,p.price,p.image_url,p.stock
              FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? LIMIT 1');
            $stmt->execute([$variantId]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT id,sku,name,price,image_url,stock
                 FROM products
                 WHERE sku=? AND parent_product_id IS NULL AND product_type='single'
                 LIMIT 1"
            );
            $stmt->execute([$sku]);
        }
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {

            echo json_encode([
                'success' => false,
                'message' => '商品不存在'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $variant = null;
        if ($variantId > 0) {
            $variantStmt = $pdo->prepare('SELECT * FROM product_variants WHERE id=? AND product_id=? LIMIT 1');
            $variantStmt->execute([$variantId, $product['id']]);
            $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);
            if (!$variant) {
                echo json_encode(['success'=>false,'message'=>'分类商品不存在'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $stock = (int)($variant['stock'] ?? $product['stock']);
        $cartSku = (string)($variant['sku'] ?? $product['sku']);
        $cartKey = $variant ? 'variant:' . $variant['id'] : $product['sku'];

        if ($stock <= 0) {

            echo json_encode([
                'success' => false,
                'message' => '❌ 库存不足'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $found = false;
        $totalProductQty = 0;
        foreach ($_SESSION['cart'] as $cartItem) {
            if ((string)($cartItem['cart_key'] ?? $cartItem['sku']) === $cartKey) {
                $totalProductQty += (int)$cartItem['qty'];
            }
        }

        if ($totalProductQty >= $stock) {
            echo json_encode([
                'success' => false,
                'message' => '已达到库存上限'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        foreach ($_SESSION['cart'] as &$item) {

            if ((string)($item['cart_key'] ?? $item['sku']) === $cartKey) {

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
                'variant_id' => $variant ? (int)$variant['id'] : null,
                'sku'   => $cartSku,
                'cart_key' => $cartKey,
                'name'  => $product['name'] . ($variant ? ' · ' . $variant['variant_name'] : ''),
                'price' => (float)($variant['price'] ?? $product['price']),
                'img'   => ($variant['image_url'] ?? null) ?: $product['image_url'],
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
