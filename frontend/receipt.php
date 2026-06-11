<?php
session_start();
require __DIR__ . '/../config.php';
date_default_timezone_set("Asia/Kuala_Lumpur");

$order_number = $_GET['order_number'] ?? '';
if (!$order_number) die("❌ 订单号缺失");

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=?");
$stmt->execute([$order_number]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if ($order) {
        $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
        $stmt_items->execute([$order['id']]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        $order_data = [
            "id"    => $order['order_number'],
            "time"  => $order['created_at'],
            "items" => []
        ];
        foreach ($items as $it) {
            $order_data['items'][] = [
                "sku"   => $it['sku'] ?? '',
                "name"  => $it['product_name'],
                "qty"   => $it['quantity'],
                "price" => $it['price']
            ];
        }
    } else {
        die("❌ 找不到订单，请返回重新下单。");
    }

$timeFormatted = date("Y年n月j日 H:i", strtotime($order_data['time']));
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<title>订单收据 - Yummy Diary</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<style>
body { font-family:"Segoe UI",Arial,sans-serif; background:#fff; color:#333; margin:0; padding:0; text-align:center; animation:fadeIn 0.8s ease; }
@keyframes fadeIn { from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }
.header { padding:20px; position:relative; }
.header img { width:90px; height:auto; }
h2 { margin-top:10px; font-size:20px; }
.container { max-width:500px; margin:20px auto; padding:20px; border:1px solid #eee; border-radius:12px; background:#fafafa; box-shadow:0 4px 12px rgba(0,0,0,0.05);}
p { margin:6px 0; }
table { width:100%; border-collapse:collapse; margin:15px 0;}
th,td { border:1px solid #ddd; padding:8px; font-size:14px;}
th { background:#f5f5f5; }
.total { text-align:right; font-size:16px; font-weight:bold; margin-top:10px; }
.shipping { margin-top:15px; padding:12px; border:1px dashed #aaa; border-radius:10px; background:#fdfdfd; font-size:14px; text-align:left;}
.footer { margin-top:20px; font-size:14px; color:#555; }
.footer h3 { margin:8px 0 5px; font-size:15px; }
.contact-icon { font-size:18px; margin-right:6px; vertical-align:middle; }
.btn-row { display:flex; justify-content:center; gap:12px; margin-top:18px; flex-wrap:wrap; }
.btn-short { flex:1; max-width:120px; padding:10px; border:2px solid #333; border-radius:12px; background:transparent; color:#333; font-size:14px; cursor:pointer; transition:all 0.3s ease;}
.btn-short i{ margin-right:6px;}
.btn-short:hover { background:#333; color:#fff; }

/* 返回按钮 */
.back-btn {
  position: absolute;
  top: 15px;
  left: 15px;
  padding: 8px 14px;
  border: 2px solid #333;
  border-radius: 25px;
  background: transparent;
  color: #333;
  font-size: 14px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.3s ease;
}
.back-btn:hover { background:#333; color:#fff; }
.back-btn i { font-size: 14px; }

/* 手机端优化 */
@media(max-width:768px){
  .header img{width:70px;}
  h2{font-size:18px;}
  .container{width:92%; margin:15px auto; padding:15px;}
  th,td{font-size:12px; padding:6px;}
  .btn-row{gap:8px;}
  .btn-short{max-width:100px; font-size:13px; padding:8px;}
  .back-btn { padding:6px 12px; font-size:13px; top:10px; left:10px; }
}

/* === 弹窗样式 === */
.modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; }
.modal-content { background: #fff; padding: 20px; border-radius: 12px; width: 95%; max-width: 600px; text-align: center; box-shadow: 0 6px 20px rgba(0,0,0,0.2); animation: scaleIn 0.4s ease; }
@keyframes scaleIn { from { transform:scale(0.8); opacity:0; } to { transform:scale(1); opacity:1; } }
.modal-poster { width: 100%; max-width: 500px; margin: 0 auto; background:#fff; border:1px solid #333; border-radius:12px; font-family:'Ma Shan Zheng', cursive, Arial, sans-serif; display:flex; box-shadow: 0 4px 12px rgba(0,0,0,0.2); overflow:hidden; }
.modal-left { flex: 0 0 40%; display:flex; align-items:center; justify-content:center; border-right:1px solid #ddd; padding:10px; }
.modal-left img { max-width:100%; height:auto; }
.modal-right { flex:1; padding:15px; text-align:center; display:flex; flex-direction:column; justify-content:center; }
.modal-right p { margin:8px 0; }
/* 黑色手写体 */
.handwritten { font-family:'Ma+Shan+Zheng', cursive, Arial, sans-serif; font-weight:bold; color:#222; }
.modal-actions { margin-top: 15px; }
.modal-actions button { margin: 5px; padding: 10px 18px; border-radius: 25px; border: 2px solid #333; background: transparent; color:#333; cursor: pointer; }
.modal-actions button:hover { background:#333; color:#fff; }
.modal-content strong {
  font-weight: 800;
  color: #111;
  text-shadow: 0.5px 0.5px #ccc;
}
</style>
</head>
<body>
  <div class="header">
    <a href="shop.php" class="back-btn"><i class="fas fa-arrow-left"></i> 返回菜单</a>
    <img src="/yummy-diary/images/猫_购物袋.jpg" alt="Yummy Diary">
    <h2>🧾 Yummy Diary · 订单收据</h2>
  </div>

  <div class="container" id="receipt">
    <p><strong>订单号:</strong> <?= htmlspecialchars($order_data['id']) ?></p>
    <p><strong>下单时间:</strong> <?= $timeFormatted ?></p>

    <table>
      <tr><th>数量</th><th>商品</th><th>单价 (RM)</th><th>小计 (RM)</th></tr>
      <?php
      $total = 0;
      foreach ($order_data['items'] as $item) {
          $subtotal = $item['price'] * $item['qty'];
          $total += $subtotal;
          $skuLabel = $item['sku'] ? "[".htmlspecialchars($item['sku'])."] " : "";
          echo "<tr>
                  <td>{$item['qty']}</td>
                  <td>{$skuLabel}".htmlspecialchars($item['name'])."</td>
                  <td>".number_format($item['price'],2)."</td>
                  <td>".number_format($subtotal,2)."</td>
                </tr>";
      }
$shipping_cost = 7.50;
$gifts = [];

if ($total >= 49.90) {
    $shipping_cost = 0.00;
    $shipping_msg = "🚚 满 RM49.90：免运<br>🎁 赠品：魔芋爽 + 小挂件";
    $gifts[] = "魔芋爽";
    $gifts[] = "小挂件";
} elseif ($total >= 39.90) {
    $shipping_cost = 1.90;
    $shipping_msg = "🚚 满 RM39.90：运费 RM1.90<br>🎁 赠品：魔芋爽 + 小挂件";
    $gifts[] = "魔芋爽";
    $gifts[] = "小挂件";
} elseif ($total >= 29.90) {
    $shipping_cost = 3.50;
    $shipping_msg = "🚚 满 RM29.90：运费 RM3.50<br>🎁 赠品：魔芋爽 + 小挂件";
    $gifts[] = "魔芋爽";
    $gifts[] = "小挂件";
} elseif ($total >= 19.90) {
    $shipping_cost = 5.90;
    $shipping_msg = "🚚 满 RM19.90：运费 RM5.90";
} else {
    $shipping_msg = "🚚 普通运费：RM7.50";
}

      foreach ($gifts as $gift) {
          echo "<tr>
                  <td>1</td>
                  <td>[赠] {$gift}</td>
                  <td>0.00</td>
                  <td>0.00</td>
                </tr>";
      }

      echo "<tr>
              <td colspan='3' style='text-align:right;'>运费</td>
              <td>".number_format($shipping_cost,2)."</td>
            </tr>";

      $grand_total = $total + $shipping_cost;
      ?>
    </table>

    <p class="total">商品总额: RM <?= number_format($total,2) ?></p>
    <p class="total">运费: RM <?= number_format($shipping_cost,2) ?></p>
    <p class="total">总价 (含运费): RM <?= number_format($grand_total,2) ?></p>

    <div class="shipping"><?= $shipping_msg ?></div>

    <div class="footer">
      <h3>💳 付款提示</h3>
      <p>请联系商家获取付款方式。</p>
      <h3>📩 联系方式</h3>
      <p>
        <a href="https://www.instagram.com/yummydiaryy_" target="_blank" style="color:#333; text-decoration:none;">
          <i class="fab fa-instagram contact-icon"></i>@yummydiaryy_
        </a><br>
        <a href="https://wa.me/60106654042" target="_blank" style="color:#333; text-decoration:none;">
          <i class="fab fa-whatsapp contact-icon"></i> +60 10 665 4042
        </a>
      </p>
    </div>

    <div class="btn-row">
      <a href="index.php" class="btn-short"><i class="fas fa-check"></i> <strong>完成</strong></a>
      <button id="downloadPdf" class="btn-short"><i class="fas fa-file-pdf"></i> PDF</button>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script>
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
