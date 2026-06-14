<?php
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

$stmt = $pdo->query("SELECT g.label group_label,c.category_key,c.name category_name
  FROM category_groups g JOIN product_categories c ON c.group_id=g.id
  WHERE g.status=1 AND c.status=1 ORDER BY g.sort_order,c.sort_order");
$categoryGroups = [];
foreach ($stmt->fetchAll() as $row) {
    $categoryGroups[$row['group_label']][$row['category_key']] = $row['category_name'];
}
$availableSingles = $pdo->query("SELECT p.id,p.sku,p.name,p.price,p.stock,p.image_url,p.parent_product_id,parent.name parent_name
  FROM products p LEFT JOIN products parent ON parent.id=p.parent_product_id
  WHERE p.product_type='single' ORDER BY p.name,p.sku")->fetchAll(PDO::FETCH_ASSOC);

function uploadProductImage(string $field, ?int $index = null): ?string {
    if ($index === null) {
        $error = $_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE;
        $tmp = $_FILES[$field]['tmp_name'] ?? '';
        $size = $_FILES[$field]['size'] ?? 0;
    } else {
        $error = $_FILES[$field]['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        $tmp = $_FILES[$field]['tmp_name'][$index] ?? '';
        $size = $_FILES[$field]['size'][$index] ?? 0;
    }
    if ($error !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $type = mime_content_type($tmp);
    if (!isset($allowed[$type]) || $size > 2 * 1024 * 1024) return null;
    $dir = __DIR__ . '/../frontend/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $name = uniqid('', true) . '.' . $allowed[$type];
    return move_uploaded_file($tmp, $dir . $name) ? 'frontend/uploads/' . $name : null;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $type = ($_POST['product_type'] ?? 'single') === 'grouped' ? 'grouped' : 'single';
    $price = (float)($_POST['price'] ?? 0);
    $stock = max(0, (int)($_POST['stock'] ?? 0));
    $category = trim($_POST['category'] ?? '');
    if ($type === 'grouped') {
        $firstSourceId = (int)($_POST['source_product_id'][0] ?? 0);
        if ($firstSourceId > 0) {
            $firstSourceStmt = $pdo->prepare("SELECT sku,price FROM products WHERE id=?");
            $firstSourceStmt->execute([$firstSourceId]);
            $firstSource = $firstSourceStmt->fetch();
            $sku = $firstSource['sku'] ?? '';
            $price = (float)($firstSource['price'] ?? 0);
        } else {
            $sku = trim($_POST['variant_sku'][0] ?? '');
            $price = (float)($_POST['variant_price'][0] ?? 0);
        }
        $stock = 0;
    }
    if ($sku === '' || $name === '' || $category === '' || $price < 0) {
        $error = '请填写完整商品资料。';
    } else {
        try {
            $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM products WHERE category=?');
            $sortStmt->execute([$category]);
            $pdo->beginTransaction();
            $insert = $pdo->prepare("INSERT INTO products
              (sku,name,product_type,variant_flavors,variant_sizes,price,stock,category,image_url,sort_order,is_hot,created_at)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $insert->execute([$sku,$name,$type,null,null,$price,$stock,$category,uploadProductImage('image'),$sortStmt->fetchColumn(),isset($_POST['is_hot'])?1:0]);
            $productId = (int)$pdo->lastInsertId();
            if ($type === 'grouped') {
                $variantNames = $_POST['variant_name'] ?? [];
                if (count($variantNames) === 0) {
                    throw new RuntimeException('分类商品至少需要一个分类项目。');
                }
                $variantStmt = $pdo->prepare('INSERT INTO product_variants
                  (product_id,source_product_id,variant_name,sku,price,stock,image_url,sort_order)
                  VALUES (?,?,?,?,?,?,?,?)');
                foreach ($variantNames as $index => $variantName) {
                    $variantName = trim($variantName);
                    $sourceId = (int)($_POST['source_product_id'][$index] ?? 0);
                    if ($variantName === '') {
                        throw new RuntimeException('分类项目名称不能为空。');
                    }
                    $source = null;
                    if ($sourceId > 0) {
                        $sourceStmt = $pdo->prepare("SELECT sku,price,stock,image_url FROM products WHERE id=? AND product_type='single'");
                        $sourceStmt->execute([$sourceId]);
                        $source = $sourceStmt->fetch();
                        if (!$source) {
                            throw new RuntimeException('分类项目来源商品不存在，请刷新页面后重试。');
                        }
                        $pdo->prepare('DELETE FROM product_variants WHERE source_product_id=?')->execute([$sourceId]);
                    }
                    $variantSku = trim($_POST['variant_sku'][$index] ?? '') ?: ($source['sku'] ?? '');
                    $variantPrice = isset($_POST['variant_price'][$index]) ? (float)$_POST['variant_price'][$index] : (float)($source['price'] ?? 0);
                    $variantStock = isset($_POST['variant_stock'][$index]) ? max(0, (int)$_POST['variant_stock'][$index]) : (int)($source['stock'] ?? 0);
                    $variantImage = uploadProductImage('variant_image', $index) ?: ($source['image_url'] ?? null);
                    if ($variantSku === '') {
                        throw new RuntimeException('分类项目 SKU 不能为空。');
                    }
                    $variantStmt->execute([
                        $productId,
                        $sourceId ?: null,
                        $variantName,
                        $variantSku,
                        $variantPrice,
                        $variantStock,
                        $variantImage,
                        $index + 1
                    ]);
                    if ($sourceId > 0) {
                        $pdo->prepare('UPDATE products SET name=?,sku=?,price=?,stock=?,image_url=?,parent_product_id=? WHERE id=?')
                            ->execute([$variantName,$variantSku,$variantPrice,$variantStock,$variantImage ?: null,$productId,$sourceId]);
                    }
                }
            }
            $pdo->commit();
            header('Location: products.php?cat=' . urlencode($category) . '&msg=' . urlencode('商品已添加'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = '新增失败：' . $e->getMessage();
        }
    }
}
?>
<!doctype html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>新增商品</title><link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">
<style>
.edit-layout{display:grid;grid-template-columns:360px 1fr;gap:22px;align-items:start}.preview-card{background:#fff;border:1px solid var(--line);border-radius:26px;padding:22px;box-shadow:var(--shadow);position:sticky;top:24px}.preview-image{aspect-ratio:1;border-radius:24px;background:#fff7f0;border:1px solid var(--line);display:grid;place-items:center;overflow:hidden}.preview-image img{width:100%;height:100%;object-fit:cover}.preview-card h3{font-size:22px}.preview-card p{color:var(--muted)}.preview-price{display:inline-flex;padding:9px 14px;border-radius:999px;background:#fffaf4;border:1px solid var(--line);font-weight:900}.form-section{margin-bottom:20px}.edit-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.form-field{display:flex;flex-direction:column;gap:7px}.form-field.full{grid-column:1/-1}.form-field label{font-size:13px;font-weight:800;color:var(--muted)}.type-choice{display:grid;grid-template-columns:1fr 1fr;gap:10px}.type-choice label{padding:16px;border:1px solid var(--line);border-radius:18px;background:#fffaf4;font-weight:800}.variant-fields{display:none;padding:22px;border:1px dashed #d8bfa4;border-radius:20px;background:#fffaf4}.variant-fields.show{display:block}.variant-head{display:flex;justify-content:space-between;align-items:center;gap:14px}.variant-actions{display:flex;gap:10px}.variant-row{display:grid;grid-template-columns:minmax(150px,.8fr) minmax(280px,2fr) auto;gap:14px;padding:16px;margin-top:12px;border:1px solid #f2bfd5;border-radius:18px;background:#fff;align-items:center}.variant-row input{min-width:0}.remove-variant{border:1px solid #ffb4c8;background:#fff;color:#d33;border-radius:12px;padding:8px 12px}.product-picker{display:none;position:fixed;inset:0;z-index:3000;background:rgba(30,24,22,.42);padding:34px}.product-picker.show{display:block}.picker-card{max-width:1180px;height:calc(100vh - 68px);margin:auto;background:#fff;border-radius:28px;padding:24px;box-sizing:border-box;display:flex;flex-direction:column}.picker-head{display:flex;justify-content:space-between;align-items:center}.picker-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px;overflow:auto;padding:18px 4px}.picker-product{display:grid;grid-template-columns:74px 1fr;gap:12px;text-align:left;border:1px solid var(--line);border-radius:18px;padding:12px;background:#fff}.picker-product img{width:74px;height:74px;object-fit:cover;border-radius:12px}.picker-product strong,.picker-product small{display:block}.picker-product small{color:var(--muted);margin-top:3px}.picker-close{width:42px;height:42px;border-radius:50%;border:1px solid var(--line);background:#fff;font-size:22px}.file-upload{padding:16px;border:1px dashed #d8bfa4;border-radius:20px;background:#fffaf4}.form-actions{display:flex;gap:10px;margin-top:20px}@media(max-width:900px){.edit-layout{grid-template-columns:1fr}.preview-card{position:static}}@media(max-width:600px){.edit-form-grid,.variant-row{grid-template-columns:1fr}.form-field.full{grid-column:auto}}
  .variant-photo{width:72px;height:72px;border-radius:14px;border:1px solid #ead8c8;object-fit:cover;}
</style></head><body><?php include __DIR__ . '/includes/sidebar.php'; ?><main>
<section class="page-header"><div class="page-title"><h2>新增商品</h2><p>建立单商品，或建立拥有多个独立分类项目的分类商品</p></div></section>
<?php if($error): ?><div class="msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="edit-layout"><aside class="preview-card"><div class="preview-image"><img id="previewImage" src="/yummy-diary/images/soldout.png"></div><h3 id="previewName">商品名称</h3><p id="previewSku">SKU：-</p><p id="previewStock">库存：0</p><span id="previewPrice" class="preview-price">RM 0.00</span></aside>
<form class="admin-card" method="post" enctype="multipart/form-data">
<div class="form-section"><h3>商品类型</h3><div class="type-choice"><label><input type="radio" name="product_type" value="single" checked> 单商品</label><label><input type="radio" name="product_type" value="grouped"> 分类商品</label></div></div>
<div class="form-section"><h3>基本资料</h3><div class="edit-form-grid">
<div class="form-field single-only"><label>SKU</label><input id="sku" name="sku" required></div><div class="form-field"><label>商品名称</label><input id="name" name="name" required></div>
<div class="form-field single-only"><label>价格</label><input id="price" type="number" step=".01" min="0" name="price" required></div><div class="form-field single-only"><label>库存</label><input id="stock" type="number" min="0" name="stock" required></div>
<div class="form-field full"><label>商城分类</label><select name="category" required><?php foreach($categoryGroups as $group=>$items): ?><optgroup label="<?= htmlspecialchars($group) ?>"><?php foreach($items as $key=>$label): ?><option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
</div></div>
<div id="variantFields" class="form-section variant-fields"><div class="variant-head"><div><h3>分类商品项目</h3><p>建立新分类名称，或选择现有商品归入此分类商品。</p></div><div class="variant-actions"><button type="button" id="addVariant" class="btn btn-edit">＋ 添加新分类</button><button type="button" id="useExisting" class="btn btn-move">使用现有商品</button></div></div><div id="variantList"></div></div>
<div class="form-section"><h3>商品图片</h3><div class="file-upload"><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></div></div>
<label><input type="checkbox" name="is_hot" value="1"> 设为热销商品</label>
<div class="form-actions"><button class="btn btn-edit" type="submit">保存商品</button><a class="btn btn-move" href="products.php">返回商品列表</a></div>
</form></div>
<div id="productPicker" class="product-picker"><div class="picker-card"><div class="picker-head"><div><h3>选择现有商品</h3><p>点击商品卡片即可加入，商品会从原来的分类商品移动到这里。</p></div><button type="button" class="picker-close">×</button></div><input id="pickerSearch" type="search" placeholder="搜索名称或 SKU"><div class="picker-grid"><?php foreach($availableSingles as $single): ?><button type="button" class="picker-product" data-search="<?= htmlspecialchars(strtolower($single['name'].' '.$single['sku'])) ?>" data-id="<?= (int)$single['id'] ?>" data-name="<?= htmlspecialchars($single['name'],ENT_QUOTES) ?>" data-sku="<?= htmlspecialchars($single['sku'],ENT_QUOTES) ?>" data-price="<?= htmlspecialchars($single['price'],ENT_QUOTES) ?>" data-stock="<?= (int)$single['stock'] ?>"><img src="/yummy-diary/<?= htmlspecialchars($single['image_url'] ?: 'images/soldout.png') ?>"><span><strong><?= htmlspecialchars($single['name']) ?></strong><small><?= htmlspecialchars($single['sku']) ?> · RM <?= number_format((float)$single['price'],2) ?> · 库存 <?= (int)$single['stock'] ?></small><?php if($single['parent_name']): ?><small>目前属于：<?= htmlspecialchars($single['parent_name']) ?></small><?php endif; ?></span></button><?php endforeach; ?></div></div></div>
</main><script>
const fields=document.getElementById('variantFields'),list=document.getElementById('variantList');
const addForm=document.querySelector('form.admin-card');
addForm.addEventListener('submit',()=>{
  list.querySelectorAll('.variant-row').forEach((row,index)=>{
    const file=row.querySelector('input[type="file"][name^="variant_image"]');
    if(file)file.name=`variant_image[${index}]`;
  });
});
const singleOptions=<?= json_encode($availableSingles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function bindVariantImage(row){const file=row.querySelector('input[type="file"][name="variant_image[]"]');const img=row.querySelector('.variant-photo');if(!file||!img)return;file.onchange=()=>{const f=file.files[0];if(f)img.src=URL.createObjectURL(f);};}
function addVariant(product=null){const row=document.createElement('div');row.className='variant-row'+(product?' variant-existing':'');row.innerHTML=product?`<input name="variant_name[]" placeholder="分类名称" required value="${product.name}"><input type="hidden" name="source_product_id[]" value="${product.id}"><input type="hidden" name="variant_sku[]" value=""><input type="hidden" name="variant_price[]" value=""><input type="hidden" name="variant_stock[]" value=""><input type="file" name="variant_image[]" hidden><div><strong>${product.sku}</strong><br><span>RM ${product.price} · 库存 ${product.stock}</span></div><button type="button" class="remove-variant">删除</button>`:`<input name="variant_name[]" placeholder="分类名称" required><input type="hidden" name="source_product_id[]" value=""><input name="variant_sku[]" placeholder="SKU" required><input type="number" step=".01" min="0" name="variant_price[]" placeholder="价格 RM" required><input type="number" min="0" name="variant_stock[]" placeholder="库存" required><img class="variant-photo" src="/yummy-diary/images/soldout.png">
<input type="file" name="variant_image[]" accept="image/jpeg,image/png,image/gif,image/webp"><button type="button" class="remove-variant">删除</button>`;row.querySelector('.remove-variant').onclick=()=>row.remove();bindVariantImage(row);list.appendChild(row)}
document.getElementById('addVariant').onclick=()=>addVariant();
const picker=document.getElementById('productPicker');document.getElementById('useExisting').onclick=()=>picker.classList.add('show');picker.querySelector('.picker-close').onclick=()=>picker.classList.remove('show');picker.addEventListener('click',e=>{if(e.target===picker)picker.classList.remove('show')});picker.querySelectorAll('.picker-product').forEach(card=>card.onclick=()=>{addVariant();const row=list.lastElementChild;row.querySelector('[name="variant_name[]"]').value=card.dataset.name;row.querySelector('[name="source_product_id[]"]').value=card.dataset.id;row.querySelector('[name="variant_sku[]"]').value=card.dataset.sku;row.querySelector('[name="variant_price[]"]').value=card.dataset.price;row.querySelector('[name="variant_stock[]"]').value=card.dataset.stock;row.querySelector('.variant-photo').src=card.querySelector('img').src;picker.classList.remove('show')});pickerSearch.oninput=e=>{const q=e.target.value.toLowerCase();picker.querySelectorAll('.picker-product').forEach(card=>card.hidden=!card.dataset.search.includes(q))};
document.querySelectorAll('[name=product_type]').forEach(r=>r.onchange=()=>{const grouped=r.value==='grouped'&&r.checked;fields.classList.toggle('show',grouped);document.querySelectorAll('.single-only').forEach(el=>el.style.display=grouped?'none':'flex');[sku,price,stock].forEach(el=>el.required=!grouped);if(grouped&&!list.children.length)addVariant()});
['sku','name','price','stock'].forEach(id=>document.getElementById(id).oninput=()=>{previewName.textContent=name.value||'商品名称';previewSku.textContent='SKU：'+(sku.value||'-');previewStock.textContent='库存：'+(stock.value||0);previewPrice.textContent='RM '+Number(price.value||0).toFixed(2)});
image.onchange=()=>{const f=image.files[0];if(f)previewImage.src=URL.createObjectURL(f)};
</script></body></html>
