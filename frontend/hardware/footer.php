<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>

<footer class="site-footer">
  <p>© 2025 Yummy Diary | All Rights Reserved.</p>
</footer>

<div class="cart-fab" id="cartFab">
  <div class="cart-inner">
    <img src="/yummy-diary/images/猫_购物袋.jpg" alt="购物车" class="cart-img" width="52" height="52" loading="lazy">
  </div>
  <span class="cart-badge" style="display:none;">0</span>
</div>

<div id="cartModal" class="modal">
  <div class="modal-card">
    <span class="close">&times;</span>
    <h2>🛍 我的购物袋</h2>
    <div class="cart-content"></div>
    <div class="cart-footer">
      <button id="clearCartBtn">清空</button>
      <button id="checkoutBtn"><strong>去结算</strong></button>
    </div>
  </div>
</div>

<style>
html{overflow-y:scroll;}
.site-footer{text-align:center;padding:20px;background:#f9f9f9;margin-top:40px;color:#666;font-size:14px;}
body::after{content:"";display:block;height:var(--footer-space,0px);}

.cart-fab{position:fixed;bottom:70px;right:20px;width:75px;height:75px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 12px rgba(0,0,0,.2);cursor:pointer;z-index:1100;overflow:hidden;transition:transform .3s ease;}
.cart-img{width:70%;height:70%;object-fit:contain;}
.cart-badge{position:absolute;top:8px;right:8px;background:#000;color:#fff;font-size:14px;padding:4px 7px;border-radius:50%;font-weight:bold;box-shadow:0 0 4px rgba(0,0,0,.3);}

.modal{display:none;position:fixed;z-index:2000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.3);}
.modal-card{background:#fff;width:420px;max-width:95%;padding:20px;border-radius:20px;box-shadow:0 8px 25px rgba(0,0,0,.15);position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);max-height:80vh;overflow-y:auto;}
.modal-card h2{text-align:center;font-size:18px;margin-top:0;color:#444;}
.close{position:absolute;right:15px;top:15px;font-size:20px;cursor:pointer;color:#000;}

.cart-content ul{list-style:none;padding:0;margin:0;}
.cart-item{display:flex;align-items:flex-start;gap:10px;background:#fafafa;margin-bottom:10px;padding:10px;border-radius:12px;font-size:14px;color:#555;}
.cart-thumb{width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid #eee;}
.cart-info{flex:1;display:flex;flex-direction:column;}
.cart-name{font-weight:bold;color:#333;font-size:14px;margin-bottom:6px;word-break:break-word;}
.cart-meta{display:flex;flex-direction:column;align-items:flex-end;font-size:13px;color:#666;gap:2px;}
.cart-qty{display:flex;align-items:center;gap:5px;margin-bottom:3px;}
.qty-btn{width:28px;height:28px;border:1px solid #000;border-radius:4px;background:#fff;cursor:pointer;font-weight:bold;font-size:14px;}
.qty-btn:hover{background:#000;color:#fff;}
.qty-btn:disabled{opacity:.5;cursor:not-allowed;}
.cart-price,.cart-subtotal{font-size:12px;color:#444;}
.cart-empty{text-align:center;color:#aaa;padding:30px 0;}

.cart-summary{border-top:1px solid #eee;padding-top:12px;margin-top:10px;text-align:right;line-height:1.7;font-size:14px;}
.cart-summary strong{font-size:15px;color:#000;}

.shipping-choice{display:flex;gap:12px;justify-content:flex-end;align-items:center;margin-bottom:10px;font-weight:800;}
.shipping-choice label{cursor:pointer;white-space:nowrap;padding:6px 10px;border:1px solid #eee;border-radius:999px;background:#fff;}
.shipping-choice input{margin-right:4px;}
.shipping-note{color:#e75480;font-weight:800;}
.shipping-detail{color:#777;font-size:13px;}

.cart-footer{text-align:center;margin-top:15px;display:flex;justify-content:space-around;}
.cart-footer button{background:transparent;border:2px solid #000;padding:10px 20px;border-radius:25px;color:#000;cursor:pointer;font-weight:bold;transition:.3s;}
.cart-footer button:hover{background:#000;color:#fff;}
.cart-footer button:disabled{border-color:#ccc;color:#ccc;background:transparent;cursor:not-allowed;}
#checkoutBtn{background:#000;color:#fff;border-color:#000;}

@media(max-width:768px){
  .cart-fab{bottom:20px;right:15px;width:60px;height:60px;}
  .cart-badge{top:5px;right:5px;font-size:12px;padding:3px 6px;}
  .modal-card{width:95%!important;padding:15px!important;}
  .shipping-choice{justify-content:center;flex-wrap:wrap;}
}
</style>

<script defer>
function getCartAndUpdate(){
  return fetch("api/add_to_cart.php?mode=getCart")
    .then(res=>res.json())
    .then(data=>{if(data.success)updateCartUI(data);return data;})
    .catch(err=>console.error("getCart failed",err));
}

function escapeHtml(value){
  return String(value ?? "")
    .replaceAll("&","&amp;")
    .replaceAll("<","&lt;")
    .replaceAll(">","&gt;")
    .replaceAll('"',"&quot;")
    .replaceAll("'","&#039;");
}

function safeImagePath(value){
  const path=String(value ?? "").replaceAll("\\","/");
  return /^[a-zA-Z0-9/_\-.]+$/.test(path)?path:"images/soldout.png";
}

function getShipping(total){
  const region=localStorage.getItem("shipping_region") || "west";

  if(region==="east"){
    if(total>=49.90)return {region,cost:9.90,note:"🎁 送：1包魔芋爽 + 小挂件",detail:"东马满 RM49.90"};
    if(total>=39.90)return {region,cost:11.90,note:"🎁 送：1包魔芋爽 + 小挂件",detail:"东马满 RM39.90"};
    if(total>=29.90)return {region,cost:12.90,note:"🎁 送：1包魔芋爽 + 小挂件",detail:"东马满 RM29.90"};
    if(total>=19.90)return {region,cost:13.90,note:"",detail:"东马满 RM19.90"};
    return {region,cost:15.90,note:"",detail:"东马普通运费"};
  }

  return {region,cost:10.00,note:"",detail:"西马运费"};
}

function updateCartUI(data){
  const badge=document.querySelector(".cart-badge");
  if(badge){
    badge.textContent=data.count ?? 0;
    badge.style.display=(data.count ?? 0)>0?"inline-block":"none";
  }

  const cartContent=document.querySelector(".cart-content");
  const checkoutBtn=document.getElementById("checkoutBtn");
  const clearBtn=document.getElementById("clearCartBtn");

  let total=0;

  if(!data.cart || data.cart.length===0){
    if(cartContent)cartContent.innerHTML=`<div class="cart-empty">购物车是空的</div>`;
    if(checkoutBtn)checkoutBtn.disabled=true;
    if(clearBtn)clearBtn.disabled=true;
    return;
  }

  if(checkoutBtn)checkoutBtn.disabled=false;
  if(clearBtn)clearBtn.disabled=false;

  let listHtml="";

  data.cart.forEach(item=>{
    const price=parseFloat(item.price);
    const qty=parseInt(item.qty,10);
    const sku=escapeHtml(item.sku);
    const name=escapeHtml(item.name);
    const image=escapeHtml(safeImagePath(item.img));
    const subtotal=price*qty;
    total+=subtotal;

    listHtml+=`
      <li class="cart-item" data-sku="${sku}">
        <img src="/yummy-diary/${image}" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';" alt="${name}" class="cart-thumb">
        <div class="cart-info">
          <div class="cart-name">[${sku}] ${name}</div>
          <div class="cart-meta">
            <div class="cart-qty">
              <button class="qty-btn dec"
                data-sku="${sku}"
                data-cart-key="${escapeHtml(item.cart_key || item.sku)}"
                data-variant-id="${Number(item.variant_id || 0)}"
                data-name="${name}"
                data-price="${price}"
                data-img="${image}">-</button>
              <span class="qty-value">${qty}</span>
              <button class="qty-btn inc"
                data-sku="${sku}"
                data-cart-key="${escapeHtml(item.cart_key || item.sku)}"
                data-variant-id="${Number(item.variant_id || 0)}"
                data-name="${name}"
                data-price="${price}"
                data-img="${image}">+</button>
            </div>
            <div class="cart-price">单价：RM${price.toFixed(2)}</div>
            <div class="cart-subtotal">小计：RM${subtotal.toFixed(2)}</div>
          </div>
        </div>
      </li>`;
  });

  const shipping=getShipping(total);
  const grandTotal=total+shipping.cost;

  if(cartContent){
    cartContent.innerHTML=`
      <ul>${listHtml}</ul>
      <div class="cart-summary">
        <div class="shipping-choice">
          <label>
            <input type="radio" name="shipping_region" value="west" ${shipping.region==="west"?"checked":""}>
            西马
          </label>
          <label>
            <input type="radio" name="shipping_region" value="east" ${shipping.region==="east"?"checked":""}>
            东马
          </label>
        </div>

        <div>商品总额：RM${total.toFixed(2)}</div>
        <div class="shipping-detail">${shipping.detail}</div>
        <div>运费：RM${shipping.cost.toFixed(2)}</div>
        ${shipping.note?`<div class="shipping-note">${shipping.note}</div>`:""}
        <div><strong>总计（含运费）：RM${grandTotal.toFixed(2)}</strong></div>
      </div>`;
  }
}

document.addEventListener("DOMContentLoaded",()=>{
  const footer=document.querySelector(".site-footer");
  if(footer)document.body.style.setProperty("--footer-space",footer.offsetHeight+"px");

  getCartAndUpdate();

  document.querySelector(".cart-content").addEventListener("change",e=>{
    if(e.target.name==="shipping_region"){
      localStorage.setItem("shipping_region",e.target.value);
      getCartAndUpdate();
    }
  });

  document.querySelector(".cart-content").addEventListener("click",e=>{
    const t=e.target;

    if(t.classList.contains("dec") || t.classList.contains("inc")){
      const fd=new FormData();
      fd.append("sku",t.dataset.sku);
      fd.append("cart_key",t.dataset.cartKey || t.dataset.sku);
      fd.append("variant_id",t.dataset.variantId || "0");
      fd.append("name",t.dataset.name);
      fd.append("price",t.dataset.price);
      fd.append("img",t.dataset.img);

      const url=t.classList.contains("dec") ? "api/add_to_cart.php?mode=removeOne" : "api/add_to_cart.php";

      fetch(url,{method:"POST",body:fd})
        .then(r=>r.json())
        .then(d=>updateCartUI(d))
        .catch(()=>getCartAndUpdate());
    }
  });

  const clearBtn=document.getElementById("clearCartBtn");
  if(clearBtn){
    clearBtn.addEventListener("click",()=>{
      fetch("api/add_to_cart.php?mode=clear")
        .then(r=>r.json())
        .then(d=>updateCartUI(d));
    });
  }

  const checkoutBtn=document.getElementById("checkoutBtn");
  if(checkoutBtn){
    checkoutBtn.addEventListener("click",async()=>{
      checkoutBtn.disabled=true;
      checkoutBtn.textContent="处理中...";

      try{
        const fd=new FormData();
        fd.append("shipping_region",localStorage.getItem("shipping_region") || "west");

        const checkoutResponse=await fetch("api/checkout.php",{method:"POST",body:fd});
        const checkoutData=await checkoutResponse.json();

        if(!checkoutResponse.ok || !checkoutData.success){
          throw new Error(checkoutData.msg || "建立订单失败");
        }

        window.location.href=checkoutData.receipt_url;
      }catch(error){
        alert("❌ 结算失败: "+error.message);
        checkoutBtn.disabled=false;
        checkoutBtn.innerHTML="<strong>去结算</strong>";
      }
    });
  }
});

(function(){
  const fab=document.getElementById("cartFab");
  const modal=document.getElementById("cartModal");
  const close=document.querySelector(".close");

  if(fab)fab.onclick=()=>modal.style.display="block";
  if(close)close.onclick=()=>modal.style.display="none";

  window.onclick=e=>{
    if(e.target===modal)modal.style.display="none";
  };
})();
</script>
