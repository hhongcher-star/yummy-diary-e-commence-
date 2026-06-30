<?php
// 搜索建议 API：用户输入关键词时返回匹配商品建议，供 header 搜索框使用。
session_start();
require __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    // 模糊搜索：商品名 + SKU + 拼音
    $sql = "
        SELECT p.id, p.sku, p.name, p.image_url, p.price, p.stock
        FROM products p
        WHERE p.parent_product_id IS NULL
          AND (
               p.name LIKE ?
            OR p.sku LIKE ?
            OR (p.pinyin IS NOT NULL AND p.pinyin LIKE ?)
            OR EXISTS (
                SELECT 1 FROM product_variants v
                WHERE v.product_id=p.id AND (v.variant_name LIKE ? OR v.sku LIKE ?)
            )
          )
        ORDER BY p.created_at DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $like = "%{$q}%";
    $stmt->execute([$like, $like, $like, $like, $like]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 输出 JSON
echo json_encode($results, JSON_UNESCAPED_UNICODE);

