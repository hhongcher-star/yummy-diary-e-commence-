<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    // 模糊搜索：商品名 + SKU + 拼音
    $sql = "
        SELECT id, sku, name, image_url, price, stock 
        FROM products 
        WHERE name LIKE ? 
           OR sku LIKE ? 
           OR (pinyin IS NOT NULL AND pinyin LIKE ?)
        ORDER BY created_at DESC 
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $like = "%{$q}%";
    $stmt->execute([$like, $like, $like]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 输出 JSON
echo json_encode($results, JSON_UNESCAPED_UNICODE);

