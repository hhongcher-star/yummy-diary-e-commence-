<?php
session_start();
require 'config.php';

// ====================
// 购物车检查
// ====================
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    echo json_encode(["success" => false, "msg" => "购物车为空"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ====================
// 生成订单号 & 时间
// ====================
$order_number = "2025" . date("mdHis") . "-" . rand(100,999);
$order_time   = date("Y-m-d H:i:s");

// ====================
// 计算总价 + 库存检测
// ====================
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    // 查询数据库库存
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE sku = ? LIMIT 1");
    $stmt->execute([$item['sku']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["success" => false, "msg" => "商品不存在: " . $item['name']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stock = (int)$row['stock'];
    if ($stock < $item['qty']) {
        echo json_encode([
            "success" => false, 
            "msg" => "库存不足: " . $item['name'] . " (剩余: $stock)"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 计算小计
    $total += $item['price'] * $item['qty'];
}

// ====================
// 暂存订单到 SESSION（不写数据库）
// ====================
$_SESSION['pending_order'] = [
    "order_number" => $order_number,
    "created_at"   => $order_time,
    "items"        => $_SESSION['cart'],
    "total"        => $total
];

// ====================
// 返回 JSON → 前端跳转 receipt 页面（SEO友好）
// ====================
echo json_encode([
    "success" => true,
    "order_number" => $order_number,
    "redirect" => "/receipt?order_number=" . urlencode($order_number)  // ✅ 改成无 .php URL
], JSON_UNESCAPED_UNICODE);
exit; 