<?php
session_start();
session_destroy();

// 确保清除 session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ====================
// 访问密钥
// ====================
$secret_key = "u7Xh29LmQpRa45ZtBnYvWc0JfKe8Gs1D";

// 强制跳转回登录页面（带上 key）
header("Location: login.php?key=$secret_key");
exit;
