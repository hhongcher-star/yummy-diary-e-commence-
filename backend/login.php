<?php
session_start();

require __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash
         FROM admins
         WHERE username = ?
         LIMIT 1"
    );

    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];

        header("Location: dashboard.php");
        exit;
    }

    $error = "❌ 用户名或密码错误";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>后台登录 - Yummy Diary</title>

<style>
:root{
    --bg:#fbf6ef;
    --card:#fffdf9;
    --cream:#f4e6d3;
    --cream-2:#ead6bd;
    --cream-3:#c9a984;
    --text:#3b2a20;
    --muted:#8b7a6b;
    --line:#eadfd3;
    --danger:#d64545;
    --shadow:0 24px 60px rgba(120,90,60,.16);
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    font-family:"Segoe UI",Arial,sans-serif;
    background:
        radial-gradient(circle at top left, #fff4e4 0, transparent 34%),
        radial-gradient(circle at bottom right, #f1dfc9 0, transparent 36%),
        linear-gradient(135deg,#fffaf5 0%,#f8eee2 100%);
    color:var(--text);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:22px;
}

.login-page{
    width:100%;
    max-width:980px;
    display:grid;
    grid-template-columns:1.05fr .95fr;
    background:rgba(255,255,255,.58);
    border:1px solid rgba(234,223,211,.9);
    border-radius:34px;
    overflow:hidden;
    box-shadow:var(--shadow);
    backdrop-filter:blur(16px);
}

.login-brand{
    padding:46px;
    background:
        linear-gradient(135deg,rgba(255,250,244,.96),rgba(239,222,202,.88));
    position:relative;
    overflow:hidden;
}

.login-brand::before{
    content:"";
    position:absolute;
    width:260px;
    height:260px;
    border-radius:50%;
    right:-90px;
    top:-80px;
    background:rgba(255,255,255,.55);
}

.login-brand::after{
    content:"";
    position:absolute;
    width:160px;
    height:160px;
    border-radius:50%;
    left:-60px;
    bottom:-50px;
    background:rgba(201,169,132,.16);
}

.logo-badge{
    width:74px;
    height:74px;
    border-radius:26px;
    background:#ead6bd;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:34px;
    box-shadow:0 16px 34px rgba(120,90,60,.14);
    position:relative;
    z-index:1;
}

.login-brand h1{
    margin:28px 0 12px;
    font-size:38px;
    line-height:1.15;
    letter-spacing:-.8px;
    position:relative;
    z-index:1;
}

.login-brand p{
    margin:0;
    max-width:390px;
    color:var(--muted);
    line-height:1.8;
    font-size:15px;
    position:relative;
    z-index:1;
}

.brand-pills{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:28px;
    position:relative;
    z-index:1;
}

.brand-pills span{
    padding:9px 13px;
    border-radius:999px;
    background:rgba(255,255,255,.7);
    border:1px solid var(--line);
    color:#7c6147;
    font-size:13px;
    font-weight:800;
}

.login-panel{
    padding:46px;
    background:rgba(255,255,255,.84);
}

.login-box{
    width:100%;
    max-width:360px;
    margin:0 auto;
}

.login-box h2{
    margin:0 0 8px;
    font-size:28px;
    color:var(--text);
}

.login-box .subtitle{
    margin:0 0 26px;
    color:var(--muted);
    font-size:14px;
    line-height:1.7;
}

.form-group{
    margin-bottom:14px;
    text-align:left;
}

.form-group label{
    display:block;
    margin-bottom:7px;
    color:var(--muted);
    font-size:13px;
    font-weight:800;
}

.form-group input{
    width:100%;
    border:1px solid var(--line);
    background:#fff;
    border-radius:17px;
    padding:14px 15px;
    font-size:15px;
    outline:none;
    color:var(--text);
    transition:.2s;
}

.form-group input:focus{
    border-color:var(--cream-3);
    box-shadow:0 0 0 4px rgba(201,169,132,.16);
}

.login-btn{
    width:100%;
    border:none;
    border-radius:18px;
    padding:14px 18px;
    margin-top:8px;
    background:linear-gradient(135deg,#f7eadc,#d8bfa4);
    color:#3a2a20;
    font-size:15px;
    font-weight:900;
    cursor:pointer;
    transition:.2s;
    box-shadow:0 14px 28px rgba(120,90,60,.16);
}

.login-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 18px 34px rgba(120,90,60,.2);
}

.error{
    background:#fff4f4;
    border:1px solid #ffd5d5;
    color:var(--danger);
    padding:12px 14px;
    border-radius:17px;
    margin-bottom:16px;
    font-size:14px;
    font-weight:800;
    text-align:left;
}

.login-footer{
    margin-top:20px;
    color:var(--muted);
    font-size:12px;
    text-align:center;
}

@media(max-width:820px){
    .login-page{
        grid-template-columns:1fr;
        max-width:460px;
    }

    .login-brand{
        padding:34px;
    }

    .login-brand h1{
        font-size:30px;
    }

    .login-panel{
        padding:34px;
    }
}

@media(max-width:480px){
    body{
        padding:14px;
    }

    .login-page{
        border-radius:26px;
    }

    .login-brand,
    .login-panel{
        padding:26px 20px;
    }

    .login-brand h1{
        font-size:27px;
    }

    .login-box h2{
        font-size:24px;
    }
}
</style>
</head>

<body>

<div class="login-page">
    <section class="login-brand">
        <div class="logo-badge">🍪</div>

        <h1>Yummy Diary<br>Admin Panel</h1>

        <p>
            管理商品、库存、订单和热销展示。
            登录后台后可以查看销售数据并维护店铺内容。
        </p>

        <div class="brand-pills">
            <span>商品管理</span>
            <span>库存追踪</span>
            <span>订单记录</span>
            <span>热销排序</span>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-box">
            <h2>后台登录</h2>
            <p class="subtitle">请输入管理员账号和密码继续。</p>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text"
                           name="username"
                           placeholder="请输入用户名"
                           autocomplete="username"
                           required>
                </div>

                <div class="form-group">
                    <label>密码</label>
                    <input type="password"
                           name="password"
                           placeholder="请输入密码"
                           autocomplete="current-password"
                           required>
                </div>

                <button type="submit" class="login-btn">登录后台</button>
            </form>

            <div class="login-footer">
                © <?= date('Y') ?> Yummy Diary Admin
            </div>
        </div>
    </section>
</div>

</body>
</html>