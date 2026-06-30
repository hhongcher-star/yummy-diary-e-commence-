<?php // å‰å°å…±äº«é¡µå¤´ï¼šåŒ…å« Logoã€ä¸»å¯¼èˆªã€æœç´¢æ¡†å’Œæœç´¢å»ºè®®äº¤äº’ã€‚ ?>
<header>
  <div class="logo">
    <a href="<?= htmlspecialchars(appUrl(), ENT_QUOTES) ?>">
      <img src="/yummy-diary/images/logo1" alt="Yummy Diary Logo">
    </a>
  </div>

  <nav>
    <a href="<?= htmlspecialchars(appUrl(), ENT_QUOTES) ?>">é¦–é¡µ Home</a>
    <a href="<?= htmlspecialchars(appUrl('shop'), ENT_QUOTES) ?>">å•†åº— Shop</a>
    <a href="<?= htmlspecialchars(appUrl('contact'), ENT_QUOTES) ?>">è”ç³» Contact</a>
  </nav>

  <div class="nav-right">
    <!-- âœ… æœç´¢æ”¹ä¸º /search -->
    <form class="search-box" action="<?= htmlspecialchars(appUrl('search'), ENT_QUOTES) ?>" method="get" style="position:relative;">
      <svg xmlns="http://www.w3.org/2000/svg" class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <input type="text" id="searchBox" name="q" placeholder="Search Here..." required autocomplete="off">
      <div id="suggestions" class="suggestions-box"></div>
    </form>
  </div>
</header>

<style>
<?php include __DIR__ . '/../assets/css/hardware-header.css'; ?>
</style>

<script>
<?php include __DIR__ . '/../assets/js/hardware-header.js.php'; ?>
</script> 

