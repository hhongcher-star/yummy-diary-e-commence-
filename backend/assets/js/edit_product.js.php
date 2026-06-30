
const variantFields=document.getElementById('variantFields');
const variantList=document.getElementById('variantList');
const editForm=document.querySelector('form.admin-card');
editForm.addEventListener('submit',()=>{
  variantList.querySelectorAll('.variant-row').forEach((row,index)=>{
    const file=row.querySelector('input[type="file"][name^="variant_image"]');
    if(file)file.name=`variant_image[${index}]`;
  });
});
function bindRemove(row){row.querySelector('.remove-variant').onclick=()=>row.remove();}
function bindImagePreview(row){
  const fileInput=row.querySelector('input[type="file"][name="variant_image[]"]');
  const img=row.querySelector('.variant-photo');
  if(!fileInput||!img)return;
  fileInput.addEventListener('change',()=>{
    const file=fileInput.files[0];
    if(!file)return;
    img.src=URL.createObjectURL(file);
  });
}
const singleOptions=<?= json_encode($availableSingles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function addVariant(product=null){const row=document.createElement('div');row.className='variant-row'+(product?' variant-existing':'');row.innerHTML=product?`<input name="variant_name[]" placeholder="åˆ†ç±»åç§°" required value="${product.name}"><input type="hidden" name="source_product_id[]" value="${product.id}"><input type="hidden" name="variant_sku[]" value=""><input type="hidden" name="variant_price[]" value=""><input type="hidden" name="variant_stock[]" value=""><input type="hidden" name="existing_variant_image[]" value=""><input type="file" name="variant_image[]" hidden><img class="variant-photo" src="/yummy-diary/images/soldout.png" alt="åˆ†ç±»å›¾ç‰‡é¢„è§ˆ" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';"><div><strong>${product.sku}</strong><br><span>RM ${product.price} Â· åº“å­˜ ${product.stock}</span></div><button type="button" class="remove-variant">åˆ é™¤</button>`:`<input name="variant_name[]" placeholder="åˆ†ç±»åç§°" required><input type="hidden" name="source_product_id[]" value="0"><input name="variant_sku[]" placeholder="SKU" required><input type="number" step=".01" min="0" name="variant_price[]" placeholder="ä»·æ ¼ RM" required><input type="number" min="0" name="variant_stock[]" placeholder="åº“å­˜" required><input type="hidden" name="existing_variant_image[]" value=""><img class="variant-photo" src="/yummy-diary/images/soldout.png" alt="åˆ†ç±»å›¾ç‰‡é¢„è§ˆ" onerror="this.onerror=null;this.src='/yummy-diary/images/soldout.png';"><input type="file" name="variant_image[]" accept="image/jpeg,image/png,image/gif,image/webp"><button type="button" class="remove-variant">åˆ é™¤</button>`;bindRemove(row);bindImagePreview(row);variantList.appendChild(row);}
document.querySelectorAll('.variant-row').forEach(row=>{bindRemove(row);bindImagePreview(row);});
document.getElementById('addVariant').onclick=()=>addVariant();
const picker=document.getElementById('productPicker');
document.getElementById('useExisting').onclick=()=>picker.classList.add('show');
picker.querySelector('.picker-close').onclick=()=>picker.classList.remove('show');
picker.addEventListener('click',e=>{if(e.target===picker)picker.classList.remove('show')});
picker.querySelectorAll('.picker-product').forEach(card=>card.onclick=()=>{addVariant({id:card.dataset.id,name:card.dataset.name,sku:card.dataset.sku,price:card.dataset.price,stock:card.dataset.stock});const row=variantList.lastElementChild;row.classList.remove('variant-existing');row.querySelector('[name="variant_sku[]"]').type='text';row.querySelector('[name="variant_sku[]"]').value=card.dataset.sku;const priceInput=row.querySelector('[name="variant_price[]"]');priceInput.type='number';priceInput.step='0.01';priceInput.min='0';priceInput.value=card.dataset.price;row.querySelector('[name="variant_stock[]"]').type='number';row.querySelector('[name="variant_stock[]"]').value=card.dataset.stock;row.querySelector('[name="existing_variant_image[]"]').value=card.dataset.image;const image=row.querySelector('.variant-photo');image.src='/yummy-diary/'+card.dataset.image;const file=row.querySelector('[name="variant_image[]"]');file.hidden=false;file.accept='image/jpeg,image/png,image/gif,image/webp';bindImagePreview(row);picker.classList.remove('show')});
document.getElementById('pickerSearch').oninput=e=>{const q=e.target.value.toLowerCase();picker.querySelectorAll('.picker-product').forEach(card=>card.hidden=!card.dataset.search.includes(q))};
document.querySelectorAll('[name=product_type]').forEach(radio=>{
  radio.addEventListener('change',()=>{const grouped=radio.value==='grouped'&&radio.checked;variantFields.classList.toggle('show',grouped);document.querySelectorAll('.single-only').forEach(el=>el.style.display=grouped?'none':'flex');document.querySelectorAll('.single-only input').forEach(el=>el.required=!grouped);if(grouped&&!variantList.children.length)addVariant();});
});
if(document.querySelector('[name=product_type]:checked').value==='grouped'){document.querySelectorAll('.single-only').forEach(el=>el.style.display='none');document.querySelectorAll('.single-only input').forEach(el=>el.required=false);}

