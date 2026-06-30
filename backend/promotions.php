<?php
// ä¼˜æƒ ç®¡ç†é¡µï¼šåˆ›å»ºã€æ›´æ–°å’Œç®¡ç†ä¿ƒé”€è§„åˆ™ã€‚
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';

date_default_timezone_set("Asia/Kuala_Lumpur");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>ä¼˜æƒ ç®¡ç†</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<style>
<?php include __DIR__ . '/assets/css/promotions.css'; ?>
</style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>ä¼˜æƒ ç®¡ç†</h2>
      <p>ç®¡ç†ä¼˜æƒ æ´»åŠ¨ã€æŠ˜æ‰£åˆ¸ã€æ»¡å‡ä¼˜æƒ å’Œå®¢æˆ·ä¿ƒé”€æ–¹æ¡ˆ</p>
    </div>
  </section>

  <section class="promo-summary">
    <div class="summary-card">
      <span>å½“å‰æ´»åŠ¨</span>
      <strong>0</strong>
      <small>æš‚æ— è¿›è¡Œä¸­çš„ä¼˜æƒ æ´»åŠ¨</small>
    </div>

    <div class="summary-card">
      <span>ä¼˜æƒ åˆ¸</span>
      <strong>0</strong>
      <small>å¯ç”¨äºŽæœªæ¥æŠ˜æ‰£ä»£ç </small>
    </div>

    <div class="summary-card">
      <span>çŠ¶æ€</span>
      <strong>å¾…è®¾ç½®</strong>
      <small>Promotion module pending setup</small>
    </div>
  </section>

  <section class="promo-grid">
    <div class="promo-card">
      <div class="promo-icon">ðŸŽ</div>
      <h3>æŠ˜æ‰£ä¼˜æƒ </h3>
      <p>é€‚åˆè®¾ç½®ç™¾åˆ†æ¯”æŠ˜æ‰£ï¼Œä¾‹å¦‚ 10% OFFã€20% OFFã€‚</p>
      <span class="promo-status">Coming Soon</span>
    </div>

    <div class="promo-card">
      <div class="promo-icon">ðŸ§¾</div>
      <h3>æ»¡å‡æ´»åŠ¨</h3>
      <p>é€‚åˆè®¾ç½®æ»¡ RM50 å‡ RM5ã€æ»¡ RM100 å‡ RM10ã€‚</p>
      <span class="promo-status">Coming Soon</span>
    </div>

    <div class="promo-card">
      <div class="promo-icon">ðŸ”¥</div>
      <h3>çƒ­é”€ç»„åˆ</h3>
      <p>é€‚åˆæŠŠçƒ­é”€å•†å“ç»„åˆæˆä¿ƒé”€å¥—é¤ï¼Œæé«˜å®¢å•ä»·ã€‚</p>
      <span class="promo-status">Coming Soon</span>
    </div>
  </section>

  <section class="empty-promo">
    <div class="big-icon">ðŸ“Œ</div>
    <h3>æš‚æ—¶æ²¡æœ‰ä¼˜æƒ æ´»åŠ¨</h3>
    <p>
      ä¹‹åŽå¯ä»¥åœ¨è¿™é‡Œæ–°å¢žä¼˜æƒ åˆ¸ã€è®¾ç½®æŠ˜æ‰£è§„åˆ™ã€é™åˆ¶ä½¿ç”¨æ¬¡æ•°ï¼Œ
      ä»¥åŠæŸ¥çœ‹å“ªäº›ä¼˜æƒ æ´»åŠ¨å¸¦æ¥æœ€å¤šè®¢å•ã€‚
    </p>

    <div class="promo-actions">
      <button type="button" class="btn btn-edit" onclick="alert('ä¼˜æƒ æ´»åŠ¨åŠŸèƒ½è¿˜æœªå¼€å¯')">
        âž• æ–°å¢žä¼˜æƒ 
      </button>

      <a href="products.php" class="btn btn-move">
        ðŸª åŽ»å•†å“ç®¡ç†
      </a>
    </div>
  </section>
</main>

</body>
</html>

