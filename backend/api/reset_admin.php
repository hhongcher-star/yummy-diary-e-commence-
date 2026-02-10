<?php
require 'config.php'; // 引入数据库配置

$newPassword = "123456";
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

try {
    // 检查是否已有 admin 用户
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = 'admin'");
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // 更新已有用户密码
        $update = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = 'admin'");
        $update->execute([$hash]);
        echo "✅ 管理员密码已重置为 123456";
    } else {
        // 如果没有，就新建一个 admin 用户
        $insert = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES ('admin', ?)");
        $insert->execute([$hash]);
        echo "✅ 管理员账号已创建，用户名：admin，密码：123456";
    }
} catch (PDOException $e) {
    echo "❌ 出错了: " . $e->getMessage();
}
