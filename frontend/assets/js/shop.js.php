
    window.addEventListener("load", () => {
      const loader = document.getElementById("loader");
      const content = document.getElementById("content");
      setTimeout(() => {
        loader.classList.add("fade-out");
        setTimeout(() => {
          loader.style.display = "none";
          content.classList.add("show");
        }, 600);
      }, 2000); 
    });

    document.addEventListener("DOMContentLoaded", () => {
      const productImagePreview = document.getElementById("productImagePreview");
      const productImagePreviewImg = document.getElementById("productImagePreviewImg");
      const closeProductImagePreview = () => {
        productImagePreview.classList.remove("show");
        productImagePreviewImg.src = "";
        document.body.style.overflow = "";
      };

      document.body.addEventListener("click", event => {
        const image = event.target.closest(".product-info img, .variant-product img");
        if (!image) return;
        event.preventDefault();
        event.stopPropagation();
        productImagePreviewImg.src = image.currentSrc || image.src;
        productImagePreviewImg.alt = image.alt || "å•†å“å›¾ç‰‡";
        productImagePreview.classList.add("show");
        document.body.style.overflow = "hidden";
      });

      document.getElementById("closeProductImagePreview").addEventListener("click", closeProductImagePreview);
      productImagePreview.addEventListener("click", event => {
        if (event.target === productImagePreview || event.target === productImagePreviewImg) {
          closeProductImagePreview();
        }
      });

      // âœ… AJAX åˆ†ç±»åˆ‡æ¢
      document.querySelectorAll(".cat-link").forEach(link => {
        link.addEventListener("click", e => {
          e.preventDefault();
          const cat = link.dataset.cat;
          if (window.sortOrderDirty && !confirm('å½“å‰æŽ’åºè¿˜æ²¡æœ‰ä¿å­˜ï¼Œç¡®å®šæ”¾å¼ƒä¿®æ”¹å¹¶åˆ‡æ¢åˆ†ç±»å—ï¼Ÿ')) {
            return;
          }

          // æ›´æ–° active
          document.querySelectorAll(".cat-link").forEach(a => a.classList.remove("active"));
          link.classList.add("active");

          // æ›´æ–°æ ‡é¢˜
          document.getElementById("category-title").textContent = link.textContent;

          // AJAX æ‹‰å•†å“
          fetch(<?= json_encode(appUrl('shop')) ?> + `?cat=${encodeURIComponent(cat)}&ajax=1<?= $sortAdmin ? '&sort_admin=1' : '' ?>`, {cache:'no-store'})
            .then(res => res.text())
            .then(html => {
              document.querySelector(".shop-content").innerHTML = html;
              window.sortOrderDirty = false;
              window.captureSortOrder?.();
              window.refreshSortNumbers?.();
              window.updateSortSaveState?.('æ‹–åŠ¨å•†å“åŽæŒ‰ä¿å­˜', '');
              if (window.enableAdminSorting) window.enableAdminSorting();
            });
        });
      });
    });
  
