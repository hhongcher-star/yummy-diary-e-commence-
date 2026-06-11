<?php
require __DIR__ . '/auth_admin.php';
require __DIR__ . '/../config.php';

date_default_timezone_set("Asia/Kuala_Lumpur");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>优惠管理</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<link rel="stylesheet" href="/yummy-diary/backend/css/admin_layout.css">

<style>
  .promo-summary{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:14px;
    margin-bottom:22px;
  }

  .summary-card{
    background:#fffaf4;
    border:1px solid var(--line);
    border-radius:22px;
    padding:18px;
    box-shadow:0 10px 28px rgba(120,90,60,.08);
  }

  .summary-card span{
    display:block;
    color:var(--muted);
    font-size:13px;
    font-weight:700;
    margin-bottom:6px;
  }

  .summary-card strong{
    display:block;
    font-size:26px;
    color:var(--text);
  }

  .summary-card small{
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:12px;
  }

  .promo-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:16px;
  }

  .promo-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    padding:22px;
    box-shadow:var(--shadow);
    position:relative;
    overflow:hidden;
  }

  .promo-card::before{
    content:"";
    position:absolute;
    right:-40px;
    top:-40px;
    width:130px;
    height:130px;
    border-radius:50%;
    background:#fff4e8;
  }

  .promo-icon{
    width:52px;
    height:52px;
    border-radius:18px;
    background:#ead6bd;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:24px;
    margin-bottom:14px;
    position:relative;
    z-index:1;
  }

  .promo-card h3{
    margin:0 0 8px;
    color:var(--text);
    font-size:18px;
    position:relative;
    z-index:1;
  }

  .promo-card p{
    margin:0 0 16px;
    color:var(--muted);
    line-height:1.6;
    font-size:14px;
    position:relative;
    z-index:1;
  }

  .promo-status{
    display:inline-flex;
    padding:7px 12px;
    border-radius:999px;
    background:#fffaf4;
    border:1px solid var(--line);
    color:#9b7656;
    font-size:13px;
    font-weight:800;
    position:relative;
    z-index:1;
  }

  .empty-promo{
    margin-top:18px;
    background:#fffaf4;
    border:1px dashed #d8bfa4;
    border-radius:26px;
    padding:42px 24px;
    text-align:center;
    color:var(--muted);
    box-shadow:var(--shadow);
  }

  .empty-promo .big-icon{
    width:78px;
    height:78px;
    margin:0 auto 18px;
    border-radius:26px;
    background:#ead6bd;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:34px;
  }

  .empty-promo h3{
    margin:0 0 8px;
    color:var(--text);
    font-size:22px;
  }

  .empty-promo p{
    margin:0 auto 18px;
    max-width:520px;
    line-height:1.7;
  }

  .promo-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    justify-content:center;
  }

  @media(max-width:768px){
    .promo-actions .btn{
      width:100%;
    }
  }
</style>
</head>

<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main>
  <section class="page-header">
    <div class="page-title">
      <h2>优惠管理</h2>
      <p>管理优惠活动、折扣券、满减优惠和客户促销方案</p>
    </div>
  </section>

  <section class="promo-summary">
    <div class="summary-card">
      <span>当前活动</span>
      <strong>0</strong>
      <small>暂无进行中的优惠活动</small>
    </div>

    <div class="summary-card">
      <span>优惠券</span>
      <strong>0</strong>
      <small>可用于未来折扣代码</small>
    </div>

    <div class="summary-card">
      <span>状态</span>
      <strong>待设置</strong>
      <small>Promotion module pending setup</small>
    </div>
  </section>

  <section class="promo-grid">
    <div class="promo-card">
      <div class="promo-icon">🎁</div>
      <h3>折扣优惠</h3>
      <p>适合设置百分比折扣，例如 10% OFF、20% OFF。</p>
      <span class="promo-status">Coming Soon</span>
    </div>

    <div class="promo-card">
      <div class="promo-icon">🧾</div>
      <h3>满减活动</h3>
      <p>适合设置满 RM50 减 RM5、满 RM100 减 RM10。</p>
      <span class="promo-status">Coming Soon</span>
    </div>

    <div class="promo-card">
      <div class="promo-icon">🔥</div>
      <h3>热销组合</h3>
      <p>适合把热销商品组合成促销套餐，提高客单价。</p>
      <span class="promo-status">Coming Soon</span>
    </div>
  </section>

  <section class="empty-promo">
    <div class="big-icon">📌</div>
    <h3>暂时没有优惠活动</h3>
    <p>
      之后可以在这里新增优惠券、设置折扣规则、限制使用次数，
      以及查看哪些优惠活动带来最多订单。
    </p>

    <div class="promo-actions">
      <button type="button" class="btn btn-edit" onclick="alert('优惠活动功能还未开启')">
        ➕ 新增优惠
      </button>

      <a href="products.php" class="btn btn-move">
        🍪 去商品管理
      </a>
    </div>
  </section>
</main>

</body>
</html>