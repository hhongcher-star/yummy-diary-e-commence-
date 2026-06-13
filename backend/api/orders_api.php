<?php
require __DIR__ . '/../auth_admin.php';
require __DIR__ . '/../../config.php';
header('Content-Type: application/json');

try {

    // ====================
    // type=sales_summary → 根据 filter 返回销售额
    // ====================
    if (isset($_GET['type']) && $_GET['type'] === 'sales_summary') {

        $period = $_GET['period'] ?? 'month';
        $month  = $_GET['month'] ?? date('m');
        $year   = $_GET['year'] ?? date('Y');

        if ($period === 'today') {

            $sql = "SELECT SUM(total) FROM orders 
                    WHERE DATE(created_at) = CURDATE()
                    AND status = 'paid'";
            $stmt = $pdo->prepare($sql);
            $title = "今日销售额";

        } elseif ($period === 'week') {

            $sql = "SELECT SUM(total) FROM orders 
                    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
                    AND status = 'paid'";
            $stmt = $pdo->prepare($sql);
            $title = "本周销售额";

        } elseif ($period === 'year') {

            $sql = "SELECT SUM(total) FROM orders 
                    WHERE YEAR(created_at) = :year
                    AND status = 'paid'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':year', (int)$year, PDO::PARAM_INT);
            $title = $year . " 全年销售额";

        } elseif ($period === 'custom_month') {

            $sql = "SELECT SUM(total) FROM orders 
                    WHERE YEAR(created_at) = :year
                    AND MONTH(created_at) = :month
                    AND status = 'paid'";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':year', (int)$year, PDO::PARAM_INT);
            $stmt->bindValue(':month', (int)$month, PDO::PARAM_INT);
            $title = $year . " 年 " . $month . " 月销售额";

        } else {

            $sql = "SELECT SUM(total) FROM orders 
                    WHERE MONTH(created_at) = MONTH(CURDATE()) 
                    AND YEAR(created_at) = YEAR(CURDATE())
                    AND status = 'paid'";
            $stmt = $pdo->prepare($sql);
            $title = "本月销售额";
        }

        $stmt->execute();
        $sales = $stmt->fetchColumn();
        $sales = $sales ? $sales : 0;

        echo json_encode([
            "title" => $title,
            "sales" => floatval($sales)
        ]);
        exit;
    }

    // ====================
    // type=product_analysis → 商品销售分析 (仅返回销售数据，分类与库存由 product_api 协作)
    // ====================
    if (isset($_GET['type']) && $_GET['type'] === 'product_analysis') {

        $period   = $_GET['period'] ?? 'month';
        $month    = $_GET['month'] ?? date('m');
        $year     = $_GET['year'] ?? date('Y');
        $sort     = $_GET['sort'] ?? 'qty';
        $limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

        if ($limit <= 0) $limit = 50;

        if ($period === 'today') {
            $dateCondition = "DATE(o.created_at) = CURDATE()";
        } elseif ($period === 'week') {
            $dateCondition = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        } elseif ($period === 'year') {
            $dateCondition = "YEAR(o.created_at) = :year";
        } elseif ($period === 'custom_month') {
            $dateCondition = "YEAR(o.created_at) = :year AND MONTH(o.created_at) = :month";
        } else {
            $dateCondition = "YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
        }

        $orderByMap = [
            "qty" => "qty_sold DESC",
            "sales" => "sales DESC",
            "orders" => "order_count DESC",
            "avg_price" => "avg_price DESC"
        ];
        $orderBy = $orderByMap[$sort] ?? "qty_sold DESC";

        $sql = "
            SELECT 
                oi.sku,
                oi.product_name,
                SUM(oi.quantity) AS qty_sold,
                COUNT(DISTINCT o.id) AS order_count,
                SUM(oi.quantity * oi.price) AS sales,
                AVG(oi.price) AS avg_price
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.status = 'paid'
            AND $dateCondition
            GROUP BY oi.sku, oi.product_name
            ORDER BY $orderBy
            LIMIT $limit
        ";

        $stmt = $pdo->prepare($sql);
        if ($period === 'year' || $period === 'custom_month') $stmt->bindValue(':year', (int)$year, PDO::PARAM_INT);
        if ($period === 'custom_month') $stmt->bindValue(':month', (int)$month, PDO::PARAM_INT);

        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["products" => $products]);
        exit;
    }

    // ====================
    // type=trend → 返回最近 7 天销售额 + 订单数
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
