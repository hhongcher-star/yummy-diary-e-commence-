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
    <img src="/yummy-diary/images/猫_购物袋.jpg" alt="购物车" class="cart-img" loading="lazy">
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

    <h2>🛍 我的购物袋</h2>

    <div class="cart-content"></div>

    <div class="cart-footer">
      <button id="clearCartBtn">清空</button>
      <button id="checkoutBtn">
        <strong>去结算</strong>
      </button>
    </div>

  </div>

</div>

<style>

html{
  overflow-y:scroll;
}

.site-footer{
  text-align:center;
  padding:20px;
  background:#f9f9f9;
  margin-top:40px;
  color:#666;
  font-size:14px;
}

body::after{
  content:"";
  display:block;
  height:var(--footer-space,0px);
}

/* floating cart */

.cart-fab{
  position:fixed;
  bottom:70px;
  right:20px;
  width:75px;
  height:75px;
  background:#fff;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  box-shadow:0 6px 18px rgba(0,0,0,.18);
  cursor:pointer;
  z-index:1100;
  overflow:hidden;
}

.cart-inner{
  width:62px;
  height:62px;
  display:flex;
  align-items:center;
  justify-content:center;
}

.cart-img{
  width:58px;
  height:58px;
  object-fit:contain;
  display:block;
}

.cart-badge{
  position:absolute;
  top:8px;
  right:8px;
  background:#000;
  color:#fff;
  font-size:13px;
  min-width:22px;
  height:22px;
  display:flex;
  align-items:center;
  justify-content:center;
  border-radius:50%;
  font-weight:bold;
}

/* modal */

.modal{
  display:none;
  position:fixed;
  z-index:2000;
  left:0;
  top:0;
  width:100%;
  height:100%;
  background:rgba(0,0,0,.35);
}

.modal-card{
  background:#fff;
  width:420px;
  max-width:95%;
  padding:20px;
  padding-top:72px;
  border-radius:24px;
  box-shadow:0 8px 25px rgba(0,0,0,.15);
  position:absolute;
  top:50%;
  left:50%;
  transform:translate(-50%,-50%);
  max-height:80vh;

  overflow-x:visible;
  overflow-y:visible;
}

/* dog */

.cart-modal-dog-wrap{
  position:absolute;
  top:-95px;
  left:50%;
  transform:translateX(-50%);
  z-index:9999;
  pointer-events:none;
}

.cart-modal-dog{
  width:145px;
  height:145px;
  object-fit:contain;
  display:block;
  animation:dogFloat 1.8s ease-in-out infinite;
}

@keyframes dogFloat{

  0%,100%{
    transform:translateY(0);
  }

  50%{
    transform:translateY(-8px);
  }
}

.modal-card h2{
  text-align:center;
  font-size:18px;
  margin-top:0;
  color:#444;
}

.close{
  position:absolute;
  right:15px;
  top:15px;
  font-size:22px;
  cursor:pointer;
  color:#000;
}

/* cart item */

.cart-content ul{
  list-style:none;
  padding:0;
  margin:0;
}

.cart-content{
  max-height:calc(80vh - 210px);
  overflow-y:auto;
  overscroll-behavior:contain;
  -webkit-overflow-scrolling:touch;
  padding-right:4px;
}

.cart-item{
  display:flex;
  align-items:flex-start;
  gap:10px;
  background:#fafafa;
  margin-bottom:10px;
  padding:10px;
  border-radius:12px;
  font-size:14px;
  color:#555;
}

.cart-thumb{
  width:60px;
  height:60px;
  border-radius:8px;
  object-fit:cover;
  border:1px solid #eee;
}

.cart-info{
  flex:1;
  display:flex;
  flex-direction:column;
}

.cart-name{
  font-weight:bold;
  color:#333;
  font-size:14px;
  margin-bottom:6px;
  word-break:break-word;
}

.cart-meta{
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  font-size:13px;
  color:#666;
  gap:2px;
}

.cart-qty{
  display:flex;
  align-items:center;
  gap:6px;
  margin-bottom:3px;
}

.qty-btn{
  width:28px;
  height:28px;
  border:1px solid #000;
  border-radius:5px;
  background:#fff;
  cursor:pointer;
  font-weight:bold;
  font-size:14px;
}

.qty-btn:hover{
  background:#000;
  color:#fff;
}

/* empty */

.cart-empty{
  text-align:center;
  color:#777;
  padding:24px 0;
  font-size:15px;
}

/* summary */

.cart-summary{
  border-top:1px solid #eee;
  padding-top:14px;
  margin-top:12px;
  text-align:right;
  line-height:1.7;
  font-size:14px;
}

.cart-summary strong{
  font-size:16px;
  color:#000;
}

.shipping-choice{
  display:flex;
  justify-content:flex-end;
  align-items:center;
  gap:12px;
  margin-bottom:12px;
  font-weight:800;
}

.shipping-choice label{
  cursor:pointer;
  white-space:nowrap;
  padding:6px 14px;
  border:1px solid #eee;
  border-radius:999px;
  background:#fff;
}

.shipping-choice input{
  margin-right:5px;
}

.shipping-note{
  color:#e75480;
  font-weight:800;
}

.shipping-detail{
  color:#777;
  font-size:13px;
}

/* footer btn */

.cart-footer{
  text-align:center;
  margin-top:15px;
  display:flex;
  justify-content:space-around;
}

.cart-footer button{
  background:transparent;
  border:2px solid #000;
  padding:10px 22px;
  border-radius:25px;
  color:#000;
  cursor:pointer;
  font-weight:bold;
}

#checkoutBtn{
  background:#000;
  color:#fff;
  border-color:#000;
}

/* mobile */

@media(max-width:768px){

  .cart-fab{
    bottom:20px;
    right:15px;
    width:64px;
    height:64px;
  }

  .cart-inner{
    width:56px;
    height:56px;
  }

  .cart-img{
    width:52px;
    height:52px;
  }

  .cart-badge{
    top:5px;
    right:5px;
    font-size:12px;
    min-width:20px;
    height:20px;
  }

  .modal-card{
    width:95%!important;
    padding:15px!important;
    padding-top:70px!important;
  }

  .cart-content{
    max-height:calc(80vh - 195px);
  }

  .cart-modal-dog-wrap{
    top:-82px;
  }

  .cart-modal-dog{
    width:125px;
    height:125px;
  }

  .shipping-choice{
    justify-content:flex-end;
    flex-wrap:wrap;
  }
}

</style>

<script defer>

function getCartAndUpdate(){

  return fetch("/frontend/api/add_to_cart.php?mode=getCart")
    .then(res=>res.json())
    .then(data=>{

      if(data.success){
        updateCartUI(data);
      }

      return data;
    })
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

  return /^[a-zA-Z0-9/_\-. ]+$/.test(path)
    ? path
    : "images/soldout.png";
}

/* dog */

function updateCartDog(count){

  const dog=document.getElementById("cartModalDog");

  if(!dog)return;

  if(count > 0){

    dog.src="/yummy-diary/images/dog 2.png";

  }else{

    dog.src="/yummy-diary/images/dog1.png";
  }
}

/* shipping */

function getShipping(total){

  const region=localStorage.getItem("shipping_region") || "west";

  if(region==="east"){

    if(total>=49.90){
      return {
        region,
        cost:9.90,
        note:"🎁 送：1包魔芋爽 + 小挂件",
        detail:"东马满 RM49.90"
      };
    }

    if(total>=39.90){
      return {
        region,
        cost:11.90,
        note:"🎁 送：1包魔芋爽 + 小挂件",
        detail:"东马满 RM39.90"
      };
    }

    if(total>=29.90){
      return {
        region,
        cost:12.90,
        note:"🎁 送：1包魔芋爽 + 小挂件",
        detail:"东马满 RM29.90"
      };
    }

    if(total>=19.90){
      return {
        region,
        cost:13.90,
        note:"",
        detail:"东马满 RM19.90"
      };
    }

    return {
      region,
      cost:15.90,
      note:"",
      detail:"东马普通运费"
    };
  }

  if(total>=49.90){
    return {
      region,
      cost:0.00,
      note:"🎁 送：1包魔芋爽 + 小挂件",
      detail:"西马满 RM49.90 免运"
    };
  }

  if(total>=39.90){
    return {
      region,
      cost:1.90,
      note:"🎁 送：1包魔芋爽 + 小挂件",
      detail:"西马满 RM39.90"
    };
  }

  if(total>=29.90){
    return {
      region,
      cost:3.50,
      note:"🎁 送：1包魔芋爽 + 小挂件",
      detail:"西马满 RM29.90"
    };
  }

  if(total>=19.90){
    return {
      region,
      cost:5.90,
      note:"",
      detail:"西马满 RM19.90"
    };
  }

  return {
    region,
    cost:7.50,
    note:"",
    detail:"西马普通运费"
  };
}

/* update UI */

function updateCartUI(data){

  const count=data.count ?? 0;

  const badge=document.querySelector(".cart-badge");

  if(badge){

    badge.textContent=count;

    badge.style.display=count>0
      ? "flex"
      : "none";
  }

  updateCartDog(count);

  const cartContent=document.querySelector(".cart-content");
  const checkoutBtn=document.getElementById("checkoutBtn");
  const clearBtn=document.getElementById("clearCartBtn");

  let total=0;

  if(!data.cart || data.cart.length===0){

    if(cartContent){

      cartContent.innerHTML=`
        <div class="cart-empty">
          购物袋是空的
        </div>
      `;
    }

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

        <img
          src="/yummy-diary/${image}"
          onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';"
          alt="${name}"
          class="cart-thumb"
        >

        <div class="cart-info">

          <div class="cart-name">
            [${sku}] ${name}
          </div>

          <div class="cart-meta">

            <div class="cart-qty">

              <button
                class="qty-btn dec"
                data-sku="${sku}"
                data-cart-key="${escapeHtml(item.cart_key || item.sku)}"
                data-variant-id="${Number(item.variant_id || 0)}"
                data-name="${name}"
                data-price="${price}"
                data-img="${image}"
              >-</button>

              <span>${qty}</span>

              <button
                class="qty-btn inc"
                data-sku="${sku}"
                data-cart-key="${escapeHtml(item.cart_key || item.sku)}"
                data-variant-id="${Number(item.variant_id || 0)}"
                data-name="${name}"
                data-price="${price}"
                data-img="${image}"
              >+</button>

            </div>

            <div>
              单价：RM${price.toFixed(2)}
            </div>

            <div>
              小计：RM${subtotal.toFixed(2)}
            </div>

          </div>

        </div>

      </li>
    `;
  });

  const shipping=getShipping(total);
  const grandTotal=total+shipping.cost;

  cartContent.innerHTML=`

    <ul>${listHtml}</ul>

    <div class="cart-summary">

      <div class="shipping-choice">

        <label>
          <input
            type="radio"
            name="shipping_region"
            value="west"
            ${shipping.region==="west"?"checked":""}
          >
          西马
        </label>

        <label>
          <input
            type="radio"
            name="shipping_region"
            value="east"
            ${shipping.region==="east"?"checked":""}
          >
          东马
        </label>

      </div>

      <div>
        商品总额：RM${total.toFixed(2)}
      </div>

      <div class="shipping-detail">
        ${shipping.detail}
      </div>

      <div>
        运费：RM${shipping.cost.toFixed(2)}
      </div>

      ${
        shipping.note
          ? `<div class="shipping-note">${shipping.note}</div>`
          : ""
      }

      <div>
        <strong>
          总计（含运费）：RM${grandTotal.toFixed(2)}
        </strong>
      </div>

    </div>
  `;
}

/* DOM */

document.addEventListener("DOMContentLoaded",()=>{

  const footer=document.querySelector(".site-footer");

  if(footer){

    document.body.style.setProperty(
      "--footer-space",
      footer.offsetHeight+"px"
    );
  }

  getCartAndUpdate();

  document.querySelector(".cart-content")
    .addEventListener("change",e=>{

      if(e.target.name==="shipping_region"){

        localStorage.setItem(
          "shipping_region",
          e.target.value
        );

        getCartAndUpdate();
      }
    });

  document.querySelector(".cart-content")
    .addEventListener("click",e=>{

      const t=e.target;

      if(
        t.classList.contains("dec") ||
        t.classList.contains("inc")
      ){

        const fd=new FormData();

        fd.append("sku",t.dataset.sku);
        fd.append("cart_key",t.dataset.cartKey || t.dataset.sku);
        fd.append("variant_id",t.dataset.variantId || "0");
        fd.append("name",t.dataset.name);
        fd.append("price",t.dataset.price);
        fd.append("img",t.dataset.img);

        const url=t.classList.contains("dec")
          ? "/frontend/api/add_to_cart.php?mode=removeOne"
          : "/frontend/api/add_to_cart.php";

        fetch(url,{
          method:"POST",
          body:fd
        })
        .then(r=>r.json())
        .then(d=>updateCartUI(d))
        .catch(()=>getCartAndUpdate());
      }
    });

  document.getElementById("clearCartBtn")
    .addEventListener("click",()=>{

      fetch("/frontend/api/add_to_cart.php?mode=clear")
        .then(r=>r.json())
        .then(d=>updateCartUI(d));
    });

  document.getElementById("checkoutBtn")
    .addEventListener("click",async()=>{

      const checkoutBtn=document.getElementById("checkoutBtn");

      checkoutBtn.disabled=true;
      checkoutBtn.textContent="处理中...";

      try{

        const fd=new FormData();

        fd.append(
          "region",
          localStorage.getItem("shipping_region") || "west"
        );

        const checkoutResponse=await fetch(
          "api/checkout.php",
          {
            method:"POST",
            body:fd
          }
        );

        const checkoutData=await checkoutResponse.json();

        if(
          !checkoutResponse.ok ||
          !checkoutData.success
        ){
          throw new Error(
            checkoutData.msg || "建立订单失败"
          );
        }

        window.location.href=checkoutData.receipt_url;

      }catch(error){

        alert("❌ 结算失败: "+error.message);

        checkoutBtn.disabled=false;

        checkoutBtn.innerHTML="<strong>去结算</strong>";
      }
    });
});

/* modal */

(function(){

  const fab=document.getElementById("cartFab");
  const modal=document.getElementById("cartModal");
  const close=document.querySelector(".close");

  if(fab){

    fab.onclick=()=>{

      modal.style.display="block";
      fab.style.display="none";
    };
  }

  if(close){

    close.onclick=()=>{

      modal.style.display="none";
      fab.style.display="flex";
    };
  }

  window.onclick=e=>{

    if(e.target===modal){

      modal.style.display="none";
      fab.style.display="flex";
    }
  };
})();

</script>
