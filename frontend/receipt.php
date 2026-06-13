<?php
session_start();
require __DIR__ . '/../config.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

$order_number = $_GET['order_number'] ?? '';
$access_token = $_GET['token'] ?? '';

if (!$order_number || !$access_token) {
    die("❌ 订单访问资料缺失");
}

$isAdmin = !empty($_SESSION['admin_id']);

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=?");
    $stmt->execute([$order_number]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=? AND access_token=?");
    $stmt->execute([$order_number, $access_token]);
}

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("❌ 找不到订单，请返回重新下单。");
}

$stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
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
        "price" => (float)$it['price']
    ];
}

$timeFormatted = date("Y年n月j日 H:i", strtotime($order_data['time']));

$total = 0;
foreach ($order_data['items'] as $item) {
    $total += $item['price'] * $item['qty'];
}

$shipping_cost = (float)$order_data['shipping'];
$grand_total = (float)$order_data['grand_total'];

$gifts = [];
$shipping_msg = "";

if (abs($shipping_cost - 15.90) < 0.01) {
    $shipping_msg = "🚚 东马普通运费：RM15.90";
} elseif (abs($shipping_cost - 13.90) < 0.01) {
    $shipping_msg = "🚚 东马满 RM19.90：运费 RM13.90";
} elseif (abs($shipping_cost - 12.90) < 0.01) {
    $shipping_msg = "🚚 东马满 RM29.90：运费 RM12.90<br>🎁 赠品：1包魔芋爽 + 小挂件";
    $gifts = ["魔芋爽", "小挂件"];
} elseif (abs($shipping_cost - 11.90) < 0.01) {
    $shipping_msg = "🚚 东马满 RM39.90：运费 RM11.90<br>🎁 赠品：1包魔芋爽 + 小挂件";
    $gifts = ["魔芋爽", "小挂件"];
} elseif (abs($shipping_cost - 9.90) < 0.01) {
    $shipping_msg = "🚚 东马满 RM49.90：运费 RM9.90<br>🎁 赠品：1包魔芋爽 + 小挂件";
    $gifts = ["魔芋爽", "小挂件"];
} elseif (abs($shipping_cost - 7.50) < 0.01) {
    $shipping_msg = "🚚 西马普通运费：RM7.50";
} elseif (abs($shipping_cost - 5.90) < 0.01) {
    $shipping_msg = "🚚 西马满 RM19.90：运费 RM5.90";
} elseif (abs($shipping_cost - 3.50) < 0.01) {
    $shipping_msg = "🚚 西马满 RM29.90：运费 RM3.50<br>🎁 赠品：1包魔芋爽 + 小挂件";
    $gifts = ["魔芋爽", "小挂件"];
} elseif (abs($shipping_cost - 1.90) < 0.01) {
    $shipping_msg = "🚚 西马满 RM39.90：运费 RM1.90<br>🎁 赠品：1包魔芋爽 + 小挂件";
    $gifts = ["魔芋爽", "小挂件"];
} elseif (abs($shipping_cost - 0.00) < 0.01) {
    $shipping_msg = "🚚 西马满 RM49.90：免运<br>🎁 赠品：1包魔芋爽 + 小挂件";
    $gifts = ["魔芋爽", "小挂件"];
} else {
    $shipping_msg = "🚚 运费：RM" . number_format($shipping_cost, 2);
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<title>订单收据 - Yummy Diary</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
body{
  font-family:"Segoe UI",Arial,sans-serif;
  background:#fff;
  color:#333;
  margin:0;
  padding:0;
  text-align:center;
  animation:fadeIn .8s ease;
}

@keyframes fadeIn{
  from{opacity:0;transform:translateY(10px);}
  to{opacity:1;transform:translateY(0);}
}

.header{
  padding:20px;
  position:relative;
}

.header img{
  width:90px;
  height:auto;
}

h2{
  margin-top:10px;
  font-size:20px;
}

.container{
  max-width:500px;
  margin:20px auto;
  padding:20px;
  border:1px solid #eee;
  border-radius:12px;
  background:#fafafa;
  box-shadow:0 4px 12px rgba(0,0,0,.05);
}

p{
  margin:6px 0;
}

table{
  width:100%;
  border-collapse:collapse;
  margin:15px 0;
}

th,td{
  border:1px solid #ddd;
  padding:8px;
  font-size:14px;
}

th{
  background:#f5f5f5;
}

.total{
  text-align:right;
  font-size:16px;
  font-weight:bold;
  margin-top:10px;
}

.shipping{
  margin-top:15px;
  padding:12px;
  border:1px dashed #aaa;
  border-radius:10px;
  background:#fdfdfd;
  font-size:14px;
  text-align:left;
  line-height:1.7;
}

.footer{
  margin-top:20px;
  font-size:14px;
  color:#555;
}

.footer h3{
  margin:8px 0 5px;
  font-size:15px;
}

.contact-icon{
  font-size:18px;
  margin-right:6px;
  vertical-align:middle;
}

.btn-row{
  display:flex;
  justify-content:center;
  gap:12px;
  margin-top:18px;
  flex-wrap:wrap;
}

.btn-short{
  flex:1;
  max-width:120px;
  padding:10px;
  border:2px solid #333;
  border-radius:12px;
  background:transparent;
  color:#333;
  font-size:14px;
  cursor:pointer;
  transition:all .3s ease;
  text-decoration:none;
}

.btn-short i{
  margin-right:6px;
}

.btn-short:hover{
  background:#333;
  color:#fff;
}

.back-btn{
  position:absolute;
  top:15px;
  left:15px;
  padding:8px 14px;
  border:2px solid #333;
  border-radius:25px;
  background:transparent;
  color:#333;
  font-size:14px;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  gap:6px;
  transition:all .3s ease;
}

.back-btn:hover{
  background:#333;
  color:#fff;
}

.payment-modal{
  display:none;
  position:fixed;
  inset:0;
  z-index:3000;
  padding:20px;
  box-sizing:border-box;
  background:rgba(0,0,0,.55);
  align-items:center;
  justify-content:center;
}

.payment-modal.show{display:flex;}

.payment-card{
  position:relative;
  width:min(430px,100%);
  max-height:90vh;
  overflow-y:auto;
  padding:24px;
  box-sizing:border-box;
  border-radius:20px;
  background:#fff;
  box-shadow:0 18px 60px rgba(0,0,0,.25);
}

.payment-card h3{margin:0 40px 12px;font-size:18px;}
.payment-card p{margin:0 0 14px;line-height:1.6;}
.payment-card img{display:block;width:100%;height:auto;border-radius:12px;cursor:zoom-in;}

.payment-close{
  position:absolute;
  top:12px;
  right:12px;
  width:38px;
  height:38px;
  border:0;
  border-radius:50%;
  background:#f3f3f3;
  font-size:22px;
  cursor:pointer;
}

.payment-instagram{
  display:block;
  margin-top:16px;
  padding:12px 16px;
  border-radius:12px;
  background:#111;
  color:#fff;
  font-weight:700;
  text-decoration:none;
}

.payment-home{
  display:block;
  margin-top:10px;
  padding:11px 16px;
  border:2px solid #111;
  border-radius:12px;
  color:#111;
  font-weight:700;
  text-decoration:none;
}

.image-preview{
  display:none;
  position:fixed;
  inset:0;
  z-index:4000;
  padding:18px;
  box-sizing:border-box;
  background:rgba(0,0,0,.9);
  align-items:center;
  justify-content:center;
}

.image-preview.show{display:flex;}
.image-preview img{max-width:96vw;max-height:92vh;object-fit:contain;cursor:zoom-out;}

.preview-close{
  position:fixed;
  top:16px;
  right:16px;
  width:42px;
  height:42px;
  border:0;
  border-radius:50%;
  background:#fff;
  color:#111;
  font-size:24px;
  cursor:pointer;
}

@media(max-width:768px){
  .header img{
    width:70px;
  }

  h2{
    font-size:18px;
  }

  .container{
    width:92%;
    margin:15px auto;
    padding:15px;
    box-sizing:border-box;
  }

  th,td{
    font-size:12px;
    padding:6px;
  }

  .btn-row{
    gap:8px;
  }

  .btn-short{
    max-width:100px;
    font-size:13px;
    padding:8px;
  }

  .back-btn{
    padding:6px 12px;
    font-size:13px;
    top:10px;
    left:10px;
  }
}
</style>
</head>

<body>

<div class="header">
  <a href="/shop" class="back-btn">
    <i class="fas fa-arrow-left"></i> 返回菜单
  </a>

  <img src="/yummy-diary/images/猫_购物袋.jpg" alt="Yummy Diary">
  <h2>🧾 Yummy Diary · 订单收据</h2>
</div>

<div class="container" id="receipt">
  <p><strong>订单号:</strong> <?= htmlspecialchars($order_data['id']) ?></p>
  <p><strong>下单时间:</strong> <?= htmlspecialchars($timeFormatted) ?></p>

  <table>
    <tr>
      <th>数量</th>
      <th>商品</th>
      <th>单价 (RM)</th>
      <th>小计 (RM)</th>
    </tr>

    <?php foreach ($order_data['items'] as $item): ?>
      <?php
        $subtotal = $item['price'] * $item['qty'];
        $skuLabel = $item['sku'] ? "[" . htmlspecialchars($item['sku']) . "] " : "";
      ?>
      <tr>
        <td><?= (int)$item['qty'] ?></td>
        <td><?= $skuLabel . htmlspecialchars($item['name']) ?></td>
        <td><?= number_format($item['price'], 2) ?></td>
        <td><?= number_format($subtotal, 2) ?></td>
      </tr>
    <?php endforeach; ?>

    <?php foreach ($gifts as $gift): ?>
      <tr>
        <td>1</td>
        <td>[赠] <?= htmlspecialchars($gift) ?></td>
        <td>0.00</td>
        <td>0.00</td>
      </tr>
    <?php endforeach; ?>

    <tr>
      <td colspan="3" style="text-align:right;">运费</td>
      <td><?= number_format($shipping_cost, 2) ?></td>
    </tr>
  </table>

  <p class="total">商品总额: RM <?= number_format($total, 2) ?></p>
  <p class="total">运费: RM <?= number_format($shipping_cost, 2) ?></p>
  <p class="total">总价: RM <?= number_format($grand_total, 2) ?></p>

  <div class="shipping">
    <?= $shipping_msg ?>
  </div>

  <div class="footer">
    <h3>💳 付款提示</h3>
    <p>请联系商家获取付款方式。</p>

    <h3>📩 联系方式</h3>
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
      <i class="fas fa-credit-card"></i> <strong>付款</strong>
    </button>

    <button id="downloadPdf" class="btn-short">
      <i class="fas fa-file-pdf"></i> PDF
    </button>
  </div>
</div>

<div id="paymentModal" class="payment-modal" role="dialog" aria-modal="true" aria-labelledby="paymentTitle">
  <div class="payment-card">
    <button type="button" id="closePayment" class="payment-close" aria-label="关闭">&times;</button>
    <h3 id="paymentTitle">付款</h3>
    <p>
      请把付款记录发给
      <a href="https://www.instagram.com/itszweii__?utm_source=ig_web_button_share_sheet&amp;igsh=ZDNlZDc0MzIxNw=="
         target="_blank"
         rel="noopener noreferrer">@itszweii__</a>
    </p>
    <img id="paymentImage" src="/yummy-diary/images/payment-qr.png" alt="Touch 'n Go 付款二维码">
    <a class="payment-instagram"
       href="https://www.instagram.com/itszweii__?utm_source=ig_web_button_share_sheet&amp;igsh=ZDNlZDc0MzIxNw=="
       target="_blank"
       rel="noopener noreferrer">
      打开 Instagram 发送付款记录
    </a>
    <a class="payment-home" href="/">
      <i class="fas fa-home"></i> 回到主页
    </a>
  </div>
</div>

<div id="imagePreview" class="image-preview" role="dialog" aria-modal="true" aria-label="付款图片放大预览">
  <button type="button" id="closePreview" class="preview-close" aria-label="关闭">&times;</button>
  <img src="/yummy-diary/images/payment-qr.png" alt="放大的 Touch 'n Go 付款二维码">
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
const paymentModal = document.getElementById("paymentModal");
const imagePreview = document.getElementById("imagePreview");

document.getElementById("openPayment").addEventListener("click", () => {
  paymentModal.classList.add("show");
  document.body.style.overflow = "hidden";
});

function closePaymentModal(){
  paymentModal.classList.remove("show");
  document.body.style.overflow = "";
}

document.getElementById("closePayment").addEventListener("click", closePaymentModal);
paymentModal.addEventListener("click", event => {
  if(event.target === paymentModal) closePaymentModal();
});

document.getElementById("paymentImage").addEventListener("click", () => {
  imagePreview.classList.add("show");
});

function closeImagePreview(){
  imagePreview.classList.remove("show");
}

document.getElementById("closePreview").addEventListener("click", closeImagePreview);
imagePreview.addEventListener("click", event => {
  if(event.target === imagePreview || event.target.tagName === "IMG") closeImagePreview();
});

document.getElementById("downloadPdf").addEventListener("click", () => {
  const { jsPDF } = window.jspdf;
  const receipt = document.getElementById("receipt");

  html2canvas(receipt, { scale: 2 }).then(canvas => {
    const imgData = canvas.toDataURL("image/png");
    const pdf = new jsPDF("p", "mm", "a4");
    const pdfWidth = 210;
    const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

    pdf.addImage(imgData, "PNG", 0, 0, pdfWidth, pdfHeight);
    pdf.save("YummyDiary-Receipt.pdf");
  });
});
</script>

</body>
</html>
