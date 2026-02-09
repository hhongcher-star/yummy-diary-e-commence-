<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact - Yummy Diary</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .contact-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 80vh;
      padding: 40px;
      background: #fff;
      gap: 50px;
    }

    /* 左边猫咪 */
    .contact-left img {
      max-width: 280px;
      height: auto;
      animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    /* 右边文字区域 */
    .contact-right {
      border: 2px solid #000;
      padding: 30px;
      max-width: 400px;
      background: #fff;
      border-radius: 8px;
      font-family: "Courier New", monospace;
      opacity: 0;
      transform: translateY(20px);
      animation: fadeInUp 1s ease forwards;
      animation-delay: 0.5s;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .contact-right h2 {
      margin-bottom: 20px;
      font-size: 1.5rem;
      color: #000;
    }

    .contact-right p {
      margin-bottom: 10px;
      font-size: 1rem;
    }

    .contact-right a {
      display: block;
      margin: 12px 0;
      font-size: 1.1rem;
      color: #000;
      text-decoration: none;
      transition: transform 0.2s ease, color 0.2s ease;
    }

    .contact-right a:hover {
      text-decoration: underline;
      transform: scale(1.05);
      color: #444;
    }

    /* 📱 手机端优化 */
    @media (max-width: 768px) {
      .contact-wrapper {
        flex-direction: column;
        gap: 20px;
        padding: 20px;
        text-align: center;
      }

      .contact-left img {
        max-width: 200px;
      }

      .contact-right {
        width: 100%;
        max-width: 320px;
        padding: 20px;
        font-size: 0.95rem;
      }

      .contact-right h2 {
        font-size: 1.3rem;
      }

      .contact-right a {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>

  <?php include 'header.php'; ?>

  <section class="contact-wrapper">
    <!-- 左边猫咪插图 -->
    <div class="contact-left">
      <img src="images/43" alt="Cat Contact">
    </div>

    <!-- 右边联系方式 -->
    <div class="contact-right">
      <h2>联系我们 Contact</h2>
      <p>你可以通过以下方式找到我们：</p>

      <!-- 📧 Email -->
      <a href="mailto:zhiweichan1012@gmail.com">📧 Email: zhiweichan1012@gmail.com</a>

      <!-- 🛒 Instagram -->
      <a href="https://www.instagram.com/yummydiaryy_" target="_blank">📷 Instagram: @yummydiaryy_</a>

      <!-- 💬 WhatsApp -->
      <a href="https://wa.me/60106654042" target="_blank">💬 WhatsApp: +60 10 665 4042</a>
    </div>
  </section>

  <?php include 'footer.php'; ?>

</body>
</html>
