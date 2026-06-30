
// âœ… å¤ç”¨è´­ç‰©è½¦é€»è¾‘ï¼ˆfooter é‡Œçš„ updateCartUIï¼‰+ åŠ åº“å­˜æ£€æµ‹
document.addEventListener("DOMContentLoaded", () => {
  document.body.addEventListener("click", function(e) {
    if (e.target.classList.contains("add-to-cart")) {
      e.preventDefault();
      const btn = e.target;

      const stock = parseInt(btn.dataset.stock, 10);
      const sku   = btn.dataset.sku;

      let currentQty = 0;
      if (window.cartData && window.cartData.cart) {
        const found = window.cartData.cart.find(item => item.sku === sku);
        if (found) currentQty = found.qty;
      }
      if (currentQty >= stock) {
        alert("âš ï¸ å·²è¾¾åˆ°åº“å­˜ä¸Šé™ï¼Œä¸èƒ½å†æ·»åŠ äº†ï¼");
        btn.disabled = true;
        btn.textContent = "å”®ç½„";
        return;
      }

      const formData = new FormData();
      formData.append("id", btn.dataset.id);
      formData.append("sku", btn.dataset.sku);
      formData.append("name", btn.dataset.name);
      formData.append("price", btn.dataset.price);
      formData.append("img", btn.dataset.img);

      fetch(<?= json_encode(appUrl('frontend/api/add_to_cart.php')) ?>, { method:"POST", body:formData })
        .then(res=>res.json())
        .then(data => {
          if(data.success && typeof updateCartUI === "function") {
            window.cartData = data;
            updateCartUI(data);
            if (data.cart.some(item => item.sku === sku && item.qty >= stock)) {
              btn.disabled = true;
              btn.textContent = "å”®ç½„";
            }
          }
        });
    }
  });
});

