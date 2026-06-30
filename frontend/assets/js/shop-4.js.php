
  document.addEventListener("DOMContentLoaded", () => {
    const modal = document.getElementById("variantModal");
    const flavorBox = document.getElementById("flavorOptions");
    const pagination = document.getElementById("variantPagination");
    const previousPageButton = document.getElementById("variantPrevPage");
    const nextPageButton = document.getElementById("variantNextPage");
    const pageStatus = document.getElementById("variantPageStatus");
    const qtyText = document.getElementById("variantQty");
    const addButton = document.getElementById("variantAdd");
    
    const variantsPerPage = 6;
    let products = [], selected = null, flavor = "", qty = 1, variantPage = 0;

    function storefrontImageUrl(value) {
      let path = String(value || "").replaceAll("\\", "/").trim();
      path = path.replace(/^https?:\/\/[^/]+/i, "");
      path = path.replace(/^\/?yummy-diary\//i, "");
      path = path.replace(/^\/+/, "");

      if (path.startsWith("uploads/")) {
        path = "frontend/" + path;
      }

      return <?= json_encode(appUrl()) ?> + (path || "images/soldout.png");
    }

    function parse(button) {
      if (button.dataset.productType === "grouped") {
        let variants = [];
        try { variants = JSON.parse(button.dataset.variants || "[]"); } catch (error) {}
        return {
          button,
          family: button.dataset.sku,
          flavor: variants[0]?.variant_name || "é»˜è®¤",
          variants
        };
      }
      const sections = button.dataset.name.trim().split(/\s*[|ï½œ]\s*/);
      const parts = sections[0].split(/[Â·â€¢]/);
      let family = (parts[0] || sections[0]).trim();
      let detectedSize = "";
      const sizeMatch = family.match(/\s+(å•åŒ…|ç›’è£…\s*\d+\s*åŒ…|æ— ç›’\s*\d+\s*åŒ…|\d+\s*åŒ…|æ•´ç›’|ç›’è£…)$/u);
      if (sizeMatch) {
        detectedSize = sizeMatch[1].replace(/\s+/g, "");
        family = family.slice(0, sizeMatch.index).trim();
      }
      return {button, family, flavor:(parts.slice(1).join("Â·") || "åŽŸå‘³").trim(), size:(sections.slice(1).join("ï½œ") || detectedSize || "é»˜è®¤è§„æ ¼").trim()};
    }
    function details() {
      if (!selected) return;
      const button = selected.button;
      const variant = selected.variant;
      const sku = variant?.sku || button.dataset.sku;
      const stock = Number(variant?.stock ?? button.dataset.stock);
      const price = Number(variant?.price ?? button.dataset.price);
      const imageUrl = variant?.image_url || button.dataset.img;
      const inCart = window.cartData?.cart?.find(item => item.sku === sku)?.qty || 0;
      const available = Math.max(0, stock - inCart);
      qty = Math.max(1, Math.min(qty, available || 1));
      qtyText.textContent = qty;
      document.getElementById("variantTitle").textContent = variant ? `${button.dataset.name} Â· ${variant.variant_name}` : button.dataset.name;
      document.getElementById("variantSku").textContent = "ç¼–å·ï¼š" + sku;
      document.getElementById("variantStock").textContent = "åº“å­˜ï¼š" + stock;
      document.getElementById("variantPrice").textContent = "RM " + price.toFixed(2);
      const variantImage = document.getElementById("variantImage");
      variantImage.onerror = () => {
        variantImage.onerror = null;
        variantImage.src = <?= json_encode(productImageUrl(null)) ?>;
      };
      variantImage.src = storefrontImageUrl(imageUrl);
      addButton.disabled = available <= 0;
      addButton.textContent = available > 0 ? `åŠ å…¥è´­ç‰©è¢‹ Â· RM ${(price * qty).toFixed(2)}` : "å·²è¾¾åˆ°åº“å­˜ä¸Šé™";
    }
    function render() {
      const configured = products[0]?.button.dataset.productType === "grouped";
      const flavors = configured ? products[0].variants.map(item => item.variant_name) : [products[0].flavor];
      const pageCount = Math.max(1, Math.ceil(flavors.length / variantsPerPage));
      variantPage = Math.min(Math.max(variantPage, 0), pageCount - 1);
      const visibleFlavors = flavors.slice(variantPage * variantsPerPage, (variantPage + 1) * variantsPerPage);
      flavorBox.innerHTML = visibleFlavors.map(value => `<button type="button" class="variant-option${value === flavor ? " selected" : ""}" data-flavor="${escapeHtml(value)}">${escapeHtml(value)}</button>`).join("");
      pagination.classList.toggle("show", pageCount > 1);
      pageStatus.textContent = `${variantPage + 1} / ${pageCount}`;
      previousPageButton.disabled = variantPage === 0;
      nextPageButton.disabled = variantPage >= pageCount - 1;
      selected = products[0];
      selected.variant = configured ? selected.variants.find(item => item.variant_name === flavor) : null;
      details();
      
    }
    function open(button) {
      const current = parse(button);
      products = button.dataset.productType === "grouped"
        ? [current]
        : [current];
      flavor = current.flavor; qty = 1; variantPage = 0;
      render();
      document.getElementById("flavorOptions").closest(".variant-section").style.display = button.dataset.productType === "grouped" ? "" : "none";
      modal.classList.add("show");
      document.body.style.overflow = "hidden";
      
    }
    function close() {
      modal.classList.remove("show");
      document.body.style.overflow = "";
    }

    document.addEventListener("click", event => {
      const button = event.target.closest(".add-to-cart");
      if (!button) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      open(button);
    }, true);
    flavorBox.addEventListener("click", event => {
      const option = event.target.closest("[data-flavor]");
      if (!option) return;
      flavor = option.dataset.flavor;
      render();
    });
    previousPageButton.onclick = () => {
      variantPage = Math.max(0, variantPage - 1);
      render();
    };
    nextPageButton.onclick = () => {
      variantPage += 1;
      render();
    };
    document.getElementById("variantMinus").onclick = () => { qty = Math.max(1, qty - 1); details(); };
    document.getElementById("variantPlus").onclick = () => {
      const sku = selected.variant?.sku || selected.button.dataset.sku;
      const stock = Number(selected.variant?.stock ?? selected.button.dataset.stock);
      const inCart = window.cartData?.cart?.find(item => item.sku === sku)?.qty || 0;
      qty = Math.min(qty + 1, Math.max(1, stock - inCart));
      details();
    };
    addButton.onclick = async () => {
      if (!selected) return;
      addButton.disabled = true;
      try {
        for (let index = 0; index < qty; index++) {
          const data = new FormData();
          data.append("sku", selected.button.dataset.sku);
          if (selected.button.dataset.productType === "grouped") {
            data.append("variant_id", selected.variant?.id || "");
          }
          const response = await fetch(<?= json_encode(appUrl('frontend/api/add_to_cart.php')) ?>, {method:"POST", body:data});
          const result = await response.json();
          if (!result.success) throw new Error(result.message || "åŠ å…¥è´­ç‰©è¢‹å¤±è´¥");
          window.cartData = result;
        }
        updateCartUI(window.cartData);
        qty = 1;
        details();
        addButton.textContent = "å·²åŠ å…¥è´­ç‰©è¢‹ âœ“";
        window.setTimeout(details, 700);
      } catch (error) {
        alert(error.message);
        details();
      }
    };
    modal.querySelector(".variant-close").onclick = close;
    modal.addEventListener("click", event => { if (event.target === modal) close(); });
    
  });
  
