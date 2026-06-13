<?php
require __DIR__ . '/../auth_admin.php';
require __DIR__ . '/../../config.php';

header('Content-Type: application/json');

date_default_timezone_set('Asia/Kuala_Lumpur');

// ====================
// 分类分组（与 products.php 一致）
// ====================
$categoryGroups = [
    'snacks' => [
        'label' => '速食小吃',
        'children' => [
            'moyu'      => '魔芋爽',
            'xieliu'    => '蟹柳',
            'egg'       => '鹌鹑蛋',
            'tofu'      => '鱼豆腐',
            'latiao'    => '辣条',
            'jinzhen'   => '金针菇',
            'tudoupian' => '土豆片',
            'lianou'    => '莲藕片',
            'moyu2'     => '魔芋',
            'haidai'    => '海带',
            'other'     => '其他'
        ]
    ],

    'meals' => [
        'label' => '粉类/速食主食',
        'children' => [
            'noodle'   => '酸辣粉',
            'luosifen' => '螺蛳粉',
            'hotpot'   => '自热火锅'
        ]
    ],

    'candy' => [
        'label' => '糖果',
        'children' => [
            'qqcandy' => 'QQ糖果',
            'coffee'  => '咖啡糖',
            'other1'  => '其他'
        ]
    ],

    'chips' => [
        'label' => '脆片坚果类',
        'children' => [
            'lays'   => 'Lays 薯片',
            'other2' => '其他'
        ]
    ],

    'creative' => [
        'label' => '文创小物',
        'children' => [
            'creative' => '文创小物'
        ]
    ]
];

// ====================
// 展平成 category map
// ====================
$categories = [];
$categoryGroupLabels = [];

foreach ($categoryGroups as $groupKey => $group) {
    foreach ($group['children'] as $key => $label) {
        $categories[$key] = $label;
        $categoryGroupLabels[$key] = $group['label'];
    }
}

try {

    $type = $_GET['type'] ?? '';

    // ====================
    // type=categories → 给 dropdown 用
    // ====================
    if ($type === 'categories') {

        $result = [];
        $result[] = [
            "value" => "all",
            "label" => "全部分类",
            "group" => ""
        ];

        foreach ($categoryGroups as $groupKey => $group) {
            foreach ($group['children'] as $key => $label) {
                $result[] = [
                    "value" => $key,
                    "label" => $label,
                    "group" => $group['label']
                ];
            }
        }

        echo json_encode([
            "categories" => $result
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ====================
    // type=product_map → 给 dashboard 合并订单分析用
    // ====================
    if ($type === 'product_map') {

        $stmt = $pdo->prepare("
            SELECT 
                id,
                sku,
                name,
                category,
                stock,
                warning_level,
                is_hot,
                hot_order
            FROM products
            ORDER BY category ASC, sort_order ASC, id DESC
        ");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        $nameMap = [];

        foreach ($products as $p) {
            $categoryKey = $p['category'] ?? '';
            $categoryLabel = $categories[$categoryKey] ?? '未分类';
            $groupLabel = $categoryGroupLabels[$categoryKey] ?? '';

            $item = [
                "id" => (int)$p['id'],
                "sku" => $p['sku'],
                "name" => $p['name'],
                "category" => $categoryKey,
                "category_label" => $categoryLabel,
                "category_group" => $groupLabel,
                "stock" => (int)$p['stock'],
                "warning_level" => (int)$p['warning_level'],
                "is_hot" => (int)$p['is_hot'],
                "hot_order" => (int)$p['hot_order']
            ];

            // 用 sku 做 key，方便 dashboard merge
            if (!empty($p['sku'])) {
                $map[$p['sku']] = $item;
            }
            $nameMap[$p['name']] = $item;
        }

        echo json_encode([
            "products" => array_values($map),
            "product_map" => $map,
            "product_name_map" => $nameMap
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ====================
    // type=single_product → 查单个商品，可选
    // ====================
    if ($type === 'single_product') {

        $sku = $_GET['sku'] ?? '';

        if ($sku === '') {
            echo json_encode(["error" => "Missing sku"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT 
                id,
                sku,
                name,
                category,
                stock,
                warning_level,
                is_hot,
                hot_order
            FROM products
            WHERE sku = ?
            LIMIT 1
        ");
        $stmt->execute([$sku]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            echo json_encode(["product" => null], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $categoryKey = $p['category'] ?? '';
        $categoryLabel = $categories[$categoryKey] ?? '未分类';
        $groupLabel = $categoryGroupLabels[$categoryKey] ?? '';

        echo json_encode([
            "product" => [
                "id" => (int)$p['id'],
                "sku" => $p['sku'],
                "name" => $p['name'],
                "category" => $categoryKey,
                "category_label" => $categoryLabel,
                "category_group" => $groupLabel,
                "stock" => (int)$p['stock'],
                "warning_level" => (int)$p['warning_level'],
                "is_hot" => (int)$p['is_hot'],
                "hot_order" => (int)$p['hot_order']
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ====================
    // 默认
    // ====================
    echo json_encode([
        "error" => "Invalid type",
        "available_types" => [
            "categories",
            "product_map",
            "single_product"
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    echo json_encode([
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
