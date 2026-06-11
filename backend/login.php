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
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .login-box input {
            width: 100%;
            box-sizing: border-box;
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

        @media (max-width: 480px) {
            .login-box {
                padding: 20px 15px;
                border-radius: 8px;
            }
            .login-box h2 {
                font-size: 18px;
            }
            .login-box input,
            .login-box button {
                font-size: 14px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Yummy Diary 后台登录</h2>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="text" name="username" placeholder="用户名" required>
            <input type="password" name="password" placeholder="密码" required>
            <button type="submit">登录</button>
        </form>
    </div>
</body>
</html>
