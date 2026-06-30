<?php
// å‰å°é¦–é¡µï¼šå±•ç¤ºå“ç‰Œå…¥å£ï¼ŒåŒæ—¶åˆå§‹åŒ–è®¿å®¢è¿½è¸ªå’Œå¿…è¦çš„è´­ç‰©è½¦æ¸…ç†é€»è¾‘ã€‚
session_start();
require __DIR__ . '/../config.php';

// âœ… detect ä»Žæ”¶æ®é¡µå›žé¦–é¡µ
// âœ… æ‰‹åŠ¨æ¸…ç†
if (isset($_GET['clear']) && $_GET['clear'] == '1') {
    unset($_SESSION['cart']);
    unset($_SESSION['orders']);
    unset($_SESSION['pending_order']);
    unset($_SESSION['pending_orders']);
}

// âœ… è®¿å®¢æ£€æµ‹
if (!isset($_COOKIE['visitor_token'])) {
    $token = bin2hex(random_bytes(16));
    setcookie('visitor_token', $token, time() + (86400 * 30), "/");
    $stmt = $pdo->prepare("INSERT IGNORE INTO visitors (visitor_token) VALUES (?)");
    $stmt->execute([$token]);
} else {
    $token = $_COOKIE['visitor_token'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO visitors (visitor_token) VALUES (?)");
    $stmt->execute([$token]);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Yummy Diary</title>

  <link rel="stylesheet" href="/yummy-diary/css/style.css">
  <meta name="description" content="Yummy Diary - ç²¾é€‰é›¶é£Ÿã€è¾£æ¡ã€ç³–æžœã€æ–‡åˆ›å°ç‰©ï¼Œè®©ç”Ÿæ´»æ›´æœ‰æ»‹å‘³ã€‚">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

  <style>
<?php include __DIR__ . '/assets/css/index.css'; ?>
</style>
</head>
<body>

  <div id="loader">
    <img src="/yummy-diary/images/loading-dog.png" alt="Loading...">
  </div>

  <div id="content">
    <?php include __DIR__ . '/hardware/header.php'; ?>

    <!-- æ¬¢è¿ŽåŒº -->
    <section class="hero">
      <div class="hero-img" data-aos="fade-right">
        <img src="/yummy-diary/images/33" alt="Yummy Diary Dog">
      </div>
      <div class="hero-text" data-aos="fade-left">
        <h1>
          <strong>Welcome to <span>Yummy Diary</strong></span>
        </h1>
        <p>
          ä¸€å£é›¶é£Ÿï¼Œä¸€ä»¶å°ç‰© <br>
          é™ªä½ æ”¶é›†æ¯ä¸€ä»½å°ç¡®å¹¸

        </p>
        <button class="btn" onclick="location.href=<?= htmlspecialchars(json_encode(appUrl('shop')), ENT_QUOTES) ?>"><strong>è¿›å…¥å•†åº— Shop Now</strong></button>
      </div>
    </section>

    <!-- ä¸‰å¼ å¡ç‰‡ -->
    <section class="info-cards">
      <!-- ç¬¬ä¸€å¼  -->
      <div class="card" data-aos="fade-up">
        <div class="card-img">
          <img src="/yummy-diary/images/34" alt="é›¶é£Ÿå°åº—">
        </div>
        <div class="card-text">
          <p><strong>ðŸšš è¿è´¹å¤§æŠ˜æ‰£</strong><br>
          <p><strong>å¥—é¤1</strong> </p>

è¿è´¹åªéœ€ <strong>Rm1.90</strong>ï¼ˆæ»¡ RM27.90)<br>
<p>ðŸŽ é€ï¼š1 åŒ…è¿·ä½ é­”èŠ‹çˆ½ + 1 ä¸ªèŒèŒå°ç‹—æŒ‚ä»¶</p><br>


<p><strong> å¥—é¤2</strong></p> 
è¿è´¹ç«‹å‡ <strong>Rm3.50</strong>ï¼ˆæ»¡ RM18.90)
<p>ðŸŽ é€ï¼š1 åŒ…è¿·ä½ é­”èŠ‹çˆ½


          </p>
        </div>
      </div>

      <!-- ç¬¬äºŒå¼  -->
      <div class="card reverse" data-aos="fade-up" data-aos-delay="200">
        <div class="card-img">
          <!-- å°çŒ«å›¾æ”¾è¿™é‡Œ -->
          <img src="/yummy-diary/images/35" alt="è¶…å€¼ä¼˜æƒ å°çŒ«">
        </div>
        <div class="card-text">
          <strong> æ¬¢è¿Žå…‰ä¸´â€”Yummydiary é›¶é£Ÿå°åº—</strong> 
          <p>
è¿™é‡Œæ˜¯å±žäºŽä½ çš„ç¾Žå‘³å°è§’è½ðŸ¬

æˆ‘ä»¬å¸Œæœ›æ¯ä¸€ä½æ¥åˆ°è¿™é‡Œçš„äºº
éƒ½èƒ½åœ¨å¿™ç¢Œçš„æ—¥å¸¸é‡Œ, æ‰¾åˆ°å±žäºŽè‡ªå·±çš„å¹¸ç¦å°çž¬é—´

æ¯ä¸€æ¬¾ç²¾é€‰é›¶é£Ÿ, 
éƒ½æ˜¯æˆ‘ä»¬ç”¨å¿ƒæŒ‘é€‰çš„å°æƒŠå–œ, 
æƒ³å¸¦ç»™ä½ ä¸€ä»½æ”¾æ¾ã€å¤šäº›å¿«ä¹, 
ä¹Ÿä¸ºç”Ÿæ´»æ·»ä¸Šä¸€ç‚¹ç‚¹ä»ªå¼æ„Ÿä¸Žæ¸©æš–

å–œæ¬¢çš„è¯, è®°å¾—å…³æ³¨æˆ‘ä»¬çš„ IG
<p>
  å–œæ¬¢çš„è¯ï¼Œè®°å¾—å…³æ³¨æˆ‘ä»¬çš„ IG 
  <a href="https://www.instagram.com/yummydiaryy_?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank">
    ðŸ‘‰<strong>@yummydiaryy_</strong>
          </a>
          </p>
        </div>
      </div>

      <!-- ç¬¬ä¸‰å¼  -->
      <div class="card" data-aos="fade-up" data-aos-delay="400">
        <div class="card-img">
          <img src="/yummy-diary/images/36" alt="è¿è´¹å¤§ä¼˜æƒ ">
        </div>
        <div class="card-text">
          <strong> ðŸ“Œ è¶…å€¼ä¼˜æƒ æ¥å•¦ï¼</strong>
          <p>
<strong>ðŸ·ï¸è¶…ä½Žä»·æ ¼</strong><br>
æˆ‘ä»¬åšæŒ <strong> æ¯”çƒ­é—¨åº—æ›´ä½Ž</strong> çš„å¥½ä»·æ ¼ï¼<br>
è®©æ¯ä¸€å£ç¾Žå‘³éƒ½ä¹°å¾—æ”¾å¿ƒåˆåˆ’ç®—<br><br>

ðŸšš <strong> è¿è´¹å® ç²‰</strong>

åŽŸä»· RM7.50 âž çŽ°åœ¨åªéœ€ <b><strong>RM1.90 </strong></b><br>
å°æé†’: å•ç¬”æ»¡ <strong>Rm27.90</strong> å°±èƒ½äº«å—å•¦<br>

ðŸŽ <strong>æš–å¿ƒå°ç¤¼ç‰©</strong><br>
æ¯ä¸€ä»½è®¢å• æˆ‘ä»¬éƒ½ä¼šé€ <strong>1-2ä»½å°ç¤¼ç‰©</strong><br>
å¸Œæœ›ä½ æ‹†åŒ…è£¹çš„é‚£ä¸€åˆ»
èƒ½å¤šä¸€ä»½æƒŠå–œ, ä¹Ÿå¤šä¸€ä»½è¢«æƒ¦è®°çš„æ¸©æš–
          </p>
        </div>
      </div>
    </section>

    <?php include __DIR__ . '/hardware/footer.php'; ?>
  </div>

  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
<?php include __DIR__ . '/assets/js/index.js.php'; ?>
</script>

</body>
</html>

