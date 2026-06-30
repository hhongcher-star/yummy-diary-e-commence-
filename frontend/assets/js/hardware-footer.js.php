

function getCartAndUpdate(){

  return fetch(<?= json_encode(appUrl('frontend/api/add_to_cart.php?mode=getCart')) ?>)
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
        cost:4.90,
        note:"ðŸŽ é€ï¼š1åŒ…é­”èŠ‹çˆ½ + å°æŒ‚ä»¶",
        detail:"ä¸œé©¬æ»¡ RM49.90"
      };
    }

    if(total>=39.90){
      return {
        region,
        cost:6.90,
        note:"ðŸŽ é€ï¼š1åŒ…é­”èŠ‹çˆ½ + å°æŒ‚ä»¶",
        detail:"ä¸œé©¬æ»¡ RM39.90"
      };
    }

    if(total>=29.90){
      return {
        region,
        cost:8.90,
        note:"ðŸŽ é€ï¼š1åŒ…é­”èŠ‹çˆ½ + å°æŒ‚ä»¶",
        detail:"ä¸œé©¬æ»¡ RM29.90"
      };
    }

    if(total>=19.90){
      return {
        region,
        cost:10.90,
        note:"",
        detail:"ä¸œé©¬æ»¡ RM19.90"
      };
    }

    return {
      region,
      cost:12.90,
      note:"",
      detail:"ä¸œé©¬æ™®é€šè¿è´¹"
    };
  }

  if(total>=49.90){
    return {
      region,
      cost:0.00,
      note:"ðŸŽ é€ï¼š1åŒ…é­”èŠ‹çˆ½ + å°æŒ‚ä»¶",
      detail:"è¥¿é©¬æ»¡ RM49.90 å…è¿"
    };
  }

  if(total>=39.90){
    return {
      region,
      cost:1.90,
      note:"ðŸŽ é€ï¼š1åŒ…é­”èŠ‹çˆ½ + å°æŒ‚ä»¶",
      detail:"è¥¿é©¬æ»¡ RM39.90"
    };
  }

  if(total>=29.90){
    return {
      region,
      cost:3.50,
      note:"ðŸŽ é€ï¼š1åŒ…é­”èŠ‹çˆ½ + å°æŒ‚ä»¶",
      detail:"è¥¿é©¬æ»¡ RM29.90"
    };
  }

  if(total>=19.90){
    return {
      region,
      cost:5.90,
      note:"",
      detail:"è¥¿é©¬æ»¡ RM19.90"
    };
  }

  return {
    region,
    cost:7.50,
    note:"",
    detail:"è¥¿é©¬æ™®é€šè¿è´¹"
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
          è´­ç‰©è¢‹æ˜¯ç©ºçš„
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
              å•ä»·ï¼šRM${price.toFixed(2)}
            </div>

            <div>
              å°è®¡ï¼šRM${subtotal.toFixed(2)}
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
          è¥¿é©¬
        </label>

        <label>
          <input
            type="radio"
            name="shipping_region"
            value="east"
            ${shipping.region==="east"?"checked":""}
          >
          ä¸œé©¬
        </label>

      </div>

      <div>
        å•†å“æ€»é¢ï¼šRM${total.toFixed(2)}
      </div>

      <div class="shipping-detail">
        ${shipping.detail}
      </div>

      <div>
        è¿è´¹ï¼šRM${shipping.cost.toFixed(2)}
      </div>

      ${
        shipping.note
          ? `<div class="shipping-note">${shipping.note}</div>`
          : ""
      }

      <div>
        <strong>
          æ€»è®¡ï¼ˆå«è¿è´¹ï¼‰ï¼šRM${grandTotal.toFixed(2)}
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
          ? <?= json_encode(appUrl('frontend/api/add_to_cart.php?mode=removeOne')) ?>
          : <?= json_encode(appUrl('frontend/api/add_to_cart.php')) ?>;

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

      fetch(<?= json_encode(appUrl('frontend/api/add_to_cart.php?mode=clear')) ?>)
        .then(r=>r.json())
        .then(d=>updateCartUI(d));
    });

  document.getElementById("checkoutBtn")
    .addEventListener("click",async()=>{

      const checkoutBtn=document.getElementById("checkoutBtn");

      checkoutBtn.disabled=true;
      checkoutBtn.textContent="å¤„ç†ä¸­...";

      try{

        const fd=new FormData();

        fd.append(
          "region",
          localStorage.getItem("shipping_region") || "west"
        );

        const checkoutResponse=await fetch(
          <?= json_encode(appUrl('frontend/api/checkout.php')) ?>,
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
            checkoutData.msg || checkoutData.message || "å»ºç«‹è®¢å•å¤±è´¥"
          );
        }

        window.location.href=checkoutData.receipt_url;

      }catch(error){

        alert("âŒ ç»“ç®—å¤±è´¥: "+error.message);

        checkoutBtn.disabled=false;

        checkoutBtn.innerHTML="<strong>åŽ»ç»“ç®—</strong>";
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


