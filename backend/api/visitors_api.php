<?php
session_start();
require '../config.php';

// ====================
// 访问密钥
// ====================
$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    echo json_encode(["error" => "❌ 未授权访问"]);
    exit;
}

header('Content-Type: application/json');

try {
    // 累计访客
    $stmt = $pdo->query("SELECT COUNT(*) FROM visitors");
    $total_visitors = $stmt->fetchColumn();

    // 最近 7 天访客趋势
    $stmt = $pdo->query("SELECT DATE(created_at) as day, COUNT(*) as cnt 
                         FROM visitors 
                         GROUP BY day 
                         ORDER BY day DESC 
                         LIMIT 7");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $visitors = [];
    foreach (array_reverse($rows) as $r) {
        $labels[] = $r['day'];
        $visitors[] = intval($r['cnt']);
    }

    echo json_encode([
        "total_visitors" => intval($total_visitors),
        "labels" => $labels,
        "visitors" => $visitors
    ]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}

