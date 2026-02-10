<?php
session_start();

// ====================
// 访问密钥（你自己改成复杂字符串）
// ====================
$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";

// 检查 URL 是否带 key
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    die("❌ 未授权访问");
}

// ====================
// 默认账号
// ====================
$default_username = "admin";
$password_file = __DIR__ . "/admin_password.txt";

// 如果文件存在，就读取里面的密码，否则默认是 "admin"
if (file_exists($password_file)) {
    $default_password = trim(file_get_contents($password_file));
} else {
    $default_password = "admin"; // 默认密码
}

$error = '';
$success = '';

// ====================
// 登录逻辑
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === $default_username && $password === $default_password) {
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = $default_username;
        header("Location: dashboard.php?key=$secret_key");
        exit;
    } else {
        $error = "❌ 用户名或密码错误";
    }
}

// ====================
// 修改密码逻辑
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if ($old_password === $default_password) {
        file_put_contents($password_file, $new_password); // 保存到文件
        $success = "✅ 密码修改成功，新密码已生效";
        $default_password = $new_password; // 更新内存里的密码
    } else {
        $error = "❌ 原密码错误，无法修改";
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- 移动端适配关键 -->
    <title>后台登录 - Yummy Diary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 10px;
            color: #000;
        }
        .login-box {
            background: #fff;
            padding: 30px 20px;
            border-radius: 12px;
            box-shadow: 0 0 12px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 360px;
            text-align: center;
            border: 1px solid #000;
        }
        .login-box h2 {
            margin-bottom: 20px;
            font-size: 20px;
            color: #000;
        }
        .login-box h3 {
            margin-top: 20px;
            font-size: 16px;
            color: #333;
        }
        .login-box input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #000;
            border-radius: 6px;
            font-size: 15px;
            background: #fff;
            color: #000;
        }
        .login-box button {
            width: 100%;
            padding: 12px;
            background: #000;
            border: none;
            color: white;
            font-size: 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
        }
        .login-box button:hover {
            background: #333;
        }
        .error {
            color: #c00;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .success {
            color: #060;
            margin-bottom: 15px;
            font-size: 14px;
        }
        hr {
            border: none;
            border-top: 1px solid #ccc;
            margin: 20px 0;
        }

        /* 📱 手机端优化 */
        @media (max-width: 480px) {
            .login-box {
                padding: 20px 15px;
                border-radius: 8px;
            }
            .login-box h2 {
                font-size: 18px;
            }
            .login-box input, .login-box button {
                font-size: 14px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Yummy Diary 后台登录</h2>

        <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

        <!-- 登录表单 -->
        <form method="post">
            <input type="hidden" name="login" value="1">
            <input type="text" name="username" placeholder="用户名" required>
            <input type="password" name="password" placeholder="密码" required>
            <button type="submit">登录</button>
        </form>

        <hr>
        <h3>修改密码</h3>
        <!-- 修改密码表单 -->
        <form method="post">
            <input type="hidden" name="change_password" value="1">
            <input type="password" name="old_password" placeholder="原密码" required>
            <input type="password" name="new_password" placeholder="新密码" required>
            <button type="submit">修改密码</button>
        </form>
    </div>
</body>
</html>



