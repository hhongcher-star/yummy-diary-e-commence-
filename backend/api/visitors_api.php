<?php
require __DIR__ . '/../auth_admin.php';
require __DIR__ . '/../../config.php';

header('Content-Type: application/json');

try {

    // 累计访客
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM visitors
    ");
    $total_visitors = $stmt->fetchColumn();

    // 最近 7 天访客趋势
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as day,
            COUNT(*) as cnt
        FROM visitors
        GROUP BY day
        ORDER BY day DESC
        LIMIT 7
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $visitors = [];

    foreach (array_reverse($rows) as $r) {
        $labels[] = $r['day'];
        $visitors[] = (int)$r['cnt'];
    }

    echo json_encode([
        "total_visitors" => (int)$total_visitors,
        "labels" => $labels,
        "visitors" => $visitors
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "error" => $e->getMessage()
    ]);
}