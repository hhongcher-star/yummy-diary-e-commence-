<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>

<footer class="site-footer">
  <p>© 2025 Yummy Diary | All Rights Reserved.</p>
</footer>

<!-- 🛍 固定底部购物车按钮 -->
<div class="cart-fab" id="cartFab">
  <div class="cart-inner">
    <img src="/yummy-diary/images/猫_购物袋.jpg" alt="购物车" class="cart-img" width="52" height="52" loading="lazy">
  </div>
  <span class="cart-badge" style="display:none;">0</span>
</div>

<!-- 🛒 弹窗购物车详情 -->
<div id="cartModal" class="modal">
  <div class="modal-card">
    <span class="close">&times;</span>
    <h2>🛍 我的购物袋</h2>
    <div class="cart-content"><!-- JS 渲染 --></div>
    <div class="cart-footer">
      <button id="clearCartBtn">清空</button>
      <button id="checkoutBtn"><strong>去结算</strong></button>
    </div>
  </div>
</div>

<style>
html { overflow-y: scroll; }
.site-footer { text-align:center; padding:20px; background:#f9f9f9; margin-top:40px; color:#666; font-size:14px; }
body::after { content:""; display:block; height:var(--footer-space,0px); }

.cart-fab { position:fixed; bottom:70px; right:20px; width:75px; height:75px; background:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 6px 12px rgba(0,0,0,0.2); cursor:pointer; z-index:1100; overflow:hidden; transition:transform 0.3s ease; }
.cart-inner { display:flex; flex-direction:column; align-items:center; }
.cart-img { width:70%; height:70%; object-fit:contain; }
.cart-badge { position:absolute; top:8px; right:8px; background:#000; color:#fff; font-size:14px; padding:4px 7px; border-radius:50%; font-weight:bold; box-shadow:0 0 4px rgba(0,0,0,0.3); }

@media(max-width:768px){
  .cart-fab{ bottom:20px; right:15px; width:60px; height:60px; }
  .cart-badge{ top:5px; right:5px; font-size:12px; padding:3px 6px; }
}

.modal { display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.3); }
.modal-card { background:#fff; width:420px; max-width:95%; padding:20px; border-radius:20px; box-shadow:0 8px 25px rgba(0,0,0,0.15); position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); animation:fadeIn 0.3s ease; max-height:80vh; overflow-y:auto; }
@keyframes fadeIn{ from{transform:translate(-50%, -60%);opacity:0;} to{transform:translate(-50%, -50%);opacity:1;} }
@media(max-width:768px){ .modal-card{width:95%!important; padding:15px!important;} }
.modal-card h2 { text-align:center;font-size:18px;margin-top:0;color:#444; }
.close{ position:absolute; right:15px; top:15px; font-size:20px; cursor:pointer; color:#000; }
.close:hover{ color:#555; }

.cart-content ul{ list-style:none; padding:0; margin:0; }
.cart-item{ display:flex; align-items:flex-start; gap:10px; background:#fafafa; margin-bottom:10px; padding:10px; border-radius:12px; font-size:14px; color:#555; }
.cart-thumb{ width:60px; height:60px; border-radius:8px; object-fit:cover; border:1px solid #eee; }
.cart-info{ flex:1; display:flex; flex-direction:column; }
.cart-name{ font-weight:bold; color:#333; font-size:14px; margin-bottom:6px; word-break:break-word; }
.cart-meta{ display:flex; flex-direction:column; align-items:flex-end; font-size:13px; color:#666; gap:2px; }
.cart-qty{ display:flex; align-items:center; gap:5px; margin-bottom:3px; }
.qty-btn{ width:28px; height:28px; border:1px solid #000; border-radius:4px; background:#fff; cursor:pointer; font-weight:bold; font-size:14px; }
.qty-btn:hover{ background:#000; color:#fff; }
.qty-btn:disabled { opacity:0.5; cursor:not-allowed; }
.cart-price, .cart-subtotal{ font-size:12px; color:#444; }
.cart-empty{text-align:center;color:#aaa;padding:30px 0;}

.cart-summary{
  border-top:1px solid #eee;
  padding-top:10px;
  margin-top:10px;
  text-align:right;
  line-height:1.6;
  font-size:14px;
}
.cart-summary strong{ font-size:15px; color:#000; }

.cart-footer{text-align:center;margin-top:15px; display:flex; justify-content:space-around; }
.cart-footer button{ background:transparent; border:2px solid #000; padding:10px 20px; border-radius:25px; color:#000; cursor:pointer; font-weight:bold; transition:0.3s; }
.cart-footer button:hover{ background:#000; color:#fff; }
.cart-footer button:disabled{ border:2px solid #ccc; color:#ccc; background:transparent; cursor:not-allowed; }
#checkoutBtn {
  background: #000 !important;
  color: #fff !important;
  border: none !important;
  padding: 12px 28px;
  border-radius: 999px;
  font-weight: bold;
  font-size: 15px;
  cursor: pointer;
  transition: 0.3s;
}
#checkoutBtn:hover { background: #333; }
</style>

<script defer>
function getCartAndUpdate() {
  return fetch("api/add_to_cart.php?mode=getCart")
    .then(res => res.json())
    .then(data => { if (data.success) updateCartUI(data); return data; })
    .catch(err => console.error("getCart failed", err));
}

function updateCartUI(data) {
  const badge = document.querySelector(".cart-badge");
  if (badge) {
    badge.textContent = data.count ?? 0;
    badge.style.display = (data.count ?? 0) > 0 ? "inline-block" : "none";
  }

  const cartContent = document.querySelector(".cart-content");
  const checkoutBtn = document.getElementById("checkoutBtn");
  const clearBtn = document.getElementById("clearCartBtn");

  let total = 0;

  if (!data.cart || data.cart.length === 0) {
    if (cartContent) cartContent.innerHTML = `<div class="cart-empty">购物车是空的</div>`;
    if (checkoutBtn) checkoutBtn.disabled = true;
    if (clearBtn) clearBtn.disabled = true;
    return;
  }

  // 非空：确保按钮可用
  if (checkoutBtn) checkoutBtn.disabled = false;
  if (clearBtn) clearBtn.disabled = false;

  let listHtml = "";
  data.cart.forEach(item => {
    const price = parseFloat(item.price);
    const qty   = parseInt(item.qty, 10);
    const subtotal = price * qty;
    total += subtotal;

    listHtml += `
    <li class="cart-item" data-sku="${item.sku}">
      <img src="/yummy-diary/${item.img}" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';" alt="${item.name}" class="cart-thumb">
      <div class="cart-info">
        <div class="cart-name">[${item.sku}] ${item.name}</div>
        <div class="cart-meta">
          <div class="cart-qty">
            <button class="qty-btn dec"
              data-sku="${item.sku}"
              data-name="${item.name}"
              data-price="${price}"
              data-img="${item.img}">-</button>
            <span class="qty-value">${qty}</span>
            <button class="qty-btn inc"
              data-sku="${item.sku}"
              data-name="${item.name}"
              data-price="${price}"
              data-img="${item.img}">+</button>
          </div>
          <div class="cart-price">单价：RM${price.toFixed(2)}</div>
          <div class="cart-subtotal">小计：RM${subtotal.toFixed(2)}</div>
        </div>
      </div>
    </li>`;
  });

  // 运费算法
  let shipping_cost = 7.50;
  if (total >= 49.90) {
  shipping_cost = 0.00;
} else if (total >= 39.90) {
  shipping_cost = 1.90;
} else if (total >= 29.90) {
  shipping_cost = 3.50;
} else if (total >= 19.90) {
  shipping_cost = 5.90;
}
  const grand_total = total + shipping_cost;

  // 渲染
  if (cartContent) {
    cartContent.innerHTML = `
      <ul>${listHtml}</ul>
      <div class="cart-summary">
        <div>商品总额：RM${total.toFixed(2)}</div>
        <div>运费：RM${shipping_cost.toFixed(2)}</div>
        <div><strong>总计（含运费）：RM${grand_total.toFixed(2)}</strong></div>
      </div>
    `;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const footer = document.querySelector(".site-footer");
  if (footer) document.body.style.setProperty("--footer-space", footer.offsetHeight + "px");

  getCartAndUpdate();

  // 数量 +/-
  document.querySelector(".cart-content").addEventListener("click", e => {
    const t = e.target;
    if (t.classList.contains("dec") || t.classList.contains("inc")) {
      const fd = new FormData();
      fd.append("sku", t.dataset.sku);
      fd.append("name", t.dataset.name);
      fd.append("price", t.dataset.price);
      fd.append("img", t.dataset.img);
      const url = t.classList.contains("dec") ? "api/add_to_cart.php?mode=removeOne" : "api/add_to_cart.php";
      fetch(url, { method:"POST", body: fd })
        .then(r => r.json())
        .then(d => updateCartUI(d))
        .catch(() => getCartAndUpdate());
    }
  });

  // 清空
  const clearBtn = document.getElementById("clearCartBtn");
  if (clearBtn) {
    clearBtn.addEventListener("click", () => {
      fetch("api/add_to_cart.php?mode=clear")
        .then(r => r.json())
        .then(d => updateCartUI(d));
    });
  }

  // 去结算
  const checkoutBtn = document.getElementById("checkoutBtn");
  if (checkoutBtn) {
    checkoutBtn.addEventListener("click", () => {
      fetch("api/checkout.php", { method:"POST" })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            window.location.href = "receipt.php?order_number=" + encodeURIComponent(d.order_number);
          } else {
            alert("❌ 结算失败: " + (d.msg || "未知错误"));
          }
        })
        .catch(() => alert("❌ 结算请求失败"));
    });
  }
});

// Modal 控制
(function(){
  const fab=document.getElementById("cartFab");
  const modal=document.getElementById("cartModal");
  const close=document.querySelector(".close");
  if (fab) fab.onclick=()=>modal.style.display="block";
  if (close) close.onclick=()=>modal.style.display="none";
  window.onclick=e=>{ if(e.target===modal) modal.style.display="none"; };
})();
</script>

