<?php
session_start();
require __DIR__ . '/../config.php';

// ✅ detect 从收据页回首页
// ✅ 手动清理
if (isset($_GET['clear']) && $_GET['clear'] == '1') {
    unset($_SESSION['cart']);
    unset($_SESSION['orders']);
    unset($_SESSION['pending_order']);
}

// ✅ 访客检测
if (!isset($_COOKIE['visitor_token'])) {
    $token = bin2hex(random_bytes(16));
    setcookie('visitor_token', $token, time() + (86400 * 30), "/");
    $stmt = $pdo->prepare("INSERT IGNORE INTO visitors (visitor_token) VALUES (?)");
    $stmt->execute([$token]);
} else {
    $token = $_COOKIE['visitor_token'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO visitors (visitor_token) VALUES (?)");
    $stmt->execute([$token]);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Yummy Diary</title>

  <link rel="stylesheet" href="/yummy-diary/css/style.css">
  <meta name="description" content="Yummy Diary - 精选零食、辣条、糖果、文创小物，让生活更有滋味。">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

  <style>
    #loader {
      position: fixed;top: 0;left: 0;width: 100vw;height: 100vh;
      background: #fff;display: flex;justify-content: center;align-items: center;
      z-index: 2000;transition: opacity 0.6s ease, visibility 0.6s ease;
    }
    #loader.fade-out {opacity: 0;visibility: hidden;}
    #loader img {width: 150px;animation: wag 1.4s infinite ease-in-out;}
    @keyframes wag {
      0%,100%{transform:rotate(0deg);}
      25%{transform:rotate(2deg);}
      75%{transform:rotate(-2deg);}
    }
    #content {opacity: 0;transition: opacity 1s ease;}
    #content.show {opacity: 1;}

    /* 卡片展示区 */
    .info-cards {
      display: flex;flex-direction: column;gap: 30px;
      margin: 40px auto;max-width: 1000px;padding: 0 15px;
    }
    .card {
      display: flex;align-items: center;gap: 20px;
      background: #fff;border-radius: 12px;
      box-shadow: 0 3px 12px rgba(0,0,0,0.08);padding: 20px;
    }
    .card.reverse {flex-direction: row-reverse;}
    .card-img img {
      max-width: 280px;   /* 桌面端图片最大 280 */
      width: 100%;
      border-radius: 10px;
    }
    .card-text {flex: 1;}
    .card-text h3 {margin: 0 0 10px;font-size: 1.4rem;color: #333;}
    .card-text p {
      margin: 0 0 10px;font-size: 1rem;line-height: 1.6;
      color: #555;white-space: pre-line;
    }

    /* ===== 手机端优化 (768 以下) ===== */
    @media (max-width: 768px) {
      .card, .card.reverse {
        flex-direction: column;   /* 手机端上下排列 */
        text-align: center;
      }
      .card-img {
        display: flex;
        justify-content: center;
        margin-bottom: 15px;
      }
      .card-img img {
        max-width: 150px;   /* 手机端固定最大 300 */
        width: 100%;
        height: auto;
        margin: 0 auto;
      }
      .card-text h3 {
        font-size: 1.2rem; 
        display: inline-block;
        border-bottom: 2px solid #000;
        padding-bottom: 3px;

      }
      .card-text p {
        font-size: 0.95rem; /* 手机端段落文字缩小一点 */
        line-height: 1.5;
      }
    strong{
        font-weight:800;
        color: #111;
        text-shadow: 0.5px 0.5px #ccc;
    }
  </style>
</head>
<body>

  <div id="loader">
    <img src="/yummy-diary/images/loading-dog.png" alt="Loading...">
  </div>

  <div id="content">
    <?php include __DIR__ . '/hardware/header.php'; ?>

    <!-- 欢迎区 -->
    <section class="hero">
      <div class="hero-img" data-aos="fade-right">
        <img src="/yummy-diary/images/33" alt="Yummy Diary Dog">
      </div>
      <div class="hero-text" data-aos="fade-left">
        <h1>
          <strong>Welcome to <span>Yummy Diary</strong></span>
        </h1>
        <p>
          一口零食，一件小物 <br>
          陪你收集每一份小确幸

        </p>
        <button class="btn" onclick="location.href=<?= htmlspecialchars(json_encode(appUrl('shop')), ENT_QUOTES) ?>"><strong>进入商店 Shop Now</strong></button>
      </div>
    </section>

    <!-- 三张卡片 -->
    <section class="info-cards">
      <!-- 第一张 -->
      <div class="card" data-aos="fade-up">
        <div class="card-img">
          <img src="/yummy-diary/images/34" alt="零食小店">
        </div>
        <div class="card-text">
          <p><strong>🚚 运费大折扣</strong><br>
          <p><strong>套餐1</strong> </p>

运费只需 <strong>Rm1.90</strong>（满 RM27.90)<br>
<p>🎁 送：1 包迷你魔芋爽 + 1 个萌萌小狗挂件</p><br>


<p><strong> 套餐2</strong></p> 
运费立减 <strong>Rm3.50</strong>（满 RM18.90)
<p>🎁 送：1 包迷你魔芋爽


          </p>
        </div>
      </div>

      <!-- 第二张 -->
      <div class="card reverse" data-aos="fade-up" data-aos-delay="200">
        <div class="card-img">
          <!-- 小猫图放这里 -->
          <img src="/yummy-diary/images/35" alt="超值优惠小猫">
        </div>
        <div class="card-text">
          <strong> 欢迎光临—Yummydiary 零食小店</strong> 
          <p>
这里是属于你的美味小角落🍬

我们希望每一位来到这里的人
都能在忙碌的日常里, 找到属于自己的幸福小瞬间

每一款精选零食, 
都是我们用心挑选的小惊喜, 
想带给你一份放松、多些快乐, 
也为生活添上一点点仪式感与温暖

喜欢的话, 记得关注我们的 IG
<p>
  喜欢的话，记得关注我们的 IG 
  <a href="https://www.instagram.com/yummydiaryy_?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank">
    👉<strong>@yummydiaryy_</strong>
          </a>
          </p>
        </div>
      </div>

      <!-- 第三张 -->
      <div class="card" data-aos="fade-up" data-aos-delay="400">
        <div class="card-img">
          <img src="/yummy-diary/images/36" alt="运费大优惠">
        </div>
        <div class="card-text">
          <strong> 📌 超值优惠来啦！</strong>
          <p>
<strong>🏷️超低价格</strong><br>
我们坚持 <strong> 比热门店更低</strong> 的好价格！<br>
让每一口美味都买得放心又划算<br><br>

🚚 <strong> 运费宠粉</strong>

原价 RM7.50 ➝ 现在只需 <b><strong>RM1.90 </strong></b><br>
小提醒: 单笔满 <strong>Rm27.90</strong> 就能享受啦<br>

🎁 <strong>暖心小礼物</strong><br>
每一份订单 我们都会送 <strong>1-2份小礼物</strong><br>
希望你拆包裹的那一刻
能多一份惊喜, 也多一份被惦记的温暖
          </p>
        </div>
      </div>
    </section>

    <?php include __DIR__ . '/hardware/footer.php'; ?>
  </div>

  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 800, offset: 100, once: true });
    window.addEventListener("load", () => {
      const loader = document.getElementById("loader");
      const content = document.getElementById("content");
      setTimeout(() => {
        loader.classList.add("fade-out");
        setTimeout(() => {
          loader.style.display = "none";
          content.classList.add("show");
        }, 600);
      }, 3000);
    });
  </script>

</body>
</html>
