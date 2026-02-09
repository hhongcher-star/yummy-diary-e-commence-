<?php
// 确保 session 开启
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php'; // ✅ 数据库连接

// 统一 JSON 返回头
header('Content-Type: application/json; charset=UTF-8');

// 确保 cart 存在
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$mode = $_GET['mode'] ?? 'add'; // 默认模式是 add

$sku   = trim($_POST['sku'] ?? '');
$name  = trim($_POST['name'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$img   = trim($_POST['img'] ?? '');

switch ($mode) {
    case 'getCart': // ✅ 获取购物车
        break;

    case 'removeOne': // ✅ 删除一个商品
        foreach ($_SESSION['cart'] as $k => &$item) {
            if ((string)$item['sku'] === (string)$sku) {
                $item['qty']--;
                if ($item['qty'] <= 0) {
                    unset($_SESSION['cart'][$k]); // 数量 <=0 直接删掉
                }
                break;
            }
        }
        unset($item);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // 重排索引
        break;

    case 'clear': // ✅ 清空购物车
        $_SESSION['cart'] = [];
        break;

    case 'add': // ✅ 添加商品
    default:
        if ($sku !== '' && $name !== '') {
            // 🔎 查询数据库库存 + id
            $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE sku = ? LIMIT 1");
            $stmt->execute([$sku]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stock = $row ? (int)$row['stock'] : 0;
            $product_id = $row ? (int)$row['id'] : 0;

            if ($stock <= 0) {
                echo json_encode(['success' => false, 'message' => '❌ 库存不足'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $found = false;
            foreach ($_SESSION['cart'] as &$item) {
                if ((string)$item['sku'] === (string)$sku) {
                    if ($item['qty'] < $stock) {
                        $item['qty']++;
                    } else {
                        echo json_encode(['success' => false, 'message' => '⚠️ 已达到库存上限'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                $_SESSION['cart'][] = [
                    'id'    => $product_id, // ✅ 保存 product_id
                    'sku'   => $sku,
                    'name'  => $name,
                    'price' => $price,
                    'img'   => $img,
                    'qty'   => 1
                ];
            }
        }
        break;
}

// 计算购物车商品总数
$itemCount = 0;
foreach ($_SESSION['cart'] as $item) {
    $itemCount += $item['qty'];
}

// 返回 JSON 给前端
echo json_encode([
    'success' => true,
    'count'   => $itemCount,
    'cart'    => array_values($_SESSION['cart'])
], JSON_UNESCAPED_UNICODE);

exit; 

