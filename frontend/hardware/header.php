<header>
  <div class="logo">
    <a href="<?= htmlspecialchars(appUrl(), ENT_QUOTES) ?>">
      <img src="/yummy-diary/images/logo1" alt="Yummy Diary Logo">
    </a>
  </div>

  <nav>
    <a href="<?= htmlspecialchars(appUrl(), ENT_QUOTES) ?>">首页 Home</a>
    <a href="<?= htmlspecialchars(appUrl('shop'), ENT_QUOTES) ?>">商店 Shop</a>
    <a href="<?= htmlspecialchars(appUrl('contact'), ENT_QUOTES) ?>">联系 Contact</a>
  </nav>

  <div class="nav-right">
    <!-- ✅ 搜索改为 /search -->
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
/* 🔽 搜索提示框样式 */
#suggestions {
  position:absolute;
  top:100%;
  left:0;
  right:0;
  background:#fff;
  border:1px solid #ddd;
  border-top:none;
  max-height:200px;
  overflow-y:auto;
  display:none;
  z-index:9999;
  font-size:14px;
}
#suggestions div {
  padding:8px 10px;
  cursor:pointer;
}
#suggestions div:hover {
  background:#f0f0f0;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const searchBox = document.getElementById("searchBox");
  const suggestionsBox = document.getElementById("suggestions");

  searchBox.addEventListener("keyup", () => {
    const query = searchBox.value.trim();
    if (query.length < 1) {
      suggestionsBox.style.display = "none";
      return;
    }

    fetch(<?= json_encode(appUrl('api/search-suggest')) ?> + "?q=" + encodeURIComponent(query))
      .then(res => res.json())
      .then(data => {
        suggestionsBox.innerHTML = "";
        if (data.length > 0) {
          data.forEach(item => {
            const div = document.createElement("div");
            div.textContent = `[${item.sku}] ${item.name}`;
            div.addEventListener("click", () => {
              searchBox.value = item.name;
              window.location.href = <?= json_encode(appUrl('search')) ?> + "?q=" + encodeURIComponent(item.name);
            });
            suggestionsBox.appendChild(div);
          });
          suggestionsBox.style.display = "block";
        } else {
          suggestionsBox.style.display = "none";
        }
      });
  });

  // 点击外面时关闭提示框
  document.addEventListener("click", (e) => {
    if (!searchBox.contains(e.target) && !suggestionsBox.contains(e.target)) {
      suggestionsBox.style.display = "none";
    }
  });
});
</script> 
