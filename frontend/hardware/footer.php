<?php
// å‰å°å…±äº«é¡µè„šä¸Žè´­ç‰©è½¦æµ®çª—ï¼šè´Ÿè´£è´­ç‰©è½¦å±•ç¤ºã€æ•°é‡æ›´æ–°ã€æ¸…ç©ºå’Œç»“è´¦å…¥å£ã€‚
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>

<footer class="site-footer">
  <p>Â© 2025 Yummy Diary | All Rights Reserved.</p>
</footer>

<div class="cart-fab" id="cartFab">
  <div class="cart-inner">
    <img src="/yummy-diary/images/çŒ«_è´­ç‰©è¢‹.jpg" alt="è´­ç‰©è½¦" class="cart-img" loading="lazy">
  </div>
  <span class="cart-badge" style="display:none;">0</span>
</div>

<div id="cartModal" class="modal">

  <div class="modal-card">

    <div class="cart-modal-dog-wrap">
      <img
        id="cartModalDog"
        src="/yummy-diary/images/dog1.png"
        alt="dog"
        class="cart-modal-dog"
      >
    </div>

    <span class="close">&times;</span>

    <h2>ðŸ› æˆ‘çš„è´­ç‰©è¢‹</h2>

    <div class="cart-content"></div>

    <div class="cart-footer">
      <button id="clearCartBtn">æ¸…ç©º</button>
      <button id="checkoutBtn">
        <strong>åŽ»ç»“ç®—</strong>
      </button>
    </div>

  </div>

</div>

<style>
<?php include __DIR__ . '/../assets/css/hardware-footer.css'; ?>
</style>

<script defer>
<?php include __DIR__ . '/../assets/js/hardware-footer.js.php'; ?>
</script>

