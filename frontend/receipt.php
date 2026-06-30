<?php
// æ”¶æ®é¡µé¢ï¼šç”¨æˆ·ç»“è´¦åŽæ˜¾ç¤ºè®¢å•ç¼–å·ã€è®¢å•æ˜Žç»†å’Œä»˜æ¬¾ç¡®è®¤çŠ¶æ€ã€‚
session_start();
require __DIR__ . '/../config.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

$order_number = $_GET['order_number'] ?? '';
$access_token = $_GET['token'] ?? '';

if (!$order_number || !$access_token) {
    die("âŒ è®¢å•è®¿é—®èµ„æ–™ç¼ºå¤±");
}

$isAdmin = !empty($_SESSION['admin_id']);
$isPendingReceipt = false;

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=?");
    $stmt->execute([$order_number]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=? AND access_token=?");
    $stmt->execute([$order_number, $access_token]);
}

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("âŒ æ‰¾ä¸åˆ°è®¢å•ï¼Œè¯·è¿”å›žé‡æ–°ä¸‹å•ã€‚");
}

$stmt_items = $pdo->prepare(
    "SELECT oi.*,
            COALESCE(pv.image_url, p.image_url) AS item_image_url
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     LEFT JOIN product_variants pv ON pv.product_id = oi.product_id AND pv.sku = oi.sku
     WHERE oi.order_id=?
     ORDER BY oi.id"
);
$stmt_items->execute([$order['id']]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$order_data = [
    "id"          => $order['order_number'],
    "time"        => $order['created_at'],
    "status"      => $order['status'],
    "shipping"    => (float)($order['shipping'] ?? 0),
    "grand_total" => (float)($order['grand_total'] ?? 0),
    "items"       => []
];

foreach ($items as $it) {
    $order_data['items'][] = [
        "sku"   => $it['sku'] ?? '',
        "name"  => $it['product_name'],
        "qty"   => (int)$it['quantity'],
        "price" => (float)$it['price'],
        "image" => $it['item_image_url'] ?? ''
    ];
}

$isPendingReceipt = (($order['order_status'] ?? 'pending') === 'draft');

$timeFormatted = date("Yå¹´næœˆjæ—¥ H:i", strtotime($order_data['time']));

$total = 0;
foreach ($order_data['items'] as $item) {
    $total += $item['price'] * $item['qty'];
}

$shipping_cost = (float)$order_data['shipping'];
$grand_total = (float)$order_data['grand_total'];

$gifts = [];
$isEastMalaysia = strtolower(trim((string)($order['region'] ?? ''))) === 'east';
$region_label = $isEastMalaysia ? 'ä¸œé©¬' : 'è¥¿é©¬';
$shipping_tier = 'æ™®é€šè¿è´¹';

if ($total >= 49.90) {
    $shipping_tier = 'æ»¡ RM49.90';
} elseif ($total >= 39.90) {
    $shipping_tier = 'æ»¡ RM39.90';
} elseif ($total >= 29.90) {
    $shipping_tier = 'æ»¡ RM29.90';
} elseif ($total >= 19.90) {
    $shipping_tier = 'æ»¡ RM19.90';
}

$shipping_text = $shipping_cost > 0
    ? 'è¿è´¹ RM' . number_format($shipping_cost, 2)
    : 'å…è¿';
$shipping_msg = "ðŸšš {$region_label}{$shipping_tier}ï¼š{$shipping_text}";

if ($total >= 29.90) {
    $shipping_msg .= '<br>ðŸŽ èµ å“ï¼š1åŒ…é­”èŠ‹çˆ½ + å°æŒ‚ä»¶';
    $gifts = ["é­”èŠ‹çˆ½", "å°æŒ‚ä»¶"];
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<title>è®¢å•æ”¶æ® - Yummy Diary</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
<?php include __DIR__ . '/assets/css/receipt.css'; ?>
</style>
</head>

<body>

<div class="header">
  <a href="<?= htmlspecialchars(appUrl('shop'), ENT_QUOTES) ?>" class="back-btn">
    <i class="fas fa-arrow-left"></i> è¿”å›žèœå•
  </a>

  <img src="/yummy-diary/images/çŒ«_è´­ç‰©è¢‹.jpg" alt="Yummy Diary">
  <h2>ðŸ§¾ Yummy Diary Â· è®¢å•æ”¶æ®</h2>
</div>

<div class="container" id="receipt">
  <p><strong>è®¢å•å·:</strong> <?= htmlspecialchars($order_data['id']) ?></p>
  <p><strong>ä¸‹å•æ—¶é—´:</strong> <?= htmlspecialchars($timeFormatted) ?></p>

  <table>
    <tr>
      <th>æ•°é‡</th>
      <th>ç…§ç‰‡</th>
      <th>å•†å“</th>
      <th>å•ä»· (RM)</th>
      <th>å°è®¡ (RM)</th>
    </tr>

    <?php foreach ($order_data['items'] as $item): ?>
      <?php
        $subtotal = $item['price'] * $item['qty'];
        $skuLabel = $item['sku'] ? "[" . htmlspecialchars($item['sku']) . "] " : "";
      ?>
      <tr>
        <td><?= (int)$item['qty'] ?></td>
        <td>
          <img src="<?= htmlspecialchars(productImageUrl($item['image']), ENT_QUOTES) ?>"
               alt="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
               style="width:46px;height:46px;object-fit:cover;border:1px solid #eee;border-radius:8px;background:#fff;"
               onerror="this.onerror=null;this.src='<?= htmlspecialchars(productImageUrl(null), ENT_QUOTES) ?>';">
        </td>
        <td><?= $skuLabel . htmlspecialchars($item['name']) ?></td>
        <td><?= number_format($item['price'], 2) ?></td>
        <td><?= number_format($subtotal, 2) ?></td>
      </tr>
    <?php endforeach; ?>

    <?php foreach ($gifts as $gift): ?>
      <tr>
        <td>1</td>
        <td></td>
        <td>[èµ ] <?= htmlspecialchars($gift) ?></td>
        <td>0.00</td>
        <td>0.00</td>
      </tr>
    <?php endforeach; ?>

    <tr>
      <td colspan="4" style="text-align:right;">è¿è´¹</td>
      <td><?= number_format($shipping_cost, 2) ?></td>
    </tr>
  </table>

  <p class="total">å•†å“æ€»é¢: RM <?= number_format($total, 2) ?></p>
  <p class="total">è¿è´¹: RM <?= number_format($shipping_cost, 2) ?></p>
  <p class="total">æ€»ä»·: RM <?= number_format($grand_total, 2) ?></p>

  <div class="shipping">
    <?= $shipping_msg ?>
  </div>

  <div class="footer">
    <h3>ðŸ’³ ä»˜æ¬¾æç¤º</h3>
    <p>è¯·è”ç³»å•†å®¶èŽ·å–ä»˜æ¬¾æ–¹å¼ã€‚</p>

    <h3>ðŸ“© è”ç³»æ–¹å¼</h3>
    <p>
      <a href="https://www.instagram.com/yummydiaryy_" target="_blank" style="color:#333;text-decoration:none;">
        <i class="fab fa-instagram contact-icon"></i>@yummydiaryy_
      </a>
      <br>
      <a href="https://wa.me/60106654042" target="_blank" style="color:#333;text-decoration:none;">
        <i class="fab fa-whatsapp contact-icon"></i> +60 10 665 4042
      </a>
    </p>
  </div>

  <div class="btn-row">
    <button type="button" id="openPayment" class="btn-short">
      <i class="fas fa-credit-card"></i> <strong>ä»˜æ¬¾</strong>
    </button>

  </div>
</div>

<div id="paymentModal" class="payment-modal" role="dialog" aria-modal="true" aria-labelledby="paymentTitle">
  <div class="payment-card">
    <button type="button" id="closePayment" class="payment-close" aria-label="å…³é—­">&times;</button>
    <h3 id="paymentTitle">ä»˜æ¬¾</h3>
    <p>
      è¯·æŠŠä»˜æ¬¾è®°å½•å‘ç»™
      <a href="https://www.instagram.com/yummydiaryy_?utm_source=ig_web_button_share_sheet&amp;igsh=ZDNlZDc0MzIxNw=="
         target="_blank"
         rel="noopener noreferrer">@yummydiaryy_</a>
    </p>
    <img id="paymentImage" src="/yummy-diary/images/payment-qr.png" alt="Touch 'n Go ä»˜æ¬¾äºŒç»´ç ">
    <a class="payment-instagram"
       href="https://www.instagram.com/yummydiaryy_?utm_source=ig_web_button_share_sheet&amp;igsh=ZDNlZDc0MzIxNw==="
       target="_blank"
       rel="noopener noreferrer">
      æ‰“å¼€ Instagram å‘é€ä»˜æ¬¾è®°å½•
    </a>
    <a class="payment-home" href="<?= htmlspecialchars(appUrl(), ENT_QUOTES) ?>">
      <i class="fas fa-home"></i> å›žåˆ°ä¸»é¡µ
    </a>
  </div>
</div>

<div id="imagePreview" class="image-preview" role="dialog" aria-modal="true" aria-label="ä»˜æ¬¾å›¾ç‰‡æ”¾å¤§é¢„è§ˆ">
  <button type="button" id="closePreview" class="preview-close" aria-label="å…³é—­">&times;</button>
  <img src="/yummy-diary/images/payment-qr.png" alt="æ”¾å¤§çš„ Touch 'n Go ä»˜æ¬¾äºŒç»´ç ">
</div>

<script>
<?php include __DIR__ . '/assets/js/receipt.js.php'; ?>
</script>

</body>
</html>

