<?php
session_start();

// ====================
// 开启调试（开发用，生产建议关闭）
// ====================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ====================
// 访问密钥
// ====================
$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    echo json_encode(["error" => "❌ 未授权访问"]);
    exit;
}

require '../config.php';
header('Content-Type: application/json');

try {
    // ====================
    // 如果请求 type=trend → 返回最近 7 天销售额 + 订单数
    // ====================
    if (isset($_GET['type']) && $_GET['type'] === 'trend') {
        $stmt = $pdo->prepare("SELECT DATE(created_at) as day, 
                                      SUM(total) as sales,
                                      COUNT(*) as orders
                               FROM orders 
                               WHERE status='paid' 
                               GROUP BY day 
                               ORDER BY day DESC 
                               LIMIT 7");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $labels = [];
        $sales  = [];
        $orders = [];

        foreach (array_reverse($rows) as $r) {
            $labels[] = $r['day'];
            $sales[]  = $r['sales'] ? (float)$r['sales'] : 0;
            $orders[] = (int)$r['orders'];
        }

        echo json_encode([
            "labels" => $labels,
            "sales"  => $sales,
            "orders" => $orders
        ]);
        exit;
    }

    // ====================
    // 默认 → 今日订单、本月销售额
    // ====================
    // 今日已付款订单数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders 
                           WHERE DATE(created_at) = CURDATE()
                           AND status = 'paid'");
    $stmt->execute();
    $today_orders = $stmt->fetchColumn();

    // 本月已付款销售额
    $stmt = $pdo->prepare("SELECT SUM(total) FROM orders 
                           WHERE MONTH(created_at) = MONTH(CURDATE()) 
                           AND YEAR(created_at) = YEAR(CURDATE())
                           AND status = 'paid'");
    $stmt->execute();
    $month_sales = $stmt->fetchColumn();
    $month_sales = $month_sales ? $month_sales : 0;

    echo json_encode([
        "today_orders" => intval($today_orders),
        "month_sales"  => floatval($month_sales)
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}

